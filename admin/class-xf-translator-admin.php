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

	}

	/**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('API Translator', 'api-translator'),
            __('API Translator', 'api-translator'),
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
        $tabs = array(
            'general' => __('Settings', 'api-translator'),
            'queue' => __('Translation Queue', 'api-translator'),
            'existing-queue' => __('Existing Post Queue', 'api-translator'),
            'translations' => __('Translations', 'api-translator'),
            'translation-rules' => __('Translation Rules', 'api-translator')
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
                
            case 'process_queue':
                $this->handle_process_queue();
                break;
                
            case 'analyze_posts':
                $this->handle_analyze_posts();
                break;
                
            case 'retry_queue_entry':
                $this->handle_retry_queue_entry();
                break;
        }
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
        
        $this->add_notice(__('Settings saved successfully.', 'api-translator'), 'success');
    }
    
    /**
     * Handle add language
     */
    private function handle_add_language() {
        if (isset($_POST['language_name']) && isset($_POST['language_prefix'])) {
            $name = sanitize_text_field($_POST['language_name']);
            $prefix = sanitize_text_field($_POST['language_prefix']);
            
            if ($this->settings->add_language($name, $prefix)) {
                $this->add_notice(__('Language added successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Language already exists or invalid data.', 'api-translator'), 'error');
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
            
            if ($this->settings->update_language($index, $name, $prefix)) {
                $this->add_notice(__('Language updated successfully.', 'api-translator'), 'success');
            } else {
                $this->add_notice(__('Failed to update language.', 'api-translator'), 'error');
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
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Get selected post type from form
        $selected_post_type = isset($_POST['analyze_post_type']) ? sanitize_text_field($_POST['analyze_post_type']) : 'post';
        
        // Validate post type exists and is not attachment (media can't be translated)
        if (!post_type_exists($selected_post_type)) {
            $this->add_notice(__('Invalid post type selected.', 'xf-translator'), 'error');
            return;
        }
        
        if ($selected_post_type === 'attachment') {
            $this->add_notice(__('Media files (attachments) cannot be translated and are excluded from analysis.', 'xf-translator'), 'error');
            return;
        }
        
        // Get post type object for display name
        $post_type_obj = get_post_type_object($selected_post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->label : $selected_post_type;
        
        // Get all languages from settings
        $languages = $this->settings->get('languages', array());
        
        if (empty($languages)) {
            $this->add_notice(__('No languages configured. Please add languages in the Settings tab.', 'xf-translator'), 'error');
            return;
        }
        
        // Get all published posts of selected type that are NOT translations (English posts/pages)
        // Posts that have _xf_translator_original_post_id are translations, so we exclude them
        $args = array(
            'post_type' => $selected_post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_xf_translator_original_post_id',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_api_translator_original_post_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $english_posts = get_posts($args);
        
        if (empty($english_posts)) {
            $this->add_notice(
                sprintf(__('No English %s found to analyze.', 'xf-translator'), strtolower($post_type_label)),
                'info'
            );
            return;
        }
        
        $total_added = 0;
        $posts_analyzed = 0;
        
        foreach ($english_posts as $post) {
            $posts_analyzed++;
            $missing_languages = array();
            
            // Check which languages have translations for this post
            foreach ($languages as $language) {
                $language_prefix = $language['prefix'];
                $translated_post_id = get_post_meta($post->ID, '_xf_translator_translated_post_' . $language_prefix, true);
                
                // If no translation exists, add to missing languages
                if (empty($translated_post_id) || !get_post($translated_post_id)) {
                    $missing_languages[] = $language;
                }
            }
            
            // Add missing languages to queue with type 'OLD'
            foreach ($missing_languages as $language) {
                // Check if queue entry already exists for this post and language (any type)
                // This prevents duplicates if the post already has 'NEW' or 'OLD' entries
                $existing_entry = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                     WHERE parent_post_id = %d 
                     AND lng = %s",
                    $post->ID,
                    $language['name']
                ));
                
                // Only add if it doesn't already exist (regardless of type)
                if ($existing_entry == 0) {
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'parent_post_id' => $post->ID,
                            'translated_post_id' => null,
                            'lng' => $language['name'],
                            'status' => 'pending',
                            'type' => 'OLD',
                            'created' => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%s', '%s', '%s')
                    );
                    
                    if ($result !== false) {
                        $total_added++;
                    } else {
                        error_log('XF Translator: Failed to insert queue entry for post ' . $post->ID . ', language: ' . $language['name']);
                    }
                }
            }
        }
        
        $this->add_notice(
            sprintf(
                __('Analysis complete. Analyzed %d %s and added %d missing translations to the queue.', 'xf-translator'),
                $posts_analyzed,
                strtolower($post_type_label),
                $total_added
            ),
            'success'
        );
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
        
        if ($queue_entry['status'] !== 'failed') {
            $this->add_notice(__('Only failed queue entries can be retried.', 'xf-translator'), 'error');
            return;
        }
        
        // Log retry attempt
        error_log('XF Translator: Retrying queue entry #' . $queue_entry_id . ' (Post ID: ' . $queue_entry['parent_post_id'] . ', Language: ' . $queue_entry['lng'] . ')');
        
        // Process the translation immediately (this will trigger API logging)
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        $processor = new Xf_Translator_Processor();
        $result = $processor->process_queue_entry_by_id($queue_entry_id);
        
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
            $this->add_notice(
                sprintf(
                    __('Failed to retry queue entry #%d. %s', 'xf-translator'),
                    $queue_entry_id,
                    $error_message ?: __('Please check the error logs for details.', 'xf-translator')
                ),
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
                    $current_value = is_array($field_value) ? serialize($field_value) : (string)$field_value;
                    
                    // Compare values
                    if ($previous_value !== $current_value) {
                        $edited_fields[] = 'acf_' . $field_key;
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
            $value_to_store = is_array($current_value) ? serialize($current_value) : (string)$current_value;
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
                
                // Store the value for comparison
                $meta_value = isset($meta_values[0]) ? $meta_values[0] : '';
                $value_to_store = is_array($meta_value) ? serialize($meta_value) : (string)$meta_value;
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
                    
                    $value_to_store = is_array($field_value) ? serialize($field_value) : (string)$field_value;
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
                    $current_value = is_array($field_value) ? serialize($field_value) : (string)$field_value;
                    
                    // Compare values
                    if ($previous_value !== $current_value) {
                        $edited_fields[] = 'acf_' . $field_key;
                        
                        // Update stored value for next comparison
                        update_post_meta($post_id, '_xf_translator_prev_acf_' . $field_key, $current_value);
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
        $current_value = is_array($meta_value) ? serialize($meta_value) : (string)$meta_value;
        
        // If no previous value stored, this might be first time tracking
        // Store current value for next time and skip (we need a baseline to compare against)
        if ($previous_value === '') {
            update_post_meta($post_id, $prev_meta_key, $current_value);
            error_log('XF Translator: First time tracking ' . ($is_acf_field ? 'ACF' : 'custom') . ' field ' . $meta_key . ' for post ' . $post_id . ', storing value for next comparison');
            return;
        }
        
        // Compare values
        if ($previous_value !== $current_value) {
            // Update stored value for next comparison
            update_post_meta($post_id, $prev_meta_key, $current_value);
            
            // Determine field prefix
            $field_prefix = $is_acf_field ? 'acf_' : 'meta_';
            
            error_log('XF Translator: Detected ' . ($is_acf_field ? 'ACF' : 'custom') . ' field change: ' . $meta_key . ' for post ' . $post_id . ' (prev: ' . substr($previous_value, 0, 50) . ' -> new: ' . substr($current_value, 0, 50) . ')');
            
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
                $current_value = is_array($current_value) ? serialize($current_value) : (string)$current_value;
                
                // Compare values
                if ($previous_value !== $current_value && $previous_value !== '') {
                    $edited_fields[] = 'meta_' . $meta_key;
                    
                    // Update stored value for next comparison
                    update_post_meta($post_id, '_xf_translator_prev_meta_' . $meta_key, $current_value);
                } elseif ($previous_value === '' && !empty($current_value)) {
                    // First time tracking this field - store it for next time
                    update_post_meta($post_id, '_xf_translator_prev_meta_' . $meta_key, $current_value);
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

   

}









