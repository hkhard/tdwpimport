<?php
/**
 * TDT File AST Parser
 *
 * Recursive-descent parser that converts tokens into Abstract Syntax Tree (AST).
 * Properly handles arbitrarily nested structures using recursion instead of regex.
 *
 * @package Poker_Tournament_Import
 * @subpackage Parsers
 * @since 2.4.9
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AST node helper class
 *
 * Provides factory methods for creating typed AST nodes.
 *
 * @since 2.4.9
 */
class TDT_AST {
    /**
     * Create Object node
     *
     * @param array $entries Associative array of key => value pairs
     * @return array Object AST node
     */
    public static function obj($entries) {
        return array(
            '_type' => 'Object',
            'entries' => $entries
        );
    }

    /**
     * Create Array node
     *
     * @param array $items Array of values
     * @return array Array AST node
     */
    public static function arr($items) {
        return array(
            '_type' => 'Array',
            'items' => $items
        );
    }

    /**
     * Create String node
     *
     * @param string $v String value
     * @return array String AST node
     */
    public static function str($v) {
        return array(
            '_type' => 'String',
            'value' => $v
        );
    }

    /**
     * Create Number node
     *
     * @param int|float $v Numeric value
     * @return array Number AST node
     */
    public static function num($v) {
        return array(
            '_type' => 'Number',
            'value' => $v
        );
    }

    /**
     * Create Boolean node
     *
     * @param bool $v Boolean value
     * @return array Boolean AST node
     */
    public static function bool($v) {
        return array(
            '_type' => 'Boolean',
            'value' => $v
        );
    }

    /**
     * Create New constructor node
     *
     * @param string $ctor Constructor name (e.g., "GamePlayer", "GameBuyin")
     * @param array $arg Argument (Object or other node)
     * @return array New AST node
     */
    public static function new($ctor, $arg) {
        return array(
            '_type' => 'New',
            'ctor' => $ctor,
            'arg' => $arg
        );
    }

    /**
     * Create Call expression node (for method calls like Map.from(...))
     *
     * @param string $object Object name (e.g., "Map")
     * @param string $method Method name (e.g., "from")
     * @param array $arg Method argument
     * @return array Call AST node
     */
    public static function call($object, $method, $arg) {
        return array(
            '_type' => 'Call',
            'object' => $object,
            'method' => $method,
            'arg' => $arg
        );
    }
}

/**
 * Recursive-descent parser for .tdt files
 *
 * Parses token stream from TDT_Lexer into Abstract Syntax Tree.
 * Uses recursive descent to handle arbitrarily nested structures correctly.
 *
 * @since 2.4.9
 */
class TDT_Parser {
    /**
     * Token array
     * @var TDT_Token[]
     */
    private $toks;

    /**
     * Current position in token array
     * @var int
     */
    private $pos = 0;

    /**
     * Total token count
     * @var int
     */
    private $len = 0;

    /**
     * Constructor
     *
     * @param string $text Source .tdt file content
     * @throws Exception If lexer encounters unexpected characters
     */
    public function __construct($text) {
        $lexer = new TDT_Lexer($text);
        $this->toks = $lexer->tokens();
        $this->len = count($this->toks);
    }

    /**
     * Parse entire document (root object)
     *
     * @return array Root AST node (Object)
     * @throws Exception If parsing fails
     */
    public function parseDocument() {
        // The root is an Object {...}
        $node = $this->parseObject();
        $this->expect('EOF');
        return $node;
    }

    /**
     * Parse Object: { key: value, key: value, ... }
     *
     * @return array Object AST node
     * @throws Exception If parsing fails
     */
    private function parseObject() {
        $this->expect('{');
        $entries = array();

        if (!$this->peekIs('}')) {
            do {
                $key = $this->parseKey();
                $this->expect(':');
                $val = $this->parseValue();
                $entries[$key] = $val;
            } while ($this->accept(','));
        }

        $this->expect('}');
        return TDT_AST::obj($entries);
    }

    /**
     * Parse object key (identifier or string)
     *
     * @return string Key name
     * @throws Exception If invalid key encountered
     */
    private function parseKey() {
        $t = $this->peek();

        if ($t->type === 'IDENT') {
            $this->pos++;
            return $t->value;
        }

        if ($t->type === 'STRING') {
            $this->pos++;
            return $t->value;
        }

        throw new Exception(esc_html("Invalid key at position {$t->pos}"));
    }

    /**
     * Parse Array: [ value, value, ... ]
     *
     * @return array Array AST node
     * @throws Exception If parsing fails
     */
    private function parseArray() {
        $this->expect('[');
        $items = array();

        if (!$this->peekIs(']')) {
            do {
                $items[] = $this->parseValue();
            } while ($this->accept(','));
        }

        $this->expect(']');
        return TDT_AST::arr($items);
    }

    /**
     * Parse value (string, number, bool, object, array, or new constructor)
     *
     * @return array AST node for the value
     * @throws Exception If unexpected token encountered
     */
    private function parseValue() {
        $t = $this->peek();

        switch ($t->type) {
            case 'STRING':
                $this->pos++;
                return TDT_AST::str($t->value);

            case 'NUMBER':
                $this->pos++;
                return TDT_AST::num($t->value);

            case 'BOOL':
                $this->pos++;
                return TDT_AST::bool($t->value);

            case '{':
                return $this->parseObject();

            case '[':
                return $this->parseArray();

            case 'NEW':
                return $this->parseNew();

            case 'IDENT':
                // Check if this is a method call like Map.from(...)
                $ident = $t->value;
                $this->pos++;

                // Check for dot (property access)
                if ($this->peekIs('.')) {
                    $this->pos++; // consume dot
                    $method = $this->expect('IDENT')->value;
                    $this->expect('(');
                    $arg = $this->parseValue();
                    $this->expect(')');
                    return TDT_AST::call($ident, $method, $arg);
                }

                // Just an identifier (shouldn't happen in valid .tdt)
                throw new Exception(esc_html("Unexpected identifier '{$ident}' at position {$t->pos}"));

            default:
                throw new Exception(esc_html("Unexpected token {$t->type} at position {$t->pos}"));
        }
    }

    /**
     * Parse New constructor: new ConstructorName(argument) or new Namespace.ClassName(argument)
     *
     * @return array New AST node
     * @throws Exception If parsing fails
     */
    private function parseNew() {
        $this->expect('NEW');
        $ctor = $this->expect('IDENT')->value;

        // Check for namespaced constructor (e.g., LO.OverlayPropSet)
        if ($this->peekIs('.')) {
            $this->pos++; // consume dot
            $className = $this->expect('IDENT')->value;
            $ctor = $ctor . '.' . $className;
        }

        $this->expect('(');
        $arg = $this->parseValue();
        $this->expect(')');
        return TDT_AST::new($ctor, $arg);
    }

    /**
     * Peek at current token without consuming
     *
     * @return TDT_Token Current token
     */
    private function peek() {
        return $this->toks[$this->pos];
    }

    /**
     * Check if current token matches type
     *
     * @param string $type Token type to check
     * @return bool True if matches
     */
    private function peekIs($type) {
        return $this->toks[$this->pos]->type === $type;
    }

    /**
     * Try to accept token of given type
     *
     * @param string $type Token type to accept
     * @return bool True if accepted and consumed, false otherwise
     */
    private function accept($type) {
        if ($this->peekIs($type)) {
            $this->pos++;
            return true;
        }
        return false;
    }

    /**
     * Expect token of given type (throws if not found)
     *
     * @param string $type Expected token type
     * @return TDT_Token The consumed token
     * @throws Exception If token doesn't match expected type
     */
    private function expect($type) {
        $t = $this->peek();

        if ($t->type !== $type) {
            throw new Exception(esc_html("Expected {$type}, got {$t->type} at position {$t->pos}"));
        }

        $this->pos++;
        return $t;
    }
}
