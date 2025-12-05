<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://xfinitive.co
 * @since      1.0.0
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/admin
 * @author     ghazali <shafe_ghazali@xfinitive.co>
 */
class Xf_Translator_Admin {

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

	private $settings;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		$this->settings = new Settings();

	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/xf-translator-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/xf-translator-admin.js', array( 'jquery' ), $this->version, false );
		
		// Localize script for AJAX
		wp_localize_script( $this->plugin_name, 'apiTranslator', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'xf_translator_ajax' )
		) );

	}

	/**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Unite.AI Translations', 'xf-translator'),
            __('Unite.AI Translations', 'xf-translator'),
            'manage_options',
            'xf-translator',
            array($this, 'render_settings_page'),
            'dashicons-translation',
            30
        );
    }

	/**
     * Render settings page
     */
    public function render_settings_page() {
        // Make admin instance available to templates
        global $api_translator_admin;
        $api_translator_admin = $this;
        
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Redirect if someone tries to access the removed translations tab
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
        
        include plugin_dir_path( __FILE__ ) . 'partials/xf-translator-admin-display.php';
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['api_translator_action']) || !check_admin_referer('api_translator_settings', 'api_translator_nonce')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['api_translator_action']);
        
        switch ($action) {
            case 'save_general':
                $this->save_general_settings();
                break;
                
            case 'add_language':
                $this->handle_add_language();
                break;
                
            case 'edit_language':
                $this->handle_edit_language();
                break;
                
            case 'delete_language':
                $this->handle_delete_language();
                break;
                
            case 'save_brand_tones':
                $this->save_brand_tones();
                break;
                
                
            case 'add_exclude_path':
                $this->handle_add_exclude_path();
                break;
                
            case 'delete_exclude_path':
                $this->handle_delete_exclude_path();
                break;
                
            case 'add_glossary_term':
                $this->handle_add_glossary_term();
                break;
                
            case 'delete_glossary_term':
                $this->handle_delete_glossary_term();
                break;

            case 'save_acf_settings':
                $this->save_acf_settings();
                break;

            case 'process_queue':
                $this->handle_process_queue();
                break;
                
            case 'translate_menu':
                $this->handle_translate_menu();
                break;
                
            case 'translate_all_menus':
                $this->handle_translate_all_menus();
                break;
                
            case 'translate_menu_item':
                $this->handle_translate_menu_item();
                break;
                
            case 'translate_taxonomy':
                $this->handle_translate_taxonomy();
                break;
                
            case 'translate_term':
                $this->handle_translate_term();
                break;
                
            case 'analyze_posts':
                $this->handle_analyze_posts();
                break;
                
            case 'retry_queue_entry':
                $this->handle_retry_queue_entry();
                break;
                
            case 'reset_stuck_processing':
                $this->handle_reset_stuck_processing();
                break;
                
            case 'save_meta_fields':
                $this->handle_save_meta_fields();
                break;
                
            case 'save_user_meta_translations':
                $this->handle_save_user_meta_translations();
                break;
                
            case 'fix_old_slugs':
                $this->handle_fix_old_translated_post_slugs();
                break;
        }
    }
    
    /**
     * Handle save user meta translations
     */
    private function handle_save_user_meta_translations() {
        if (!isset($_POST['user_id']) || !isset($_POST['language_prefix'])) {
            $this->add_notice(__('Invalid request.', 'xf-translator'), 'error');
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $language_prefix = sanitize_text_field($_POST['language_prefix']);
        
        if (empty($user_id) || empty($language_prefix)) {
            $this->add_notice(__('User ID and language are required.', 'xf-translator'), 'error');
            return;
        }
        
        if (!isset($_POST['translated_meta']) || !is_array($_POST['translated_meta'])) {
            $this->add_notice(__('No translations provided.', 'xf-translator'), 'error');
            return;
        }
        
        $translatable_fields = $this->settings->get_translatable_user_meta_fields();
        
        foreach ($_POST['translated_meta'] as $meta_key => $translated_value) {
            // Verify this is a translatable field
            if (!in_array($meta_key, $translatable_fields)) {
                continue;
            }
            
            // Handle 'user_description' - WordPress stores it as 'description' in user meta
            $store_key = $meta_key;
            if ($meta_key === 'user_description') {
                $store_key = 'description';
            }
            
            // Sanitize the translated value
            $translated_value = sanitize_textarea_field($translated_value);
            
            // Store translated value with language prefix
            $translated_meta_key = '_xf_translator_user_meta_' . $store_key . '_' . $language_prefix;
            update_user_meta($user_id, $translated_meta_key, $translated_value);
        }
        
        $this->add_notice(__('User meta translations saved successfully.', 'xf-translator'), 'success');
    }
    
    /**
     * Handle save meta fields
     */
    private function handle_save_meta_fields() {
        $post_meta_fields = array();
        $user_meta_fields = array();
        
        if (isset($_POST['selected_post_meta_fields']) && !empty($_POST['selected_post_meta_fields'])) {
            $post_meta_fields = array_filter(array_map('trim', explode(',', sanitize_text_field($_POST['selected_post_meta_fields']))));
        }
        
        if (isset($_POST['selected_user_meta_fields']) && !empty($_POST['selected_user_meta_fields'])) {
            $user_meta_fields = array_filter(array_map('trim', explode(',', sanitize_text_field($_POST['selected_user_meta_fields']))));
        }
        
        $this->settings->update_translatable_post_meta_fields($post_meta_fields);
        $this->settings->update_translatable_user_meta_fields($user_meta_fields);
        
        $this->add_notice(__('Meta fields settings saved successfully.', 'xf-translator'), 'success');
    }
    
    /**
     * Save general settings
     */
    private function save_general_settings() {
        if (isset($_POST['api_key'])) {
            $this->settings->update('api_key', sanitize_text_field($_POST['api_key']));
        }
        
        if (isset($_POST['deepseek_api_key'])) {
            $this->settings->update('deepseek_api_key', sanitize_text_field($_POST['deepseek_api_key']));
        }
        
        if (isset($_POST['selected_model'])) {
            $this->settings->update('selected_model', sanitize_text_field($_POST['selected_model']));
        }
        
        if (isset($_POST['processing_delay_minutes'])) {
            $delay = intval($_POST['processing_delay_minutes']);
            if ($delay >= 0) {
                $this->settings->update('processing_delay_minutes', $delay);
            }
        }
        
        // Save cron enable/disable settings
        $enable_new_cron = isset($_POST['enable_new_translations_cron']) ? true : false;
        $enable_old_cron = isset($_POST['enable_old_translations_cron']) ? true : false;
        
        $this->settings->update('enable_new_translations_cron', $enable_new_cron);
        $this->settings->update('enable_old_translations_cron', $enable_old_cron);
        
        // Update cron schedules based on settings
        $this->update_cron_schedules($enable_new_cron, $enable_old_cron);
        
        $this->add_notice(__('Settings saved successfully.', 'api-translator'), 'success');
    }
    
    /**
     * Update cron schedules based on settings
     *
     * @param bool $enable_new Enable NEW translations cron
     * @param bool $enable_old Enable OLD translations cron
     */
    private function update_cron_schedules($enable_new, $enable_old) {
        // Handle NEW translations cron
        $new_timestamp = wp_next_scheduled('xf_translator_process_new_cron');
        if ($enable_new && !$new_timestamp) {
            // Enable: schedule if not already scheduled
            wp_schedule_event(time(), 'every_1_minute', 'xf_translator_process_new_cron');
        } elseif (!$enable_new && $new_timestamp) {
            // Disable: unschedule if currently scheduled
            wp_unschedule_event($new_timestamp, 'xf_translator_process_new_cron');
        }
        
        // Handle OLD translations cron
        $old_timestamp = wp_next_scheduled('xf_translator_process_old_cron');
        if ($enable_old && !$old_timestamp) {
            // Enable: schedule if not already scheduled
            wp_schedule_event(time(), 'every_1_minute', 'xf_translator_process_old_cron');
        } elseif (!$enable_old && $old_timestamp) {
            // Disable: unschedule if currently scheduled
            wp_unschedule_event($old_timestamp, 'xf_translator_process_old_cron');
        }
    }
    
    /**
     * Handle add language
     */
    private function handle_add_language() {
        if (isset($_POST['language_name']) && isset($_POST['language_prefix'])) {
            $name = sanitize_text_field($_POST['language_name']);
            $prefix = sanitize_text_field($_POST['language_prefix']);
            $path = isset($_POST['language_path']) ? sanitize_text_field($_POST['language_path']) : '';
            $description = isset($_POST['language_description']) ? sanitize_textarea_field($_POST['language_description']) : '';
            
            // Validate prefix is not duplicate
            if ($this->settings->prefix_exists($prefix)) {
                $this->add_notice(__('This prefix is already in use. Please choose a different prefix.', 'api-translator'), 'error');
                return;
            }
            
            // Determine final path value (use path if provided, otherwise prefix)
            $final_path = !empty($path) ? $path : $prefix;
            
            // Validate path is not duplicate
            if ($this->settings->path_exists($final_path)) {
                $this->add_notice(__('This path is already in use by another language. Please choose a different path.', 'api-translator'), 'error');
                return;
            }
            
            if ($this->settings->add_language($name, $prefix, $path, $description)) {
                $this->add_notice(__('Language added successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Language already exists or invalid data. Path or prefix may already be in use.', 'api-translator'), 'error');
            }
        }
    }
    
    /**
     * Handle edit language
     */
    private function handle_edit_language() {
        if (isset($_POST['language_index']) && isset($_POST['language_name']) && isset($_POST['language_prefix'])) {
            $index = intval($_POST['language_index']);
            $name = sanitize_text_field($_POST['language_name']);
            $prefix = sanitize_text_field($_POST['language_prefix']);
            $path = isset($_POST['language_path']) ? sanitize_text_field($_POST['language_path']) : '';
            $description = isset($_POST['language_description']) ? sanitize_textarea_field($_POST['language_description']) : '';
            
            // Validate prefix is not duplicate (excluding current language)
            if ($this->settings->prefix_exists($prefix, $index)) {
                $this->add_notice(__('This prefix is already in use. Please choose a different prefix.', 'api-translator'), 'error');
                return;
            }
            
            // Get existing language to determine final path value
            $languages = $this->settings->get('languages', array());
            $existing = isset($languages[$index]) ? $languages[$index] : array();
            
            // Determine final path value
            $final_path = '';
            if (!empty($path)) {
                $final_path = $path;
            } elseif (isset($existing['path']) && !empty($existing['path'])) {
                $final_path = $existing['path'];
            } else {
                $final_path = $prefix;
            }
            
            // Validate path is not duplicate (excluding current language)
            if ($this->settings->path_exists($final_path, $index)) {
                $this->add_notice(__('This path is already in use by another language. Please choose a different path.', 'api-translator'), 'error');
                return;
            }
            
            if ($this->settings->update_language($index, $name, $prefix, $path, $description)) {
                $this->add_notice(__('Language updated successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Failed to update language. Path or prefix may already be in use.', 'api-translator'), 'error');
            }
        }
    }
    
    /**
     * Handle delete language
     */
    private function handle_delete_language() {
        if (isset($_POST['language_index'])) {
            $index = intval($_POST['language_index']);
            
            if ($this->settings->remove_language($index)) {
                $this->add_notice(__('Language removed successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Failed to remove language.', 'api-translator'), 'error');
            }
        }
    }
    
    /**
     * Save brand tone
     */
    private function save_brand_tones() {
        if (isset($_POST['brand_tone'])) {
            $tone = sanitize_textarea_field($_POST['brand_tone']);
            $this->settings->update_brand_tone($tone);
            $this->add_notice(__('Brand tone saved successfully.', 'api-translator'), 'success');
        }
    }

    /**
     * Save ACF settings
     */
    private function save_acf_settings() {
        if (isset($_POST['translatable_acf_fields'])) {
            $fields = array_map('sanitize_text_field', $_POST['translatable_acf_fields']);
            $this->settings->update_translatable_acf_fields($fields);
            $this->add_notice(__('ACF translation settings saved successfully.', 'api-translator'), 'success');
        }
    }

    /**
     * AJAX handler to save ACF settings
     */
    public function ajax_save_acf_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : array();
        
        // Remove empty values
        $fields = array_filter($fields, function($field) {
            return !empty(trim($field));
        });

        $this->settings->update_translatable_acf_fields($fields);
        
        wp_send_json_success(array(
            'message' => __('ACF fields saved successfully. These fields will be automatically translated when you translate posts.', 'xf-translator'),
            'fields' => $fields
        ));
    }

    /**
     * Handle add exclude path
     */
    private function handle_add_exclude_path() {
        if (isset($_POST['exclude_path'])) {
            $path = sanitize_text_field($_POST['exclude_path']);
            
            if ($this->settings->add_exclude_path($path)) {
                $this->add_notice(__('Exclude path added successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Path already exists.', 'api-translator'), 'error');
            }
        }
    }
    
    /**
     * Handle delete exclude path
     */
    private function handle_delete_exclude_path() {
        if (isset($_POST['path_index'])) {
            $index = intval($_POST['path_index']);
            
            if ($this->settings->remove_exclude_path($index)) {
                $this->add_notice(__('Exclude path removed successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Failed to remove exclude path.', 'api-translator'), 'error');
            }
        }
    }
    
    /**
     * Handle add glossary term
     */
    private function handle_add_glossary_term() {
        if (isset($_POST['glossary_term'])) {
            $term = sanitize_text_field($_POST['glossary_term']);
            $context = isset($_POST['glossary_context']) ? sanitize_text_field($_POST['glossary_context']) : '';
            
            if ($this->settings->add_glossary_term($term, $context)) {
                $this->add_notice(__('Glossary term added successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Term already exists.', 'api-translator'), 'error');
            }
        }
    }
    
    /**
     * Handle delete glossary term
     */
    private function handle_delete_glossary_term() {
        if (isset($_POST['term_index'])) {
            $index = intval($_POST['term_index']);
            
            if ($this->settings->remove_glossary_term($index)) {
                $this->add_notice(__('Glossary term removed successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Failed to remove glossary term.', 'api-translator'), 'error');
            }
        }
    }
    
    /**
     * Add admin notice
     *
     * @param string $message Notice message
     * @param string $type Notice type (success, error, warning, info)
     */
    private function add_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
  
    /**
     * Handle process queue action
     */
    private function handle_process_queue() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        
        $processor = new Xf_Translator_Processor();
        $result = $processor->process_next_translation();
        
        if ($result) {
            $this->add_notice(
                sprintf(
                    __('Translation processed successfully! Post ID: %d, Language: %s, Translated Post ID: %d', 'xf-translator'),
                    $result['post_id'],
                    $result['language'],
                    $result['translated_post_id']
                ),
                'success'
            );
        } else {
            $this->add_notice(__('No pending translations to process or processing failed.', 'xf-translator'), 'info');
        }
    }
    
    /**
     * Create queue entries when a new post is created
     *
     * @param int $post_id Post ID
     * @param WP_Post|bool $post Post object or false
     */
    public function create_translation_queue_entries($post_id, $post = null) {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        if (Xf_Translator_Processor::is_creating_translated_post()) {
            return;
        }
        
        // Get post object if not provided
        if (!$post) {
            $post = get_post($post_id);
        }
        
        if (!$post) {
            return;
        }
        
        // Only process published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Skip if this is already a translated post (has original post ID meta)
        if (get_post_meta($post_id, '_api_translator_original_post_id', true) || 
            get_post_meta($post_id, '_xf_translator_original_post_id', true)) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Get languages from settings
        $languages = $this->settings->get('languages', array());
        
        if (empty($languages)) {
            return; // No languages configured
        }
        
        // Check if queue entries already exist for this post
        $existing_entries = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE parent_post_id = %d",
            $post_id
        ));
        
        // Only create entries if they don't already exist
        if ($existing_entries > 0) {
            return;
        }
        
        // Create queue entry for each language
        foreach ($languages as $language) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'parent_post_id' => $post_id,
                    'translated_post_id' => null,
                    'lng' => $language['name'],
                    'status' => 'pending',
                    'type' => 'NEW',
                    'created' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s')
            );
            
            // Log if insert failed
            if ($result === false) {
                error_log('XF Translator: Failed to insert queue entry for post ' . $post_id . ', language: ' . $language['name']);
            }
        }
    }
    
    /**
     * Create queue entries after post is inserted (alternative hook)
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     * @param WP_Post|null $post_before Post object before update
     */
    public function create_translation_queue_entries_after_insert($post_id, $post, $update, $post_before = null) {
        // Only process new posts, not updates
        if ($update) {
            return;
        }
        
        // Call the main function
        $this->create_translation_queue_entries($post_id, $post);
    }
    
    /**
     * Create queue entries when post status transitions to publish
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     */
    public function create_translation_queue_entries_on_publish($new_status, $old_status, $post) {
        // Only process when transitioning to publish
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        // Call the main function
        $this->create_translation_queue_entries($post->ID, $post);
    }
    
    /**
     * Handle analyze posts action
     * Analyzes all English posts and pages and adds missing translations to queue
     */
    public function handle_analyze_posts() {
        // Get selected post types from form (support both single and multiple)
        $selected_post_types = array();
        if (isset($_POST['analyze_post_type'])) {
            if (is_array($_POST['analyze_post_type'])) {
                $selected_post_types = array_map('sanitize_text_field', $_POST['analyze_post_type']);
            } else {
                $selected_post_types = array(sanitize_text_field($_POST['analyze_post_type']));
            }
        }
        
        // Default to 'post' if nothing selected
        if (empty($selected_post_types)) {
            $selected_post_types = array('post');
        }
        
        // Filter out invalid post types and attachments
        $selected_post_types = array_filter($selected_post_types, function($type) {
            return post_type_exists($type) && $type !== 'attachment';
        });
        
        if (empty($selected_post_types)) {
            $this->add_notice(__('Invalid post type(s) selected.', 'xf-translator'), 'error');
            return;
        }

        // Optional date filters
        $start_date = isset($_POST['analyze_start_date']) ? sanitize_text_field($_POST['analyze_start_date']) : '';
        $end_date = isset($_POST['analyze_end_date']) ? sanitize_text_field($_POST['analyze_end_date']) : '';
        $start_datetime = null;
        $end_datetime = null;

        if (!empty($start_date)) {
            $start_datetime = DateTime::createFromFormat('Y-m-d', $start_date);
            if (!$start_datetime) {
                $this->add_notice(__('Invalid start date format. Please use YYYY-MM-DD.', 'xf-translator'), 'error');
                return;
            }
            $start_datetime->setTime(0, 0, 0);
        }

        if (!empty($end_date)) {
            $end_datetime = DateTime::createFromFormat('Y-m-d', $end_date);
            if (!$end_datetime) {
                $this->add_notice(__('Invalid end date format. Please use YYYY-MM-DD.', 'xf-translator'), 'error');
                return;
            }
            $end_datetime->setTime(23, 59, 59);
        }

        if ($start_datetime && $end_datetime && $start_datetime > $end_datetime) {
            $this->add_notice(__('Start date cannot be later than end date.', 'xf-translator'), 'error');
            return;
        }
        
        // Get languages
        $languages = $this->settings->get('languages', array());
        if (empty($languages)) {
            $this->add_notice(__('No languages configured. Please add languages in the Settings tab.', 'xf-translator'), 'error');
            return;
        }
        
        // Check if there's already an active job
        $existing_job = get_transient('xf_analyze_active_job');
        if ($existing_job && $existing_job['status'] === 'processing') {
            $this->add_notice(
                __('An analysis job is already running. Please wait for it to complete or clear it first.', 'xf-translator'),
                'warning'
            );
            return;
        }
        
        // Count total posts to analyze
        $total_posts = 0;
        foreach ($selected_post_types as $post_type) {
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'AND',
                    array('key' => '_xf_translator_original_post_id', 'compare' => 'NOT EXISTS'),
                    array('key' => '_api_translator_original_post_id', 'compare' => 'NOT EXISTS'),
                    array('key' => '_xf_translator_language', 'compare' => 'NOT EXISTS')
                )
            );
            
            // Add date filters if provided
            if (!empty($start_date) || !empty($end_date)) {
                $range = array('inclusive' => true);
                if (!empty($start_date)) {
                    $range['after'] = $start_date . ' 00:00:00';
                }
                if (!empty($end_date)) {
                    $range['before'] = $end_date . ' 23:59:59';
                }
                $args['date_query'] = array($range);
            }
            
            $posts = get_posts($args);
            $total_posts += count($posts);
        }
        
        if ($total_posts === 0) {
            $this->add_notice(
                __('No English posts found to analyze in the selected post types.', 'xf-translator'),
                'info'
            );
            return;
        }
        
        // Create job record
        $job_data = array(
            'job_id' => wp_generate_uuid4(),
            'post_types' => $selected_post_types,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'total_posts' => $total_posts,
            'processed_posts' => 0,
            'added_entries' => 0,
            'processed_post_ids' => array(), // Track processed post IDs
            'status' => 'processing',
            'created_at' => current_time('mysql'),
            'last_updated' => current_time('mysql')
        );
        
        // Store job with fixed key (only one active job at a time)
        set_transient('xf_analyze_active_job', $job_data, DAY_IN_SECONDS);
        
        // Get cron URL
        $cron_url = site_url('/wp-content/plugins/xf-translator/analyze-posts.php');
        
        $this->add_notice(
            sprintf(
                __('Analysis job created! Total posts to analyze: %d. The job will process 50 posts per run. Add the cron URL to your system cron to start processing.', 'xf-translator'),
                $total_posts
            ) . '<br><br><strong>' . __('Cron URL:', 'xf-translator') . '</strong> <code>' . esc_html($cron_url) . '</code><br>' .
            '<a href="' . esc_url($cron_url) . '" target="_blank" class="button">' . __('View Progress', 'xf-translator') . '</a>',
            'success'
        );
    }
    
    /**
     * AJAX handler: Get batch info and start processing
     */
    public function ajax_get_analyze_batch() {
        check_ajax_referer('xf_translator_ajax', 'nonce');
        
        $batch_id = get_transient('xf_analyze_current_batch');
        
        if (!$batch_id) {
            wp_send_json_error(array('message' => __('No active batch found.', 'xf-translator')));
            return;
        }
        
        $batch_data = get_transient('xf_analyze_batch_' . $batch_id);
        
        if (!$batch_data) {
            wp_send_json_error(array('message' => __('Batch data not found.', 'xf-translator')));
            return;
        }
        
        wp_send_json_success(array(
            'batch_id' => $batch_id,
            'total_posts' => $batch_data['total_posts'],
            'processed' => $batch_data['processed'],
            'added' => $batch_data['added'],
            'post_type_label' => $batch_data['post_type_label']
        ));
    }
    
    /**
     * AJAX handler: Process a batch of posts (for real-time updates when user is on page)
     */
    public function ajax_process_analyze_batch() {
        check_ajax_referer('xf_translator_ajax', 'nonce');
        
        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        if (empty($batch_id)) {
            wp_send_json_error(array('message' => __('Batch ID is required.', 'xf-translator')));
            return;
        }
        
        // Get batch data
        $batch_data = get_transient('xf_analyze_batch_' . $batch_id);
        
        if (!$batch_data) {
            wp_send_json_error(array('message' => __('Batch data not found.', 'xf-translator')));
            return;
        }
        
        // Process one batch and return status
        $result = $this->process_single_analyze_batch($batch_id, $offset);
        
        if ($result['completed']) {
            wp_send_json_success(array(
                'processed' => $result['processed'],
                'total' => $result['total'],
                'added' => $result['added'],
                'completed' => true,
                'message' => $result['message']
            ));
        } else {
            wp_send_json_success(array(
                'processed' => $result['processed'],
                'total' => $result['total'],
                'added' => $result['added'],
                'completed' => false,
                'batch_size' => $result['batch_size']
            ));
        }
    }
    
    /**
     * Process a single batch of posts (public method for standalone processor)
     * Returns result array with status information
     * 
     * @param string $batch_id The batch ID
     * @param int $offset Optional offset (if not provided, uses current processed count)
     * @return array Result array with status information
     */
    public function process_single_analyze_batch($batch_id, $offset = null) {
        $batch_size = 50; // Process 50 posts at a time
        
        // Get batch data
        $batch_data = get_transient('xf_analyze_batch_' . $batch_id);
        
        if (!$batch_data) {
            return array(
                'completed' => true,
                'processed' => 0,
                'total' => 0,
                'added' => 0,
                'message' => __('Batch data not found.', 'xf-translator')
            );
        }
        
        // Use provided offset or current processed count
        if ($offset === null) {
            $offset = $batch_data['processed'];
        }
        
        // Check if already completed
        if ($batch_data['processed'] >= $batch_data['total_posts']) {
            // Completed, clean up
            delete_transient('xf_analyze_batch_' . $batch_id);
            delete_transient('xf_analyze_current_batch');
            return array(
                'completed' => true,
                'processed' => $batch_data['processed'],
                'total' => $batch_data['total_posts'],
                'added' => $batch_data['added'],
                'message' => sprintf(
                    __('Analysis complete! Analyzed %d %s and added %d missing translations to the queue.', 'xf-translator'),
                    $batch_data['processed'],
                    strtolower($batch_data['post_type_label']),
                    $batch_data['added']
                )
            );
        }
        
        // Get posts for this batch
        $posts = $this->get_posts_batch($batch_data['post_type'], $offset, $batch_size);
        
        if (empty($posts)) {
            // No more posts to process
            delete_transient('xf_analyze_batch_' . $batch_id);
            delete_transient('xf_analyze_current_batch');
            return array(
                'completed' => true,
                'processed' => $batch_data['processed'],
                'total' => $batch_data['total_posts'],
                'added' => $batch_data['added'],
                'message' => sprintf(
                    __('Analysis complete! Analyzed %d %s and added %d missing translations to the queue.', 'xf-translator'),
                    $batch_data['processed'],
                    strtolower($batch_data['post_type_label']),
                    $batch_data['added']
                )
            );
        }
        
        // Process this batch
        $result = $this->process_analyze_batch($posts, $batch_data);
        
        // Update batch data
        $batch_data['processed'] += count($posts);
        $batch_data['added'] += $result['added'];
        set_transient('xf_analyze_batch_' . $batch_id, $batch_data, 2 * HOUR_IN_SECONDS);
        
        // Clean up if completed
        if ($batch_data['processed'] >= $batch_data['total_posts']) {
            delete_transient('xf_analyze_batch_' . $batch_id);
            delete_transient('xf_analyze_current_batch');
        }
        
        return array(
            'completed' => $batch_data['processed'] >= $batch_data['total_posts'],
            'processed' => $batch_data['processed'],
            'total' => $batch_data['total_posts'],
            'added' => $batch_data['added'],
            'batch_size' => count($posts),
            'message' => sprintf(
                __('Processed batch: %d/%d posts analyzed, %d added to queue.', 'xf-translator'),
                $batch_data['processed'],
                $batch_data['total_posts'],
                $batch_data['added']
            )
        );
    }
    
    /**
     * Get a batch of posts to process
     */
    private function get_posts_batch($post_type, $offset, $batch_size) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_xf_translator_original_post_id',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_api_translator_original_post_id',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_xf_translator_language',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        return get_posts($args);
    }
    
    /**
     * Process a batch of posts with optimized bulk queries
     */
    private function process_analyze_batch($posts, $batch_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        if (empty($posts)) {
            return array('added' => 0);
        }
        
        $post_ids = array_map(function($post) { return $post->ID; }, $posts);
        $languages = $batch_data['languages'];
        
        // Bulk fetch all translation meta in one query per language
        $translation_meta = $this->bulk_get_translation_meta($post_ids, $languages);
        
        // Bulk check existing queue entries
        $existing_entries = $this->bulk_check_existing_entries($post_ids, $languages);
        
        // Prepare bulk insert values
        $values = array();
        $placeholders = array();
        
        foreach ($posts as $post) {
            foreach ($languages as $language) {
                $key = $post->ID . '_' . $language['name'];
                
                // Check if translation exists
                $has_translation = isset($translation_meta[$key]) && !empty($translation_meta[$key]);
                
                // Check if queue entry already exists
                $has_queue_entry = isset($existing_entries[$key]);
                
                // Only add if translation doesn't exist and queue entry doesn't exist
                if (!$has_translation && !$has_queue_entry) {
                    $values[] = $wpdb->prepare(
                        "(%d, NULL, %s, 'pending', 'OLD', %s)",
                        $post->ID,
                        $language['name'],
                        current_time('mysql')
                    );
                }
            }
        }
        
        // Bulk insert if we have values
        $added = 0;
        if (!empty($values)) {
            $query = "INSERT INTO $table_name (parent_post_id, translated_post_id, lng, status, type, created) VALUES " . implode(', ', $values);
            $result = $wpdb->query($query);
            
            if ($result !== false) {
                $added = count($values);
            } else {
                error_log('XF Translator: Bulk insert failed: ' . $wpdb->last_error);
            }
        }
        
        return array('added' => $added);
    }
    
    /**
     * Bulk fetch translation meta for multiple posts and languages
     */
    public function bulk_get_translation_meta($post_ids, $languages) {
        if (empty($post_ids) || empty($languages)) {
            return array();
        }
        
        global $wpdb;
        $meta_keys = array();
        
        // Build meta keys for all language prefixes
        foreach ($languages as $language) {
            $meta_keys[] = '_xf_translator_translated_post_' . $language['prefix'];
        }
        
        // Sanitize post IDs and meta keys
        $post_ids = array_map('intval', $post_ids);
        $post_ids = array_filter($post_ids);
        $post_ids_escaped = implode(',', $post_ids);
        
        $meta_keys_escaped = array();
        foreach ($meta_keys as $key) {
            $meta_keys_escaped[] = $wpdb->prepare('%s', $key);
        }
        $meta_keys_escaped = implode(',', $meta_keys_escaped);
        
        // Build query to get all translation meta in one go
        // Using esc_sql for safety since we've already sanitized
        $query = "SELECT post_id, meta_key, meta_value 
                  FROM {$wpdb->postmeta} 
                  WHERE post_id IN ($post_ids_escaped) 
                  AND meta_key IN ($meta_keys_escaped)";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Build result map: post_id_language_name => translated_post_id
        $translation_meta = array();
        foreach ($results as $row) {
            // Extract language prefix from meta key
            $meta_key = $row['meta_key'];
            foreach ($languages as $language) {
                if ($meta_key === '_xf_translator_translated_post_' . $language['prefix']) {
                    $key = $row['post_id'] . '_' . $language['name'];
                    $translated_post_id = intval($row['meta_value']);
                    
                    // Verify the translated post exists
                    if ($translated_post_id > 0 && get_post($translated_post_id)) {
                        $translation_meta[$key] = $translated_post_id;
                    }
                    break;
                }
            }
        }
        
        return $translation_meta;
    }
    
    /**
     * Bulk check existing queue entries for multiple posts and languages
     */
    public function bulk_check_existing_entries($post_ids, $languages) {
        if (empty($post_ids) || empty($languages)) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Sanitize post IDs
        $post_ids = array_map('intval', $post_ids);
        $post_ids = array_filter($post_ids);
        $post_ids_escaped = implode(',', $post_ids);
        
        // Sanitize language names
        $language_names = array_map(function($lang) { return sanitize_text_field($lang['name']); }, $languages);
        $language_names_escaped = array();
        foreach ($language_names as $name) {
            $language_names_escaped[] = $wpdb->prepare('%s', $name);
        }
        $language_names_escaped = implode(',', $language_names_escaped);
        
        // Build query
        $query = "SELECT parent_post_id, lng 
                  FROM $table_name 
                  WHERE parent_post_id IN ($post_ids_escaped) 
                  AND lng IN ($language_names_escaped)";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Build result map: post_id_language_name => true
        $existing_entries = array();
        foreach ($results as $row) {
            $key = $row['parent_post_id'] . '_' . $row['lng'];
            $existing_entries[$key] = true;
        }
        
        return $existing_entries;
    }
    
    /**
     * Handle retry failed queue entry
     */
    private function handle_retry_queue_entry() {
        if (!isset($_POST['queue_entry_id'])) {
            $this->add_notice(__('Queue entry ID is required.', 'xf-translator'), 'error');
            return;
        }
        
        $queue_entry_id = intval($_POST['queue_entry_id']);
        
        if ($queue_entry_id <= 0) {
            $this->add_notice(__('Invalid queue entry ID.', 'xf-translator'), 'error');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Check if queue entry exists and is failed
        $queue_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $queue_entry_id
        ), ARRAY_A);
        
        if (!$queue_entry) {
            $this->add_notice(__('Queue entry not found.', 'xf-translator'), 'error');
            return;
        }
        
        // Allow retry for both failed and processing status (processing items might be stuck)
        if ($queue_entry['status'] !== 'failed' && $queue_entry['status'] !== 'processing') {
            $this->add_notice(__('Only failed or processing queue entries can be retried.', 'xf-translator'), 'error');
            return;
        }
        
        // If status is processing, reset it to pending first
        if ($queue_entry['status'] === 'processing') {
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'pending',
                    'error_message' => null
                ),
                array('id' => $queue_entry_id),
                array('%s', '%s'),
                array('%d')
            );
        }
        
        // Log retry attempt
        if (class_exists('Xf_Translator_Logger')) {
            Xf_Translator_Logger::info('Retrying queue entry #' . $queue_entry_id . ' (Post ID: ' . $queue_entry['parent_post_id'] . ', Language: ' . $queue_entry['lng'] . ', Previous status: ' . $queue_entry['status'] . ')');
        } else {
            error_log('XF Translator: Retrying queue entry #' . $queue_entry_id . ' (Post ID: ' . $queue_entry['parent_post_id'] . ', Language: ' . $queue_entry['lng'] . ', Previous status: ' . $queue_entry['status'] . ')');
        }
        
        // Increase PHP execution time limit significantly for retry operations
        // This helps prevent timeouts during post creation/update and meta field operations
        $retry_time_limit = 300; // 5 minutes - enough for heavy operations
        if (function_exists('set_time_limit')) {
            @set_time_limit($retry_time_limit);
        }
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', $retry_time_limit);
        }
        
        // Start output buffering to prevent premature connection closure
        if (!ob_get_level()) {
            ob_start();
        }
        
        // Flush output buffer periodically to keep connection alive
        // This helps prevent 502 errors from web server timeouts
        register_shutdown_function(function() use ($queue_entry_id) {
            if (ob_get_level()) {
                ob_end_flush();
            }
        });
        
        // Send initial response to keep connection alive
        if (ob_get_level()) {
            echo str_repeat(' ', 1024); // Send 1KB of whitespace
            ob_flush();
            flush();
        }
        
        // Process the translation immediately (this will trigger API logging)
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        $processor = new Xf_Translator_Processor();
        
        // Track start time for timeout detection
        $start_time = time();
        $max_processing_time = 240; // 4 minutes max processing time
        
        try {
            $result = $processor->process_queue_entry_by_id($queue_entry_id);
            
            // Check if processing took too long (might indicate timeout)
            $processing_time = time() - $start_time;
            if ($processing_time > $max_processing_time) {
                if (class_exists('Xf_Translator_Logger')) {
                    Xf_Translator_Logger::warning('Retry processing took ' . $processing_time . ' seconds (may have timed out)');
                } else {
                    error_log('XF Translator: Retry processing took ' . $processing_time . ' seconds (may have timed out)');
                }
            }
            
            if ($result !== false && isset($result['success']) && $result['success']) {
                $this->add_notice(
                    sprintf(
                        __('Queue entry #%d has been successfully processed. Translation completed.', 'xf-translator'),
                        $queue_entry_id
                    ),
                    'success'
                );
            } else {
                $error_message = $processor->get_last_error();
                
                // Check if error might be timeout-related
                if (empty($error_message) || strpos(strtolower($error_message), 'timeout') !== false) {
                    $error_message = __('Processing timed out. The queue entry has been reset to pending status and will be processed by the background queue processor.', 'xf-translator');
                    
                    // Ensure status is set to pending for background processing
                    $wpdb->update(
                        $table_name,
                        array('status' => 'pending'),
                        array('id' => $queue_entry_id),
                        array('%s'),
                        array('%d')
                    );
                }
                
                $this->add_notice(
                    sprintf(
                        __('Failed to retry queue entry #%d. %s', 'xf-translator'),
                        $queue_entry_id,
                        $error_message ?: __('Please check the error logs for details.', 'xf-translator')
                    ),
                    'error'
                );
            }
        } catch (Exception $e) {
            // Catch any exceptions that might occur during processing
            $error_message = __('An error occurred during processing: ', 'xf-translator') . $e->getMessage();
            
            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::error('Retry exception: ' . $e->getMessage());
            } else {
                error_log('XF Translator Retry Exception: ' . $e->getMessage());
            }
            
            // Reset to pending so it can be retried again
            $wpdb->update(
                $table_name,
                array('status' => 'pending'),
                array('id' => $queue_entry_id),
                array('%s'),
                array('%d')
            );
            
            $this->add_notice(
                sprintf(
                    __('Error retrying queue entry #%d. %s The entry has been reset to pending status.', 'xf-translator'),
                    $queue_entry_id,
                    $error_message
                ),
                'error'
            );
        }
    }
    
    /**
     * Handle reset stuck processing queue entries
     * Resets entries that have been in "processing" status for more than 5 minutes
     *
     * @since    1.0.0
     */
    private function handle_reset_stuck_processing() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Calculate the cutoff time (5 minutes ago)
        $cutoff_time = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        
        // Find all processing entries older than 5 minutes
        $stuck_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id, parent_post_id, lng, type, created 
             FROM $table_name 
             WHERE status = 'processing' 
             AND created <= %s
             ORDER BY id ASC",
            $cutoff_time
        ), ARRAY_A);
        
        if (empty($stuck_entries)) {
            $this->add_notice(
                __('No stuck processing jobs found. All processing jobs are less than 5 minutes old.', 'xf-translator'),
                'info'
            );
            return;
        }
        
        $reset_count = 0;
        $reset_ids = array();
        
        foreach ($stuck_entries as $entry) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'status' => 'pending',
                    'error_message' => null
                ),
                array('id' => $entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $reset_count++;
                $reset_ids[] = $entry['id'];
            }
        }
        
        if ($reset_count > 0) {
            // Log the reset action
            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::info(
                    sprintf(
                        'Reset %d stuck processing queue entries (IDs: %s)',
                        $reset_count,
                        implode(', ', $reset_ids)
                    )
                );
            } else {
                error_log(sprintf(
                    'XF Translator: Reset %d stuck processing queue entries (IDs: %s)',
                    $reset_count,
                    implode(', ', $reset_ids)
                ));
            }
            
            $this->add_notice(
                sprintf(
                    __('Successfully reset %d stuck processing job(s) back to pending status. They will be processed by the background queue processor.', 'xf-translator'),
                    $reset_count
                ),
                'success'
            );
        } else {
            $this->add_notice(
                __('Failed to reset stuck processing jobs. Please try again.', 'xf-translator'),
                'error'
            );
        }
    }
    
    /**
     * Handle post edit - detect changes and create EDIT queue entries
     *
     * @param int $post_id Post ID
     * @param WP_Post $post_after Post object after update
     * @param WP_Post $post_before Post object before update
     */
    public function handle_post_edit($post_id, $post_after, $post_before) {
        // DISABLED: Automatic translation on post edits is disabled
        // To re-enable, remove or comment out this return statement
        return;
        
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        if (Xf_Translator_Processor::is_creating_translated_post()) {
            return;
        }
        
        // Only process published posts
        if ($post_after->post_status !== 'publish') {
            return;
        }
        
        // Skip if this is already a translated post (has original post ID meta)
        if (get_post_meta($post_id, '_api_translator_original_post_id', true) || 
            get_post_meta($post_id, '_xf_translator_original_post_id', true)) {
            return;
        }
        
        // Detect which fields changed
        $edited_fields = array();
        
        // Compare title, content, excerpt using post_before object
        if ($post_after->post_title !== $post_before->post_title) {
            $edited_fields[] = 'title';
        }
        if ($post_after->post_content !== $post_before->post_content) {
            $edited_fields[] = 'content';
        }
        if ($post_after->post_excerpt !== $post_before->post_excerpt) {
            $edited_fields[] = 'excerpt';
        }
        
        // Check ACF fields if they exist
        if (function_exists('get_fields')) {
            $fields_after = get_fields($post_id);
            
            if ($fields_after && is_array($fields_after)) {
                foreach ($fields_after as $field_key => $field_value) {
                    // Skip internal ACF fields
                    if (strpos($field_key, '_') === 0) {
                        continue;
                    }
                    
                    // Get previous value from stored meta
                    $previous_value = get_post_meta($post_id, '_xf_translator_prev_acf_' . $field_key, true);
                    
                    // Normalize both values for consistent comparison
                    $previous_normalized = $this->normalize_value_for_comparison($previous_value);
                    $current_normalized = $this->normalize_value_for_comparison($field_value);
                    
                    // Compare normalized values
                    if ($previous_normalized !== $current_normalized) {
                        $edited_fields[] = 'acf_' . $field_key;
                        // Update stored value for next comparison (store normalized version)
                        update_post_meta($post_id, '_xf_translator_prev_acf_' . $field_key, $current_normalized);
                    }
                }
            }
        }
        
        // Check WordPress native custom fields
        // Use a delayed check because custom fields might be saved after post_updated fires
        // Schedule a check after a short delay to ensure all meta is saved
        if (!wp_next_scheduled('xf_translator_check_custom_fields', array($post_id))) {
            wp_schedule_single_event(time() + 1, 'xf_translator_check_custom_fields', array($post_id));
        }
        
        // If no fields changed, return
        if (empty($edited_fields)) {
            return;
        }
        
        // Use helper function to create EDIT queue entries
        $this->create_edit_queue_entries($post_id, $edited_fields);
    }
    
    /**
     * Store field values on save_post (early hook to catch custom fields)
     * This is called when post is saved, before custom fields might be updated
     */
    public function store_pre_edit_values_on_save($post_id) {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        if (Xf_Translator_Processor::is_creating_translated_post()) {
            return;
        }
        
        // Skip if this is a translated post
        if (get_post_meta($post_id, '_api_translator_original_post_id', true) || 
            get_post_meta($post_id, '_xf_translator_original_post_id', true)) {
            return;
        }
        
        // Only store if post is published
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Store custom field values before they might be updated
        $this->store_custom_field_values($post_id);
    }
    
    /**
     * Store custom field values before meta is updated
     * This hooks into update_post_metadata filter BEFORE the update happens
     * 
     * @param null|bool $check Whether to allow updating metadata
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     * @param mixed $prev_value Previous value (if updating)
     * @return null|bool Return null to allow update, or bool to short-circuit
     */
    public function store_before_meta_update($check, $post_id, $meta_key, $meta_value, $prev_value = null) {
        // Skip our internal meta keys
        if (strpos($meta_key, '_xf_translator_') === 0 || 
            strpos($meta_key, '_api_translator_') === 0 ||
            strpos($meta_key, '_') === 0) { // Skip all internal meta keys
            return;
        }
        
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        if (Xf_Translator_Processor::is_creating_translated_post()) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Skip if this is a translated post
        if (get_post_meta($post_id, '_api_translator_original_post_id', true) || 
            get_post_meta($post_id, '_xf_translator_original_post_id', true)) {
            return;
        }
        
        // Skip if it's an ACF field (we handle those separately)
        if (function_exists('get_fields') && function_exists('get_field_object') && get_field_object($meta_key, $post_id)) {
            return;
        }
        
        // Get current value BEFORE update and store it
        // Use $prev_value if provided (WordPress passes it), otherwise get from database
        if ($prev_value !== null) {
            $current_value = $prev_value;
        } else {
            $current_value = get_post_meta($post_id, $meta_key, true);
        }
        
        if ($current_value !== '' && $current_value !== false) {
            // Store normalized value for consistent comparison
            $value_to_store = $this->normalize_value_for_comparison($current_value);
            update_post_meta($post_id, '_xf_translator_prev_meta_' . $meta_key, $value_to_store);
            error_log('XF Translator: Stored previous value for custom field ' . $meta_key . ' on post ' . $post_id . ': ' . substr($value_to_store, 0, 50));
        }
        
        // Return null to allow the update to proceed
        return null;
    }
    
    /**
     * Store custom field values for comparison
     * Helper function to avoid code duplication
     */
    private function store_custom_field_values($post_id) {
        // Store WordPress native custom fields for comparison
        // Get all post meta and store custom fields (not starting with _)
        $all_meta = get_post_meta($post_id);
        if ($all_meta && is_array($all_meta)) {
            foreach ($all_meta as $meta_key => $meta_values) {
                // Skip internal meta keys and our own meta keys
                if (strpos($meta_key, '_') === 0) {
                    continue;
                }
                
                // Skip if it's an ACF field (we handle those separately)
                if (function_exists('get_fields') && function_exists('get_field_object') && get_field_object($meta_key, $post_id)) {
                    continue;
                }
                
                // Store normalized value for comparison
                $meta_value = isset($meta_values[0]) ? $meta_values[0] : '';
                $value_to_store = $this->normalize_value_for_comparison($meta_value);
                update_post_meta($post_id, '_xf_translator_prev_meta_' . $meta_key, $value_to_store);
            }
        }
    }
    
    /**
     * Store ACF field values before update (helper for edit detection)
     * This is called before post update to capture previous values
     */
    public function store_pre_edit_values($post_id) {
        // Skip if this is a translated post
        if (get_post_meta($post_id, '_xf_translator_original_post_id', true) || 
            get_post_meta($post_id, '_api_translator_original_post_id', true)) {
            return;
        }
        
        // Store post fields for comparison
        $post = get_post($post_id);
        if ($post) {
            update_post_meta($post_id, '_xf_translator_prev_title', $post->post_title);
            update_post_meta($post_id, '_xf_translator_prev_content', $post->post_content);
            update_post_meta($post_id, '_xf_translator_prev_excerpt', $post->post_excerpt);
        }
        
        // Store ACF field values for comparison
        if (function_exists('get_fields')) {
            $fields = get_fields($post_id);
            if ($fields && is_array($fields)) {
                foreach ($fields as $field_key => $field_value) {
                    // Skip internal ACF fields
                    if (strpos($field_key, '_') === 0) {
                        continue;
                    }
                    
                    // Store normalized value for consistent comparison
                    $value_to_store = $this->normalize_value_for_comparison($field_value);
                    update_post_meta($post_id, '_xf_translator_prev_acf_' . $field_key, $value_to_store);
                }
            }
        }
        
        // Store WordPress native custom fields for comparison
        $this->store_custom_field_values($post_id);
    }
    
    /**
     * Handle ACF save - detect ACF field changes and create EDIT queue entries
     * This is called after ACF fields are saved
     *
     * @param int $post_id Post ID
     */
    public function handle_acf_save($post_id) {
        // DISABLED: Automatic translation on post edits is disabled
        // To re-enable, remove or comment out this return statement
        return;
        
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        if (Xf_Translator_Processor::is_creating_translated_post()) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Skip if this is already a translated post
        if (get_post_meta($post_id, '_api_translator_original_post_id', true) || 
            get_post_meta($post_id, '_xf_translator_original_post_id', true)) {
            return;
        }
        
        // Detect which ACF fields changed
        $edited_fields = array();
        
        if (function_exists('get_fields')) {
            $fields_after = get_fields($post_id);
            
            if ($fields_after && is_array($fields_after)) {
                foreach ($fields_after as $field_key => $field_value) {
                    // Skip internal ACF fields
                    if (strpos($field_key, '_') === 0) {
                        continue;
                    }
                    
                    // Get previous value from stored meta
                    $previous_value = get_post_meta($post_id, '_xf_translator_prev_acf_' . $field_key, true);
                    
                    // Normalize both values for consistent comparison
                    $previous_normalized = $this->normalize_value_for_comparison($previous_value);
                    $current_normalized = $this->normalize_value_for_comparison($field_value);
                    
                    // Compare normalized values
                    if ($previous_normalized !== $current_normalized) {
                        $edited_fields[] = 'acf_' . $field_key;
                        
                        // Update stored value for next comparison (store normalized version)
                        update_post_meta($post_id, '_xf_translator_prev_acf_' . $field_key, $current_normalized);
                    }
                }
            }
        }
        
        // If ACF fields changed, create EDIT queue entries
        if (!empty($edited_fields)) {
            $this->create_edit_queue_entries($post_id, $edited_fields);
        }
    }
    
    /**
     * Handle meta update - detect custom field changes (including ACF)
     * This is a fallback for fields that might not trigger ACF hooks
     * Uses a debounce mechanism to batch multiple field updates
     *
     * @param int $meta_id Meta ID
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     */
    public function handle_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // DISABLED: Automatic translation on post edits is disabled
        // To re-enable, remove or comment out this return statement
        return;
        
        // Skip our internal meta keys
        if (strpos($meta_key, '_xf_translator_') === 0 || 
            strpos($meta_key, '_api_translator_') === 0 ||
            strpos($meta_key, '_') === 0) { // Skip all internal ACF fields
            return;
        }
        
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        if (Xf_Translator_Processor::is_creating_translated_post()) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Skip if this is already a translated post
        if (get_post_meta($post_id, '_api_translator_original_post_id', true) || 
            get_post_meta($post_id, '_xf_translator_original_post_id', true)) {
            return;
        }
        
        // Check if this is an ACF field or WordPress native custom field
        $is_acf_field = false;
        if (function_exists('get_fields') && get_field_object($meta_key, $post_id)) {
            $is_acf_field = true;
        }
        
        // Get previous value (check both ACF and native custom fields)
        $prev_meta_key = $is_acf_field ? '_xf_translator_prev_acf_' . $meta_key : '_xf_translator_prev_meta_' . $meta_key;
        $previous_value = get_post_meta($post_id, $prev_meta_key, true);
        
        // Normalize both values for consistent comparison
        $previous_normalized = $this->normalize_value_for_comparison($previous_value);
        $current_normalized = $this->normalize_value_for_comparison($meta_value);
        
        // If no previous value stored, this might be first time tracking
        // Store current value for next time and skip (we need a baseline to compare against)
        if ($previous_normalized === '') {
            update_post_meta($post_id, $prev_meta_key, $current_normalized);
            error_log('XF Translator: First time tracking ' . ($is_acf_field ? 'ACF' : 'custom') . ' field ' . $meta_key . ' for post ' . $post_id . ', storing value for next comparison');
            return;
        }
        
        // Compare normalized values
        if ($previous_normalized !== $current_normalized) {
            // Update stored value for next comparison (store normalized version)
            update_post_meta($post_id, $prev_meta_key, $current_normalized);
            
            // Determine field prefix
            $field_prefix = $is_acf_field ? 'acf_' : 'meta_';
            
            error_log('XF Translator: Detected ' . ($is_acf_field ? 'ACF' : 'custom') . ' field change: ' . $meta_key . ' for post ' . $post_id . ' (prev: ' . substr($previous_normalized, 0, 50) . ' -> new: ' . substr($current_normalized, 0, 50) . ')');
            
            // Create EDIT queue entries immediately (don't batch for native custom fields to ensure they're processed)
            $edited_fields = array($field_prefix . $meta_key);
            $this->create_edit_queue_entries($post_id, $edited_fields);
        } else {
            error_log('XF Translator: No change detected for ' . ($is_acf_field ? 'ACF' : 'custom') . ' field ' . $meta_key . ' for post ' . $post_id);
        }
    }
    
    /**
     * Check custom fields after post update (delayed check)
     * This ensures we catch custom fields that might be saved after post_updated fires
     *
     * @param int $post_id Post ID
     */
    public function check_custom_fields_after_update($post_id) {
        // DISABLED: Automatic translation on post edits is disabled
        // To re-enable, remove or comment out this return statement
        return;
        
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        if (Xf_Translator_Processor::is_creating_translated_post()) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }
        
        // Skip if this is already a translated post
        if (get_post_meta($post_id, '_api_translator_original_post_id', true) || 
            get_post_meta($post_id, '_xf_translator_original_post_id', true)) {
            return;
        }
        
        // Detect which WordPress native custom fields changed
        $edited_fields = array();
        
        $all_meta_after = get_post_meta($post_id);
        if ($all_meta_after && is_array($all_meta_after)) {
            foreach ($all_meta_after as $meta_key => $meta_values) {
                // Skip internal meta keys and our own meta keys
                if (strpos($meta_key, '_') === 0) {
                    continue;
                }
                
                // Skip if it's an ACF field (we handle those separately)
                if (function_exists('get_fields') && function_exists('get_field_object') && get_field_object($meta_key, $post_id)) {
                    continue;
                }
                
                // Get previous value from stored meta
                $previous_value = get_post_meta($post_id, '_xf_translator_prev_meta_' . $meta_key, true);
                $current_value = isset($meta_values[0]) ? $meta_values[0] : '';
                
                // Normalize both values for consistent comparison
                $previous_normalized = $this->normalize_value_for_comparison($previous_value);
                $current_normalized = $this->normalize_value_for_comparison($current_value);
                
                // Compare normalized values
                if ($previous_normalized !== $current_normalized && $previous_normalized !== '') {
                    $edited_fields[] = 'meta_' . $meta_key;
                    
                    // Update stored value for next comparison (store normalized version)
                    update_post_meta($post_id, '_xf_translator_prev_meta_' . $meta_key, $current_normalized);
                } elseif ($previous_normalized === '' && !empty($current_normalized)) {
                    // First time tracking this field - store it for next time
                    update_post_meta($post_id, '_xf_translator_prev_meta_' . $meta_key, $current_normalized);
                }
            }
        }
        
        // If custom fields changed, create EDIT queue entries
        if (!empty($edited_fields)) {
            error_log('XF Translator: Detected custom field changes for post ' . $post_id . ': ' . implode(', ', $edited_fields));
            $this->create_edit_queue_entries($post_id, $edited_fields);
        }
    }
    
    /**
     * Process pending ACF field updates (batched)
     * Called via scheduled action to batch multiple field updates
     *
     * @param int $post_id Post ID
     */
    public function process_pending_acf_fields($post_id) {
        $pending_fields = get_transient('_xf_translator_pending_fields_' . $post_id);
        
        if (is_array($pending_fields) && !empty($pending_fields)) {
            // Remove duplicates
            $edited_fields = array_unique($pending_fields);
            
            // Create EDIT queue entries
            $this->create_edit_queue_entries($post_id, $edited_fields);
            
            // Clear the transient
            delete_transient('_xf_translator_pending_fields_' . $post_id);
        }
    }
    
    /**
     * Normalize value for comparison to ensure consistent comparison
     * Handles arrays, strings, and edge cases
     *
     * @param mixed $value The value to normalize
     * @return string Normalized string value for comparison
     */
    private function normalize_value_for_comparison($value) {
        // Handle null/empty
        if ($value === null || $value === false) {
            return '';
        }
        
        // Handle arrays - sort keys for consistent serialization
        if (is_array($value)) {
            // Recursively sort array keys
            $this->recursive_ksort($value);
            return serialize($value);
        }
        
        // Handle objects - serialize them
        if (is_object($value)) {
            return serialize($value);
        }
        
        // Handle strings - trim whitespace and normalize line endings
        $normalized = trim((string)$value);
        // Normalize line endings (Windows \r\n, Mac \r, Unix \n -> all to \n)
        $normalized = str_replace(array("\r\n", "\r"), "\n", $normalized);
        
        return $normalized;
    }
    
    /**
     * Recursively sort array keys for consistent serialization
     *
     * @param array $array Array to sort
     */
    private function recursive_ksort(&$array) {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $this->recursive_ksort($value);
            }
        }
        ksort($array);
    }
    
    /**
     * Create EDIT queue entries for edited fields
     * Helper function to avoid code duplication
     *
     * @param int $post_id Post ID
     * @param array $edited_fields Array of edited field names
     */
    private function create_edit_queue_entries($post_id, $edited_fields) {
        if (empty($edited_fields)) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Get languages from settings
        $languages = $this->settings->get('languages', array());
        
        if (empty($languages)) {
            return; // No languages configured
        }
        
        // Create EDIT queue entries for each language that has a translated post
        foreach ($languages as $language) {
            // Check if translated post exists
            $translated_post_id = get_post_meta($post_id, '_xf_translator_translated_post_' . $language['prefix'], true);
            
            if ($translated_post_id && get_post($translated_post_id)) {
                // Check if EDIT entry already exists for this post and language
                $existing_edit = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                     WHERE parent_post_id = %d 
                     AND lng = %s 
                     AND type = 'EDIT' 
                     AND status = 'pending'",
                    $post_id,
                    $language['name']
                ));
                
                // Only create if no pending EDIT entry exists
                if ($existing_edit == 0) {
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'parent_post_id' => $post_id,
                            'translated_post_id' => $translated_post_id,
                            'lng' => $language['name'],
                            'status' => 'pending',
                            'type' => 'EDIT',
                            'edited_fields' => json_encode($edited_fields),
                            'created' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
                    );
                    
                    if ($result === false) {
                        error_log('XF Translator: Failed to insert EDIT queue entry for post ' . $post_id . ', language: ' . $language['name']);
                    } else {
                        error_log('XF Translator: Created EDIT queue entry for post ' . $post_id . ', language: ' . $language['name'] . ', fields: ' . implode(', ', $edited_fields));
                    }
                }
            }
        }
    }

    /**
     * Handle menu translation request
     */
    private function handle_translate_menu() {
        if (!isset($_POST['menu_id']) || !isset($_POST['target_language'])) {
            add_settings_error('api_translator_messages', 'menu_translation_error', __('Menu ID and target language are required.', 'xf-translator'), 'error');
            return;
        }
        
        $menu_id = intval($_POST['menu_id']);
        $target_language = sanitize_text_field($_POST['target_language']);
        
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-menu-translation-processor.php';
        $processor = new Xf_Translator_Menu_Processor();
        
        $result = $processor->translate_menu($menu_id, $target_language);
        
        if ($result && !is_wp_error($result)) {
            add_settings_error('api_translator_messages', 'menu_translation_success', __('Menu translated successfully!', 'xf-translator'), 'success');
        } else {
            $error_message = is_wp_error($result) ? $result->get_error_message() : __('Failed to translate menu.', 'xf-translator');
            add_settings_error('api_translator_messages', 'menu_translation_error', $error_message, 'error');
        }
    }
    
    /**
     * Handle translating all menus into a single language
     */
    private function handle_translate_all_menus() {
        if (!isset($_POST['target_language'])) {
            add_settings_error('api_translator_messages', 'menu_translation_error', __('Target language is required.', 'xf-translator'), 'error');
            return;
        }
        
        $target_language = sanitize_text_field($_POST['target_language']);
        $languages = $this->settings->get('languages', array());
        $target_language_prefix = '';
        
        foreach ($languages as $language) {
            if ($language['name'] === $target_language) {
                $target_language_prefix = $language['prefix'];
                break;
            }
        }
        
        if (empty($target_language_prefix)) {
            add_settings_error('api_translator_messages', 'menu_translation_error', __('Invalid target language.', 'xf-translator'), 'error');
            return;
        }
        
        $all_menus = wp_get_nav_menus();
        
        if (empty($all_menus)) {
            add_settings_error('api_translator_messages', 'menu_translation_error', __('No menus found to translate.', 'xf-translator'), 'error');
            return;
        }
        
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-menu-translation-processor.php';
        $processor = new Xf_Translator_Menu_Processor();
        
        $translated_count = 0;
        $skipped_count = 0;
        $failed_menus = array();
        
        foreach ($all_menus as $menu) {
            // Only process original menus
            $original_menu_id = get_term_meta($menu->term_id, '_xf_translator_original_menu_id', true);
            if (!empty($original_menu_id)) {
                continue;
            }
            
            // Skip menu if translation already exists
            $translated_menu_id = get_term_meta($menu->term_id, '_xf_translator_menu_' . $target_language_prefix, true);
            $translated_menu = $translated_menu_id ? wp_get_nav_menu_object($translated_menu_id) : false;
            
            if ($translated_menu) {
                $skipped_count++;
                continue;
            }
            
            $result = $processor->translate_menu($menu->term_id, $target_language);
            
            if ($result && !is_wp_error($result)) {
                $translated_count++;
            } else {
                $failed_menus[] = $menu->name;
            }
        }
        
        $message = sprintf(
            __('Bulk menu translation finished. %1$d translated, %2$d skipped, %3$d failed.', 'xf-translator'),
            $translated_count,
            $skipped_count,
            count($failed_menus)
        );
        
        $notice_type = empty($failed_menus) ? 'success' : 'warning';
        add_settings_error('api_translator_messages', 'menu_bulk_translation_summary', $message, $notice_type);
        
        if (!empty($failed_menus)) {
            $failed_list = implode(', ', $failed_menus);
            add_settings_error('api_translator_messages', 'menu_bulk_translation_failed', sprintf(__('Failed menus: %s', 'xf-translator'), $failed_list), 'error');
        }
    }
    
    /**
     * Handle individual menu item translation request
     */
    private function handle_translate_menu_item() {
        if (!isset($_POST['menu_item_id']) || !isset($_POST['target_language'])) {
            add_settings_error('api_translator_messages', 'menu_item_translation_error', __('Menu item ID and target language are required.', 'xf-translator'), 'error');
            return;
        }
        
        $menu_item_id = intval($_POST['menu_item_id']);
        $target_language = sanitize_text_field($_POST['target_language']);
        
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-menu-translation-processor.php';
        $processor = new Xf_Translator_Menu_Processor();
        
        $result = $processor->translate_menu_item($menu_item_id, $target_language);
        
        if ($result && !is_wp_error($result)) {
            add_settings_error('api_translator_messages', 'menu_item_translation_success', __('Menu item translated successfully!', 'xf-translator'), 'success');
        } else {
            $error_message = is_wp_error($result) ? $result->get_error_message() : __('Failed to translate menu item.', 'xf-translator');
            add_settings_error('api_translator_messages', 'menu_item_translation_error', $error_message, 'error');
        }
    }
    
    /**
     * Handle taxonomy translation request
     */
    private function handle_translate_taxonomy() {
        if (!isset($_POST['taxonomy']) || !isset($_POST['target_language'])) {
            add_settings_error('api_translator_messages', 'taxonomy_translation_error', __('Taxonomy and target language are required.', 'xf-translator'), 'error');
            return;
        }
        
        $taxonomy = sanitize_text_field($_POST['taxonomy']);
        $target_language = sanitize_text_field($_POST['target_language']);
        
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-taxonomy-translation-processor.php';
        $processor = new Xf_Translator_Taxonomy_Processor();
        
        $result = $processor->translate_taxonomy($taxonomy, $target_language);
        
        if ($result && !is_wp_error($result)) {
            $translated_count = is_array($result) ? count($result) : 0;
            add_settings_error('api_translator_messages', 'taxonomy_translation_success', sprintf(__('Taxonomy translated successfully! %d terms translated.', 'xf-translator'), $translated_count), 'success');
        } else {
            $error_message = is_wp_error($result) ? $result->get_error_message() : __('Failed to translate taxonomy.', 'xf-translator');
            add_settings_error('api_translator_messages', 'taxonomy_translation_error', $error_message, 'error');
        }
    }
    
    /**
     * Handle individual term translation request
     */
    private function handle_translate_term() {
        if (!isset($_POST['term_id']) || !isset($_POST['target_language'])) {
            add_settings_error('api_translator_messages', 'term_translation_error', __('Term ID and target language are required.', 'xf-translator'), 'error');
            return;
        }
        
        $term_id = intval($_POST['term_id']);
        $target_language = sanitize_text_field($_POST['target_language']);
        
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-taxonomy-translation-processor.php';
        $processor = new Xf_Translator_Taxonomy_Processor();
        
        $result = $processor->translate_term($term_id, $target_language);
        
        if ($result && !is_wp_error($result)) {
            add_settings_error('api_translator_messages', 'term_translation_success', __('Term translated successfully!', 'xf-translator'), 'success');
        } else {
            $error_message = is_wp_error($result) ? $result->get_error_message() : __('Failed to translate term.', 'xf-translator');
            add_settings_error('api_translator_messages', 'term_translation_error', $error_message, 'error');
        }
    }
    
    /**
     * AJAX handler to get post content
     */
    public function ajax_get_post_content() {
        check_ajax_referer('xf_translator_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'xf-translator')));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID', 'xf-translator')));
            return;
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'xf-translator')));
            return;
        }
        
        wp_send_json_success(array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt
        ));
    }
    
    /**
     * AJAX handler to test translation
     */
    public function ajax_test_translation() {
        // Increase execution time for test translation
        @set_time_limit(300);
        
        error_log('XF Translator: Test translation AJAX called');
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            error_log('XF Translator: Invalid nonce in test translation');
            wp_send_json_error(array('message' => __('Invalid nonce', 'xf-translator')));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            error_log('XF Translator: Unauthorized test translation attempt');
            wp_send_json_error(array('message' => __('Unauthorized', 'xf-translator')));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $target_language = isset($_POST['target_language']) ? sanitize_text_field($_POST['target_language']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $prompt_template = isset($_POST['prompt_template']) ? sanitize_text_field($_POST['prompt_template']) : 'current';
        $custom_prompt = isset($_POST['custom_prompt']) ? sanitize_textarea_field($_POST['custom_prompt']) : '';
        
        error_log('XF Translator: Test translation params - Post ID: ' . $post_id . ', Language: ' . $target_language . ', Model: ' . $model);
        
        if (!$post_id || !$target_language || !$model) {
            error_log('XF Translator: Missing required parameters in test translation');
            wp_send_json_error(array('message' => __('Missing required parameters', 'xf-translator')));
            return;
        }
        
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        $processor = new Xf_Translator_Processor();
        
        $start_time = microtime(true);
        
        error_log('XF Translator: Starting test translation...');
        
        // Test translation (doesn't create posts)
        $result = $processor->test_translation($post_id, $target_language, $model, $prompt_template, $custom_prompt);
        
        $end_time = microtime(true);
        $response_time = round($end_time - $start_time, 2);
        
        error_log('XF Translator: Test translation completed. Response time: ' . $response_time . ' seconds');
        
        if (is_wp_error($result)) {
            error_log('XF Translator: Test translation error: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        if (!$result) {
            error_log('XF Translator: Test translation returned false');
            wp_send_json_error(array('message' => __('Translation failed', 'xf-translator')));
            return;
        }
        
        error_log('XF Translator: Test translation successful');
        
        wp_send_json_success(array(
            'translated_title' => $result['title'] ?? '',
            'translated_content' => $result['content'] ?? '',
            'translated_excerpt' => $result['excerpt'] ?? '',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'response_time' => $response_time,
            'test_post_id' => $result['test_post_id'] ?? false,
            'test_post_url' => $result['test_post_url'] ?? ''
        ));
    }
    
    /**
     * AJAX handler to save default model
     */
    public function ajax_save_default_model() {
        check_ajax_referer('xf_translator_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'xf-translator')));
            return;
        }
        
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        
        if (!$model) {
            wp_send_json_error(array('message' => __('Invalid model', 'xf-translator')));
            return;
        }
        
        $this->settings->update('selected_model', $model);
        
        wp_send_json_success(array('message' => __('Default model saved successfully', 'xf-translator')));
    }

    /**
     * Add language filter dropdown to posts/pages list
     */
    public function add_language_filter_dropdown($post_type) {
        if (!in_array($post_type, array('post', 'page'), true)) {
            return;
        }
        
        $languages = $this->settings->get('languages', array());
        if (empty($languages)) {
            return;
        }
        
        $selected = isset($_GET['xf_language_filter']) ? sanitize_text_field($_GET['xf_language_filter']) : '';
        
        echo '<label for="xf-language-filter" class="screen-reader-text">' . esc_html__('Filter by language', 'xf-translator') . '</label>';
        echo '<select name="xf_language_filter" id="xf-language-filter" class="postform">';
        echo '<option value="">' . esc_html__('All Languages', 'xf-translator') . '</option>';
        echo '<option value="original"' . selected($selected, 'original', false) . '>' . esc_html__('Original (No Translation)', 'xf-translator') . '</option>';
        
        foreach ($languages as $language) {
            if (empty($language['prefix'])) {
                continue;
            }
            $label = isset($language['name']) ? $language['name'] : $language['prefix'];
            echo '<option value="' . esc_attr($language['prefix']) . '"' . selected($selected, $language['prefix'], false) . '>' . esc_html($label) . '</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * Filter posts/pages list by language
     */
    public function filter_posts_by_language($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        global $pagenow;
        if ($pagenow !== 'edit.php') {
            return;
        }
        
        $post_type = $query->get('post_type');
        if (empty($post_type)) {
            $post_type = 'post';
        }
        
        if (!in_array($post_type, array('post', 'page'), true)) {
            return;
        }
        
        if (!isset($_GET['xf_language_filter'])) {
            return;
        }
        
        $filter = sanitize_text_field($_GET['xf_language_filter']);
        if ($filter === '') {
            return;
        }
        
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        
        if ($filter === 'original') {
            $meta_query[] = array(
                'key' => '_xf_translator_language',
                'compare' => 'NOT EXISTS'
            );
        } else {
            $meta_query[] = array(
                'key' => '_xf_translator_language',
                'value' => $filter,
                'compare' => '='
            );
        }
        
        $query->set('meta_query', $meta_query);
    }

    /**
     * AJAX handler to check prefix availability
     */
    public function ajax_check_prefix_availability() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $prefix = isset($_POST['prefix']) ? sanitize_text_field($_POST['prefix']) : '';
        $exclude_index = isset($_POST['exclude_index']) && $_POST['exclude_index'] !== '' ? intval($_POST['exclude_index']) : null;
        
        if (empty($prefix)) {
            wp_send_json_error(array('message' => 'Prefix is required'));
            return;
        }
        
        $exists = $this->settings->prefix_exists($prefix, $exclude_index);
        
        wp_send_json_success(array(
            'available' => !$exists,
            'prefix' => $prefix
        ));
    }
    
    /**
     * Get human-readable label for a meta field key
     *
     * @param string $key Meta field key
     * @param string $type 'post' or 'user'
     * @return string Human-readable label
     */
    public function get_meta_field_label($key, $type = 'post') {
        // Common field label mappings
        $label_map = array(
            // Post meta fields
            '_yoast_wpseo_title' => 'Yoast SEO Title',
            '_yoast_wpseo_metadesc' => 'Yoast SEO Meta Description',
            'rank_math_title' => 'Rank Math Title',
            'rank_math_description' => 'Rank Math Description',
            '_aioseo_title' => 'All in One SEO Title',
            '_aioseo_description' => 'All in One SEO Description',
            '_seopress_titles_title' => 'SEOPress Title',
            '_seopress_titles_desc' => 'SEOPress Description',
            '_meta_title' => 'Meta Title',
            '_meta_description' => 'Meta Description',
            'meta_title' => 'Meta Title',
            'meta_description' => 'Meta Description',
            
            // User meta fields
            'description' => 'Biographical Info',
            'user_description' => 'Biographical Info',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'nickname' => 'Nickname',
            'display_name' => 'Display Name',
        );
        
        // Check if we have a mapped label
        if (isset($label_map[$key])) {
            return $label_map[$key];
        }
        
        // Try to derive label from key
        $label = $key;
        
        // Remove leading underscore
        $label = ltrim($label, '_');
        
        // Replace underscores and hyphens with spaces
        $label = str_replace(array('_', '-'), ' ', $label);
        
        // Handle plugin prefixes
        if (strpos($label, 'yoast wpseo') === 0) {
            $label = 'Yoast SEO ' . substr($label, 12);
        } elseif (strpos($label, 'rank math') === 0) {
            $label = 'Rank Math ' . substr($label, 9);
        } elseif (strpos($label, 'aioseo') === 0) {
            $label = 'All in One SEO ' . substr($label, 6);
        } elseif (strpos($label, 'seopress') === 0) {
            $label = 'SEOPress ' . substr($label, 8);
        }
        
        // Capitalize words
        $label = ucwords($label);
        
        return $label;
    }
    
    /**
     * AJAX handler to scan post meta fields
     */
    public function ajax_scan_post_meta_fields() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        global $wpdb;
        
        // Get all unique meta keys from posts
        $meta_keys = $wpdb->get_col(
            "SELECT DISTINCT meta_key 
            FROM {$wpdb->postmeta} 
            WHERE meta_key NOT LIKE '\\_%' 
            AND meta_key NOT LIKE '_xf_translator_%'
            AND meta_key NOT LIKE '_api_translator_%'
            AND meta_key NOT LIKE '_edit_%'
            AND meta_key NOT LIKE '_wp_%'
            ORDER BY meta_key ASC
            LIMIT 200"
        );
        
        // Also get private meta keys (starting with _) but exclude internal ones
        $private_meta_keys = $wpdb->get_col(
            "SELECT DISTINCT meta_key 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '\\_%' 
            AND meta_key NOT LIKE '_xf_translator_%'
            AND meta_key NOT LIKE '_api_translator_%'
            AND meta_key NOT LIKE '_edit_%'
            AND meta_key NOT LIKE '_wp_%'
            AND meta_key NOT LIKE '_thumbnail_%'
            ORDER BY meta_key ASC
            LIMIT 200"
        );
        
        $all_keys = array_merge($meta_keys, $private_meta_keys);
        $all_keys = array_unique($all_keys);
        sort($all_keys);
        
        // Get sample values for each field
        $fields_with_samples = array();
        foreach ($all_keys as $key) {
            $sample = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND meta_value != '' 
                AND LENGTH(meta_value) < 200
                AND meta_value NOT LIKE '{%'
                LIMIT 1",
                $key
            ));
            
            $label = $this->get_meta_field_label($key, 'post');
            
            if ($sample && is_string($sample)) {
                $fields_with_samples[] = array(
                    'key' => $key,
                    'label' => $label,
                    'sample' => $sample
                );
            } else {
                $fields_with_samples[] = array(
                    'key' => $key,
                    'label' => $label,
                    'sample' => ''
                );
            }
        }
        
        wp_send_json_success(array('fields' => $fields_with_samples));
    }
    
    /**
     * AJAX handler to scan user meta fields
     */
    public function ajax_scan_user_meta_fields() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        global $wpdb;
        
        // Get all unique meta keys from users
        $meta_keys = $wpdb->get_col(
            "SELECT DISTINCT meta_key 
            FROM {$wpdb->usermeta} 
            WHERE meta_key NOT LIKE '\\_%' 
            AND meta_key NOT LIKE '_xf_translator_%'
            AND meta_key NOT LIKE '_api_translator_%'
            AND meta_key NOT LIKE '_wp_%'
            ORDER BY meta_key ASC
            LIMIT 200"
        );
        
        // Also get private meta keys (starting with _) but exclude internal ones
        $private_meta_keys = $wpdb->get_col(
            "SELECT DISTINCT meta_key 
            FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE '\\_%' 
            AND meta_key NOT LIKE '_xf_translator_%'
            AND meta_key NOT LIKE '_api_translator_%'
            AND meta_key NOT LIKE '_wp_%'
            AND meta_key != '_capabilities'
            AND meta_key != '_user_level'
            AND meta_key != '_user_roles'
            ORDER BY meta_key ASC
            LIMIT 200"
        );
        
        $all_keys = array_merge($meta_keys, $private_meta_keys);
        $all_keys = array_unique($all_keys);
        sort($all_keys);
        
        // Get sample values for each field
        $fields_with_samples = array();
        foreach ($all_keys as $key) {
            $sample = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = %s 
                AND meta_value != '' 
                AND LENGTH(meta_value) < 200
                AND meta_value NOT LIKE '{%'
                LIMIT 1",
                $key
            ));
            
            $label = $this->get_meta_field_label($key, 'user');
            
            if ($sample && is_string($sample)) {
                $fields_with_samples[] = array(
                    'key' => $key,
                    'label' => $label,
                    'sample' => $sample
                );
            } else {
                $fields_with_samples[] = array(
                    'key' => $key,
                    'label' => $label,
                    'sample' => ''
                );
            }
        }
        
        wp_send_json_success(array('fields' => $fields_with_samples));
    }
    
    /**
     * AJAX handler to translate user meta fields in bulk
     */
    public function ajax_translate_user_meta_bulk() {
        // Enable error logging for debugging
        error_log('XF Translator: Bulk translation AJAX called');
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            error_log('XF Translator: Invalid nonce in bulk translation');
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            error_log('XF Translator: Insufficient permissions in bulk translation');
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $selected_fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : array();
        $target_language_prefix = isset($_POST['language_prefix']) ? sanitize_text_field($_POST['language_prefix']) : '';
        
        error_log('XF Translator: Fields: ' . print_r($selected_fields, true) . ', Language: ' . $target_language_prefix);
        
        if (empty($selected_fields) || empty($target_language_prefix)) {
            wp_send_json_error(array('message' => 'Fields and language are required'));
            return;
        }
        
        // Normalize field list - remove duplicates that map to the same actual database key
        // This prevents translating both 'description' and 'user_description' since they map to the same field
        $normalized_fields = array();
        $processed_keys = array();
        
        // Prefer 'description' over 'user_description' if both exist
        // Sort to ensure 'description' comes before 'user_description'
        usort($selected_fields, function($a, $b) {
            if ($a === 'description' && $b === 'user_description') return -1;
            if ($a === 'user_description' && $b === 'description') return 1;
            return strcmp($a, $b);
        });
        
        foreach ($selected_fields as $meta_key) {
            // Normalize the key (user_description -> description)
            $actual_key = ($meta_key === 'user_description') ? 'description' : $meta_key;
            
            // Only add if we haven't processed this actual key yet
            if (!in_array($actual_key, $processed_keys)) {
                $normalized_fields[] = $meta_key; // Keep original for display
                $processed_keys[] = $actual_key;
            }
        }
        
        // Use normalized fields instead of selected_fields
        $selected_fields = $normalized_fields;
        
        // Get language name
        $languages = $this->settings->get('languages', array());
        $target_language_name = '';
        foreach ($languages as $lang) {
            if ($lang['prefix'] === $target_language_prefix) {
                $target_language_name = $lang['name'];
                break;
            }
        }
        
        if (empty($target_language_name)) {
            wp_send_json_error(array('message' => 'Invalid language'));
            return;
        }
        
        // Get all users
        $users = get_users();
        $results = array();
        
        // Load Settings class first (required by translation processor)
        // Settings class is in the same directory as this admin class
        $settings_file = dirname(__FILE__) . '/class-settings.php';
        if (!file_exists($settings_file)) {
            wp_send_json_error(array('message' => 'Settings class file not found at: ' . $settings_file));
            return;
        }
        require_once $settings_file;
        
        // Load translation processor
        // Translation processor is in the includes directory (one level up from admin)
        $processor_file = dirname(dirname(__FILE__)) . '/includes/class-translation-processor.php';
        if (!file_exists($processor_file)) {
            wp_send_json_error(array('message' => 'Translation processor file not found'));
            return;
        }
        
        require_once $processor_file;
        
        if (!class_exists('Xf_Translator_Processor')) {
            wp_send_json_error(array('message' => 'Translation processor class not found'));
            return;
        }
        
        if (!class_exists('Settings')) {
            wp_send_json_error(array('message' => 'Settings class not found'));
            return;
        }
        
        try {
            error_log('XF Translator: Creating translation processor instance');
            $translation_processor = new Xf_Translator_Processor();
            error_log('XF Translator: Translation processor created successfully');
        } catch (Exception $e) {
            error_log('XF Translator: Exception creating processor: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Failed to initialize translation processor: ' . $e->getMessage()));
            return;
        } catch (Error $e) {
            error_log('XF Translator: Error creating processor: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Failed to initialize translation processor: ' . $e->getMessage()));
            return;
        } catch (Throwable $e) {
            error_log('XF Translator: Throwable creating processor: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Failed to initialize translation processor: ' . $e->getMessage()));
            return;
        }
        
        foreach ($users as $user) {
            $user_results = array();
            
            foreach ($selected_fields as $meta_key) {
                // Handle 'user_description' - WordPress stores it as 'description' in user meta
                $actual_key = ($meta_key === 'user_description') ? 'description' : $meta_key;
                $original_value = get_user_meta($user->ID, $actual_key, true);
                
                if (empty($original_value)) {
                    continue; // Skip empty fields
                }
                
                // Translate this field for this user
                try {
                    error_log("XF Translator: Translating field {$meta_key} for user {$user->ID}");
                    $translation_result = $translation_processor->translate_user_meta_field($user->ID, $meta_key, $target_language_name, $target_language_prefix);
                    
                    if (isset($translation_result['success']) && $translation_result['success']) {
                        $user_results[] = array(
                            'field' => $meta_key,
                            'field_label' => $this->get_meta_field_label($meta_key, 'user'),
                            'original' => $original_value,
                            'translated' => isset($translation_result['translated_value']) ? $translation_result['translated_value'] : '',
                            'success' => true
                        );
                    } else {
                        $error_msg = isset($translation_result['error']) ? $translation_result['error'] : 'Translation failed';
                        error_log("XF Translator: Translation failed for field {$meta_key}, user {$user->ID}: {$error_msg}");
                        $user_results[] = array(
                            'field' => $meta_key,
                            'field_label' => $this->get_meta_field_label($meta_key, 'user'),
                            'original' => $original_value,
                            'translated' => '',
                            'success' => false,
                            'error' => $error_msg
                        );
                    }
                } catch (Exception $e) {
                    error_log("XF Translator: Exception translating field {$meta_key}, user {$user->ID}: " . $e->getMessage());
                    $user_results[] = array(
                        'field' => $meta_key,
                        'field_label' => $this->get_meta_field_label($meta_key, 'user'),
                        'original' => $original_value,
                        'translated' => '',
                        'success' => false,
                        'error' => 'Exception: ' . $e->getMessage()
                    );
                } catch (Error $e) {
                    error_log("XF Translator: Error translating field {$meta_key}, user {$user->ID}: " . $e->getMessage());
                    $user_results[] = array(
                        'field' => $meta_key,
                        'field_label' => $this->get_meta_field_label($meta_key, 'user'),
                        'original' => $original_value,
                        'translated' => '',
                        'success' => false,
                        'error' => 'Error: ' . $e->getMessage()
                    );
                } catch (Throwable $e) {
                    error_log("XF Translator: Throwable translating field {$meta_key}, user {$user->ID}: " . $e->getMessage());
                    $user_results[] = array(
                        'field' => $meta_key,
                        'field_label' => $this->get_meta_field_label($meta_key, 'user'),
                        'original' => $original_value,
                        'translated' => '',
                        'success' => false,
                        'error' => 'Error: ' . $e->getMessage()
                    );
                }
            }
            
            if (!empty($user_results)) {
                $results[] = array(
                    'user_id' => $user->ID,
                    'user_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'fields' => $user_results
                );
            }
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'language' => $target_language_name,
            'language_prefix' => $target_language_prefix
        ));
    }
    
    /**
     * AJAX handler to save a single user meta translation
     */
    public function ajax_save_user_meta_translation() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $meta_key = isset($_POST['meta_key']) ? sanitize_text_field($_POST['meta_key']) : '';
        $language_prefix = isset($_POST['language_prefix']) ? sanitize_text_field($_POST['language_prefix']) : '';
        $translated_value = isset($_POST['translated_value']) ? sanitize_textarea_field($_POST['translated_value']) : '';
        
        if (empty($user_id) || empty($meta_key) || empty($language_prefix)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
        
        // Handle 'user_description' - WordPress stores it as 'description' in user meta
        $store_key = ($meta_key === 'user_description') ? 'description' : $meta_key;
        
        // Store translated value with language prefix
        $translated_meta_key = '_xf_translator_user_meta_' . $store_key . '_' . $language_prefix;
        update_user_meta($user_id, $translated_meta_key, $translated_value);
        
        wp_send_json_success(array('message' => 'Translation saved successfully'));
    }
    
    /**
     * AJAX handler to load user meta translations from database
     */
    public function ajax_load_user_meta_translations() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $language_prefix = isset($_POST['language_prefix']) ? sanitize_text_field($_POST['language_prefix']) : '';
        $selected_fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : array();
        
        if (empty($language_prefix)) {
            wp_send_json_error(array('message' => 'Language is required'));
            return;
        }
        
        // Get language name
        $languages = $this->settings->get('languages', array());
        $target_language_name = '';
        foreach ($languages as $lang) {
            if ($lang['prefix'] === $language_prefix) {
                $target_language_name = $lang['name'];
                break;
            }
        }
        
        // If no fields specified, use configured translatable fields
        if (empty($selected_fields)) {
            $selected_fields = $this->settings->get_translatable_user_meta_fields();
        }
        
        if (empty($selected_fields)) {
            wp_send_json_error(array('message' => 'No fields configured'));
            return;
        }
        
        // Normalize field list - remove duplicates that map to the same actual database key
        // This prevents showing both 'description' and 'user_description' since they map to the same field
        $normalized_fields = array();
        $processed_keys = array();
        
        // Prefer 'description' over 'user_description' if both exist
        // Sort to ensure 'description' comes before 'user_description'
        usort($selected_fields, function($a, $b) {
            if ($a === 'description' && $b === 'user_description') return -1;
            if ($a === 'user_description' && $b === 'description') return 1;
            return strcmp($a, $b);
        });
        
        foreach ($selected_fields as $meta_key) {
            // Normalize the key (user_description -> description)
            $actual_key = ($meta_key === 'user_description') ? 'description' : $meta_key;
            
            // Only add if we haven't processed this actual key yet
            if (!in_array($actual_key, $processed_keys)) {
                $normalized_fields[] = $meta_key; // Keep original for display
                $processed_keys[] = $actual_key;
            }
        }
        
        // Use normalized fields instead of selected_fields
        $selected_fields = $normalized_fields;
        
        // Get all users
        $users = get_users();
        $results = array();
        
        foreach ($users as $user) {
            $user_results = array();
            
            foreach ($selected_fields as $meta_key) {
                // Handle 'user_description' - WordPress stores it as 'description' in user meta
                $actual_key = ($meta_key === 'user_description') ? 'description' : $meta_key;
                $original_value = get_user_meta($user->ID, $actual_key, true);
                
                if (empty($original_value)) {
                    continue; // Skip empty fields
                }
                
                // Get translated value
                $translated_meta_key = '_xf_translator_user_meta_' . $actual_key . '_' . $language_prefix;
                $translated_value = get_user_meta($user->ID, $translated_meta_key, true);
                
                // Only include this field if a translation exists (not empty)
                // This prevents showing users who don't have translations for the selected language
                if (!empty($translated_value)) {
                    $user_results[] = array(
                        'field' => $meta_key,
                        'field_label' => $this->get_meta_field_label($meta_key, 'user'),
                        'original' => $original_value,
                        'translated' => $translated_value,
                        'success' => true
                    );
                }
            }
            
            // Only add user to results if they have at least one translation
            if (!empty($user_results)) {
                $results[] = array(
                    'user_id' => $user->ID,
                    'user_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'fields' => $user_results
                );
            }
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'language' => $target_language_name,
            'language_prefix' => $language_prefix
        ));
    }

    /**
     * AJAX handler to get ACF field groups
     */
    public function ajax_get_acf_field_groups() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        if (!function_exists('acf_get_field_groups')) {
            wp_send_json_error(array('message' => 'ACF functions not available'));
            return;
        }

        // Get all ACF field groups
        $field_groups = acf_get_field_groups();
        $groups_data = array();

        error_log('XF Translator ACF: Found ' . count($field_groups) . ' ACF field groups');

        foreach ($field_groups as $group) {
            $location_info = isset($group['location']) ? $group['location'] : array();
            error_log('XF Translator ACF: Field group "' . $group['title'] . '" (key: ' . $group['key'] . ') location: ' . print_r($location_info, true));

            $groups_data[] = array(
                'key' => $group['key'],
                'title' => $group['title'],
                'location' => $location_info
            );
        }

        wp_send_json_success(array('groups' => $groups_data));
    }

    /**
     * AJAX handler to scan ACF fields by field group
     */
    public function ajax_scan_acf_fields_by_group() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $group_key = isset($_POST['group_key']) ? sanitize_text_field($_POST['group_key']) : '';

        if (empty($group_key)) {
            wp_send_json_error(array('message' => 'Field group key is required'));
            return;
        }

        if (!function_exists('acf_get_fields')) {
            wp_send_json_error(array('message' => 'ACF functions not available'));
            return;
        }

        // Get fields for this specific field group
        $fields = acf_get_fields($group_key);
        $acf_fields = array();

        error_log('XF Translator ACF: Scanning field group "' . $group_key . '" - found ' . (is_array($fields) ? count($fields) : 0) . ' total fields');

        if ($fields && is_array($fields)) {
            foreach ($fields as $field) {
                $field_type = isset($field['type']) ? $field['type'] : 'unknown';
                error_log('XF Translator ACF: Field "' . $field['name'] . '" type: ' . $field_type);

                // Only include text-based fields that can be translated
                $translatable_types = array('text', 'textarea', 'wysiwyg', 'email', 'url', 'number');

                if (in_array($field_type, $translatable_types)) {
                    // Get sample data from existing posts (optional)
                    $sample_value = $this->get_acf_field_sample($field['name']);
                    error_log('XF Translator ACF: Added translatable field "' . $field['name'] . '" with sample: "' . substr($sample_value, 0, 50) . '"');

                    $acf_fields[] = array(
                        'key' => $field['name'],
                        'label' => $field['label'],
                        'type' => $field_type,
                        'sample' => $sample_value,
                        'instructions' => isset($field['instructions']) ? $field['instructions'] : ''
                    );
                } else {
                    error_log('XF Translator ACF: Skipped non-translatable field "' . $field['name'] . '" type: ' . $field_type);
                }
            }
        }

        error_log('XF Translator ACF: Returning ' . count($acf_fields) . ' translatable fields for group "' . $group_key . '"');

        wp_send_json_success(array('fields' => $acf_fields));
    }

    /**
     * AJAX handler to test ACF detection
     */
    public function ajax_test_acf_detection() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
                wp_send_json_error(array('message' => 'Invalid nonce'));
                return;
            }

            // Check user permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }

            $result = array(
                'acf_active' => function_exists('get_fields'),
                'field_groups_count' => 0,
                'options_pages_checked' => array('footer-options', 'header-options', 'theme-options'),
                'fields_in_options' => 0,
                'posts_checked' => 0,
                'post_types_found' => array()
            );

            // Check ACF field groups
            if (function_exists('acf_get_field_groups')) {
                $field_groups = acf_get_field_groups();
                $result['field_groups_count'] = count($field_groups);
            }

            // Check options pages for ACF fields
            $options_pages = array('footer-options', 'header-options', 'theme-options');
            foreach ($options_pages as $option_page) {
                try {
                    $fields = get_fields($option_page);
                    if ($fields && is_array($fields)) {
                        $result['fields_in_options'] += count($fields);
                    }
                } catch (Exception $e) {
                    // Ignore errors for individual option pages
                }
            }

            // Check a few posts for ACF fields
            $args = array(
                'post_type' => 'any',
                'posts_per_page' => 10,
                'post_status' => 'publish'
            );

            $posts = get_posts($args);
            $result['posts_checked'] = count($posts);

            foreach ($posts as $post) {
                $post_type = $post->post_type;
                $result['post_types_found'][$post_type] = ($result['post_types_found'][$post_type] ?? 0) + 1;

                // Check if post has ACF fields
                try {
                    $fields = get_fields($post->ID);
                    if ($fields && is_array($fields) && !empty($fields)) {
                        $result['posts_with_acf_fields'] = ($result['posts_with_acf_fields'] ?? 0) + 1;
                    }
                } catch (Exception $e) {
                    // Ignore errors for individual posts
                }
            }

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('XF Translator ACF: Error in ACF detection test: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Test failed: ' . $e->getMessage()));
        }
    }

    /**
     * Helper method to get sample ACF field value
     */
    private function get_acf_field_sample($field_name) {
        // Get a recent post that has this field
        $args = array(
            'post_type' => 'any',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => $field_name,
                    'compare' => 'EXISTS'
                )
            ),
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        $posts = get_posts($args);

        if (!empty($posts)) {
            $value = get_field($field_name, $posts[0]->ID);
            if (is_string($value) || is_numeric($value)) {
                return substr((string)$value, 0, 100);
            }
        }

        return '';
    }

    /**
     * AJAX handler to scan ACF fields (legacy - now replaced by group-based scanning)
     */
    public function ajax_scan_acf_fields() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        if (!function_exists('get_fields')) {
            wp_send_json_error(array('message' => 'ACF plugin is not active'));
            return;
        }

        // Additional ACF check - try to get fields from a test post
        $test_post_id = get_option('page_on_front'); // Use front page as test
        if ($test_post_id) {
            $test_fields = get_fields($test_post_id);
            if ($test_fields === false) {
                wp_send_json_error(array('message' => 'ACF functions are available but not returning data. Check ACF configuration.'));
                return;
            }
        }

        // Get posts to scan for ACF fields (limit to recent posts for performance)
        $args = array(
            'post_type' => 'any',
            'posts_per_page' => 50, // Limit for performance
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        $posts = get_posts($args);
        $acf_fields = array();
        $posts_checked = 0;

        foreach ($posts as $post) {
            $posts_checked++;
            $fields = get_fields($post->ID);

            // Debug: Check if get_fields returned anything
            if (empty($fields)) {
                continue; // No ACF fields on this post
            }

            if ($fields && is_array($fields)) {
                foreach ($fields as $field_key => $field_value) {
                    // Skip internal ACF fields (field keys that start with underscore)
                    if (strpos($field_key, '_') === 0) {
                        continue;
                    }

                    // Only include text/numeric fields for translation
                    if (!is_string($field_value) && !is_numeric($field_value)) {
                        continue;
                    }

                    // Skip empty values
                    if (empty(trim($field_value))) {
                        continue;
                    }

                    if (!isset($acf_fields[$field_key])) {
                        // Try to get field label from ACF
                        $field_object = get_field_object($field_key, $post->ID);
                        $label = $field_object && isset($field_object['label']) ? $field_object['label'] : $field_key;

                        $acf_fields[$field_key] = array(
                            'key' => $field_key,
                            'label' => $label,
                            'sample' => substr((string)$field_value, 0, 100)
                        );
                    }
                }
            }

            // Break if we have enough fields (performance optimization)
            if (count($acf_fields) >= 20) {
                break;
            }
        }

        // If no fields found through get_fields(), try alternative method
        if (empty($acf_fields)) {
            global $wpdb;

            // Look for ACF meta keys (they start with underscore)
            $acf_meta_keys = $wpdb->get_col("
                SELECT DISTINCT meta_key
                FROM {$wpdb->postmeta}
                WHERE meta_key LIKE '\_%'
                AND meta_key NOT LIKE '\_wp%'
                AND meta_key NOT LIKE '\_edit%'
                LIMIT 50
            ");

            if (!empty($acf_meta_keys)) {
                // Get sample values for these fields
                foreach ($acf_meta_keys as $meta_key) {
                    // Remove underscore to get field name
                    $field_key = substr($meta_key, 1);

                    // Get a sample post that has this field
                    $sample_post_id = $wpdb->get_var($wpdb->prepare("
                        SELECT post_id
                        FROM {$wpdb->postmeta}
                        WHERE meta_key = %s
                        AND meta_value != ''
                        LIMIT 1
                    ", $meta_key));

                    if ($sample_post_id) {
                        $sample_value = get_post_meta($sample_post_id, $meta_key, true);

                        if (!empty($sample_value) && (is_string($sample_value) || is_numeric($sample_value))) {
                            // Try to get field label
                            $field_object = get_field_object($field_key, $sample_post_id);
                            $label = $field_object && isset($field_object['label']) ? $field_object['label'] : $field_key;

                            $acf_fields[$field_key] = array(
                                'key' => $field_key,
                                'label' => $label,
                                'sample' => substr((string)$sample_value, 0, 100)
                            );
                        }
                    }

                    if (count($acf_fields) >= 20) {
                        break;
                    }
                }
            }
        }

        wp_send_json_success(array(
            'fields' => array_values($acf_fields),
            'posts_checked' => $posts_checked,
            'total_found' => count($acf_fields)
        ));
    }

    /**
     * AJAX handler to translate ACF fields in bulk
     */
    public function ajax_translate_acf_bulk() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
                wp_send_json_error(array('message' => 'Invalid nonce'));
                return;
            }

            // Check user permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Insufficient permissions'));
                return;
            }

            $language_prefix = isset($_POST['language_prefix']) ? sanitize_text_field($_POST['language_prefix']) : '';
            $selected_fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : array();

            if (empty($language_prefix)) {
                wp_send_json_error(array('message' => 'Language is required'));
                return;
            }

            if (empty($selected_fields)) {
                wp_send_json_error(array('message' => 'No fields selected'));
                return;
            }

            // Get language name
            $languages = $this->settings->get('languages', array());
            $target_language_name = '';
            foreach ($languages as $lang) {
                if ($lang['prefix'] === $language_prefix) {
                    $target_language_name = $lang['name'];
                    break;
                }
            }

            if (empty($target_language_name)) {
                wp_send_json_error(array('message' => 'Invalid language'));
                return;
            }

        // First check ACF options pages for the selected fields
        $options_results = array();
        foreach ($selected_fields as $field_key) {
            // Check common ACF options pages
            $options_pages = array('', 'options', 'acf-options', 'footer-options', 'header-options', 'theme-options');

            foreach ($options_pages as $options_page) {
                try {
                    $option_name = $options_page ? $options_page : 'option';
                    $field_value = get_field($field_key, $option_name);

                    error_log('XF Translator ACF: Checking field "' . $field_key . '" in options page "' . $option_name . '", value: ' . (is_string($field_value) ? substr($field_value, 0, 50) : json_encode($field_value)));

                    if ($field_value !== null && $field_value !== false && (!empty($field_value) || is_numeric($field_value))) {
                        $options_results[] = array(
                            'option_name' => $option_name,
                            'field' => $field_key,
                            'field_label' => $this->get_acf_field_label($field_key, false), // No post ID for options
                            'original' => $field_value,
                            'translated' => '',
                            'success' => false,
                            'error' => 'Ready for translation'
                        );
                        error_log('XF Translator ACF: Found field "' . $field_key . '" in ACF options page "' . $option_name . '": ' . substr((string)$field_value, 0, 50));
                        break; // Found it, no need to check other option pages
                    }
                } catch (Exception $e) {
                    error_log('XF Translator ACF: Error checking field "' . $field_key . '" in options page "' . $option_name . '": ' . $e->getMessage());
                }
            }
        }

        // If we found options page results, translate them
        if (!empty($options_results)) {
            error_log('XF Translator ACF: Translating ACF fields in options pages');

            $translated_options_results = array();
            foreach ($options_results as $option_result) {
                $field_key = $option_result['field'];
                $original_value = $option_result['original'];
                $option_name = $option_result['option_name'];

                try {
                    // Translate the field
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
                    $processor = new Xf_Translator_Processor();
                    
                    // Use Reflection to access protected methods
                    $reflection = new ReflectionClass($processor);
                    
                    // Build prompt from ACF field data
                    $acf_data = array('acf_' . $field_key => $original_value);
                    $build_prompt_method = $reflection->getMethod('build_translation_prompt');
                    $build_prompt_method->setAccessible(true);
                    $prompt_data = $build_prompt_method->invoke($processor, $acf_data, $target_language_name);
                    $prompt = $prompt_data['prompt'];
                    $placeholders_map = $prompt_data['placeholders_map'];
                    
                    // Call translation API with prompt string and language prefix
                    $call_api_method = $reflection->getMethod('call_translation_api');
                    $call_api_method->setAccessible(true);
                    $translation_result = $call_api_method->invoke($processor, $prompt, $language_prefix, 0, 0);

                    if ($translation_result !== false) {
                        // Parse the translation response
                        $parse_method = $reflection->getMethod('parse_translation_response');
                        $parse_method->setAccessible(true);
                        $parsed_translation = $parse_method->invoke($processor, $translation_result, $acf_data);
                        
                        // Restore HTML tags and URLs from placeholders
                        if (!empty($parsed_translation) && !empty($placeholders_map)) {
                            $field_key_with_prefix = 'acf_' . $field_key;
                            if (isset($parsed_translation[$field_key_with_prefix]) && isset($placeholders_map[$field_key_with_prefix])) {
                                $restore_method = $reflection->getMethod('restore_html_and_urls');
                                $restore_method->setAccessible(true);
                                $parsed_translation[$field_key_with_prefix] = $restore_method->invoke(
                                    $processor,
                                    $parsed_translation[$field_key_with_prefix],
                                    $placeholders_map[$field_key_with_prefix]
                                );
                            }
                        }
                        
                        if (isset($parsed_translation['acf_' . $field_key])) {
                            $translated_value = $parsed_translation['acf_' . $field_key];

                        // Save to ACF options with language prefix
                            // Normalize option name: 'option' or empty string becomes 'options'
                            $acf_option_key = ($option_name === 'option' || $option_name === '') ? 'options' : $option_name;

                        error_log('XF Translator ACF: Saving translated field "' . $field_key . '" to options page "' . $acf_option_key . '_' . $language_prefix . '"');

                        // Try to save with ACF update_field
                        $save_result = update_field($field_key, $translated_value, $acf_option_key . '_' . $language_prefix);

                            // Also save to options table with a consistent key format for retrieval
                            // Format: _xf_translator_acf_options_{option_name}_{field_key}_{language_prefix}
                            $xf_option_key = '_xf_translator_acf_options_' . $acf_option_key . '_' . $field_key . '_' . $language_prefix;
                            update_option($xf_option_key, $translated_value);
                            
                            // Also save the option name for easy retrieval
                            update_option($xf_option_key . '_option_name', $option_name);

                            if ($save_result === false) {
                                error_log('XF Translator ACF: update_field failed, but saved to options table with key: ' . $xf_option_key);
                        }

                        $translated_options_results[] = array(
                            'option_name' => $option_name,
                            'field' => $field_key,
                            'field_label' => $option_result['field_label'],
                            'original' => $original_value,
                            'translated' => $translated_value,
                            'success' => true
                        );
                    } else {
                            error_log('XF Translator ACF: Translation response did not contain field "' . $field_key . '"');
                        $translated_options_results[] = array(
                            'option_name' => $option_name,
                            'field' => $field_key,
                            'field_label' => $option_result['field_label'],
                            'original' => $original_value,
                            'translated' => '',
                            'success' => false,
                                'error' => 'Translation response parsing failed'
                            );
                        }
                    } else {
                        error_log('XF Translator ACF: Translation API call failed for field "' . $field_key . '"');
                        $translated_options_results[] = array(
                            'option_name' => $option_name,
                            'field' => $field_key,
                            'field_label' => $option_result['field_label'],
                            'original' => $original_value,
                            'translated' => '',
                            'success' => false,
                            'error' => 'Translation API call failed'
                        );
                    }
                } catch (Exception $e) {
                    error_log('XF Translator ACF: Exception during translation of field "' . $field_key . '": ' . $e->getMessage());
                    $translated_options_results[] = array(
                        'option_name' => $option_name,
                        'field' => $field_key,
                        'field_label' => $option_result['field_label'],
                        'original' => $original_value,
                        'translated' => '',
                        'success' => false,
                        'error' => 'Exception: ' . $e->getMessage()
                    );
                }
            }

            wp_send_json_success(array(
                'results' => array(array(
                    'post_id' => 0, // Special ID for options
                    'post_title' => 'ACF Options Pages',
                    'fields' => $translated_options_results
                )),
                'language' => $target_language_name,
                'language_prefix' => $language_prefix,
                'debug' => array(
                    'posts_checked' => 0,
                    'total_posts_found' => 0,
                    'posts_with_fields' => 0,
                    'selected_fields' => $selected_fields,
                    'processed_count' => 1,
                    'options_found' => count($translated_options_results),
                    'message' => 'Translated ACF fields in options pages'
                )
            ));
            return;
        }
        } catch (Exception $e) {
            error_log('XF Translator ACF: Critical error in ajax_translate_acf_bulk: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Critical error: ' . $e->getMessage()));
            return;
        }

        try {
            // Get posts to check for ACF fields (can't reliably query ACF fields with meta_query)
            // Include all post types to be comprehensive
            $args = array(
                'post_type' => 'any',
                'posts_per_page' => 500, // Check more posts to be thorough
                'post_status' => 'publish',
                'orderby' => 'modified',
                'order' => 'DESC'
            );

            $all_posts = get_posts($args);
            $posts = array(); // Posts that actually have the selected ACF fields

            // Debug: Log post types found
            $post_types_found = array();
            foreach ($all_posts as $post) {
                $post_types_found[$post->post_type] = ($post_types_found[$post->post_type] ?? 0) + 1;
            }
            error_log('XF Translator ACF: Post types found: ' . print_r($post_types_found, true));

            // Filter posts to only include those that have the selected ACF fields
            $checked_posts = 0;
            foreach ($all_posts as $post) {
                $checked_posts++;
                $has_any_field = false;

                foreach ($selected_fields as $field_key) {
                    $field_value = get_field($field_key, $post->ID);
                    if ($field_value !== null && $field_value !== false && (!empty($field_value) || is_numeric($field_value))) {
                        $has_any_field = true;
                        error_log('XF Translator ACF: Found field "' . $field_key . '" in post ' . $post->ID . ' (' . $post->post_type . '): ' . substr((string)$field_value, 0, 50));
                        break;
                    }
                }

                if ($has_any_field) {
                    $posts[] = $post;
                }

                // Limit to reasonable number for performance
                if (count($posts) >= 50) {
                    break;
                }

                // Also limit total checking for performance
                if ($checked_posts >= 200) {
                    break;
                }
            }

            // Debug: Log what we found
            error_log('XF Translator ACF: Checked ' . $checked_posts . ' posts total');
            error_log('XF Translator ACF: Found ' . count($posts) . ' posts with selected ACF fields');
            error_log('XF Translator ACF: Selected fields: ' . implode(', ', $selected_fields));

            $results = array();
            $processed_count = 0;

            foreach ($posts as $post) {
                $post_results = array();
                $has_translations = false;

                foreach ($selected_fields as $field_key) {
                    $original_value = get_field($field_key, $post->ID);

                    // Skip if field doesn't exist or is empty
                    if ($original_value === null || $original_value === false || (empty($original_value) && !is_numeric($original_value))) {
                        continue;
                    }

                    // Only process text/numeric fields
                    if (!is_string($original_value) && !is_numeric($original_value)) {
                        continue;
                    }

                    // Check if translation already exists
                    $translated_meta_key = '_xf_translator_acf_' . $field_key . '_' . $language_prefix;
                    $existing_translation = get_post_meta($post->ID, $translated_meta_key, true);

                    if (!empty($existing_translation)) {
                        // Already translated
                        $post_results[] = array(
                            'field' => $field_key,
                            'field_label' => $this->get_acf_field_label($field_key, $post->ID),
                            'original' => $original_value,
                            'translated' => $existing_translation,
                            'success' => true,
                            'skipped' => true
                        );
                        $has_translations = true;
                        continue;
                    }

                    // Translate the field
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
                    $processor = new Xf_Translator_Processor();
                    
                    // Use Reflection to access protected methods
                    $reflection = new ReflectionClass($processor);
                    
                    // Build prompt from ACF field data
                    $acf_data = array('acf_' . $field_key => $original_value);
                    $build_prompt_method = $reflection->getMethod('build_translation_prompt');
                    $build_prompt_method->setAccessible(true);
                    $prompt_data = $build_prompt_method->invoke($processor, $acf_data, $target_language_name);
                    $prompt = $prompt_data['prompt'];
                    $placeholders_map = $prompt_data['placeholders_map'];
                    
                    // Call translation API with prompt string and language prefix
                    $call_api_method = $reflection->getMethod('call_translation_api');
                    $call_api_method->setAccessible(true);
                    $translation_result = $call_api_method->invoke($processor, $prompt, $language_prefix, 0, $post->ID);

                    if ($translation_result !== false) {
                        // Parse the translation response
                        $parse_method = $reflection->getMethod('parse_translation_response');
                        $parse_method->setAccessible(true);
                        $parsed_translation = $parse_method->invoke($processor, $translation_result, $acf_data);
                        
                        // Restore HTML tags and URLs from placeholders
                        if (!empty($parsed_translation) && !empty($placeholders_map)) {
                            $field_key_with_prefix = 'acf_' . $field_key;
                            if (isset($parsed_translation[$field_key_with_prefix]) && isset($placeholders_map[$field_key_with_prefix])) {
                                $restore_method = $reflection->getMethod('restore_html_and_urls');
                                $restore_method->setAccessible(true);
                                $parsed_translation[$field_key_with_prefix] = $restore_method->invoke(
                                    $processor,
                                    $parsed_translation[$field_key_with_prefix],
                                    $placeholders_map[$field_key_with_prefix]
                                );
                            }
                        }
                        
                        if (isset($parsed_translation['acf_' . $field_key])) {
                            $translated_value = $parsed_translation['acf_' . $field_key];

                        // Save translation
                        update_post_meta($post->ID, $translated_meta_key, $translated_value);

                        $post_results[] = array(
                            'field' => $field_key,
                            'field_label' => $this->get_acf_field_label($field_key, $post->ID),
                            'original' => $original_value,
                            'translated' => $translated_value,
                            'success' => true
                        );
                        $has_translations = true;
                    } else {
                        $post_results[] = array(
                            'field' => $field_key,
                            'field_label' => $this->get_acf_field_label($field_key, $post->ID),
                            'original' => $original_value,
                            'translated' => '',
                            'success' => false,
                                'error' => 'Translation response parsing failed'
                            );
                        }
                    } else {
                        $post_results[] = array(
                            'field' => $field_key,
                            'field_label' => $this->get_acf_field_label($field_key, $post->ID),
                            'original' => $original_value,
                            'translated' => '',
                            'success' => false,
                            'error' => 'Translation API call failed'
                        );
                    }
                }

                if ($has_translations) {
                    $results[] = array(
                        'post_id' => $post->ID,
                        'post_title' => $post->post_title,
                        'fields' => $post_results
                    );
                    $processed_count++;
                }

                // Limit results for performance
                if ($processed_count >= 20) {
                    break;
                }
            }

            wp_send_json_success(array(
                'results' => $results,
                'language' => $target_language_name,
                'language_prefix' => $language_prefix,
                'debug' => array(
                    'posts_checked' => $checked_posts,
                    'total_posts_found' => count($all_posts),
                    'posts_with_fields' => count($posts),
                    'selected_fields' => $selected_fields,
                    'processed_count' => $processed_count,
                    'post_types_found' => $post_types_found
                )
            ));
        } catch (Exception $e) {
            error_log('XF Translator ACF: Error in post checking: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error checking posts: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX handler to save ACF field translation
     */
    public function ajax_save_acf_translation() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $field_key = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';
        $language_prefix = isset($_POST['language_prefix']) ? sanitize_text_field($_POST['language_prefix']) : '';
        $translated_value = isset($_POST['translated_value']) ? wp_kses_post($_POST['translated_value']) : '';

        if (!$post_id || empty($field_key) || empty($language_prefix)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }

        $translated_meta_key = '_xf_translator_acf_' . $field_key . '_' . $language_prefix;
        update_post_meta($post_id, $translated_meta_key, $translated_value);

        wp_send_json_success(array('message' => 'Translation saved'));
    }

    /**
     * AJAX handler to load ACF field translations
     */
    public function ajax_load_acf_translations() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $language_prefix = isset($_POST['language_prefix']) ? sanitize_text_field($_POST['language_prefix']) : '';
        $selected_fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : array();

        if (empty($language_prefix)) {
            wp_send_json_error(array('message' => 'Language is required'));
            return;
        }

        // Get language name
        $languages = $this->settings->get('languages', array());
        $target_language_name = '';
        foreach ($languages as $lang) {
            if ($lang['prefix'] === $language_prefix) {
                $target_language_name = $lang['name'];
                break;
            }
        }

        // If no fields specified, use configured translatable fields
        if (empty($selected_fields)) {
            $selected_fields = $this->settings->get_translatable_acf_fields();
        }

        if (empty($selected_fields)) {
            wp_send_json_error(array('message' => 'No fields configured'));
            return;
        }

        $results = array();

        // First, check for ACF options page translations
        $options_results = array();
        $options_pages = array('', 'options', 'acf-options', 'footer-options', 'header-options', 'theme-options');
        
        foreach ($selected_fields as $field_key) {
            foreach ($options_pages as $options_page) {
                $option_name = $options_page ? $options_page : 'option';
                // Normalize option name: 'option' or empty string becomes 'options'
                $acf_option_key = ($option_name === 'option' || $option_name === '') ? 'options' : $option_name;
                
                // Check if translation exists in options table
                $xf_option_key = '_xf_translator_acf_options_' . $acf_option_key . '_' . $field_key . '_' . $language_prefix;
                $translated_value = get_option($xf_option_key, '');
                
                // If not found in options table, try to get from ACF directly (in case it was saved via update_field)
                if (empty($translated_value) && function_exists('get_field')) {
                    $translated_value = get_field($field_key, $acf_option_key . '_' . $language_prefix);
                }
                
                if (!empty($translated_value)) {
                    // Get original value from ACF options
                    $original_value = get_field($field_key, $option_name);
                    
                    if ($original_value !== null && $original_value !== false) {
                        $options_results[] = array(
                            'option_name' => $option_name,
                            'field' => $field_key,
                            'field_label' => $this->get_acf_field_label($field_key, false),
                            'original' => $original_value,
                            'translated' => $translated_value,
                            'success' => true
                        );
                        break; // Found it, no need to check other option pages
                    }
                }
            }
        }
        
        // Add options page results if any found
        if (!empty($options_results)) {
            $results[] = array(
                'post_id' => 0, // Special ID for options pages
                'post_title' => 'ACF Options Pages',
                'fields' => $options_results
            );
        }

        // Get posts that have ACF field translations (limit for performance)
        $args = array(
            'post_type' => 'any',
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR'
            )
        );

        // Add meta query for each field
        foreach ($selected_fields as $field_key) {
            $args['meta_query'][] = array(
                'key' => '_xf_translator_acf_' . $field_key . '_' . $language_prefix,
                'compare' => 'EXISTS'
            );
        }

        $posts = get_posts($args);

        foreach ($posts as $post) {
            $post_results = array();

            foreach ($selected_fields as $field_key) {
                $translated_meta_key = '_xf_translator_acf_' . $field_key . '_' . $language_prefix;
                $translated_value = get_post_meta($post->ID, $translated_meta_key, true);
                $original_value = get_field($field_key, $post->ID);

                if (!empty($translated_value)) {
                    $post_results[] = array(
                        'field' => $field_key,
                        'field_label' => $this->get_acf_field_label($field_key, $post->ID),
                        'original' => $original_value,
                        'translated' => $translated_value,
                        'success' => true
                    );
                }
            }

            if (!empty($post_results)) {
                $results[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'fields' => $post_results
                );
            }
        }

        wp_send_json_success(array(
            'results' => $results,
            'language' => $target_language_name,
            'language_prefix' => $language_prefix
        ));
    }

    /**
     * Helper method to get ACF field label
     */
    private function get_acf_field_label($field_key, $post_id) {
        if (function_exists('get_field_object')) {
            $field_object = get_field_object($field_key, $post_id);
            if ($field_object && isset($field_object['label'])) {
                return $field_object['label'];
            }
        }
        return $field_key;
    }

    /**
     * AJAX handler to check path availability
     */
    public function ajax_check_path_availability() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'xf_translator_ajax')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        $prefix = isset($_POST['prefix']) ? sanitize_text_field($_POST['prefix']) : '';
        $exclude_index = isset($_POST['exclude_index']) && $_POST['exclude_index'] !== '' ? intval($_POST['exclude_index']) : null;
        
        // If path is empty, it will use prefix as fallback, so check prefix instead
        if (empty($path)) {
            if (!empty($prefix)) {
                $path = $prefix;
            } else {
                wp_send_json_success(array(
                    'available' => true,
                    'path' => ''
                ));
                return;
            }
        }
        
        $exists = $this->settings->path_exists($path, $exclude_index);
        
        wp_send_json_success(array(
            'available' => !$exists,
            'path' => $path
        ));
    }

    /**
     * AJAX handler to fetch paginated translation jobs (NEW/EDIT types)
     */
    public function ajax_get_translation_jobs() {
        check_ajax_referer('xf_translator_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'xf-translator')));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Get parameters
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // Build WHERE clause
        $where = array("type IN ('NEW', 'EDIT')");
        $where_values = array();
        
        if (!empty($status)) {
            // Validate status to prevent SQL injection
            $valid_statuses = array('pending', 'processing', 'completed', 'failed');
            if (in_array($status, $valid_statuses)) {
                $where[] = "status = %s";
                $where_values[] = $status;
            }
        }
        
        // Handle search separately with proper escaping
        $post_ids = null;
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            // Get post IDs that match the search
            $post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s",
                $search_like
            ));
            
            if (empty($post_ids)) {
                // No posts match, return empty result
                wp_send_json_success(array(
                    'jobs' => array(),
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => 0,
                        'total_items' => 0,
                        'per_page' => $per_page
                    )
                ));
                return;
            }
        }
        
        // Build final WHERE clause with post IDs if search is used
        if ($post_ids !== null) {
            $post_ids_int = array_map('intval', $post_ids);
            $post_ids_placeholders = implode(',', $post_ids_int);
            $where[] = "parent_post_id IN ($post_ids_placeholders)";
        }
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE $where_clause",
                $where_values
            );
        } else {
            $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        }
        $total = $wpdb->get_var($count_query);
        
        // Calculate pagination
        $offset = ($page - 1) * $per_page;
        $total_pages = ceil($total / $per_page);
        
        // Get jobs
        $order_by = "ORDER BY id DESC";
        $limit = "LIMIT %d OFFSET %d";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE $where_clause $order_by $limit",
                array_merge($where_values, array($per_page, $offset))
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE $where_clause $order_by $limit",
                $per_page,
                $offset
            );
        }
        
        $jobs = $wpdb->get_results($query, ARRAY_A);
        
        // Format jobs with post information
        $formatted_jobs = array();
        foreach ($jobs as $job) {
            $post = get_post($job['parent_post_id']);
            $post_title = $post ? $post->post_title : __('Post not found', 'xf-translator');
            $post_edit_link = $post ? get_edit_post_link($job['parent_post_id']) : '#';
            
            $translated_post = null;
            $translated_post_title = '';
            $translated_post_link = '#';
            
            if ($job['status'] === 'completed' && !empty($job['translated_post_id'])) {
                $translated_post = get_post($job['translated_post_id']);
                if ($translated_post) {
                    $translated_post_title = $translated_post->post_title;
                    $translated_post_link = get_edit_post_link($job['translated_post_id']);
                }
            }
            
            // Determine status color
            $status_color = '#f0ad4e'; // pending
            if ($job['status'] === 'completed') {
                $status_color = '#46b450'; // green
            } elseif ($job['status'] === 'failed') {
                $status_color = '#dc3232'; // red
            } elseif ($job['status'] === 'processing') {
                $status_color = '#0073aa'; // blue
            }
            
            $formatted_jobs[] = array(
                'id' => $job['id'],
                'parent_post_id' => $job['parent_post_id'],
                'post_title' => $post_title,
                'post_edit_link' => $post_edit_link,
                'translated_post_id' => $job['translated_post_id'],
                'translated_post_title' => $translated_post_title,
                'translated_post_link' => $translated_post_link,
                'lng' => $job['lng'],
                'type' => $job['type'] ?: 'NEW',
                'status' => $job['status'],
                'status_color' => $status_color,
                'error_message' => isset($job['error_message']) ? $job['error_message'] : '',
                'created' => $job['created']
            );
        }
        
        wp_send_json_success(array(
            'jobs' => $formatted_jobs,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $per_page
            )
        ));
    }

    /**
     * AJAX handler to fetch paginated existing post jobs (OLD type)
     */
    public function ajax_get_existing_jobs() {
        check_ajax_referer('xf_translator_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'xf-translator')));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Get parameters
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // Build WHERE clause
        $where = array("type = 'OLD'");
        $where_values = array();
        
        if (!empty($status)) {
            // Validate status to prevent SQL injection
            $valid_statuses = array('pending', 'processing', 'completed', 'failed');
            if (in_array($status, $valid_statuses)) {
                $where[] = "status = %s";
                $where_values[] = $status;
            }
        }
        
        // Handle search separately with proper escaping
        $post_ids = null;
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            // Get post IDs that match the search
            $post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s",
                $search_like
            ));
            
            if (empty($post_ids)) {
                // No posts match, return empty result
                wp_send_json_success(array(
                    'jobs' => array(),
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => 0,
                        'total_items' => 0,
                        'per_page' => $per_page
                    )
                ));
                return;
            }
        }
        
        // Build final WHERE clause with post IDs if search is used
        if ($post_ids !== null) {
            $post_ids_int = array_map('intval', $post_ids);
            $post_ids_placeholders = implode(',', $post_ids_int);
            $where[] = "parent_post_id IN ($post_ids_placeholders)";
        }
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE $where_clause",
                $where_values
            );
        } else {
            $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        }
        $total = $wpdb->get_var($count_query);
        
        // Calculate pagination
        $offset = ($page - 1) * $per_page;
        $total_pages = ceil($total / $per_page);
        
        // Get jobs
        $order_by = "ORDER BY id DESC";
        $limit = "LIMIT %d OFFSET %d";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE $where_clause $order_by $limit",
                array_merge($where_values, array($per_page, $offset))
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE $where_clause $order_by $limit",
                $per_page,
                $offset
            );
        }
        
        $jobs = $wpdb->get_results($query, ARRAY_A);
        
        // Format jobs with post information
        $formatted_jobs = array();
        foreach ($jobs as $job) {
            $post = get_post($job['parent_post_id']);
            $post_title = $post ? $post->post_title : __('Post not found', 'xf-translator');
            $post_edit_link = $post ? get_edit_post_link($job['parent_post_id']) : '#';
            
            // Determine status color
            $status_color = '#f0ad4e'; // pending
            if ($job['status'] === 'completed') {
                $status_color = '#46b450'; // green
            } elseif ($job['status'] === 'failed') {
                $status_color = '#dc3232'; // red
            } elseif ($job['status'] === 'processing') {
                $status_color = '#0073aa'; // blue
            }
            
            $formatted_jobs[] = array(
                'id' => $job['id'],
                'parent_post_id' => $job['parent_post_id'],
                'post_title' => $post_title,
                'post_edit_link' => $post_edit_link,
                'translated_post_id' => $job['translated_post_id'],
                'lng' => $job['lng'],
                'type' => $job['type'] ?: 'OLD',
                'status' => $job['status'],
                'status_color' => $status_color,
                'error_message' => isset($job['error_message']) ? $job['error_message'] : '',
                'created' => $job['created']
            );
        }
        
        wp_send_json_success(array(
            'jobs' => $formatted_jobs,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $per_page
            )
        ));
    }

    /**
     * Allow duplicate slugs for translated posts
     * 
     * This filter prevents WordPress from appending "-2", "-3", etc. to slugs
     * when creating translated posts, allowing them to use the same slug as the original.
     *
     * @param string $slug The proposed slug
     * @param int $post_ID The post ID (0 for new posts)
     * @param string $post_status The post status
     * @param string $post_type The post type
     * @param int $post_parent The post parent ID
     * @return string|null The slug to use, or null to let WordPress make it unique
     */
    public function allow_duplicate_slug_for_translated_posts($slug, $post_ID, $post_status, $post_type, $post_parent)
    {
        // Check if we're currently creating a translated post
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        
        $is_creating = Xf_Translator_Processor::is_creating_translated_post();
        
        // Add debug logging
        error_log('XF Translator: pre_wp_unique_post_slug filter called. Slug: ' . $slug . ', Post ID: ' . $post_ID . ', Status: ' . $post_status . ', Type: ' . $post_type . ', Is creating: ' . ($is_creating ? 'yes' : 'no'));
        
        if ($is_creating) {
            // Allow the duplicate slug by returning it
            // This prevents WordPress from appending "-2", "-3", etc.
            error_log('XF Translator: Allowing duplicate slug for translated post (flag set): ' . $slug);
            return $slug;
        }
        
        // For existing posts that are translated posts, also allow duplicate slugs
        // Check both by post ID and by checking if it's a translated post
        if ($post_ID > 0 && is_numeric($post_ID)) {
            $original_post_id = get_post_meta($post_ID, '_xf_translator_original_post_id', true);
            if (empty($original_post_id)) {
                $original_post_id = get_post_meta($post_ID, '_api_translator_original_post_id', true);
            }
            
            if ($original_post_id) {
                // This is a translated post, get the desired slug from meta
                $desired_slug = get_post_meta($post_ID, '_xf_translator_desired_slug', true);
                if (empty($desired_slug)) {
                    // Fallback: get slug from original post
                    $original_post = get_post($original_post_id);
                    if ($original_post) {
                        $desired_slug = $original_post->post_name;
                    }
                }
                
                // If we have a desired slug and it matches what we're trying to set, allow it
                if (!empty($desired_slug) && $slug === $desired_slug) {
                    error_log('XF Translator: Allowing duplicate slug for existing translated post (meta check): ' . $slug);
                    return $slug;
                }
            }
        }
        
        // For all other cases, return null to let WordPress handle slug uniqueness normally
        return null;
    }

    /**
     * Preserve slug for translated posts during wp_insert_post_data filter.
     * This runs before the post is saved and ensures the slug isn't changed.
     * 
     * @param array $data Post data array
     * @param array $postarr Original post data array
     * @return array Modified post data
     */
    public function preserve_slug_for_translated_posts($data, $postarr)
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        
        // Only process if we're creating a translated post
        if (!Xf_Translator_Processor::is_creating_translated_post()) {
            return $data;
        }
        
        // If post_name is set in $postarr, preserve it exactly as provided
        if (isset($postarr['post_name']) && !empty($postarr['post_name'])) {
            $data['post_name'] = $postarr['post_name'];
            error_log('XF Translator: Preserving slug in wp_insert_post_data: ' . $postarr['post_name']);
        }
        
        return $data;
    }

    /**
     * Fix translated post slug after insertion
     * This is a backup method in case WordPress still changes the slug
     * 
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @param bool $update Whether this is an update
     */
    public function fix_translated_post_slug_after_insert($post_id, $post, $update)
    {
        // Check if this is a translated post (either by flag or by meta)
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        
        $is_creating = Xf_Translator_Processor::is_creating_translated_post();
        
        // Check if this is a translated post
        $original_post_id = get_post_meta($post_id, '_xf_translator_original_post_id', true);
        if (empty($original_post_id)) {
            $original_post_id = get_post_meta($post_id, '_api_translator_original_post_id', true);
        }
        
        // Only process if it's a translated post
        if (!$original_post_id && !$is_creating) {
            return;
        }
        
        // Get desired slug from meta first, then fallback to original post
        $desired_slug = get_post_meta($post_id, '_xf_translator_desired_slug', true);
        
        if (empty($desired_slug) && $original_post_id) {
            $original_post = get_post($original_post_id);
            if ($original_post) {
                $desired_slug = $original_post->post_name;
                if (empty($desired_slug)) {
                    $desired_slug = sanitize_title($original_post->post_title);
                }
            }
        }
        
        if (!empty($desired_slug) && $post->post_name !== $desired_slug) {
            error_log('XF Translator: wp_insert_post hook - Fixing slug from ' . $post->post_name . ' to ' . $desired_slug);
            
            global $wpdb;
            $result = $wpdb->update(
                $wpdb->posts,
                array('post_name' => $desired_slug),
                array('ID' => $post_id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                clean_post_cache($post_id);
                wp_cache_delete($post_id, 'posts');
                delete_option('rewrite_rules');
                error_log('XF Translator: wp_insert_post hook - Successfully fixed slug to: ' . $desired_slug);
            } else {
                error_log('XF Translator: wp_insert_post hook - Failed to fix slug. Error: ' . $wpdb->last_error);
            }
        }
    }

    /**
     * Handle fixing old translated post slugs
     * This cleans up existing translated posts that have "-2", "-3", etc. in their slugs
     */
    private function handle_fix_old_translated_post_slugs()
    {
        $result = $this->fix_existing_translated_post_slugs();
        
        if ($result['success']) {
            add_settings_error(
                'xf_translator_messages',
                'xf_translator_slug_fix_success',
                sprintf(
                    __('Successfully fixed %d translated post slug(s). %d post(s) were already correct.', 'xf-translator'),
                    $result['fixed'],
                    $result['already_correct']
                ),
                'success'
            );
        } else {
            add_settings_error(
                'xf_translator_messages',
                'xf_translator_slug_fix_error',
                __('An error occurred while fixing slugs. Please check the debug log for details.', 'xf-translator'),
                'error'
            );
        }
    }

    /**
     * Fix slugs for existing translated posts that have "-2", "-3" suffixes
     * 
     * @return array Result array with 'success', 'fixed', 'already_correct', 'errors' keys
     */
    public function fix_existing_translated_post_slugs()
    {
        global $wpdb;
        
        $result = array(
            'success' => true,
            'fixed' => 0,
            'already_correct' => 0,
            'errors' => array()
        );
        
        // Get all posts that are translations and have slugs with numeric suffixes
        $translated_posts = $wpdb->get_results(
            "SELECT p.ID, p.post_name, p.post_type, p.post_status,
                    pm.meta_value as original_post_id
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key IN ('_xf_translator_original_post_id', '_api_translator_original_post_id')
             AND pm.meta_value > 0
             AND p.post_status != 'trash'
             AND (p.post_name REGEXP '-[0-9]+$' OR p.post_name LIKE '%-2' OR p.post_name LIKE '%-3' OR p.post_name LIKE '%-4' OR p.post_name LIKE '%-5')
             ORDER BY p.ID ASC"
        );
        
        if (empty($translated_posts)) {
            error_log('XF Translator: No translated posts with numeric suffixes found.');
            return $result;
        }
        
        error_log('XF Translator: Found ' . count($translated_posts) . ' translated posts with numeric suffixes. Starting cleanup...');
        
        foreach ($translated_posts as $translated_post) {
            $original_post = get_post($translated_post->original_post_id);
            
            if (!$original_post) {
                $result['errors'][] = sprintf(
                    'Post ID %d: Original post (ID: %s) not found.',
                    $translated_post->ID,
                    $translated_post->original_post_id
                );
                error_log('XF Translator: Original post not found for translated post ID: ' . $translated_post->ID . ', Original ID: ' . $translated_post->original_post_id);
                continue;
            }
            
            // Get the original slug
            $original_slug = $original_post->post_name;
            if (empty($original_slug)) {
                $original_slug = sanitize_title($original_post->post_title);
            }
            
            // Check if the slug needs fixing
            if ($translated_post->post_name === $original_slug) {
                $result['already_correct']++;
                continue;
            }
            
            // Fix the slug
            $update_result = $wpdb->update(
                $wpdb->posts,
                array('post_name' => $original_slug),
                array('ID' => $translated_post->ID),
                array('%s'),
                array('%d')
            );
            
            if ($update_result !== false) {
                // Update the desired slug meta for future reference
                update_post_meta($translated_post->ID, '_xf_translator_desired_slug', $original_slug);
                
                // Clear caches
                clean_post_cache($translated_post->ID);
                wp_cache_delete($translated_post->ID, 'posts');
                
                $result['fixed']++;
                error_log(sprintf(
                    'XF Translator: Fixed slug for post ID %d from "%s" to "%s"',
                    $translated_post->ID,
                    $translated_post->post_name,
                    $original_slug
                ));
            } else {
                $error_msg = sprintf(
                    'Post ID %d: Failed to update slug. Database error: %s',
                    $translated_post->ID,
                    $wpdb->last_error
                );
                $result['errors'][] = $error_msg;
                error_log('XF Translator: ' . $error_msg);
                $result['success'] = false;
            }
        }
        
        // Clear rewrite rules cache
        delete_option('rewrite_rules');
        
        error_log(sprintf(
            'XF Translator: Slug cleanup completed. Fixed: %d, Already correct: %d, Errors: %d',
            $result['fixed'],
            $result['already_correct'],
            count($result['errors'])
        ));
        
        return $result;
    }

}









