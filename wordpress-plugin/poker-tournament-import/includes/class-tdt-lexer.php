<?php
/**
 * TDT File Lexer (Tokenizer)
 *
 * Tokenizes Tournament Director (.tdt) file content into structured tokens
 * for parsing by the TDT Parser. Handles strings, numbers, identifiers, keywords,
 * and punctuation with proper escape sequence support.
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
 * Token class representing a single lexical unit
 *
 * @since 2.4.9
 */
class TDT_Token {
    /**
     * Token type (STRING, NUMBER, BOOL, IDENT, NEW, punctuation, etc.)
     * @var string
     */
    public $type;

    /**
     * Token value (actual string, number, or identifier)
     * @var mixed
     */
    public $value;

    /**
     * Position in source file
     * @var int
     */
    public $pos;

    /**
     * Constructor
     *
     * @param string $type Token type
     * @param mixed $value Token value
     * @param int $pos Position in source
     */
    public function __construct($type, $value, $pos) {
        $this->type = $type;
        $this->value = $value;
        $this->pos = $pos;
    }
}

/**
 * Lexer class for tokenizing .tdt file content
 *
 * Performs lexical analysis on Tournament Director file format,
 * converting raw text into structured tokens for parser consumption.
 *
 * @since 2.4.9
 */
class TDT_Lexer {
    /**
     * Source content
     * @var string
     */
    private $s;

    /**
     * Content length
     * @var int
     */
    private $len;

    /**
     * Current position
     * @var int
     */
    private $i = 0;

    /**
     * Constructor
     *
     * @param string $s Source content to tokenize
     */
    public function __construct($s) {
        $this->s = $s;
        $this->len = strlen($s);
    }

    /**
     * Tokenize entire content into array of tokens
     *
     * @return TDT_Token[] Array of tokens
     * @throws Exception If unexpected character encountered
     */
    public function tokens() {
        $out = array();

        while ($this->i < $this->len) {
            $this->skipWs();
            if ($this->i >= $this->len) break;

            $ch = $this->s[$this->i];

            // Punctuators: {}[]():,
            if (strpos('{}[]():,', $ch) !== false) {
                $out[] = new TDT_Token($ch, $ch, $this->i);
                $this->i++;
                continue;
            }

            // String literals
            if ($ch === '"') {
                $out[] = $this->readString();
                continue;
            }

            // Handle dot: could be decimal number (.5) or property access (Map.from)
            if ($ch === '.') {
                // Check if next character is a digit (decimal number like .5)
                if ($this->i + 1 < $this->len && ctype_digit($this->s[$this->i + 1])) {
                    $out[] = $this->readNumber();
                } else {
                    // Dot as punctuator (property access like Map.from)
                    $out[] = new TDT_Token('.', '.', $this->i);
                    $this->i++;
                }
                continue;
            }

            // Numbers (including negative, but NOT starting with dot)
            if (ctype_digit($ch) || $ch === '-') {
                $out[] = $this->readNumber();
                continue;
            }

            // Identifiers and keywords
            if (ctype_alpha($ch) || $ch === '_') {
                $tok = $this->readIdentOrKeyword();
                $out[] = $tok;
                continue;
            }

            throw new Exception(esc_html("Unexpected char '{$ch}' at position {$this->i}"));
        }

        $out[] = new TDT_Token('EOF', null, $this->i);
        return $out;
    }

    /**
     * Skip whitespace and comments
     */
    private function skipWs() {
        while ($this->i < $this->len) {
            $c = $this->s[$this->i];

            // Handle // comments
            if ($c === '/' && $this->i + 1 < $this->len && $this->s[$this->i + 1] === '/') {
                $this->i += 2;
                while ($this->i < $this->len && $this->s[$this->i] !== "\n") {
                    $this->i++;
                }
                continue;
            }

            // Handle /* */ comments
            if ($c === '/' && $this->i + 1 < $this->len && $this->s[$this->i + 1] === '*') {
                $this->i += 2;
                while ($this->i + 1 < $this->len &&
                       !($this->s[$this->i] === '*' && $this->s[$this->i + 1] === '/')) {
                    $this->i++;
                }
                $this->i += 2; // skip */
                continue;
            }

            // Skip whitespace
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $this->i++;
                continue;
            }

            break;
        }
    }

    /**
     * Read string literal with escape sequence support
     *
     * @return TDT_Token String token
     * @throws Exception If string is unterminated
     */
    private function readString() {
        $start = $this->i;
        $this->i++; // skip opening "
        $buf = '';

        while ($this->i < $this->len) {
            $c = $this->s[$this->i++];

            if ($c === '"') {
                break; // closing quote
            }

            if ($c === '\\') {
                if ($this->i >= $this->len) {
                    throw new Exception(esc_html("Unterminated escape at position {$this->i}"));
                }

                $esc = $this->s[$this->i++];
                switch ($esc) {
                    case '"':  $buf .= '"';  break;
                    case '\\': $buf .= '\\'; break;
                    case 'n':  $buf .= "\n"; break;
                    case 'r':  $buf .= "\r"; break;
                    case 't':  $buf .= "\t"; break;
                    default:   $buf .= '\\' . $esc; // be liberal
                }
            } else {
                $buf .= $c;
            }
        }

        return new TDT_Token('STRING', $buf, $start);
    }

    /**
     * Read number (integer or float)
     *
     * @return TDT_Token Number token
     */
    private function readNumber() {
        $start = $this->i;
        $hasDot = false;

        if ($this->s[$this->i] === '-') {
            $this->i++;
        }

        while ($this->i < $this->len) {
            $c = $this->s[$this->i];

            if ($c === '.') {
                if ($hasDot) break; // second dot = end of number
                $hasDot = true;
                $this->i++;
                continue;
            }

            if (!ctype_digit($c)) {
                break;
            }

            $this->i++;
        }

        $text = substr($this->s, $start, $this->i - $start);
        $val = $hasDot ? floatval($text) : intval($text);

        return new TDT_Token('NUMBER', $val, $start);
    }

    /**
     * Read identifier or keyword
     *
     * @return TDT_Token IDENT, NEW, or BOOL token (BOOL can be true/false/null)
     */
    private function readIdentOrKeyword() {
        $start = $this->i;

        while ($this->i < $this->len) {
            $c = $this->s[$this->i];
            if (!(ctype_alnum($c) || $c === '_')) {
                break;
            }
            $this->i++;
        }

        $text = substr($this->s, $start, $this->i - $start);

        // Check for keywords
        if ($text === 'new') {
            return new TDT_Token('NEW', $text, $start);
        }
        if ($text === 'true') {
            return new TDT_Token('BOOL', true, $start);
        }
        if ($text === 'false') {
            return new TDT_Token('BOOL', false, $start);
        }
        if ($text === 'null') {
            return new TDT_Token('BOOL', null, $start);
        }

        return new TDT_Token('IDENT', $text, $start);
    }
}
