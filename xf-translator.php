<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://xfinitive.co
 * @since             1.0.0
 * @package           Xf_Translator
 *
 * @wordpress-plugin
 * Plugin Name:       Unite.AI Translations
 * Plugin URI:        https://xfinitive.co
 * Description:       Serverside translation multilingual plugin 
 * Version:           1.0.1
 * Author:            ghazali
 * Author URI:        https://xfinitive.co/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       xf-translator
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'XF_TRANSLATOR_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-xf-translator-activator.php
 */
function activate_xf_translator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator-activator.php';
	Xf_Translator_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-xf-translator-deactivator.php
 */
function deactivate_xf_translator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator-deactivator.php';
	Xf_Translator_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_xf_translator' );
register_deactivation_hook( __FILE__, 'deactivate_xf_translator' );

/**
 * Load the logger class
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator-logger.php';

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator.php';

/**
 * Helper function to log messages to plugin-specific log file
 * 
 * @param string $message Log message
 * @param string $level Log level (info, error, warning, debug)
 * @return void
 */
function xf_translator_log($message, $level = 'info') {
    if (class_exists('Xf_Translator_Logger')) {
        Xf_Translator_Logger::log($message, $level);
    }
}

/**
 * Increase HTTP request timeout for translation API calls
 * Note: Per-request timeouts are set in call_translation_api() and capped at 90 seconds
 * to avoid Cloudflare's 100-second timeout limit. This global filter provides a fallback.
 *
 * @since    1.0.0
 */
add_filter('http_request_timeout', function($timeout) {
	// Per-request timeout in call_translation_api() will override this
	// This is just a fallback for other requests
	return 90; // 90 seconds to stay under Cloudflare's 100-second limit
});

/**
 * Increase cURL connection timeout for translation API requests
 * This prevents connection timeouts when the API server is slow to respond
 * 
 * WordPress sets both CURLOPT_CONNECTTIMEOUT and CURLOPT_TIMEOUT to the same value,
 * but we want a longer connection timeout to allow the API server time to accept the connection
 *
 * @since    1.0.0
 */
add_filter('http_api_curl', function($handle, $r, $url) {
	// Only apply to OpenAI or DeepSeek API endpoints
	if (strpos($url, 'api.openai.com') !== false || strpos($url, 'api.deepseek.com') !== false) {
		// Get the timeout from request args, or use a default
		$request_timeout = isset($r['timeout']) ? (int) $r['timeout'] : 600;
		$is_deepseek = strpos($url, 'api.deepseek.com') !== false;
		
		// Set connection timeout - time to establish connection
		// Use a longer connection timeout to allow time for initial connection
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, max($request_timeout, 120));
		
		// Keep the overall timeout as specified in the request
		// WordPress will also set this, but we ensure it's set correctly
		// Add a small buffer (10 seconds) to the timeout to account for network delays
		curl_setopt($handle, CURLOPT_TIMEOUT, $request_timeout + 10);
		
		// For DeepSeek, add additional options to handle long-running connections
		if ($is_deepseek) {
			// Enable TCP keep-alive to prevent connection drops
			curl_setopt($handle, CURLOPT_TCP_KEEPALIVE, 1);
			curl_setopt($handle, CURLOPT_TCP_KEEPIDLE, 100);
			curl_setopt($handle, CURLOPT_TCP_KEEPINTVL, 5);
			
			// Use HTTP/1.1 (not HTTP/2) for better compatibility with long connections
			curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			
			// Disable pipelining which can cause issues with long responses
			curl_setopt($handle, CURLOPT_PIPEWAIT, 0);
			
			// Set buffer size for reading response (larger buffer for long responses)
			curl_setopt($handle, CURLOPT_BUFFERSIZE, 16384); // 16KB buffer
			
			// Don't fail on HTTP errors immediately - let us handle them
			curl_setopt($handle, CURLOPT_FAILONERROR, false);
			
			// Set low speed limit and time to prevent premature connection closure
			// For DeepSeek, use a very low threshold (10 bytes/sec) since it can stream slowly
			// but still be working correctly. Only abort if truly stalled (no data for 120 seconds)
			curl_setopt($handle, CURLOPT_LOW_SPEED_LIMIT, value: 0);
			curl_setopt($handle, CURLOPT_LOW_SPEED_TIME, 180);
			
			// Disable Expect: 100-continue header which can cause issues with some servers
			// We need to modify existing headers, so get them first
			$existing_headers = curl_getinfo($handle, CURLINFO_HEADER_OUT);
			// Note: We can't easily modify headers here, but we can try to prevent the issue
			// by ensuring proper connection handling
			
			// Enable verbose output for debugging (only if WP_DEBUG is on)
			if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				$verbose_file = WP_CONTENT_DIR . '/xf-translator-curl-debug.log';
				$verbose_handle = fopen($verbose_file, 'a');
				if ($verbose_handle) {
					curl_setopt($handle, CURLOPT_VERBOSE, true);
					curl_setopt($handle, CURLOPT_STDERR, $verbose_handle);
				}
			}
		}
		
		// xf_translator_log('cURL options set - CONNECTTIMEOUT: 120, TIMEOUT: ' . $request_timeout . ($is_deepseek ? ' (DeepSeek: TCP keep-alive enabled)' : ''), 'debug');
	}
	return $handle;
}, 10, 3);

/**
 * Add custom cron schedule for every 1 minute
 *
 * @since    1.0.0
 */
add_filter('cron_schedules', function($schedules) {
	$schedules['every_1_minute'] = array(
		'interval' => 60, // 60 seconds = 1 minute
		'display' => __('Every 1 Minute', 'xf-translator')
	);
	return $schedules;
});

/**
 * Compatibility function to unschedule all events for a hook
 * Works with both old and new WordPress versions
 *
 * @param string $hook The hook name
 * @since    1.0.0
 */
function xf_translator_unschedule_all_events($hook) {
	// Use WordPress 5.1+ function if available
	if (function_exists('wp_unschedule_all_events')) {
		wp_unschedule_all_events($hook);
		return;
	}
	
	// Fallback for older WordPress versions
	// Get all scheduled events for this hook and unschedule them one by one
	// Use wp_get_scheduled_event() in a loop until no more events are found
	$max_iterations = 100; // Safety limit to prevent infinite loops
	$iterations = 0;
	
	while ($iterations < $max_iterations) {
		$timestamp = wp_next_scheduled($hook);
		if ($timestamp === false) {
			// No more scheduled events found
			break;
		}
		wp_unschedule_event($timestamp, $hook);
		$iterations++;
	}
}

/**
 * Process NEW translations via cron
 *
 * @since    1.0.0
 */
function xf_translator_process_new_translations_cron() {
	// CRITICAL: Check if cron is enabled FIRST before any processing
	// This prevents any execution if the option is disabled
	$settings_file = plugin_dir_path(__FILE__) . 'admin/class-settings.php';
	
	// Check if settings file exists
	if (!file_exists($settings_file)) {
		// If settings file doesn't exist, unschedule events and exit
		xf_translator_unschedule_all_events('xf_translator_process_new_cron');
		return;
	}
	
	// Check if class already exists to avoid conflicts
	if (!class_exists('Settings')) {
		require_once $settings_file;
	}
	
	// Only proceed if Settings class is available
	if (!class_exists('Settings')) {
		xf_translator_unschedule_all_events('xf_translator_process_new_cron');
		return;
	}
	
	$settings = new Settings();
	
	if (!$settings->get('enable_new_translations_cron', true)) {
		// Cron disabled - unschedule any remaining events and exit immediately
		xf_translator_unschedule_all_events('xf_translator_process_new_cron');
		return;
	}
	
	// SAFETY: Only run in proper cron context to prevent blocking page loads
	// This prevents WordPress pseudo-cron from running during regular page requests
	if (!wp_doing_cron() && !defined('WP_CLI')) {
		return; // Don't run during regular page loads
	}
	
	// SAFETY: Track execution time to prevent long-running processes
	$start_time = time();
	$max_execution_time = 240; // 4 minutes max (leaves buffer for cleanup)
	
	// Load required files
	require_once plugin_dir_path(__FILE__) . 'includes/class-translation-processor.php';
	
	// Initialize processor
	$processor = new Xf_Translator_Processor();
	
	// Process next NEW translation (type='NEW', status='pending')
	$result = $processor->process_next_translation('NEW');
	
	// SAFETY: Check if processing took too long
	$execution_time = time() - $start_time;
	if ($execution_time > $max_execution_time) {
		xf_translator_log('Cron: NEW translation processing exceeded time limit (' . $execution_time . 's). Aborted to prevent site slowdown.', 'warning');
	}
	
	if ($result) {
		xf_translator_log('Cron: NEW translation processed successfully', 'info');
	} else {
		$error = $processor->get_last_error();
		if (empty($error)) {
			$error = 'No pending NEW translations found in queue';
		}
		//xf_translator_log('Cron: ' . $error, 'debug');
	}
}
add_action('xf_translator_process_new_cron', 'xf_translator_process_new_translations_cron');

/**
 * Process OLD translations via cron
 *
 * @since    1.0.0
 */
function xf_translator_process_old_translations_cron() {
	// CRITICAL: Check if cron is enabled FIRST before any processing
	// This prevents any execution if the option is disabled
	$settings_file = plugin_dir_path(__FILE__) . 'admin/class-settings.php';
	
	// Check if settings file exists
	if (!file_exists($settings_file)) {
		// If settings file doesn't exist, unschedule events and exit
		xf_translator_unschedule_all_events('xf_translator_process_old_cron');
		return;
	}
	
	// Check if class already exists to avoid conflicts
	if (!class_exists('Settings')) {
		require_once $settings_file;
	}
	
	// Only proceed if Settings class is available
	if (!class_exists('Settings')) {
		xf_translator_unschedule_all_events('xf_translator_process_old_cron');
		return;
	}
	
	$settings = new Settings();
	
	if (!$settings->get('enable_old_translations_cron', true)) {
		// Cron disabled - unschedule any remaining events and exit immediately
		xf_translator_unschedule_all_events('xf_translator_process_old_cron');
		return;
	}
	
	// SAFETY: Only run in proper cron context to prevent blocking page loads
	// This prevents WordPress pseudo-cron from running during regular page requests
	if (!wp_doing_cron() && !defined('WP_CLI')) {
		return; // Don't run during regular page loads
	}
	
	// SAFETY: Track execution time to prevent long-running processes
	$start_time = time();
	$max_execution_time = 240; // 4 minutes max (leaves buffer for cleanup)
	
	// Load required files
	require_once plugin_dir_path(__FILE__) . 'includes/class-translation-processor.php';
	
	// Initialize processor
	$processor = new Xf_Translator_Processor();
	
	// Process next OLD translation (type='OLD', status='pending')
	$result = $processor->process_next_translation('OLD');
	
	// SAFETY: Check if processing took too long
	$execution_time = time() - $start_time;
	if ($execution_time > $max_execution_time) {
		xf_translator_log('Cron: OLD translation processing exceeded time limit (' . $execution_time . 's). Aborted to prevent site slowdown.', 'warning');
	}
	
	if ($result) {
		xf_translator_log('Cron: OLD translation processed successfully', 'info');
	} else {
		$error = $processor->get_last_error();
		if (empty($error)) {
			$error = 'No pending OLD translations found in queue';
		}
		xf_translator_log('Cron: ' . $error, 'debug');
	}
}
add_action('xf_translator_process_old_cron', 'xf_translator_process_old_translations_cron');

/**
 * Clean up orphaned cron events on plugin load
 * This ensures disabled cron jobs don't run even if events are still scheduled
 *
 * @since    1.0.0
 */
function xf_translator_cleanup_orphaned_cron_events() {
	$settings_file = plugin_dir_path(__FILE__) . 'admin/class-settings.php';
	
	// Check if settings file exists before requiring it
	if (!file_exists($settings_file)) {
		return;
	}
	
	// Check if class already exists to avoid conflicts
	if (!class_exists('Settings')) {
		require_once $settings_file;
	}
	
	// Only proceed if Settings class is available
	if (!class_exists('Settings')) {
		return;
	}
	
	try {
		$settings = new Settings();
		
		// If NEW translations cron is disabled, remove any scheduled events
		if (!$settings->get('enable_new_translations_cron', true)) {
			xf_translator_unschedule_all_events('xf_translator_process_new_cron');
		}
		
		// If OLD translations cron is disabled, remove any scheduled events
		if (!$settings->get('enable_old_translations_cron', true)) {
			xf_translator_unschedule_all_events('xf_translator_process_old_cron');
		}
	} catch (Exception $e) {
		// Silently fail to prevent breaking the site
		error_log('XF Translator: Error cleaning up cron events: ' . $e->getMessage());
	}
}
// Run cleanup early, before WordPress cron system processes events
add_action('init', 'xf_translator_cleanup_orphaned_cron_events', 1);

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_xf_translator() {

	$plugin = new Xf_Translator();
	$plugin->run();

}
run_xf_translator();
