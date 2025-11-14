<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://xfinitive.co
 * @since      1.0.0
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/public
 * @author     ghazali <shafe_ghazali@xfinitive.co>
 */
class Xf_Translator_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		// Add rewrite rules on init
		add_action('init', array($this, 'add_rewrite_rules'));

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Xf_Translator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Xf_Translator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/xf-translator-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Xf_Translator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Xf_Translator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/xf-translator-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Add rewrite rules for language-prefixed URLs
	 */
	public function add_rewrite_rules() {
		// Get all language prefixes from settings
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-settings.php';
		$settings = new Settings();
		$languages = $settings->get('languages', array());
		
		if (empty($languages)) {
			return;
		}
		
		// Build regex pattern for all language prefixes
		$prefixes = array();
		foreach ($languages as $language) {
			if (!empty($language['prefix'])) {
				$prefixes[] = preg_quote($language['prefix'], '/');
			}
		}
		
		if (empty($prefixes)) {
			return;
		}
		
		$prefix_pattern = implode('|', $prefixes);
		
		// Add query var for language prefix
		add_rewrite_tag('%xf_lang_prefix%', '(' . $prefix_pattern . ')');
		
		// Create pattern that matches slugs but excludes asset file extensions
		// Match any characters except /, but ensure it doesn't end with asset extensions
		// Using a pattern that explicitly excludes common asset extensions
		$slug_pattern = '([^/.]+|([^/]+)(?<!\.css)(?<!\.js)(?<!\.jpg)(?<!\.jpeg)(?<!\.png)(?<!\.gif)(?<!\.svg)(?<!\.ico)(?<!\.woff)(?<!\.woff2)(?<!\.ttf)(?<!\.eot)(?<!\.pdf)(?<!\.zip)(?<!\.map))';
		
		// Simpler approach: match slug that doesn't contain a dot followed by asset extension
		// This is more reliable than negative lookbehind
		$slug_pattern = '([^/]+)(?<!\.css)(?<!\.js)(?<!\.jpg)(?<!\.jpeg)(?<!\.png)(?<!\.gif)(?<!\.svg)(?<!\.ico)(?<!\.woff)(?<!\.woff2)(?<!\.ttf)(?<!\.eot)(?<!\.pdf)(?<!\.zip)(?<!\.map)';
		
		// Even simpler: just match the slug and filter in the query handler
		// This is more reliable
		$slug_pattern = '([^/]+)';
		
		// Add rewrite rules for posts and pages with language prefix
		add_rewrite_rule(
			'^(' . $prefix_pattern . ')/' . $slug_pattern . '/?$',
			'index.php?name=$matches[2]&xf_lang_prefix=$matches[1]',
			'top'
		);
		
		// Also handle pagination
		add_rewrite_rule(
			'^(' . $prefix_pattern . ')/' . $slug_pattern . '/page/([0-9]+)/?$',
			'index.php?name=$matches[2]&paged=$matches[3]&xf_lang_prefix=$matches[1]',
			'top'
		);
	}
	
	/**
	 * Filter query to find translated post by language prefix
	 */
	public function filter_translated_post_query($query) {
		if (is_admin() || !$query->is_main_query()) {
			return;
		}
		
		// Skip for asset requests (CSS, JS, images, etc.) - more thorough check
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		
		// Remove query string for checking
		$request_path = parse_url($request_uri, PHP_URL_PATH);
		$request_path = $request_path ?: $request_uri;
		
		// Check if the requested file actually exists (this handles all asset files)
		// First check with language prefix removed
		$lang_prefix = get_query_var('xf_lang_prefix');
		if ($lang_prefix) {
			// Remove language prefix from path for file check
			$request_path_no_prefix = preg_replace('#^/' . preg_quote($lang_prefix, '#') . '/#', '/', $request_path);
			$file_path = ABSPATH . ltrim($request_path_no_prefix, '/');
			if (file_exists($file_path) && is_file($file_path)) {
				return; // It's a real file, skip processing
			}
		}
		
		// Also check the original path
		$file_path = ABSPATH . ltrim($request_path, '/');
		if (file_exists($file_path) && is_file($file_path)) {
			return; // It's a real file, skip processing
		}
		
		// Check for asset file extensions (with query strings)
		$asset_extensions = array('\.css', '\.js', '\.jpg', '\.jpeg', '\.png', '\.gif', '\.svg', '\.ico', '\.woff', '\.woff2', '\.ttf', '\.eot', '\.pdf', '\.zip', '\.map');
		$asset_pattern = '/(' . implode('|', $asset_extensions) . ')(\?|$)/i';
		if (preg_match($asset_pattern, $request_uri)) {
			return;
		}
		
		// Skip for wp-json, wp-admin, wp-content, wp-includes, wp-cron
		if (preg_match('#/(wp-json|wp-admin|wp-content|wp-includes|wp-cron)/#', $request_uri)) {
			return;
		}
		
		// Skip if it's a feed request
		if (preg_match('#/(feed|rdf|rss|rss2|atom)/?#', $request_uri)) {
			return;
		}
		
		$lang_prefix = get_query_var('xf_lang_prefix');
		if (empty($lang_prefix)) {
			return;
		}
		
		// Get the post by slug
		$post_name = get_query_var('name');
		if (empty($post_name)) {
			return;
		}
		
		// Additional check: skip if post_name looks like an asset file
		$asset_pattern_simple = '/\.(' . implode('|', array('css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'zip', 'map')) . ')$/i';
		if (preg_match($asset_pattern_simple, $post_name)) {
			return;
		}
		
		// Find translated post with this slug and language prefix
		$args = array(
			'name' => $post_name,
			'post_type' => 'any',
			'posts_per_page' => 1,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_xf_translator_language',
					'value' => $lang_prefix,
					'compare' => '='
				),
				array(
					'relation' => 'OR',
					array(
						'key' => '_xf_translator_original_post_id',
						'compare' => 'EXISTS'
					),
					array(
						'key' => '_api_translator_original_post_id',
						'compare' => 'EXISTS'
					)
				)
			)
		);
		
		$translated_posts = get_posts($args);
		if (!empty($translated_posts)) {
			$query->set('p', $translated_posts[0]->ID);
			$query->set('name', ''); // Clear name query to use ID instead
		}
	}
	
	/**
	 * Modify permalink to include language prefix for translated posts
	 * 
	 * @param string $permalink The post's permalink
	 * @param WP_Post|int $post The post object or post ID
	 * @return string Modified permalink with language prefix
	 */
	public function add_language_prefix_to_permalink($permalink, $post) {
		// Skip if this is an asset URL (CSS, JS, images, etc.)
		$asset_extensions = array('.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.pdf', '.zip');
		foreach ($asset_extensions as $ext) {
			if (strpos($permalink, $ext) !== false) {
				return $permalink;
			}
		}
		
		// Skip for wp-json, wp-admin, wp-content, wp-includes
		if (strpos($permalink, '/wp-json/') !== false || 
		    strpos($permalink, '/wp-admin/') !== false || 
		    strpos($permalink, '/wp-content/') !== false || 
		    strpos($permalink, '/wp-includes/') !== false) {
			return $permalink;
		}
		
		// Handle case where $post might be an integer (post ID) instead of object
		if (is_numeric($post)) {
			$post = get_post($post);
		}
		
		// If we still don't have a valid post object, return original permalink
		if (!$post || !is_object($post) || !isset($post->ID)) {
			return $permalink;
		}
		
		// Check if this is a translated post
		$original_post_id = get_post_meta($post->ID, '_xf_translator_original_post_id', true);
		if (!$original_post_id) {
			$original_post_id = get_post_meta($post->ID, '_api_translator_original_post_id', true);
		}
		
		// If not a translated post, return original permalink
		if (!$original_post_id) {
			return $permalink;
		}
		
		// Get language prefix
		$language_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
		if (empty($language_prefix)) {
			return $permalink;
		}
		
		// Parse the permalink URL
		$parsed_url = parse_url($permalink);
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		
		// Add language prefix to the path
		// Remove leading slash if present, add prefix, then add back
		$path = ltrim($path, '/');
		
		// Check if prefix is already in the URL
		if (strpos($path, $language_prefix . '/') === 0) {
			return $permalink; // Already has prefix
		}
		
		// Add language prefix
		$new_path = '/' . $language_prefix . '/' . $path;
		
		// Reconstruct the URL
		$new_permalink = $scheme . $host . $port . $new_path;
		if (isset($parsed_url['query'])) {
			$new_permalink .= '?' . $parsed_url['query'];
		}
		if (isset($parsed_url['fragment'])) {
			$new_permalink .= '#' . $parsed_url['fragment'];
		}
		
		return $new_permalink;
	}

}
