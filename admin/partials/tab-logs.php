<?php
/**
 * Logs Tab
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle log clearing
if (isset($_POST['clear_log']) && check_admin_referer('xf_translator_clear_log', 'xf_translator_clear_log_nonce')) {
    if (class_exists('Xf_Translator_Logger')) {
        $cleared = Xf_Translator_Logger::clear_log();
        if ($cleared) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Log file cleared successfully.', 'xf-translator') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to clear log file.', 'xf-translator') . '</p></div>';
        }
    }
}

// Get log file info
$log_file_path = '';
$log_file_size = '0 B';
$log_content = '';

if (class_exists('Xf_Translator_Logger')) {
    $log_file_path = Xf_Translator_Logger::get_log_file_path();
    $log_file_size = Xf_Translator_Logger::get_log_file_size();
    
    if (file_exists($log_file_path)) {
        // Read last 500 lines of log file
        $lines = file($log_file_path);
        if ($lines) {
            $total_lines = count($lines);
            $start_line = max(0, $total_lines - 500);
            $log_content = implode('', array_slice($lines, $start_line));
            
            if ($total_lines > 500) {
                $log_content = '... (showing last 500 lines of ' . number_format($total_lines) . ' total lines) ...' . "\n\n" . $log_content;
            }
        }
    }
}
?>

<div class="api-translator-section">
    <h2><?php _e('Plugin Logs', 'xf-translator'); ?></h2>
    <p><?php _e('View debug logs specific to the XF Translator plugin. Logs are stored separately from WordPress debug.log.', 'xf-translator'); ?></p>
    
    <?php if (!defined('WP_DEBUG') || !WP_DEBUG) : ?>
        <div class="notice notice-warning">
            <p><?php _e('WP_DEBUG is not enabled. Logging is disabled. To enable logging, add <code>define(\'WP_DEBUG\', true);</code> to your wp-config.php file.', 'xf-translator'); ?></p>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div>
                <strong><?php _e('Log File:', 'xf-translator'); ?></strong>
                <code style="margin-left: 10px;"><?php echo esc_html($log_file_path ?: __('Not available', 'xf-translator')); ?></code>
                <br>
                <strong><?php _e('File Size:', 'xf-translator'); ?></strong>
                <span style="margin-left: 10px;"><?php echo esc_html($log_file_size); ?></span>
            </div>
            <?php if ($log_file_path && file_exists($log_file_path)) : ?>
                <form method="post" action="" style="display: inline-block;">
                    <?php wp_nonce_field('xf_translator_clear_log', 'xf_translator_clear_log_nonce'); ?>
                    <input type="hidden" name="clear_log" value="1">
                    <button type="submit" class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear the log file?', 'xf-translator'); ?>');">
                        <?php _e('Clear Log', 'xf-translator'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($log_file_path && file_exists($log_file_path)) : ?>
            <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.5;">
                <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($log_content ?: __('Log file is empty.', 'xf-translator')); ?></pre>
            </div>
            <p style="margin-top: 10px; color: #666; font-size: 12px;">
                <?php _e('Note: Only the last 500 lines are shown. To view the full log, download the file directly.', 'xf-translator'); ?>
            </p>
        <?php else : ?>
            <div style="padding: 20px; background: #f9f9f9; border: 1px solid #ddd; text-align: center;">
                <p><?php _e('No log file found. Logs will be created when WP_DEBUG is enabled and the plugin starts logging.', 'xf-translator'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
        <h3><?php _e('About Plugin Logs', 'xf-translator'); ?></h3>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><?php _e('Logs are stored in: <code>wp-content/uploads/xf-translator-logs/xf-translator.log</code>', 'xf-translator'); ?></li>
            <li><?php _e('Logs are automatically rotated when the file size exceeds 10MB.', 'xf-translator'); ?></li>
            <li><?php _e('Only the last 5 backup files are kept.', 'xf-translator'); ?></li>
            <li><?php _e('Logging only works when WP_DEBUG is enabled in wp-config.php.', 'xf-translator'); ?></li>
            <li><?php _e('Log files are protected from direct web access via .htaccess.', 'xf-translator'); ?></li>
        </ul>
    </div>
</div>

