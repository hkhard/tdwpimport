<?php
/**
 * Migration Tools Admin Page
 *
 * Admin interface for migrating tournament relationships and verifying data integrity
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Migration_Admin_Page {

    /**
     * PHP 8.2+ compatibility - declare dynamic properties
     */
    private $migration_tools;

    /**
     * Constructor
     */
    public function __construct() {
        $this->migration_tools = new Poker_Tournament_Migration_Tools();
        add_action('admin_notices', array($this, 'show_migration_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_migration_assets'));
    }

    public function enqueue_migration_assets($hook) {
        if ('poker-tournament-import_page_poker-migration-tools' !== $hook) return;
        wp_add_inline_style('wp-admin', '.status-good{color:#00a32a;font-weight:bold}.status-bad{color:#d63638;font-weight:bold}.diagnostics-table{margin-top:15px}.diagnostics-table table{font-size:12px}');
        wp_add_inline_script('wp-admin', 'setTimeout(function(){const url=new URL(window.location);url.searchParams.delete("migration_status");url.searchParams.delete("action");window.history.replaceState({},document.title,url.href)},3000);');
    }

    /**
     * Render migration tools page
     */
    public function render_migration_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->show_migration_status(); ?>
            <?php $this->show_migration_actions(); ?>
            <?php $this->show_verification_results(); ?>
        </div>
        <?php
    }

    /**
     * Show migration status
     */
    private function show_migration_status() {
        $migration_count = $this->migration_tools->get_migration_count();
        $verification = $this->migration_tools->verify_relationships();
        ?>
        <div class="migration-status-card">
            <h2><?php esc_html_e('Migration Status', 'poker-tournament-import'); ?></h2>
            <div class="status-grid">
                <div class="status-item">
                    <span class="status-number"><?php echo esc_html($verification['total']); ?></span>
                    <span class="status-label"><?php esc_html_e('Total Tournaments', 'poker-tournament-import'); ?></span>
                </div>
                <div class="status-item <?php echo esc_attr($migration_count > 0 ? 'needs-attention' : 'good'); ?>">
                    <span class="status-number"><?php echo esc_html($migration_count); ?></span>
                    <span class="status-label"><?php esc_html_e('Need Migration', 'poker-tournament-import'); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-number"><?php echo esc_html($verification['has_both']); ?></span>
                    <span class="status-label"><?php esc_html_e('Complete Relationships', 'poker-tournament-import'); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-number"><?php echo esc_html($verification['has_neither']); ?></span>
                    <span class="status-label"><?php esc_html_e('No Relationships', 'poker-tournament-import'); ?></span>
                </div>
            </div>

            <?php if ($migration_count > 0): ?>
                <div class="migration-notice">
                    <p><strong><?php esc_html_e('Attention:', 'poker-tournament-import'); ?></strong>
                    <?php
                    /* translators: %d: number of tournaments */
                    printf(
                        /* translators: %d: number of tournaments */
                        esc_html__('%d tournaments need migration to fix series/season relationships.', 'poker-tournament-import'),
                        esc_html($migration_count)
                    );
                    ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="migration-success">
                    <p><strong><?php esc_html_e('Excellent!', 'poker-tournament-import'); ?></strong>
                    <?php esc_html_e('All tournaments have proper series/season relationships.', 'poker-tournament-import'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Show migration actions
     */
    private function show_migration_actions() {
        $migration_count = $this->migration_tools->get_migration_count();
        ?>
        <div class="migration-actions-card">
            <h2><?php esc_html_e('Migration Actions', 'poker-tournament-import'); ?></h2>

            <?php if ($migration_count > 0): ?>
                <div class="action-section">
                    <h3><?php esc_html_e('Bulk Migration', 'poker-tournament-import'); ?></h3>
                    <p><?php esc_html_e('Migrate all tournaments that are missing series/season relationships. This will:', 'poker-tournament-import'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Find or create series posts based on tournament data', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Find or create season posts based on tournament data', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Link tournaments to their series and seasons', 'poker-tournament-import'); ?></li>
                        <li><?php esc_html_e('Apply tournament type/format/category taxonomies', 'poker-tournament-import'); ?></li>
                    </ul>

                    <form method="post" class="migration-form">
                        <?php wp_nonce_field('poker_migration_action', 'nonce'); ?>
                        <input type="hidden" name="poker_migration_action" value="migrate_all">
                        <p>
                            <button type="submit" class="button button-primary"
                                    onclick="return confirm('<?php
                                    esc_attr_e('This will migrate all tournaments. Are you sure?', 'poker-tournament-import');
                                    ?>')">
                                <?php esc_html_e('Migrate All Tournaments', 'poker-tournament-import'); ?>
                            </button>
                            <span class="migration-count">
                                (<?php
                                /* translators: %d: number of tournaments */
                                printf(esc_html__('%d tournaments will be migrated', 'poker-tournament-import'), esc_html($migration_count)); ?>)
                            </span>
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <div class="action-section">
                <h3><?php esc_html_e('Data Verification', 'poker-tournament-import'); ?></h3>
                <p><?php esc_html_e('Verify the integrity of tournament relationships and identify any issues.', 'poker-tournament-import'); ?></p>

                <form method="post" class="migration-form">
                    <?php wp_nonce_field('poker_migration_action', 'nonce'); ?>
                    <input type="hidden" name="poker_migration_action" value="verify">
                    <p>
                        <button type="submit" class="button">
                            <?php esc_html_e('Verify Relationships', 'poker-tournament-import'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <div class="action-section">
                <h3><?php esc_html_e('Player Data Synchronization', 'poker-tournament-import'); ?></h3>
                <p><strong><?php esc_html_e('Critical Fix:', 'poker-tournament-import'); ?></strong>
                <?php esc_html_e('Sync tournament player data from import files to database tables. This fixes:', 'poker-tournament-import'); ?></p>
                <ul>
                    <li><?php esc_html_e('Empty tournament displays (no player data)', 'poker-tournament-import'); ?></li>
                    <li><?php esc_html_e('Tab interface not showing content', 'poker-tournament-import'); ?></li>
                    <li><?php esc_html_e('Player statistics not calculating', 'poker-tournament-import'); ?></li>
                </ul>

                <form method="post" class="migration-form">
                    <?php wp_nonce_field('poker_migration_action', 'nonce'); ?>
                    <input type="hidden" name="poker_migration_action" value="sync_players">
                    <p>
                        <button type="submit" class="button button-primary"
                                onclick="return confirm('<?php
                                esc_attr_e('This will sync all tournament player data to fix display issues. Continue?', 'poker-tournament-import');
                                ?>')">
                            <?php esc_html_e('Sync All Player Data', 'poker-tournament-import'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Show verification results
     */
    private function show_verification_results() {
        $verification_results = $this->migration_tools->get_admin_notice('verification_results');

        if ($verification_results) {
            $this->migration_tools->clear_admin_notice('verification_results');
            ?>
            <div class="verification-results-card">
                <h2><?php esc_html_e('Verification Results', 'poker-tournament-import'); ?></h2>

                <div class="verification-summary">
                    <h3><?php esc_html_e('Relationship Status Summary', 'poker-tournament-import'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <tr>
                            <td><?php esc_html_e('Total Tournaments', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($verification_results['total']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Have Series Relationship', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($verification_results['has_series']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Have Season Relationship', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($verification_results['has_season']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Have Both Relationships', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($verification_results['has_both']); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Have No Relationships', 'poker-tournament-import'); ?></td>
                            <td><?php echo esc_html($verification_results['has_neither']); ?></td>
                        </tr>
                    </table>
                </div>

                <?php if (!empty($verification_results['orphaned_series']) || !empty($verification_results['orphaned_seasons'])): ?>
                    <div class="orphaned-relationships">
                        <h3><?php esc_html_e('Orphaned Relationships Found', 'poker-tournament-import'); ?></h3>

                        <?php if (!empty($verification_results['orphaned_series'])): ?>
                            <p><strong><?php esc_html_e('Tournaments with broken series relationships:', 'poker-tournament-import'); ?></strong></p>
                            <ul>
                                <?php foreach ($verification_results['orphaned_series'] as $tournament_id): ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_edit_post_link($tournament_id)); ?>">
                                            <?php echo esc_html(get_the_title($tournament_id)); ?> (ID: <?php echo esc_html($tournament_id); ?>)
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($verification_results['orphaned_seasons'])): ?>
                            <p><strong><?php esc_html_e('Tournaments with broken season relationships:', 'poker-tournament-import'); ?></strong></p>
                            <ul>
                                <?php foreach ($verification_results['orphaned_seasons'] as $tournament_id): ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_edit_post_link($tournament_id)); ?>">
                                            <?php echo esc_html(get_the_title($tournament_id)); ?> (ID: <?php echo esc_html($tournament_id); ?>)
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        $migration_results = $this->migration_tools->get_admin_notice('migration_results');
        if ($migration_results) {
            $this->migration_tools->clear_admin_notice('migration_results');
            ?>
            <div class="migration-results-card">
                <h2><?php esc_html_e('Migration Results', 'poker-tournament-import'); ?></h2>

                <div class="migration-summary">
                    <?php if ($migration_results['success'] > 0): ?>
                        <div class="success-notice">
                            <p><strong><?php esc_html_e('Migration Successful!', 'poker-tournament-import'); ?></strong></p>
                            <p><?php
                            /* translators: %d: number of tournaments */
                            printf(esc_html__('%d tournaments were successfully migrated.', 'poker-tournament-import'), esc_html($migration_results['success'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($migration_results['failed'] > 0): ?>
                        <div class="error-notice">
                            <p><strong><?php esc_html_e('Migration Issues Found', 'poker-tournament-import'); ?></strong></p>
                            <p><?php
                            /* translators: %d: number of tournaments */
                            printf(esc_html__('%d tournaments could not be migrated.', 'poker-tournament-import'), esc_html($migration_results['failed'])); ?></p>

                            <?php if (!empty($migration_results['errors'])): ?>
                                <h4><?php esc_html_e('Errors:', 'poker-tournament-import'); ?></h4>
                                <ul>
                                    <?php foreach ($migration_results['errors'] as $error): ?>
                                        <li><?php echo esc_html($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        $sync_results = $this->migration_tools->get_admin_notice('sync_results');
        if ($sync_results) {
            $this->migration_tools->clear_admin_notice('sync_results');
            ?>
            <div class="migration-results-card">
                <h2><?php esc_html_e('Player Data Sync Results', 'poker-tournament-import'); ?></h2>

                <div class="migration-summary">
                    <?php if ($sync_results['synced'] > 0): ?>
                        <div class="success-notice">
                            <p><strong><?php esc_html_e('Sync Successful!', 'poker-tournament-import'); ?></strong></p>
                            <p><?php
                            /* translators: %d: number of tournaments */
                            printf(esc_html__('%d tournaments had their player data synchronized successfully.', 'poker-tournament-import'), esc_html($sync_results['synced'])); ?></p>
                            <p><em><?php esc_html_e('This should fix the empty tournament displays and tab interface issues.', 'poker-tournament-import'); ?></em></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($sync_results['failed'] > 0): ?>
                        <div class="error-notice">
                            <p><strong><?php esc_html_e('Sync Issues Found', 'poker-tournament-import'); ?></strong></p>
                            <p><?php
                            /* translators: %d: number of tournaments */
                            printf(esc_html__('%d tournaments could not be synced.', 'poker-tournament-import'), esc_html($sync_results['failed'])); ?></p>

                            <?php if (!empty($sync_results['errors'])): ?>
                                <h4><?php esc_html_e('Errors:', 'poker-tournament-import'); ?></h4>
                                <ul>
                                    <?php foreach (array_slice($sync_results['errors'], 0, 10) as $error): ?>
                                        <li><?php echo esc_html($error); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($sync_results['errors']) > 10): ?>
                                        <li><?php
                                        /* translators: %d: number of additional errors */
                                        printf(esc_html__('... and %d more errors', 'poker-tournament-import'), count($sync_results['errors']) - 10); ?></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($sync_results['diagnostics'])): ?>
                                <h4><?php esc_html_e('Tournament Diagnostics:', 'poker-tournament-import'); ?></h4>
                                <div class="diagnostics-table">
                                    <table class="wp-list-table widefat striped">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Tournament ID', 'poker-tournament-import'); ?></th>
                                                <th><?php esc_html_e('Title', 'poker-tournament-import'); ?></th>
                                                <th><?php esc_html_e('Has Data', 'poker-tournament-import'); ?></th>
                                                <th><?php esc_html_e('Players', 'poker-tournament-import'); ?></th>
                                                <th><?php esc_html_e('Has UUID', 'poker-tournament-import'); ?></th>
                                                <th><?php esc_html_e('Meta Keys', 'poker-tournament-import'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sync_results['diagnostics'] as $diag): ?>
                                                <tr>
                                                    <td><?php echo esc_html($diag['tournament_id']); ?></td>
                                                    <td><?php echo esc_html($diag['title']); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo esc_attr($diag['has_tournament_data'] ? 'good' : 'bad'); ?>">
                                                            <?php echo esc_html($diag['has_tournament_data'] ? '✓' : '✗'); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo esc_html($diag['player_count']); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo esc_attr($diag['has_uuid'] ? 'good' : 'bad'); ?>">
                                                            <?php echo esc_html($diag['has_uuid'] ? '✓' : '✗'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <code style="font-size: 11px;">
                                                            <?php echo esc_html(implode(', ', array_keys($diag['all_meta_keys']))); ?>
                                                        </code>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Show migration notices
     */
    public function show_migration_notices() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'poker-tournament-import_page_poker-migration-tools') {
            return;
        }

        // Check for migration status in URL parameters
        if (isset($_GET['migration_status']) && $_GET['migration_status'] === 'completed') {
            $action = sanitize_text_field($_GET['action'] ?? '');

            switch ($action) {
                case 'migrate_all':
                    $this->show_admin_notice(
                        __('Migration completed! The page will refresh to show updated status.', 'poker-tournament-import'),
                        'success'
                    );
                    break;
                case 'verify':
                    $this->show_admin_notice(
                        __('Relationship verification completed! See results below.', 'poker-tournament-import'),
                        'success'
                    );
                    break;
                case 'sync_players':
                    $this->show_admin_notice(
                        __('Player data synchronization completed! This should fix tournament display issues.', 'poker-tournament-import'),
                        'success'
                    );
                    break;
                default:
                    $this->show_admin_notice(
                        __('Operation completed successfully!', 'poker-tournament-import'),
                        'success'
                    );
                    break;
            }

            // Clean up the URL parameters
        }
    }

    /**
     * Show admin notice
     */
    private function show_admin_notice($message, $type = 'info') {
        $class = 'notice';
        switch ($type) {
            case 'success':
                $class .= ' notice-success is-dismissible';
                break;
            case 'error':
                $class .= ' notice-error is-dismissible';
                break;
            case 'warning':
                $class .= ' notice-warning is-dismissible';
                break;
            default:
                $class .= ' notice-info is-dismissible';
                break;
        }

        printf('<div class="%s"><p>%s</p></div>', esc_attr($class), esc_html($message));
    }
}
