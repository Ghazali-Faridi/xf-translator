<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://xfinitive.co
 * @since      1.0.0
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 * @author     ghazali <shafe_ghazali@xfinitive.co>
 */
class Xf_Translator {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Xf_Translator_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'XF_TRANSLATOR_VERSION' ) ) {
			$this->version = XF_TRANSLATOR_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'xf-translator';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Xf_Translator_Loader. Orchestrates the hooks of the plugin.
	 * - Xf_Translator_i18n. Defines internationalization functionality.
	 * - Xf_Translator_Admin. Defines all hooks for the admin area.
	 * - Xf_Translator_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-xf-translator-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-xf-translator-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-xf-translator-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-xf-translator-public.php';

		$this->loader = new Xf_Translator_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Xf_Translator_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Xf_Translator_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Xf_Translator_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

        $this->loader->add_action('admin_menu', $plugin_admin,'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin,'handle_form_submissions');
        $this->loader->add_action('restrict_manage_posts', $plugin_admin, 'add_language_filter_dropdown');
        $this->loader->add_action('pre_get_posts', $plugin_admin, 'filter_posts_by_language');
        
        // Register AJAX handlers for test translation
        $this->loader->add_action('wp_ajax_xf_get_post_content', $plugin_admin, 'ajax_get_post_content');
        $this->loader->add_action('wp_ajax_xf_test_translation', $plugin_admin, 'ajax_test_translation');
        $this->loader->add_action('wp_ajax_xf_save_default_model', $plugin_admin, 'ajax_save_default_model');
        
        
        // Hook to create translation queue entries when a post is saved
        // Use multiple hooks for better compatibility
        $this->loader->add_action('save_post', $plugin_admin, 'create_translation_queue_entries', 10, 2);
        $this->loader->add_action('wp_after_insert_post', $plugin_admin, 'create_translation_queue_entries_after_insert', 10, 4);
        $this->loader->add_action('transition_post_status', $plugin_admin, 'create_translation_queue_entries_on_publish', 10, 3);
        
        // Hook to detect post edits and create EDIT queue entries
        $this->loader->add_action('pre_post_update', $plugin_admin, 'store_pre_edit_values', 10, 1);
        $this->loader->add_action('post_updated', $plugin_admin, 'handle_post_edit', 10, 3);
        
        // Also hook into save_post to catch custom field updates that might not trigger pre_post_update
        $this->loader->add_action('save_post', $plugin_admin, 'store_pre_edit_values_on_save', 5, 1); // Priority 5 to run early
        
        // ACF-specific hooks to detect ACF field changes
        // These fire after ACF fields are saved
        $this->loader->add_action('acf/save_post', $plugin_admin, 'handle_acf_save', 20, 1); // Priority 20 to run after ACF saves
        
        // Hook BEFORE meta is updated to store previous values
        // This is a filter, not an action, so we need to use add_filter
        $this->loader->add_filter('update_post_metadata', $plugin_admin, 'store_before_meta_update', 5, 5); // Priority 5 to run early, 5 args
        
        // Hook AFTER meta is updated to detect changes
        $this->loader->add_action('updated_post_meta', $plugin_admin, 'handle_meta_update', 10, 4);
        $this->loader->add_action('added_post_meta', $plugin_admin, 'handle_meta_update', 10, 4); // Also catch new meta additions
        
        // Hook to process batched field updates
        add_action('xf_translator_process_pending_fields', array($plugin_admin, 'process_pending_acf_fields'), 10, 1);
        
        // Hook to check custom fields after post update (delayed check)
        add_action('xf_translator_check_custom_fields', array($plugin_admin, 'check_custom_fields_after_update'), 10, 1);

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Xf_Translator_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		
		// Add filter to modify permalinks for translated posts
		$this->loader->add_filter( 'post_link', $plugin_public, 'add_language_prefix_to_permalink', 10, 2 );
		$this->loader->add_filter( 'page_link', $plugin_public, 'add_language_prefix_to_permalink', 10, 2 );
		$this->loader->add_filter( 'post_type_link', $plugin_public, 'add_language_prefix_to_permalink', 10, 2 );
		
		// Filter query to find translated posts by language prefix
		$this->loader->add_action( 'pre_get_posts', $plugin_public, 'filter_translated_post_query', 10, 1 );
		
		// Filter all queries to show only content for current language
		$this->loader->add_action( 'pre_get_posts', $plugin_public, 'filter_content_by_language', 5, 1 );
		
		// Filter menu locations to show translated menus
		$this->loader->add_filter( 'wp_nav_menu_args', $plugin_public, 'filter_nav_menu_args', 10, 1 );
		$this->loader->add_filter( 'wp_get_nav_menu_object', $plugin_public, 'filter_nav_menu_object', 10, 2 );
		$this->loader->add_filter( 'wp_get_nav_menus', $plugin_public, 'filter_nav_menus', 10, 2 );
		$this->loader->add_filter( 'theme_mod_nav_menu_locations', $plugin_public, 'filter_nav_menu_locations', 10, 1 );
		
		// Filter menu item URLs to add language prefix
		$this->loader->add_filter( 'wp_setup_nav_menu_item', $plugin_public, 'filter_nav_menu_item_url', 10, 1 );

		// Output canonical + hreflang tags for translated posts
		$this->loader->add_action( 'wp_head', $plugin_public, 'output_seo_tags', 5 );
		
		// Floating language switcher
		$this->loader->add_action( 'wp_footer', $plugin_public, 'render_language_switcher' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Xf_Translator_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
