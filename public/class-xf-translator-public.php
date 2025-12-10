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
		
		// Force homepage template for language-prefixed homepages
		add_filter('template_include', array($this, 'force_homepage_template_for_language'), 99);
		
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
		
		// Filter meta fields to show translated versions
		add_filter('get_post_meta', array($this, 'filter_get_post_meta'), 10, 4);
		add_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10, 4);
		add_filter('get_the_author_meta', array($this, 'filter_get_the_author_meta'), 20, 3);
		add_filter('the_author_meta', array($this, 'filter_the_author_meta_output'), 20, 2);
		
		// Filter user_description field for direct property access (e.g., $userdata->description)
		// This handles cases where themes use get_userdata() and access properties directly
		// WordPress applies this filter via sanitize_user_field() when $user->filter is set
		add_filter('user_description', array($this, 'filter_user_description_property'), 10, 2);
		
		// Modify WP_User object data property directly after it's created
		// This is needed because $userdata->description accesses $this->data->description directly
		add_filter('get_user_metadata', array($this, 'filter_user_metadata_for_cache'), 10, 4);
		
		// Intercept when user data is loaded and modify the description in the data object
		// Use 'template_redirect' which fires early but after query is set up
		add_action('template_redirect', array($this, 'modify_user_data_on_page_load'), 1);
		
		// Also hook into get_userdata to modify the object immediately after creation
		add_filter('get_user_metadata', array($this, 'modify_user_data_in_metadata'), 999, 4);
		
		// Hook into the loop_start to modify user data for all authors in the loop
		add_action('loop_start', array($this, 'modify_user_data_in_loop'), 1);
		
		// Filter ACF fields to convert post IDs to translated versions on frontend
		if (function_exists('get_field')) {
			add_filter('acf/load_value', array($this, 'filter_acf_load_value'), 10, 3);
		}
		
		// Filter query_posts() results (query_posts bypasses pre_get_posts)
		add_filter('the_posts', array($this, 'filter_query_posts_results'), 10, 2);

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
	 * 
	 * Note: This switcher is visible to ALL users (logged in or not) on the frontend.
	 * It only hides in the WordPress admin area.
	 */
	public function render_language_switcher() {
		// Debug logging
		$is_logged_in = is_user_logged_in();
		$is_admin_area = is_admin();
		$current_url = home_url( add_query_arg( null, null ) );
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'unknown';
		error_log( '[XF-Translator] render_language_switcher() called - User logged in: ' . ( $is_logged_in ? 'YES' : 'NO' ) . ', is_admin: ' . ( $is_admin_area ? 'YES' : 'NO' ) . ', URL: ' . $request_uri );
		
		// Only hide in admin area - show for all users on frontend (logged in or not)
		if ( is_admin() ) {
			error_log( '[XF-Translator] Blocked: is_admin() returned true' );
			return;
		}

		$languages = $this->settings->get( 'languages', array() );
		error_log( '[XF-Translator] Languages count: ' . count( $languages ) );
		if ( empty( $languages ) ) {
			error_log( '[XF-Translator] Blocked: Languages array is empty' );
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
		error_log( '[XF-Translator] Items count: ' . count( $items ) );
		if ( empty( $items ) ) {
			error_log( '[XF-Translator] Blocked: Items array is empty' );
			return;
		}
		
		error_log( '[XF-Translator] Proceeding to render switcher HTML' );

		static $assets_printed = false;
		if ( ! $assets_printed ) {
			$assets_printed = true;
			?>
			<style>
				.xf-lang-switcher {
					position: fixed !important;
					left: 24px !important;
					bottom: 24px !important;
					z-index: 9999 !important;
					font-family: inherit;
					display: block !important;
					visibility: visible !important;
					opacity: 1 !important;
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
				(function() {
					function initLanguageSwitcher() {
						var switchers = document.querySelectorAll('.xf-lang-switcher');
						console.log('[XF-Translator] Found ' + switchers.length + ' language switcher(s)');
						
						switchers.forEach(function (switcher) {
							console.log('[XF-Translator] Initializing switcher:', switcher);
							var toggle = switcher.querySelector('.xf-lang-toggle');
							if (!toggle) {
								console.warn('[XF-Translator] Toggle button not found in switcher');
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
					}
					
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', initLanguageSwitcher);
					} else {
						initLanguageSwitcher();
					}
				})();
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
		<div class="xf-lang-switcher" 
		     aria-label="<?php esc_attr_e( 'Language switcher', 'xf-translator' ); ?>"
		     data-xf-debug="rendered"
		     data-xf-user-logged-in="<?php echo is_user_logged_in() ? 'yes' : 'no'; ?>"
		     data-xf-items-count="<?php echo count( $items ); ?>">
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
		error_log( '[XF-Translator] Switcher HTML rendered successfully. Current label: ' . $current_label . ', Items: ' . count( $items ) . ', User logged in: ' . ( is_user_logged_in() ? 'YES' : 'NO' ) );
		error_log( '[XF-Translator] HTML output complete. Check page source for .xf-lang-switcher element.' );
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
	 * Normalize a language prefix for comparisons (lowercase, alphanumeric only)
	 *
	 * @param string $prefix
	 * @return string
	 */
	private function normalize_lang_prefix( $prefix ) {
		if ( empty( $prefix ) ) {
			return '';
		}

		$prefix = strtolower( $prefix );
		$prefix = preg_replace( '/[^a-z0-9]/i', '', $prefix );

		return $prefix ?: '';
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
		// Try multiple prefix variations to handle cases like "fr-CA" vs "fr"
		$prefixes_to_try = array();
		$prefixes_to_try[] = $language_prefix;

		// Add normalized (alphanumeric) version e.g., "fr-CA" -> "frca"
		$normalized = $this->normalize_lang_prefix( $language_prefix );
		if ( $normalized && $normalized !== $language_prefix ) {
			$prefixes_to_try[] = $normalized;
		}

		// Add base prefix before dash (e.g., "fr-CA" -> "fr")
		if ( strpos( $language_prefix, '-' ) !== false ) {
			$base = strtolower( substr( $language_prefix, 0, strpos( $language_prefix, '-' ) ) );
			if ( $base && ! in_array( $base, $prefixes_to_try, true ) ) {
				$prefixes_to_try[] = $base;
			}
		}

		// Deduplicate non-empty prefixes
		$prefixes_to_try = array_values( array_filter( array_unique( $prefixes_to_try ) ) );

		foreach ( $prefixes_to_try as $prefix ) {
			// Meta mapping cache first
			$meta_key = '_xf_translator_translated_post_' . $prefix;
			$post_id  = get_post_meta( $original_post_id, $meta_key, true );

			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post && $post->post_status === 'publish' ) {
					return (int) $post_id;
				}
			}

			// Query for translated post
			$args = array(
				'post_type'      => get_post_type( $original_post_id ),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_xf_translator_language',
						'value' => $prefix,
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
		
		// Debug: Log when this function is called
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('XF Translator: filter_translated_post_query called for: ' . $request_uri);
		}
		
		// Skip for asset requests (CSS, JS, images, etc.) - more thorough check
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		
		// Remove query string for checking
		$request_path = parse_url($request_uri, PHP_URL_PATH);
		$request_path = $request_path ?: $request_uri;
		
		// Get language prefix - use get_current_language_prefix() to handle cases where query_var is empty
		$lang_prefix = $this->get_current_language_prefix();
		
		// Debug: Log language detection
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$query_var = get_query_var('xf_lang_prefix');
			error_log('XF Translator DEBUG: request_uri=' . $request_uri . ', lang_prefix=' . ($lang_prefix ?: 'empty') . ', query_var=' . ($query_var ?: 'empty') . ', request_path=' . ($request_path ?: 'empty'));
		}
		
		// Check if the requested file actually exists (this handles all asset files)
		// First check with language prefix removed
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
		
		// On English/default pages: Exclude translated posts from the query
		if (empty($lang_prefix)) {
			// Get the post by slug
			$post_name = get_query_var('name');
			
			// If this is a singular query (post/page), ensure we exclude translated posts
			if ($query->is_singular || !empty($post_name) || !empty($query->get('pagename')) || !empty($query->get('p'))) {
				global $wpdb;
				
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
					error_log('XF Translator: Excluding ' . count($translated_post_ids) . ' translated posts from English singular query');
				}
			}
			
			return;
		}
		
		// Get the post by slug
		$post_name = get_query_var('name');
		$pagename = get_query_var('pagename');
		$page_id = get_query_var('page_id');
		$p = get_query_var('p');
		
		// Check if the request path is exactly the language prefix (e.g., /fr/ or /es/)
		// This handles the homepage case for language-prefixed URLs
		// $request_path is already defined above, but we need to check it for homepage detection
		$request_path_clean = trim($request_path, '/');
		
		// Get URL prefix for current language to check if we're on homepage
		$languages = $this->settings->get('languages', array());
		$is_lang_homepage = false;
		$matched_url_prefix = '';
		foreach ($languages as $language) {
			if (empty($language['prefix']) || $language['prefix'] !== $lang_prefix) {
				continue;
			}
			$url_prefix = $this->get_url_prefix_for_language($language);
			if ($url_prefix) {
				// Check if request path matches exactly the URL prefix (e.g., "fr" or "es")
				// or if it's empty (root with language prefix in query var)
				if ($request_path_clean === $url_prefix || 
				    ($request_path_clean === '' && !empty($lang_prefix))) {
					$is_lang_homepage = true;
					$matched_url_prefix = $url_prefix;
					break;
				}
			}
		}
		
		// Debug: Log what we're looking for
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('XF Translator DEBUG homepage check:');
			error_log('  - lang_prefix=' . ($lang_prefix ?: 'empty'));
			error_log('  - post_name=' . ($post_name ?: 'empty'));
			error_log('  - pagename=' . ($pagename ?: 'empty'));
			error_log('  - page_id=' . ($page_id ?: 'empty'));
			error_log('  - p=' . ($p ?: 'empty'));
			error_log('  - request_path=' . ($request_path ?: 'empty'));
			error_log('  - request_path_clean=' . ($request_path_clean ?: 'empty'));
			error_log('  - is_lang_homepage=' . ($is_lang_homepage ? 'true' : 'false'));
			error_log('  - matched_url_prefix=' . ($matched_url_prefix ?: 'empty'));
			error_log('  - query->is_home=' . ($query->is_home ? 'true' : 'false'));
			error_log('  - query->is_front_page=' . ($query->is_front_page() ? 'true' : 'false'));
			error_log('  - query->is_page=' . ($query->is_page ? 'true' : 'false'));
			error_log('  - query->is_singular=' . ($query->is_singular ? 'true' : 'false'));
			if ($query->is_page) {
				$queried_obj = $query->get_queried_object();
				error_log('  - queried_object=' . ($queried_obj ? (isset($queried_obj->post_name) ? $queried_obj->post_name : 'object without post_name') : 'null'));
			}
		}
		
		// If no post name OR we're on the language homepage (e.g., /fr/ or /es/), 
		// this is the home/blog archive page with language prefix
		// Filter the query to show only translated posts for this language
		if (empty($post_name) || $is_lang_homepage) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('XF Translator DEBUG: Entering homepage logic block');
			}
			
			// Clear query vars that might interfere
			$query->set('name', '');
			$query->set('pagename', '');
			$query->set('page_id', '');
			$query->set('p', '');
			
			// Also check if WordPress is trying to match a page with the language prefix as slug
			// (e.g., a page with slug "fr" or "es") - we need to override that
			if ($query->is_page) {
				$queried_object = $query->get_queried_object();
				if ($queried_object && isset($queried_object->post_name)) {
					if (defined('WP_DEBUG') && WP_DEBUG) {
						error_log('XF Translator DEBUG: Found queried_object with post_name=' . $queried_object->post_name);
					}
					// Check if the page slug matches any language URL prefix
					foreach ($languages as $language) {
						if (empty($language['prefix'])) {
							continue;
						}
						$url_prefix = $this->get_url_prefix_for_language($language);
						if ($url_prefix && $queried_object->post_name === $url_prefix) {
							// This is a page with the same slug as a language prefix, override it
							if (defined('WP_DEBUG') && WP_DEBUG) {
								error_log('XF Translator DEBUG: Overriding page match for slug=' . $url_prefix);
							}
							$query->queried_object = null;
							$query->queried_object_id = null;
							break;
						}
					}
				}
			}
			
			// Set query to show home/blog archive
			$query->is_home = true;
			$query->is_front_page = true;
			$query->is_404 = false;
			$query->is_singular = false;
			$query->is_single = false;
			$query->is_page = false;
			$query->is_archive = false;
			
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('XF Translator DEBUG: After setting query flags:');
				error_log('  - is_home=' . ($query->is_home ? 'true' : 'false'));
				error_log('  - is_front_page=' . ($query->is_front_page() ? 'true' : 'false'));
				error_log('  - is_page=' . ($query->is_page ? 'true' : 'false'));
				error_log('  - is_singular=' . ($query->is_singular ? 'true' : 'false'));
				error_log('XF Translator DEBUG: Calling filter_home_query_by_language with lang_prefix=' . $lang_prefix);
			}
			
			// Filter posts to show only translated posts for this language
			$this->filter_home_query_by_language($query, $lang_prefix);
			return;
		} else {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('XF Translator DEBUG: NOT entering homepage logic - post_name=' . ($post_name ?: 'empty') . ', is_lang_homepage=' . ($is_lang_homepage ? 'true' : 'false'));
			}
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
		
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('XF Translator: After exact slug match, translated_post_id=' . ($translated_post_id ?: 'NOT FOUND') . ' for slug "' . $post_name . '" and language "' . $lang_prefix . '"');
		}
		
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
		
		// If still not found, try finding the original post by slug, then get its translation
		// This handles cases where the translated post has a completely different slug
		if (!$translated_post_id) {
			// First, find the original post by slug
			$original_post_id = $wpdb->get_var($wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
					AND (pm.meta_key = '_xf_translator_original_post_id' OR pm.meta_key = '_api_translator_original_post_id')
				WHERE p.post_name = %s
				AND p.post_status = 'publish'
				AND p.post_type IN ('post', 'page')
				AND p.post_type != 'revision'
				AND pm.post_id IS NULL
				LIMIT 1",
				$post_name
			));
			
			// If we found the original post, get its translation for this language
			if ($original_post_id) {
				$translated_post_id = $wpdb->get_var($wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
						AND pm1.meta_key = '_xf_translator_language' 
						AND pm1.meta_value = %s
					INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
						AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
						AND pm2.meta_value = %d
					WHERE p.post_status = 'publish'
					AND p.post_type IN ('post', 'page')
					AND p.post_type != 'revision'
					LIMIT 1",
					$lang_prefix,
					$original_post_id
				));
				
				if ($translated_post_id) {
					error_log('XF Translator: Found Spanish translation by original post ID. Original: ' . $original_post_id . ', Translated: ' . $translated_post_id . ', Language: ' . $lang_prefix);
				}
			}
		}
		
		if ($translated_post_id) {
			$translated_post = get_post($translated_post_id);
			if ($translated_post) {
				$query->set('p', $translated_post_id);
				$query->set('name', ''); // Clear name query to use ID instead
				$query->is_404 = false; // Prevent 404
				$query->is_singular = true; // Mark as singular
				
				// Set correct query flags based on post type
				if ($translated_post->post_type === 'page') {
					$query->is_page = true;
					$query->is_single = false;
				} else {
					$query->is_single = true;
					$query->is_page = false;
				}
				
				error_log('XF Translator: Successfully found translated post ID ' . $translated_post_id . ' for language ' . $lang_prefix . ' (original slug: ' . $post_name . ')');
			} else {
				error_log('XF Translator: Found translated post ID ' . $translated_post_id . ' but get_post() returned null for language ' . $lang_prefix);
			}
		} else {
			// Log when we can't find a translation
			error_log('XF Translator: Could not find translated post for slug "' . $post_name . '" and language "' . $lang_prefix . '". The page may show English content or 404.');
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

					if ( preg_match( '#^/' . preg_quote( $url_prefix, '#' ) . '(/|$)#i', $path ) ) {
						// Store the original configured prefix (e.g. "fr-CA").
						$lang_prefix = $language['prefix'];
						break;
					}
				}
			}
		}
		
		// Debug logging for language detection (only log once per request to avoid spam)
		static $logged = false;
		if (defined('WP_DEBUG') && WP_DEBUG && !$logged) {
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			$path = parse_url($request_uri, PHP_URL_PATH);
			$query_var = get_query_var('xf_lang_prefix');
			
			// If we detected a prefix but query var is empty, try to set it manually
			if (!empty($lang_prefix) && empty($query_var)) {
				// This might happen if rewrite rules haven't been flushed
				error_log('XF Translator Language Detection WARNING: Detected prefix "' . $lang_prefix . '" from URL but query_var is empty. Rewrite rules may need flushing.');
			}
			
			error_log('XF Translator Language Detection: REQUEST_URI=' . $request_uri . ', path=' . $path . ', detected_prefix=' . ($lang_prefix ?: 'empty') . ', query_var=' . ($query_var ?: 'empty'));
			$logged = true;
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
		
		// Get all post IDs that have translations for this language, ordered by date DESC (latest first)
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
			AND p.post_type != 'revision'
			ORDER BY p.post_date DESC",
			$lang_prefix
		));
		
		if (empty($translated_post_ids)) {
			// No translated posts found, set to show nothing (or show empty result)
			$query->set('post__in', array(0)); // This will return no posts
			return;
		}
		
		// Respect post__not_in if it was set (e.g., to exclude posts already shown in widgets)
		$post_not_in = $query->get('post__not_in');
		if (!empty($post_not_in) && is_array($post_not_in)) {
			// Remove excluded post IDs from the translated post IDs array
			$translated_post_ids = array_diff($translated_post_ids, $post_not_in);
		}
		
		if (!empty($translated_post_ids)) {
			// Filter query to only show translated posts, ordered by date DESC (latest first)
		$query->set('post__in', $translated_post_ids);
			$query->set('orderby', 'date');
			$query->set('order', 'DESC');
		} else {
			// All translated posts were excluded, show nothing
			$query->set('post__in', array(0));
		}
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
		// Only filter on frontend
		if (is_admin()) {
			return;
		}
		
		// Skip if this is the main singular query (handled by filter_translated_post_query)
		// But filter related posts on singular pages (secondary queries)
		// Check both is_singular flag and if name/pagename/p is set (indicating singular)
		if (($query->is_singular || $query->get('name') || $query->get('pagename') || $query->get('p')) && $query->is_main_query()) {
			return;
		}
		
		// Skip if post__in is already set (e.g., by filter_home_query_by_language)
		if ($query->get('post__in')) {
			return;
		}
		
		// Only filter queries that are for posts/pages
		$post_type = $query->get('post_type');
		if (empty($post_type)) {
			$post_type = 'post';
		}
		
		// Only filter post and page queries, skip other post types
		$should_filter = false;
		if (is_array($post_type)) {
			// If it's an array, check if it includes post or page
			if (!empty(array_intersect($post_type, array('post', 'page')))) {
				$should_filter = true;
			}
		} else {
			// Check if it's post, page, or any
			if (in_array($post_type, array('post', 'page', 'any'), true)) {
				$should_filter = true;
			}
		}
		
		if (!$should_filter) {
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
			
			// Get translated post IDs for this language, ordered by date DESC (latest first)
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
				AND p.post_type != 'revision'
				ORDER BY p.post_date DESC",
				$lang_prefix
			));
			
			if (!empty($translated_post_ids)) {
				// Respect post__not_in if it was set (e.g., to exclude posts already shown in widgets)
				$post_not_in = $query->get('post__not_in');
				if (!empty($post_not_in) && is_array($post_not_in)) {
					// Remove excluded post IDs from the translated post IDs array
					$translated_post_ids = array_diff($translated_post_ids, $post_not_in);
				}
			
			if (!empty($translated_post_ids)) {
				$query->set('post__in', $translated_post_ids);
					$query->set('orderby', 'date');
					$query->set('order', 'DESC');
				} else {
					// All translated posts were excluded, show nothing
					$query->set('post__in', array(0));
				}
			} else {
				// No translated posts, show nothing
				$query->set('post__in', array(0));
			}
		}
	}

	/**
	 * Filter query_posts() results to show only posts in the current language
	 * This handles cases where themes use query_posts() which bypasses pre_get_posts
	 *
	 * @param array $posts Array of post objects
	 * @param WP_Query $query Query object
	 * @return array Filtered array of post objects
	 */
	public function filter_query_posts_results($posts, $query) {
		// Only filter on frontend
		if (is_admin()) {
			return $posts;
		}
		
		// Skip if no posts
		if (empty($posts)) {
			return $posts;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		
		// For singular queries on English pages, filter out translated posts even from main query
		// This prevents translated posts from appearing on English post pages
		if (empty($lang_prefix) && $query->is_singular) {
			// On English pages, filter out all translated posts
			global $wpdb;
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
				$filtered_posts = array();
				foreach ($posts as $post) {
					if (!in_array($post->ID, $translated_post_ids)) {
						$filtered_posts[] = $post;
					}
				}
				$removed_count = count($posts) - count($filtered_posts);
				if ($removed_count > 0) {
					error_log('XF Translator: Filtered singular query on English page. Removed ' . $removed_count . ' translated post(s). Original count: ' . count($posts) . ', Filtered count: ' . count($filtered_posts));
				}
				return $filtered_posts;
			}
		}
		
		// For singular queries on language-prefixed pages, only filter if it's NOT the main query
		// The main singular query should show the correct translated post (handled by filter_translated_post_query)
		if ($query->is_singular && $query->is_main_query() && !empty($lang_prefix)) {
			return $posts;
		}
		
		// Filter home/blog archive queries AND related posts on singular pages
		$is_home_or_archive = $query->is_home || $query->is_front_page() || $query->is_archive;
		$is_singular_related = $query->is_singular && !$query->is_main_query();
		
		// If it's not a main query AND not a home/archive query AND not a related post query, skip it (likely a widget)
		if (!$query->is_main_query() && !$is_home_or_archive && !$is_singular_related) {
			return $posts;
		}
		
		// Filter home/blog archive queries OR related posts on singular pages
		if (!$is_home_or_archive && !$is_singular_related) {
			return $posts;
		}
		
		// Get current language prefix from URL (use the method that detects from URL path)
		$lang_prefix = $this->get_current_language_prefix();
		
		// If post__in is already set (by filter_content_by_language), we still need to filter results
		// to respect post__not_in exclusions (posts already shown in widgets)
		$post_in_set = $query->get('post__in');
		$post_not_in = $query->get('post__not_in');
		
		// Debug: Log if we're filtering (only on home page to avoid spam)
		if ($query->is_home || $query->is_front_page()) {
			error_log('XF Translator: Filtering query_posts results. Lang prefix: ' . ($lang_prefix ?: 'empty') . ', Posts count: ' . count($posts) . ', Post IDs: ' . implode(', ', array_map(function($p) { return $p->ID; }, $posts)) . ', post__in: ' . ($post_in_set ? 'set(' . count($post_in_set) . ')' : 'not set') . ', post__not_in: ' . ($post_not_in ? 'set(' . count($post_not_in) . ')' : 'not set'));
		}
		
		// If post__in is set and we have post__not_in, filter out excluded posts
		if (!empty($post_in_set) && is_array($post_in_set) && !empty($post_not_in) && is_array($post_not_in)) {
			$filtered_posts = array();
			foreach ($posts as $post) {
				if (!in_array($post->ID, $post_not_in)) {
					$filtered_posts[] = $post;
				}
			}
			// Re-sort by date DESC after filtering
			if (!empty($filtered_posts)) {
				usort($filtered_posts, function($a, $b) {
					return strtotime($b->post_date) - strtotime($a->post_date);
				});
			}
			if ($query->is_home || $query->is_front_page()) {
				error_log('XF Translator: After filtering post__not_in, posts count: ' . count($filtered_posts));
			}
			return $filtered_posts;
		}
		
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
				$filtered_posts = array();
				foreach ($posts as $post) {
					if (!in_array($post->ID, $translated_post_ids)) {
						$filtered_posts[] = $post;
					}
				}
				return $filtered_posts;
			}
		} else {
			// On language-prefixed URL: Show only translated posts for this language
			// If post__not_in is set (e.g., from query_posts with exclusions), query translated posts directly
			// Note: query_posts() might not preserve post__not_in in the query object, so check global $do_not_duplicate
			global $do_not_duplicate;
			$post_not_in = $query->get('post__not_in');
			if (empty($post_not_in) && !empty($do_not_duplicate) && is_array($do_not_duplicate)) {
				$post_not_in = $do_not_duplicate;
			}
			
			// Only use post__not_in if it's a reasonable number (widgets typically show 6-10 posts)
			// If it has too many posts, it might be accumulating from multiple queries
			// Check for large post__not_in first (even if post__in is set) - handle this before normal logic
			if (!empty($post_not_in) && is_array($post_not_in) && count($post_not_in) > 20) {
				// Too many posts in exclusion list, likely accumulated incorrectly
				// Query all translated posts directly (ignore the exclusion list since it's wrong)
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
					AND p.post_type != 'revision'
					ORDER BY p.post_date DESC",
					$lang_prefix
				));
				
				if (!empty($translated_post_ids)) {
					// Get the first 6 posts from widgets to exclude (limit to reasonable number)
					global $do_not_duplicate;
					$widget_post_ids = array();
					if (!empty($do_not_duplicate) && is_array($do_not_duplicate)) {
						// Limit to first 10 posts (widgets typically show 6)
						$widget_ids = array_slice($do_not_duplicate, 0, 10);
						// Convert to translated IDs if needed
						foreach ($widget_ids as $widget_id) {
							$widget_id = intval($widget_id);
							$post_lang = get_post_meta($widget_id, '_xf_translator_language', true);
							if ($post_lang === $lang_prefix) {
								$widget_post_ids[] = $widget_id;
							} else {
								$translated_id = $this->get_translated_post_id($widget_id, $lang_prefix);
								if ($translated_id) {
									$widget_post_ids[] = $translated_id;
								}
							}
						}
					}
					
					// Remove widget posts from the list
					if (!empty($widget_post_ids)) {
						$translated_post_ids = array_diff($translated_post_ids, $widget_post_ids);
					}
					
					if (!empty($translated_post_ids)) {
						$translated_posts = array();
						foreach ($translated_post_ids as $post_id) {
							$post = get_post($post_id);
							if ($post && $post->post_status === 'publish') {
								$translated_posts[] = $post;
							}
						}
						if ($query->is_home || $query->is_front_page()) {
							error_log('XF Translator: Querying all translated posts (ignoring large exclusion list of ' . count($post_not_in) . '). Found ' . count($translated_posts) . ' posts after excluding ' . count($widget_post_ids) . ' widget posts.');
						}
						return $translated_posts;
					}
				}
				
				if ($query->is_home || $query->is_front_page()) {
					error_log('XF Translator: post__not_in has too many posts (' . count($post_not_in) . '), queried all translated posts directly but got none.');
				}
				// Return empty array if we couldn't get posts (fall through would cause issues)
				return array();
			} else if (!empty($post_not_in) && is_array($post_not_in) && count($post_not_in) <= 20 && empty($post_in_set)) {
				// Convert post IDs to translated versions if needed
				// $do_not_duplicate might contain original English post IDs, but we need translated French IDs
				$post_not_in_translated = array();
				foreach ($post_not_in as $post_id) {
					$post_id = intval($post_id);
					// Check if this is already a translated post for this language
					$post_lang = get_post_meta($post_id, '_xf_translator_language', true);
					if ($post_lang === $lang_prefix) {
						// Already a translated post, use it as-is
						$post_not_in_translated[] = $post_id;
					} else {
						// Might be an original post, try to find its translation
						$translated_id = $this->get_translated_post_id($post_id, $lang_prefix);
						if ($translated_id) {
							$post_not_in_translated[] = $translated_id;
						} else {
							// If no translation found, assume it's already a translated post ID and use it
							$post_not_in_translated[] = $post_id;
						}
					}
				}
				$post_not_in_clean = array_unique(array_map('intval', $post_not_in_translated));
				
				if ($query->is_home || $query->is_front_page()) {
					error_log('XF Translator: Converting post__not_in. Original count: ' . count($post_not_in) . ', Translated count: ' . count($post_not_in_clean) . ', IDs: ' . implode(', ', array_slice($post_not_in_clean, 0, 10)));
				}
				$placeholders = implode(',', array_fill(0, count($post_not_in_clean), '%d'));
				$sql = $wpdb->prepare(
					"SELECT DISTINCT p.ID 
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
						AND pm1.meta_key = '_xf_translator_language' 
						AND pm1.meta_value = %s
					INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
						AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
					WHERE p.post_status = 'publish'
					AND p.post_type IN ('post', 'page')
					AND p.post_type != 'revision'
					AND p.ID NOT IN ($placeholders)
					ORDER BY p.post_date DESC",
					array_merge(array($lang_prefix), $post_not_in_clean)
				);
				$translated_post_ids = $wpdb->get_col($sql);
				
				if (!empty($translated_post_ids)) {
					$translated_posts = array();
					foreach ($translated_post_ids as $post_id) {
						$post = get_post($post_id);
						if ($post && $post->post_status === 'publish') {
							$translated_posts[] = $post;
						}
					}
					if ($query->is_home || $query->is_front_page()) {
						error_log('XF Translator: Querying translated posts directly. Found ' . count($translated_posts) . ' posts after excluding ' . count($post_not_in) . ' posts.');
					}
					return $translated_posts;
				} else {
					if ($query->is_home || $query->is_front_page()) {
						error_log('XF Translator: No translated posts found after excluding ' . count($post_not_in_clean) . ' posts. Falling back to normal query.');
					}
					// Fall through to normal translation mapping logic
				}
			}
			
			// First, get a mapping of original post IDs to translated post IDs, ordered by date DESC
			$original_to_translated = $wpdb->get_results($wpdb->prepare(
				"SELECT pm2.meta_value as original_id, p.ID as translated_id
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
					AND pm1.meta_key = '_xf_translator_language' 
					AND pm1.meta_value = %s
				INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
					AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
				WHERE p.post_status = 'publish'
				AND p.post_type IN ('post', 'page')
				AND p.post_type != 'revision'
				ORDER BY p.post_date DESC",
				$lang_prefix
			), OBJECT_K);
			
			// Build mapping array: original_id => translated_id
			$translation_map = array();
			foreach ($original_to_translated as $row) {
				$translation_map[intval($row->original_id)] = intval($row->translated_id);
			}
			
			// Debug: Log translation map with details
			if ($query->is_home || $query->is_front_page() || $query->is_singular) {
				error_log('XF Translator: Found ' . count($translation_map) . ' translated posts for language: ' . $lang_prefix);
				if (!empty($translation_map)) {
					$map_details = array();
					foreach ($translation_map as $orig_id => $trans_id) {
						$map_details[] = "orig:$orig_id=>trans:$trans_id";
					}
					error_log('XF Translator: Translation map details: ' . implode(', ', $map_details));
				}
			}
			
			if (!empty($translation_map)) {
				// Create reverse map: translated_id => original_id for quick lookup
				$translated_ids = array_values($translation_map);
				
				// Get post__not_in from query to respect exclusions (e.g., posts already shown in widgets)
				// Note: query_posts() might not preserve post__not_in in the query object, so check global $do_not_duplicate
				global $do_not_duplicate;
				$post_not_in = $query->get('post__not_in');
				if (empty($post_not_in) && !empty($do_not_duplicate) && is_array($do_not_duplicate)) {
					// Limit to first 10 posts (widgets typically show 6 posts: 2 large + 4 small)
					// If $do_not_duplicate has more, it's likely accumulated incorrectly
					if (count($do_not_duplicate) > 10) {
						$post_not_in = array_slice($do_not_duplicate, 0, 10);
						if ($query->is_home || $query->is_front_page()) {
							error_log('XF Translator: Limiting $do_not_duplicate from ' . count($do_not_duplicate) . ' to ' . count($post_not_in) . ' posts.');
						}
					} else {
						$post_not_in = $do_not_duplicate;
					}
				}
				if (empty($post_not_in) || !is_array($post_not_in)) {
					$post_not_in = array();
				}
				
				$filtered_posts = array();
				foreach ($posts as $post) {
					// Check if this post is already a translated post (check post meta directly)
					$post_lang = get_post_meta($post->ID, '_xf_translator_language', true);
					$original_post_id = get_post_meta($post->ID, '_xf_translator_original_post_id', true);
					if (!$original_post_id) {
						$original_post_id = get_post_meta($post->ID, '_api_translator_original_post_id', true);
					}
					
					if ($post_lang === $lang_prefix) {
						// This is already a translated post for this language, keep it only if not excluded
						if (!in_array($post->ID, $post_not_in)) {
						$filtered_posts[] = $post;
						if ($query->is_home || $query->is_front_page() || $query->is_singular) {
							error_log('XF Translator: Kept post ' . $post->ID . ' - already translated for language ' . $lang_prefix);
							}
						}
					} elseif (isset($translation_map[$post->ID])) {
						// This is an original post, get its translated version
						$translated_id = $translation_map[$post->ID];
						// Only add if not excluded
						if (!in_array($translated_id, $post_not_in)) {
						$translated_post = get_post($translated_id);
						if ($translated_post && $translated_post->post_status === 'publish') {
							$filtered_posts[] = $translated_post;
							if ($query->is_home || $query->is_front_page() || $query->is_singular) {
								error_log('XF Translator: Replaced original post ' . $post->ID . ' with translated post ' . $translated_id . ' for language ' . $lang_prefix);
							}
						} else {
							if ($query->is_home || $query->is_front_page() || $query->is_singular) {
								error_log('XF Translator: Translation ' . $translated_id . ' for original post ' . $post->ID . ' not found or not published');
								}
							}
						}
					} elseif ($original_post_id && isset($translation_map[$original_post_id])) {
						// This post is a translation for a different language, but we found the original
						// Get the translation for the requested language
						$translated_id = $translation_map[$original_post_id];
						// Only add if not excluded
						if (!in_array($translated_id, $post_not_in)) {
						$translated_post = get_post($translated_id);
						if ($translated_post && $translated_post->post_status === 'publish') {
							$filtered_posts[] = $translated_post;
							if ($query->is_home || $query->is_front_page() || $query->is_singular) {
								error_log('XF Translator: Replaced post ' . $post->ID . ' (original: ' . $original_post_id . ') with translated post ' . $translated_id . ' for language ' . $lang_prefix);
								}
							}
						}
					} else {
						// Post is neither translated nor has a translation
						if ($query->is_home || $query->is_front_page() || $query->is_singular) {
							$map_keys = array_keys($translation_map);
							error_log('XF Translator: Skipping post ' . $post->ID . ' - no translation found for language ' . $lang_prefix . ' (post_lang: ' . ($post_lang ?: 'none') . ', original_post_id: ' . ($original_post_id ?: 'none') . ', in_map: ' . (isset($translation_map[$post->ID]) ? 'yes' : 'no') . ', map_has_original: ' . ($original_post_id && isset($translation_map[$original_post_id]) ? 'yes' : 'no') . ', map_keys: ' . implode(',', array_slice($map_keys, 0, 5)) . ')');
						}
					}
				}
				
				// Sort filtered posts by date DESC (latest first) to ensure correct ordering
				usort($filtered_posts, function($a, $b) {
					return strtotime($b->post_date) - strtotime($a->post_date);
				});
				
				// Debug: Log how many posts matched
				if ($query->is_home || $query->is_front_page() || $query->is_singular) {
					error_log('XF Translator: Filtered to ' . count($filtered_posts) . ' posts out of ' . count($posts) . ' original posts. Translation map has ' . count($translation_map) . ' entries.');
				}
				return $filtered_posts;
			} else {
				// No translated posts found in database for this language
				// Return empty to avoid showing English posts on French page
				if ($query->is_home || $query->is_front_page()) {
					error_log('XF Translator: No translated posts found for language: ' . $lang_prefix . '. Returning empty array.');
				}
				return array();
			}
		}
		
		return $posts;
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
		
		// Debug logging for description field
		static $logged_meta_keys = array();
		if (($meta_key === 'description' || $meta_key === 'user_description') && !isset($logged_meta_keys[$user_id . '_' . $meta_key])) {
			error_log('XF Translator User Meta: filter_get_user_meta called - meta_key: "' . $meta_key . '", user_id: ' . $user_id);
			$logged_meta_keys[$user_id . '_' . $meta_key] = true;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		if (empty($lang_prefix)) {
			if (($meta_key === 'description' || $meta_key === 'user_description') && isset($logged_meta_keys[$user_id . '_' . $meta_key])) {
				error_log('XF Translator User Meta: No language prefix in filter_get_user_meta');
			}
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
	 * Filter ACF field values to convert post IDs to translated versions on frontend
	 * This ensures "you may like" and other relationship fields show translated posts
	 *
	 * @param mixed $value The field value
	 * @param int $post_id The post ID
	 * @param array $field The ACF field array
	 * @return mixed Converted value with translated post IDs
	 */
	public function filter_acf_load_value($value, $post_id, $field) {
		// Only filter on frontend
		if (is_admin()) {
			return $value;
		}
		
		// Get field name early for debug logging
		$field_name = isset($field['name']) ? $field['name'] : '';
		
		// Also try to get field name from field key if name is not available
		if (empty($field_name) && isset($field['key']) && function_exists('acf_get_field')) {
			$field_obj = acf_get_field($field['key']);
			if ($field_obj && isset($field_obj['name'])) {
				$field_name = $field_obj['name'];
			}
		}
		
		// Get current language prefix early to determine if we need to process
		$lang_prefix = $this->get_current_language_prefix();
		
		// Validate that the language prefix is actually configured (security check)
		if (!empty($lang_prefix)) {
			$languages = $this->settings->get('languages', array());
			$is_valid_prefix = false;
			foreach ($languages as $language) {
				if (isset($language['prefix']) && strtolower($language['prefix']) === strtolower($lang_prefix)) {
					$is_valid_prefix = true;
					break;
				}
			}
			if (!$is_valid_prefix) {
				error_log('XF Translator ACF: Invalid language prefix detected: ' . $lang_prefix . ' - not in configured languages. Falling back to English.');
				$lang_prefix = ''; // Reset to English/default
			}
		}
		
		// Debug logging for sbposts__content to see if filter is called
		if ($field_name === 'sbposts__content') {
			error_log('XF Translator ACF: filter_acf_load_value called for sbposts__content - post_id: ' . $post_id . ', value empty: ' . (empty($value) ? 'YES' : 'NO') . ', value type: ' . gettype($value) . ', lang_prefix: ' . ($lang_prefix ?: 'empty'));
		}
		
		// If no language prefix, we're on English/default - no conversion needed
		// But still return early if value is empty to avoid unnecessary processing
		if (empty($lang_prefix)) {
			if ($field_name === 'sbposts__content') {
				error_log('XF Translator ACF: sbposts__content - no language prefix, returning original value');
			}
			return $value;
		}
		
		// On translated pages, don't return early if value is empty
		// We need to check if we should load from original post first
		// Only skip if value is empty AND it's not a field we care about
		$strict_filter_fields = array(
			'related_posts',
			'yml__select_posts',
			'you_may_like',
			'recommended_posts',
			'sbposts__content',
			'select_posts',
		);
		
		// For post fields (not options), ensure we're loading from the correct post
		// When on a translated page, if post_id is the original post, get the translated post's field
		if (is_numeric($post_id) && !empty($lang_prefix) && !in_array($field_name, $strict_filter_fields, true)) {
			$current_post_id = (int) $post_id;
			$original_post_id = $this->get_original_post_id($current_post_id);
			
			// If current post is NOT a translated post (no original_post_id meta), but we're on a translated page,
			// this means we're viewing the original post's page but the URL has a language prefix
			// OR the filter was called with original post ID when it should use translated post ID
			if (!$original_post_id) {
				// This is likely the original post - find the translated version
				$translated_post_id = $this->get_translated_post_id($current_post_id, $lang_prefix);
				if ($translated_post_id && $translated_post_id !== $current_post_id && function_exists('get_field')) {
					// Check if translated post has this field (it should, as fields are copied during translation)
					// Temporarily remove filter to avoid recursion
					remove_filter('acf/load_value', array($this, 'filter_acf_load_value'), 10);
					$translated_value = get_field($field_name, $translated_post_id);
					add_filter('acf/load_value', array($this, 'filter_acf_load_value'), 10, 3);
					
					if ($translated_value !== null && $translated_value !== false && (!empty($translated_value) || is_numeric($translated_value))) {
						error_log('XF Translator ACF: Loaded field "' . $field_name . '" from translated post ' . $translated_post_id . ' (was called with original post ' . $current_post_id . ')');
						return $translated_value;
					}
				}
			}
			// If current post IS a translated post, the value should already be correct (from the translated post)
			// But if it's empty, it means the field wasn't copied - return empty (don't fallback to original)
		}
		
		// If value is empty and it's not a field we need to process, return early
		if (empty($value) && !in_array($field_name, $strict_filter_fields, true)) {
			if ($field_name === 'sbposts__content') {
				error_log('XF Translator ACF: sbposts__content value is empty and not in strict filter fields, returning early');
			}
			return $value;
		}
		
		// Check if this is an ACF options page (post_id will be a string like 'option', 'options', 'footer-options', etc.)
		if (is_string($post_id) && !is_numeric($post_id)) {
			// This is an ACF options page field
			$field_key = isset($field['name']) ? $field['name'] : '';
			
			// If field name not available, try to get it from field key
			if (empty($field_key) && isset($field['key']) && function_exists('acf_get_field')) {
				$field_obj = acf_get_field($field['key']);
				if ($field_obj && isset($field_obj['name'])) {
					$field_key = $field_obj['name'];
				}
			}
			
			// Debug logging
			error_log('XF Translator ACF Options: Filter called - post_id: ' . $post_id . ', field_key: ' . $field_key . ', lang_prefix: ' . $lang_prefix . ', field array keys: ' . (is_array($field) ? implode(', ', array_keys($field)) : 'not array'));
			
			if (!empty($field_key)) {
				// Normalize option name
				$option_name = $post_id;
				$acf_option_key = ($option_name === 'option' || $option_name === '') ? 'options' : $option_name;
				
				// Try to get translated value from options table
				// Try multiple key formats to handle different storage scenarios
				$possible_keys = array(
					'_xf_translator_acf_options_' . $acf_option_key . '_' . $field_key . '_' . $lang_prefix,
					'_xf_translator_acf_options_option_' . $field_key . '_' . $lang_prefix, // Alternative format
					'_xf_translator_acf_options_' . $field_key . '_' . $lang_prefix, // Without options page name
				);
				
				// Also try with different option page variations
				if ($acf_option_key === 'options') {
					$possible_keys[] = '_xf_translator_acf_options_options_' . $field_key . '_' . $lang_prefix;
					$possible_keys[] = '_xf_translator_acf_options_' . $field_key . '_' . $lang_prefix;
				}
				
				$translated_value = '';
				foreach ($possible_keys as $key_to_try) {
					$translated_value = get_option($key_to_try, '');
					if (!empty($translated_value)) {
						error_log('XF Translator ACF Options: Found translation with key: ' . $key_to_try);
						break;
					}
				}
				
				if (empty($translated_value)) {
					error_log('XF Translator ACF Options: Looking for translation with keys: ' . implode(', ', $possible_keys));
				}
				
				if (!empty($translated_value)) {
					error_log('XF Translator ACF Options: Found translation in options table, length: ' . strlen($translated_value));
					return $translated_value;
				}
				
				// If not found in options table, try to get from ACF directly (in case it was saved via update_field)
				if (empty($translated_value) && function_exists('get_field')) {
					// Try with the normalized key
					$acf_lookup_key = $acf_option_key . '_' . $lang_prefix;
					error_log('XF Translator ACF Options: Trying ACF direct lookup with key: ' . $acf_lookup_key);
					$translated_value = get_field($field_key, $acf_lookup_key);
					
					// Also try with 'option' instead of 'options'
					if (empty($translated_value) && $acf_option_key === 'options') {
						$acf_lookup_key_alt = 'option_' . $lang_prefix;
						error_log('XF Translator ACF Options: Trying ACF direct lookup with alternative key: ' . $acf_lookup_key_alt);
						$translated_value = get_field($field_key, $acf_lookup_key_alt);
					}
					
					if (!empty($translated_value)) {
						error_log('XF Translator ACF Options: Found translation in ACF, length: ' . strlen($translated_value));
						return $translated_value;
					}
				}
				
				// Debug: Check what options exist in the database for THIS specific language
				global $wpdb;
				// First, check for options matching the exact language prefix we're looking for
				$lang_specific_options = $wpdb->get_results($wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name LIKE %s",
					'_xf_translator_acf_options_%' . $field_key . '%',
					'%_' . $wpdb->esc_like($lang_prefix)
				));
				if (!empty($lang_specific_options)) {
					$lang_option_names = array_map(function($o) { return $o->option_name; }, $lang_specific_options);
					error_log('XF Translator ACF Options: Found ' . count($lang_specific_options) . ' options for field "' . $field_key . '" and language "' . $lang_prefix . '": ' . implode(', ', $lang_option_names));
				} else {
					error_log('XF Translator ACF Options: No options found for field "' . $field_key . '" and language "' . $lang_prefix . '"');
					// Check what languages DO have translations for this field (for debugging)
					$all_field_options = $wpdb->get_results($wpdb->prepare(
						"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
						'_xf_translator_acf_options_%' . $field_key . '%'
					));
					if (!empty($all_field_options)) {
						$all_option_names = array_map(function($o) { return $o->option_name; }, $all_field_options);
						error_log('XF Translator ACF Options: Found options for field "' . $field_key . '" in other languages: ' . implode(', ', $all_option_names));
						// Extract language codes from option names to show what languages are available
						$available_langs = array();
						foreach ($all_option_names as $opt_name) {
							if (preg_match('/_([a-z]{2}(?:-[A-Z]{2})?)$/i', $opt_name, $matches)) {
								$available_langs[] = $matches[1];
							}
						}
						if (!empty($available_langs)) {
							$available_langs = array_unique($available_langs);
							error_log('XF Translator ACF Options: Available languages for field "' . $field_key . '": ' . implode(', ', $available_langs) . ' (requested: ' . $lang_prefix . ')');
						}
					}
				}
				
				error_log('XF Translator ACF Options: No translation found for field: ' . $field_key . ' (options page: ' . $acf_option_key . ', lang: ' . $lang_prefix . ')');
				error_log('XF Translator ACF Options: Expected key format: _xf_translator_acf_options_' . $acf_option_key . '_' . $field_key . '_' . $lang_prefix);
			} else {
				error_log('XF Translator ACF Options: Field key is empty, field array: ' . print_r($field, true));
			}
			
			// If no translation found for options page, return original value
			return $value;
		}
		
		// For regular post fields, handle ACF relationship fields
		// Only filter if we're on a translated page (check URL language prefix)
		// This ensures sidebar posts and other ACF fields are filtered on translated pages
		// even if the global $post context is not set correctly
		if (empty($lang_prefix)) {
			// On English/default page, don't filter ACF fields
			return $value;
		}

		// Fields that should be strictly filtered (only show translated posts, no English fallback)
		// (Already defined above, but keeping for clarity)

		// Special handling for repeater fields - ACF stores them differently
		// For sbposts__content, we need to load the full repeater structure from post meta
		if ($field_name === 'sbposts__content') {
			// Get the current post ID - could be from get_queried_object_id() in shortcode
			$current_post_id = is_numeric($post_id) ? (int) $post_id : get_queried_object_id();
			if (!$current_post_id && isset($GLOBALS['post']) && $GLOBALS['post']) {
				$current_post_id = $GLOBALS['post']->ID;
			}
			
			error_log('XF Translator ACF: sbposts__content special handling - post_id: ' . $current_post_id . ', value type: ' . gettype($value) . ', is_array: ' . (is_array($value) ? 'YES' : 'NO'));
			
			// Check if value is just a count (ACF repeater format issue)
			// Value can come as string, number, or array with count
			$is_count_only = false;
			$needs_reload = false;
			
			if (is_array($value)) {
				$is_count_only = (count($value) === 1 && isset($value[0]) && is_numeric($value[0]) && !is_array($value[0]));
			} elseif (is_numeric($value) || (is_string($value) && is_numeric($value))) {
				// Value is just a number (count)
				$is_count_only = true;
				$needs_reload = true;
			} elseif (empty($value)) {
				$needs_reload = true;
			}
			
			error_log('XF Translator ACF: sbposts__content - is_count_only: ' . ($is_count_only ? 'YES' : 'NO') . ', needs_reload: ' . ($needs_reload ? 'YES' : 'NO') . ', empty(value): ' . (empty($value) ? 'YES' : 'NO'));
			
			if ($needs_reload || $is_count_only) {
				error_log('XF Translator ACF: sbposts__content - Need to load from post meta or original post');
				// Try to load from original post if this is a translated post
				$original_post_id = $this->get_original_post_id($current_post_id);
				
				if ($original_post_id && $original_post_id !== $current_post_id) {
					// Load repeater field properly using ACF's get_field with format_value
					if (function_exists('get_field')) {
						remove_filter('acf/load_value', array($this, 'filter_acf_load_value'), 10);
						$original_value = get_field($field_name, $original_post_id, true); // true = format value
						add_filter('acf/load_value', array($this, 'filter_acf_load_value'), 10, 3);
						
						if (!empty($original_value) && is_array($original_value)) {
							// Check if it's a proper repeater structure
							$has_proper_structure = false;
							foreach ($original_value as $row) {
								if (is_array($row) && isset($row['select_posts'])) {
									$has_proper_structure = true;
									break;
								}
							}
							
							if ($has_proper_structure) {
								$value = $original_value;
								error_log('XF Translator ACF: Loaded original sbposts__content from post ' . $original_post_id . ' (current: ' . $current_post_id . ') - rows: ' . count($value));
							} else {
								error_log('XF Translator ACF: Original value loaded but doesn\'t have proper repeater structure');
							}
						}
					}
				}
			}
			
			// If still empty or just a count, try loading directly from post meta
			$is_count_only = (is_array($value) && count($value) === 1 && isset($value[0]) && is_numeric($value[0]) && !is_array($value[0]));
			if (empty($value) || $is_count_only) {
				error_log('XF Translator ACF: sbposts__content - Attempting to load from post meta directly');
				
				// Get the field key - ACF stores repeater sub-fields using field KEY, not name
				$field_key = isset($field['key']) ? $field['key'] : '';
				if (empty($field_key) && function_exists('acf_get_field')) {
					$field_obj = acf_get_field($field_name);
					if ($field_obj && isset($field_obj['key'])) {
						$field_key = $field_obj['key'];
					}
				}
				
				error_log('XF Translator ACF: sbposts__content - field_key: ' . ($field_key ?: 'NOT FOUND'));
				
				// Try both field name and field key for the repeater count
				$repeater_count = get_post_meta($current_post_id, $field_name, true);
				if (empty($repeater_count) || !is_numeric($repeater_count)) {
					$repeater_count = get_post_meta($current_post_id, $field_key, true);
				}
				
				// If still empty, try original post
				if (empty($repeater_count) || !is_numeric($repeater_count)) {
					$original_post_id = $this->get_original_post_id($current_post_id);
					if ($original_post_id && $original_post_id !== $current_post_id) {
						$repeater_count = get_post_meta($original_post_id, $field_name, true);
						if (empty($repeater_count) || !is_numeric($repeater_count)) {
							$repeater_count = get_post_meta($original_post_id, $field_key, true);
						}
					}
				}
				
				error_log('XF Translator ACF: sbposts__content - repeater_count: ' . ($repeater_count ?: 'NOT FOUND'));
				
				if ($repeater_count && is_numeric($repeater_count) && $repeater_count > 0) {
					$repeater_rows = array();
					$source_post_id = $current_post_id;
					
					// Check if we need to load from original - try both field name and key patterns
					$test_meta_name = $field_name . '_0_select_posts';
					$test_meta_key = $field_key . '_0_select_posts';
					$has_data = !empty(get_post_meta($current_post_id, $test_meta_name, true)) || 
								!empty(get_post_meta($current_post_id, $test_meta_key, true));
					
					if (!$has_data) {
						$original_post_id = $this->get_original_post_id($current_post_id);
						if ($original_post_id && $original_post_id !== $current_post_id) {
							$source_post_id = $original_post_id;
							error_log('XF Translator ACF: sbposts__content - Using original post ' . $original_post_id . ' as source');
						}
					}
					
					// Get the select_posts sub-field key
					$select_posts_field_key = '';
					if ($field_key && function_exists('acf_get_field')) {
						$repeater_field = acf_get_field($field_key);
						if ($repeater_field && isset($repeater_field['sub_fields'])) {
							foreach ($repeater_field['sub_fields'] as $sub_field) {
								if (isset($sub_field['name']) && $sub_field['name'] === 'select_posts') {
									$select_posts_field_key = $sub_field['key'];
									break;
								}
							}
						}
					}
					
					error_log('XF Translator ACF: sbposts__content - select_posts_field_key: ' . ($select_posts_field_key ?: 'NOT FOUND'));
					
					for ($i = 0; $i < $repeater_count; $i++) {
						// Try multiple meta key patterns
						$select_posts = null;
						
						// Pattern 1: field_name_row_index_select_posts
						$meta_key1 = $field_name . '_' . $i . '_select_posts';
						$select_posts = get_post_meta($source_post_id, $meta_key1, true);
						
						// Pattern 2: field_key_row_index_select_posts_field_key
						if (empty($select_posts) && $field_key && $select_posts_field_key) {
							$meta_key2 = $field_key . '_' . $i . '_' . $select_posts_field_key;
							$select_posts = get_post_meta($source_post_id, $meta_key2, true);
						}
						
						// Pattern 3: field_key_row_index_select_posts (field name)
						if (empty($select_posts) && $field_key) {
							$meta_key3 = $field_key . '_' . $i . '_select_posts';
							$select_posts = get_post_meta($source_post_id, $meta_key3, true);
						}
						
						if (!empty($select_posts)) {
							// Ensure it's an array
							if (!is_array($select_posts)) {
								$select_posts = array($select_posts);
							}
							$repeater_rows[] = array('select_posts' => $select_posts);
							error_log('XF Translator ACF: sbposts__content - Row ' . $i . ' loaded with ' . count($select_posts) . ' posts: ' . implode(', ', $select_posts));
						} else {
							error_log('XF Translator ACF: sbposts__content - Row ' . $i . ' - No select_posts found (tried: ' . $meta_key1 . ($field_key ? ', ' . $meta_key3 : '') . ')');
						}
					}
					
					if (!empty($repeater_rows)) {
						$value = $repeater_rows;
						error_log('XF Translator ACF: Loaded sbposts__content from post meta - source_post_id: ' . $source_post_id . ', rows: ' . count($repeater_rows));
					} else {
						error_log('XF Translator ACF: sbposts__content - No repeater rows loaded from post meta');
					}
				} else {
					error_log('XF Translator ACF: sbposts__content - Invalid repeater_count: ' . ($repeater_count ?: 'empty'));
				}
			}
		} elseif (in_array($field_name, $strict_filter_fields, true) && empty($value)) {
			// For other strict filter fields, try loading from original post
			$current_post_id = is_numeric($post_id) ? (int) $post_id : get_queried_object_id();
			if (!$current_post_id && isset($GLOBALS['post']) && $GLOBALS['post']) {
				$current_post_id = $GLOBALS['post']->ID;
			}
			
			$original_post_id = $this->get_original_post_id($current_post_id);
			
			if ($original_post_id && $original_post_id !== $current_post_id) {
				if (function_exists('get_field')) {
					remove_filter('acf/load_value', array($this, 'filter_acf_load_value'), 10);
					$original_value = get_field($field_name, $original_post_id);
					add_filter('acf/load_value', array($this, 'filter_acf_load_value'), 10, 3);
					if (!empty($original_value)) {
						$value = $original_value;
						error_log('XF Translator ACF: Loaded original value for field "' . $field_name . '" from post ' . $original_post_id);
					}
				}
			}
		}

		// For these fields, strictly filter - only show translated posts
		// Note: sbposts__content is a repeater field containing select_posts arrays
		// The convert_post_ids_to_translated function handles nested arrays recursively
		if (in_array($field_name, $strict_filter_fields, true)) {
			// For sbposts__content, check if value is just a count AFTER ACF processing
			if ($field_name === 'sbposts__content') {
				// Ensure value is an array first to check properly
				$temp_value = is_array($value) ? $value : (empty($value) ? array() : (array) $value);
				
				// Check if it's just a count (array with single numeric value)
				$is_count_format = (is_array($temp_value) && count($temp_value) === 1 && isset($temp_value[0]) && is_numeric($temp_value[0]) && !is_array($temp_value[0]));
				
				if ($is_count_format) {
					error_log('XF Translator ACF: sbposts__content detected as count format AFTER ACF processing - loading from post meta');
					
					// Get the current post ID
					$current_post_id = is_numeric($post_id) ? (int) $post_id : get_queried_object_id();
					if (!$current_post_id && isset($GLOBALS['post']) && $GLOBALS['post']) {
						$current_post_id = $GLOBALS['post']->ID;
					}
					
					// Get the field key
					$field_key = isset($field['key']) ? $field['key'] : '';
					if (empty($field_key) && function_exists('acf_get_field')) {
						$field_obj = acf_get_field($field_name);
						if ($field_obj && isset($field_obj['key'])) {
							$field_key = $field_obj['key'];
						}
					}
					
					// Get repeater count (use the numeric value from array)
					$repeater_count = (int) $temp_value[0];
					
					// Try to load from current post first, then original
					$source_post_id = $current_post_id;
					$original_post_id = $this->get_original_post_id($current_post_id);
					
					// Check if current post has the data
					$test_meta = get_post_meta($current_post_id, $field_name . '_0_select_posts', true);
					if (empty($test_meta) && $field_key) {
						$test_meta = get_post_meta($current_post_id, $field_key . '_0_select_posts', true);
					}
					
					if (empty($test_meta) && $original_post_id && $original_post_id !== $current_post_id) {
						$source_post_id = $original_post_id;
						error_log('XF Translator ACF: sbposts__content - Using original post ' . $original_post_id . ' as source');
					}
					
					// Load repeater rows
					$repeater_rows = array();
					for ($i = 0; $i < $repeater_count; $i++) {
						$select_posts = null;
						
						// Try field name pattern
						$meta_key1 = $field_name . '_' . $i . '_select_posts';
						$select_posts = get_post_meta($source_post_id, $meta_key1, true);
						
						// Try field key pattern
						if (empty($select_posts) && $field_key) {
							$meta_key2 = $field_key . '_' . $i . '_select_posts';
							$select_posts = get_post_meta($source_post_id, $meta_key2, true);
						}
						
						if (!empty($select_posts)) {
							if (!is_array($select_posts)) {
								$select_posts = array($select_posts);
							}
							$repeater_rows[] = array('select_posts' => $select_posts);
							error_log('XF Translator ACF: sbposts__content - Row ' . $i . ' loaded with ' . count($select_posts) . ' posts: ' . implode(', ', $select_posts));
						}
					}
					
					if (!empty($repeater_rows)) {
						$value = $repeater_rows;
						error_log('XF Translator ACF: sbposts__content - Successfully loaded ' . count($repeater_rows) . ' rows from post meta');
					} else {
						error_log('XF Translator ACF: sbposts__content - Failed to load rows from post meta (count: ' . $repeater_count . ', source_post_id: ' . $source_post_id . ')');
					}
				}
			}
			
			// Ensure value is an array to avoid foreach warnings in theme
			if (!is_array($value)) {
				$value = empty($value) ? array() : (array) $value;
			}

			// Enhanced debug logging for sidebar posts
			if ($field_name === 'sbposts__content') {
				error_log('XF Translator ACF: Filtering sbposts__content - post_id: ' . $post_id . ', lang: ' . $lang_prefix);
				error_log('XF Translator ACF: sbposts__content original structure - count: ' . count($value) . ', type: ' . gettype($value));
				if (is_array($value) && !empty($value)) {
					error_log('XF Translator ACF: sbposts__content - Full value structure: ' . print_r($value, true));
					foreach ($value as $idx => $row) {
						error_log('XF Translator ACF: sbposts__content row ' . $idx . ' - row type: ' . gettype($row) . ', is_array: ' . (is_array($row) ? 'YES' : 'NO'));
						if (is_array($row)) {
							error_log('XF Translator ACF: sbposts__content row ' . $idx . ' - keys: ' . implode(', ', array_keys($row)));
							if (isset($row['select_posts'])) {
								$select_posts = $row['select_posts'];
								// Extract IDs for logging
								$ids_for_log = array();
								if (is_array($select_posts)) {
									foreach ($select_posts as $sp_item) {
										if (is_object($sp_item) && isset($sp_item->ID)) {
											$ids_for_log[] = $sp_item->ID;
										} elseif (is_numeric($sp_item)) {
											$ids_for_log[] = $sp_item;
										}
									}
								}
								error_log('XF Translator ACF: sbposts__content row ' . $idx . ' - select_posts: ' . (is_array($select_posts) ? 'array(' . count($select_posts) . ')' : gettype($select_posts)) . ' - IDs: ' . (is_array($select_posts) ? implode(', ', $ids_for_log) : 'N/A'));
							} else {
								error_log('XF Translator ACF: sbposts__content row ' . $idx . ' - NO select_posts key, full row: ' . print_r($row, true));
							}
						} else {
							error_log('XF Translator ACF: sbposts__content row ' . $idx . ' - NOT an array, value: ' . print_r($row, true));
						}
					}
				}
			}

			// Use strict filtering - exclude untranslated posts
			// But first, log what we're about to convert
			if ($field_name === 'sbposts__content') {
				$value_before_conversion = $value;
				error_log('XF Translator ACF: About to convert sbposts__content - value type: ' . gettype($value) . ', is_array: ' . (is_array($value) ? 'YES (count: ' . count($value) . ')' : 'NO'));
			}
			
			$converted_value = $this->convert_post_ids_to_translated($value, $lang_prefix, 0, true);
			
			// If conversion resulted in empty array but we had data, log a warning
			if ($field_name === 'sbposts__content' && empty($converted_value) && !empty($value)) {
				error_log('XF Translator ACF: WARNING - sbposts__content conversion resulted in empty array! Original had ' . count($value) . ' rows.');
				// Try to see if any of the original posts have translations
				if (is_array($value)) {
					foreach ($value as $row_idx => $row) {
						if (is_array($row) && isset($row['select_posts'])) {
							$select_posts = is_array($row['select_posts']) ? $row['select_posts'] : array($row['select_posts']);
							error_log('XF Translator ACF: Checking translations for row ' . $row_idx . ' with ' . count($select_posts) . ' posts');
							foreach ($select_posts as $post_id_or_obj) {
								$check_id = is_object($post_id_or_obj) && isset($post_id_or_obj->ID) ? $post_id_or_obj->ID : (is_numeric($post_id_or_obj) ? $post_id_or_obj : 0);
								if ($check_id) {
									$trans_id = $this->get_translated_post_id($check_id, $lang_prefix);
									$post_status = get_post_status($check_id);
									$trans_status = $trans_id ? get_post_status($trans_id) : 'N/A';
									error_log('XF Translator ACF: Row ' . $row_idx . ' - Post ' . $check_id . ' (status: ' . $post_status . ') translation: ' . ($trans_id ? $trans_id . ' (status: ' . $trans_status . ')' : 'NONE'));
								}
							}
						} else {
							error_log('XF Translator ACF: Row ' . $row_idx . ' - NOT an array or missing select_posts key. Row type: ' . gettype($row));
						}
					}
				}
			}
			
			// Also check if converted_value has rows but they're empty
			if ($field_name === 'sbposts__content' && !empty($converted_value) && is_array($converted_value)) {
				$empty_rows = 0;
				foreach ($converted_value as $row_idx => $row) {
					if (is_array($row) && isset($row['select_posts'])) {
						if (empty($row['select_posts']) || (is_array($row['select_posts']) && count($row['select_posts']) === 0)) {
							$empty_rows++;
						}
					}
				}
				if ($empty_rows > 0) {
					error_log('XF Translator ACF: WARNING - sbposts__content has ' . count($converted_value) . ' rows but ' . $empty_rows . ' rows have empty select_posts arrays!');
				}
			}
			
			// For sbposts__content (repeater field), ensure select_posts arrays contain IDs, not objects
			// This is important because the shortcode expects IDs and casts them to int
			if ($field_name === 'sbposts__content' && is_array($converted_value)) {
				error_log('XF Translator ACF: Normalizing sbposts__content - converted_value count: ' . count($converted_value));
				foreach ($converted_value as $idx => $row) {
					error_log('XF Translator ACF: Processing row ' . $idx . ' - is_array: ' . (is_array($row) ? 'YES' : 'NO') . ', has select_posts: ' . (is_array($row) && isset($row['select_posts']) ? 'YES' : 'NO'));
					
					if (is_array($row) && isset($row['select_posts'])) {
						$select_posts = $row['select_posts'];
						error_log('XF Translator ACF: Row ' . $idx . ' select_posts before normalization - type: ' . gettype($select_posts) . ', is_array: ' . (is_array($select_posts) ? 'YES (count: ' . count($select_posts) . ')' : 'NO'));
						
						if (is_array($select_posts)) {
							// Convert post objects to IDs if needed
							$normalized_select_posts = array();
							foreach ($select_posts as $sp_item) {
								if (is_object($sp_item) && isset($sp_item->ID)) {
									// It's a post object, extract the ID
									$normalized_select_posts[] = (int) $sp_item->ID;
								} elseif (is_numeric($sp_item)) {
									// It's already an ID
									$normalized_select_posts[] = (int) $sp_item;
								}
							}
							$converted_value[$idx]['select_posts'] = $normalized_select_posts;
							
							error_log('XF Translator ACF: Row ' . $idx . ' select_posts after normalization - count: ' . count($normalized_select_posts) . ', IDs: ' . implode(', ', $normalized_select_posts));
							
							// Debug: Log if select_posts became empty after normalization
							if (empty($normalized_select_posts) && !empty($select_posts)) {
								error_log('XF Translator ACF: WARNING - select_posts became empty after normalization in row ' . $idx . '. Original had ' . count($select_posts) . ' items.');
							}
						} elseif (!is_array($select_posts) && !empty($select_posts)) {
							// Handle case where select_posts is not an array (single value)
							if (is_object($select_posts) && isset($select_posts->ID)) {
								$converted_value[$idx]['select_posts'] = array((int) $select_posts->ID);
							} elseif (is_numeric($select_posts)) {
								$converted_value[$idx]['select_posts'] = array((int) $select_posts);
							}
						} elseif (empty($select_posts)) {
							// Ensure select_posts exists as empty array even if it was removed
							$converted_value[$idx]['select_posts'] = array();
							error_log('XF Translator ACF: Row ' . $idx . ' - select_posts was empty, set to empty array');
						}
					} elseif (is_array($row) && !isset($row['select_posts'])) {
						// Row exists but select_posts key is missing - this shouldn't happen but handle it
						error_log('XF Translator ACF: WARNING - Row ' . $idx . ' exists but select_posts key is missing! Row keys: ' . implode(', ', array_keys($row)));
						$converted_value[$idx]['select_posts'] = array();
					}
				}
				
				// Final check: Log the final structure
				if ($field_name === 'sbposts__content') {
					$total_rows = count($converted_value);
					$rows_with_posts = 0;
					$all_post_ids = array();
					foreach ($converted_value as $idx => $row) {
						if (is_array($row) && isset($row['select_posts']) && !empty($row['select_posts']) && is_array($row['select_posts'])) {
							$rows_with_posts++;
							$all_post_ids = array_merge($all_post_ids, $row['select_posts']);
						}
					}
					error_log('XF Translator ACF: sbposts__content FINAL - Total rows: ' . $total_rows . ', Rows with posts: ' . $rows_with_posts . ', All post IDs: ' . implode(', ', $all_post_ids));
				}
			}
			
			// Debug logging for sidebar posts
			if ($field_name === 'sbposts__content' || $field_name === 'select_posts') {
				error_log('XF Translator ACF: Filtering field "' . $field_name . '" - Original count: ' . (is_array($value) ? count($value) : 'not array') . ', Converted count: ' . (is_array($converted_value) ? count($converted_value) : 'not array'));
				
				if ($field_name === 'sbposts__content' && is_array($converted_value) && !empty($converted_value)) {
					foreach ($converted_value as $idx => $row) {
						if (is_array($row) && isset($row['select_posts'])) {
							$select_posts = $row['select_posts'];
							// Extract IDs for logging
							$ids_for_log = array();
							if (is_array($select_posts)) {
								foreach ($select_posts as $sp_item) {
									if (is_object($sp_item) && isset($sp_item->ID)) {
										$ids_for_log[] = $sp_item->ID;
									} elseif (is_numeric($sp_item)) {
										$ids_for_log[] = $sp_item;
									}
								}
							}
							error_log('XF Translator ACF: sbposts__content CONVERTED row ' . $idx . ' - select_posts: ' . (is_array($select_posts) ? 'array(' . count($select_posts) . ')' : gettype($select_posts)) . ' - IDs: ' . (is_array($select_posts) ? implode(', ', $ids_for_log) : 'N/A'));
						}
					}
				}
			}
			
			return $converted_value;
		}

		// For other ACF fields, convert post IDs but allow original posts as fallback
		$converted_value = $this->convert_post_ids_to_translated($value, $lang_prefix, 0, false);
		return $converted_value;
	}
	
	/**
	 * Convert post IDs in a value to their translated versions (on-demand check)
	 * This is used for ACF fields on frontend to dynamically convert post IDs
	 *
	 * @param mixed $value The value that may contain post IDs
	 * @param string $language_prefix Language prefix (e.g., 'fr-CA')
	 * @param int $depth Current recursion depth (to prevent infinite loops)
	 * @param bool $filter_untranslated If true, exclude posts without translations. If false, show original if no translation exists.
	 * @return mixed Value with post IDs converted to translated versions
	 */
	private function convert_post_ids_to_translated($value, $language_prefix, $depth = 0, $filter_untranslated = false) {
		// Prevent infinite recursion (max depth of 10 levels)
		if ($depth > 10) {
			return $value;
		}

		if (empty($value) || empty($language_prefix)) {
			return $value;
		}

		// Handle arrays (including ACF relationship fields which store arrays of post IDs or post objects)
		if (is_array($value)) {
			$converted = array();
			$original_count = count($value);
			foreach ($value as $key => $item) {
				// Recursively process nested arrays (increment depth)
				// IMPORTANT: Pass $filter_untranslated flag to nested calls so repeater fields work correctly
				if (is_array($item)) {
					// Debug logging for nested arrays (repeater rows with select_posts)
					if ($depth === 0 && isset($item['select_posts'])) {
						$select_posts_before = $item['select_posts'];
						// Extract IDs from post objects if needed
						$ids_before = array();
						if (is_array($select_posts_before)) {
							foreach ($select_posts_before as $sp_item) {
								if (is_object($sp_item) && isset($sp_item->ID)) {
									$ids_before[] = $sp_item->ID;
								} elseif (is_numeric($sp_item)) {
									$ids_before[] = $sp_item;
								}
							}
						}
						error_log('XF Translator: Processing nested array (repeater row) at depth ' . $depth . ', key: ' . $key . ', select_posts before: ' . (is_array($select_posts_before) ? 'array(' . count($select_posts_before) . ') - IDs: ' . implode(', ', $ids_before) : gettype($select_posts_before)));
					}
					
					$nested_result = $this->convert_post_ids_to_translated($item, $language_prefix, $depth + 1, $filter_untranslated);
					
					// CRITICAL FIX: If select_posts was in the original item, ensure it stays as an array in the result
					if (isset($item['select_posts']) && is_array($item['select_posts'])) {
						// If select_posts was converted to something other than an array, fix it
						if (!isset($nested_result['select_posts']) || !is_array($nested_result['select_posts'])) {
							// select_posts was lost or converted incorrectly - reconvert it properly
							$original_select_posts = $item['select_posts'];
							$converted_select_posts = $this->convert_post_ids_to_translated($original_select_posts, $language_prefix, $depth + 2, $filter_untranslated);
							
							// Ensure result is an array
							if (!is_array($converted_select_posts)) {
								if (is_numeric($converted_select_posts)) {
									$converted_select_posts = array((int) $converted_select_posts);
								} else {
									$converted_select_posts = array();
								}
							}
							
							if (!is_array($nested_result)) {
								$nested_result = array();
							}
							$nested_result['select_posts'] = $converted_select_posts;
							error_log('XF Translator: Fixed select_posts conversion - was ' . gettype($nested_result['select_posts'] ?? 'missing') . ', now array with ' . count($converted_select_posts) . ' items');
						}
					}
					
					// Debug logging for nested arrays after conversion
					if ($depth === 0 && isset($item['select_posts'])) {
						$select_posts_after = isset($nested_result['select_posts']) ? $nested_result['select_posts'] : 'not found';
						// Extract IDs from post objects if needed
						$ids_after = array();
						if (is_array($select_posts_after)) {
							foreach ($select_posts_after as $sp_item) {
								if (is_object($sp_item) && isset($sp_item->ID)) {
									$ids_after[] = $sp_item->ID;
								} elseif (is_numeric($sp_item)) {
									$ids_after[] = $sp_item;
								}
							}
						}
						error_log('XF Translator: Processing nested array (repeater row) at depth ' . $depth . ', key: ' . $key . ', select_posts after: ' . (is_array($select_posts_after) ? 'array(' . count($select_posts_after) . ') - IDs: ' . implode(', ', $ids_after) : gettype($select_posts_after)));
					}
					
					// For nested arrays (like repeater rows), always preserve the structure
					// even if some fields become empty (e.g., select_posts might be empty after filtering)
					// This ensures repeater rows are not completely removed
					// IMPORTANT: Always preserve the array structure, even if it's empty
					// This is crucial for repeater fields where empty sub-arrays should still exist
					if (is_array($nested_result)) {
						// CRITICAL FIX: If select_posts was converted to a non-array (string/number), convert it back to array
						if (isset($item['select_posts']) && is_array($item['select_posts'])) {
							if (!isset($nested_result['select_posts'])) {
								// select_posts was removed during conversion, restore it as empty array
								$nested_result['select_posts'] = array();
								error_log('XF Translator: Restored empty select_posts key in repeater row at depth ' . $depth . ', key: ' . $key);
							} elseif (!is_array($nested_result['select_posts'])) {
								// select_posts was converted to a string/number instead of array - fix it
								$converted_id = $nested_result['select_posts'];
								$nested_result['select_posts'] = is_numeric($converted_id) ? array((int) $converted_id) : array();
								error_log('XF Translator: Fixed select_posts - was ' . gettype($converted_id) . ' (' . $converted_id . '), converted to array with ' . count($nested_result['select_posts']) . ' items');
							}
						}
						$converted[$key] = $nested_result;
					} elseif ($nested_result !== false && $nested_result !== null) {
						$converted[$key] = $nested_result;
					} else {
						// Even if result is null/false, preserve the original structure for repeater rows
						// This prevents repeater rows from disappearing entirely
						// But ensure select_posts exists as empty array if it was in original
						if (is_array($item) && isset($item['select_posts'])) {
							$item['select_posts'] = array(); // Set to empty array instead of removing
						}
						$converted[$key] = $item;
					}
				} elseif (is_object($item) && isset($item->ID)) {
					// ACF post object - check if this is already a translated post
					$item_language = get_post_meta($item->ID, '_xf_translator_language', true);
				$normalized_item_lang = $this->normalize_lang_prefix($item_language);
				$normalized_target_lang = $this->normalize_lang_prefix($language_prefix);

				if ($item_language === $language_prefix || ($normalized_item_lang && $normalized_item_lang === $normalized_target_lang)) {
						// This is already a translated post for the current language
						// For nested arrays (like select_posts in repeater), return ID instead of object
						// This ensures compatibility with shortcodes that expect IDs
						if ($depth > 0) {
							$converted[$key] = (int) $item->ID;
						} else {
							$converted[$key] = $item;
						}
					} else {
						// This is an original post, try to find its translation
						$translated_id = $this->get_translated_post_id($item->ID, $language_prefix);
						if ($translated_id) {
							// Verify the translated post exists and is published
							$translated_post = get_post($translated_id);
							if ($translated_post && $translated_post->post_status === 'publish') {
								// For nested arrays (like select_posts in repeater), return ID instead of object
								// This ensures compatibility with shortcodes that expect IDs
								if ($depth > 0) {
									$converted[$key] = (int) $translated_id;
								} else {
									$converted[$key] = $translated_post;
								}
							}
							// If translated post doesn't exist or isn't published, handle based on filter_untranslated flag
							elseif (!$filter_untranslated) {
								// If we're not filtering untranslated, show original
								// For nested arrays, return ID instead of object
								if ($depth > 0) {
									$converted[$key] = (int) $item->ID;
								} else {
									$converted[$key] = $item;
								}
							}
						} else {
							// No translation exists
							if ($filter_untranslated) {
								// Skip this post (don't add to $converted array)
							} else {
								// Show original post if filtering is not enabled
								// For nested arrays, return ID instead of object
								if ($depth > 0) {
									$converted[$key] = (int) $item->ID;
								} else {
									$converted[$key] = $item;
								}
							}
						}
					}
				} elseif (is_numeric($item) && $item > 0) {
					// Check if this is a post ID
					$post = get_post($item);
					if ($post && $post->post_type !== 'attachment') {
						// Check if this post is already a translated post for the current language
				$post_language = get_post_meta($item, '_xf_translator_language', true);
				// Normalize both prefixes to handle variations (e.g., fr vs fr-CA)
				$normalized_post_lang = $this->normalize_lang_prefix($post_language);
				$normalized_target_lang = $this->normalize_lang_prefix($language_prefix);

				if ($post_language === $language_prefix || ($normalized_post_lang && $normalized_post_lang === $normalized_target_lang)) {
							// This is already a translated post for the current language, use it as-is
							$converted[$key] = $item;
						} else {
							// This is an original post, try to find its translation
							$translated_id = $this->get_translated_post_id($item, $language_prefix);
							if ($translated_id) {
								// Verify the translated post exists and is published
								$translated_post = get_post($translated_id);
								if ($translated_post && $translated_post->post_status === 'publish') {
									// Only include if translation exists and is published
									$converted[$key] = $translated_id;
								} elseif (!$filter_untranslated) {
									// If we're not filtering untranslated, show original
									$converted[$key] = $item;
								}
							} else {
								// No translation exists
								if ($filter_untranslated) {
									// Skip this post (don't add to $converted array)
								} else {
									// Show original post if filtering is not enabled
									$converted[$key] = $item;
								}
							}
						}
					} else {
						// Not a post ID or is attachment, keep original
						$converted[$key] = $item;
					}
				} else {
					// Not a post ID, keep original
					$converted[$key] = $item;
				}
			}
			// Filter out any false/null values and reindex if needed
			$converted = array_filter($converted, function($item) {
				return $item !== false && $item !== null;
			});
			// Reindex array to remove gaps (important for ACF relationship fields)
			if (!empty($converted) && array_keys($converted) !== range(0, count($converted) - 1)) {
				$converted = array_values($converted);
			}
			
			// Debug logging for related posts filtering
			if ($original_count > 0) {
				$converted_count = count($converted);
				if ($converted_count === 0) {
					error_log('XF Translator: Related posts filtered - Original count: ' . $original_count . ', Filtered count: 0, Language: ' . $language_prefix);
					$original_ids = array_map(function($item) {
						if (is_object($item) && isset($item->ID)) return $item->ID;
						if (is_numeric($item)) return $item;
						return 'unknown';
					}, $value);
					error_log('XF Translator: Original post IDs: ' . implode(', ', $original_ids));
					
					// Check if any of the original posts have translations
					$has_any_translations = false;
					foreach ($original_ids as $orig_id) {
						if (is_numeric($orig_id)) {
							$trans_id = $this->get_translated_post_id($orig_id, $language_prefix);
							if ($trans_id) {
								$has_any_translations = true;
								error_log('XF Translator: Post ' . $orig_id . ' HAS translation: ' . $trans_id);
							} else {
								error_log('XF Translator: Post ' . $orig_id . ' has NO translation for language: ' . $language_prefix);
							}
						}
					}
					
					// If no translations found at all, return empty array (correct behavior)
					// If translations exist but weren't found, there might be a bug
					if (!$has_any_translations) {
						error_log('XF Translator: None of the related posts have translations. Returning empty array.');
					}
				} else {
					error_log('XF Translator: Related posts filtered - Original count: ' . $original_count . ', Filtered count: ' . $converted_count . ', Language: ' . $language_prefix);
				}
			}
			
			return $converted;
		}

		// Handle post objects (ACF might return post objects instead of IDs)
		if (is_object($value) && isset($value->ID)) {
			$translated_id = $this->get_translated_post_id($value->ID, $language_prefix);
			if ($translated_id) {
				$translated_post = get_post($translated_id);
				return $translated_post ? $translated_post : $value;
			}
			// If no translation exists, return original (backward compatible for single values)
			// Note: For arrays, untranslated posts are filtered out in the array handling above
			return $value;
		}

		// Handle single numeric value (could be a post ID)
		if (is_numeric($value) && $value > 0) {
			$post = get_post($value);
			if ($post && $post->post_type !== 'attachment') {
				// Try to get translated version
				$translated_id = $this->get_translated_post_id($value, $language_prefix);
				return $translated_id ? $translated_id : $value;
			}
		}

		// Handle serialized data (though ACF usually stores arrays directly)
		if (is_string($value) && is_serialized($value)) {
			$unserialized = unserialize($value);
			if (is_array($unserialized) || is_numeric($unserialized) || (is_object($unserialized) && isset($unserialized->ID))) {
				$converted = $this->convert_post_ids_to_translated($unserialized, $language_prefix, $depth + 1, $filter_untranslated);
				return serialize($converted);
			}
		}

		return $value;
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
		
		// Build list of language prefixes to try
		// Sometimes translations are stored with different prefix variations (e.g., "fr" vs "fr-CA")
		$lang_prefixes_to_try = array($lang_prefix);
		
		// If lang_prefix contains a hyphen (e.g., "fr-CA"), also try without it (e.g., "fr")
		if (strpos($lang_prefix, '-') !== false) {
			$parts = explode('-', $lang_prefix);
			$lang_prefixes_to_try[] = $parts[0]; // e.g., "fr" from "fr-CA"
		}
		
		// Also try URL-safe version (removes hyphens)
		$url_safe_prefix = preg_replace('/[^A-Za-z0-9]/', '', $lang_prefix);
		if ($url_safe_prefix !== $lang_prefix && !in_array($url_safe_prefix, $lang_prefixes_to_try)) {
			$lang_prefixes_to_try[] = $url_safe_prefix;
		}
		
		// Remove duplicates
		$lang_prefixes_to_try = array_values(array_unique($lang_prefixes_to_try));
		
		// Try each key with each language prefix variation
		foreach ($keys_to_try as $key) {
			foreach ($lang_prefixes_to_try as $prefix) {
				$translated_meta_key = '_xf_translator_user_meta_' . $key . '_' . $prefix;
				// Bypass our own filter to avoid recursion
				remove_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10);
				$translated_value = get_user_meta($user_id, $translated_meta_key, $single);
				add_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10, 4);
				
				// Debug logging for description field
				if ($normalized_key === 'description' || in_array('description', $keys_to_try) || in_array('user_description', $keys_to_try)) {
					error_log('XF Translator User Meta: Trying key "' . $key . '" with prefix "' . $prefix . '" (meta_key: "' . $translated_meta_key . '") - found: ' . ($translated_value !== false && !empty($translated_value) ? 'YES' : 'NO'));
				}
				
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
		
		// Debug logging for ALL fields to see what's being called
		static $logged_fields = array();
		if (!isset($logged_fields[$field])) {
			error_log('XF Translator User Meta: filter_get_the_author_meta called - field: "' . $field . '", user_id: ' . $user_id);
			$logged_fields[$field] = true;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		
		// Debug logging for user meta translation
		if ($field === 'description' || $field === 'user_description') {
			error_log('XF Translator User Meta: Processing description field - user_id: ' . $user_id . ', lang_prefix: ' . ($lang_prefix ?: 'empty') . ', value length: ' . strlen((string)$value));
		}
		
		if (empty($lang_prefix)) {
			if ($field === 'description' || $field === 'user_description') {
				error_log('XF Translator User Meta: No language prefix detected, returning original value');
			}
			return $value;
		}
		
		// Check if this meta field should be translated
		$translatable_fields = $this->settings->get_translatable_user_meta_fields();
		if (empty($translatable_fields)) {
			if ($field === 'description' || $field === 'user_description') {
				error_log('XF Translator User Meta: No translatable fields configured');
			}
			return $value;
		}
		
		// Check if this field is in the translatable fields list
		if (!$this->is_field_translatable($field, $translatable_fields)) {
			if ($field === 'description' || $field === 'user_description') {
				error_log('XF Translator User Meta: Field "' . $field . '" is not in translatable fields list: ' . print_r($translatable_fields, true));
			}
			return $value;
		}
		
		// Get normalized meta key
		$normalized_key = $this->normalize_user_meta_key($field);
		
		// Try to get translated value - bypass our own filter to avoid recursion
		remove_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10);
		$translated_value = $this->get_translated_user_meta($user_id, $field, $normalized_key, $lang_prefix, true, $translatable_fields);
		add_filter('get_user_meta', array($this, 'filter_get_user_meta'), 10, 4);
		
		if ($field === 'description' || $field === 'user_description') {
			error_log('XF Translator User Meta: Translated value found: ' . (!empty($translated_value) ? 'YES (length: ' . strlen((string)$translated_value) . ')' : 'NO'));
		}
		
		// Return translated value if available, otherwise original
		return !empty($translated_value) ? $translated_value : $value;
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
	 * Filter user_description property for direct object access (e.g., $userdata->description)
	 * This handles cases where themes use get_userdata() and access properties directly
	 *
	 * @param string $value The description value
	 * @param int $user_id User ID
	 * @return string Translated description or original value
	 */
	public function filter_user_description_property($value, $user_id)
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
		$translatable_fields = $this->settings->get_translatable_user_meta_fields();
		if (empty($translatable_fields)) {
			return $value;
		}
		
		// Check if description is in translatable fields
		if (!in_array('description', $translatable_fields) && !in_array('user_description', $translatable_fields)) {
			return $value;
		}
		
		// Get normalized meta key
		$normalized_key = 'description'; // Always use 'description' as the normalized key
		
		// Try to get translated value
		$translated_value = $this->get_translated_user_meta($user_id, 'description', $normalized_key, $lang_prefix, true, $translatable_fields);
		
		// Return translated value if available, otherwise original
		return !empty($translated_value) ? $translated_value : $value;
	}
	
	/**
	 * Modify user data on page load to update description property
	 * This handles cases where themes use $userdata->description directly
	 */
	public function modify_user_data_on_page_load()
	{
		// Only on frontend
		if (is_admin()) {
			return;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		if (empty($lang_prefix)) {
			return;
		}
		
		// Check if this meta field should be translated
		$translatable_fields = $this->settings->get_translatable_user_meta_fields();
		if (empty($translatable_fields)) {
			return;
		}
		
		// Check if description is in translatable fields
		if (!in_array('description', $translatable_fields) && !in_array('user_description', $translatable_fields)) {
			return;
		}
		
		// Get the current post's author
		global $post, $author;
		$user_id = 0;
		
		if ($post && $post->post_author) {
			$user_id = $post->post_author;
		} elseif (!empty($author)) {
			$user_id = $author;
		}
		
		if (!$user_id) {
			return;
		}
		
		// Get translated description
		$normalized_key = 'description';
		$translated_value = $this->get_translated_user_meta($user_id, 'description', $normalized_key, $lang_prefix, true, $translatable_fields);
		
		if (!empty($translated_value)) {
			// Modify the user object's data property directly
			$user = get_userdata($user_id);
			if ($user) {
				// Modify the data object
				if (isset($user->data->description)) {
					$user->data->description = $translated_value;
				}
				// Also set it as a direct property for immediate access
				$user->description = $translated_value;
				error_log('XF Translator: Modified user ' . $user_id . ' description to translated version (length: ' . strlen($translated_value) . ')');
			}
		}
	}
	
	/**
	 * Modify user data when metadata is retrieved
	 * This intercepts get_user_metadata to modify the description before it's cached
	 */
	public function modify_user_data_in_metadata($value, $user_id, $meta_key, $single)
	{
		// Only filter on frontend
		if (is_admin()) {
			return $value;
		}
		
		// Only handle description field
		if ($meta_key !== 'description' && $meta_key !== 'user_description') {
			return $value;
		}
		
		// Use the existing filter logic
		return $this->filter_user_metadata_for_cache($value, $user_id, $meta_key, $single);
	}
	
	/**
	 * Modify user data in the loop to catch authors loaded during post rendering
	 */
	public function modify_user_data_in_loop($query)
	{
		// Only on frontend
		if (is_admin()) {
			return;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		if (empty($lang_prefix)) {
			return;
		}
		
		// Check if this meta field should be translated
		$translatable_fields = $this->settings->get_translatable_user_meta_fields();
		if (empty($translatable_fields)) {
			return;
		}
		
		// Check if description is in translatable fields
		if (!in_array('description', $translatable_fields) && !in_array('user_description', $translatable_fields)) {
			return;
		}
		
		// Get the current post's author
		global $post, $author;
		$user_id = 0;
		
		if ($post && $post->post_author) {
			$user_id = $post->post_author;
		} elseif (!empty($author)) {
			$user_id = $author;
		}
		
		if (!$user_id) {
			return;
		}
		
		// Get translated description
		$normalized_key = 'description';
		$translated_value = $this->get_translated_user_meta($user_id, 'description', $normalized_key, $lang_prefix, true, $translatable_fields);
		
		if (!empty($translated_value)) {
			// Modify the user object's data property directly
			$user = get_userdata($user_id);
			if ($user) {
				// Modify the data object
				if (isset($user->data->description)) {
					$user->data->description = $translated_value;
				}
				// Also set it as a direct property for immediate access
				$user->description = $translated_value;
			}
			
			// Also modify the global $author variable if it exists
			if (!empty($author) && $author == $user_id) {
				$userdata = get_userdata($author);
				if ($userdata) {
					if (isset($userdata->data->description)) {
						$userdata->data->description = $translated_value;
					}
					$userdata->description = $translated_value;
				}
			}
		}
	}
	
	/**
	 * Filter user metadata to modify description in cached user data
	 * This intercepts when user meta is loaded into the WP_User object
	 */
	public function filter_user_metadata_for_cache($value, $user_id, $meta_key, $single)
	{
		// Only filter on frontend
		if (is_admin()) {
			return $value;
		}
		
		// Only handle description field
		if ($meta_key !== 'description' && $meta_key !== 'user_description') {
			return $value;
		}
		
		// Get current language prefix
		$lang_prefix = $this->get_current_language_prefix();
		if (empty($lang_prefix)) {
			return $value;
		}
		
		// Check if this meta field should be translated
		$translatable_fields = $this->settings->get_translatable_user_meta_fields();
		if (empty($translatable_fields)) {
			return $value;
		}
		
		// Check if description is in translatable fields
		if (!in_array('description', $translatable_fields) && !in_array('user_description', $translatable_fields)) {
			return $value;
		}
		
		// Get normalized meta key
		$normalized_key = 'description';
		
		// Try to get translated value
		$translated_value = $this->get_translated_user_meta($user_id, 'description', $normalized_key, $lang_prefix, true, $translatable_fields);
		
		// Return translated value if available, otherwise original
		return !empty($translated_value) ? $translated_value : $value;
	}
	
	/**
	 * Force homepage template for language-prefixed homepages (e.g., /fr/ or /es/)
	 * 
	 * @param string $template The template path
	 * @return string The template path
	 */
	public function force_homepage_template_for_language($template) {
		// Only on frontend
		if (is_admin()) {
			return $template;
		}
		
		// Get language prefix
		$lang_prefix = $this->get_current_language_prefix();
		if (empty($lang_prefix)) {
			return $template;
		}
		
		// Check if we're on a language homepage
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$request_path = parse_url($request_uri, PHP_URL_PATH);
		$request_path = trim($request_path, '/');
		
		// Get URL prefix for current language
		$languages = $this->settings->get('languages', array());
		$is_lang_homepage = false;
		foreach ($languages as $language) {
			if (empty($language['prefix']) || $language['prefix'] !== $lang_prefix) {
				continue;
			}
			$url_prefix = $this->get_url_prefix_for_language($language);
			if ($url_prefix && ($request_path === $url_prefix || $request_path === '')) {
				$is_lang_homepage = true;
				break;
			}
		}
		
		if (!$is_lang_homepage) {
			return $template;
		}
		
		// Check if we have a homepage template (page-home.php)
		$home_template = locate_template(array('page-home.php'));
		if ($home_template) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('XF Translator DEBUG: Forcing homepage template: ' . $home_template);
			}
			return $home_template;
		}
		
		// Fallback: try to find the translated homepage page and use its template
		global $wpdb;
		$front_page_id = get_option('page_on_front');
		if ($front_page_id) {
			// Find translated version of the front page
			$translated_front_page_id = $wpdb->get_var($wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
					AND pm1.meta_key = '_xf_translator_language' 
					AND pm1.meta_value = %s
				INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
					AND (pm2.meta_key = '_xf_translator_original_post_id' OR pm2.meta_key = '_api_translator_original_post_id')
					AND pm2.meta_value = %d
				WHERE p.post_status = 'publish'
				AND p.post_type = 'page'
				LIMIT 1",
				$lang_prefix,
				$front_page_id
			));
			
			if ($translated_front_page_id) {
				$page_template = get_page_template_slug($translated_front_page_id);
				if ($page_template) {
					$template_path = locate_template(array($page_template));
					if ($template_path) {
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log('XF Translator DEBUG: Using translated front page template: ' . $template_path);
						}
						return $template_path;
					}
				}
			}
		}
		
		return $template;
	}

}
