<?php
/**
 * Translation Queue Dashboard Tab
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'xf_translate_queue';

// Get queue statistics (only NEW and EDIT types, exclude OLD)
$pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending' AND type IN ('NEW', 'EDIT')");
$processing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'processing' AND type IN ('NEW', 'EDIT')");
$completed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed' AND type IN ('NEW', 'EDIT')");
$failed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed' AND type IN ('NEW', 'EDIT')");

// Get pending jobs (only NEW and EDIT types)
$pending_jobs = $wpdb->get_results(
    "SELECT * FROM $table_name 
     WHERE  type IN ('NEW', 'EDIT')
     ORDER BY id ASC 
     LIMIT 50",
    ARRAY_A
);

// Get recent completed/failed jobs (only NEW and EDIT types)
$recent_jobs = $wpdb->get_results(
    "SELECT * FROM $table_name 
     WHERE status IN ('completed', 'failed', 'processing')
     AND type IN ('NEW', 'EDIT')
     ORDER BY id DESC 
     LIMIT 50",
    ARRAY_A
);
?>

<div class="api-translator-section">
    <h2><?php _e('Queue Statistics', 'xf-translator'); ?></h2>
    
    <div class="api-translator-stats" style="display: flex; gap: 20px; margin: 20px 0;">
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #f0ad4e;">
            <h3 style="margin: 0; font-size: 32px; color: #f0ad4e;"><?php echo number_format($pending_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Pending Jobs', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #0073aa;">
            <h3 style="margin: 0; font-size: 32px; color: #0073aa;"><?php echo number_format($processing_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Processing', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #46b450;">
            <h3 style="margin: 0; font-size: 32px; color: #46b450;"><?php echo number_format($completed_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Completed', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #dc3232;">
            <h3 style="margin: 0; font-size: 32px; color: #dc3232;"><?php echo number_format($failed_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Failed', 'xf-translator'); ?></p>
        </div>
    </div>
    
    <?php if ($pending_count > 0) : ?>
        <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa;">
            <h3 style="margin-top: 0;"><?php _e('Process Pending Jobs', 'xf-translator'); ?></h3>
            <!-- <p><?php _e('Click the button below to process the next pending translation job. You can also access the processing script directly or set it up as a cron job.', 'xf-translator'); ?></p> -->
            <form method="post" action="" style="margin-top: 10px;">
                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                <input type="hidden" name="api_translator_action" value="process_queue">
                <!-- <?php submit_button(__('Process Next Pending Job', 'xf-translator'), 'primary', 'submit', false); ?> -->
            </form>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <?php _e('Processing URL:', 'xf-translator'); ?> 
                <code><?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'process-translation.php'); ?></code>
            </p>
        </div>
    <?php endif; ?>
    
    <?php settings_errors('api_translator_messages'); ?>
</div>

<div class="api-translator-section">
    <h2><?php _e('Pending Translation Jobs', 'xf-translator'); ?></h2>
    
    <?php if (empty($pending_jobs)) : ?>
        <p><?php _e('No pending jobs in the queue.', 'xf-translator'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('ID', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Post', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Language', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Type', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Status', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Created', 'xf-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_jobs as $job) : ?>
                    <?php
                    $post = get_post($job['parent_post_id']);
                    $post_title = $post ? $post->post_title : __('Post not found', 'xf-translator');
                    $post_edit_link = $post ? get_edit_post_link($job['parent_post_id']) : '#';
                    ?>
                    <tr>
                        <td>
                            <strong>#<?php echo esc_html($job['id']); ?></strong>
                        </td>
                        <td>
                            <?php if ($post) : ?>
                                <a href="<?php echo esc_url($post_edit_link); ?>" target="_blank">
                                    <?php echo esc_html($post_title); ?>
                                </a>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['parent_post_id']); ?></small>
                            <?php else : ?>
                                <?php echo esc_html($post_title); ?>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['parent_post_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($job['lng']); ?>
                        </td>
                        <td>
                            <span style="padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html($job['type'] ?: 'NEW'); ?>
                            </span>
                        </td>
                        <td>
                           <?php 
                            $color="#f0ad4e";
                            if($job['status']=="pending"){
                                $color="#f0ad4e";
                            }

                             if($job['status']=="completed"){
                                $color="green";
                            }

                             if($job['status']=="failed"){
                                $color="red";
                            }

                            $error_message = isset($job['error_message']) ? $job['error_message'] : '';
                            
                            // Set color for processing status
                            if($job['status']=="processing"){
                                $color="#0073aa";
                            }
                            ?>
                            <span style="padding: 3px 8px; background: <?php echo $color; ?>; color: #fff; border-radius: 3px; font-size: 11px; margin-right: 5px;">
                                <?php echo esc_html(ucfirst($job['status'])); ?>
                            </span>
                            <?php if($job['status']=="failed" || $job['status']=="processing") : ?>
                                <div style="margin-top: 5px;">
                                    <?php if($job['status']=="failed" && !empty($error_message)) : ?>
                                        <button type="button" 
                                                class="button button-small view-error-detail" 
                                                data-error-message="<?php echo esc_attr($error_message); ?>"
                                                data-queue-id="<?php echo esc_attr($job['id']); ?>"
                                                style="margin-right: 5px; font-size: 11px;">
                                            <?php _e('View Detail', 'xf-translator'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <form method="post" action="" style="display: inline-block; margin: 0;">
                                        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                        <input type="hidden" name="api_translator_action" value="retry_queue_entry">
                                        <input type="hidden" name="queue_entry_id" value="<?php echo esc_attr($job['id']); ?>">
                                        <button type="submit" class="button button-small" style="background: #46b450; color: #fff; border-color: #46b450; font-size: 11px;">
                                            <?php _e('Retry', 'xf-translator'); ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($job['created']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- <div class="api-translator-section">
    <h2><?php _e('Recent Queue Activity', 'xf-translator'); ?></h2>
    
    <?php if (empty($recent_jobs)) : ?>
        <p><?php _e('No recent activity.', 'xf-translator'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('ID', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Post', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Language', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Type', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Status', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Error/Logs', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Translated Post', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Actions', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Created', 'xf-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_jobs as $job) : ?>
                    <?php
                    $post = get_post($job['parent_post_id']);
                    $post_title = $post ? $post->post_title : __('Post not found', 'xf-translator');
                    $post_edit_link = $post ? get_edit_post_link($job['parent_post_id']) : '#';
                    
                    $translated_post = $job['translated_post_id'] ? get_post($job['translated_post_id']) : null;
                    $translated_post_title = $translated_post ? $translated_post->post_title : '';
                    $translated_post_edit_link = $translated_post ? get_edit_post_link($job['translated_post_id']) : '#';
                    
                    $status_color = '#46b450'; // green
                    if ($job['status'] === 'failed') {
                        $status_color = '#dc3232'; // red
                    } elseif ($job['status'] === 'processing') {
                        $status_color = '#0073aa'; // blue
                    }
                    
                    // Get error message if failed
                    $error_message = isset($job['error_message']) ? $job['error_message'] : '';
                    
                    // Get API log (always try to retrieve, even if post doesn't exist)
                    $api_log = null;
                    $log_meta_key = '_xf_translator_api_log_' . $job['id'];
                    $api_log_json = get_post_meta($job['parent_post_id'], $log_meta_key, true);
                    
                    if ($api_log_json) {
                        $api_log = json_decode($api_log_json, true);
                        // If JSON decode failed, silently handle it (don't log on every page load)
                        // Only log if WP_DEBUG is enabled and it's a critical issue
                        if ($api_log === null && json_last_error() !== JSON_ERROR_NONE) {
                            // Silently set to null - corrupted log data, not a critical error
                            $api_log = null;
                            // Only log once per queue entry to avoid spam (check if we've logged this before)
                            $error_logged_key = '_xf_translator_log_error_logged_' . $job['id'];
                            if (!get_post_meta($job['parent_post_id'], $error_logged_key, true) && defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('XF Translator: Failed to decode API log JSON for queue ID ' . $job['id'] . ': ' . json_last_error_msg());
                                error_log('XF Translator: Raw JSON (first 500 chars): ' . substr($api_log_json, 0, 500));
                                // Mark as logged to avoid repeated logs
                                update_post_meta($job['parent_post_id'], $error_logged_key, '1');
                            }
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <strong>#<?php echo esc_html($job['id']); ?></strong>
                        </td>
                        <td>
                            <?php if ($post) : ?>
                                <a href="<?php echo esc_url($post_edit_link); ?>" target="_blank">
                                    <?php echo esc_html($post_title); ?>
                                </a>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['parent_post_id']); ?></small>
                            <?php else : ?>
                                <?php echo esc_html($post_title); ?>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['parent_post_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($job['lng']); ?>
                        </td>
                        <td>
                            <span style="padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html($job['type'] ?: 'NEW'); ?>
                            </span>
                        </td>
                        <td>
                            <span style="padding: 3px 8px; background: <?php echo esc_attr($status_color); ?>; color: #fff; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html(ucfirst($job['status'])); ?>
                            </span>
                        </td>
                        <td style="max-width: 300px;">
                            <?php if ($error_message) : ?>
                                <div style="margin-bottom: 10px;">
                                    <strong style="color: #dc3232;"><?php _e('Error:', 'xf-translator'); ?></strong>
                                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                        <?php echo esc_html($error_message); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($api_log) : ?>
                                <details style="margin-top: 10px;" open>
                                    <summary style="cursor: pointer; color: #0073aa; font-size: 12px; font-weight: bold;">
                                        <?php _e('View API Log', 'xf-translator'); ?>
                                    </summary>
                                    <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 11px; max-height: 400px; overflow-y: auto;">
                                        <?php if (isset($api_log['endpoint'])) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <strong><?php _e('Endpoint:', 'xf-translator'); ?></strong> 
                                                <code><?php echo esc_html($api_log['endpoint']); ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['model'])) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <strong><?php _e('Model:', 'xf-translator'); ?></strong> 
                                                <code><?php echo esc_html($api_log['model']); ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <div style="margin-bottom: 10px;">
                                            <strong><?php _e('Request Body:', 'xf-translator'); ?></strong>
                                            <pre style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; overflow-x: auto; font-size: 10px; max-height: 250px; white-space: pre-wrap; word-wrap: break-word;"><?php 
                                                $request_body = isset($api_log['request']) ? $api_log['request'] : array();
                                                echo esc_html(json_encode($request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                                            ?></pre>
                                        </div>
                                        <?php if (isset($api_log['error'])) : ?>
                                            <div style="margin-bottom: 10px; padding: 8px; background: #ffe6e6; border-left: 3px solid #dc3232; border-radius: 3px;">
                                                <strong style="color: #dc3232;"><?php _e('Error:', 'xf-translator'); ?></strong>
                                                <div style="margin-top: 5px; color: #666;">
                                                    <?php echo esc_html($api_log['error']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['response_code'])) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <strong><?php _e('Response Code:', 'xf-translator'); ?></strong> 
                                                <code style="padding: 2px 6px; background: <?php echo $api_log['response_code'] == 200 ? '#d4edda' : '#f8d7da'; ?>; border-radius: 3px;">
                                                    <?php echo esc_html($api_log['response_code']); ?>
                                                </code>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['response_body']) && !empty($api_log['response_body'])) : ?>
                                            <div>
                                                <strong><?php _e('Response Body:', 'xf-translator'); ?></strong>
                                                <pre style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; overflow-x: auto; font-size: 10px; max-height: 250px; white-space: pre-wrap; word-wrap: break-word;"><?php 
                                                    $response_body = $api_log['response_body'];
                                                    // Try to pretty print if it's JSON
                                                    $decoded = json_decode($response_body, true);
                                                    if ($decoded) {
                                                        echo esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                                    } else {
                                                        echo esc_html($response_body);
                                                    }
                                                ?></pre>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['timestamp'])) : ?>
                                            <div style="margin-top: 10px; font-size: 10px; color: #999;">
                                                <?php _e('Timestamp:', 'xf-translator'); ?> <?php echo esc_html($api_log['timestamp']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php else : ?>
                                <?php if ($job['status'] === 'completed' || $job['status'] === 'failed' || $job['status'] === 'processing') : ?>
                                    <div style="font-size: 12px; color: #999;">
                                        <?php _e('No API log available', 'xf-translator'); ?>
                                        <?php if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) : ?>
                                            <br><small style="color: #666;">
                                                Debug: Post ID: <?php echo esc_html($job['parent_post_id']); ?>, 
                                                Queue ID: <?php echo esc_html($job['id']); ?>, 
                                                Meta Key: _xf_translator_api_log_<?php echo esc_html($job['id']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($translated_post) : ?>
                                <a href="<?php echo esc_url($translated_post_edit_link); ?>" target="_blank">
                                    <?php echo esc_html($translated_post_title); ?>
                                </a>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['translated_post_id']); ?></small>
                            <?php else : ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($job['status'] === 'failed') : ?>
                                <form method="post" action="" style="display: inline-block; margin: 0;">
                                    <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                    <input type="hidden" name="api_translator_action" value="retry_queue_entry">
                                    <input type="hidden" name="queue_entry_id" value="<?php echo esc_attr($job['id']); ?>">
                                    <button type="submit" class="button button-small" style="background: #46b450; color: #fff; border-color: #46b450;">
                                        <?php _e('Retry', 'xf-translator'); ?>
                                    </button>
                                </form>
                            <?php else : ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($job['created']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div> -->

<!-- Error Detail Modal -->
<div id="error-detail-modal" class="api-translator-modal" style="display: none;">
    <div class="api-translator-modal-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
        <div class="api-translator-modal-header" style="padding: 15px 20px; background: #dc3232; color: #fff; border-bottom: 1px solid #ddd;">
            <h2 style="margin: 0; font-size: 18px;"><?php _e('Translation Error Details', 'xf-translator'); ?></h2>
            <span class="api-translator-modal-close" style="float: right; cursor: pointer; font-size: 24px; line-height: 1; opacity: 0.8;">&times;</span>
        </div>
        <div class="api-translator-modal-body" style="padding: 20px;">
            <div id="error-detail-content">
                <p style="margin: 0; color: #666;"><?php _e('Loading error details...', 'xf-translator'); ?></p>
            </div>
        </div>
        <div class="api-translator-modal-footer" style="padding: 15px 20px; background: #f9f9f9; border-top: 1px solid #ddd; text-align: right;">
            <button type="button" class="button api-translator-modal-close"><?php _e('Close', 'xf-translator'); ?></button>
        </div>
    </div>
</div>

<style>
.api-translator-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.api-translator-modal-content {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    position: relative;
}

.api-translator-modal-close {
    cursor: pointer;
}

.api-translator-modal-close:hover {
    opacity: 1 !important;
}

#error-detail-content {
    word-wrap: break-word;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 13px;
    line-height: 1.6;
    color: #333;
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border-left: 4px solid #dc3232;
}
</style>

