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
		
		// Add posts_where filter to exclude translated posts at SQL level (more reliable than meta_query)
		add_filter('posts_where', array($this, 'filter_posts_where_exclude_translations'), 999, 2);
		
		// Filter meta fields to show translated versions
		add_filter('get_post_meta', array($this, 'filter_get_post_meta'), 10, 4);
		add_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10, 4);
		add_filter('get_the_author_meta', array($this, 'filter_get_the_author_meta'), 20, 3);
		add_filter('the_author_meta', array($this, 'filter_the_author_meta_output'), 20, 2);
		// Add filter for specific author meta fields (WordPress uses dynamic filters: get_the_author_{$field})
		// This is the actual filter WordPress uses for get_the_author_meta('description')
		// Note: The dynamic filter passes ($value, $user_id, $original_user_id) - no field parameter
		add_filter('get_the_author_description', array($this, 'filter_get_the_author_description'), 20, 3);
		add_filter('the_author_description', array($this, 'filter_the_author_meta_output'), 20, 2);
		
		// Debug: Log that filters are registered (only once per page load)
		if (!defined('XF_TRANSLATOR_LOGGED_CONSTRUCTOR')) {
			define('XF_TRANSLATOR_LOGGED_CONSTRUCTOR', true);
			$this->write_debug_log('XF Translator Public class constructor called, filters being registered');
		}
		
		// Verify filter is actually registered on wp hook
		add_action('wp', array($this, 'debug_check_filters'), 999);
		
		// Removed excessive logging hook - was causing too many log entries

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
			
			// Use the configured prefix exactly as stored for lookups/meta,
			// and normalize only for hreflang output.
			$prefix             = $language['prefix'];
			$translated_post_id = $this->get_translated_post_id( $original_post_id, $prefix );
			if ( ! $translated_post_id ) {
				continue;
			}

			$permalink = get_permalink( $translated_post_id );
			if ( ! $permalink ) {
				continue;
			}

			// hreflang should be lowercase (e.g., de-de, fr-ca), but this is
			// independent from how we store prefixes/meta internally.
			$hreflang = ! empty( $language['hreflang'] ) ? strtolower( $language['hreflang'] ) : strtolower( $prefix );
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

				// URL segment for this language (e.g. "fr-CA" => "frCA").
				$url_prefix = $this->get_url_prefix_for_language( $language );

				// Use the configured prefix exactly for lookups/meta.
				$translated_id = $this->get_translated_post_id( $original_post_id, $language['prefix'] );
				if ( ! $translated_id ) {
					// No translation exists, link to home page with language prefix
					$items[] = array(
						'label'  => $language['name'],
						'url'    => $url_prefix ? home_url( '/' . $url_prefix . '/' ) : home_url( '/' ),
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

				$url_prefix = $this->get_url_prefix_for_language( $language );
				$items[] = array(
					'label'  => $language['name'],
					'url'    => $url_prefix ? home_url( '/' . $url_prefix . '/' ) : home_url( '/' ),
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
	 * Get the URL-safe prefix segment for a language configuration.
	 *
	 * Uses the 'path' field if available, otherwise falls back to 'prefix'.
	 * Example:
	 * - Stored path "fr" → URL prefix "fr".
	 * - Stored path "Ar" → URL prefix "Ar".
	 * - If path not set, uses prefix: "fr-CA" → URL prefix "frCA" (hyphen removed).
	 *
	 * This lets site owners store human-friendly prefixes (used in meta / hreflang),
	 * while URLs use a simple segment that avoids server/host restrictions.
	 *
	 * @param array|string $language Language settings array or raw prefix string.
	 * @return string URL-safe prefix (no slashes, no spaces), or empty string on failure.
	 */
	private function get_url_prefix_for_language( $language ) {
		if ( is_array( $language ) ) {
			// Use 'path' field if available, otherwise fall back to 'prefix'
			$prefix = isset( $language['path'] ) && !empty( $language['path'] ) 
				? $language['path'] 
				: ( isset( $language['prefix'] ) ? $language['prefix'] : '' );
		} else {
			$prefix = (string) $language;
		}

		if ( ! $prefix ) {
			return '';
		}

		// Trim whitespace and slashes.
		$prefix = trim( $prefix );
		$prefix = trim( $prefix, '/' );

		if ( $prefix === '' ) {
			return '';
		}

		// Build URL-safe prefix: remove all non-alphanumeric characters.
		// e.g. "fr-CA" => "frCA", "pt-BR" => "ptBR", "fr" => "fr", "Ar" => "Ar".
		$url_prefix = preg_replace( '/[^A-Za-z0-9]/', '', $prefix );

		return $url_prefix ?: '';
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
		$languages = $settings->get( 'languages', array() );
		
		if ( empty( $languages ) ) {
			return;
		}

		// Register query var so it's accessible in WP_Query.
		add_rewrite_tag( '%xf_lang_prefix%', '([^/]+)' );
		global $wp;
		$wp->add_query_var( 'xf_lang_prefix' );
		
		// Get all public taxonomies for rewrite rules
		$taxonomies = get_taxonomies( array( 'public' => true, 'publicly_queryable' => true ), 'objects' );

		// Add per-language rewrite rules so we can map clean URL prefixes
		// (e.g. "frCA") back to stored prefixes (e.g. "fr-CA") in xf_lang_prefix.
		foreach ( $languages as $language ) {
			if ( empty( $language['prefix'] ) ) {
				continue;
			}

			$stored_prefix = $language['prefix']; // e.g. "fr-CA".
			$url_prefix    = $this->get_url_prefix_for_language( $language ); // e.g. "frCA".

			if ( ! $url_prefix ) {
				continue;
			}

			$escaped_url_prefix = preg_quote( $url_prefix, '/' );

			// Home page with language prefix: /frCA/
			add_rewrite_rule(
				'^' . $escaped_url_prefix . '/?$',
				'index.php?xf_lang_prefix=' . urlencode( $stored_prefix ),
				'top'
			);

			// Single posts/pages with language prefix: /frCA/post-slug/
			add_rewrite_rule(
				'^' . $escaped_url_prefix . '/([^/]+)/?$',
				'index.php?name=$matches[1]&xf_lang_prefix=' . urlencode( $stored_prefix ),
				'top'
			);

			// Pagination for posts/pages: /frCA/post-slug/page/2/
			add_rewrite_rule(
				'^' . $escaped_url_prefix . '/([^/]+)/page/([0-9]+)/?$',
				'index.php?name=$matches[1]&paged=$matches[2]&xf_lang_prefix=' . urlencode( $stored_prefix ),
				'top'
			);

			// Taxonomy archives with language prefix
			// Category archives: /frCA/category/category-slug/
			// Tag archives: /frCA/tag/tag-slug/
			// Custom taxonomy: /frCA/taxonomy/term-slug/
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! $taxonomy->public || ! $taxonomy->publicly_queryable ) {
					continue;
				}

				$taxonomy_slug = $taxonomy->rewrite['slug'] ?? $taxonomy->name;

				// Taxonomy archive
				add_rewrite_rule(
					'^' . $escaped_url_prefix . '/' . preg_quote( $taxonomy_slug, '/' ) . '/([^/]+)/?$',
					'index.php?' . $taxonomy->query_var . '=$matches[1]&xf_lang_prefix=' . urlencode( $stored_prefix ),
					'top'
				);

				// Paginated taxonomy archive
				add_rewrite_rule(
					'^' . $escaped_url_prefix . '/' . preg_quote( $taxonomy_slug, '/' ) . '/([^/]+)/page/([0-9]+)/?$',
					'index.php?' . $taxonomy->query_var . '=$matches[1]&paged=$matches[2]&xf_lang_prefix=' . urlencode( $stored_prefix ),
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
	 * Debug: Check if filters are registered (temporary debugging function)
	 * Only log once per page load to reduce log spam
	 */
	public function debug_check_filters() {
		if (!defined('XF_TRANSLATOR_LOGGED_FILTER_CHECK')) {
			define('XF_TRANSLATOR_LOGGED_FILTER_CHECK', true);
			$has_filter1 = has_filter('get_the_author_meta', array($this, 'filter_get_the_author_meta'));
			$has_filter2 = has_filter('get_the_author_description', array($this, 'filter_get_the_author_description'));
			$this->write_debug_log('Filter check - get_the_author_meta: ' . ($has_filter1 ? 'YES (priority: ' . $has_filter1 . ')' : 'NO') . ', get_the_author_description: ' . ($has_filter2 ? 'YES (priority: ' . $has_filter2 . ')' : 'NO'));
		}
	}
	
	/**
	 * Get current language prefix from URL or post meta
	 *
	 * @return string Language prefix or empty string
	 */
	private function get_current_language_prefix() {
		// First, try to get from query var (URL) - this is the most reliable
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// If we have a prefix from query var, use it (it's already the stored prefix format)
		if (!empty($lang_prefix)) {
			return $lang_prefix;
		}
		
		// If no language prefix in URL, check if we're on a translated post
		global $post;
		if ($post && isset($post->ID)) {
			$post_lang = get_post_meta($post->ID, '_xf_translator_language', true);
			if (!empty($post_lang)) {
				return $post_lang;
			}
		}
		
		// Also check from current URL path
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$path        = parse_url( $request_uri, PHP_URL_PATH );

		if ( $path ) {
			// Try to match any configured language by its URL-safe prefix
			// (e.g. "fr-CA" uses "frCA" in the URL).
			$languages = $this->settings->get( 'languages', array() );
			foreach ( $languages as $language ) {
				if ( empty( $language['prefix'] ) ) {
					continue;
				}

				$url_prefix = $this->get_url_prefix_for_language( $language );
				if ( ! $url_prefix ) {
					continue;
				}

				// Match URL prefix at the start of the path
				if ( preg_match( '#^/' . preg_quote( $url_prefix, '#' ) . '(/|$)#i', $path ) ) {
					// Return the original configured prefix (e.g. "fr-CA").
					return $language['prefix'];
				}
			}
		}
		
		return '';
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
		$this->write_debug_log('filter_content_by_language - Main query - lang_prefix: ' . ($lang_prefix ? $lang_prefix : 'EMPTY'));
		
		// Check if this is home page by checking query vars
		// At pre_get_posts, is_home() might not be set yet, so check query vars directly
		$is_home_page = false;
		$name = $query->get('name');
		$pagename = $query->get('pagename');
		$page_id = $query->get('page_id');
		$p = $query->get('p');
		$category_name = $query->get('category_name');
		$tag = $query->get('tag');
		
		// If no specific post/page/category/tag is requested, it's likely home/blog archive
		if (empty($name) && empty($pagename) && empty($page_id) && empty($p) && empty($category_name) && empty($tag)) {
			$is_home_page = true;
		}
		
		global $wpdb;
		
		if (empty($lang_prefix)) {
			// On English/default: Exclude all translated posts
			// The actual exclusion is handled by filter_posts_where_exclude_translations
			// which uses SQL NOT EXISTS for more reliable filtering
			// We don't need to set meta_query here as posts_where filter handles it
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
	
	/**
	 * Filter posts results to remove translated posts on English pages
	 * This is a backup filter that runs after the query executes
	 *
	 * @param array $posts Array of post objects
	 * @param WP_Query $query Query object
	 * @return array Filtered posts
	 */
	public function filter_posts_results_by_language($posts, $query) {
		// Only filter main query on frontend
		if (is_admin() || !$query->is_main_query()) {
			return $posts;
		}
		
		// Skip for singular posts/pages
		if ($query->is_singular) {
			return $posts;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// Only filter if we're on English (no prefix)
		if (!empty($lang_prefix)) {
			return $posts;
		}
		
		// Filter out any posts that have translation meta or are linked as translations
		$filtered_posts = array();
		global $wpdb;
		
		foreach ($posts as $post) {
			$has_language_meta = get_post_meta($post->ID, '_xf_translator_language', true);
			$has_original_id = get_post_meta($post->ID, '_xf_translator_original_post_id', true);
			$has_legacy_original_id = get_post_meta($post->ID, '_api_translator_original_post_id', true);
			
			// Check if this post is linked as a translation from another post (forward links)
			// The meta_value is stored as a string, so we need to cast it
			$is_linked_translation = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} 
				WHERE meta_key LIKE '_xf_translator_translated_post_%' 
				AND CAST(meta_value AS UNSIGNED) = %d",
				$post->ID
			));
			
			// Only include posts that don't have any translation meta AND aren't linked as translations
			if (empty($has_language_meta) && empty($has_original_id) && empty($has_legacy_original_id) && !$is_linked_translation) {
				$filtered_posts[] = $post;
			}
		}
		
		return $filtered_posts;
	}
	
	/**
	 * Filter posts_where to exclude translated posts at SQL level
	 * This is more reliable than meta_query as it directly modifies the SQL
	 *
	 * @param string $where WHERE clause
	 * @param WP_Query $query Query object
	 * @return string Modified WHERE clause
	 */
	public function filter_posts_where_exclude_translations($where, $query) {
		// Only filter on frontend (apply to ALL frontend queries, not just main query)
		// This ensures translated posts don't appear in widgets or custom loops either.
		if (is_admin()) {
			return $where;
		}
		
		// Skip for singular posts/pages
		if ($query->is_singular) {
			return $where;
		}
		
		// Get current language prefix from URL
		$lang_prefix = get_query_var('xf_lang_prefix');
		
		// Only filter if we're on English (no prefix)
		if (!empty($lang_prefix)) {
			return $where;
		}
		
		global $wpdb;
		
		// First, get all translated post IDs to verify what we're excluding
		$translated_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id 
			FROM {$wpdb->postmeta} 
			WHERE meta_key IN ('_xf_translator_original_post_id', '_api_translator_original_post_id', '_xf_translator_language')"
		);
		
		$forward_linked_ids = $wpdb->get_col(
			"SELECT DISTINCT CAST(meta_value AS UNSIGNED) 
			FROM {$wpdb->postmeta} 
			WHERE meta_key LIKE '_xf_translator_translated_post_%'
			AND meta_value != ''
			AND CAST(meta_value AS UNSIGNED) > 0"
		);
		
		$all_translated = array_unique(array_merge($translated_ids, $forward_linked_ids));
		
		// Exclude posts that are translations by checking:
		// 1. Posts with _xf_translator_language meta (any translation)
		// 2. Posts with _xf_translator_original_post_id or _api_translator_original_post_id (linked translations)
		// 3. Posts that are referenced in _xf_translator_translated_post_* meta keys on other posts (forward links)
		$exclusion_sql = " AND NOT EXISTS (
			SELECT 1 
			FROM {$wpdb->postmeta} pm
			WHERE pm.post_id = {$wpdb->posts}.ID
			AND pm.meta_key IN ('_xf_translator_original_post_id', '_api_translator_original_post_id', '_xf_translator_language')
		) AND NOT EXISTS (
			SELECT 1 
			FROM {$wpdb->postmeta} pm2
			WHERE pm2.meta_key LIKE '_xf_translator_translated_post_%'
			AND CAST(pm2.meta_value AS UNSIGNED) = {$wpdb->posts}.ID
		)";
		
		$where .= $exclusion_sql;
		
		return $where;
	}

	/**
	 * Prevent WordPress canonical redirects from stripping language prefixes
	 * on translated URLs (e.g. /de-DE/post-slug/ → /post-slug/).
	 *
	 * @param string|false $redirect_url  The URL WordPress wants to redirect to.
	 * @param string       $requested_url The originally requested URL.
	 *
	 * @return string|false Modified redirect URL or false to disable redirect.
	 */
	public function filter_redirect_canonical( $redirect_url, $requested_url ) {
		// Only affect frontend.
		if ( is_admin() ) {
			return $redirect_url;
		}

		// If our language query var is present, never canonical-redirect.
		$lang_prefix = get_query_var( 'xf_lang_prefix' );
		if ( ! empty( $lang_prefix ) ) {
			return false;
		}

		// Extra safety: detect language prefixes directly from the requested path.
		$path = parse_url( $requested_url, PHP_URL_PATH );
		if ( empty( $path ) ) {
			return $redirect_url;
		}

		$languages = $this->settings->get( 'languages', array() );
		if ( empty( $languages ) ) {
			return $redirect_url;
		}

		foreach ( $languages as $language ) {
			if ( empty( $language['prefix'] ) ) {
				continue;
			}

			// Use URL-safe prefix for path matching (e.g. "fr-CA" => "frCA").
			$url_prefix = $this->get_url_prefix_for_language( $language );
			if ( ! $url_prefix ) {
				continue;
			}

			// Match URLs that start with the URL prefix, e.g. /frCA/... or /deDE/...
			if ( preg_match( '#^/' . preg_quote( $url_prefix, '#' ) . '(/|$)#i', $path ) ) {
				return false;
			}
		}

		return $redirect_url;
	}
	
	/**
	 * Filter get_post_meta to return translated meta values
	 *
	 * @param mixed $value Meta value
	 * @param int $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param bool $single Whether to return single value
	 * @return mixed Translated meta value or original value
	 */
	public function filter_get_post_meta($value, $post_id, $meta_key, $single)
	{
		// Only filter on frontend
		if (is_admin()) {
			return $value;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		if (empty($lang_prefix)) {
			return $value;
		}
		
		// Check if this meta field should be translated
		$translatable_fields = $this->settings->get_translatable_post_meta_fields();
		if (empty($translatable_fields) || !in_array($meta_key, $translatable_fields)) {
			return $value;
		}
		
		// Get original post ID if this is a translated post
		$original_post_id = $this->get_original_post_id($post_id);
		
		// Get translated meta value from original post
		$translated_meta_key = '_xf_translator_meta_' . $meta_key . '_' . $lang_prefix;
		$translated_value = get_post_meta($original_post_id, $translated_meta_key, $single);
		
		// Return translated value if available, otherwise original
		if (!empty($translated_value)) {
			return $translated_value;
		}
		
		// If no translated value, return original (which might be from translated post or original post)
		return $value;
	}
	
	/**
	 * Filter get_user_meta to return translated meta values
	 *
	 * @param mixed $value Meta value
	 * @param int $user_id User ID
	 * @param string $meta_key Meta key
	 * @param bool $single Whether to return single value
	 * @return mixed Translated meta value or original value
	 */
	public function filter_get_user_meta($value, $user_id, $meta_key, $single)
	{
		// Only filter on frontend
		if (is_admin()) {
			return $value;
		}
		
		// Skip if no meta key provided (getting all meta)
		if (empty($meta_key)) {
			return $value;
		}
		
		// Get current language prefix
		// On singular pages, prioritize the post's language meta
		global $post;
		$lang_prefix = '';
		if (is_singular() && $post && isset($post->ID)) {
			$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
		}
		
		// If no language from post, use the general method
		if (empty($lang_prefix)) {
			$lang_prefix = $this->get_current_language_prefix();
		}
		
		if (empty($lang_prefix)) {
			return $value;
		}
		
		// Check if this meta field should be translated
		$translatable_fields = $this->settings->get_translatable_user_meta_fields();
		if (empty($translatable_fields)) {
			return $value;
		}
		
		// Check if this field is in the translatable fields list (exact match or normalized)
		if (!$this->is_field_translatable($meta_key, $translatable_fields)) {
			return $value;
		}
		
		// Get normalized meta key (handles WordPress internal mappings like user_description -> description)
		$normalized_key = $this->normalize_user_meta_key($meta_key);
		
		// Try to get translated value - bypass our own filter to avoid recursion
		remove_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10);
		$translated_value = $this->get_translated_user_meta($user_id, $meta_key, $normalized_key, $lang_prefix, $single, $translatable_fields);
		add_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10, 4);
		
		// Return translated value if available, otherwise original
		return !empty($translated_value) ? $translated_value : $value;
	}
	
	/**
	 * Check if a field is translatable (handles field name variations)
	 *
	 * @param string $meta_key Meta key to check
	 * @param array $translatable_fields List of translatable field keys
	 * @return bool True if field should be translated
	 */
	private function is_field_translatable($meta_key, $translatable_fields)
	{
		// Direct match
		if (in_array($meta_key, $translatable_fields)) {
			return true;
		}
		
		// Check normalized version
		$normalized = $this->normalize_user_meta_key($meta_key);
		if ($normalized !== $meta_key && in_array($normalized, $translatable_fields)) {
			return true;
		}
		
		// Check if any translatable field normalizes to this key
		foreach ($translatable_fields as $translatable_field) {
			$normalized_translatable = $this->normalize_user_meta_key($translatable_field);
			if ($normalized_translatable === $meta_key || $normalized_translatable === $normalized) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Normalize user meta key (handles WordPress internal mappings)
	 *
	 * @param string $meta_key Original meta key
	 * @return string Normalized meta key
	 */
	private function normalize_user_meta_key($meta_key)
	{
		// WordPress stores 'user_description' as 'description' in the database
		if ($meta_key === 'user_description') {
			return 'description';
		}
		
		return $meta_key;
	}
	
	/**
	 * Get translated user meta value, trying multiple possible keys
	 *
	 * @param int $user_id User ID
	 * @param string $original_key Original meta key requested
	 * @param string $normalized_key Normalized meta key
	 * @param string $lang_prefix Language prefix
	 * @param bool $single Whether to return single value
	 * @param array $translatable_fields List of translatable fields
	 * @return mixed Translated value or empty
	 */
	private function get_translated_user_meta($user_id, $original_key, $normalized_key, $lang_prefix, $single, $translatable_fields)
	{
		// Try keys in order of preference:
		// 1. Normalized key (this is how translations are stored - always try this first)
		// 2. Original key if it's in translatable fields
		// 3. Any translatable field that normalizes to this key
		
		$keys_to_try = array();
		
		// Always try normalized key first (translations are stored with normalized keys)
		$keys_to_try[] = $normalized_key;
		
		// Add original key if it's in translatable fields and different from normalized
		if ($original_key !== $normalized_key && in_array($original_key, $translatable_fields)) {
			$keys_to_try[] = $original_key;
		}
		
		// Add any translatable field that normalizes to our target
		foreach ($translatable_fields as $field) {
			$normalized_field = $this->normalize_user_meta_key($field);
			if ($normalized_field === $normalized_key && !in_array($field, $keys_to_try) && $field !== $normalized_key) {
				$keys_to_try[] = $field;
			}
		}
		
		// Remove duplicates while preserving order
		$keys_to_try = array_values(array_unique($keys_to_try));
		
		// Get all possible language prefixes to try
		// This handles cases where URL prefix (e.g., "fr") doesn't match stored prefix (e.g., "fr-CA")
		$lang_prefixes_to_try = array($lang_prefix);
		
		// Always try to find matching prefixes from configured languages
		// This handles both short prefixes (e.g., "fr") and full prefixes (e.g., "fr-CA")
		$languages = $this->settings->get('languages', array());
		foreach ($languages as $language) {
			if (!empty($language['prefix'])) {
				$stored_prefix = $language['prefix'];
				
				// If exact match, it's already in the array
				if (strtolower($stored_prefix) === strtolower($lang_prefix)) {
					continue; // Already added
				}
				
				// If lang_prefix is short (e.g., "fr"), check if stored prefix starts with it
				if (strlen($lang_prefix) <= 3 && strpos($lang_prefix, '-') === false) {
					if (strtolower(substr($stored_prefix, 0, strlen($lang_prefix))) === strtolower($lang_prefix)) {
						if (!in_array($stored_prefix, $lang_prefixes_to_try)) {
							$lang_prefixes_to_try[] = $stored_prefix;
						}
					}
				}
				
				// Also check reverse: if stored prefix is short and lang_prefix starts with it
				if (strlen($stored_prefix) <= 3 && strpos($stored_prefix, '-') === false) {
					if (strtolower(substr($lang_prefix, 0, strlen($stored_prefix))) === strtolower($stored_prefix)) {
						if (!in_array($stored_prefix, $lang_prefixes_to_try)) {
							$lang_prefixes_to_try[] = $stored_prefix;
						}
					}
				}
			}
		}
		
		// Try each key with each language prefix
		foreach ($keys_to_try as $key) {
			foreach ($lang_prefixes_to_try as $prefix_to_try) {
				$translated_meta_key = '_xf_translator_user_meta_' . $key . '_' . $prefix_to_try;
				// Bypass our own filter to avoid recursion
				remove_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10);
				$translated_value = get_user_meta($user_id, $translated_meta_key, $single);
				add_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10, 4);
				
				// Check if we got a valid translated value
				// For single values, check if it's not empty and not false
				// For arrays, check if it's not empty
				if ($single) {
					if ($translated_value !== false && $translated_value !== '' && $translated_value !== null) {
						return $translated_value;
					}
				} else {
					if (!empty($translated_value) && is_array($translated_value)) {
						return $translated_value;
					}
				}
			}
		}
		
		return '';
	}
	
	/**
	 * Filter get_the_author_description - specific handler for description field
	 * WordPress uses dynamic filter get_the_author_{$field} which doesn't pass field name
	 *
	 * @param mixed $value Meta value
	 * @param int $user_id User ID
	 * @param int|false $original_user_id Original user ID
	 * @return mixed Translated meta value or original value
	 */
	public function filter_get_the_author_description($value, $user_id, $original_user_id) {
		// Call the main filter function with the field name explicitly set
		return $this->filter_get_the_author_meta($value, $user_id, 'description');
	}
	
	/**
	 * Filter get_the_author_meta to return translated author meta values
	 *
	 * @param mixed $value Meta value
	 * @param int $user_id User ID
	 * @param string $field Meta field name
	 * @return mixed Translated meta value or original value
	 */
	public function filter_get_the_author_meta($value, $user_id, $field)
	{
		// Only filter on frontend
		if (is_admin()) {
			return $value;
		}
		
		// Skip if no field provided
		if (empty($field)) {
			return $value;
		}
		
		// If user_id is not provided or is 0, try to get it from global post
		global $post;
		if (empty($user_id) && $post && isset($post->post_author)) {
			$user_id = $post->post_author;
		}
		
		// If still no user ID, return original value
		if (empty($user_id)) {
			return $value;
		}
		
		// Get current language prefix
		// On singular pages, prioritize the post's language meta
		$lang_prefix = '';
		if (is_singular() && $post && isset($post->ID)) {
			$lang_prefix = get_post_meta($post->ID, '_xf_translator_language', true);
		}
		
		// If no language from post, use the general method
		if (empty($lang_prefix)) {
			$lang_prefix = $this->get_current_language_prefix();
		}
		
		if (empty($lang_prefix)) {
			return $value;
		}
		
		// Check if this meta field should be translated
		$translatable_fields = $this->settings->get_translatable_user_meta_fields();
		if (empty($translatable_fields)) {
			return $value;
		}
		
		// Check if this field is in the translatable fields list
		if (!$this->is_field_translatable($field, $translatable_fields)) {
			return $value;
		}
		
		// Get normalized meta key
		$normalized_key = $this->normalize_user_meta_key($field);
		
		// Try to get translated value - bypass our own filter to avoid recursion
		remove_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10);
		remove_filter('get_the_author_meta', array($this, 'filter_get_the_author_meta'), 20);
		$translated_value = $this->get_translated_user_meta($user_id, $field, $normalized_key, $lang_prefix, true, $translatable_fields);
		add_filter('get_the_author_meta', array($this, 'filter_get_the_author_meta'), 20, 3);
		add_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10, 4);
		
		// Return translated value if available
		// get_translated_user_meta returns empty string if no translation found
		// So if we get a non-empty value, use it; otherwise use original
		// Check for both empty string and false (get_user_meta returns false if key doesn't exist)
		if ($translated_value !== false && $translated_value !== '' && $translated_value !== null) {
			return $translated_value;
		}
		
		return $value;
	}
	
	/**
	 * Filter the_author_meta output to return translated author meta values
	 * This handles direct output of author meta (e.g., the_author_meta('description'))
	 *
	 * @param mixed $value Meta value
	 * @param string $field Meta field name
	 * @return mixed Translated meta value or original value
	 */
	public function filter_the_author_meta_output($value, $field)
	{
		// Only filter on frontend
		if (is_admin()) {
			return $value;
		}
		
		// Get the user ID from the current post author
		global $post;
		if (!$post) {
			return $value;
		}
		
		$user_id = $post->post_author;
		if (!$user_id) {
			return $value;
		}
		
		// Use the existing filter logic
		return $this->filter_get_the_author_meta($value, $user_id, $field);
	}
	
	/**
	 * Helper method to write to debug log file
	 * 
	 * @param string $message Log message
	 * @return void
	 */
	private function write_debug_log($message) {
		$log_file = WP_CONTENT_DIR . '/xf-translator-debug.log';
		$timestamp = date('Y-m-d H:i:s');
		$log_entry = $timestamp . ' - ' . $message . "\n";
		
		// Ensure directory exists and file is writable
		if (!file_exists($log_file)) {
			// Try to create the file
			@touch($log_file);
			@chmod($log_file, 0666);
		}
		
		// Write to custom log file (without error suppression so we can see errors)
		$result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
		
		// If file write failed, also log to error_log so we know about it
		if ($result === false) {
			$error = error_get_last();
			error_log('XF Translator: Failed to write to debug log file: ' . $log_file . ' - Message: ' . $message . ' - Error: ' . ($error ? $error['message'] : 'Unknown error'));
		}
		
		// Also write to error_log for visibility in debug.log
		error_log('XF Translator: ' . $message);
	}

}

