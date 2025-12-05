<?php
/**
 * Existing Post Queue Dashboard Tab
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'xf_translate_queue';

// Get queue statistics for OLD type
$pending_old_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending' AND type = 'OLD'");
$processing_old_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'processing' AND type = 'OLD'");
$completed_old_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed' AND type = 'OLD'");
$failed_old_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed' AND type = 'OLD'");

// Jobs are now loaded via AJAX for better performance and pagination
?>

<div class="api-translator-section">
    <h2><?php _e('Analyze Existing', 'xf-translator'); ?></h2>
    
    <div style="margin: 20px 0; padding: 15px; background: #fff; border-left: 4px solid #0073aa;">
        <h3 style="margin-top: 0;"><?php _e('Analyze English Posts & Pages', 'xf-translator'); ?></h3>
        
        <?php
        // Check if there's an active job
        $active_job = get_transient('xf_analyze_active_job');
        $cron_url = site_url('/wp-content/plugins/xf-translator/analyze-posts.php');
        ?>
        
        <?php if ($active_job && $active_job['status'] === 'processing'): ?>
            <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <h4 style="margin-top: 0;"><?php _e('Active Analysis Job', 'xf-translator'); ?></h4>
                <p><strong><?php _e('Status:', 'xf-translator'); ?></strong> <?php _e('Processing', 'xf-translator'); ?></p>
                <p><strong><?php _e('Progress:', 'xf-translator'); ?></strong> 
                    <?php echo number_format($active_job['processed_posts']); ?> / <?php echo number_format($active_job['total_posts']); ?> posts 
                    (<?php echo $active_job['total_posts'] > 0 ? round(($active_job['processed_posts'] / $active_job['total_posts']) * 100) : 0; ?>%)
                </p>
                <p><strong><?php _e('Queue Entries Added:', 'xf-translator'); ?></strong> <?php echo number_format($active_job['added_entries']); ?></p>
                
                <p style="margin-top: 15px;">
                    <strong><?php _e('Cron URL:', 'xf-translator'); ?></strong><br>
                    <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0; word-break: break-all;"><?php echo esc_html($cron_url); ?></code>
                </p>
                
                <p>
                    <a href="<?php echo esc_url($cron_url); ?>" target="_blank" class="button button-primary"><?php _e('View Progress Page', 'xf-translator'); ?></a>
                </p>
                
            </div>
        <?php elseif ($active_job && $active_job['status'] === 'completed'): ?>
            <div style="margin: 20px 0; padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">
                <h4 style="margin-top: 0;"><?php _e('Last Analysis Job - Completed', 'xf-translator'); ?></h4>
                <p><strong><?php _e('Processed:', 'xf-translator'); ?></strong> <?php echo number_format($active_job['processed_posts']); ?> / <?php echo number_format($active_job['total_posts']); ?> posts</p>
                <p><strong><?php _e('Queue Entries Added:', 'xf-translator'); ?></strong> <?php echo number_format($active_job['added_entries']); ?></p>
                <?php if (isset($active_job['completed_at'])): ?>
                    <p><strong><?php _e('Completed:', 'xf-translator'); ?></strong> <?php echo date('Y-m-d H:i:s', strtotime($active_job['completed_at'])); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p><?php _e('Select a post type below and click the button to analyze all English content of that type and check which translations are missing.', 'xf-translator'); ?></p>
        <?php
        $selected_start_date = '';
        $selected_end_date = '';
        if (isset($_POST['analyze_start_date'])) {
            $selected_start_date = sanitize_text_field($_POST['analyze_start_date']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_start_date)) {
                $selected_start_date = '';
            }
        }
        if (isset($_POST['analyze_end_date'])) {
            $selected_end_date = sanitize_text_field($_POST['analyze_end_date']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_end_date)) {
                $selected_end_date = '';
            }
        }
        ?>
        <form method="post" action="" style="margin-top: 10px;">
            <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
            <input type="hidden" name="api_translator_action" value="analyze_posts">
            
            <?php
            // Get all public post types, excluding attachment (media) since media can't be translated
            $post_types = get_post_types(array('public' => true), 'objects');
            // Remove attachment post type (media files)
            if (isset($post_types['attachment'])) {
                unset($post_types['attachment']);
            }
            // Get selected post types from POST (form submission) or GET (after redirect) or default to 'post'
            $selected_post_types = array('post');
            if (isset($_POST['analyze_post_type']) && is_array($_POST['analyze_post_type'])) {
                $selected_post_types = array_map('sanitize_text_field', $_POST['analyze_post_type']);
                // Filter out invalid post types
                $selected_post_types = array_filter($selected_post_types, function($type) {
                    return post_type_exists($type) && $type !== 'attachment';
                });
                if (empty($selected_post_types)) {
                    $selected_post_types = array('post');
                }
            } elseif (isset($_POST['analyze_post_type'])) {
                // Handle single value (backward compatibility)
                $selected_post_types = array(sanitize_text_field($_POST['analyze_post_type']));
            } elseif (isset($_GET['post_type'])) {
                $selected_post_types = array(sanitize_text_field($_GET['post_type']));
            }
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="analyze_post_type"><?php _e('Post Types', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <select name="analyze_post_type[]" id="analyze_post_type" multiple style="min-width: 200px; height: 150px;">
                            <?php foreach ($post_types as $post_type_key => $post_type_obj) : ?>
                                <option value="<?php echo esc_attr($post_type_key); ?>" <?php selected(in_array($post_type_key, $selected_post_types), true); ?>>
                                    <?php echo esc_html($post_type_obj->label); ?> (<?php echo esc_html($post_type_key); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Hold Ctrl (Windows) or Cmd (Mac) to select multiple post types. Select which post types you want to analyze.', 'xf-translator'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="analyze_start_date"><?php _e('Start date (optional)', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="analyze_start_date" name="analyze_start_date" value="<?php echo esc_attr($selected_start_date); ?>" style="max-width: 220px;">
                        <p class="description">
                            <?php _e('Only analyze posts published on or after this date.', 'xf-translator'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="analyze_end_date"><?php _e('End date (optional)', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="analyze_end_date" name="analyze_end_date" value="<?php echo esc_attr($selected_end_date); ?>" style="max-width: 220px;">
                        <p class="description">
                            <?php _e('Only analyze posts published on or before this date.', 'xf-translator'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Analyze Selected Post Types', 'xf-translator'), 'primary', 'submit', false); ?>
        </form>
    </div>
    
    <?php settings_errors('api_translator_messages'); ?>
</div>

<div class="api-translator-section">
    <h2><?php _e('Queue Statistics (OLD Posts)', 'xf-translator'); ?></h2>
    
    <div class="api-translator-stats" style="display: flex; gap: 20px; margin: 20px 0;">
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #f0ad4e;">
            <h3 style="margin: 0; font-size: 32px; color: #f0ad4e;"><?php echo number_format($pending_old_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Pending Jobs', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #0073aa;">
            <h3 style="margin: 0; font-size: 32px; color: #0073aa;"><?php echo number_format($processing_old_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Processing', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #46b450;">
            <h3 style="margin: 0; font-size: 32px; color: #46b450;"><?php echo number_format($completed_old_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Completed', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #dc3232;">
            <h3 style="margin: 0; font-size: 32px; color: #dc3232;"><?php echo number_format($failed_old_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Failed', 'xf-translator'); ?></p>
        </div>
    </div>
    
    <?php if ($processing_old_count > 0) : ?>
        <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa;">
            <h3 style="margin-top: 0;"><?php _e('Reset Stuck Processing Jobs', 'xf-translator'); ?></h3>
            <p style="margin: 10px 0; color: #666;">
                <?php _e('If any jobs are stuck in "processing" status for more than 5 minutes, you can reset them back to "pending" so they can be retried.', 'xf-translator'); ?>
            </p>
            <form method="post" action="" style="margin-top: 10px;">
                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                <input type="hidden" name="api_translator_action" value="reset_stuck_processing">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset all processing jobs that have been stuck for more than 5 minutes?', 'xf-translator')); ?>');">
                    <?php _e('Reset Stuck Processing Jobs', 'xf-translator'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="api-translator-section">
    <h2><?php _e('Translation Jobs (OLD Posts)', 'xf-translator'); ?></h2>
    
    <!-- Filters and Search -->
    <div style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label for="existing-job-status-filter" style="display: block; margin-bottom: 5px; font-weight: bold;">
                    <?php _e('Filter by Status:', 'xf-translator'); ?>
                </label>
                <select id="existing-job-status-filter" style="min-width: 150px;">
                    <option value=""><?php _e('All Statuses', 'xf-translator'); ?></option>
                    <option value="pending"><?php _e('Pending', 'xf-translator'); ?></option>
                    <option value="processing"><?php _e('Processing', 'xf-translator'); ?></option>
                    <option value="completed"><?php _e('Completed', 'xf-translator'); ?></option>
                    <option value="failed"><?php _e('Failed', 'xf-translator'); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label for="existing-job-search" style="display: block; margin-bottom: 5px; font-weight: bold;">
                    <?php _e('Search by Post Name:', 'xf-translator'); ?>
                </label>
                <input type="text" id="existing-job-search" placeholder="<?php esc_attr_e('Enter post name...', 'xf-translator'); ?>" style="width: 100%; max-width: 300px;">
            </div>
            <div style="align-self: flex-end;">
                <button type="button" id="clear-existing-filters" class="button" style="margin-top: 20px;">
                    <?php _e('Clear Filters', 'xf-translator'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="existing-jobs-loading" style="text-align: center; padding: 20px; display: none;">
        <span class="spinner is-active" style="float: none; margin: 0;"></span>
        <p><?php _e('Loading jobs...', 'xf-translator'); ?></p>
    </div>
    
    <!-- Jobs Table Container -->
    <div id="existing-jobs-table-container">
        <table class="wp-list-table widefat fixed striped" id="existing-translation-jobs-table">
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
            <tbody id="existing-jobs-table-body">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">
                        <?php _e('Loading jobs...', 'xf-translator'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="existing-jobs-pagination" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <span id="existing-jobs-pagination-info"></span>
            </div>
            <div style="display: flex; gap: 5px;">
                <button type="button" id="existing-jobs-prev-page" class="button" disabled>
                    <?php _e('Previous', 'xf-translator'); ?>
                </button>
                <span id="existing-jobs-page-numbers" style="display: flex; align-items: center; gap: 5px;"></span>
                <button type="button" id="existing-jobs-next-page" class="button" disabled>
                    <?php _e('Next', 'xf-translator'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- <div class="api-translator-section">
    <h2><?php _e('Recent Queue Activity (OLD Posts)', 'xf-translator'); ?></h2>
    
    <?php if (empty($recent_old_jobs)) : ?>
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
                <?php foreach ($recent_old_jobs as $job) : ?>
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
                                <?php echo esc_html($job['type'] ?: 'OLD'); ?>
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
                                <?php if ($job['status'] === 'processing' || $job['status'] === 'completed' || $job['status'] === 'failed') : ?>
                                    <span style="color: #999; font-size: 12px;"><?php _e('No API log available', 'xf-translator'); ?></span>
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
                                <span style="color: #999;"><?php _e('Not created', 'xf-translator'); ?></span>
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



