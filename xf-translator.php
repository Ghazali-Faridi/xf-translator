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
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator.php';

/**
 * Increase HTTP request timeout for translation API calls
 * This ensures large translation requests don't timeout
 *
 * @since    1.0.0
 */
add_filter('http_request_timeout', function($timeout) {
	return 600; // 5 minutes
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
		
		// Set connection timeout to 120 seconds - time to establish connection
		// This is separate from the overall request timeout
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 120);
		
		// Keep the overall timeout as specified in the request
		// WordPress will also set this, but we ensure it's set correctly
		curl_setopt($handle, CURLOPT_TIMEOUT, $request_timeout);
		
		error_log('XF Translator: cURL options set - CONNECTTIMEOUT: 120, TIMEOUT: ' . $request_timeout);
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
