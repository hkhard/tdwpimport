<?php
/**
 * Debug Class
 *
 * Handles debugging and logging functionality for the plugin
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

class Poker_Tournament_Import_Debug {
    // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug/diagnostic class

    /**
     * Debug messages array
     */
    private static $debug_messages = array();

    /**
     * Start time for performance tracking
     */
    private static $start_time;

    /**
     * Initialize debug system
     */
    public static function init() {
        self::$start_time = microtime(true);
        self::log('=== TDT Import Debug System Initialized ===');
        self::log('Debug Mode: ' . (self::is_debug_enabled() ? 'ENABLED' : 'DISABLED'));
        self::log('Debug Logging: ' . (self::is_logging_enabled() ? 'ENABLED' : 'DISABLED'));
        self::log('PHP Version: ' . PHP_VERSION);
        self::log('WordPress Version: ' . get_bloginfo('version'));
        self::log('Plugin Version: 1.0.1');
        self::log('Server Info: ' . (isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown'));
    }

    /**
     * Check if debug mode is enabled
     */
    public static function is_debug_enabled() {
        return get_option('tdwp_import_debug_mode', 0) === '1';
    }

    /**
     * Check if debug logging is enabled
     */
    public static function is_logging_enabled() {
        return get_option('tdwp_import_debug_logging', 0) === '1';
    }

    /**
     * Check if debug is enabled for current import
     */
    public static function is_import_debug_enabled() {
        return self::is_debug_enabled() ||
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Debug class, no user input modification
               (isset($_POST['enable_debug_this_import']) && $_POST['enable_debug_this_import'] === '1');
    }

    /**
     * Log debug message
     */
    public static function log($message, $data = null) {
        // Only process debug messages if debug is enabled
        if (!self::is_import_debug_enabled()) {
            return;
        }

        $timestamp = gmdate('Y-m-d H:i:s') . '.' . substr(microtime(), 2, 3);
        $memory_usage = memory_get_usage(true);
        $memory_usage_mb = round($memory_usage / 1024 / 1024, 2);

        $log_entry = "[{$timestamp}] [Memory: {$memory_usage_mb}MB] {$message}";

        // Add data if provided
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $log_entry .= "\nData: " . print_r($data, true);
            } else {
                $log_entry .= "\nData: " . var_export($data, true);
            }
        }

        // Store messages only when debug is enabled
        self::$debug_messages[] = array(
            'timestamp' => $timestamp,
            'message' => $message,
            'data' => $data,
            'memory' => $memory_usage_mb
        );

        // Log to error log if enabled
        if (self::is_logging_enabled()) {
            error_log('TDT Import: ' . $log_entry);
        }
    }

    /**
     * Log performance timing
     */
    public static function log_time($label) {
        $current_time = microtime(true);
        $elapsed = round(($current_time - self::$start_time) * 1000, 2);
        self::log("⏱️ {$label}: {$elapsed}ms elapsed");
    }

    /**
     * Log function execution
     */
    public static function log_function($function_name, $args = array()) {
        self::log("🔧 Function: {$function_name}", $args);
    }

    /**
     * Log WordPress operations
     */
    public static function log_wp_operation($operation, $result = null) {
        self::log("📝 WP Operation: {$operation}", $result);
    }

    /**
     * Log GameHistoryItem processing
     */
    public static function log_game_history($type, $data = null) {
        self::log("🎮 GameHistory: {$type}", $data);
    }

    /**
     * Log winner detection
     */
    public static function log_winner_detection($winner_name, $source, $timestamp = null) {
        $message = "🏆 Winner Detected: {$winner_name} (Source: {$source})";
        if ($timestamp) {
            $message .= " at {$timestamp}";
        }
        self::log($message);
    }

    /**
     * Log elimination processing
     */
    public static function log_elimination($eliminated, $eliminator, $source = 'unknown') {
        self::log("⚔️  Elimination: {$eliminated} eliminated by {$eliminator} (Source: {$source})");
    }

    /**
     * Log error
     */
    public static function log_error($message, $exception = null) {
        self::log("❌ ERROR: {$message}");
        if ($exception && is_object($exception)) {
            self::log("Exception: " . $exception->getMessage());
            self::log("Trace: " . $exception->getTraceAsString());
        } elseif ($exception) {
            if (is_array($exception)) {
                self::log("Exception Data: " . print_r($exception, true));
            } else {
                self::log("Exception: " . (string) $exception);
            }
        }
    }

    /**
     * Log success
     */
    public static function log_success($message, $data = null) {
        self::log("✅ SUCCESS: {$message}", $data);
    }

    /**
     * Log warning
     */
    public static function log_warning($message, $data = null) {
        self::log("⚠️ WARNING: {$message}", $data);
    }

    /**
     * Get all debug messages
     */
    public static function get_debug_messages() {
        return self::$debug_messages;
    }

    /**
     * Clear debug messages
     */
    public static function clear_debug_messages() {
        self::$debug_messages = array();
    }

    /**
     * Render debug output
     */
    public static function render_debug_output() {
        if (empty(self::$debug_messages)) {
            return '<div class="import-debug-output"><div class="debug-header"><h4>🐛 Debug Information</h4></div><div class="debug-messages"><p>No debug messages recorded.</p></div></div>';
        }

        $total_time = round((microtime(true) - self::$start_time) * 1000, 2);
        $peak_memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        ob_start();
        ?>
        <div class="import-debug-output">
            <div class="debug-header">
                <h4>🐛 Debug Information</h4>
                <div class="debug-stats">
                    <span>⏱️ Total Time: <?php echo esc_html($total_time); ?>ms</span>
                    <span>💾 Peak Memory: <?php echo esc_html($peak_memory); ?>MB</span>
                    <span>📝 Messages: <?php echo esc_html(count(self::$debug_messages)); ?></span>
                </div>
            </div>

            <div class="debug-messages">
                <?php foreach (self::$debug_messages as $index => $msg): ?>
                    <div class="debug-message <?php echo esc_attr(self::get_message_class($msg['message'])); ?>">
                        <div class="debug-message-header">
                            <span class="debug-index">#<?php echo esc_html($index + 1); ?></span>
                            <span class="debug-timestamp"><?php echo esc_html($msg['timestamp']); ?></span>
                            <span class="debug-memory"><?php echo esc_html($msg['memory']); ?>MB</span>
                        </div>
                        <div class="debug-message-content">
                            <?php echo esc_html($msg['message']); ?>
                        </div>
                        <?php if ($msg['data'] !== null): ?>
                            <div class="debug-data">
                                <pre><?php echo esc_html(print_r($msg['data'], true)); ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
        .import-debug-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin: 20px 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 12px;
        }

        .debug-header {
            background: #e9ecef;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
        }

        .debug-header h4 {
            margin: 0 0 10px 0;
            color: #495057;
        }

        .debug-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .debug-stats span {
            background: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .debug-messages {
            max-height: 500px;
            overflow-y: auto;
            padding: 10px;
        }

        .debug-message {
            margin-bottom: 10px;
            border-left: 4px solid #6c757d;
            background: #fff;
            border-radius: 0 4px 4px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .debug-message.error {
            border-left-color: #dc3545;
        }

        .debug-message.success {
            border-left-color: #28a745;
        }

        .debug-message.warning {
            border-left-color: #ffc107;
        }

        .debug-message-header {
            background: #f8f9fa;
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
        }

        .debug-index {
            background: #0073aa;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .debug-timestamp {
            color: #6c757d;
        }

        .debug-memory {
            color: #17a2b8;
            font-weight: bold;
        }

        .debug-message-content {
            padding: 10px 12px;
            color: #495057;
            line-height: 1.4;
        }

        .debug-data {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 10px;
            overflow-x: auto;
        }

        .debug-data pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #495057;
        }

        @media (max-width: 782px) {
            .debug-stats {
                flex-direction: column;
                gap: 5px;
            }

            .debug-message-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Get CSS class for message type
     */
    private static function get_message_class($message) {
        $message = strtolower($message);
        if (strpos($message, 'error') !== false || strpos($message, '❌') !== false) {
            return 'error';
        }
        if (strpos($message, 'success') !== false || strpos($message, '✅') !== false) {
            return 'success';
        }
        if (strpos($message, 'warning') !== false || strpos($message, '⚠️') !== false) {
            return 'warning';
        }
        return '';
    }
}