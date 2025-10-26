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
        ?>
        <div class="wrap poker-formula-manager">
            <h1><?php esc_html_e('Tournament Formula Manager', 'poker-tournament-import'); ?></h1>
            <?php $this->render_formulas_tab(); ?>
        </div>

        <style>
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
                <h2 id="modal-title"><?php esc_html_e('Edit Formula', 'poker-tournament-import'); ?></h2>
                <form id="formula-editor-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="formula-name"><?php esc_html_e('Formula Name:', 'poker-tournament-import'); ?></label></th>
                            <td><input type="text" id="formula-name" class="regular-text" readonly></td>
                        </tr>
                        <tr>
                            <th><label for="formula-display-name"><?php esc_html_e('Display Name:', 'poker-tournament-import'); ?></label></th>
                            <td><input type="text" id="formula-display-name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="formula-description"><?php esc_html_e('Description:', 'poker-tournament-import'); ?></label></th>
                            <td><textarea id="formula-description" rows="3" class="large-text" required></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="formula-category"><?php esc_html_e('Category:', 'poker-tournament-import'); ?></label></th>
                            <td>
                                <select id="formula-category" required>
                                    <option value="points"><?php esc_html_e('Points Calculation', 'poker-tournament-import'); ?></option>
                                    <option value="season"><?php esc_html_e('Season Standings', 'poker-tournament-import'); ?></option>
                                    <option value="custom"><?php esc_html_e('Custom', 'poker-tournament-import'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="formula-dependencies"><?php esc_html_e('Dependencies:', 'poker-tournament-import'); ?></label></th>
                            <td>
                                <textarea id="formula-dependencies" rows="4" class="large-text"
                                          placeholder="assign(&quot;T33&quot;, round(n/3))
assign(&quot;T80&quot;, floor(n*0.9))
assign(&quot;avgBC&quot;, monies/buyins)"></textarea>
                                <p class="description"><?php esc_html_e('One assignment per line. These will be processed before the main formula.', 'poker-tournament-import'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="formula-expression"><?php esc_html_e('Formula:', 'poker-tournament-import'); ?></label></th>
                            <td>
                                <textarea id="formula-expression" rows="6" class="large-text" required
                                          placeholder="assign(&quot;points&quot;, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10))"></textarea>
                                <p class="description"><?php esc_html_e('Use Tournament Director formula syntax. See Variables & Functions tab for reference.', 'poker-tournament-import'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="button" class="button button-primary" id="save-formula-btn">
                            <?php esc_html_e('Save Formula', 'poker-tournament-import'); ?>
                        </button>
                        <button type="button" class="button" id="test-formula-btn">
                            <?php esc_html_e('Test Formula', 'poker-tournament-import'); ?>
                        </button>
                        <button type="button" class="button" onclick="openVariableReferenceModal()">
                            <?php esc_html_e('Show Variable Reference', 'poker-tournament-import'); ?>
                        </button>
                        <button type="button" class="button" onclick="closeFormulaModal()">
                            <?php esc_html_e('Cancel', 'poker-tournament-import'); ?>
                        </button>
                    </p>
                </form>

                <div id="formula-test-result" style="margin-top: 15px;"></div>
            </div>
        </div>

        <!-- Variable Reference Modal -->
        <div id="variable-reference-modal" class="formula-modal">
            <div class="formula-modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
                <h2><?php esc_html_e('Tournament Director Variable Reference', 'poker-tournament-import'); ?></h2>

                <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
                    <a href="#" class="nav-tab nav-tab-active" data-target="var-tab-tournament">
                        <?php esc_html_e('Tournament Variables', 'poker-tournament-import'); ?>
                    </a>
                    <a href="#" class="nav-tab" data-target="var-tab-player">
                        <?php esc_html_e('Player Variables', 'poker-tournament-import'); ?>
                    </a>
                    <a href="#" class="nav-tab" data-target="var-tab-mapping">
                        <?php esc_html_e('Variable Mapping', 'poker-tournament-import'); ?>
                    </a>
                    <a href="#" class="nav-tab" data-target="var-tab-functions">
                        <?php esc_html_e('Functions', 'poker-tournament-import'); ?>
                    </a>
                </div>

                <!-- Tournament Variables Tab -->
                <div id="var-tab-tournament" class="var-tab-pane">
                    <h3><?php esc_html_e('Tournament Information Variables', 'poker-tournament-import'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Variable', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Aliases', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Type', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>buyins</code></td>
                                <td><code>n</code>, <code>numberofplayers</code></td>
                                <td>int</td>
                                <td><?php esc_html_e('Total number of players who bought in', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>rank</code></td>
                                <td><code>r</code></td>
                                <td>int</td>
                                <td><?php esc_html_e('Player finish position (1st, 2nd, 3rd, etc.)', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>numberOfHits</code></td>
                                <td><code>nh</code>, <code>hits</code></td>
                                <td>int</td>
                                <td><?php esc_html_e('Number of player eliminations (knockouts)', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>pot</code></td>
                                <td><code>prizepool</code>, <code>pp</code></td>
                                <td>decimal</td>
                                <td><?php esc_html_e('Total prize pool amount', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>totalBuyinsAmount</code></td>
                                <td></td>
                                <td>decimal</td>
                                <td><?php esc_html_e('Total money collected from buy-ins', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>totalRebuysAmount</code></td>
                                <td></td>
                                <td>decimal</td>
                                <td><?php esc_html_e('Total money collected from rebuys', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>totalAddOnsAmount</code></td>
                                <td></td>
                                <td>decimal</td>
                                <td><?php esc_html_e('Total money collected from add-ons', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>defaultBuyinFee</code></td>
                                <td><code>buyinAmount</code></td>
                                <td>decimal</td>
                                <td><?php esc_html_e('Buy-in cost per player', 'poker-tournament-import'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Player Variables Tab -->
                <div id="var-tab-player" class="var-tab-pane" style="display: none;">
                    <h3><?php esc_html_e('Player Information Variables', 'poker-tournament-import'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Variable', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Aliases', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Type', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>prizeWinnings</code></td>
                                <td><code>pw</code>, <code>winnings</code></td>
                                <td>decimal</td>
                                <td><?php esc_html_e('Prize money won by player', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>numberOfRebuys</code></td>
                                <td><code>rebuys</code>, <code>nr</code></td>
                                <td>int</td>
                                <td><?php esc_html_e('Number of rebuys player made', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>numberOfAddOns</code></td>
                                <td><code>addons</code>, <code>na</code></td>
                                <td>int</td>
                                <td><?php esc_html_e('Number of add-ons player purchased', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>chipStack</code></td>
                                <td></td>
                                <td>decimal</td>
                                <td><?php esc_html_e('Player chip stack at elimination', 'poker-tournament-import'); ?></td>
                            </tr>
                            <tr>
                                <td><code>inTheMoney</code></td>
                                <td></td>
                                <td>bool</td>
                                <td><?php esc_html_e('Whether player finished in the money', 'poker-tournament-import'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Variable Mapping Tab -->
                <div id="var-tab-mapping" class="var-tab-pane" style="display: none;">
                    <h3><?php esc_html_e('Data Key to Variable Name Mapping', 'poker-tournament-import'); ?></h3>
                    <p><?php esc_html_e('This shows how our internal data fields map to Tournament Director formula variables:', 'poker-tournament-import'); ?></p>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Our Data Key', 'poker-tournament-import'); ?></th>
                                <th></th>
                                <th><?php esc_html_e('TD Formula Variable', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Aliases', 'poker-tournament-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>total_players</code></td>
                                <td>→</td>
                                <td><code>buyins</code></td>
                                <td><code>n</code>, <code>numberofplayers</code></td>
                            </tr>
                            <tr>
                                <td><code>finish_position</code></td>
                                <td>→</td>
                                <td><code>rank</code></td>
                                <td><code>r</code></td>
                            </tr>
                            <tr>
                                <td><code>hits</code></td>
                                <td>→</td>
                                <td><code>numberOfHits</code></td>
                                <td><code>nh</code></td>
                            </tr>
                            <tr>
                                <td><code>total_money</code></td>
                                <td>→</td>
                                <td><code>pot</code></td>
                                <td><code>prizepool</code>, <code>pp</code></td>
                            </tr>
                            <tr>
                                <td><code>total_buyins_amount</code></td>
                                <td>→</td>
                                <td><code>totalBuyinsAmount</code></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><code>total_rebuys_amount</code></td>
                                <td>→</td>
                                <td><code>totalRebuysAmount</code></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><code>total_addons_amount</code></td>
                                <td>→</td>
                                <td><code>totalAddOnsAmount</code></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><code>buyin_amount</code></td>
                                <td>→</td>
                                <td><code>defaultBuyinFee</code></td>
                                <td><code>buyinAmount</code></td>
                            </tr>
                            <tr>
                                <td><code>winnings</code></td>
                                <td>→</td>
                                <td><code>prizeWinnings</code></td>
                                <td><code>pw</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Functions Tab -->
                <div id="var-tab-functions" class="var-tab-pane" style="display: none;">
                    <h3><?php esc_html_e('Available Mathematical Functions', 'poker-tournament-import'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Function', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                                <th><?php esc_html_e('Example', 'poker-tournament-import'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>abs(x)</code></td>
                                <td><?php esc_html_e('Absolute value', 'poker-tournament-import'); ?></td>
                                <td><code>abs(-5) = 5</code></td>
                            </tr>
                            <tr>
                                <td><code>sqrt(x)</code></td>
                                <td><?php esc_html_e('Square root', 'poker-tournament-import'); ?></td>
                                <td><code>sqrt(16) = 4</code></td>
                            </tr>
                            <tr>
                                <td><code>pow(x, y)</code></td>
                                <td><?php esc_html_e('Power (x raised to y)', 'poker-tournament-import'); ?></td>
                                <td><code>pow(2, 3) = 8</code></td>
                            </tr>
                            <tr>
                                <td><code>log(x)</code></td>
                                <td><?php esc_html_e('Natural logarithm', 'poker-tournament-import'); ?></td>
                                <td><code>log(10)</code></td>
                            </tr>
                            <tr>
                                <td><code>exp(x)</code></td>
                                <td><?php esc_html_e('e raised to power x', 'poker-tournament-import'); ?></td>
                                <td><code>exp(1) = 2.718...</code></td>
                            </tr>
                            <tr>
                                <td><code>round(x)</code></td>
                                <td><?php esc_html_e('Round to nearest integer', 'poker-tournament-import'); ?></td>
                                <td><code>round(3.7) = 4</code></td>
                            </tr>
                            <tr>
                                <td><code>floor(x)</code></td>
                                <td><?php esc_html_e('Round down to integer', 'poker-tournament-import'); ?></td>
                                <td><code>floor(3.7) = 3</code></td>
                            </tr>
                            <tr>
                                <td><code>ceil(x)</code></td>
                                <td><?php esc_html_e('Round up to integer', 'poker-tournament-import'); ?></td>
                                <td><code>ceil(3.2) = 4</code></td>
                            </tr>
                            <tr>
                                <td><code>max(x, y)</code></td>
                                <td><?php esc_html_e('Maximum of two values', 'poker-tournament-import'); ?></td>
                                <td><code>max(5, 10) = 10</code></td>
                            </tr>
                            <tr>
                                <td><code>min(x, y)</code></td>
                                <td><?php esc_html_e('Minimum of two values', 'poker-tournament-import'); ?></td>
                                <td><code>min(5, 10) = 5</code></td>
                            </tr>
                            <tr>
                                <td><code>if(cond, true, false)</code></td>
                                <td><?php esc_html_e('Conditional expression', 'poker-tournament-import'); ?></td>
                                <td><code>if(r <= 3, 100, 50)</code></td>
                            </tr>
                            <tr>
                                <td><code>assign(var, value)</code></td>
                                <td><?php esc_html_e('Assign value to variable', 'poker-tournament-import'); ?></td>
                                <td><code>assign("points", n-r+1)</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="submit" style="margin-top: 20px;">
                    <button type="button" class="button button-primary" onclick="closeVariableReferenceModal()">
                        <?php esc_html_e('Close', 'poker-tournament-import'); ?>
                    </button>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Formula editor modal
            window.openFormulaModal = function(formulaKey) {
                $('#formula-editor-modal').show();
                $('#formula-name').val(formulaKey);

                if (formulaKey === 'new') {
                    $('#modal-title').text('<?php esc_html_e('Add New Formula', 'poker-tournament-import'); ?>');
                    $('#formula-display-name').val('').prop('readonly', false);
                    $('#formula-description').val('');
                    $('#formula-category').val('points');
                    $('#formula-dependencies').val('');
                    $('#formula-expression').val('');
                } else {
                    $('#modal-title').text('<?php esc_html_e('Edit Formula', 'poker-tournament-import'); ?>');

                    // Load formula data via AJAX
                    $.post(ajaxurl, {
                        action: 'poker_get_formula',
                        formula_key: formulaKey,
                        nonce: '<?php echo esc_attr(wp_create_nonce("poker_formula_manager")); ?>'
                    }, function(response) {
                        if (response.success) {
                            var isDefault = response.data.is_default || false;

                            // Display name is readonly only for default formulas
                            $('#formula-display-name').val(response.data.name).prop('readonly', isDefault);
                            $('#formula-description').val(response.data.description);
                            $('#formula-category').val(response.data.category);

                            // Convert dependencies array to newline-separated string if needed
                            var deps = response.data.dependencies;
                            if (Array.isArray(deps)) {
                                deps = deps.join('\n');
                            }
                            $('#formula-dependencies').val(deps);
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
                    nonce: '<?php echo esc_attr(wp_create_nonce("poker_formula_manager")); ?>'
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
                    nonce: '<?php echo esc_attr(wp_create_nonce("poker_formula_validator")); ?>'
                }, function(response) {
                    $('#formula-test-result').html(response);
                });
            });

            // Delete formula
            window.deleteFormula = function(formulaKey) {
                if (confirm('<?php esc_html_e('Are you sure you want to delete this formula?', 'poker-tournament-import'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'poker_delete_formula',
                        formula_name: formulaKey,
                        nonce: '<?php echo esc_attr(wp_create_nonce("poker_formula_manager")); ?>'
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

            // Variable reference modal
            window.openVariableReferenceModal = function() {
                $('#variable-reference-modal').show();
            };

            window.closeVariableReferenceModal = function() {
                $('#variable-reference-modal').hide();
            };

            // Variable reference tab switching
            $('#variable-reference-modal .nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).data('target');

                // Update active tab
                $('#variable-reference-modal .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Show targeted tab pane
                $('#variable-reference-modal .var-tab-pane').hide();
                $('#' + target).show();
            });
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
            <h2><?php esc_html_e('Manage Formulas', 'poker-tournament-import'); ?></h2>

            <button type="button" class="button button-primary" onclick="openFormulaModal('new')">
                <?php esc_html_e('Add New Formula', 'poker-tournament-import'); ?>
            </button>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Category', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Status', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Actions', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formulas as $key => $formula): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($formula['name']); ?></strong>
                                <?php if (isset($formula_validator->get_default_formulas()[$key])): ?>
                                    <span class="badge" style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">
                                        <?php esc_html_e('Default', 'poker-tournament-import'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($formula['category']); ?></td>
                            <td><?php echo esc_html($formula['description']); ?></td>
                            <td>
                                <?php if ($key === $active_tournament): ?>
                                    <span style="color: #46b450;"><?php esc_html_e('Active Tournament', 'poker-tournament-import'); ?></span>
                                <?php endif; ?>
                                <?php if ($key === $active_season): ?>
                                    <span style="color: #46b450;"><?php esc_html_e('Active Season', 'poker-tournament-import'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button" onclick="openFormulaModal('<?php echo esc_js($key); ?>')">
                                    <?php esc_html_e('Edit', 'poker-tournament-import'); ?>
                                </button>
                                <?php if (!isset($formula_validator->get_default_formulas()[$key])): ?>
                                    <button type="button" class="button" onclick="deleteFormula('<?php echo esc_js($key); ?>')">
                                        <?php esc_html_e('Delete', 'poker-tournament-import'); ?>
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
            <h2><?php esc_html_e('Formula Validator', 'poker-tournament-import'); ?></h2>

            <div class="formula-test">
                <h3><?php esc_html_e('Test Your Formula', 'poker-tournament-import'); ?></h3>

                <div class="form-field">
                    <label for="formula-input"><?php esc_html_e('Formula:', 'poker-tournament-import'); ?></label>
                    <textarea id="formula-input" rows="6" style="width: 100%; font-family: monospace;"
                              placeholder="assign(&quot;points&quot;, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10))"></textarea>
                </div>

                <div class="form-field">
                    <label><?php esc_html_e('Test Data:', 'poker-tournament-import'); ?></label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <input type="number" id="test-n" placeholder="Total Players (n)" value="20">
                        <input type="number" id="test-r" placeholder="Finish Position (r)" value="3">
                        <input type="number" id="test-hits" placeholder="Hits (numberofHits)" value="5">
                        <input type="number" id="test-money" placeholder="Total Money" value="2000">
                        <input type="number" id="test-buyins" placeholder="Buy-ins" value="20">
                    </div>
                </div>

                <button type="button" class="button button-primary" onclick="pokerValidateFormula()">
                    <?php esc_html_e('Validate & Test', 'poker-tournament-import'); ?>
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
                nonce: '<?php echo esc_attr(wp_create_nonce("poker_formula_validator")); ?>'
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
            <h2><?php esc_html_e('Formula Settings', 'poker-tournament-import'); ?></h2>

            <form method="post" action="options.php">
                <?php settings_fields('poker_formula_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="poker_active_tournament_formula"><?php esc_html_e('Active Tournament Formula', 'poker-tournament-import'); ?></label>
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
                            <p class="description"><?php esc_html_e('Formula used for calculating tournament points.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="poker_active_season_formula"><?php esc_html_e('Active Season Formula', 'poker-tournament-import'); ?></label>
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
                            <p class="description"><?php esc_html_e('Formula used for calculating season standings.', 'poker-tournament-import'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="poker_formula_debug_mode"><?php esc_html_e('Debug Mode', 'poker-tournament-import'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="poker_formula_debug_mode" id="poker_formula_debug_mode"
                                   value="1" <?php checked(get_option('poker_formula_debug_mode', 0)); ?>>
                            <label for="poker_formula_debug_mode"><?php esc_html_e('Enable formula debugging and logging', 'poker-tournament-import'); ?></label>
                            <p class="description"><?php esc_html_e('When enabled, formula calculations will be logged for troubleshooting.', 'poker-tournament-import'); ?></p>
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
            <h2><?php esc_html_e('Available Variables', 'poker-tournament-import'); ?></h2>
            <p><?php esc_html_e('These variables can be used in your formulas. They are automatically populated with tournament data.', 'poker-tournament-import'); ?></p>

            <div class="variable-list">
                <?php foreach ($variables as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?></span>
                        <span class="variable-type">(<?php echo esc_html($info['type']); ?>)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('Available Functions', 'poker-tournament-import'); ?></h2>
            <p><?php esc_html_e('These mathematical functions can be used in your formulas.', 'poker-tournament-import'); ?></p>

            <div class="variable-list">
                <?php foreach ($functions as $name => $info): ?>
                    <div class="variable-item">
                        <span class="variable-name"><?php echo esc_html($name); ?>()</span>
                        <span class="variable-type">(<?php echo esc_html($info['params']); ?> params)</span>
                        <p><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('Formula Examples', 'poker-tournament-import'); ?></h2>

            <div class="formula-examples">
                <h3><?php esc_html_e('Simple Linear Points', 'poker-tournament-import'); ?></h3>
                <div class="formula-code">assign("points", (n - r + 1) * 10)</div>

                <h3><?php esc_html_e('Tournament Director Default', 'poker-tournament-import'); ?></h3>
                <div class="formula-code">assign("T33", round(n/3))
assign("T80", floor(n*0.9))
assign("monies", totalBuyInsAmount + totalRebuysAmount + totalAddOnsAmount)
assign("avgBC", monies/buyins)
assign("temp", 10 * (sqrt(n) / sqrt(T33 + 1)) * (1 + log(avgBC + 0.25)) + (numberofHits * 10))
assign("points", if(T80 > r and T33 < r, round(temp * pow(0.66, (r-T33))) + (numberofHits * 10), if(T33 >= r, round(10 * (sqrt(n) / sqrt(r)) * (1 + log(avgBC + 0.25))) + (numberofHits * 10), 1)))</div>

                <h3><?php esc_html_e('Exponential Decay', 'poker-tournament-import'); ?></h3>
                <div class="formula-code">assign("points", round(100 * pow(0.9, r-1)))</div>

                <h3><?php esc_html_e('Position-Based Bonus', 'poker-tournament-import'); ?></h3>
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
            register_setting('poker_formula_settings', 'poker_active_tournament_formula', array(
                'sanitize_callback' => 'sanitize_text_field'
            ));
            register_setting('poker_formula_settings', 'poker_active_season_formula', array(
                'sanitize_callback' => 'sanitize_text_field'
            ));
            register_setting('poker_formula_settings', 'poker_formula_debug_mode', array(
                'sanitize_callback' => 'boolval'
            ));
        }
    }
}