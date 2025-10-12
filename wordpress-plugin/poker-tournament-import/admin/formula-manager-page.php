<?php
/**
 * Formula Manager Admin Page
 *
 * Complete admin interface for managing Tournament Director formulas
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Formula_Manager_Page {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'save_formula_settings'));
    }

    /**
     * Render the formula manager page
     */
    public function render_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'formulas';
        ?>
        <div class="wrap poker-formula-manager">
            <h1><?php _e('Tournament Formula Manager', 'poker-tournament-import'); ?></h1>

            <div class="poker-formula-tabs">
                <ul class="tab-nav">
                    <li>
                        <a href="?page=poker-formula-manager&amp;tab=formulas"
                           class="<?php echo $active_tab === 'formulas' ? 'tab-active' : ''; ?>">
                           <?php _e('Formulas', 'poker-tournament-import'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="?page=poker-formula-manager&amp;tab=validator"
                           class="<?php echo $active_tab === 'validator' ? 'tab-active' : ''; ?>">
                           <?php _e('Formula Validator', 'poker-tournament-import'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="?page=poker-formula-manager&amp;tab=settings"
                           class="<?php echo $active_tab === 'settings' ? 'tab-active' : ''; ?>">
                           <?php _e('Settings', 'poker-tournament-import'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="?page=poker-formula-manager&amp;tab=variables"
                           class="<?php echo $active_tab === 'variables' ? 'tab-active' : ''; ?>">
                           <?php _e('Variables & Functions', 'poker-tournament-import'); ?>
                        </a>
                    </li>
                </ul>

                <?php
                switch ($active_tab) {
                    case 'formulas':
                        $this->render_formulas_tab();
                        break;
                    case 'validator':
                        $this->render_validator_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'variables':
                        $this->render_variables_tab();
                        break;
                }
                ?>
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
            transition: all 0.2s ease;
        }
        .tab-nav a:hover {
            background: #e0e0e0;
        }
        .tab-nav a.tab-active {
            background: #fff;
            color: #000;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
        }
        .tab-content {
            padding: 20px;
            border: 1px solid #ccc;
            border-top: none;
            background: #fff;
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
        .formula-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
        }
        .formula-modal-content {
            background: #fff;
            margin: 50px auto;
            padding: 20px;
            max-width: 600px;
            border-radius: 4px;
        }
        .validation-results {
            margin-top: 15px;
        }
        .validation-results .success {
            background: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
            padding: 10px;
            border-radius: 4px;
        }
        .validation-results .error {
            background: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
            padding: 10px;
            border-radius: 4px;
        }
        .validation-results .warning {
            background: #fcf8e3;
            border: 1px solid #faebcc;
            color: #8a6d3b;
            padding: 10px;
            border-radius: 4px;
        }
        .formula-code {
            font-family: 'Courier New', monospace;
            background: #f8f8f8;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            white-space: pre-wrap;
        }
        </style>

        <!-- Formula Editor Modal -->
        <div id="formula-editor-modal" class="formula-modal">
            <div class="formula-modal-content">
                <h2 id="modal-title"><?php _e('Edit Formula', 'poker-tournament-import'); ?></h2>
                <form id="formula-editor-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="formula-name"><?php _e('Formula Name:', 'poker-tournament-import'); ?></label></th>
                            <td><input type="text" id="formula-name" class="regular-text" readonly></td>
                        </tr>
                        <tr>
                            <th><label for="formula-display-name"><?php _e('Display Name:', 'poker-tournament-import'); ?></label></th>
                            <td><input type="text" id="formula-display-name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="formula-description"><?php _e('Description:', 'poker-tournament-import'); ?></label></th>
                            <td><textarea id="formula-description" rows="3" class="large-text" required></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="formula-category"><?php _e('Category:', 'poker-tournament-import'); ?></label></th>
                            <td>
                                <select id="formula-category" required>
                                    <option value="points"><?php _e('Points Calculation', 'poker-tournament-import'); ?></option>
                                    <option value="season"><?php _e('Season Standings', 'poker-tournament-import'); ?></option>
                                    <option value="custom"><?php _e('Custom', 'poker-tournament-import'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="formula-dependencies"><?php _e('Dependencies:', 'poker-tournament-import'); ?></label></th>
                            <td>
                                <textarea id="formula-dependencies" rows="4" class="large-text"
                                          placeholder="assign(&quot;T33&quot;, round(n/3))
assign(&quot;T80&quot;, floor(n*0.9))
assign(&quot;avgBC&quot;, monies/buyins)"></textarea>
                                <p class="description"><?php _e('One assignment per line. These will be processed before the main formula.', 'poker-tournament-import'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="formula-expression"><?php _e('Formula:', 'poker-tournament-import'); ?></label></th>
                            <td>
                                <textarea id="formula-expression" rows="6" class="large-text" required
                                          placeholder="assign(&quot;points&quot;, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10))"></textarea>
                                <p class="description"><?php _e('Use Tournament Director formula syntax. See Variables & Functions tab for reference.', 'poker-tournament-import'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="button" class="button button-primary" id="save-formula-btn">
                            <?php _e('Save Formula', 'poker-tournament-import'); ?>
                        </button>
                        <button type="button" class="button" id="test-formula-btn">
                            <?php _e('Test Formula', 'poker-tournament-import'); ?>
                        </button>
                        <button type="button" class="button" onclick="closeFormulaModal()">
                            <?php _e('Cancel', 'poker-tournament-import'); ?>
                        </button>
                    </p>
                </form>

                <div id="formula-test-result" style="margin-top: 15px;"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.tab-nav a').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');

                $('.tab-nav a').removeClass('tab-active');
                $(this).addClass('tab-active');

                $('.tab-content').hide();
                $(target).show();
            });

            // Formula editor modal
            window.openFormulaModal = function(formulaKey) {
                $('#formula-editor-modal').show();
                $('#formula-name').val(formulaKey);

                if (formulaKey === 'new') {
                    $('#modal-title').text('<?php _e('Add New Formula', 'poker-tournament-import'); ?>');
                    $('#formula-display-name').val('').prop('readonly', false);
                    $('#formula-description').val('');
                    $('#formula-category').val('points');
                    $('#formula-dependencies').val('');
                    $('#formula-expression').val('');
                } else {
                    $('#modal-title').text('<?php _e('Edit Formula', 'poker-tournament-import'); ?>');
                    $('#formula-display-name').val('').prop('readonly', true);

                    // Load formula data via AJAX
                    $.post(ajaxurl, {
                        action: 'poker_get_formula',
                        formula_key: formulaKey,
                        nonce: '<?php echo wp_create_nonce("poker_formula_manager"); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#formula-display-name').val(response.data.name);
                            $('#formula-description').val(response.data.description);
                            $('#formula-category').val(response.data.category);
                            $('#formula-dependencies').val(response.data.dependencies);
                            $('#formula-expression').val(response.data.formula);
                        }
                    });
                }
            };

            window.closeFormulaModal = function() {
                $('#formula-editor-modal').hide();
                $('#formula-test-result').empty();
            };

            // Save formula
            $('#save-formula-btn').click(function() {
                var formData = {
                    action: 'poker_save_formula',
                    formula_name: $('#formula-name').val(),
                    display_name: $('#formula-display-name').val(),
                    description: $('#formula-description').val(),
                    category: $('#formula-category').val(),
                    dependencies: $('#formula-dependencies').val(),
                    formula: $('#formula-expression').val(),
                    nonce: '<?php echo wp_create_nonce("poker_formula_manager"); ?>'
                };

                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        closeFormulaModal();
                        location.reload();
                    } else {
                        alert(response.data.message + '\n' + (response.data.errors ? response.data.errors.join('\n') : ''));
                    }
                });
            });

            // Test formula
            $('#test-formula-btn').click(function() {
                var formula = $('#formula-dependencies').val() + ';' + $('#formula-expression').val();
                var testData = {
                    n: 20,
                    r: 3,
                    hits: 5,
                    total_money: 2000,
                    total_buyins: 20
                };

                $.post(ajaxurl, {
                    action: 'poker_validate_formula',
                    formula: formula,
                    test_data: testData,
                    nonce: '<?php echo wp_create_nonce("poker_formula_validator"); ?>'
                }, function(response) {
                    $('#formula-test-result').html(response);
                });
            });

            // Delete formula
            window.deleteFormula = function(formulaKey) {
                if (confirm('<?php _e('Are you sure you want to delete this formula?', 'poker-tournament-import'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'poker_delete_formula',
                        formula_name: formulaKey,
                        nonce: '<?php echo wp_create_nonce("poker_formula_manager"); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    });
                }
            };
        });
        </script>
        <?php
    }

    /**
     * Render formulas management tab
     */
    private function render_formulas_tab() {
        $formula_validator = new Poker_Tournament_Formula_Validator();
        $formulas = $formula_validator->get_all_formulas();
        $active_tournament = get_option('poker_active_tournament_formula', 'tournament_points');
        $active_season = get_option('poker_active_season_formula', 'season_total');
        ?>
        <div class="formula-editor">
            <h2><?php _e('Manage Formulas', 'poker-tournament-import'); ?></h2>

            <button type="button" class="button button-primary" onclick="openFormulaModal('new')">
                <?php _e('Add New Formula', 'poker-tournament-import'); ?>
            </button>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'poker-tournament-import'); ?></th>
                        <th><?php _e('Category', 'poker-tournament-import'); ?></th>
                        <th><?php _e('Description', 'poker-tournament-import'); ?></th>
                        <th><?php _e('Status', 'poker-tournament-import'); ?></th>
                        <th><?php _e('Actions', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formulas as $key => $formula): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($formula['name']); ?></strong>
                                <?php if (isset($formula_validator->get_default_formulas()[$key])): ?>
                                    <span class="badge" style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">
                                        <?php _e('Default', 'poker-tournament-import'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($formula['category']); ?></td>
                            <td><?php echo esc_html($formula['description']); ?></td>
                            <td>
                                <?php if ($key === $active_tournament): ?>
                                    <span style="color: #46b450;"><?php _e('Active Tournament', 'poker-tournament-import'); ?></span>
                                <?php endif; ?>
                                <?php if ($key === $active_season): ?>
                                    <span style="color: #46b450;"><?php _e('Active Season', 'poker-tournament-import'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button" onclick="openFormulaModal('<?php echo esc_js($key); ?>')">
                                    <?php _e('Edit', 'poker-tournament-import'); ?>
                                </button>
                                <?php if (!isset($formula_validator->get_default_formulas()[$key])): ?>
                                    <button type="button" class="button" onclick="deleteFormula('<?php echo esc_js($key); ?>')">
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

                <div id="validation-result"></div>
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
     * Render settings tab
     */
    private function render_settings_tab() {
        $formula_validator = new Poker_Tournament_Formula_Validator();
        $formulas = $formula_validator->get_all_formulas();
        $active_tournament = get_option('poker_active_tournament_formula', 'tournament_points');
        $active_season = get_option('poker_active_season_formula', 'season_total');
        ?>
        <div class="formula-settings">
            <h2><?php _e('Formula Settings', 'poker-tournament-import'); ?></h2>

            <form method="post" action="options.php">
                <?php settings_fields('poker_formula_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="poker_active_tournament_formula"><?php _e('Active Tournament Formula', 'poker-tournament-import'); ?></label>
                        </th>
                        <td>
                            <select name="poker_active_tournament_formula" id="poker_active_tournament_formula">
                                <?php foreach ($formulas as $key => $formula): ?>
                                    <?php if ($formula['category'] === 'points' || $formula['category'] === 'custom'): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($active_tournament, $key); ?>>
                                            <?php echo esc_html($formula['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Formula used for calculating tournament points.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="poker_active_season_formula"><?php _e('Active Season Formula', 'poker-tournament-import'); ?></label>
                        </th>
                        <td>
                            <select name="poker_active_season_formula" id="poker_active_season_formula">
                                <?php foreach ($formulas as $key => $formula): ?>
                                    <?php if ($formula['category'] === 'season' || $formula['category'] === 'custom'): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($active_season, $key); ?>>
                                            <?php echo esc_html($formula['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Formula used for calculating season standings.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="poker_formula_debug_mode"><?php _e('Debug Mode', 'poker-tournament-import'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="poker_formula_debug_mode" id="poker_formula_debug_mode"
                                   value="1" <?php checked(get_option('poker_formula_debug_mode', 0)); ?>>
                            <label for="poker_formula_debug_mode"><?php _e('Enable formula debugging and logging', 'poker-tournament-import'); ?></label>
                            <p class="description"><?php _e('When enabled, formula calculations will be logged for troubleshooting.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render variables and functions reference tab
     */
    private function render_variables_tab() {
        $formula_validator = new Poker_Tournament_Formula_Validator();
        $variables = $formula_validator->get_available_variables();
        $functions = $formula_validator->get_available_functions();
        ?>
        <div class="formula-reference">
            <h2><?php _e('Available Variables', 'poker-tournament-import'); ?></h2>
            <p><?php _e('These variables can be used in your formulas. They are automatically populated with tournament data.', 'poker-tournament-import'); ?></p>

            <div class="variable-list">
                <?php foreach ($variables as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?></span>
                        <span class="variable-type">(<?php echo esc_html($info['type']); ?>)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php _e('Available Functions', 'poker-tournament-import'); ?></h2>
            <p><?php _e('These mathematical functions can be used in your formulas.', 'poker-tournament-import'); ?></p>

            <div class="variable-list">
                <?php foreach ($functions as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?>()</span>
                        <span class="variable-type">(<?php echo esc_html($info['params']); ?> params)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php _e('Formula Examples', 'poker-tournament-import'); ?></h2>

            <div class="formula-examples">
                <h3><?php _e('Simple Linear Points', 'poker-tournament-import'); ?></h3>
                <div class="formula-code">assign("points", (n - r + 1) * 10)</div>

                <h3><?php _e('Tournament Director Default', 'poker-tournament-import'); ?></h3>
                <div class="formula-code">assign("T33", round(n/3))
assign("T80", floor(n*0.9))
assign("monies", totalBuyInsAmount + totalRebuysAmount + totalAddOnsAmount)
assign("avgBC", monies/buyins)
assign("temp", 10 * (sqrt(n) / sqrt(T33 + 1)) * (1 + log(avgBC + 0.25)) + (numberofHits * 10))
assign("points", if(T80 > r and T33 < r, round(temp * pow(0.66, (r-T33))) + (numberofHits * 10), if(T33 >= r, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10), 1)))</div>

                <h3><?php _e('Exponential Decay', 'poker-tournament-import'); ?></h3>
                <div class="formula-code">assign("points", round(100 * pow(0.9, r-1)))</div>

                <h3><?php _e('Position-Based Bonus', 'poker-tournament-import'); ?></h3>
                <div class="formula-code">assign("points", if(r <= 3, 100 - (r-1) * 20, max(10, 50 - r)))</div>
            </div>
        </div>
        <?php
    }

    /**
     * Save formula settings
     */
    public function save_formula_settings() {
        if (isset($_POST['option_page']) && $_POST['option_page'] === 'poker_formula_settings') {
            register_setting('poker_formula_settings', 'poker_active_tournament_formula');
            register_setting('poker_formula_settings', 'poker_active_season_formula');
            register_setting('poker_formula_settings', 'poker_formula_debug_mode');
        }
    }
}