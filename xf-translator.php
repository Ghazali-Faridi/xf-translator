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
 * Version:           1.1.1
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
	try {
		$activator_file = plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator-activator.php';
		if (file_exists($activator_file)) {
			require_once $activator_file;
			if (class_exists('Xf_Translator_Activator')) {
				Xf_Translator_Activator::activate();
			}
		}
	} catch (Throwable $e) {
		// Log error but don't fail activation completely
		if (function_exists('error_log')) {
			error_log('XF Translator: Activation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
		}
	}
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
$logger_file = plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator-logger.php';
if (file_exists($logger_file)) {
	require_once $logger_file;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
$core_file = plugin_dir_path( __FILE__ ) . 'includes/class-xf-translator.php';
if (file_exists($core_file)) {
	require $core_file;
}

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
 * Global error handler to suppress "Packets out of order" warnings from wpdb
 * These warnings occur due to stale database connections after long-running operations
 * and don't indicate actual errors - the operations complete successfully
 *
 * @param int $errno Error number
 * @param string $errstr Error message
 * @param string $errfile Error file
 * @param int $errline Error line
 * @return bool True if error was suppressed, false to let WordPress handle it
 */
function xf_translator_suppress_packets_warning($errno, $errstr, $errfile, $errline) {
	// Suppress "Packets out of order" warnings from wpdb
	// These are harmless connection state warnings that occur after long operations
	if (strpos($errstr, 'Packets out of order') !== false && 
		strpos($errfile, 'class-wpdb.php') !== false) {
		return true; // Suppress this warning - don't log it
	}
	// Let other errors be handled normally by WordPress
	return false;
}

// Set up error handler early to catch all "Packets out of order" warnings
// Only set if WordPress core functions are available
// Priority 999 ensures it runs before most other error handlers
if (function_exists('add_action')) {
	// Store previous error handler to restore if needed
	$previous_handler = set_error_handler('xf_translator_suppress_packets_warning', E_WARNING | E_NOTICE);
}

/**
 * Register hooks after WordPress is loaded
 * This prevents fatal errors during activation on PHP 8.3
 */
function xf_translator_register_hooks() {
	// Check if WordPress functions are available
	if (!function_exists('add_filter') || !function_exists('add_action')) {
		return;
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
		// Use a reasonable connection timeout (30 seconds) instead of max()
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
		
		// Keep the overall timeout as specified in the request
		// WordPress will also set this, but we ensure it's set correctly
		// Add a small buffer (10 seconds) to the timeout to account for network delays
		curl_setopt($handle, CURLOPT_TIMEOUT, $request_timeout + 10);
		
		// CRITICAL: Disable low speed limit completely to prevent transfer timeout
		// This is the key fix - prevents cURL from aborting on slow transfers
		curl_setopt($handle, CURLOPT_LOW_SPEED_LIMIT, 0);
		curl_setopt($handle, CURLOPT_LOW_SPEED_TIME, 0); // Disable completely
		
		// Increase buffer size for reading response (match test plugin)
		curl_setopt($handle, CURLOPT_BUFFERSIZE, 32768); // 32KB buffer (increased from 16KB)
		
		// Don't fail on HTTP errors immediately - let us handle them
		curl_setopt($handle, CURLOPT_FAILONERROR, false);
		
		// For DeepSeek, add additional options to handle long-running connections
		if ($is_deepseek) {
			// Enable TCP keep-alive to prevent connection drops
			curl_setopt($handle, CURLOPT_TCP_KEEPALIVE, 1);
			curl_setopt($handle, CURLOPT_TCP_KEEPIDLE, 60); // Changed from 100 to 60
			curl_setopt($handle, CURLOPT_TCP_KEEPINTVL, 10); // Changed from 5 to 10
			
			// Use HTTP/1.1 (not HTTP/2) for better compatibility with long connections
			curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			
			// Disable pipelining which can cause issues with long responses
			curl_setopt($handle, CURLOPT_PIPEWAIT, 0);
			
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
		
		// xf_translator_log('cURL options set - CONNECTTIMEOUT: 30, TIMEOUT: ' . $request_timeout . ', LOW_SPEED_TIME: 0' . ($is_deepseek ? ' (DeepSeek: TCP keep-alive enabled)' : ''), 'debug');
	}
	return $handle;
}, PHP_INT_MAX, 3); // Highest possible priority - runs after ALL plugins

}

// Register hooks after WordPress is loaded (but early enough for filters to work)
if (function_exists('add_action')) {
	add_action('plugins_loaded', 'xf_translator_register_hooks', 1);
}

/**
 * Register admin menu directly so it always shows even if core class fails.
 */
function xf_translator_add_admin_menu() {
	$capability = apply_filters('xf_translator_admin_capability', 'manage_options');
	add_menu_page(
		__('Unite.AI Translations', 'xf-translator'),
		__('Unite.AI Translations', 'xf-translator'),
		$capability,
		'xf-translator',
		'xf_translator_render_settings_page',
		'dashicons-translation',
		30
	);
}

/**
 * Render settings page (standalone so menu works even if admin class fails).
 */
function xf_translator_render_settings_page() {
	$admin_file = plugin_dir_path(__FILE__) . 'admin/class-xf-translator-admin.php';
	if (!file_exists($admin_file)) {
		echo '<div class="wrap"><p>' . esc_html__('Plugin files missing.', 'xf-translator') . '</p></div>';
		return;
	}
	if (!class_exists('Settings')) {
		require_once plugin_dir_path(__FILE__) . 'admin/class-settings.php';
	}
	if (!class_exists('Xf_Translator_Admin')) {
		require_once $admin_file;
	}
	$plugin_name = 'xf-translator';
	$version = defined('XF_TRANSLATOR_VERSION') ? XF_TRANSLATOR_VERSION : '1.0.0';
	$plugin_admin = new Xf_Translator_Admin($plugin_name, $version);
	global $api_translator_admin;
	$api_translator_admin = $plugin_admin;
	$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
	if ($current_tab === 'translations') {
		$current_tab = 'general';
	}
	$tabs = array(
		'general' => __('Settings', 'api-translator'),
		'test-translation' => __('Test Translation', 'api-translator'),
		'queue' => __('Translation Queue', 'api-translator'),
		'existing-queue' => __('Existing Post Queue', 'api-translator'),
		'translation-rules' => __('Translation Rules', 'api-translator'),
		'menu-translation' => __('Menu Translation', 'api-translator'),
		'taxonomy-translation' => __('Taxonomy Translation', 'api-translator'),
		'acf-translation' => __('ACF Translation', 'api-translator'),
		'user-meta-translation' => __('User Meta Translation', 'api-translator'),
		'logs' => __('Logs', 'api-translator')
	);
	include plugin_dir_path(__FILE__) . 'admin/partials/xf-translator-admin-display.php';
}

add_action('admin_menu', 'xf_translator_add_admin_menu', 9);

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
	// Only run if WordPress is fully loaded to prevent errors during activation
	// Check if we're in a context where WordPress core functions are available
	if (!function_exists('add_action') || !function_exists('add_filter')) {
		return;
	}
	
	try {
		$plugin = new Xf_Translator();
		$plugin->run();
	} catch (Exception $e) {
		// Log error but don't break the site
		if (function_exists('error_log')) {
			error_log('XF Translator: Failed to initialize plugin: ' . $e->getMessage());
		}
	} catch (Error $e) {
		// Catch PHP 7+ fatal errors
		if (function_exists('error_log')) {
			error_log('XF Translator: Fatal error initializing plugin: ' . $e->getMessage());
		}
	} catch (Throwable $e) {
		// Catch any other throwable (PHP 7+)
		if (function_exists('error_log')) {
			error_log('XF Translator: Throwable error initializing plugin: ' . $e->getMessage());
		}
	}
}

// Run the plugin - WordPress will handle activation separately via register_activation_hook
// The try-catch blocks above will prevent fatal errors during activation
run_xf_translator();
