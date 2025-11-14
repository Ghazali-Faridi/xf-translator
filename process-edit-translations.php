<?php
/**
 * Edit Translation Processing Script
 *
 * This script processes the next pending EDIT translation in the queue
 * Only processes entries with type='EDIT' and status='pending'
 * Updates existing translated posts instead of creating new ones
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

// Initialize processor
$processor = new Xf_Translator_Processor();

// Process next EDIT translation (type='EDIT', status='pending')
$result = $processor->process_next_translation('EDIT');

// Output result (for debugging/logging)
if ($result) {
    echo json_encode(array(
        'success' => true,
        'message' => 'Edit translation processed successfully',
        'type' => 'EDIT',
        'data' => $result
    ), JSON_PRETTY_PRINT);
} else {
    $error_message = $processor->get_last_error();
    if (empty($error_message)) {
        $error_message = 'No pending EDIT translations found in queue';
    }
    
    // Get the queue entry to check what failed
    global $wpdb;
    $table_name = $wpdb->prefix . 'xf_translate_queue';
    $failed_entry = $wpdb->get_row(
        "SELECT * FROM $table_name 
         WHERE status = 'failed' 
         AND type = 'EDIT'
         ORDER BY id DESC 
         LIMIT 1",
        ARRAY_A
    );
    
    $response = array(
        'success' => false,
        'message' => $error_message,
        'error' => $error_message,
        'type' => 'EDIT'
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



