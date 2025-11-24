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
		wp_localize_script( $this->plugin_name, 'xfTranslator', array(
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
                
            case 'process_queue':
                $this->handle_process_queue();
                break;
                
            case 'translate_menu':
                $this->handle_translate_menu();
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
        
        if (isset($_POST['processing_delay_minutes'])) {
            $delay = intval($_POST['processing_delay_minutes']);
            if ($delay >= 0) {
                $this->settings->update('processing_delay_minutes', $delay);
            }
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
        
        // Get all languages from settings
        $languages = $this->settings->get('languages', array());
        
        if (empty($languages)) {
            $this->add_notice(__('No languages configured. Please add languages in the Settings tab.', 'xf-translator'), 'error');
            return;
        }
        
        // Process each selected post type
        $total_added = 0;
        $total_posts_analyzed = 0;
        $results_by_type = array();
        
        foreach ($selected_post_types as $selected_post_type) {
            // Get post type object for display name
            $post_type_obj = get_post_type_object($selected_post_type);
            $post_type_label = $post_type_obj ? $post_type_obj->label : $selected_post_type;
            
            // Get all published posts of selected type that are NOT translations (English posts/pages)
            // Posts that have _xf_translator_original_post_id or _xf_translator_language are translations, so we exclude them
            $args = array(
                'post_type' => $selected_post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
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

            if ($start_datetime || $end_datetime) {
                $range = array(
                    'inclusive' => true
                );

                if ($start_datetime) {
                    $range['after'] = $start_datetime->format('Y-m-d H:i:s');
                }

                if ($end_datetime) {
                    $range['before'] = $end_datetime->format('Y-m-d H:i:s');
                }

                $args['date_query'] = array($range);
            }
            
            $english_posts = get_posts($args);
            
            if (empty($english_posts)) {
                $results_by_type[$selected_post_type] = array(
                    'label' => $post_type_label,
                    'analyzed' => 0,
                    'added' => 0
                );
                continue;
            }
            
            $posts_analyzed = 0;
            $added_for_type = 0;
            
            foreach ($english_posts as $post) {
                $posts_analyzed++;
                $missing_languages = array();
                
                // Check which languages have translations for this post
                foreach ($languages as $language) {
                    $language_prefix = $language['prefix'];
                    $has_translation = false;
                    
                    // Method 1: Check meta key on original post (forward link)
                    $translated_post_id = get_post_meta($post->ID, '_xf_translator_translated_post_' . $language_prefix, true);
                    if (!empty($translated_post_id) && get_post($translated_post_id)) {
                        $has_translation = true;
                    }
                    
                    // Method 2: Reverse lookup - check if any translated post exists with this as original
                    // This catches cases where the forward meta key might not be set
                    if (!$has_translation) {
                        $translated_posts = get_posts(array(
                            'post_type' => $post->post_type,
                            'post_status' => 'publish',
                            'posts_per_page' => 1,
                            'meta_query' => array(
                                'relation' => 'AND',
                                array(
                                    'key' => '_xf_translator_original_post_id',
                                    'value' => $post->ID,
                                    'compare' => '='
                                ),
                                array(
                                    'key' => '_xf_translator_language',
                                    'value' => $language_prefix,
                                    'compare' => '='
                                )
                            )
                        ));
                        
                        if (!empty($translated_posts)) {
                            $has_translation = true;
                            // Optionally, set the forward link for future reference
                            update_post_meta($post->ID, '_xf_translator_translated_post_' . $language_prefix, $translated_posts[0]->ID);
                        }
                    }
                    
                    // Also check for old meta key format (_api_translator_original_post_id)
                    if (!$has_translation) {
                        $translated_posts = get_posts(array(
                            'post_type' => $post->post_type,
                            'post_status' => 'publish',
                            'posts_per_page' => 1,
                            'meta_query' => array(
                                'relation' => 'AND',
                                array(
                                    'key' => '_api_translator_original_post_id',
                                    'value' => $post->ID,
                                    'compare' => '='
                                ),
                                array(
                                    'key' => '_xf_translator_language',
                                    'value' => $language_prefix,
                                    'compare' => '='
                                )
                            )
                        ));
                        
                        if (!empty($translated_posts)) {
                            $has_translation = true;
                            // Optionally, set the forward link for future reference
                            update_post_meta($post->ID, '_xf_translator_translated_post_' . $language_prefix, $translated_posts[0]->ID);
                        }
                    }
                    
                    // If no translation exists, add to missing languages
                    if (!$has_translation) {
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
                            $added_for_type++;
                        } else {
                            error_log('XF Translator: Failed to insert queue entry for post ' . $post->ID . ', language: ' . $language['name']);
                        }
                    }
                }
            } // End foreach post
            
            // Store results for this post type
            $results_by_type[$selected_post_type] = array(
                'label' => $post_type_label,
                'analyzed' => $posts_analyzed,
                'added' => $added_for_type
            );
            
            $total_posts_analyzed += $posts_analyzed;
        } // End foreach post type
        
        // Build success message
        $message_parts = array();
        foreach ($results_by_type as $post_type => $result) {
            if ($result['analyzed'] > 0) {
                $message_parts[] = sprintf(
                    '%d %s (%d added)',
                    $result['analyzed'],
                    strtolower($result['label']),
                    $result['added']
                );
            }
        }
        
        if (empty($message_parts)) {
            $this->add_notice(
                __('No English posts found to analyze in the selected post types.', 'xf-translator'),
                'info'
            );
        } else {
            $this->add_notice(
                sprintf(
                    __('Analysis complete.', 'xf-translator'),
                    implode(', ', $message_parts),
                    $total_added
                ),
                'success'
            );

            if ($start_datetime || $end_datetime) {
                $range_text = '';
                if ($start_datetime && $end_datetime) {
                    $range_text = sprintf(
                        __('between %1$s and %2$s', 'xf-translator'),
                        date_i18n(get_option('date_format'), $start_datetime->getTimestamp()),
                        date_i18n(get_option('date_format'), $end_datetime->getTimestamp())
                    );
                } elseif ($start_datetime) {
                    $range_text = sprintf(
                        __('on or after %s', 'xf-translator'),
                        date_i18n(get_option('date_format'), $start_datetime->getTimestamp())
                    );
                } elseif ($end_datetime) {
                    $range_text = sprintf(
                        __('on or before %s', 'xf-translator'),
                        date_i18n(get_option('date_format'), $end_datetime->getTimestamp())
                    );
                }

                if (!empty($range_text)) {
                    $this->add_notice(
                        sprintf(__('Date filter applied: %s.', 'xf-translator'), $range_text),
                        'info'
                    );
                }
            }
        }
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
    private function bulk_get_translation_meta($post_ids, $languages) {
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
    private function bulk_check_existing_entries($post_ids, $languages) {
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
        check_ajax_referer('xf_translator_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'xf-translator')));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $target_language = isset($_POST['target_language']) ? sanitize_text_field($_POST['target_language']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        $prompt_template = isset($_POST['prompt_template']) ? sanitize_text_field($_POST['prompt_template']) : 'current';
        $custom_prompt = isset($_POST['custom_prompt']) ? sanitize_textarea_field($_POST['custom_prompt']) : '';
        
        if (!$post_id || !$target_language || !$model) {
            wp_send_json_error(array('message' => __('Missing required parameters', 'xf-translator')));
            return;
        }
        
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-translation-processor.php';
        $processor = new Xf_Translator_Processor();
        
        $start_time = microtime(true);
        
        // Test translation (doesn't create posts)
        $result = $processor->test_translation($post_id, $target_language, $model, $prompt_template, $custom_prompt);
        
        $end_time = microtime(true);
        $response_time = round($end_time - $start_time, 2);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Translation failed', 'xf-translator')));
            return;
        }
        
        wp_send_json_success(array(
            'translated_title' => $result['title'] ?? '',
            'translated_content' => $result['content'] ?? '',
            'translated_excerpt' => $result['excerpt'] ?? '',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'response_time' => $response_time
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

}









