<?php
/**
 * Shortcode Help and Documentation Page
 *
 * Comprehensive guide for integrating poker tournament shortcodes
 * into WordPress pages and posts
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Shortcode_Help_Page {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_help_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_help_assets'));
    }

    public function enqueue_help_assets($hook) {
        if ('tournament_page_poker-shortcode-help' !== $hook) return;

        // Enqueue styles
        wp_enqueue_style(
            'poker-shortcode-help',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/shortcode-help.css',
            array(),
            POKER_TOURNAMENT_IMPORT_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'poker-shortcode-help',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/shortcode-help.js',
            array('jquery'),
            POKER_TOURNAMENT_IMPORT_VERSION,
            true
        );
    }

    /**
     * Add admin menu
     */
    public function add_help_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=tournament',
            __('Shortcode Help', 'poker-tournament-import'),
            __('Shortcode Help', 'poker-tournament-import'),
            'manage_options',
            'poker-shortcode-help',
            array($this, 'render_help_page')
        );
    }

    /**
     * Render the help page
     */
    public function render_help_page() {
        ?>
        <div class="wrap poker-shortcode-help">
            <h1><?php esc_html_e('Poker Tournament Shortcodes Guide', 'poker-tournament-import'); ?></h1>

            <div class="help-navigation">
                <ul class="help-nav">
                    <li><a href="#getting-started" class="nav-active"><?php esc_html_e('Getting Started', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#tournament-shortcodes"><?php esc_html_e('Tournament Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#series-shortcodes"><?php esc_html_e('Series Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#season-shortcodes"><?php esc_html_e('Season Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#player-shortcodes"><?php esc_html_e('Player Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#advanced-usage"><?php esc_html_e('Advanced Usage', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#troubleshooting"><?php esc_html_e('Troubleshooting', 'poker-tournament-import'); ?></a></li>
                </ul>
            </div>

            <div class="help-content">
                <?php $this->render_getting_started_section(); ?>
                <?php $this->render_tournament_shortcodes_section(); ?>
                <?php $this->render_series_shortcodes_section(); ?>
                <?php $this->render_season_shortcodes_section(); ?>
                <?php $this->render_player_shortcodes_section(); ?>
                <?php $this->render_advanced_usage_section(); ?>
                <?php $this->render_troubleshooting_section(); ?>
            </div>
        </div>


        <?php
    }

    /**
     * Render getting started section
     */
    private function render_getting_started_section() {
        ?>
        <section id="getting-started" class="help-section">
            <h2><?php esc_html_e('Getting Started with Shortcodes', 'poker-tournament-import'); ?></h2>

            <p><?php esc_html_e('Shortcodes are simple codes you can add to any WordPress page or post to display tournament information. This guide will show you how to use all available shortcodes effectively.', 'poker-tournament-import'); ?></p>

            <h3><?php esc_html_e('How to Add Shortcodes', 'poker-tournament-import'); ?></h3>

            <div class="tabbed-interface">
                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="editor"><?php esc_html_e('Block Editor', 'poker-tournament-import'); ?></button>
                    <button class="tab-button" data-tab="classic"><?php esc_html_e('Classic Editor', 'poker-tournament-import'); ?></button>
                    <button class="tab-button" data-tab="html"><?php esc_html_e('HTML Editor', 'poker-tournament-import'); ?></button>
                </div>

                <div class="tab-content">
                    <div id="editor-content">
                        <h4><?php esc_html_e('WordPress Block Editor (Gutenberg)', 'poker-tournament-import'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Edit your page or post', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Click the + icon to add a new block', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Search for "Shortcode" or "Custom HTML"', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Add the block and paste your shortcode', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Click "Preview" or "Update" to see results', 'poker-tournament-import'); ?></li>
                        </ol>
                    </div>

                    <div id="classic-content" style="display: none;">
                        <h4><?php esc_html_e('Classic WordPress Editor', 'poker-tournament-import'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Edit your page or post', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Click the "Add Media" button', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Select "Insert Shortcode"', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Choose your shortcode from the dropdown', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Configure parameters and insert', 'poker-tournament-import'); ?></li>
                        </ol>
                    </div>

                    <div id="html-content" style="display: none;">
                        <h4><?php esc_html_e('Text/HTML Editor', 'poker-tournament-import'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Edit your page or post', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Click the "Text" tab in the editor', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Paste your shortcode directly', 'poker-tournament-import'); ?></li>
                            <li><?php esc_html_e('Save or update your page', 'poker-tournament-import'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="tip-box">
                <strong><?php esc_html_e('Pro Tip:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('You can mix and match multiple shortcodes on the same page to create comprehensive tournament displays.', 'poker-tournament-import'); ?>
            </div>

            <h3><?php esc_html_e('Finding Tournament IDs', 'poker-tournament-import'); ?></h3>

            <p><?php esc_html_e('Most shortcodes require a tournament, series, or season ID. Here\'s how to find them:', 'poker-tournament-import'); ?></p>

            <ol>
                <li><?php esc_html_e('Go to <strong>Tournaments</strong> → <strong>All Tournaments</strong>', 'poker-tournament-import'); ?></li>
                <li><?php esc_html_e('Hover over any tournament title and look at the URL in your browser', 'poker-tournament-import'); ?></li>
                <li><?php esc_html_e('The ID will be in the URL like: <code>post.php?post=123</code> (ID is 123)', 'poker-tournament-import'); ?></li>
                <li><?php esc_html_e('Alternatively, check the "Shortcode Helper" meta box on the tournament edit page', 'poker-tournament-import'); ?></li>
            </ol>
        </section>
        <?php
    }

    /**
     * Render tournament shortcodes section
     */
    private function render_tournament_shortcodes_section() {
        ?>
        <section id="tournament-shortcodes" class="help-section">
            <h2><?php esc_html_e('Tournament Shortcodes', 'poker-tournament-import'); ?></h2>

            <div class="shortcode-block">
                <strong>[tournament_results id="123" show_players="true" show_structure="false"]</strong>
            </div>

            <h3><?php esc_html_e('Basic Tournament Display', 'poker-tournament-import'); ?></h3>

            <table class="attribute-table">
                <tr>
                    <th><?php esc_html_e('Attribute', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Default', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Required', 'poker-tournament-import'); ?></th>
                </tr>
                <tr>
                    <td class="required-attr">id</td>
                    <td><?php esc_html_e('Tournament ID number', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php esc_html_e('Yes', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_players</td>
                    <td><?php esc_html_e('Show player results table', 'poker-tournament-import'); ?></td>
                    <td>true</td>
                    <td><?php esc_html_e('No', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_structure</td>
                    <td><?php esc_html_e('Show tournament statistics', 'poker-tournament-import'); ?></td>
                    <td>false</td>
                    <td><?php esc_html_e('No', 'poker-tournament-import'); ?></td>
                </tr>
            </table>

            <div class="example-box">
                <h4><?php esc_html_e('Complete Tournament Page Example', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[tournament_results id="123" show_players="true" show_structure="true"]</strong>
                </div>
                <p><?php esc_html_e('This creates a full tournament page with player results, statistics, and detailed information.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php esc_html_e('What This Shortcode Shows:', 'poker-tournament-import'); ?></h3>

            <ul>
                <li>✅ <?php esc_html_e('Tournament title and metadata', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Date and location information', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Prize pool and buy-in details', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Complete player results with positions', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Achievement badges (🏆🥈🥉🎯💭⚔️)', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Player points and winnings', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Tournament statistics summary', 'poker-tournament-import'); ?></li>
            </ul>
        </section>
        <?php
    }

    /**
     * Render series shortcodes section
     */
    private function render_series_shortcodes_section() {
        ?>
        <section id="series-shortcodes" class="help-section">
            <h2><?php esc_html_e('Series Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><?php esc_html_e('Series Standings (New)', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[series_standings id="123" formula="season_total" show_details="true"]</strong>
            </div>

            <table class="attribute-table">
                <tr>
                    <th><?php esc_html_e('Attribute', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Default', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Required', 'poker-tournament-import'); ?></th>
                </tr>
                <tr>
                    <td class="required-attr">id</td>
                    <td><?php esc_html_e('Series ID number', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php esc_html_e('Yes', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">formula</td>
                    <td><?php esc_html_e('Season formula to use (season_total, season_best, etc.)', 'poker-tournament-import'); ?></td>
                    <td>season_total</td>
                    <td><?php esc_html_e('No', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_details</td>
                    <td><?php esc_html_e('Show detailed statistics columns', 'poker-tournament-import'); ?></td>
                    <td>false</td>
                    <td><?php esc_html_e('No', 'poker-tournament-import'); ?></td>
                </tr>
            </table>

            <h3><?php esc_html_e('Tabbed Series Interface', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[series_tabs series_id="123" active="overview"]</strong>
            </div>

            <h3><?php esc_html_e('Individual Series Tabs', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php esc_html_e('Series Overview', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_overview id="123" limit="10"]</strong>
                </div>

                <h4><?php esc_html_e('Series Results', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_results id="123" limit="20"]</strong>
                </div>

                <h4><?php esc_html_e('Series Statistics', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_statistics id="123"]</strong>
                </div>

                <h4><?php esc_html_e('Series Players', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_players id="123"]</strong>
                </div>
            </div>

            <h3><?php esc_html_e('Complete Series Page Example', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <div class="shortcode-block">
                    <strong>[series_tabs series_id="123"]</strong>
                </div>
                <p><?php esc_html_e('This creates a modern tabbed interface with Overview, Results, Statistics, and Players tabs. Perfect for series landing pages!', 'poker-tournament-import'); ?></p>
            </div>

            <div class="tip-box">
                <strong><?php esc_html_e('Pro Tip:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Use the tabbed interface for a modern, professional look. It automatically handles navigation and content loading.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Render season shortcodes section
     */
    private function render_season_shortcodes_section() {
        ?>
        <section id="season-shortcodes" class="help-section">
            <h2><?php esc_html_e('Season Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><?php esc_html_e('Season Standings (New)', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[season_standings id="456" formula="season_total" show_details="true"]</strong>
            </div>

            <h3><?php esc_html_e('Tabbed Season Interface', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[season_tabs season_id="456" active="overview"]</strong>
            </div>

            <h3><?php esc_html_e('Individual Season Tabs', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php esc_html_e('Season Overview', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_overview id="456" limit="10"]</strong>
                </div>

                <h4><?php esc_html_e('Season Results', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_results id="456" limit="20"]</strong>
                </div>

                <h4><?php esc_html_e('Season Statistics', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_statistics id="456"]</strong>
                </div>

                <h4><?php esc_html_e('Season Players', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_players id="456"]</strong>
                </div>
            </div>

            <div class="tip-box">
                <strong><?php esc_html_e('Note:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Season shortcodes work similarly to series shortcodes but aggregate data across all tournaments in a season.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Render player shortcodes section
     */
    private function render_player_shortcodes_section() {
        ?>
        <section id="player-shortcodes" class="help-section">
            <h2><?php esc_html_e('Player Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><?php esc_html_e('Player Profile', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[player_profile name="John Doe" show_stats="true"]</strong>
            </div>

            <h3><?php esc_html_e('Player Profile Attributes', 'poker-tournament-import'); ?></h3>

            <table class="attribute-table">
                <tr>
                    <th><?php esc_html_e('Attribute', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Default', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Required', 'poker-tournament-import'); ?></th>
                </tr>
                <tr>
                    <td class="optional-attr">name</td>
                    <td><?php esc_html_e('Player name (exact match)', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php esc_html_e('No*', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">id</td>
                    <td><?php esc_html_e('Player post ID', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php esc_html_e('No*', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_stats</td>
                    <td><?php esc_html_e('Show career statistics', 'poker-tournament-import'); ?></td>
                    <td>true</td>
                    <td><?php esc_html_e('No', 'poker-tournament-import'); ?></td>
                </tr>
            </table>

            <div class="warning-box">
                <strong><?php esc_html_e('Important:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('You must provide either "name" or "id" but not both.', 'poker-tournament-import'); ?>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('Example by Name:', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[player_profile name="Sarah Smith"]</strong>
                </div>

                <h4><?php esc_html_e('Example by ID:', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[player_profile id="789"]</strong>
                </div>
            </div>

            <h3><?php esc_html_e('What This Shows:', 'poker-tournament-import'); ?></h3>

            <ul>
                <li>✅ <?php esc_html_e('Player name and profile information', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Career statistics summary', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Tournaments played count', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Total winnings and best cash', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Average and best finish positions', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Recent tournament history', 'poker-tournament-import'); ?></li>
            </ul>
        </section>
        <?php
    }

    /**
     * Render advanced usage section
     */
    private function render_advanced_usage_section() {
        ?>
        <section id="advanced-usage" class="helpcode-section">
            <h2><?php esc_html_e('Advanced Usage Examples', 'poker-tournament-import'); ?></h2>

            <h3><?php esc_html_e('Creating a Tournament Landing Page', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php esc_html_e('Complete Tournament Landing Page', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <h2><?php esc_html_e('2023 Poker Championship Series', 'poker-tournament-import'); ?></h2>

                    <p><?php esc_html_e('Welcome to our championship series! Check out the latest results and standings below.', 'poker-tournament-import'); ?></p>

                    <h3><?php esc_html_e('Latest Tournament', 'poker-tournament-import'); ?></h3>
                    [tournament_results id="456" show_players="true"]

                    <h3><?php esc_html_e('Series Standings', 'poker-tournament-import'); ?></h3>
                    [series_standings id="123" show_details="true" formula="season_total"]
                </div>
                <p><?php esc_html_e('This creates a complete landing page with tournament results and current standings.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php esc_html_e('Creating a Player Profile Page', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <div class="shortcode-block">
                    [player_profile name="John Smith" show_stats="true"]

                    <h3><?php esc_html_e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                    [tournament_results id="123"]
                    [tournament_results id="124"]
                    [tournament_results id="125"]
                </div>
            </div>

            <h3><?php esc_html_e('Creating a Series Hub Page', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <div class="shortcode-block">
                    <h2><?php esc_html_e('Weekly Poker Series', 'poker-tournament-import'); ?></h2>

                    <p><?php esc_html_e('Join us every Tuesday night for our weekly poker series! Check out current standings and recent results.', 'poker-tournament-import'); ?></p>

                    [series_tabs series_id="789" active="overview"]
                </div>
            </div>

            <h3><?php esc_html_e('Custom Styling Integration', 'poker-tournament-import'); ?></h3>

            <div class="tip-box">
                <strong><?php esc_html_e('Pro Tip:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('All shortcodes include CSS classes that you can target with custom CSS. Common classes include: <code>.tournament-results</code>, <code>.series-standings</code>, <code>.player-profile</code>, and many more.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Render troubleshooting section
     */
    private function render_troubleshooting_section() {
        ?>
        <section id="troubleshooting" class="help-section">
            <h2><?php esc_html_e('Troubleshooting', 'poker-tournament-import'); ?></h2>

            <h3><?php esc_html_e('Common Issues and Solutions', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php esc_html_e('Issue: Shortcode shows "Tournament ID required"', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Make sure you\'re using the correct tournament ID. Check the URL when editing the tournament (look for <code>post=123</code> where 123 is the ID).', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('Issue: Player profile shows "Player not found"', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Ensure the player name matches exactly (case-sensitive). You can also use the player ID instead of name for more reliable results.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('Issue: Tables appear empty or show no data', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Check that tournaments have been imported with results data. Go to Tournaments → Import to upload .tdt files.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('Issue: Formulas not calculating correctly', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Go to Tournaments → Formulas to configure your custom formulas. Check the formula syntax and variables being used.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php esc_html_e('Getting Help', 'poker-tournament-import'); ?></h3>

            <ul>
                <li>📖 <?php esc_html_e('Check the Formula Manager for syntax help', 'poker-tournament-import'); ?></li>
                <li>🔍 <?php esc_html_e('Use the shortcode preview feature to test before publishing', 'poker-tournament-import'); ?></li>
                <li>💾 <?php esc_html_e('Backup your content before making changes', 'poker-tournament-import'); ?></li>
                <li>📞 <?php esc_html_e('Contact support if issues persist', 'poker-tournament-import'); ?></li>
            </ul>

            <div class="warning-box">
                <strong><?php esc_html_e('Important:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Always test shortcodes in a draft page before publishing to ensure they work as expected.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Register settings
     */
    public function register_help_settings() {
        register_setting('poker_shortcode_help', 'poker_shortcode_help_displayed', array(
            'sanitize_callback' => 'boolval'
        ));
    }
}
