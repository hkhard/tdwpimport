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
            <h1><?php _e('Poker Tournament Shortcodes Guide', 'poker-tournament-import'); ?></h1>

            <div class="help-navigation">
                <ul class="help-nav">
                    <li><a href="#getting-started" class="nav-active"><?php _e('Getting Started', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#tournament-shortcodes"><?php _e('Tournament Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#series-shortcodes"><?php _e('Series Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#season-shortcodes"><?php _e('Season Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#player-shortcodes"><?php _e('Player Shortcodes', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#advanced-usage"><?php _e('Advanced Usage', 'poker-tournament-import'); ?></a></li>
                    <li><a href="#troubleshooting"><?php _e('Troubleshooting', 'poker-tournament-import'); ?></a></li>
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

        <style>
        .poker-shortcode-help {
            max-width: 1200px;
        }
        .help-navigation {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .help-nav {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .help-nav li {
            margin: 0;
        }
        .help-nav a {
            display: block;
            padding: 8px 16px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            transition: all 0.2s ease;
        }
        .help-nav a:hover,
        .help-nav a.nav-active {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        .help-section {
            margin-bottom: 40px;
            padding: 25px;
            background: #fff;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .help-section h2 {
            margin-top: 0;
            color: #23282d;
            font-size: 24px;
            font-weight: 600;
        }
        .help-section h3 {
            color: #3c434a;
            font-size: 18px;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .shortcode-block {
            background: #f8f9f9;
            border: 1px solid #ddd;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .shortcode-block strong {
            color: #0073aa;
        }
        .example-box {
            background: #fff;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }
        .example-box h4 {
            margin-top: 0;
            color: #0073aa;
        }
        .attribute-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .attribute-table th,
        .attribute-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .attribute-table th {
            background: #f8f9f9;
            font-weight: 600;
        }
        .attribute-table tr:nth-child(even) {
            background: #f8f9f9;
        }
        .required-attr {
            color: #d63638;
            font-weight: 600;
        }
        .optional-attr {
            color: #3c434a;
        }
        .tip-box {
            background: #e7f3ff;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fef7ed;
            border-left: 4px solid #d63638;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .video-embed {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin: 20px 0;
        }
        .tabbed-interface {
            display: flex;
            margin: 20px 0;
        }
        .tab-buttons {
            display: flex;
            background: #f8f9f9;
        }
        .tab-button {
            padding: 10px 20px;
            border: none;
            background: #f8f9f9;
            cursor: pointer;
            border-right: 1px solid #ddd;
        }
        .tab-button.active {
            background: #fff;
            border-bottom: 2px solid #0073aa;
        }
        .tab-content {
            flex: 1;
            padding: 20px;
            border: 1px solid #ddd;
            background: #fff;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.help-nav a').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');

                $('.help-nav a').removeClass('nav-active');
                $(this).addClass('nav-active');

                // Smooth scroll to section
                $('html, body').animate({
                    scrollTop: $(target).offset().top - 100
                }, 500);
            });

            // Highlight current section on scroll
            $(window).scroll(function() {
                var scrollPosition = $(window).scrollTop() + 150;

                $('.help-section').each(function() {
                    var sectionTop = $(this).offset().top;
                    var sectionBottom = sectionTop + $(this).outerHeight();

                    if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                        var sectionId = '#' + $(this).attr('id');
                        $('.help-nav a').removeClass('nav-active');
                        $('.help-nav a[href="' + sectionId + '"]').addClass('nav-active');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render getting started section
     */
    private function render_getting_started_section() {
        ?>
        <section id="getting-started" class="help-section">
            <h2><?php _e('Getting Started with Shortcodes', 'poker-tournament-import'); ?></h2>

            <p><?php _e('Shortcodes are simple codes you can add to any WordPress page or post to display tournament information. This guide will show you how to use all available shortcodes effectively.', 'poker-tournament-import'); ?></p>

            <h3><?php _e('How to Add Shortcodes', 'poker-tournament-import'); ?></h3>

            <div class="tabbed-interface">
                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="editor"><?php _e('Block Editor', 'poker-tournament-import'); ?></button>
                    <button class="tab-button" data-tab="classic"><?php _e('Classic Editor', 'poker-tournament-import'); ?></button>
                    <button class="tab-button" data-tab="html"><?php _e('HTML Editor', 'poker-tournament-import'); ?></button>
                </div>

                <div class="tab-content">
                    <div id="editor-content">
                        <h4><?php _e('WordPress Block Editor (Gutenberg)', 'poker-tournament-import'); ?></h4>
                        <ol>
                            <li><?php _e('Edit your page or post', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Click the + icon to add a new block', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Search for "Shortcode" or "Custom HTML"', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Add the block and paste your shortcode', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Click "Preview" or "Update" to see results', 'poker-tournament-import'); ?></li>
                        </ol>
                    </div>

                    <div id="classic-content" style="display: none;">
                        <h4><?php _e('Classic WordPress Editor', 'poker-tournament-import'); ?></h4>
                        <ol>
                            <li><?php _e('Edit your page or post', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Click the "Add Media" button', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Select "Insert Shortcode"', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Choose your shortcode from the dropdown', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Configure parameters and insert', 'poker-tournament-import'); ?></li>
                        </ol>
                    </div>

                    <div id="html-content" style="display: none;">
                        <h4><?php _e('Text/HTML Editor', 'poker-tournament-import'); ?></h4>
                        <ol>
                            <li><?php _e('Edit your page or post', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Click the "Text" tab in the editor', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Paste your shortcode directly', 'poker-tournament-import'); ?></li>
                            <li><?php _e('Save or update your page', 'poker-tournament-import'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="tip-box">
                <strong><?php _e('Pro Tip:', 'poker-tournament-import'); ?></strong> <?php _e('You can mix and match multiple shortcodes on the same page to create comprehensive tournament displays.', 'poker-tournament-import'); ?>
            </div>

            <h3><?php _e('Finding Tournament IDs', 'poker-tournament-import'); ?></h3>

            <p><?php _e('Most shortcodes require a tournament, series, or season ID. Here\'s how to find them:', 'poker-tournament-import'); ?></p>

            <ol>
                <li><?php _e('Go to <strong>Tournaments</strong> → <strong>All Tournaments</strong>', 'poker-tournament-import'); ?></li>
                <li><?php _e('Hover over any tournament title and look at the URL in your browser', 'poker-tournament-import'); ?></li>
                <li><?php _e('The ID will be in the URL like: <code>post.php?post=123</code> (ID is 123)', 'poker-tournament-import'); ?></li>
                <li><?php _e('Alternatively, check the "Shortcode Helper" meta box on the tournament edit page', 'poker-tournament-import'); ?></li>
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
            <h2><?php _e('Tournament Shortcodes', 'poker-tournament-import'); ?></h2>

            <div class="shortcode-block">
                <strong>[tournament_results id="123" show_players="true" show_structure="false"]</strong>
            </div>

            <h3><?php _e('Basic Tournament Display', 'poker-tournament-import'); ?></h3>

            <table class="attribute-table">
                <tr>
                    <th><?php _e('Attribute', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Description', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Default', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Required', 'poker-tournament-import'); ?></th>
                </tr>
                <tr>
                    <td class="required-attr">id</td>
                    <td><?php _e('Tournament ID number', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php _e('Yes', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_players</td>
                    <td><?php _e('Show player results table', 'poker-tournament-import'); ?></td>
                    <td>true</td>
                    <td><?php _e('No', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_structure</td>
                    <td><?php _e('Show tournament statistics', 'poker-tournament-import'); ?></td>
                    <td>false</td>
                    <td><?php _e('No', 'poker-tournament-import'); ?></td>
                </tr>
            </table>

            <div class="example-box">
                <h4><?php _e('Complete Tournament Page Example', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[tournament_results id="123" show_players="true" show_structure="true"]</strong>
                </div>
                <p><?php _e('This creates a full tournament page with player results, statistics, and detailed information.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php _e('What This Shortcode Shows:', 'poker-tournament-import'); ?></h3>

            <ul>
                <li>✅ <?php _e('Tournament title and metadata', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Date and location information', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Prize pool and buy-in details', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Complete player results with positions', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Achievement badges (🏆🥈🥉🎯💭⚔️)', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Player points and winnings', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Tournament statistics summary', 'poker-tournament-import'); ?></li>
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
            <h2><?php _e('Series Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><?php _e('Series Standings (New)', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[series_standings id="123" formula="season_total" show_details="true"]</strong>
            </div>

            <table class="attribute-table">
                <tr>
                    <th><?php _e('Attribute', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Description', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Default', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Required', 'poker-tournament-import'); ?></th>
                </tr>
                <tr>
                    <td class="required-attr">id</td>
                    <td><?php _e('Series ID number', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php _e('Yes', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">formula</td>
                    <td><?php _e('Season formula to use (season_total, season_best, etc.)', 'poker-tournament-import'); ?></td>
                    <td>season_total</td>
                    <td><?php _e('No', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_details</td>
                    <td><?php _e('Show detailed statistics columns', 'poker-tournament-import'); ?></td>
                    <td>false</td>
                    <td><?php _e('No', 'poker-tournament-import'); ?></td>
                </tr>
            </table>

            <h3><?php _e('Tabbed Series Interface', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[series_tabs series_id="123" active="overview"]</strong>
            </div>

            <h3><?php _e('Individual Series Tabs', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php _e('Series Overview', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_overview id="123" limit="10"]</strong>
                </div>

                <h4><?php _e('Series Results', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_results id="123" limit="20"]</strong>
                </div>

                <h4><?php _e('Series Statistics', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_statistics id="123"]</strong>
                </div>

                <h4><?php _e('Series Players', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[series_players id="123"]</strong>
                </div>
            </div>

            <h3><?php _e('Complete Series Page Example', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <div class="shortcode-block">
                    <strong>[series_tabs series_id="123"]</strong>
                </div>
                <p><?php _e('This creates a modern tabbed interface with Overview, Results, Statistics, and Players tabs. Perfect for series landing pages!', 'poker-tournament-import'); ?></p>
            </div>

            <div class="tip-box">
                <strong><?php _e('Pro Tip:', 'poker-tournament-import'); ?></strong> <?php _e('Use the tabbed interface for a modern, professional look. It automatically handles navigation and content loading.', 'poker-tournament-import'); ?>
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
            <h2><?php _e('Season Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><?php _e('Season Standings (New)', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[season_standings id="456" formula="season_total" show_details="true"]</strong>
            </div>

            <h3><?php _e('Tabbed Season Interface', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[season_tabs season_id="456" active="overview"]</strong>
            </div>

            <h3><?php _e('Individual Season Tabs', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php _e('Season Overview', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_overview id="456" limit="10"]</strong>
                </div>

                <h4><?php _e('Season Results', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_results id="456" limit="20"]</strong>
                </div>

                <h4><?php _e('Season Statistics', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_statistics id="456"]</strong>
                </div>

                <h4><?php _e('Season Players', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[season_players id="456"]</strong>
                </div>
            </div>

            <div class="tip-box">
                <strong><?php _e('Note:', 'poker-tournament-import'); ?></strong> <?php _e('Season shortcodes work similarly to series shortcodes but aggregate data across all tournaments in a season.', 'poker-tournament-import'); ?>
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
            <h2><?php _e('Player Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><?php _e('Player Profile', 'poker-tournament-import'); ?></h3>

            <div class="shortcode-block">
                <strong>[player_profile name="John Doe" show_stats="true"]</strong>
            </div>

            <h3><?php _e('Player Profile Attributes', 'poker-tournament-import'); ?></h3>

            <table class="attribute-table">
                <tr>
                    <th><?php _e('Attribute', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Description', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Default', 'poker-tournament-import'); ?></th>
                    <th><?php _e('Required', 'poker-tournament-import'); ?></th>
                </tr>
                <tr>
                    <td class="optional-attr">name</td>
                    <td><?php _e('Player name (exact match)', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php _e('No*', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">id</td>
                    <td><?php _e('Player post ID', 'poker-tournament-import'); ?></td>
                    <td>-</td>
                    <td><?php _e('No*', 'poker-tournament-import'); ?></td>
                </tr>
                <tr>
                    <td class="optional-attr">show_stats</td>
                    <td><?php _e('Show career statistics', 'poker-tournament-import'); ?></td>
                    <td>true</td>
                    <td><?php _e('No', 'poker-tournament-import'); ?></td>
                </tr>
            </table>

            <div class="warning-box">
                <strong><?php _e('Important:', 'poker-tournament-import'); ?></strong> <?php _e('You must provide either "name" or "id" but not both.', 'poker-tournament-import'); ?>
            </div>

            <div class="example-box">
                <h4><?php _e('Example by Name:', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[player_profile name="Sarah Smith"]</strong>
                </div>

                <h4><?php _e('Example by ID:', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <strong>[player_profile id="789"]</strong>
                </div>
            </div>

            <h3><?php _e('What This Shows:', 'poker-tournament-import'); ?></h3>

            <ul>
                <li>✅ <?php _e('Player name and profile information', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Career statistics summary', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Tournaments played count', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Total winnings and best cash', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Average and best finish positions', 'poker-tournament-import'); ?></li>
                <li>✅ <?php _e('Recent tournament history', 'poker-tournament-import'); ?></li>
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
            <h2><?php _e('Advanced Usage Examples', 'poker-tournament-import'); ?></h2>

            <h3><?php _e('Creating a Tournament Landing Page', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php _e('Complete Tournament Landing Page', 'poker-tournament-import'); ?></h4>
                <div class="shortcode-block">
                    <h2><?php _e('2023 Poker Championship Series', 'poker-tournament-import'); ?></h2>

                    <p><?php _e('Welcome to our championship series! Check out the latest results and standings below.', 'poker-tournament-import'); ?></p>

                    <h3><?php _e('Latest Tournament', 'poker-tournament-import'); ?></h3>
                    [tournament_results id="456" show_players="true"]

                    <h3><?php _e('Series Standings', 'poker-tournament-import'); ?></h3>
                    [series_standings id="123" show_details="true" formula="season_total"]
                </div>
                <p><?php _e('This creates a complete landing page with tournament results and current standings.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php _e('Creating a Player Profile Page', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <div class="shortcode-block">
                    [player_profile name="John Smith" show_stats="true"]

                    <h3><?php _e('Recent Tournaments', 'poker-tournament-import'); ?></h3>
                    [tournament_results id="123"]
                    [tournament_results id="124"]
                    [tournament_results id="125"]
                </div>
            </div>

            <h3><?php _e('Creating a Series Hub Page', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <div class="shortcode-block">
                    <h2><?php _e('Weekly Poker Series', 'poker-tournament-import'); ?></h2>

                    <p><?php _e('Join us every Tuesday night for our weekly poker series! Check out current standings and recent results.', 'poker-tournament-import'); ?></p>

                    [series_tabs series_id="789" active="overview"]
                </div>
            </div>

            <h3><?php _e('Custom Styling Integration', 'poker-tournament-import'); ?></h3>

            <div class="tip-box">
                <strong><?php _e('Pro Tip:', 'poker-tournament-import'); ?></strong> <?php _e('All shortcodes include CSS classes that you can target with custom CSS. Common classes include: <code>.tournament-results</code>, <code>.series-standings</code>, <code>.player-profile</code>, and many more.', 'poker-tournament-import'); ?>
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
            <h2><?php _e('Troubleshooting', 'poker-tournament-import'); ?></h2>

            <h3><?php _e('Common Issues and Solutions', 'poker-tournament-import'); ?></h3>

            <div class="example-box">
                <h4><?php _e('Issue: Shortcode shows "Tournament ID required"', 'poker-tournament-import'); ?></h4>
                <p><strong><?php _e('Solution:', 'poker-tournament-import'); ?></strong> <?php _e('Make sure you\'re using the correct tournament ID. Check the URL when editing the tournament (look for <code>post=123</code> where 123 is the ID).', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php _e('Issue: Player profile shows "Player not found"', 'poker-tournament-import'); ?></h4>
                <p><strong><?php _e('Solution:', 'poker-tournament-import'); ?></strong> <?php _e('Ensure the player name matches exactly (case-sensitive). You can also use the player ID instead of name for more reliable results.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php _e('Issue: Tables appear empty or show no data', 'poker-tournament-import'); ?></h4>
                <p><strong><?php _e('Solution:', 'poker-tournament-import'); ?></strong> <?php _e('Check that tournaments have been imported with results data. Go to Tournaments → Import to upload .tdt files.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php _e('Issue: Formulas not calculating correctly', 'poker-tournament-import'); ?></h4>
                <p><strong><?php _e('Solution:', 'poker-tournament-import'); ?></strong> <?php _e('Go to Tournaments → Formulas to configure your custom formulas. Check the formula syntax and variables being used.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php _e('Getting Help', 'poker-tournament-import'); ?></h3>

            <ul>
                <li>📖 <?php _e('Check the Formula Manager for syntax help', 'poker-tournament-import'); ?></li>
                <li>🔍 <?php _e('Use the shortcode preview feature to test before publishing', 'poker-tournament-import'); ?></li>
                <li>💾 <?php _e('Backup your content before making changes', 'poker-tournament-import'); ?></li>
                <li>📞 <?php _e('Contact support if issues persist', 'poker-tournament-import'); ?></li>
            </ul>

            <div class="warning-box">
                <strong><?php _e('Important:', 'poker-tournament-import'); ?></strong> <?php _e('Always test shortcodes in a draft page before publishing to ensure they work as expected.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Register settings
     */
    public function register_help_settings() {
        register_setting('poker_shortcode_help', 'poker_shortcode_help_displayed');
    }
}