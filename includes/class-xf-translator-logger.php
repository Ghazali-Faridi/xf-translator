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

