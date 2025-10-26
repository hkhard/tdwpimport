<?php
/**
 * Bulk Import Controller
 *
 * Handles REST API endpoints and orchestration for bulk .tdt file imports
 *
 * @package Poker_Tournament_Import
 * @subpackage Admin
 * @since 2.9.0
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Bulk_Import {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Batch processor instance
     */
    private $batch_processor;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Load batch processor
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/class-batch-processor.php';
        $this->batch_processor = new Poker_Tournament_Batch_Processor();
    }

    /**
     * Add admin menu item for bulk import
     */
    public function add_admin_menu() {
        add_submenu_page(
            'poker-tournament-import',
            __('Bulk Import', 'poker-tournament-import'),
            __('Bulk Import', 'poker-tournament-import'),
            'manage_options',
            'poker-bulk-import',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render bulk import admin page
     */
    public function render_admin_page() {
        require_once POKER_TOURNAMENT_IMPORT_PLUGIN_DIR . 'admin/bulk-import-page.php';
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $namespace = 'poker-tournament/v1';

        // POST /import/batch - Initiate bulk upload
        register_rest_route($namespace, '/import/batch', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_init_batch'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // POST /import/batch/{uuid}/process - Process single file
        register_rest_route($namespace, '/import/batch/(?P<uuid>[a-f0-9\-]+)/process', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_process_file'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'uuid' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-f0-9\-]{36}$/', $param);
                    }
                )
            )
        ));

        // GET /import/batch/{uuid} - Get batch status
        register_rest_route($namespace, '/import/batch/(?P<uuid>[a-f0-9\-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_status'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'uuid' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-f0-9\-]{36}$/', $param);
                    }
                )
            )
        ));

        // POST /import/batch/{uuid}/retry - Retry failed files
        register_rest_route($namespace, '/import/batch/(?P<uuid>[a-f0-9\-]+)/retry', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_retry_failed'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'uuid' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-f0-9\-]{36}$/', $param);
                    }
                )
            )
        ));

        // DELETE /import/batch/{uuid} - Cancel batch
        register_rest_route($namespace, '/import/batch/(?P<uuid>[a-f0-9\-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'rest_cancel_batch'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'uuid' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-f0-9\-]{36}$/', $param);
                    }
                )
            )
        ));
    }

    /**
     * Permission callback for REST API
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * REST: Initiate batch upload
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_init_batch($request) {
        global $wpdb;

        try {
            // Get uploaded files
            $files = $request->get_file_params();
            if (empty($files) || !isset($files['files'])) {
                return new WP_Error('no_files', __('No files were uploaded.', 'poker-tournament-import'), array('status' => 400));
            }

            // Get options
            $options = $request->get_param('options');
            if (is_string($options)) {
                $options = json_decode($options, true);
            }
            if (!is_array($options)) {
                $options = array();
            }

            // Normalize file array structure
            $file_list = $this->normalize_file_array($files['files']);

            // Validate files
            $validation_result = $this->validate_uploaded_files($file_list);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Generate batch UUID
            $batch_uuid = wp_generate_uuid4();

            // Create upload directory for this batch
            $upload_dir = $this->get_batch_upload_dir($batch_uuid);
            if (!wp_mkdir_p($upload_dir)) {
                return new WP_Error('mkdir_failed', __('Failed to create upload directory.', 'poker-tournament-import'), array('status' => 500));
            }

            // Add .htaccess to prevent direct access
            file_put_contents($upload_dir . '/.htaccess', "Options -Indexes\nDeny from all");

            // Move files to batch directory and create batch records
            $accepted_files = array();
            $rejected_files = array();

            foreach ($file_list as $file) {
                $validation = $this->validate_single_file($file);

                if (is_wp_error($validation)) {
                    $rejected_files[] = array(
                        'filename' => $file['name'],
                        'reason' => $validation->get_error_message()
                    );
                    continue;
                }

                // Move file to batch directory
                $filename = sanitize_file_name($file['name']);
                $dest_path = $upload_dir . '/' . $filename;

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file -- Required for file upload
            // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Required for file upload handling
                if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                    $rejected_files[] = array(
                        'filename' => $file['name'],
                        'reason' => __('Failed to move uploaded file.', 'poker-tournament-import')
                    );
                    continue;
                }

                // Calculate file hash for deduplication
                $file_hash = hash_file('sha256', $dest_path);

                $accepted_files[] = array(
                    'filename' => $filename,
                    'filepath' => $dest_path,
                    'filesize' => $file['size'],
                    'file_hash' => $file_hash
                );
            }

            if (empty($accepted_files)) {
                return new WP_Error('no_valid_files', __('No valid files were uploaded.', 'poker-tournament-import'), array(
                    'status' => 400,
                    'rejected_files' => $rejected_files
                ));
            }

            // Create batch record
            $batch_table = $wpdb->prefix . 'poker_import_batches';
            $wpdb->insert($batch_table, array(
                'batch_uuid' => $batch_uuid,
                'user_id' => get_current_user_id(),
                'total_files' => count($accepted_files),
                'status' => 'pending',
                'processing_mode' => isset($options['processing_mode']) ? $options['processing_mode'] : 'sequential',
                'options' => wp_json_encode($options),
                'created_at' => current_time('mysql')
            ));

            $batch_id = $wpdb->insert_id;

            // Create file records
            $files_table = $wpdb->prefix . 'poker_import_batch_files';
            foreach ($accepted_files as &$file) {
                $wpdb->insert($files_table, array(
                    'batch_id' => $batch_id,
                    'filename' => $file['filename'],
                    'filepath' => $file['filepath'],
                    'filesize' => $file['filesize'],
                    'file_hash' => $file['file_hash'],
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ));

                $file['id'] = $wpdb->insert_id;
            }
            unset($file); // Break reference

            // Estimate processing time (3 seconds per file)
            $estimated_time = count($accepted_files) * 3;

            return new WP_REST_Response(array(
                'success' => true,
                'batch_uuid' => $batch_uuid,
                'total_files' => count($accepted_files),
                'files' => $accepted_files,
                'rejected_files' => $rejected_files,
                'estimated_time_seconds' => $estimated_time,
                'next_action' => 'process'
            ), 200);

        } catch (Exception $e) {
            error_log('Poker Import Bulk: Init batch error - ' . $e->getMessage());
            return new WP_Error('init_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * REST: Process single file from batch
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_process_file($request) {
        $batch_uuid = $request->get_param('uuid');
        $file_id = $request->get_param('file_id');

        // Validate file_id parameter
        if (empty($file_id) || !is_numeric($file_id)) {
            return new WP_Error(
                'missing_file_id',
                __('Missing or invalid required parameter: file_id', 'poker-tournament-import'),
                array('status' => 400)
            );
        }

        $file_id = intval($file_id);

        try {
            // Verify batch belongs to current user
            if (!$this->verify_batch_ownership($batch_uuid)) {
                return new WP_Error('unauthorized', __('Unauthorized access to batch.', 'poker-tournament-import'), array('status' => 403));
            }

            // Retrieve batch options
            global $wpdb;
            $batches_table = $wpdb->prefix . 'poker_import_batches';
            $batch = $wpdb->get_row($wpdb->prepare(
                "SELECT options FROM $batches_table WHERE batch_uuid = %s",
                $batch_uuid
            ));

            $options = array();
            if ($batch && !empty($batch->options)) {
                $options = json_decode($batch->options, true);
                if (!is_array($options)) {
                    $options = array();
                }
            }

            // Process the file
            $result = $this->batch_processor->process_single_file($file_id, $batch_uuid, $options);

            if (is_wp_error($result)) {
                return $result;
            }

            return new WP_REST_Response($result, 200);

        } catch (Exception $e) {
            error_log('Poker Import Bulk: Process file error - ' . $e->getMessage());
            return new WP_Error('process_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * REST: Get batch status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_status($request) {
        global $wpdb;

        $batch_uuid = $request->get_param('uuid');

        try {
            // Verify batch belongs to current user
            if (!$this->verify_batch_ownership($batch_uuid)) {
                return new WP_Error('unauthorized', __('Unauthorized access to batch.', 'poker-tournament-import'), array('status' => 403));
            }

            // Get batch info
            $batch_table = $wpdb->prefix . 'poker_import_batches';
            $batch = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $batch_table WHERE batch_uuid = %s",
                $batch_uuid
            ), ARRAY_A);

            if (!$batch) {
                return new WP_Error('batch_not_found', __('Batch not found.', 'poker-tournament-import'), array('status' => 404));
            }

            // Get file statuses
            $files_table = $wpdb->prefix . 'poker_import_batch_files';
            $files = $wpdb->get_results($wpdb->prepare(
                "SELECT id, filename, filesize, status, tournament_id, error_message, processing_time_ms, processed_at
                 FROM $files_table
                 WHERE batch_id = %d
                 ORDER BY id ASC",
                $batch['id']
            ), ARRAY_A);

            // Calculate progress
            $pending_count = 0;
            foreach ($files as $file) {
                if ($file['status'] === 'pending') {
                    $pending_count++;
                }
            }

            $progress_percent = $batch['total_files'] > 0
                ? (($batch['processed_count'] / $batch['total_files']) * 100)
                : 0;

            // Estimate time remaining (3 seconds per pending file)
            $estimated_time_remaining = $pending_count * 3;

            // Get current processing file
            $current_file = null;
            foreach ($files as $file) {
                if ($file['status'] === 'processing') {
                    $current_file = $file;
                    break;
                }
            }

            return new WP_REST_Response(array(
                'batch_uuid' => $batch_uuid,
                'status' => $batch['status'],
                'total_files' => intval($batch['total_files']),
                'processed_count' => intval($batch['processed_count']),
                'success_count' => intval($batch['success_count']),
                'error_count' => intval($batch['error_count']),
                'pending_count' => $pending_count,
                'progress_percent' => round($progress_percent, 1),
                'estimated_time_remaining' => $estimated_time_remaining,
                'current_file' => $current_file,
                'files' => $files
            ), 200);

        } catch (Exception $e) {
            error_log('Poker Import Bulk: Get status error - ' . $e->getMessage());
            return new WP_Error('status_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * REST: Retry failed files
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_retry_failed($request) {
        global $wpdb;

        $batch_uuid = $request->get_param('uuid');
        $file_ids = $request->get_param('file_ids'); // Optional: specific files to retry

        try {
            // Verify batch belongs to current user
            if (!$this->verify_batch_ownership($batch_uuid)) {
                return new WP_Error('unauthorized', __('Unauthorized access to batch.', 'poker-tournament-import'), array('status' => 403));
            }

            // Get batch ID
            $batch_table = $wpdb->prefix . 'poker_import_batches';
            $batch_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $batch_table WHERE batch_uuid = %s",
                $batch_uuid
            ));

            if (!$batch_id) {
                return new WP_Error('batch_not_found', __('Batch not found.', 'poker-tournament-import'), array('status' => 404));
            }

            // Reset failed files to pending
            $files_table = $wpdb->prefix . 'poker_import_batch_files';

            if (!empty($file_ids) && is_array($file_ids)) {
                // Retry specific files
                $placeholders = implode(',', array_fill(0, count($file_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE $files_table
                     SET status = 'pending', error_message = NULL, processed_at = NULL
                     WHERE batch_id = %d AND id IN ($placeholders) AND status = 'failed'",
                    array_merge(array($batch_id), $file_ids)
                ));
                $retry_count = $wpdb->rows_affected;
            } else {
                // Retry all failed files
                $wpdb->query($wpdb->prepare(
                    "UPDATE $files_table
                     SET status = 'pending', error_message = NULL, processed_at = NULL
                     WHERE batch_id = %d AND status = 'failed'",
                    $batch_id
                ));
                $retry_count = $wpdb->rows_affected;
            }

            // Update batch status back to pending if it was failed/completed
            $wpdb->update(
                $batch_table,
                array('status' => 'pending'),
                array('id' => $batch_id)
            );

            return new WP_REST_Response(array(
                'retry_count' => $retry_count,
                'batch_uuid' => $batch_uuid,
                /* translators: %d: number of failed files being retried */
                'message' => sprintf(__('Retrying %d failed file(s).', 'poker-tournament-import'), $retry_count)
            ), 200);

        } catch (Exception $e) {
            error_log('Poker Import Bulk: Retry failed error - ' . $e->getMessage());
            return new WP_Error('retry_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * REST: Cancel batch
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_cancel_batch($request) {
        global $wpdb;

        $batch_uuid = $request->get_param('uuid');

        try {
            // Verify batch belongs to current user
            if (!$this->verify_batch_ownership($batch_uuid)) {
                return new WP_Error('unauthorized', __('Unauthorized access to batch.', 'poker-tournament-import'), array('status' => 403));
            }

            // Get batch
            $batch_table = $wpdb->prefix . 'poker_import_batches';
            $batch = $wpdb->get_row($wpdb->prepare(
                "SELECT id, processed_count FROM $batch_table WHERE batch_uuid = %s",
                $batch_uuid
            ), ARRAY_A);

            if (!$batch) {
                return new WP_Error('batch_not_found', __('Batch not found.', 'poker-tournament-import'), array('status' => 404));
            }

            // Update batch status to cancelled
            $wpdb->update(
                $batch_table,
                array(
                    'status' => 'cancelled',
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $batch['id'])
            );

            // Cancel pending files
            $files_table = $wpdb->prefix . 'poker_import_batch_files';
            $wpdb->query($wpdb->prepare(
                "UPDATE $files_table SET status = 'skipped' WHERE batch_id = %d AND status = 'pending'",
                $batch['id']
            ));

            return new WP_REST_Response(array(
                'batch_uuid' => $batch_uuid,
                'status' => 'cancelled',
                'processed_before_cancel' => intval($batch['processed_count']),
                'message' => __('Batch cancelled successfully.', 'poker-tournament-import')
            ), 200);

        } catch (Exception $e) {
            error_log('Poker Import Bulk: Cancel batch error - ' . $e->getMessage());
            return new WP_Error('cancel_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on bulk import page
        if ($hook !== 'poker-import_page_poker-bulk-import') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'poker-bulk-import',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/css/bulk-import.css',
            array(),
            POKER_TOURNAMENT_IMPORT_VERSION
        );

        // Enqueue script
        wp_enqueue_script(
            'poker-bulk-import',
            POKER_TOURNAMENT_IMPORT_PLUGIN_URL . 'assets/js/bulk-import.js',
            array('jquery'),
            POKER_TOURNAMENT_IMPORT_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'poker-bulk-import',
            'pokerBulkImportSettings',
            array(
                'nonce' => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url('poker-tournament/v1'),
                'maxUploadSize' => wp_max_upload_size(),
                'maxFileUploads' => ini_get('max_file_uploads') ?: 20,
                'postMaxSize' => wp_convert_hr_to_bytes(ini_get('post_max_size')),
                'messages' => array(
                    'uploadError' => __('Upload failed. Please try again.', 'poker-tournament-import'),
                    'processingError' => __('Processing error occurred.', 'poker-tournament-import'),
                    'noFiles' => __('Please select at least one .tdt file.', 'poker-tournament-import'),
                    'fileTooBig' => __('File exceeds maximum upload size.', 'poker-tournament-import'),
                    'tooManyFiles' => __('Too many files selected.', 'poker-tournament-import'),
                    'invalidType' => __('Only .tdt files are allowed.', 'poker-tournament-import')
                )
            )
        );
    }

    /**
     * Normalize file array structure
     * PHP can send files in different formats depending on input name
     */
    private function normalize_file_array($files) {
        $normalized = array();

        // Check if files are in nested array format
        if (isset($files['name']) && is_array($files['name'])) {
            // Multiple files with array notation: files[0], files[1], etc.
            $file_count = count($files['name']);
            for ($i = 0; $i < $file_count; $i++) {
                $normalized[] = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                );
            }
        } else {
            // Single file or already normalized
            $normalized[] = $files;
        }

        return $normalized;
    }

    /**
     * Validate uploaded files array
     */
    private function validate_uploaded_files($files) {
        $max_files = ini_get('max_file_uploads') ?: 20;

        if (count($files) > $max_files) {
            /* translators: %d: maximum number of files allowed */
            return new WP_Error('too_many_files',
                sprintf(__('Too many files. Maximum allowed: %d', 'poker-tournament-import'), $max_files),
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * Validate single uploaded file
     */
    private function validate_single_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => __('File exceeds upload_max_filesize directive.', 'poker-tournament-import'),
                UPLOAD_ERR_FORM_SIZE => __('File exceeds MAX_FILE_SIZE directive.', 'poker-tournament-import'),
                UPLOAD_ERR_PARTIAL => __('File was only partially uploaded.', 'poker-tournament-import'),
                UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'poker-tournament-import'),
                UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder.', 'poker-tournament-import'),
                UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk.', 'poker-tournament-import'),
                UPLOAD_ERR_EXTENSION => __('Upload stopped by extension.', 'poker-tournament-import')
            );

            $message = isset($error_messages[$file['error']])
                ? $error_messages[$file['error']]
                : __('Unknown upload error.', 'poker-tournament-import');

            return new WP_Error('upload_error', $message);
        }

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'tdt') {
            return new WP_Error('invalid_extension', __('Only .tdt files are allowed.', 'poker-tournament-import'));
        }

        // Check file size
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            /* translators: %s: maximum file size formatted as human-readable (e.g., "10 MB") */
            return new WP_Error('file_too_large',
                sprintf(__('File exceeds maximum size of %s.', 'poker-tournament-import'), size_format($max_size))
            );
        }

        // Check MIME type (basic check, can be spoofed)
        $allowed_mimes = array('text/plain', 'application/octet-stream');
        if (!in_array($file['type'], $allowed_mimes)) {
            // Still allow if extension is correct (MIME type can be unreliable)
            if ($ext !== 'tdt') {
                return new WP_Error('invalid_mime', __('Invalid file type detected.', 'poker-tournament-import'));
            }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading uploaded file
        }

                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_fread -- Reading file content
        // Check for suspicious content (PHP code injection)
        if (file_exists($file['tmp_name'])) {
            $handle = fopen($file['tmp_name'], 'r');
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading uploaded file
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing file handle
            $sample = fread($handle, 1000);
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reading file content
            fclose($handle);

            if (preg_match('/<\?php|<\?=/i', $sample)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing file handle
                return new WP_Error('suspicious_content', __('Suspicious content detected in file.', 'poker-tournament-import'));
            }
        }

        return true;
    }

    /**
     * Get batch-specific upload directory
     */
    private function get_batch_upload_dir($batch_uuid) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/poker-imports/' . $batch_uuid;
    }

    /**
     * Verify batch ownership
     */
    private function verify_batch_ownership($batch_uuid) {
        global $wpdb;

        $batch_table = $wpdb->prefix . 'poker_import_batches';
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $batch_table WHERE batch_uuid = %s",
            $batch_uuid
        ));

        return $user_id && ($user_id == get_current_user_id() || current_user_can('manage_options'));
    }
}

// Initialize
Poker_Tournament_Bulk_Import::get_instance();
