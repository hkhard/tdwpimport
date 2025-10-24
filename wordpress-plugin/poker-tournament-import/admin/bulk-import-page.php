<?php
/**
 * Bulk Import Admin Page
 *
 * Interface for uploading and processing multiple .tdt files simultaneously.
 *
 * @package Poker_Tournament_Import
 * @subpackage Admin
 * @since 2.9.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'poker-tournament-import'));
}

// Get upload limits from WordPress
$max_upload_size = wp_max_upload_size();
$max_file_uploads = ini_get('max_file_uploads') ?: 20;
$post_max_size = wp_convert_hr_to_bytes(ini_get('post_max_size'));
?>

<div class="wrap" id="poker-bulk-import">
    <h1><?php echo esc_html__('Bulk Tournament Import', 'poker-tournament-import'); ?></h1>

    <p class="description">
        <?php echo esc_html__('Upload multiple .tdt files to import tournaments in batch. Files will be processed sequentially to avoid timeout issues.', 'poker-tournament-import'); ?>
    </p>

    <!-- Server Limits Info -->
    <div class="poker-upload-limits notice notice-info" style="margin-top: 20px;">
        <p>
            <strong><?php echo esc_html__('Server Upload Limits:', 'poker-tournament-import'); ?></strong>
        </p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li>
                <?php
                /* translators: %s: formatted file size */
                printf(esc_html__('Max file size: %s', 'poker-tournament-import'), size_format($max_upload_size));
                ?>
            </li>
            <li>
                <?php
                /* translators: %d: number of files */
                printf(esc_html__('Max files per upload: %d', 'poker-tournament-import'), (int) $max_file_uploads);
                ?>
            </li>
            <li>
                <?php
                /* translators: %s: formatted size */
                printf(esc_html__('Max total size per request: %s', 'poker-tournament-import'), size_format($post_max_size));
                ?>
            </li>
        </ul>
    </div>

    <!-- Upload Section -->
    <div class="poker-upload-section card" style="margin-top: 20px; padding: 20px;">
        <h2><?php echo esc_html__('Select Files', 'poker-tournament-import'); ?></h2>

        <div class="poker-file-input-wrapper" style="margin: 20px 0;">
            <input
                type="file"
                id="poker-tdt-files"
                accept=".tdt"
                multiple
                style="display: none;"
            />
            <button type="button" class="button button-large" id="poker-select-files">
                <span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
                <?php echo esc_html__('Select .tdt Files', 'poker-tournament-import'); ?>
            </button>
            <span id="poker-files-selected" style="margin-left: 10px; font-weight: 500;"></span>
        </div>

        <!-- Options -->
        <div class="poker-upload-options" style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 4px;">
            <h3 style="margin-top: 0;"><?php echo esc_html__('Import Options', 'poker-tournament-import'); ?></h3>

            <p>
                <label>
                    <input type="checkbox" id="poker-skip-duplicates" checked />
                    <?php echo esc_html__('Skip duplicate tournaments (same name, date, venue)', 'poker-tournament-import'); ?>
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox" id="poker-update-existing" />
                    <?php echo esc_html__('Update existing tournaments if duplicates found', 'poker-tournament-import'); ?>
                </label>
                <span class="description" style="display: block; margin-left: 25px;">
                    <?php echo esc_html__('(Replaces tournament data with new import)', 'poker-tournament-import'); ?>
                </span>
            </p>

            <p>
                <label>
                    <input type="checkbox" id="poker-import-as-new" />
                    <?php echo esc_html__('Import all as new tournaments (ignore duplicates)', 'poker-tournament-import'); ?>
                </label>
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="poker-actions">
            <button type="button" class="button button-primary button-large" id="poker-start-upload" disabled>
                <span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span>
                <?php echo esc_html__('Start Import', 'poker-tournament-import'); ?>
            </button>

            <button type="button" class="button button-secondary" id="poker-cancel-upload" style="display: none;">
                <span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
                <?php echo esc_html__('Cancel', 'poker-tournament-import'); ?>
            </button>

            <button type="button" class="button" id="poker-retry-failed" style="display: none;">
                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                <?php echo esc_html__('Retry Failed Files', 'poker-tournament-import'); ?>
            </button>

            <button type="button" class="button" id="poker-clear-batch" style="display: none;">
                <?php echo esc_html__('Clear & Start New', 'poker-tournament-import'); ?>
            </button>
        </div>
    </div>

    <!-- Progress Section -->
    <div class="poker-progress-section card" id="poker-progress-section" style="margin-top: 20px; padding: 20px; display: none;">
        <h2><?php echo esc_html__('Import Progress', 'poker-tournament-import'); ?></h2>

        <!-- Overall Progress -->
        <div class="poker-overall-progress" style="margin: 20px 0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span id="poker-progress-label">
                    <?php echo esc_html__('Preparing import...', 'poker-tournament-import'); ?>
                </span>
                <span id="poker-progress-percent">0%</span>
            </div>

            <div class="poker-progress-bar" style="width: 100%; height: 30px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                <div id="poker-progress-fill" style="height: 100%; width: 0%; background: #2271b1; transition: width 0.3s ease;"></div>
            </div>

            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                <span id="poker-progress-stats"></span>
            </div>
        </div>

        <!-- File List -->
        <div class="poker-file-list" style="margin-top: 30px;">
            <h3><?php echo esc_html__('Files', 'poker-tournament-import'); ?></h3>

            <table class="wp-list-table widefat fixed striped" id="poker-files-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><?php echo esc_html__('#', 'poker-tournament-import'); ?></th>
                        <th><?php echo esc_html__('Filename', 'poker-tournament-import'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Size', 'poker-tournament-import'); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Status', 'poker-tournament-import'); ?></th>
                        <th><?php echo esc_html__('Details', 'poker-tournament-import'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Time', 'poker-tournament-import'); ?></th>
                    </tr>
                </thead>
                <tbody id="poker-files-tbody">
                    <!-- Files will be dynamically added here -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Results Section -->
    <div class="poker-results-section card" id="poker-results-section" style="margin-top: 20px; padding: 20px; display: none;">
        <h2><?php echo esc_html__('Import Complete', 'poker-tournament-import'); ?></h2>

        <div id="poker-results-summary" style="margin: 20px 0;">
            <!-- Summary will be displayed here -->
        </div>

        <div class="poker-results-actions">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=tournament')); ?>" class="button button-primary">
                <?php echo esc_html__('View All Tournaments', 'poker-tournament-import'); ?>
            </a>

            <button type="button" class="button" id="poker-import-another">
                <?php echo esc_html__('Import More Files', 'poker-tournament-import'); ?>
            </button>
        </div>
    </div>

    <!-- Hidden Resume State -->
    <input type="hidden" id="poker-batch-uuid" value="" />
</div>

<style>
/* Status badges */
.poker-status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.poker-status-pending {
    background: #f0f0f1;
    color: #646970;
}

.poker-status-processing {
    background: #72aee6;
    color: #fff;
}

.poker-status-completed {
    background: #00a32a;
    color: #fff;
}

.poker-status-failed {
    background: #d63638;
    color: #fff;
}

.poker-status-skipped {
    background: #dba617;
    color: #fff;
}

/* File row highlighting */
#poker-files-tbody tr.processing {
    background-color: #e5f5fa !important;
}

#poker-files-tbody tr.completed {
    background-color: #edfaef !important;
}

#poker-files-tbody tr.failed {
    background-color: #fae5e5 !important;
}

/* Spinner */
.poker-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #2271b1;
    border-radius: 50%;
    animation: poker-spin 1s linear infinite;
    vertical-align: middle;
    margin-right: 5px;
}

@keyframes poker-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Error messages */
.poker-error-message {
    color: #d63638;
    font-size: 12px;
    margin-top: 5px;
}

/* Success summary */
.poker-summary-item {
    padding: 10px 15px;
    margin: 10px 0;
    background: #fff;
    border-left: 4px solid #2271b1;
    font-size: 14px;
}

.poker-summary-item strong {
    display: inline-block;
    min-width: 150px;
}
</style>
