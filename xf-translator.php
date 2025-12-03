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
 * Version:           1.0.0
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
		
		// Set connection timeout to 120 seconds - time to establish connection
		// This is separate from the overall request timeout
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 120);
		
		// Keep the overall timeout as specified in the request
		// WordPress will also set this, but we ensure it's set correctly
		curl_setopt($handle, CURLOPT_TIMEOUT, $request_timeout);
		
		// For DeepSeek, add additional options to handle long-running connections
		if ($is_deepseek) {
			// Enable TCP keep-alive to prevent connection drops
			curl_setopt($handle, CURLOPT_TCP_KEEPALIVE, 1);
			curl_setopt($handle, CURLOPT_TCP_KEEPIDLE, 30);
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
			// If transfer speed drops below 100 bytes/sec for 60 seconds, consider it a problem
			curl_setopt($handle, CURLOPT_LOW_SPEED_LIMIT, 100);
			curl_setopt($handle, CURLOPT_LOW_SPEED_TIME, 60);
			
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
		
		xf_translator_log('cURL options set - CONNECTTIMEOUT: 120, TIMEOUT: ' . $request_timeout . ($is_deepseek ? ' (DeepSeek: TCP keep-alive enabled)' : ''), 'debug');
	}
	return $handle;
}, 10, 3);

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
