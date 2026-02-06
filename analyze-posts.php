<?php
/**
 * Post Analysis Script for System Cron
 *
 * This script analyzes posts and creates queue entries for missing translations
 * Processes 50 posts per run to avoid timeouts
 * 
 * Usage:
 * - Cron: curl https://yoursite.com/wp-content/plugins/xf-translator/analyze-posts.php
 * - Browser: Visit the URL to see progress
 *
 * @package Xf_Translator
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    require_once('../../../wp-load.php');
}

if (!defined('ABSPATH')) {
    exit;
}

// Security: Check secret key from wp-config.php or use default
$secret_key = defined('XF_ANALYZE_SECRET_KEY') ? XF_ANALYZE_SECRET_KEY : 'your-secret-key-change-me';

// Check if this is a browser request (for progress display) or cron request
$is_browser = !empty($_SERVER['HTTP_USER_AGENT']) && 
              strpos($_SERVER['HTTP_USER_AGENT'], 'curl') === false &&
              strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === false;

// For cron requests, verify secret key via header or GET param (optional)
if (!$is_browser) {
    $provided_key = isset($_SERVER['HTTP_X_SECRET_KEY']) ? $_SERVER['HTTP_X_SECRET_KEY'] : 
                   (isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '');
    
    if ($secret_key !== $provided_key && $secret_key !== 'your-secret-key-change-me') {
        http_response_code(403);
        echo json_encode(array(
            'success' => false,
            'message' => 'Unauthorized. Invalid secret key.'
        ), JSON_PRETTY_PRINT);
        exit;
    }
}

// Set execution time limit
@set_time_limit(300);

// Load required files
require_once plugin_dir_path(__FILE__) . 'admin/class-settings.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-xf-translator-admin.php';

// Initialize
$settings = new Settings();
$admin = new Xf_Translator_Admin('xf-translator', '1.0.0');

// Get active job (stored with fixed key)
$job_data = get_transient('xf_analyze_active_job');

// If no active job and this is a browser request, show "no job" message
if (!$job_data && $is_browser) {
    show_progress_page(null);
    exit;
}

// If no active job, nothing to do
if (!$job_data) {
    if ($is_browser) {
        show_progress_page(null);
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'No active analysis job found. Create a job from the admin panel first.'
        ), JSON_PRETTY_PRINT);
    }
    exit;
}

// Check if job is complete
if ($job_data['status'] === 'completed') {
    if ($is_browser) {
        show_progress_page($job_data, true);
    } else {
        echo json_encode(array(
            'success' => true,
            'message' => 'Job already completed',
            'processed' => $job_data['processed_posts'],
            'total' => $job_data['total_posts'],
            'added' => $job_data['added_entries']
        ), JSON_PRETTY_PRINT);
    }
    exit;
}

// Process batch
global $wpdb;
$table_name = $wpdb->prefix . 'xf_translate_queue';

$languages = $settings->get('languages', array());
$batch_size = 50;
$processed = $job_data['processed_posts'];
$total = $job_data['total_posts'];
$added = $job_data['added_entries'];
$batch_added = 0;
$batch_processed = 0;

// Process each post type
foreach ($job_data['post_types'] as $post_type) {
    if ($batch_processed >= $batch_size) {
        break;
    }
    
    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $batch_size - $batch_processed,
        'offset' => $processed,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => '_xf_translator_original_post_id', 'compare' => 'NOT EXISTS'),
            array('key' => '_api_translator_original_post_id', 'compare' => 'NOT EXISTS'),
            array('key' => '_xf_translator_language', 'compare' => 'NOT EXISTS')
        ),
        'orderby' => 'ID',
        'order' => 'ASC'
    );
    
    // Add date filters
    if (!empty($job_data['start_date']) || !empty($job_data['end_date'])) {
        $range = array('inclusive' => true);
        if (!empty($job_data['start_date'])) {
            $range['after'] = $job_data['start_date'] . ' 00:00:00';
        }
        if (!empty($job_data['end_date'])) {
            $range['before'] = $job_data['end_date'] . ' 23:59:59';
        }
        $args['date_query'] = array($range);
    }
    
    $post_ids = get_posts($args);
    
    if (empty($post_ids)) {
        continue;
    }
    
    // Bulk check translations and queue entries
    $translation_meta = $admin->bulk_get_translation_meta($post_ids, $languages);
    $existing_entries = $admin->bulk_check_existing_entries($post_ids, $languages);
    
    // Prepare bulk insert
    $values = array();
    foreach ($post_ids as $post_id) {
        foreach ($languages as $language) {
            $key = $post_id . '_' . $language['name'];
            
            $has_translation = isset($translation_meta[$key]) && !empty($translation_meta[$key]);
            $has_queue_entry = isset($existing_entries[$key]);
            
            if (!$has_translation && !$has_queue_entry) {
                $values[] = $wpdb->prepare(
                    "(%d, NULL, %s, 'pending', 'OLD', %s)",
                    $post_id,
                    $language['name'],
                    current_time('mysql')
                );
            }
        }
    }
    
    // Bulk insert
    if (!empty($values)) {
        $query = "INSERT INTO $table_name (parent_post_id, translated_post_id, lng, status, type, created) VALUES " . implode(', ', $values);
        $result = $wpdb->query($query);
        
        if ($result !== false) {
            $batch_added += count($values);
        } else {
            error_log('XF Translator: Bulk insert failed: ' . $wpdb->last_error);
        }
    }
    
    $batch_processed += count($post_ids);
    
    // Track processed post IDs (limit to last 100 to avoid memory issues)
    if (!isset($job_data['processed_post_ids'])) {
        $job_data['processed_post_ids'] = array();
    }
    $job_data['processed_post_ids'] = array_merge($job_data['processed_post_ids'], $post_ids);
    // Keep only last 100 post IDs
    if (count($job_data['processed_post_ids']) > 100) {
        $job_data['processed_post_ids'] = array_slice($job_data['processed_post_ids'], -100);
    }
}

// Update job data
$job_data['processed_posts'] = $processed + $batch_processed;
$job_data['added_entries'] = $added + $batch_added;
$job_data['last_updated'] = current_time('mysql');

// Check if complete
$is_complete = ($processed + $batch_processed) >= $total || $batch_processed === 0;
if ($is_complete) {
    $job_data['status'] = 'completed';
    $job_data['completed_at'] = current_time('mysql');
}

// Save updated job data
set_transient('xf_analyze_active_job', $job_data, DAY_IN_SECONDS);

// Calculate progress
$progress = $total > 0 ? round(($job_data['processed_posts'] / $total) * 100) : 0;

// Output based on request type
if ($is_browser) {
    // Show progress page with auto-refresh
    show_progress_page($job_data, $is_complete);
} else {
    // JSON output for cron
    echo json_encode(array(
        'success' => true,
        'processed' => $job_data['processed_posts'],
        'total' => $total,
        'added_this_batch' => $batch_added,
        'total_added' => $job_data['added_entries'],
        'progress' => $progress,
        'is_complete' => $is_complete,
        'status' => $job_data['status']
    ), JSON_PRETTY_PRINT);
}

/**
 * Show progress page (HTML)
 */
function show_progress_page($job_data, $is_complete = false) {
    $progress = 0;
    if ($job_data && $job_data['total_posts'] > 0) {
        $progress = round(($job_data['processed_posts'] / $job_data['total_posts']) * 100);
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Post Analysis Progress - XF Translator</title>
        <?php if (!$is_complete && $job_data): ?>
        <meta http-equiv="refresh" content="5">
        <?php endif; ?>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                margin-top: 0;
                color: #23282d;
            }
            .progress-bar-container {
                background: #f0f0f0;
                border-radius: 4px;
                height: 40px;
                margin: 20px 0;
                overflow: hidden;
                position: relative;
            }
            .progress-bar {
                background: linear-gradient(90deg, #0073aa 0%, #005177 100%);
                height: 100%;
                transition: width 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 14px;
            }
            .stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin: 20px 0;
            }
            .stat-box {
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #0073aa;
                border-radius: 4px;
            }
            .stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                margin-bottom: 5px;
            }
            .stat-value {
                font-size: 24px;
                font-weight: bold;
                color: #23282d;
            }
            .status {
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .status.processing {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                color: #856404;
            }
            .status.completed {
                background: #d4edda;
                border-left: 4px solid #28a745;
                color: #155724;
            }
            .status.no-job {
                background: #f8d7da;
                border-left: 4px solid #dc3545;
                color: #721c24;
            }
            .info {
                color: #666;
                font-size: 14px;
                margin-top: 20px;
            }
            .refresh-note {
                background: #e7f3ff;
                padding: 10px;
                border-radius: 4px;
                margin-top: 20px;
                font-size: 12px;
                color: #005177;
            }
            .cron-url {
                background: #f5f5f5;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
                font-family: monospace;
                word-break: break-all;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Post Analysis Progress</h1>
            
            <?php if (!$job_data): ?>
                <div class="status no-job">
                    <strong>No Active Job</strong>
                    <p>No analysis job is currently running. Please create a job from the WordPress admin panel.</p>
                </div>
            <?php elseif ($is_complete): ?>
                <div class="status completed">
                    <strong>✓ Analysis Complete!</strong>
                    <p>All posts have been analyzed and queue entries have been created.</p>
                </div>
                
                <div class="stats">
                    <div class="stat-box">
                        <div class="stat-label">Processed</div>
                        <div class="stat-value"><?php echo number_format($job_data['processed_posts']); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Posts</div>
                        <div class="stat-value"><?php echo number_format($job_data['total_posts']); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Queue Entries Added</div>
                        <div class="stat-value"><?php echo number_format($job_data['added_entries']); ?></div>
                    </div>
                </div>
                
                <?php if (isset($job_data['completed_at'])): ?>
                <div class="info">
                    <strong>Completed:</strong> <?php echo date('Y-m-d H:i:s', strtotime($job_data['completed_at'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="info" style="margin-top: 20px;">
                    <h3 style="margin-top: 0; margin-bottom: 10px;"><?php _e('Analyzed Posts', 'xf-translator'); ?></h3>
                    <?php 
                    if (isset($job_data['processed_post_ids']) && !empty($job_data['processed_post_ids'])) {
                        // Get post titles for processed post IDs (show last 50)
                        $display_post_ids = array_slice($job_data['processed_post_ids'], -50);
                        $posts = get_posts(array(
                            'post__in' => $display_post_ids,
                            'posts_per_page' => 50,
                            'orderby' => 'post__in',
                            'post_status' => 'any'
                        ));
                        
                        if (!empty($posts)) {
                            echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f9f9f9;">';
                            echo '<ul style="margin: 0; padding-left: 20px;">';
                            foreach ($posts as $post) {
                                $edit_link = admin_url('post.php?action=edit&post=' . $post->ID);
                                echo '<li style="margin: 5px 0; padding: 5px;">';
                                echo '<a href="' . esc_url($edit_link) . '" target="_blank" style="text-decoration: none; color: #0073aa;">';
                                echo esc_html($post->post_title);
                                echo '</a>';
                                echo ' <span style="color: #666; font-size: 12px;">(ID: ' . $post->ID . ')</span>';
                                echo '</li>';
                            }
                            echo '</ul>';
                            if (count($job_data['processed_post_ids']) > 50) {
                                echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666; font-style: italic;">';
                                echo sprintf(__('Showing last 50 of %d analyzed posts', 'xf-translator'), count($job_data['processed_post_ids']));
                                echo '</p>';
                            }
                            echo '</div>';
                        } else {
                            echo '<p style="color: #666; font-style: italic;">' . __('No posts analyzed yet.', 'xf-translator') . '</p>';
                        }
                    } else {
                        echo '<p style="color: #666; font-style: italic;">' . __('No posts analyzed yet.', 'xf-translator') . '</p>';
                    }
                    ?>
                </div>
                
            <?php else: ?>
                <div class="status processing">
                    <strong>Processing...</strong>
                    <p>Analyzing posts and creating queue entries. This page will auto-refresh every 5 seconds.</p>
                </div>
                
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo $progress; ?>%;">
                        <?php echo $progress; ?>%
                    </div>
                </div>
                
                <div class="stats">
                    <div class="stat-box">
                        <div class="stat-label">Processed</div>
                        <div class="stat-value"><?php echo number_format($job_data['processed_posts']); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Posts</div>
                        <div class="stat-value"><?php echo number_format($job_data['total_posts']); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Queue Entries Added</div>
                        <div class="stat-value"><?php echo number_format($job_data['added_entries']); ?></div>
                    </div>
                </div>
                
                <div class="info">
                    <strong>Progress:</strong> <?php echo $job_data['processed_posts']; ?> of <?php echo $job_data['total_posts']; ?> posts analyzed
                    <?php if (isset($job_data['last_updated'])): ?>
                    <br><strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s', strtotime($job_data['last_updated'])); ?>
                    <?php endif; ?>
                </div>
                
                <div class="refresh-note">
                    <strong>Note:</strong> This page automatically refreshes every 5 seconds. You can close this tab - the analysis will continue via cron job.
                </div>
            <?php endif; ?>
            
 
        </div>
    </body>
    </html>
    <?php
}

