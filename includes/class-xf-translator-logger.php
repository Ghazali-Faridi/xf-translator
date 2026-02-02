<?php
/**
 * Logger Class for XF Translator Plugin
 * 
 * Writes logs to a plugin-specific log file instead of the main WordPress debug.log
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Xf_Translator_Logger {
    
    /**
     * Log file path
     *
     * @var string
     */
    private static $log_file = null;
    
    /**
     * Maximum log file size in bytes (default: 10MB)
     *
     * @var int
     */
    private static $max_file_size = 10485760; // 10MB
    
    /**
     * Get the log file path
     *
     * @return string Log file path
     */
    private static function get_log_file() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/xf-translator-logs';
            
            // Create log directory if it doesn't exist
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                // Add .htaccess to protect log files
                $htaccess_file = $log_dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    file_put_contents($htaccess_file, "deny from all\n");
                }
            }
            
            self::$log_file = $log_dir . '/xf-translator.log';
        }
        
        return self::$log_file;
    }
    
    /**
     * Rotate log file if it's too large
     *
     * @return void
     */
    private static function rotate_log_if_needed() {
        $log_file = self::get_log_file();
        
        if (file_exists($log_file) && filesize($log_file) > self::$max_file_size) {
            $backup_file = $log_file . '.' . date('Y-m-d-His') . '.bak';
            @rename($log_file, $backup_file);
            
            // Keep only last 5 backup files
            $backup_files = glob($log_file . '.*.bak');
            if (count($backup_files) > 5) {
                // Sort by modification time and remove oldest
                usort($backup_files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                foreach (array_slice($backup_files, 0, count($backup_files) - 5) as $old_file) {
                    @unlink($old_file);
                }
            }
        }
    }
    
    /**
     * Write a log message to the plugin-specific log file
     *
     * @param string $message Log message
     * @param string $level Log level (info, error, warning, debug)
     * @return void
     */
    public static function log($message, $level = 'info') {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        // By default, only log warnings and errors to reduce log volume
        // Allow debug/info logging only if explicitly enabled via constant
        $level_lower = strtolower($level);
        $log_debug = defined('XF_TRANSLATOR_LOG_DEBUG') && XF_TRANSLATOR_LOG_DEBUG;
        
        // Skip debug and info messages unless explicitly enabled
        if (in_array($level_lower, array('debug', 'info')) && !$log_debug) {
            return;
        }
        
        $log_file = self::get_log_file();
        
        // Rotate log if needed
        self::rotate_log_if_needed();
        
        // Format log entry
        $timestamp = current_time('mysql');
        $level_upper = strtoupper($level);
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level_upper,
            $message
        );
        
        // Write to log file
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log an info message
     *
     * @param string $message Log message
     * @return void
     */
    public static function info($message) {
        self::log($message, 'info');
    }
    
    /**
     * Log an error message
     *
     * @param string $message Log message
     * @return void
     */
    public static function error($message) {
        self::log($message, 'error');
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @return void
     */
    public static function warning($message) {
        self::log($message, 'warning');
    }
    
    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @return void
     */
    public static function debug($message) {
        self::log($message, 'debug');
    }
    
    /**
     * Log API request/response data
     *
     * @param string $type Request or Response
     * @param array $data Data to log
     * @return void
     */
    public static function log_api($type, $data) {
        $message = sprintf(
            'XF Translator API %s: %s',
            $type,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        self::log($message, 'info');
    }
    
    /**
     * Log minimized API request/response data (without bodies)
     * Only saves essential information to reduce log size
     *
     * @param string $type Request or Response
     * @param array $data Data to log
     * @return void
     */
    public static function log_api_minimal($type, $data) {
        // Create minimized log with only essential info (no request/response bodies)
        $minimal_data = array(
            'type' => $type,
            'timestamp' => isset($data['timestamp']) ? $data['timestamp'] : current_time('mysql'),
            'post_id' => isset($data['post_id']) ? (int)$data['post_id'] : 0,
            'queue_id' => isset($data['queue_id']) ? (int)$data['queue_id'] : 0,
            'endpoint' => isset($data['endpoint']) ? $data['endpoint'] : '',
            'model' => isset($data['model']) ? $data['model'] : '',
            'api_type' => isset($data['api_type']) ? $data['api_type'] : '',
            'target_language' => isset($data['target_language']) ? $data['target_language'] : '',
            'response_code' => isset($data['response_code']) ? (int)$data['response_code'] : 0,
            'is_error' => isset($data['is_error']) ? (bool)$data['is_error'] : false,
        );
        
        // Only add size information (not the actual content)
        if (isset($data['request'])) {
            $request_size = is_string($data['request']) ? strlen($data['request']) : strlen(json_encode($data['request']));
            $minimal_data['request_size'] = $request_size;
        }
        
        if (isset($data['response_body'])) {
            $minimal_data['response_size'] = strlen($data['response_body']);
        }
        
        // Only include error message if there's an error
        if (!empty($data['error'])) {
            $minimal_data['error'] = $data['error'];
        }
        
        // For requests, also include if API key is configured
        if (isset($data['api_key_configured'])) {
            $minimal_data['api_key_configured'] = (bool)$data['api_key_configured'];
        }
        
        // For chunked requests, include chunk info
        if (isset($data['chunk_num'])) {
            $minimal_data['chunk_num'] = (int)$data['chunk_num'];
        }
        if (isset($data['total_chunks'])) {
            $minimal_data['total_chunks'] = (int)$data['total_chunks'];
        }
        
        $message = sprintf(
            'XF Translator API %s: %s',
            $type,
            json_encode($minimal_data, JSON_UNESCAPED_UNICODE)
        );
        
        // Always log API calls with appropriate level
        $level = ($minimal_data['is_error'] || !empty($minimal_data['error'])) ? 'error' : 'info';
        
        // Log directly to file (bypass WP_DEBUG check for API logs)
        $log_file = self::get_log_file();
        self::rotate_log_if_needed();
        
        $timestamp = current_time('mysql');
        $level_upper = strtoupper($level);
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level_upper,
            $message
        );
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get the log file URL for admin viewing
     *
     * @return string|false Log file URL or false if not accessible
     */
    public static function get_log_file_url() {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/xf-translator-logs/xf-translator.log';
        
        if (file_exists($log_file)) {
            return $upload_dir['baseurl'] . '/xf-translator-logs/xf-translator.log';
        }
        
        return false;
    }
    
    /**
     * Get the log file path
     *
     * @return string Log file path
     */
    public static function get_log_file_path() {
        return self::get_log_file();
    }
    
    /**
     * Clear the log file
     *
     * @return bool True on success, false on failure
     */
    public static function clear_log() {
        $log_file = self::get_log_file();
        if (file_exists($log_file)) {
            return @file_put_contents($log_file, '') !== false;
        }
        return true;
    }
    
    /**
     * Get log file size in human-readable format
     *
     * @return string File size
     */
    public static function get_log_file_size() {
        $log_file = self::get_log_file();
        if (file_exists($log_file)) {
            $size = filesize($log_file);
            return size_format($size, 2);
        }
        return '0 B';
    }
}

