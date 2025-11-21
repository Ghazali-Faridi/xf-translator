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
		
		// Filter menus to show translated versions
		add_filter('wp_nav_menu_args', array($this, 'filter_nav_menu_args'), 10, 1);
		
		// Filter taxonomies to show translated terms
		add_filter('get_terms', array($this, 'filter_get_terms'), 10, 4);
		add_filter('term_link', array($this, 'filter_term_link'), 10, 3);
		add_filter('get_term', array($this, 'filter_get_term'), 10, 2);
		
		// Filter taxonomy archive queries to show translated posts
		add_action('pre_get_posts', array($this, 'filter_taxonomy_archive_query'), 10, 1);

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
		// Register as public query var so it's accessible
		global $wp;
		$wp->add_query_var('xf_lang_prefix');
		
		// Get all public taxonomies for rewrite rules
		$taxonomies = get_taxonomies(array('public' => true, 'publicly_queryable' => true), 'objects');
		
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
		
		// Add rewrite rules for taxonomy archives with language prefix
		// Category archives: /fr/category/category-slug/
		// Tag archives: /fr/tag/tag-slug/
		// Custom taxonomy: /fr/taxonomy/term-slug/
		foreach ($taxonomies as $taxonomy) {
			if ($taxonomy->public && $taxonomy->publicly_queryable) {
				$taxonomy_slug = $taxonomy->rewrite['slug'] ?? $taxonomy->name;
				
				// Add rewrite rule for taxonomy archive
				add_rewrite_rule(
					'^(' . $prefix_pattern . ')/' . $taxonomy_slug . '/([^/]+)/?$',
					'index.php?' . $taxonomy->query_var . '=$matches[2]&xf_lang_prefix=$matches[1]',
					'top'
				);
				
				// Add rewrite rule for paginated taxonomy archive
				add_rewrite_rule(
					'^(' . $prefix_pattern . ')/' . $taxonomy_slug . '/([^/]+)/page/([0-9]+)/?$',
					'index.php?' . $taxonomy->query_var . '=$matches[2]&paged=$matches[3]&xf_lang_prefix=$matches[1]',
					'top'
				);
			}
		}
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
		
		// Find translated post with this slug and language prefix using direct SQL
		// This handles cases where WordPress added suffixes like -2, -3, -4, etc.
		global $wpdb;
		
		// First try exact match
		$translated_post_id = $wpdb->get_var($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
				AND pm1.meta_key = '_xf_translator_language' 
				AND pm1.meta_value = %s
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
				AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
			WHERE p.post_name = %s
			AND p.post_status = 'publish'
			AND p.post_type != 'revision'
			LIMIT 1",
			$lang_prefix,
			$post_name
		));
		
		// If not found by exact match, try to find by slug that starts with the post_name
		// This handles cases where WordPress added a suffix like -2, -3, -4, etc.
		if (!$translated_post_id) {
			$translated_post_id = $wpdb->get_var($wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
					AND pm1.meta_key = '_xf_translator_language' 
					AND pm1.meta_value = %s
				INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
					AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
				WHERE p.post_name LIKE %s
				AND p.post_status = 'publish'
				AND p.post_type != 'revision'
				ORDER BY 
					CASE 
						WHEN p.post_name = %s THEN 1
						ELSE 2
					END,
					p.post_name ASC
				LIMIT 1",
				$lang_prefix,
				$wpdb->esc_like($post_name) . '%',
				$post_name
			));
		}
		
		if ($translated_post_id) {
			$query->set('p', $translated_post_id);
			$query->set('name', ''); // Clear name query to use ID instead
			$query->is_404 = false; // Prevent 404
			$query->is_singular = true; // Mark as singular
			$query->is_single = true; // Mark as single post
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
	
	/**
	 * Filter nav menu arguments to show translated menu
	 *
	 * @param array $args Nav menu arguments
	 * @return array Modified arguments
	 */
	public function filter_nav_menu_args($args) {
		// Only filter on frontend
		if (is_admin()) {
			return $args;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $args;
		}
		
		// Get the menu ID from args
		if (!isset($args['menu']) || empty($args['menu'])) {
			return $args;
		}
		
		$menu = $args['menu'];
		
		// If menu is an ID, get the menu object
		if (is_numeric($menu)) {
			$menu_obj = wp_get_nav_menu_object($menu);
			if (!$menu_obj) {
				return $args;
			}
			$menu_id = $menu_obj->term_id;
		} elseif (is_object($menu)) {
			$menu_id = $menu->term_id;
		} else {
			// Menu is a slug or name
			$menu_obj = wp_get_nav_menu_object($menu);
			if (!$menu_obj) {
				return $args;
			}
			$menu_id = $menu_obj->term_id;
		}
		
		// Get translated menu ID
		$translated_menu_id = get_term_meta($menu_id, '_xf_translator_menu_' . $lang_prefix, true);
		
		if ($translated_menu_id) {
			$translated_menu = wp_get_nav_menu_object($translated_menu_id);
			if ($translated_menu) {
				// Replace menu with translated version
				$args['menu'] = $translated_menu_id;
			}
		}
		
		return $args;
	}
	
	/**
	 * Filter get_terms to show translated terms
	 *
	 * @param array $terms Array of term objects
	 * @param array $taxonomies Array of taxonomies
	 * @param array $args Query arguments
	 * @param WP_Term_Query $term_query Term query object
	 * @return array Filtered terms
	 */
	public function filter_get_terms($terms, $taxonomies, $args, $term_query) {
		// Only filter on frontend
		if (is_admin()) {
			return $terms;
		}
		
		// Get current language prefix
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $terms;
		}
		
		// Replace terms with their translations
		$translated_terms = array();
		foreach ($terms as $term) {
			if (!is_object($term)) {
				continue;
			}
			
			$translated_term_id = get_term_meta($term->term_id, '_xf_translator_term_' . $lang_prefix, true);
			
			if ($translated_term_id) {
				$translated_term = get_term($translated_term_id);
				if ($translated_term && !is_wp_error($translated_term)) {
					$translated_terms[] = $translated_term;
				} else {
					$translated_terms[] = $term; // Fallback to original
				}
			} else {
				$translated_terms[] = $term; // No translation available
			}
		}
		
		return $translated_terms;
	}
	
	/**
	 * Filter term link to add language prefix
	 *
	 * @param string $termlink Term link URL
	 * @param WP_Term $term Term object
	 * @param string $taxonomy Taxonomy name
	 * @return string Modified term link
	 */
	public function filter_term_link($termlink, $term, $taxonomy) {
		// Only filter on frontend
		if (is_admin()) {
			return $termlink;
		}
		
		// Get current language prefix
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $termlink;
		}
		
		// Check if this is a translated term
		$original_term_id = get_term_meta($term->term_id, '_xf_translator_original_term_id', true);
		$term_language = get_term_meta($term->term_id, '_xf_translator_language', true);
		
		// If this is a translated term for the current language, add prefix to URL
		if ($term_language === $lang_prefix) {
			$parsed_url = parse_url($termlink);
			$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
			$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
			$port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
			$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
			
			// Check if prefix is already in the URL
			if (strpos($path, '/' . $lang_prefix . '/') === false) {
				$path = ltrim($path, '/');
				$new_path = '/' . $lang_prefix . '/' . $path;
				
				$new_termlink = $scheme . $host . $port . $new_path;
				if (isset($parsed_url['query'])) {
					$new_termlink .= '?' . $parsed_url['query'];
				}
				if (isset($parsed_url['fragment'])) {
					$new_termlink .= '#' . $parsed_url['fragment'];
				}
				
				return $new_termlink;
			}
		}
		
		return $termlink;
	}
	
	/**
	 * Filter get_term to return translated term when appropriate
	 *
	 * @param WP_Term|array $term Term object or array
	 * @param string $taxonomy Taxonomy name
	 * @return WP_Term|array Filtered term
	 */
	public function filter_get_term($term, $taxonomy) {
		// Only filter on frontend
		if (is_admin()) {
			return $term;
		}
		
		if (!is_object($term)) {
			return $term;
		}
		
		// Get current language prefix
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $term;
		}
		
		// Check if this term has a translation
		$translated_term_id = get_term_meta($term->term_id, '_xf_translator_term_' . $lang_prefix, true);
		
		if ($translated_term_id) {
			$translated_term = get_term($translated_term_id);
			if ($translated_term && !is_wp_error($translated_term)) {
				return $translated_term;
			}
		}
		
		return $term;
	}
	
	/**
	 * Filter taxonomy archive queries to show translated posts
	 *
	 * @param WP_Query $query Query object
	 */
	public function filter_taxonomy_archive_query($query) {
		// Only filter on frontend, main query, and taxonomy archives
		if (is_admin() || !$query->is_main_query() || !$query->is_tax()) {
			return;
		}
		
		// Get current language prefix
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, use default (no translation)
		if (empty($lang_prefix)) {
			return;
		}
		
		// Get the queried term
		$queried_object = $query->get_queried_object();
		if (!$queried_object || !isset($queried_object->term_id)) {
			return;
		}
		
		// Check if this is a translated term
		$term_language = get_term_meta($queried_object->term_id, '_xf_translator_language', true);
		
		// If this is a translated term, filter posts to show only translated posts
		if ($term_language === $lang_prefix) {
			$meta_query = $query->get('meta_query');
			if (!is_array($meta_query)) {
				$meta_query = array();
			}
			
			// Add meta query to only show translated posts for this language
			$meta_query[] = array(
				'key' => '_xf_translator_language',
				'value' => $lang_prefix,
				'compare' => '='
			);
			
			$query->set('meta_query', $meta_query);
		}
	}

}
