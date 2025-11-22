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
	 * Settings instance
	 *
	 * @var Settings
	 */
	private $settings;

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
		
		// Load Settings class
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-settings.php';
		$this->settings = new Settings();
		
		// Add rewrite rules on init
		add_action('init', array($this, 'add_rewrite_rules'));
		
		// Filter menus to show translated versions
		add_filter('wp_nav_menu_args', array($this, 'filter_nav_menu_args'), 10, 1);
		
		// Filter taxonomies to show translated terms
		add_filter('get_terms', array($this, 'filter_get_terms'), 10, 4);
		add_filter('get_object_terms', array($this, 'filter_get_object_terms'), 10, 4);
		add_filter('get_the_categories', array($this, 'filter_get_the_categories'), 10, 2);
		add_filter('term_link', array($this, 'filter_term_link'), 10, 3);
		add_filter('get_term', array($this, 'filter_get_term'), 10, 2);
		
		// Filter taxonomy archive queries to show translated posts
		add_action('pre_get_posts', array($this, 'filter_taxonomy_archive_query'), 10, 1);

	}

	/**
	 * Output canonical and hreflang tags for translated posts
	 */
	public function output_seo_tags() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return;
		}

		$original_post_id = $this->get_original_post_id( $post_id );
		$is_translated    = $original_post_id !== $post_id;

		$languages = $this->settings->get( 'languages', array() );

		// If there are no configured languages and this post isn't a translation, skip.
		if ( empty( $languages ) && ! $is_translated ) {
			return;
		}

		$language_versions = array();

		$original_permalink = get_permalink( $original_post_id );
		if ( ! $original_permalink ) {
			return;
		}

		$original_lang_code = $this->get_language_code_for_post( $original_post_id );
		if ( ! $original_lang_code ) {
			$original_lang_code = $this->get_site_language_code();
		}

		$language_versions[ $original_lang_code ] = $original_permalink;
		$language_versions['x-default']           = $original_permalink;

		// Include configured translations.
		foreach ( $languages as $language ) {
			if ( empty( $language['prefix'] ) ) {
				continue;
			}

			$prefix             = strtolower( $language['prefix'] );
			$translated_post_id = $this->get_translated_post_id( $original_post_id, $prefix );
			if ( ! $translated_post_id ) {
				continue;
			}

			$permalink = get_permalink( $translated_post_id );
			if ( ! $permalink ) {
				continue;
			}

			$hreflang = ! empty( $language['hreflang'] ) ? strtolower( $language['hreflang'] ) : $prefix;
			$language_versions[ $hreflang ] = $permalink;
		}

		// Ensure the current post language is present.
		$current_lang_code = $this->get_language_code_for_post( $post_id );
		if ( $current_lang_code && empty( $language_versions[ $current_lang_code ] ) ) {
			$current_permalink                       = get_permalink( $post_id );
			$language_versions[ $current_lang_code ] = $current_permalink;
		}

		// If we only have one version, nothing to link.
		if ( count( $language_versions ) < 2 ) {
			return;
		}

		// Output canonical tag (current post URL).
		$canonical = get_permalink( $post_id );
		if ( $canonical ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $canonical ) );
		}

		// Output hreflang tags.
		foreach ( $language_versions as $code => $url ) {
			if ( empty( $code ) || empty( $url ) ) {
				continue;
			}

			printf(
				"<link rel=\"alternate\" href=\"%s\" hreflang=\"%s\" />\n",
				esc_url( $url ),
				esc_attr( $code )
			);
		}
	}

	/**
	 * Render floating language switcher
	 */
	public function render_language_switcher() {
		if ( is_admin() ) {
			return;
		}

		$languages = $this->settings->get( 'languages', array() );
		if ( empty( $languages ) ) {
			return;
		}

		// Get current post/page ID - try multiple methods
		$post_id = 0;
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
		} elseif ( is_home() || is_front_page() ) {
			// For home page, try to get the page ID
			$post_id = get_option( 'page_on_front' ) ?: get_option( 'page_for_posts' );
		}

		$items = array();
		$current_lang_prefix = get_query_var('xf_lang_prefix');

		// Always add English option first
		$home_url = home_url( '/' );
		$items[] = array(
			'label'  => __( 'English', 'xf-translator' ),
			'url'    => $home_url,
			'active' => empty( $current_lang_prefix ),
		);

		// If we have a post ID, get translations
		if ( $post_id ) {
			$original_post_id = $this->get_original_post_id( $post_id );
			
			// Get current language from post meta if not in URL
			if ( empty( $current_lang_prefix ) ) {
				$current_lang_prefix = get_post_meta( $post_id, '_xf_translator_language', true );
			}
			
			// Update English URL to point to original post if we have one
			if ( $original_post_id && $original_post_id !== $post_id ) {
				$original_url = get_permalink( $original_post_id );
				if ( $original_url ) {
					$items[0]['url'] = $original_url;
				}
			}

			// Add translated versions
			foreach ( $languages as $language ) {
				if ( empty( $language['prefix'] ) ) {
					continue;
				}

				$translated_id = $this->get_translated_post_id( $original_post_id, strtolower( $language['prefix'] ) );
				if ( ! $translated_id ) {
					// No translation exists, link to home page with language prefix
					$items[] = array(
						'label'  => $language['name'],
						'url'    => home_url( '/' . $language['prefix'] . '/' ),
						'active' => strtolower( $language['prefix'] ) === strtolower( $current_lang_prefix ),
					);
					continue;
				}

				$url = get_permalink( $translated_id );
				if ( ! $url ) {
					continue;
				}

				$items[] = array(
					'label'  => $language['name'],
					'url'    => $url,
					'active' => strtolower( $language['prefix'] ) === strtolower( $current_lang_prefix ),
				);
			}
		} else {
			// For non-singular pages, show language links to home
			foreach ( $languages as $language ) {
				if ( empty( $language['prefix'] ) ) {
					continue;
				}
				$items[] = array(
					'label'  => $language['name'],
					'url'    => home_url( '/' . $language['prefix'] . '/' ),
					'active' => strtolower( $language['prefix'] ) === strtolower( $current_lang_prefix ),
				);
			}
		}

		// Show switcher if we have at least one language option
		if ( empty( $items ) ) {
			return;
		}

		static $assets_printed = false;
		if ( ! $assets_printed ) {
			$assets_printed = true;
			?>
			<style>
				.xf-lang-switcher {
					position: fixed;
					left: 24px;
					bottom: 24px;
					z-index: 9999;
					font-family: inherit;
				}
				.xf-lang-switcher button {
					cursor: pointer;
				}
				.xf-lang-toggle {
					background: #1d2327;
					color: #fff;
					border: none;
					padding: 10px 16px;
					border-radius: 999px;
					font-size: 14px;
					box-shadow: 0 8px 20px rgba(0,0,0,0.2);
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.xf-lang-toggle:focus {
					outline: 2px solid #2271b1;
					outline-offset: 2px;
				}
				.xf-lang-menu {
					position: absolute;
					left: 0;
					bottom: 52px;
					background: #fff;
					border-radius: 12px;
					box-shadow: 0 12px 35px rgba(0,0,0,0.15);
					padding: 8px 0;
					min-width: 200px;
					opacity: 0;
					pointer-events: none;
					transform: translateY(10px);
					transition: opacity 0.2s ease, transform 0.2s ease;
				}
				.xf-lang-switcher.is-open .xf-lang-menu {
					opacity: 1;
					pointer-events: auto;
					transform: translateY(0);
				}
				.xf-lang-item {
					display: block;
					padding: 10px 16px;
					color: #1d2327;
					text-decoration: none;
					font-size: 14px;
				}
				.xf-lang-item:hover {
					background: #f0f0f1;
				}
				.xf-lang-item.is-active {
					font-weight: 600;
					background: rgba(34,113,177,0.08);
				}
				@media (max-width: 782px) {
					.xf-lang-switcher {
						left: 16px;
						bottom: 16px;
					}
					.xf-lang-toggle {
						font-size: 13px;
						padding: 8px 14px;
					}
					.xf-lang-menu {
						min-width: 165px;
					}
				}
			</style>
			<script>
				document.addEventListener('DOMContentLoaded', function () {
					document.querySelectorAll('.xf-lang-switcher').forEach(function (switcher) {
						var toggle = switcher.querySelector('.xf-lang-toggle');
						if (!toggle) {
							return;
						}
						toggle.addEventListener('click', function (event) {
							event.preventDefault();
							switcher.classList.toggle('is-open');
						});
						document.addEventListener('click', function (e) {
							if (!switcher.contains(e.target)) {
								switcher.classList.remove('is-open');
							}
						});
					});
				});
			</script>
			<?php
		}

		$current_label = '';
		foreach ( $items as $item ) {
			if ( $item['active'] ) {
				$current_label = $item['label'];
				break;
			}
		}
		if ( ! $current_label && ! empty( $items ) ) {
			$current_label = $items[0]['label'];
		}
		?>
		<div class="xf-lang-switcher" aria-label="<?php esc_attr_e( 'Language switcher', 'xf-translator' ); ?>">
			<button class="xf-lang-toggle" type="button">
				<?php echo esc_html( sprintf( __( 'Language: %s', 'xf-translator' ), $current_label ) ); ?>
				<svg width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<div class="xf-lang-menu" role="menu">
				<?php foreach ( $items as $item ) : ?>
					<a class="xf-lang-item <?php echo $item['active'] ? 'is-active' : ''; ?>"
					   href="<?php echo esc_url( $item['url'] ); ?>"
					   role="menuitem">
						<?php echo esc_html( $item['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get language code for a post
	 *
	 * @param int $post_id
	 * @return string|null
	 */
	private function get_language_code_for_post( $post_id ) {
		$lang = get_post_meta( $post_id, '_xf_translator_language', true );
		if ( ! $lang ) {
			return null;
		}

		return strtolower( $lang );
	}

	/**
	 * Determine site default language code from locale
	 *
	 * @return string
	 */
	private function get_site_language_code() {
		$locale = get_locale();
		if ( ! $locale ) {
			return 'x-default';
		}

		$parts = explode( '_', $locale );
		return strtolower( $parts[0] );
	}

	/**
	 * Get original post ID for a translated post
	 *
	 * @param int $post_id
	 * @return int
	 */
	private function get_original_post_id( $post_id ) {
		$original = get_post_meta( $post_id, '_xf_translator_original_post_id', true );
		if ( ! $original ) {
			$original = get_post_meta( $post_id, '_api_translator_original_post_id', true );
		}

		if ( $original ) {
			return (int) $original;
		}

		return (int) $post_id;
	}

	/**
	 * Get translated post ID for a specific language
	 *
	 * @param int    $original_post_id
	 * @param string $language_prefix
	 * @return int|false
	 */
	private function get_translated_post_id( $original_post_id, $language_prefix ) {
		$meta_key = '_xf_translator_translated_post_' . $language_prefix;
		$post_id  = get_post_meta( $original_post_id, $meta_key, true );

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post && $post->post_status === 'publish' ) {
				return (int) $post_id;
			}
		}

		$args = array(
			'post_type'      => get_post_type( $original_post_id ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_xf_translator_language',
					'value' => $language_prefix,
				),
				array(
					'key'   => '_xf_translator_original_post_id',
					'value' => $original_post_id,
				),
			),
		);

		$posts = get_posts( $args );
		if ( ! empty( $posts ) ) {
			return (int) $posts[0];
		}

		// Fallback for legacy meta key.
		$args['meta_query'][1]['key'] = '_api_translator_original_post_id';
		$posts                        = get_posts( $args );
		if ( ! empty( $posts ) ) {
			return (int) $posts[0];
		}

		return false;
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
		
		// Add rewrite rule for home page with language prefix (e.g., /fr/)
		add_rewrite_rule(
			'^(' . $prefix_pattern . ')/?$',
			'index.php?xf_lang_prefix=$matches[1]',
			'top'
		);
		
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
		
		// If no post name, this is the home/blog archive page with language prefix
		// Filter the query to show only translated posts for this language
		if (empty($post_name)) {
			// Clear query vars that might interfere
			$query->set('name', '');
			$query->set('pagename', '');
			$query->set('page_id', '');
			$query->set('p', '');
			
			// Set query to show home/blog archive
			$query->is_home = true;
			$query->is_404 = false;
			$query->is_singular = false;
			$query->is_single = false;
			$query->is_page = false;
			$query->is_archive = false;
			
			// Filter posts to show only translated posts for this language
			$this->filter_home_query_by_language($query, $lang_prefix);
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
		
		// First try exact match - check both posts and pages
		$translated_post_id = $wpdb->get_var($wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
				AND pm1.meta_key = '_xf_translator_language' 
				AND pm1.meta_value = %s
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
				AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
			WHERE p.post_name = %s
			AND p.post_status = 'publish'
			AND p.post_type IN ('post', 'page')
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
				AND p.post_type IN ('post', 'page')
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
			$translated_post = get_post($translated_post_id);
			$query->set('p', $translated_post_id);
			$query->set('name', ''); // Clear name query to use ID instead
			$query->is_404 = false; // Prevent 404
			$query->is_singular = true; // Mark as singular
			
			// Set correct query flags based on post type
			if ($translated_post && $translated_post->post_type === 'page') {
				$query->is_page = true;
				$query->is_single = false;
			} else {
				$query->is_single = true;
				$query->is_page = false;
			}
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
		
		// If path is empty or just '/', try to get the post slug directly
		if (empty($path) || $path === '/') {
			$post_slug = $post->post_name;
			if (!empty($post_slug)) {
				// Build path from post slug
				$path = '/' . $post_slug . '/';
			} else {
				// If still no slug, this might be home page - just use '/'
				$path = '/';
			}
		}
		
		// Add language prefix to the path
		// Remove leading slash if present, add prefix, then add back
		$path = ltrim($path, '/');
		
		// Check if prefix is already in the URL
		if (strpos($path, $language_prefix . '/') === 0) {
			return $permalink; // Already has prefix
		}
		
		// Add language prefix
		// If path is empty (home page), just use the prefix
		if (empty($path)) {
			$new_path = '/' . $language_prefix . '/';
		} else {
			$new_path = '/' . $language_prefix . '/' . $path;
		}
		
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
		
		// If no language prefix in URL, check if we're on a translated post/page
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
		
		// Get the menu ID from args - handle different ways menus can be specified
		$menu_id = null;
		
		// Check if menu is specified by ID
		if (isset($args['menu']) && !empty($args['menu'])) {
			$menu = $args['menu'];
			
			// If menu is an ID, get the menu object
			if (is_numeric($menu)) {
				$menu_obj = wp_get_nav_menu_object($menu);
				if ($menu_obj) {
					$menu_id = $menu_obj->term_id;
				}
			} elseif (is_object($menu)) {
				$menu_id = $menu->term_id;
			} else {
				// Menu is a slug or name
				$menu_obj = wp_get_nav_menu_object($menu);
				if ($menu_obj) {
					$menu_id = $menu_obj->term_id;
				}
			}
		}
		
		// If no menu ID from args, try to get from theme_location
		if (!$menu_id && isset($args['theme_location'])) {
			$locations = get_nav_menu_locations();
			if (isset($locations[$args['theme_location']])) {
				$menu_id = $locations[$args['theme_location']];
			}
		}
		
		// If still no menu ID, try to get from menu ID directly in args
		if (!$menu_id && isset($args['menu_id'])) {
			$menu_obj = wp_get_nav_menu_object($args['menu_id']);
			if ($menu_obj) {
				$menu_id = $menu_obj->term_id;
			}
		}
		
		if (!$menu_id) {
			return $args;
		}
		
		// Check if this menu is already a translated menu (has original_menu_id)
		// If so, we don't need to translate it again
		$original_menu_id = get_term_meta($menu_id, '_xf_translator_original_menu_id', true);
		if ($original_menu_id) {
			// This is already a translated menu, use it as-is
			return $args;
		}
		
		// Get translated menu ID
		$translated_menu_id = get_term_meta($menu_id, '_xf_translator_menu_' . $lang_prefix, true);
		
		if ($translated_menu_id) {
			$translated_menu = wp_get_nav_menu_object($translated_menu_id);
			if ($translated_menu) {
				// Replace menu with translated version
				$args['menu'] = $translated_menu_id;
				// Also update menu_id if it exists
				if (isset($args['menu_id'])) {
					$args['menu_id'] = 'menu-' . $translated_menu_id;
				}
			}
		}
		
		return $args;
	}
	
	/**
	 * Get current language prefix from URL or post meta
	 *
	 * @return string Language prefix or empty string
	 */
	private function get_current_language_prefix() {
		// First, try to get from query var (URL)
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// Also check from current URL path
		if (empty($lang_prefix)) {
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			if (preg_match('#^/([a-z]{2})/#i', $request_uri, $matches)) {
				$possible_prefix = strtolower($matches[1]);
				// Verify it's a valid language prefix
				$languages = $this->settings->get('languages', array());
				foreach ($languages as $language) {
					if (strtolower($language['prefix']) === $possible_prefix) {
						$lang_prefix = $possible_prefix;
						break;
					}
				}
			}
		}
		
		return $lang_prefix ?: '';
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
	 * Filter get_object_terms to show translated terms
	 * This catches terms retrieved via wp_get_object_terms() which is used by get_the_terms()
	 *
	 * @param array|WP_Error $terms Array of term objects or WP_Error
	 * @param array $object_ids Object IDs
	 * @param array|string $taxonomies Taxonomy names
	 * @param array $args Query arguments
	 * @return array|WP_Error Filtered terms
	 */
	public function filter_get_object_terms($terms, $object_ids, $taxonomies, $args) {
		// Only filter on frontend
		if (is_admin()) {
			return $terms;
		}
		
		// If it's an error, return as-is
		if (is_wp_error($terms)) {
			return $terms;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $terms;
		}
		
		// Replace terms with their translations
		$translated_terms = array();
		foreach ($terms as $term) {
			if (!is_object($term)) {
				$translated_terms[] = $term;
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
	 * Filter get_the_categories to show translated categories
	 * This catches categories retrieved via get_the_category()
	 *
	 * @param array $categories Array of category term objects
	 * @param int|false $post_id Post ID
	 * @return array Filtered categories
	 */
	public function filter_get_the_categories($categories, $post_id) {
		// Only filter on frontend
		if (is_admin()) {
			return $categories;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $categories;
		}
		
		// Replace categories with their translations
		$translated_categories = array();
		foreach ($categories as $category) {
			if (!is_object($category)) {
				$translated_categories[] = $category;
				continue;
			}
			
			$translated_term_id = get_term_meta($category->term_id, '_xf_translator_term_' . $lang_prefix, true);
			
			if ($translated_term_id) {
				$translated_term = get_term($translated_term_id);
				if ($translated_term && !is_wp_error($translated_term)) {
					$translated_categories[] = $translated_term;
				} else {
					$translated_categories[] = $category; // Fallback to original
				}
			} else {
				$translated_categories[] = $category; // No translation available
			}
		}
		
		return $translated_categories;
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
		$lang_prefix = $this->get_current_language_prefix();
		
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
	
	/**
	 * Filter nav menu object to return translated menu
	 *
	 * @param WP_Term|false $menu_obj Menu object or false
	 * @param int|string $menu_id Menu ID or slug
	 * @return WP_Term|false Translated menu object or original
	 */
	public function filter_nav_menu_object($menu_obj, $menu_id) {
		// Only filter on frontend
		if (is_admin()) {
			return $menu_obj;
		}
		
		if (!$menu_obj) {
			return $menu_obj;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post/page
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $menu_obj;
		}
		
		// Check if this menu is already a translated menu
		$original_menu_id = get_term_meta($menu_obj->term_id, '_xf_translator_original_menu_id', true);
		if ($original_menu_id) {
			// This is already a translated menu, use it as-is
			return $menu_obj;
		}
		
		// Get translated menu ID
		$translated_menu_id = get_term_meta($menu_obj->term_id, '_xf_translator_menu_' . $lang_prefix, true);
		
		if ($translated_menu_id) {
			$translated_menu = wp_get_nav_menu_object($translated_menu_id);
			if ($translated_menu) {
				return $translated_menu;
			}
		}
		
		return $menu_obj;
	}
	
	/**
	 * Filter nav menus list (for theme location assignments)
	 *
	 * @param array $menus Array of menu objects
	 * @param array $args Arguments
	 * @return array Filtered menus
	 */
	public function filter_nav_menus($menus, $args) {
		// Only filter on frontend
		if (is_admin()) {
			return $menus;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post/page
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $menus;
		}
		
		// Filter menus to show translated versions
		$filtered_menus = array();
		foreach ($menus as $menu) {
			// Check if this menu is already a translated menu
			$original_menu_id = get_term_meta($menu->term_id, '_xf_translator_original_menu_id', true);
			if ($original_menu_id) {
				// This is already a translated menu, include it
				$filtered_menus[] = $menu;
				continue;
			}
			
			// Get translated menu ID
			$translated_menu_id = get_term_meta($menu->term_id, '_xf_translator_menu_' . $lang_prefix, true);
			if ($translated_menu_id) {
				$translated_menu = wp_get_nav_menu_object($translated_menu_id);
				if ($translated_menu) {
					$filtered_menus[] = $translated_menu;
				} else {
					$filtered_menus[] = $menu; // Fallback to original
				}
			} else {
				$filtered_menus[] = $menu; // No translation, use original
			}
		}
		
		return $filtered_menus;
	}
	
	/**
	 * Filter nav menu locations to return translated menu IDs
	 *
	 * @param array $locations Menu location assignments
	 * @return array Filtered locations with translated menu IDs
	 */
	public function filter_nav_menu_locations($locations) {
		// Only filter on frontend
		if (is_admin()) {
			return $locations;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post/page
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If still no language prefix, use default (no translation)
		if (empty($lang_prefix)) {
			return $locations;
		}
		
		// Filter each menu location to use translated menu if available
		$filtered_locations = array();
		foreach ($locations as $location => $menu_id) {
			if (empty($menu_id)) {
				$filtered_locations[$location] = $menu_id;
				continue;
			}
			
			// Check if this menu is already a translated menu
			$original_menu_id = get_term_meta($menu_id, '_xf_translator_original_menu_id', true);
			if ($original_menu_id) {
				// This is already a translated menu, use it as-is
				$filtered_locations[$location] = $menu_id;
				continue;
			}
			
			// Get translated menu ID
			$translated_menu_id = get_term_meta($menu_id, '_xf_translator_menu_' . $lang_prefix, true);
			if ($translated_menu_id) {
				$translated_menu = wp_get_nav_menu_object($translated_menu_id);
				if ($translated_menu) {
					$filtered_locations[$location] = $translated_menu_id;
				} else {
					$filtered_locations[$location] = $menu_id; // Fallback to original
				}
			} else {
				$filtered_locations[$location] = $menu_id; // No translation, use original
			}
		}
		
		return $filtered_locations;
	}
	
	/**
	 * Filter home page query to show only translated posts for a specific language
	 *
	 * @param WP_Query $query Query object
	 * @param string $lang_prefix Language prefix
	 */
	private function filter_home_query_by_language($query, $lang_prefix) {
		if (empty($lang_prefix)) {
			return;
		}
		
		// Get all post IDs that have translations for this language
		global $wpdb;
		
		$translated_post_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT p.ID 
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
				AND pm1.meta_key = '_xf_translator_language' 
				AND pm1.meta_value = %s
			INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
				AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
			WHERE p.post_status = 'publish'
			AND p.post_type != 'revision'",
			$lang_prefix
		));
		
		if (empty($translated_post_ids)) {
			// No translated posts found, set to show nothing (or show empty result)
			$query->set('post__in', array(0)); // This will return no posts
			return;
		}
		
		// Filter query to only show translated posts
		$query->set('post__in', $translated_post_ids);
		$query->set('orderby', 'post__in'); // Maintain order
	}
	
	/**
	 * Filter menu item URLs to add language prefix
	 *
	 * @param object $menu_item Menu item object
	 * @return object Modified menu item
	 */
	public function filter_nav_menu_item_url($menu_item) {
		// Only filter on frontend
		if (is_admin()) {
			return $menu_item;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If no language prefix in URL, check if we're on a translated post/page
		if (empty($lang_prefix)) {
			global $post;
			if ($post) {
				$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
			}
		}
		
		// If no language prefix, return menu item as-is (English/default)
		if (empty($lang_prefix)) {
			return $menu_item;
		}
		
		// Skip if URL is empty or external
		if (empty($menu_item->url)) {
			return $menu_item;
		}
		
		// Skip external URLs (absolute URLs that aren't from this site)
		$home_url = home_url();
		$parsed_menu_url = parse_url($menu_item->url);
		$parsed_home_url = parse_url($home_url);
		
		// If menu URL has a different host, it's external - skip it
		if (isset($parsed_menu_url['host']) && isset($parsed_home_url['host']) && 
		    $parsed_menu_url['host'] !== $parsed_home_url['host']) {
			return $menu_item;
		}
		
		// Skip if URL already has language prefix
		$menu_path = isset($parsed_menu_url['path']) ? $parsed_menu_url['path'] : '';
		if (strpos($menu_path, '/' . $lang_prefix . '/') !== false) {
			return $menu_item;
		}
		
		// Handle different menu item types
		if ($menu_item->type === 'post_type' || $menu_item->type === 'post_type_archive') {
			// For post/page menu items, the URL should already be filtered by permalink filters
			// But if it's not, we'll add the prefix
			$object_id = $menu_item->object_id;
			if ($object_id) {
				// Check if this is a translated post
				$post_lang = get_post_meta($object_id, '_xf_translator_language', true);
				if ($post_lang === $lang_prefix) {
					// This is already a translated post, URL should be correct
					// But let's ensure it has the prefix
					$translated_url = get_permalink($object_id);
					if ($translated_url && strpos($translated_url, '/' . $lang_prefix . '/') !== false) {
						$menu_item->url = $translated_url;
					}
				} else if (empty($post_lang)) {
					// This is an original post, find its translation
					$translated_id = $this->get_translated_post_id($object_id, $lang_prefix);
					if ($translated_id) {
						$translated_url = get_permalink($translated_id);
						if ($translated_url) {
							$menu_item->url = $translated_url;
						}
					} else {
						// No translation exists, add prefix to original URL
						$menu_item->url = $this->add_language_prefix_to_url($menu_item->url, $lang_prefix);
					}
				}
			}
		} else {
			// For custom links or other types, add language prefix to URL
			$menu_item->url = $this->add_language_prefix_to_url($menu_item->url, $lang_prefix);
		}
		
		return $menu_item;
	}
	
	/**
	 * Add language prefix to a URL
	 *
	 * @param string $url Original URL
	 * @param string $lang_prefix Language prefix
	 * @return string URL with language prefix
	 */
	private function add_language_prefix_to_url($url, $lang_prefix) {
		if (empty($url) || empty($lang_prefix)) {
			return $url;
		}
		
		$parsed_url = parse_url($url);
		$home_url = home_url();
		$parsed_home = parse_url($home_url);
		
		// Skip external URLs
		if (isset($parsed_url['host']) && isset($parsed_home['host']) && 
		    $parsed_url['host'] !== $parsed_home['host']) {
			return $url;
		}
		
		// Get the path
		$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		
		// Skip if already has language prefix
		if (strpos($path, '/' . $lang_prefix . '/') !== false) {
			return $url;
		}
		
		// Remove home URL from path if present
		$home_path = isset($parsed_home['path']) ? $parsed_home['path'] : '';
		if ($home_path && strpos($path, $home_path) === 0) {
			$path = substr($path, strlen($home_path));
		}
		
		// Remove leading slash
		$path = ltrim($path, '/');
		
		// Add language prefix
		if (empty($path)) {
			$new_path = '/' . $lang_prefix . '/';
		} else {
			$new_path = '/' . $lang_prefix . '/' . $path;
		}
		
		// Reconstruct URL
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		
		$new_url = $scheme . $host . $port . $new_path;
		
		if (isset($parsed_url['query'])) {
			$new_url .= '?' . $parsed_url['query'];
		}
		if (isset($parsed_url['fragment'])) {
			$new_url .= '#' . $parsed_url['fragment'];
		}
		
		return $new_url;
	}
	
	/**
	 * Filter all queries to show only content for the current language
	 * - On English (no prefix): Show only original posts, exclude translated posts
	 * - On language-prefixed URLs: Show only translated posts for that language
	 *
	 * @param WP_Query $query Query object
	 */
	public function filter_content_by_language($query) {
		// Only filter main query on frontend
		if (is_admin() || !$query->is_main_query()) {
			return;
		}
		
		// Skip for singular posts/pages (handled by filter_translated_post_query)
		if ($query->is_singular) {
			return;
		}
		
		// Skip if post__in is already set (e.g., by filter_home_query_by_language)
		if ($query->get('post__in')) {
			return;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		global $wpdb;
		
		if (empty($lang_prefix)) {
			// On English/default: Exclude all translated posts
			// Get all translated post IDs to exclude
			$translated_post_ids = $wpdb->get_col(
				"SELECT DISTINCT p.ID 
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
					AND (pm.meta_key = '_xf_translator_original_post_id' OR pm.meta_key = '_api_translator_original_post_id')
				WHERE p.post_status = 'publish'
				AND p.post_type IN ('post', 'page')
				AND p.post_type != 'revision'"
			);
			
			if (!empty($translated_post_ids)) {
				$existing_not_in = $query->get('post__not_in') ?: array();
				$query->set('post__not_in', array_merge($existing_not_in, $translated_post_ids));
			}
		} else {
			// On language-prefixed URL: Show only translated posts for this language
			// Exclude original posts and other language translations
			
			// Get translated post IDs for this language
			$translated_post_ids = $wpdb->get_col($wpdb->prepare(
				"SELECT DISTINCT p.ID 
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
					AND pm1.meta_key = '_xf_translator_language' 
					AND pm1.meta_value = %s
				INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
					AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
				WHERE p.post_status = 'publish'
				AND p.post_type IN ('post', 'page')
				AND p.post_type != 'revision'",
				$lang_prefix
			));
			
			if (!empty($translated_post_ids)) {
				$query->set('post__in', $translated_post_ids);
				$query->set('orderby', 'post__in');
			} else {
				// No translated posts, show nothing
				$query->set('post__in', array(0));
			}
		}
	}

}
