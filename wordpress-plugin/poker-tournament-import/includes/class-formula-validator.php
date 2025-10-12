<?php
/**
 * Formula Validator Class
 *
 * Handles Tournament Director formula validation, parsing, and calculation
 * with support for customizable formulas and validation against TD specification
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Formula_Validator {

    /**
     * Available Tournament Director variables and functions
     */
    private $td_variables = array(
        'n' => array('type' => 'int', 'description' => 'Total number of players'),
        'r' => array('type' => 'int', 'description' => 'Player finish position'),
        'buyins' => array('type' => 'int', 'description' => 'Total number of buy-ins'),
        'rebuys' => array('type' => 'int', 'description' => 'Total number of rebuys'),
        'addons' => array('type' => 'int', 'description' => 'Total number of add-ons'),
        'monies' => array('type' => 'decimal', 'description' => 'Total money in pot'),
        'avgBC' => array('type' => 'decimal', 'description' => 'Average buy-in cost'),
        'numberofHits' => array('type' => 'int', 'description' => 'Number of eliminations'),
        'place' => array('type' => 'int', 'description' => 'Player finish position (alias for r)'),
        'entrants' => array('type' => 'int', 'description' => 'Total entrants (alias for n)'),
        'T33' => array('type' => 'int', 'description' => 'Round(n/3) - Top third cutoff'),
        'T80' => array('type' => 'int', 'description' => 'Floor(n*0.9) - 80% cutoff'),
        'points' => array('type' => 'decimal', 'description' => 'Calculated points'),
        'temp' => array('type' => 'decimal', 'description' => 'Temporary variable'),
        'winnings' => array('type' => 'decimal', 'description' => 'Player winnings'),
        'prizePool' => array('type' => 'decimal', 'description' => 'Total prize pool'),
        'buyinAmount' => array('type' => 'decimal', 'description' => 'Buy-in amount'),
        'feeAmount' => array('type' => 'decimal', 'description' => 'Fee amount'),
        'totalBuyInsAmount' => array('type' => 'decimal', 'description' => 'Total buy-ins amount'),
        'totalRebuysAmount' => array('type' => 'decimal', 'description' => 'Total rebuys amount'),
        'totalAddOnsAmount' => array('type' => 'decimal', 'description' => 'Total add-ons amount'),
    );

    /**
     * Available Tournament Director functions
     */
    private $td_functions = array(
        'abs' => array('params' => 1, 'description' => 'Absolute value'),
        'sqrt' => array('params' => 1, 'description' => 'Square root'),
        'log' => array('params' => 1, 'description' => 'Natural logarithm'),
        'log10' => array('params' => 1, 'description' => 'Base 10 logarithm'),
        'pow' => array('params' => 2, 'description' => 'Power function'),
        'round' => array('params' => 1, 'description' => 'Round to nearest integer'),
        'floor' => array('params' => 1, 'description' => 'Round down to integer'),
        'ceil' => array('params' => 1, 'description' => 'Round up to integer'),
        'min' => array('params' => 2, 'description' => 'Minimum of two values'),
        'max' => array('params' => 2, 'description' => 'Maximum of two values'),
        'if' => array('params' => 3, 'description' => 'Conditional: if(condition, true_value, false_value)'),
        'and' => array('params' => 2, 'description' => 'Logical AND'),
        'or' => array('params' => 2, 'description' => 'Logical OR'),
        'not' => array('params' => 1, 'description' => 'Logical NOT'),
    );

    /**
     * Default Tournament Director formulas
     */
    private $default_formulas = array(
        'tournament_points' => array(
            'name' => 'Tournament Points (Default TD Formula)',
            'description' => 'Standard Tournament Director points calculation with position-based scaling',
            'formula' => 'assign("points", if(T80 > r and T33 < r, round(temp * pow(0.66, (r-T33))) + (numberofHits * 10), if(T33 >= r, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10), 1)))',
            'dependencies' => array(
                'assign("T33", round(n/3))',
                'assign("T80", floor(n*0.9))',
                'assign("monies", totalBuyInsAmount + totalRebuysAmount + totalAddOnsAmount)',
                'assign("avgBC", monies/buyins)',
                'assign("temp", 10 * (sqrt(n) / sqrt(T33 + 1)) * (1 + log(avgBC + 0.25)) + (numberofHits * 10))',
                'assign("numberofHits", hits)'
            ),
            'category' => 'points'
        ),
        'simple_points' => array(
            'name' => 'Simple Points',
            'description' => 'Simple points system: 10 points per player eliminated',
            'formula' => 'assign("points", numberofHits * 10)',
            'dependencies' => array(
                'assign("numberofHits", hits)'
            ),
            'category' => 'points'
        ),
        'linear_points' => array(
            'name' => 'Linear Points',
            'description' => 'Linear points: (n - r + 1) * 10',
            'formula' => 'assign("points", (n - r + 1) * 10)',
            'dependencies' => array(),
            'category' => 'points'
        ),
        'exponential_points' => array(
            'name' => 'Exponential Points',
            'description' => 'Exponential decay: 100 * pow(0.9, r-1)',
            'formula' => 'assign("points", round(100 * pow(0.9, r-1)))',
            'dependencies' => array(),
            'category' => 'points'
        ),
        'season_total' => array(
            'name' => 'Season Total Points',
            'description' => 'Sum of all tournament points in season',
            'formula' => 'assign("points", sum(tournament_points))',
            'dependencies' => array(),
            'category' => 'season'
        ),
        'season_best' => array(
            'name' => 'Season Best Points',
            'description' => 'Best single tournament points in season',
            'formula' => 'assign("points", max(tournament_points))',
            'dependencies' => array(),
            'category' => 'season'
        )
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_formula_settings'));
        add_action('admin_menu', array($this, 'add_formula_admin_menu'));
    }

    /**
     * Validate formula syntax and variables
     */
    public function validate_formula($formula, $context = 'tournament') {
        $errors = array();
        $warnings = array();

        // Check for balanced parentheses
        if (!$this->check_balanced_parentheses($formula)) {
            $errors[] = 'Unbalanced parentheses in formula';
        }

        // Check for balanced quotes
        if (!$this->check_balanced_quotes($formula)) {
            $errors[] = 'Unbalanced quotes in formula';
        }

        // Extract variables and functions
        $tokens = $this->tokenize_formula($formula);
        $used_variables = array();
        $used_functions = array();

        foreach ($tokens as $token) {
            if ($token['type'] === 'variable') {
                $used_variables[] = $token['value'];
            } elseif ($token['type'] === 'function') {
                $used_functions[] = $token['value'];
            }
        }

        // Validate variables
        foreach ($used_variables as $variable) {
            if (!isset($this->td_variables[$variable])) {
                $warnings[] = "Unknown variable: {$variable}";
            }
        }

        // Validate functions
        foreach ($used_functions as $function) {
            if (!isset($this->td_functions[$function])) {
                $errors[] = "Unknown function: {$function}";
            }
        }

        // Check for assignment statements
        if (strpos($formula, 'assign(') === false && $context === 'tournament') {
            $warnings[] = 'Formula should assign points value for tournament context';
        }

        // Context-specific validation
        if ($context === 'season') {
            $season_functions = array('sum', 'max', 'min', 'avg', 'count');
            $has_season_func = false;
            foreach ($season_functions as $func) {
                if (in_array($func, $used_functions)) {
                    $has_season_func = true;
                    break;
                }
            }
            if (!$has_season_func) {
                $warnings[] = 'Season formulas should use aggregation functions (sum, max, min, avg)';
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'used_variables' => $used_variables,
            'used_functions' => $used_functions
        );
    }

    /**
     * Check for balanced parentheses
     */
    private function check_balanced_parentheses($formula) {
        $count = 0;
        $length = strlen($formula);

        for ($i = 0; $i < $length; $i++) {
            $char = $formula[$i];
            if ($char === '(') {
                $count++;
            } elseif ($char === ')') {
                $count--;
                if ($count < 0) {
                    return false;
                }
            }
        }

        return $count === 0;
    }

    /**
     * Check for balanced quotes
     */
    private function check_balanced_quotes($formula) {
        $count = 0;
        $length = strlen($formula);
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $formula[$i];
            if ($char === '\\' && !$escaped) {
                $escaped = true;
                continue;
            }
            if ($char === '"' && !$escaped) {
                $count++;
            }
            $escaped = false;
        }

        return $count % 2 === 0;
    }

    /**
     * Tokenize formula into variables, functions, numbers, and operators
     */
    private function tokenize_formula($formula) {
        $tokens = array();
        $length = strlen($formula);
        $i = 0;

        while ($i < $length) {
            $char = $formula[$i];

            // Skip whitespace
            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // Numbers
            if (ctype_digit($char) || ($char === '.' && $i + 1 < $length && ctype_digit($formula[$i + 1]))) {
                $start = $i;
                while ($i < $length && (ctype_digit($formula[$i]) || $formula[$i] === '.')) {
                    $i++;
                }
                $tokens[] = array('type' => 'number', 'value' => substr($formula, $start, $i - $start));
                continue;
            }

            // Variables and functions (alphabetic characters)
            if (ctype_alpha($char)) {
                $start = $i;
                while ($i < $length && (ctype_alpha($formula[$i]) || ctype_digit($formula[$i]))) {
                    $i++;
                }
                $value = substr($formula, $start, $i - $start);

                // Check if it's a function (followed by parentheses)
                if ($i < $length && $formula[$i] === '(') {
                    $tokens[] = array('type' => 'function', 'value' => $value);
                } else {
                    $tokens[] = array('type' => 'variable', 'value' => $value);
                }
                continue;
            }

            // Operators and punctuation
            if (in_array($char, array('+', '-', '*', '/', '^', '=', '<', '>', '!', '&', '|', ',', '(', ')'))) {
                // Handle multi-character operators
                if ($i + 1 < $length) {
                    $two_char = $char . $formula[$i + 1];
                    if (in_array($two_char, array('==', '!=', '<=', '>=', '&&', '||'))) {
                        $tokens[] = array('type' => 'operator', 'value' => $two_char);
                        $i += 2;
                        continue;
                    }
                }

                $tokens[] = array('type' => 'operator', 'value' => $char);
                $i++;
                continue;
            }

            // Strings
            if ($char === '"') {
                $start = $i + 1;
                $end = strpos($formula, '"', $start);
                if ($end === false) {
                    $tokens[] = array('type' => 'error', 'value' => 'Unterminated string');
                    break;
                }
                $tokens[] = array('type' => 'string', 'value' => substr($formula, $start, $end - $start));
                $i = $end + 1;
                continue;
            }

            $i++;
        }

        return $tokens;
    }

    /**
     * Calculate formula result with given data
     */
    public function calculate_formula($formula, $data, $context = 'tournament') {
        // Validate formula first
        $validation = $this->validate_formula($formula, $context);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'error' => implode(', ', $validation['errors']),
                'result' => null
            );
        }

        try {
            // Prepare variables
            $variables = $this->prepare_variables($data, $context);

            // Process assignment statements first
            $processed_formula = $this->process_assignments($formula, $variables);

            // Evaluate the final expression
            $result = $this->evaluate_expression($processed_formula, $variables);

            return array(
                'success' => true,
                'result' => $result,
                'variables' => $variables,
                'warnings' => $validation['warnings']
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'result' => null
            );
        }
    }

    /**
     * Prepare variables for calculation
     */
    private function prepare_variables($data, $context) {
        $variables = array();

        // Basic tournament variables
        $variables['n'] = intval($data['total_players'] ?? 1);
        $variables['r'] = intval($data['finish_position'] ?? 1);
        $variables['place'] = $variables['r'];
        $variables['entrants'] = $variables['n'];

        // Financial variables
        $variables['monies'] = floatval($data['total_money'] ?? 0);
        $variables['buyins'] = intval($data['total_buyins'] ?? 1);
        $variables['rebuys'] = intval($data['total_rebuys'] ?? 0);
        $variables['addons'] = intval($data['total_addons'] ?? 0);
        $variables['avgBC'] = $variables['buyins'] > 0 ? $variables['monies'] / $variables['buyins'] : 0;

        // Player-specific variables
        $variables['numberofHits'] = intval($data['hits'] ?? 0);
        $variables['hits'] = $variables['numberofHits'];
        $variables['winnings'] = floatval($data['winnings'] ?? 0);

        // Tournament-specific financials
        $variables['totalBuyInsAmount'] = floatval($data['total_buyins_amount'] ?? $variables['monies']);
        $variables['totalRebuysAmount'] = floatval($data['total_rebuys_amount'] ?? 0);
        $variables['totalAddOnsAmount'] = floatval($data['total_addons_amount'] ?? 0);
        $variables['buyinAmount'] = floatval($data['buyin_amount'] ?? 0);
        $variables['feeAmount'] = floatval($data['fee_amount'] ?? 0);
        $variables['prizePool'] = floatval($data['prize_pool'] ?? $variables['monies']);

        // Initialize points
        $variables['points'] = 1;
        $variables['temp'] = 0;

        return $variables;
    }

    /**
     * Process assignment statements in formula
     */
    private function process_assignments($formula, &$variables) {
        // Look for assign("variable", expression) patterns
        $pattern = '/assign\(\s*"([^"]+)"\s*,\s*([^)]+)\)/';

        while (preg_match($pattern, $formula, $matches, PREG_OFFSET_CAPTURE)) {
            $variable_name = $matches[1][0];
            $expression = trim($matches[2][0]);

            // Evaluate the expression
            $value = $this->evaluate_expression($expression, $variables);
            $variables[$variable_name] = $value;

            // Remove the assignment from formula
            $formula = substr_replace($formula, '', $matches[0][1], strlen($matches[0][0]));
        }

        return trim($formula);
    }

    /**
     * Evaluate mathematical expression safely
     */
    private function evaluate_expression($expression, $variables) {
        // Replace variables with their values
        foreach ($variables as $name => $value) {
            $expression = preg_replace('/\b' . preg_quote($name, '/') . '\b/', $value, $expression);
        }

        // Replace functions with PHP equivalents
        $expression = $this->replace_functions($expression);

        // Handle conditional expressions
        $expression = $this->handle_conditionals($expression);

        // Safe evaluation using eval (restricted environment)
        try {
            // Only allow mathematical operations and functions
            if (!preg_match('/^[0-9+\-*\/().<>=!&| sqrtroundfloorceillogpowabsifandornot ]+$/', $expression)) {
                throw new Exception("Invalid expression: {$expression}");
            }

            $result = eval("return ({$expression});");
            return is_finite($result) ? $result : 0;

        } catch (Exception $e) {
            throw new Exception("Evaluation error: " . $e->getMessage());
        }
    }

    /**
     * Replace TD functions with PHP equivalents
     */
    private function replace_functions($expression) {
        $replacements = array(
            'sqrt(' => 'sqrt(',
            'round(' => 'round(',
            'floor(' => 'floor(',
            'ceil(' => 'ceil(',
            'log(' => 'log(',
            'log10(' => 'log10(',
            'pow(' => 'pow(',
            'abs(' => 'abs(',
            'min(' => 'min(',
            'max(' => 'max(',
        );

        foreach ($replacements as $td_func => $php_func) {
            $expression = str_replace($td_func, $php_func, $expression);
        }

        return $expression;
    }

    /**
     * Handle conditional expressions
     */
    private function handle_conditionals($expression) {
        // Replace TD if(condition, true, false) with PHP ternary
        $expression = preg_replace('/if\s*\(\s*([^,]+)\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/', '($1) ? ($2) : ($3)', $expression);

        // Replace logical operators
        $expression = str_replace('and', '&&', $expression);
        $expression = str_replace('or', '||', $expression);
        $expression = str_replace('not', '!', $expression);

        return $expression;
    }

    /**
     * Get available variables
     */
    public function get_available_variables() {
        return $this->td_variables;
    }

    /**
     * Get available functions
     */
    public function get_available_functions() {
        return $this->td_functions;
    }

    /**
     * Get default formulas
     */
    public function get_default_formulas() {
        return $this->default_formulas;
    }

    /**
     * Save custom formula to database
     */
    public function save_formula($name, $formula_data) {
        $formulas = get_option('poker_tournament_formulas', array());
        $formulas[$name] = $formula_data;
        update_option('poker_tournament_formulas', $formulas);
    }

    /**
     * Get saved formula
     */
    public function get_formula($name) {
        $formulas = get_option('poker_tournament_formulas', array());
        return $formulas[$name] ?? null;
    }

    /**
     * Get all saved formulas
     */
    public function get_all_formulas() {
        $saved_formulas = get_option('poker_tournament_formulas', array());
        return array_merge($this->default_formulas, $saved_formulas);
    }

    /**
     * Delete formula
     */
    public function delete_formula($name) {
        $formulas = get_option('poker_tournament_formulas', array());
        if (isset($formulas[$name])) {
            unset($formulas[$name]);
            update_option('poker_tournament_formulas', $formulas);
            return true;
        }
        return false;
    }

    /**
     * Register admin settings
     */
    public function register_formula_settings() {
        register_setting('poker_formulas', 'poker_tournament_formulas');
    }

    /**
     * Add admin menu
     */
    public function add_formula_admin_menu() {
        include_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/formula-manager-page.php';
        $formula_manager_page = new Poker_Formula_Manager_Page();

        add_submenu_page(
            'edit.php?post_type=tournament',
            __('Formula Manager', 'poker-tournament-import'),
            __('Formulas', 'poker-tournament-import'),
            'manage_options',
            'poker-formula-manager',
            array($formula_manager_page, 'render_page')
        );
    }

    /**
     * Render formula manager interface
     */
    public function render_formula_manager() {
        ?>
        <div class="wrap">
            <h1><?php _e('Tournament Formula Manager', 'poker-tournament-import'); ?></h1>

            <div class="poker-formula-tabs">
                <ul class="tab-nav">
                    <li><a href="#formulas" class="tab-active"><?php _e('Formulas', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#validator"><?php _e('Formula Validator', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#variables"><?php _e('Variables & Functions', 'poker-tournament-import'); ?></a></li>
                </ul>

                <div id="formulas" class="tab-content">
                    <?php $this->render_formulas_tab(); ?>
                </div>

                <div id="validator" class="tab-content" style="display:none;">
                    <?php $this->render_validator_tab(); ?>
                </div>

                <div id="variables" class="tab-content" style="display:none;">
                    <?php $this->render_variables_tab(); ?>
                </div>
            </div>
        </div>

        <style>
        .poker-formula-tabs {
            margin-top: 20px;
        }
        .tab-nav {
            list-style: none;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #ccc;
        }
        .tab-nav li {
            display: inline-block;
            margin-right: 10px;
        }
        .tab-nav a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            border: 1px solid #ccc;
            border-bottom: none;
            background: #f1f1f1;
            color: #333;
        }
        .tab-nav a.tab-active {
            background: #fff;
            color: #000;
        }
        .tab-content {
            padding: 20px;
            border: 1px solid #ccc;
            border-top: none;
        }
        .formula-editor {
            max-width: 800px;
        }
        .formula-test {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
        }
        .variable-list {
            columns: 2;
            column-gap: 30px;
        }
        .variable-item {
            margin-bottom: 10px;
            break-inside: avoid;
        }
        .variable-name {
            font-family: monospace;
            background: #eee;
            padding: 2px 4px;
            border-radius: 3px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.tab-nav a').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');

                $('.tab-nav a').removeClass('tab-active');
                $(this).addClass('tab-active');

                $('.tab-content').hide();
                $(target).show();
            });
        });
        </script>
        <?php
    }

    /**
     * Render formulas management tab
     */
    private function render_formulas_tab() {
        $formulas = $this->get_all_formulas();
        ?>
        <div class="formula-editor">
            <h2><?php _e('Manage Formulas', 'poker-tournament-import'); ?></h2>

            <button type="button" class="button button-primary" onclick="pokerAddNewFormula()">
                <?php _e('Add New Formula', 'poker-tournament-import'); ?>
            </button>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'poker-tournament-import'); ?></th>
                        <th><?php _e('Category', 'poker-tournament-import'); ?></th>
                        <th><?php _e('Description', 'poker-tournament-import'); ?></th>
                        <th><?php _e('Actions', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formulas as $key => $formula): ?>
                        <tr>
                            <td><?php echo esc_html($formula['name']); ?></td>
                            <td><?php echo esc_html($formula['category']); ?></td>
                            <td><?php echo esc_html($formula['description']); ?></td>
                            <td>
                                <button type="button" class="button" onclick="pokerEditFormula('<?php echo esc_js($key); ?>')">
                                    <?php _e('Edit', 'poker-tournament-import'); ?>
                                </button>
                                <?php if (!isset($this->default_formulas[$key])): ?>
                                    <button type="button" class="button" onclick="pokerDeleteFormula('<?php echo esc_js($key); ?>')">
                                        <?php _e('Delete', 'poker-tournament-import'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render formula validator tab
     */
    private function render_validator_tab() {
        ?>
        <div class="formula-validator">
            <h2><?php _e('Formula Validator', 'poker-tournament-import'); ?></h2>

            <div class="formula-test">
                <h3><?php _e('Test Your Formula', 'poker-tournament-import'); ?></h3>

                <div class="form-field">
                    <label for="formula-input"><?php _e('Formula:', 'poker-tournament-import'); ?></label>
                    <textarea id="formula-input" rows="6" style="width: 100%; font-family: monospace;"
                              placeholder="assign(&quot;points&quot;, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10))"></textarea>
                </div>

                <div class="form-field">
                    <label><?php _e('Test Data:', 'poker-tournament-import'); ?></label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <input type="number" id="test-n" placeholder="Total Players (n)" value="20">
                        <input type="number" id="test-r" placeholder="Finish Position (r)" value="3">
                        <input type="number" id="test-hits" placeholder="Hits (numberofHits)" value="5">
                        <input type="number" id="test-money" placeholder="Total Money" value="2000">
                        <input type="number" id="test-buyins" placeholder="Buy-ins" value="20">
                    </div>
                </div>

                <button type="button" class="button button-primary" onclick="pokerValidateFormula()">
                    <?php _e('Validate & Test', 'poker-tournament-import'); ?>
                </button>

                <div id="validation-result" style="margin-top: 15px;"></div>
            </div>
        </div>

        <script>
        function pokerValidateFormula() {
            var formula = document.getElementById('formula-input').value;
            var testData = {
                n: parseInt(document.getElementById('test-n').value) || 20,
                r: parseInt(document.getElementById('test-r').value) || 3,
                hits: parseInt(document.getElementById('test-hits').value) || 5,
                total_money: parseInt(document.getElementById('test-money').value) || 2000,
                total_buyins: parseInt(document.getElementById('test-buyins').value) || 20
            };

            jQuery.post(ajaxurl, {
                action: 'poker_validate_formula',
                formula: formula,
                test_data: testData,
                nonce: '<?php echo wp_create_nonce("poker_formula_validator"); ?>'
            }, function(response) {
                document.getElementById('validation-result').innerHTML = response;
            });
        }
        </script>
        <?php
    }

    /**
     * Render variables and functions reference tab
     */
    private function render_variables_tab() {
        ?>
        <div class="formula-reference">
            <h2><?php _e('Available Variables', 'poker-tournament-import'); ?></h2>

            <div class="variable-list">
                <?php foreach ($this->td_variables as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?></span>
                        <span class="variable-type">(<?php echo esc_html($info['type']); ?>)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php _e('Available Functions', 'poker-tournament-import'); ?></h2>

            <div class="variable-list">
                <?php foreach ($this->td_functions as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?>()</span>
                        <span class="variable-type">(<?php echo esc_html($info['params']); ?> params)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}