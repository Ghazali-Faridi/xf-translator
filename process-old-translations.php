<?php
/**
 * Old Translation Processing Script
 *
 * This script processes the next pending OLD translation in the queue
 * Only processes entries with type='OLD' and status='pending'
 * Can be accessed directly or via cron job
 *
 * @package Xf_Translator
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    // Fallback: try relative path
    require_once('../../../wp-load.php');
}

// Exit if accessed directly without proper authentication
if (!defined('ABSPATH')) {
    exit;
}

// Optional: Add security check (uncomment if needed)
// if (!current_user_can('manage_options') && !wp_doing_cron()) {
//     exit('Unauthorized');
// }

// Load required files
require_once plugin_dir_path(__FILE__) . 'admin/class-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-translation-processor.php';

// Bounded parallelism: exit immediately if at capacity (no new workers)
$settings = new Settings();
$max_concurrent = (int) $settings->get('max_concurrent_processing', 20);
global $wpdb;
$queue_table = $wpdb->prefix . 'xf_translate_queue';
$processing_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$queue_table} WHERE status = 'processing'");
if ($processing_count >= $max_concurrent) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => 'At capacity', 'at_capacity' => true, 'type' => 'OLD'));
    exit;
}

// Start output buffering to prevent 502 Bad Gateway errors during long operations
// This keeps the HTTP connection alive by sending periodic data
if (!ob_get_level()) {
    ob_start();
}

// Send initial keep-alive data to prevent connection timeout
if (ob_get_level()) {
    echo str_repeat(' ', 1024); // Send 1KB of whitespace
    ob_flush();
    flush();
}

// Initialize processor
$processor = new Xf_Translator_Processor();

// Process next OLD translation (type='OLD', status='pending')
$result = $processor->process_next_translation('OLD');

// Output result (for debugging/logging)
if ($result) {
    echo json_encode(array(
        'success' => true,
        'message' => 'Old translation processed successfully',
        'type' => 'OLD',
        'data' => $result
    ), JSON_PRETTY_PRINT);
} else {
    $error_message = $processor->get_last_error();
    if (empty($error_message)) {
        $error_message = 'No pending OLD translations found in queue';
    }
    
    // Get the queue entry to check what failed
    global $wpdb;
    $table_name = $wpdb->prefix . 'xf_translate_queue';
    $failed_entry = $wpdb->get_row(
        "SELECT * FROM $table_name 
         WHERE status = 'failed' 
         AND type = 'OLD'
         ORDER BY id DESC 
         LIMIT 1",
        ARRAY_A
    );
    
    $response = array(
        'success' => false,
        'message' => $error_message,
        'error' => $error_message,
        'type' => 'OLD'
    );
    
    // If there's a failed entry, try to get the raw response
    if ($failed_entry && isset($failed_entry['parent_post_id'])) {
        $raw_response = get_post_meta($failed_entry['parent_post_id'], '_xf_translator_raw_response_' . $failed_entry['id'], true);
        if ($raw_response) {
            $response['raw_response_preview'] = substr($raw_response, 0, 500);
            $response['raw_response_length'] = strlen($raw_response);
            $response['queue_entry_id'] = $failed_entry['id'];
            $response['post_id'] = $failed_entry['parent_post_id'];
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}



