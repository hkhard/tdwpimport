<?php
/**
 * Shortcode Help and Documentation Page
 *
 * Comprehensive guide for integrating poker tournament shortcodes
 * into WordPress pages and posts.
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
        add_action('admin_menu', array($this, 'add_help_admin_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_help_assets'));
    }

    /**
     * Add admin menu entry under the main plugin menu, near the top.
     */
    public function add_help_admin_menu() {
        add_submenu_page(
            'poker-tournament-import',
            __('Shortcodes & Help', 'poker-tournament-import'),
            '📖 ' . __('Shortcodes & Help', 'poker-tournament-import'),
            'manage_options',
            'poker-shortcode-help',
            array($this, 'render_help_page'),
            1
        );
    }

    /**
     * Enqueue styles/scripts only on the help page.
     */
    public function enqueue_help_assets($hook) {
        if (false === strpos((string) $hook, 'poker-shortcode-help')) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_style(
            'poker-shortcode-help',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/shortcode-help.css',
            array(),
            POKER_TOURNAMENT_IMPORT_VERSION
        );

        $inline_js = "
jQuery(document).ready(function($) {
    // Sticky nav scroll-spy.
    $('.help-nav a').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        $('.help-nav a').removeClass('nav-active');
        $(this).addClass('nav-active');
        var \$target = $(target);
        if (\$target.length) {
            $('html, body').animate({ scrollTop: \$target.offset().top - 100 }, 400);
        }
    });

    $(window).on('scroll', function() {
        var scrollPos = $(window).scrollTop() + 150;
        $('.help-section').each(function() {
            var top = $(this).offset().top;
            var bottom = top + $(this).outerHeight();
            if (scrollPos >= top && scrollPos < bottom) {
                var id = '#' + $(this).attr('id');
                $('.help-nav a').removeClass('nav-active');
                $('.help-nav a[href=\"' + id + '\"]').addClass('nav-active');
            }
        });
    });

    // Tabbed interfaces.
    $('.tabbed-interface').each(function() {
        var \$wrap = $(this);
        \$wrap.find('.tab-button').on('click', function() {
            var tab = $(this).data('tab');
            \$wrap.find('.tab-button').removeClass('active');
            $(this).addClass('active');
            \$wrap.find('.tab-content').removeClass('active').hide();
            \$wrap.find('.tab-content[data-tab=\"' + tab + '\"]').addClass('active').show();
        });
    });

    // Click-to-copy shortcode blocks.
    $('.shortcode-block').each(function() {
        var \$block = $(this);
        if (\$block.find('.copy-button').length) {
            return;
        }
        var \$button = $('<button type=\"button\" class=\"copy-button\"></button>').text('Copy');
        \$block.append(\$button);

        var doCopy = function(e) {
            e.stopPropagation();
            var text = \$block.data('copy');
            if (!text) {
                text = \$block.clone().find('.copy-button').remove().end().text().trim();
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(String(text));
            }
            \$button.addClass('copied').text('Copied!');
            setTimeout(function() {
                \$button.removeClass('copied').text('Copy');
            }, 1500);
        };

        \$button.on('click', doCopy);
        \$block.on('click', function(e) {
            if (e.target !== \$button.get(0)) {
                doCopy(e);
            }
        });
    });
});
";
        wp_add_inline_script('jquery', $inline_js);
    }

    /**
     * Render the help page.
     */
    public function render_help_page() {
        ?>
        <div class="wrap poker-shortcode-help">
            <h1><?php esc_html_e('Poker Tournament Shortcodes Guide', 'poker-tournament-import'); ?></h1>

            <?php $this->render_intro(); ?>
            <?php $this->render_navigation(); ?>

            <div class="help-content">
                <?php $this->render_quick_reference_section(); ?>
                <?php $this->render_getting_started_section(); ?>
                <?php $this->render_tutorials_section(); ?>
                <?php $this->render_live_clock_section(); ?>
                <?php $this->render_tournament_shortcodes_section(); ?>
                <?php $this->render_series_shortcodes_section(); ?>
                <?php $this->render_season_shortcodes_section(); ?>
                <?php $this->render_player_shortcodes_section(); ?>
                <?php $this->render_registration_import_section(); ?>
                <?php $this->render_display_screens_section(); ?>
                <?php $this->render_dashboards_section(); ?>
                <?php $this->render_advanced_usage_section(); ?>
                <?php $this->render_troubleshooting_section(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Intro hero.
     */
    private function render_intro() {
        ?>
        <div class="help-intro">
            <p class="help-intro-title"><?php esc_html_e('Embed live and historical poker data anywhere on your site', 'poker-tournament-import'); ?></p>
            <p><?php esc_html_e('Shortcodes let you drop tournament results, series and season standings, player profiles, live clocks, and display screens into any page or post. Most shortcodes need an ID (tournament, series, season, or player) — see "Getting Started" below for how to find it.', 'poker-tournament-import'); ?></p>
            <p><?php esc_html_e('Every code example on this page is click-to-copy — click any shaded shortcode block to copy it to your clipboard.', 'poker-tournament-import'); ?></p>
        </div>
        <?php
    }

    /**
     * Sticky section navigation.
     */
    private function render_navigation() {
        $sections = array(
            'quick-reference'    => __('Quick Reference', 'poker-tournament-import'),
            'getting-started'    => __('Getting Started', 'poker-tournament-import'),
            'tutorials'          => __('Tutorials', 'poker-tournament-import'),
            'live-clock'         => __('Live Clock', 'poker-tournament-import'),
            'tournament-shortcodes' => __('Tournaments', 'poker-tournament-import'),
            'series-shortcodes'  => __('Series', 'poker-tournament-import'),
            'season-shortcodes'  => __('Seasons', 'poker-tournament-import'),
            'player-shortcodes'  => __('Players', 'poker-tournament-import'),
            'registration-import' => __('Registration & Import', 'poker-tournament-import'),
            'display-screens'   => __('Display Screens', 'poker-tournament-import'),
            'dashboards'        => __('Dashboards', 'poker-tournament-import'),
            'advanced-usage'    => __('Advanced Usage', 'poker-tournament-import'),
            'troubleshooting'   => __('Troubleshooting', 'poker-tournament-import'),
        );
        ?>
        <div class="help-navigation">
            <ul class="help-nav">
                <?php $first = true; ?>
                <?php foreach ($sections as $anchor => $label) : ?>
                    <li>
                        <a href="#<?php echo esc_attr($anchor); ?>" class="<?php echo $first ? 'nav-active' : ''; ?>">
                            <?php echo esc_html($label); ?>
                        </a>
                    </li>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Helper: render an attribute table row.
     */
    private function attribute_row($name, $description, $default, $required) {
        $class = $required ? 'required-attr' : 'optional-attr';
        ?>
        <tr>
            <td class="<?php echo esc_attr($class); ?>"><code><?php echo esc_html($name); ?></code></td>
            <td><?php echo esc_html($description); ?></td>
            <td><code><?php echo esc_html($default); ?></code></td>
            <td>
                <?php if ($required) : ?>
                    <span class="pti-badge pti-badge-required"><?php esc_html_e('Required', 'poker-tournament-import'); ?></span>
                <?php else : ?>
                    <span class="pti-badge pti-badge-optional"><?php esc_html_e('Optional', 'poker-tournament-import'); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Helper: open an attribute table.
     */
    private function attribute_table_open() {
        ?>
        <table class="attribute-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Attribute', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Description', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Default', 'poker-tournament-import'); ?></th>
                    <th><?php esc_html_e('Required', 'poker-tournament-import'); ?></th>
                </tr>
            </thead>
            <tbody>
        <?php
    }

    private function attribute_table_close() {
        ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Helper: render a shortcode code block.
     */
    private function shortcode_block($shortcode) {
        ?>
        <div class="shortcode-block" data-copy="<?php echo esc_attr($shortcode); ?>">
            <code><?php echo esc_html($shortcode); ?></code>
        </div>
        <?php
    }

    /**
     * Quick reference section — at-a-glance index of every shortcode.
     */
    private function render_quick_reference_section() {
        $rows = array(
            array('[tournament_results]', __('Full results for one tournament', 'poker-tournament-import'), '#tournament-shortcodes'),
            array('[tournament_series]', __('Series overview linked from a tournament', 'poker-tournament-import'), '#tournament-shortcodes'),
            array('[tournament_clock]', __('Live auto-updating tournament clock', 'poker-tournament-import'), '#live-clock'),
            array('[tdwp_live_clock]', __('Compact embeddable live clock', 'poker-tournament-import'), '#live-clock'),
            array('[series_standings]', __('Formula-based points standings', 'poker-tournament-import'), '#series-shortcodes'),
            array('[series_tabs]', __('Tabbed series interface', 'poker-tournament-import'), '#series-shortcodes'),
            array('[series_overview]', __('Series stat cards + recent tournaments', 'poker-tournament-import'), '#series-shortcodes'),
            array('[series_results]', __('Series tournaments and results', 'poker-tournament-import'), '#series-shortcodes'),
            array('[series_statistics]', __('Aggregate series statistics', 'poker-tournament-import'), '#series-shortcodes'),
            array('[series_players]', __('Unique players in a series', 'poker-tournament-import'), '#series-shortcodes'),
            array('[series_leaderboard]', __('Points/winnings leaderboard', 'poker-tournament-import'), '#series-shortcodes'),
            array('[season_standings]', __('Formula-based season standings', 'poker-tournament-import'), '#season-shortcodes'),
            array('[season_tabs]', __('Tabbed season interface', 'poker-tournament-import'), '#season-shortcodes'),
            array('[season_leaderboard]', __('Leaderboard for the active season', 'poker-tournament-import'), '#season-shortcodes'),
            array('[season_overview]', __('Season stat cards + recent tournaments', 'poker-tournament-import'), '#season-shortcodes'),
            array('[season_results]', __('Season tournaments and results', 'poker-tournament-import'), '#season-shortcodes'),
            array('[season_statistics]', __('Aggregate season statistics', 'poker-tournament-import'), '#season-shortcodes'),
            array('[season_players]', __('Unique players in a season', 'poker-tournament-import'), '#season-shortcodes'),
            array('[player_profile]', __('Player profile by name or ID', 'poker-tournament-import'), '#player-shortcodes'),
            array('[player_registration]', __('Front-end player registration form', 'poker-tournament-import'), '#registration-import'),
            array('[tdwp_tournament_import]', __('Front-end .tdt file upload form', 'poker-tournament-import'), '#registration-import'),
            array('[tdwp_tournament_display]', __('All-in-one custom display screen', 'poker-tournament-import'), '#display-screens'),
            array('[tdwp_leaderboard]', __('Live chip-count leaderboard widget', 'poker-tournament-import'), '#display-screens'),
            array('[tdwp_prize_pool]', __('Prize pool + payout breakdown widget', 'poker-tournament-import'), '#display-screens'),
            array('[tdwp_player_count]', __('Live remaining-player count widget', 'poker-tournament-import'), '#display-screens'),
            array('[tdwp_current_blinds]', __('Current (and next) blinds widget', 'poker-tournament-import'), '#display-screens'),
            array('[tdwp_screen_preview]', __('Iframe preview of a saved display screen', 'poker-tournament-import'), '#display-screens'),
            array('[poker_dashboard]', __('Organizer dashboard', 'poker-tournament-import'), '#dashboards'),
            array('[tdwp_dashboard]', __('View-switchable dashboard with drill-through', 'poker-tournament-import'), '#dashboards'),
        );
        ?>
        <section id="quick-reference" class="help-section">
            <h2><?php esc_html_e('Quick Reference', 'poker-tournament-import'); ?></h2>
            <p><?php esc_html_e('Every available shortcode at a glance. Click a row\'s category to jump to full documentation.', 'poker-tournament-import'); ?></p>
            <table class="quickref-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Shortcode', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('What it shows', 'poker-tournament-import'); ?></th>
                        <th><?php esc_html_e('Category', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><code><?php echo esc_html($row[0]); ?></code></td>
                            <td><?php echo esc_html($row[1]); ?></td>
                            <td><a href="<?php echo esc_attr($row[2]); ?>"><?php echo esc_html(ltrim($row[2], '#')); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="tip-box">
                <strong><?php esc_html_e('Alias tip:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('Every shortcode above also works with a "tdwp_" prefix (e.g. [tdwp_tournament_results]) to avoid collisions with your theme or other plugins.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Getting started section.
     */
    private function render_getting_started_section() {
        ?>
        <section id="getting-started" class="help-section">
            <h2><?php esc_html_e('Getting Started', 'poker-tournament-import'); ?></h2>
            <p><?php esc_html_e('Shortcodes are simple codes you add to any WordPress page or post to display tournament information.', 'poker-tournament-import'); ?></p>

            <h3><?php esc_html_e('How to Add a Shortcode', 'poker-tournament-import'); ?></h3>

            <div class="tabbed-interface">
                <div class="tab-buttons">
                    <button type="button" class="tab-button active" data-tab="editor"><?php esc_html_e('Block Editor', 'poker-tournament-import'); ?></button>
                    <button type="button" class="tab-button" data-tab="classic"><?php esc_html_e('Classic Editor', 'poker-tournament-import'); ?></button>
                </div>

                <div class="tab-content active" data-tab="editor">
                    <ol>
                        <li><?php esc_html_e('Edit your page or post in the Block Editor.', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Click the + icon to add a new block.', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Search for "Shortcode" and add the Shortcode block.', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Paste your shortcode into the block.', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Click Preview or Update to see the result.', 'poker-tournament-import'); ?></li>
                    </ol>
                </div>

                <div class="tab-content" data-tab="classic" style="display:none;">
                    <ol>
                        <li><?php esc_html_e('Edit your page or post in the Classic Editor.', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Place your cursor where you want the shortcode.', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Type or paste the shortcode directly into the content area.', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Save or update the page.', 'poker-tournament-import'); ?></li>
                    </ol>
                </div>
            </div>

            <div class="tip-box">
                <strong><?php esc_html_e('Pro Tip:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('You can combine multiple shortcodes on the same page to build a complete tournament, series, or season landing page.', 'poker-tournament-import'); ?>
            </div>

            <h3><?php esc_html_e('Finding IDs', 'poker-tournament-import'); ?></h3>
            <p><?php esc_html_e('Most shortcodes need a tournament, series, season, or player ID. To find one:', 'poker-tournament-import'); ?></p>
            <ol>
                <li><?php esc_html_e('Open the tournament, series, season, or player for editing.', 'poker-tournament-import'); ?></li>
                <li><?php esc_html_e('Look at the browser URL — the ID is the number after "post=" (for example post.php?post=123 means the ID is 123).', 'poker-tournament-import'); ?></li>
                <li><?php esc_html_e('IDs are also shown in the list tables (Tournaments, Series, Seasons, Players) when hovering over an item.', 'poker-tournament-import'); ?></li>
                <li><?php esc_html_e('On a tournament, series, season, or player edit screen, check the "Shortcode Helper" box in the sidebar for ready-made, copyable shortcodes.', 'poker-tournament-import'); ?></li>
            </ol>
        </section>
        <?php
    }

    /**
     * Tutorials section: step-by-step walkthroughs for common workflows.
     */
    private function render_tutorials_section() {
        ?>
        <section id="tutorials" class="help-section">
            <h2>
                <?php esc_html_e('Tutorials', 'poker-tournament-import'); ?>
                <span class="pti-badge pti-badge-new"><?php esc_html_e('New', 'poker-tournament-import'); ?></span>
            </h2>
            <p><?php esc_html_e('Step-by-step walkthroughs for the two most common jobs: importing a finished tournament, and running one live from the clock.', 'poker-tournament-import'); ?></p>

            <div class="tutorial-toc">
                <a href="#tutorial-import">
                    <strong><?php esc_html_e('Import & Manage a Tournament', 'poker-tournament-import'); ?></strong>
                    <span><?php esc_html_e('Upload a .tdt file from Tournament Director and get it live on your site.', 'poker-tournament-import'); ?></span>
                </a>
                <a href="#tutorial-live">
                    <strong><?php esc_html_e('Run a Live Tournament', 'poker-tournament-import'); ?></strong>
                    <span><?php esc_html_e('Build blinds and payouts, launch, run the clock, and display it on a screen.', 'poker-tournament-import'); ?></span>
                </a>
            </div>

            <div class="tutorial" id="tutorial-import">
                <h3><?php esc_html_e('Import & Manage a Tournament (.tdt)', 'poker-tournament-import'); ?></h3>
                <p class="tutorial-lead"><?php esc_html_e('Import a Tournament Director .tdt results file and get it live on the site.', 'poker-tournament-import'); ?></p>
                <ol class="tutorial-steps">
                    <li>
                        <strong class="step-title"><?php esc_html_e('Open the importer', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Poker Import -> Import Tournament) */
                                esc_html__('Go to %s. The main menu is "Poker Import" (spade icon).', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Poker Import → Import Tournament', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Choose your .tdt file', 'poker-tournament-import'); ?></strong>
                        <p><?php esc_html_e('Click the file field (accepts .tdt only — .xlsx/.csv are rejected) and pick the file exported from Tournament Director.', 'poker-tournament-import'); ?></p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Set import options', 'poker-tournament-import'); ?></strong>
                        <p><?php esc_html_e('Checkboxes: "Create new players automatically" (on by default), "Publish tournament immediately", "Enable debug for this import only". Under Points Formula pick "Auto-detect (Recommended)" (uses the formula in the .tdt, else your site default) or "Use specific formula" to choose one.', 'poker-tournament-import'); ?></p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Click Import Tournament', 'poker-tournament-import'); ?></strong>
                        <p><?php esc_html_e('The button appears once a file is chosen. On success you\'ll see "Tournament imported successfully!" and an Import Preview (title, series, season, date, player count). Duplicates are detected by the .tdt\'s UUID (not filename) and you\'ll be asked to confirm.', 'poker-tournament-import'); ?></p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Importing many at once?', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Poker Import -> Bulk Import) */
                                esc_html__('Use %s: click "Choose Files" (multi-select), set "Skip Duplicates"/"Update Existing"/"Import as New", then "Start Upload". Files process one-by-one with a progress bar; finish with "View Tournaments". (Limited to the server\'s max upload count, commonly 20 files, and per-file size limit.)', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Poker Import → Bulk Import', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                        <div class="info-box">
                            <?php esc_html_e('Front-end contributors can instead use the [tdwp_tournament_import] shortcode on a page — it lets a logged-in user with edit-posts upload a .tdt without the admin area (see Registration & Import below).', 'poker-tournament-import'); ?>
                        </div>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Review the tournament', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Tournaments) */
                                esc_html__('Open %s (the tournament list) and edit the imported post to confirm/adjust its Series and Season. Each row also has a "Review Points" action.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Tournaments', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Check the points', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: 1: admin menu path (Points Verification), 2: admin menu path (Points Adjustments) */
                                esc_html__('Go to %1$s to see a season-by-season health check that flags points anomalies; preview/apply a different formula per tournament if needed. For manual per-player overrides use %2$s (kept with an audit trail).', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Poker Import → Points Verification', 'poker-tournament-import') . '</span>',
                                '<span class="ui-path">' . esc_html__('Poker Import → Points Adjustments', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Refresh dashboard stats', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Poker Import -> Settings) */
                                esc_html__('Statistics are cached for speed. After importing, go to %s → "Statistics Data Mart" → click "Refresh Statistics Now" to update dashboards and leaderboards.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Poker Import → Settings', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Publish it', 'poker-tournament-import'); ?></strong>
                        <p><?php esc_html_e('Put the tournament on a page with the shortcode below (see Tournament Shortcodes for more options).', 'poker-tournament-import'); ?></p>
                        <div class="shortcode-block" data-copy="[tournament_results id=&quot;123&quot; show_players=&quot;true&quot;]">
                            <code>[tournament_results id="123" show_players="true"]</code>
                        </div>
                        <div class="tip-box">
                            <strong><?php esc_html_e('Pro Tip:', 'poker-tournament-import'); ?></strong>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Poker Import -> Debug Log) */
                                esc_html__('Troubleshooting a bad import? Check %s.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Poker Import → Debug Log', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </div>
                    </li>
                </ol>
            </div>

            <div class="tutorial" id="tutorial-live">
                <h3><?php esc_html_e('Run a Live Tournament (Tournament Manager)', 'poker-tournament-import'); ?></h3>
                <p class="tutorial-lead"><?php esc_html_e('Build the structure, launch, run the clock, and put it on a screen — all from the Tournament Manager menu (trophy icon, a separate top-level menu from Poker Import).', 'poker-tournament-import'); ?></p>
                <ol class="tutorial-steps">
                    <li>
                        <strong class="step-title"><?php esc_html_e('Build a blind schedule', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Tournament Manager -> Blind Builder) */
                                esc_html__('%s → "Add New Schedule"; set Schedule Name, Level Duration, Break Frequency & Duration (or start from a Turbo/Regular/Deep Stack default), then edit the blind levels. Mark break levels here so the clock auto-starts breaks.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Tournament Manager → Blind Builder', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Set up payouts', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Tournament Manager -> Prize Calculator) */
                                esc_html__('%s → "Add New Structure"; set the player range and add payout Places with percentages (or use the "Winner" preset). Tabs: Structures / Pool Calculator / Chop Calculator.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Tournament Manager → Prize Calculator', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Create a template (recommended)', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Tournament Manager -> Templates) */
                                esc_html__('%s → "Add New"; set Starting Chips, rebuy/add-on cost & chips and limits, late-registration cutoff, rake, and link the Blind Schedule + Prize Structure you just made.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Tournament Manager → Templates', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Launch the tournament', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Tournament Manager -> New Live Tournament) */
                                esc_html__('%s. Step 1 choose "Start Blank", "From Template", or "Copy Tournament". Step 2 configure name, starting chips, rebuys, rake, practice mode, financial policy (rebuy policy, bounty/PKO type, late-reg cutoff). Click "Create Tournament", then "Manage Tournament" to open Live Control.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Tournament Manager → New Live Tournament', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Operate the clock', 'poker-tournament-import'); ?></strong>
                        <p>
                            <?php
                            printf(
                                /* translators: %s: admin menu path (Tournament Manager -> Live Control) */
                                esc_html__('%s. On the Timer tab you get the current level (blinds/ante/duration) + next level, and controls: "Start Tournament", "Pause", "Resume", "Skip Level", "Add 5 Minutes", "Start Break" / "End Break", and "Stop Tournament" to finalize. A level-history table logs changes.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Tournament Manager → Live Control', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Manage players live', 'poker-tournament-import'); ?></strong>
                        <p><?php esc_html_e('The Players tab: "Add Player", "Process Buy-ins", and per-player "Process Rebuy", "Process Add-on", "Update Chip Count", "Bust Out Player" (with eliminator selection for bounties), and "Player Withdrawal".', 'poker-tournament-import'); ?></p>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Put it on a screen', 'poker-tournament-import'); ?></strong>
                        <p><?php esc_html_e('The simplest public display: add the clock shortcode to a page and open it with ?screen=clock for a chrome-free fullscreen venue display (see Live Tournament Clock above).', 'poker-tournament-import'); ?></p>
                        <div class="shortcode-block" data-copy="[tournament_clock tournament_id=&quot;123&quot; theme=&quot;dark&quot;]">
                            <code>[tournament_clock tournament_id="123" theme="dark"]</code>
                        </div>
                        <p>
                            <?php esc_html_e('For a richer board, combine the display widgets ([tdwp_tournament_display], [tdwp_leaderboard], [tdwp_prize_pool], [tdwp_current_blinds], [tdwp_player_count]) — see Display Screens.', 'poker-tournament-import'); ?>
                        </p>
                        <div class="shortcode-block" data-copy="[tdwp_tournament_display tournament_id=&quot;123&quot;]">
                            <code>[tdwp_tournament_display tournament_id="123"]</code>
                        </div>
                        <p>
                            <?php
                            printf(
                                /* translators: 1: admin menu path (Tournament Manager -> Display Screens), 2: admin menu path (Tournament Manager -> Layout Builder) */
                                esc_html__('For a managed TV, use %1$s to add a screen and assign the tournament, optionally styled in %2$s.', 'poker-tournament-import'),
                                '<span class="ui-path">' . esc_html__('Tournament Manager → Display Screens', 'poker-tournament-import') . '</span>',
                                '<span class="ui-path">' . esc_html__('Tournament Manager → Layout Builder', 'poker-tournament-import') . '</span>'
                            );
                            ?>
                        </p>
                        <div class="tip-box">
                            <strong><?php esc_html_e('Pro Tip:', 'poker-tournament-import'); ?></strong>
                            <?php esc_html_e('See the Live Tournament Clock section for the clock\'s live features (sounds, break countdown, multi-tab sync).', 'poker-tournament-import'); ?>
                        </div>
                    </li>
                    <li>
                        <strong class="step-title"><?php esc_html_e('Finish', 'poker-tournament-import'); ?></strong>
                        <p><?php esc_html_e('Click "Stop Tournament" on the Timer tab to finalize standings and payouts (or "Trash Tournament" to discard a test run). Results then flow into the same stats/standings the shortcodes display.', 'poker-tournament-import'); ?></p>
                    </li>
                </ol>
            </div>
        </section>
        <?php
    }

    /**
     * Live clock section.
     */
    private function render_live_clock_section() {
        ?>
        <section id="live-clock" class="help-section">
            <h2>
                <?php esc_html_e('Live Tournament Clock', 'poker-tournament-import'); ?>
                <span class="pti-badge pti-badge-new"><?php esc_html_e('New', 'poker-tournament-import'); ?></span>
            </h2>

            <p><?php esc_html_e('The live clock shows the current blind level, timer, and tournament stats, and updates automatically in real time as the tournament runs from Live Control — no page refresh needed.', 'poker-tournament-import'); ?></p>

            <?php $this->shortcode_block('[tournament_clock tournament_id="123" theme="dark" size="large" show_prizes="yes" show_rankings="yes" rankings_limit="15"]'); ?>

            <h3><?php esc_html_e('[tournament_clock] Attributes', 'poker-tournament-import'); ?></h3>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID. If 0 or omitted, the currently active tournament is auto-detected.', 'poker-tournament-import'), '0', false); ?>
                <?php $this->attribute_row('show_stats', __('Show tournament statistics alongside the clock.', 'poker-tournament-import'), 'yes', false); ?>
                <?php $this->attribute_row('show_level', __('Show the current blind level number.', 'poker-tournament-import'), 'yes', false); ?>
                <?php $this->attribute_row('theme', __('Visual theme: default, dark, or light.', 'poker-tournament-import'), 'default', false); ?>
                <?php $this->attribute_row('size', __('Widget size: small, medium, or large.', 'poker-tournament-import'), 'large', false); ?>
                <?php $this->attribute_row('show_prizes', __('Show prize pool information.', 'poker-tournament-import'), 'no', false); ?>
                <?php $this->attribute_row('show_rankings', __('Show a live player rankings panel.', 'poker-tournament-import'), 'no', false); ?>
                <?php $this->attribute_row('rankings_limit', __('Number of players shown in the rankings panel.', 'poker-tournament-import'), '10', false); ?>
                <?php $this->attribute_row('logo_url', __('Optional logo image URL to display on the clock.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>

            <div class="info-box">
                <strong><?php esc_html_e('Public fullscreen display:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('Append ?screen=clock to the page URL to open a chrome-free, fullscreen clock ideal for a venue TV or projector. It keeps the screen awake using the Wake Lock API. A "Fullscreen" button is also available directly in the widget.', 'poker-tournament-import'); ?>
            </div>

            <h3><?php esc_html_e('Live Capabilities', 'poker-tournament-import'); ?></h3>
            <ul class="feature-list">
                <li>✅ <?php esc_html_e('Current blinds (small blind / big blind / ante) with a next-level preview', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Average chip stack and current prize pool', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Sound cues at 5 minutes, 1 minute, level change, and break start', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Automatic break start with a live "Back at HH:MM" countdown', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Connection-lost overlay if the live feed drops', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Multi-tab sync so every open display stays in step', 'poker-tournament-import'); ?></li>
            </ul>

            <h3><?php esc_html_e('Compact Clock: [tdwp_live_clock]', 'poker-tournament-import'); ?></h3>
            <p><?php esc_html_e('A smaller, embeddable clock for placing inline in content rather than as a full widget.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[tdwp_live_clock tournament_id="123" show_level="true" show_time="true" show_next_blinds="false"]'); ?>

            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('show_level', __('Show the current blind level.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_time', __('Show the countdown timer.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_next_blinds', __('Show a preview of the next blind level.', 'poker-tournament-import'), 'false', false); ?>
                <?php $this->attribute_row('css_class', __('Extra CSS class(es) added to the widget wrapper.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>
        </section>
        <?php
    }

    /**
     * Tournament shortcodes section.
     */
    private function render_tournament_shortcodes_section() {
        ?>
        <section id="tournament-shortcodes" class="help-section">
            <h2><?php esc_html_e('Tournament Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><code>[tournament_results]</code></h3>
            <?php $this->shortcode_block('[tournament_results id="123" show_players="true" show_structure="false"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('show_players', __('Show the player results table.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_structure', __('Show the blind/payout structure.', 'poker-tournament-import'), 'false', false); ?>
            <?php $this->attribute_table_close(); ?>

            <div class="example-box">
                <h4><?php esc_html_e('Complete Tournament Page', 'poker-tournament-import'); ?></h4>
                <?php $this->shortcode_block('[tournament_results id="123" show_players="true" show_structure="true"]'); ?>
                <p><?php esc_html_e('Displays tournament metadata, prize pool, complete player results with achievement badges, points, and winnings.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><code>[tournament_series]</code></h3>
            <p><?php esc_html_e('Shows the series overview for the series a given tournament belongs to.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[tournament_series id="123" show_standings="true" limit="10"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('id', __('Tournament ID (used to resolve the linked series).', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('show_standings', __('Show series standings alongside the overview.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('limit', __('Number of tournaments/rows to show.', 'poker-tournament-import'), '10', false); ?>
            <?php $this->attribute_table_close(); ?>
        </section>
        <?php
    }

    /**
     * Series shortcodes section.
     */
    private function render_series_shortcodes_section() {
        ?>
        <section id="series-shortcodes" class="help-section">
            <h2><?php esc_html_e('Series Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><code>[series_standings]</code></h3>
            <p><?php esc_html_e('Formula-based points standings table for a series.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[series_standings id="123" formula="season_total" show_details="true" show_export="true"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('id', __('Series ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('formula', __('Formula key to score standings with. Empty uses the system default.', 'poker-tournament-import'), '(default)', false); ?>
                <?php $this->attribute_row('show_details', __('Show detailed statistics columns.', 'poker-tournament-import'), 'false', false); ?>
                <?php $this->attribute_row('show_export', __('Show a CSV/export control above the table.', 'poker-tournament-import'), 'true', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[series_tabs]</code></h3>
            <p><?php esc_html_e('A tabbed interface combining Overview, Results, Statistics, and Players in one shortcode.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[series_tabs series_id="123" active="overview" series_uuid=""]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('series_id', __('Series ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('active', __('Which tab is active on load.', 'poker-tournament-import'), 'overview', false); ?>
                <?php $this->attribute_row('series_uuid', __('Optional series UUID for cross-system lookups.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><?php esc_html_e('Individual Series Tabs', 'poker-tournament-import'); ?></h3>
            <p><?php esc_html_e('Each panel of [series_tabs] is also available on its own:', 'poker-tournament-import'); ?></p>
            <div class="example-box">
                <p><strong><?php esc_html_e('Overview', 'poker-tournament-import'); ?></strong> — <?php esc_html_e('stat cards + recent tournaments.', 'poker-tournament-import'); ?></p>
                <?php $this->shortcode_block('[series_overview id="123" limit="10"]'); ?>

                <p><strong><?php esc_html_e('Results', 'poker-tournament-import'); ?></strong> — <?php esc_html_e('tournaments and their results.', 'poker-tournament-import'); ?></p>
                <?php $this->shortcode_block('[series_results id="123" limit="20" show_all="false"]'); ?>

                <p><strong><?php esc_html_e('Statistics', 'poker-tournament-import'); ?></strong> — <?php esc_html_e('aggregate series statistics.', 'poker-tournament-import'); ?></p>
                <?php $this->shortcode_block('[series_statistics id="123"]'); ?>

                <p><strong><?php esc_html_e('Players', 'poker-tournament-import'); ?></strong> — <?php esc_html_e('unique players who took part in the series.', 'poker-tournament-import'); ?></p>
                <?php $this->shortcode_block('[series_players id="123"]'); ?>

                <p><strong><?php esc_html_e('Leaderboard', 'poker-tournament-import'); ?></strong> — <?php esc_html_e('points/winnings leaderboard.', 'poker-tournament-import'); ?></p>
                <?php $this->shortcode_block('[series_leaderboard id="123" limit="10"]'); ?>
            </div>

            <div class="tip-box">
                <strong><?php esc_html_e('Pro Tip:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('Use [series_tabs] for a modern, single-shortcode landing page — it handles the navigation and content loading for you.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Season shortcodes section.
     */
    private function render_season_shortcodes_section() {
        ?>
        <section id="season-shortcodes" class="help-section">
            <h2><?php esc_html_e('Season Shortcodes', 'poker-tournament-import'); ?></h2>
            <p><?php esc_html_e('Season shortcodes mirror series shortcodes but aggregate data across all tournaments in a season.', 'poker-tournament-import'); ?></p>

            <h3><code>[season_standings]</code></h3>
            <?php $this->shortcode_block('[season_standings id="456" formula="season_total" show_details="true" show_export="true"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('id', __('Season ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('formula', __('Formula key to score standings with. Empty uses the system default.', 'poker-tournament-import'), '(default)', false); ?>
                <?php $this->attribute_row('show_details', __('Show detailed statistics columns.', 'poker-tournament-import'), 'false', false); ?>
                <?php $this->attribute_row('show_export', __('Show a CSV/export control above the table.', 'poker-tournament-import'), 'true', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[season_tabs]</code></h3>
            <?php $this->shortcode_block('[season_tabs season_id="456" active="overview"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('season_id', __('Season ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('active', __('Which tab is active on load.', 'poker-tournament-import'), 'overview', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[season_leaderboard]</code></h3>
            <p><?php esc_html_e('Shows the leaderboard for the currently active season — no id attribute needed.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[season_leaderboard formula="" show_details="false" show_export="true" limit="20"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('formula', __('Formula key. Empty falls back to the option default (best_10).', 'poker-tournament-import'), '(default)', false); ?>
                <?php $this->attribute_row('show_details', __('Show detailed columns; also forces showing all players.', 'poker-tournament-import'), 'false', false); ?>
                <?php $this->attribute_row('show_export', __('Show a CSV/export control above the table.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('limit', __('Number of players shown.', 'poker-tournament-import'), '20', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><?php esc_html_e('Individual Season Tabs', 'poker-tournament-import'); ?></h3>
            <div class="example-box">
                <p><strong><?php esc_html_e('Overview', 'poker-tournament-import'); ?></strong></p>
                <?php $this->shortcode_block('[season_overview id="456" limit="10"]'); ?>

                <p><strong><?php esc_html_e('Results', 'poker-tournament-import'); ?></strong></p>
                <?php $this->shortcode_block('[season_results id="456" limit="20" show_all="false"]'); ?>

                <p><strong><?php esc_html_e('Statistics', 'poker-tournament-import'); ?></strong></p>
                <?php $this->shortcode_block('[season_statistics id="456"]'); ?>

                <p><strong><?php esc_html_e('Players', 'poker-tournament-import'); ?></strong></p>
                <?php $this->shortcode_block('[season_players id="456"]'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Player shortcodes section.
     */
    private function render_player_shortcodes_section() {
        ?>
        <section id="player-shortcodes" class="help-section">
            <h2><?php esc_html_e('Player Shortcodes', 'poker-tournament-import'); ?></h2>

            <h3><code>[player_profile]</code></h3>
            <?php $this->shortcode_block('[player_profile name="John Doe" show_stats="true"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('name', __('Player name (exact match). Provide name or id.', 'poker-tournament-import'), '(none)', false); ?>
                <?php $this->attribute_row('id', __('Player post ID. Provide name or id.', 'poker-tournament-import'), '0', false); ?>
                <?php $this->attribute_row('show_stats', __('Show career statistics.', 'poker-tournament-import'), 'true', false); ?>
            <?php $this->attribute_table_close(); ?>

            <div class="warning-box">
                <strong><?php esc_html_e('Important:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('One of "name" or "id" is required, or the shortcode will show "Player not found".', 'poker-tournament-import'); ?>
            </div>

            <div class="example-box">
                <p><strong><?php esc_html_e('By name:', 'poker-tournament-import'); ?></strong></p>
                <?php $this->shortcode_block('[player_profile name="Sarah Smith"]'); ?>
                <p><strong><?php esc_html_e('By ID:', 'poker-tournament-import'); ?></strong></p>
                <?php $this->shortcode_block('[player_profile id="789"]'); ?>
            </div>

            <h3><?php esc_html_e('What This Shows', 'poker-tournament-import'); ?></h3>
            <ul class="feature-list">
                <li>✅ <?php esc_html_e('Player name and profile information', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Career statistics summary', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Tournaments played, total winnings, and best cash', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Average and best finish positions', 'poker-tournament-import'); ?></li>
                <li>✅ <?php esc_html_e('Recent tournament history', 'poker-tournament-import'); ?></li>
            </ul>
        </section>
        <?php
    }

    /**
     * Registration & Import section.
     */
    private function render_registration_import_section() {
        ?>
        <section id="registration-import" class="help-section">
            <h2>
                <?php esc_html_e('Registration & Import', 'poker-tournament-import'); ?>
                <span class="pti-badge pti-badge-new"><?php esc_html_e('New', 'poker-tournament-import'); ?></span>
            </h2>

            <h3><code>[player_registration]</code></h3>
            <p><?php esc_html_e('A front-end registration form visitors can use to sign up for tournaments.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[player_registration title="Player Registration" require_email="yes" require_phone="no" show_bio="no" tournament_id="123"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('title', __('Form heading.', 'poker-tournament-import'), 'Player Registration', false); ?>
                <?php $this->attribute_row('require_email', __('Require an email address.', 'poker-tournament-import'), 'yes', false); ?>
                <?php $this->attribute_row('require_phone', __('Require a phone number.', 'poker-tournament-import'), 'no', false); ?>
                <?php $this->attribute_row('show_bio', __('Show an optional player bio field.', 'poker-tournament-import'), 'no', false); ?>
                <?php $this->attribute_row('success_message', __('Message shown after a successful submission.', 'poker-tournament-import'), 'Thank you for registering! We will contact you soon.', false); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament to register for. When set, remaining capacity is shown.', 'poker-tournament-import'), '0', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[tdwp_tournament_import]</code></h3>
            <p><?php esc_html_e('A front-end .tdt file upload form for logged-in users who can edit posts. This shortcode takes no attributes.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[tdwp_tournament_import]'); ?>
        </section>
        <?php
    }

    /**
     * Display screens section.
     */
    private function render_display_screens_section() {
        ?>
        <section id="display-screens" class="help-section">
            <h2>
                <?php esc_html_e('Display Screens', 'poker-tournament-import'); ?>
                <span class="pti-badge pti-badge-new"><?php esc_html_e('New', 'poker-tournament-import'); ?></span>
            </h2>

            <p><?php esc_html_e('These are lightweight, single-purpose widgets for building your own tournament-room or TV display screens — combine them freely on a page.', 'poker-tournament-import'); ?></p>

            <h3><code>[tdwp_tournament_display]</code></h3>
            <p><?php esc_html_e('An all-in-one display combining clock, blinds, players, and prizes.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[tdwp_tournament_display tournament_id="123" display_type="full" show_clock="true" show_blinds="true" show_players="true" show_prizes="true" auto_refresh="true"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('screen_name', __('Optional label for this display instance.', 'poker-tournament-import'), '(none)', false); ?>
                <?php $this->attribute_row('display_type', __('Layout: full, compact, or minimal.', 'poker-tournament-import'), 'full', false); ?>
                <?php $this->attribute_row('show_clock', __('Show the clock panel.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_blinds', __('Show the blinds panel.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_players', __('Show the players panel.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_prizes', __('Show the prizes panel.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('auto_refresh', __('Automatically refresh live data.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('css_class', __('Extra CSS class(es) added to the wrapper.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[tdwp_leaderboard]</code></h3>
            <?php $this->shortcode_block('[tdwp_leaderboard tournament_id="123" limit="10" show_chips="true" show_eliminated="false"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('limit', __('Number of players shown.', 'poker-tournament-import'), '10', false); ?>
                <?php $this->attribute_row('show_chips', __('Show live chip counts.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_eliminated', __('Include eliminated players.', 'poker-tournament-import'), 'false', false); ?>
                <?php $this->attribute_row('css_class', __('Extra CSS class(es) added to the wrapper.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[tdwp_prize_pool]</code></h3>
            <?php $this->shortcode_block('[tdwp_prize_pool tournament_id="123" show_breakdown="false"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('show_breakdown', __('Show a full payout breakdown.', 'poker-tournament-import'), 'false', false); ?>
                <?php $this->attribute_row('css_class', __('Extra CSS class(es) added to the wrapper.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[tdwp_player_count]</code></h3>
            <?php $this->shortcode_block('[tdwp_player_count tournament_id="123" show_label="true"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('show_label', __('Show a text label alongside the count.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('css_class', __('Extra CSS class(es) added to the wrapper.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[tdwp_current_blinds]</code></h3>
            <?php $this->shortcode_block('[tdwp_current_blinds tournament_id="123" show_next="true"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('show_next', __('Show the next blind level.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('css_class', __('Extra CSS class(es) added to the wrapper.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[tdwp_screen_preview]</code></h3>
            <p><?php esc_html_e('Embeds an iframe preview of a display screen you have already saved in the Display Screens admin.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[tdwp_screen_preview screen_id="5" tournament_id="123" width="100%" height="600px"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('screen_id', __('Saved display screen ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('tournament_id', __('Tournament ID.', 'poker-tournament-import'), '0', true); ?>
                <?php $this->attribute_row('width', __('Iframe width.', 'poker-tournament-import'), '100%', false); ?>
                <?php $this->attribute_row('height', __('Iframe height.', 'poker-tournament-import'), '600px', false); ?>
                <?php $this->attribute_row('css_class', __('Extra CSS class(es) added to the wrapper.', 'poker-tournament-import'), '(none)', false); ?>
            <?php $this->attribute_table_close(); ?>
        </section>
        <?php
    }

    /**
     * Dashboards section.
     */
    private function render_dashboards_section() {
        ?>
        <section id="dashboards" class="help-section">
            <h2><?php esc_html_e('Dashboards', 'poker-tournament-import'); ?></h2>
            <p><?php esc_html_e('Two distinct dashboards are available for organizers.', 'poker-tournament-import'); ?></p>

            <h3><code>[poker_dashboard]</code></h3>
            <?php $this->shortcode_block('[poker_dashboard show_stats="true" show_recent="true" show_health="false" recent_count="5"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('show_stats', __('Show summary statistics.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_recent', __('Show recent tournaments.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('show_health', __('Show data-health indicators.', 'poker-tournament-import'), 'false', false); ?>
                <?php $this->attribute_row('recent_count', __('Number of recent tournaments shown (1-20).', 'poker-tournament-import'), '5', false); ?>
            <?php $this->attribute_table_close(); ?>

            <h3><code>[tdwp_dashboard]</code></h3>
            <p><?php esc_html_e('A view-switchable dashboard with drill-through navigation.', 'poker-tournament-import'); ?></p>
            <?php $this->shortcode_block('[tdwp_dashboard view="overview" limit="10" show_stats="true" drill_through="true"]'); ?>
            <?php $this->attribute_table_open(); ?>
                <?php $this->attribute_row('view', __('Initial view to display.', 'poker-tournament-import'), 'overview', false); ?>
                <?php $this->attribute_row('limit', __('Number of items shown per view.', 'poker-tournament-import'), '10', false); ?>
                <?php $this->attribute_row('show_stats', __('Show summary statistics.', 'poker-tournament-import'), 'true', false); ?>
                <?php $this->attribute_row('drill_through', __('Allow clicking through to detail views.', 'poker-tournament-import'), 'true', false); ?>
            <?php $this->attribute_table_close(); ?>
        </section>
        <?php
    }

    /**
     * Advanced usage section.
     */
    private function render_advanced_usage_section() {
        ?>
        <section id="advanced-usage" class="help-section">
            <h2><?php esc_html_e('Advanced Usage', 'poker-tournament-import'); ?></h2>

            <h3><?php esc_html_e('Tournament Landing Page', 'poker-tournament-import'); ?></h3>
            <div class="example-box">
                <?php $this->shortcode_block("[tournament_results id=\"456\" show_players=\"true\"]\n[series_standings id=\"123\" show_details=\"true\" formula=\"season_total\"]"); ?>
                <p><?php esc_html_e('Combines the latest tournament results with current series standings on one page.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php esc_html_e('Player Profile Page', 'poker-tournament-import'); ?></h3>
            <div class="example-box">
                <?php $this->shortcode_block("[player_profile name=\"John Smith\" show_stats=\"true\"]\n[tournament_results id=\"123\"]\n[tournament_results id=\"124\"]\n[tournament_results id=\"125\"]"); ?>
                <p><?php esc_html_e('Shows a player profile followed by several recent tournament results.', 'poker-tournament-import'); ?></p>
            </div>

            <h3><?php esc_html_e('Series Hub Page', 'poker-tournament-import'); ?></h3>
            <div class="example-box">
                <?php $this->shortcode_block('[series_tabs series_id="789" active="overview"]'); ?>
                <p><?php esc_html_e('A single tabbed shortcode that acts as a complete series landing page.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="tip-box">
                <strong><?php esc_html_e('tdwp_ aliases:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('Every shortcode on this page also works with a "tdwp_" prefix (for example [tdwp_series_standings]) if your theme or another plugin already defines a shortcode with the same name.', 'poker-tournament-import'); ?>
            </div>

            <div class="tip-box">
                <strong><?php esc_html_e('Custom styling:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('All shortcodes render with predictable CSS classes you can target in your theme, such as .tournament-results, .series-standings, and .player-profile.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Troubleshooting section.
     */
    private function render_troubleshooting_section() {
        ?>
        <section id="troubleshooting" class="help-section">
            <h2><?php esc_html_e('Troubleshooting', 'poker-tournament-import'); ?></h2>

            <div class="example-box">
                <h4><?php esc_html_e('"Please specify a tournament ID" (or similar)', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Double-check the ID attribute. Open the tournament/series/season/player for editing and look for post=123 in the URL.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('"Player not found"', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Ensure the player name matches exactly (case-sensitive), or use the player ID instead for a more reliable match.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('Tables appear empty or show no data', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Confirm the tournament has been imported with results data (Tournaments → Import to upload .tdt files).', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('Formulas not calculating correctly', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Go to the Formula Manager to review your formula syntax and variables.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="example-box">
                <h4><?php esc_html_e('Clock shows nothing or is not updating', 'poker-tournament-import'); ?></h4>
                <p><strong><?php esc_html_e('Solution:', 'poker-tournament-import'); ?></strong> <?php esc_html_e('Make sure the tournament has been started from Live Control, and that the page is not being served from a full-page cache.', 'poker-tournament-import'); ?></p>
            </div>

            <div class="warning-box">
                <strong><?php esc_html_e('Important:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('Always test shortcodes on a draft page first before publishing, to make sure they render as expected.', 'poker-tournament-import'); ?>
            </div>
        </section>
        <?php
    }

    /**
     * Register settings.
     */
    public function register_help_settings() {
        register_setting('poker_shortcode_help', 'poker_shortcode_help_displayed', array(
            'sanitize_callback' => 'boolval',
        ));
    }
}
