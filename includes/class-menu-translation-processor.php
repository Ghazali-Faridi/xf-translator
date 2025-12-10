<?php
/**
 * Menu Translation Processor Class
 *
 * Handles translation of WordPress navigation menus
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Xf_Translator_Menu_Processor {
    
    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;
    
    /**
     * Translation processor instance
     *
     * @var Xf_Translator_Processor
     */
    private $translation_processor;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new Settings();
        require_once plugin_dir_path(__FILE__) . 'class-translation-processor.php';
        $this->translation_processor = new Xf_Translator_Processor();
    }
    
    /**
     * Translate an entire menu to a target language
     *
     * @param int $menu_id Original menu ID
     * @param string $target_language Target language name
     * @return int|WP_Error Translated menu ID or WP_Error on failure
     */
    public function translate_menu($menu_id, $target_language) {
        $menu = wp_get_nav_menu_object($menu_id);
        
        if (!$menu) {
            return new WP_Error('invalid_menu', __('Menu not found.', 'xf-translator'));
        }
        
        // Get language prefix
        $languages = $this->settings->get('languages', array());
        $target_language_prefix = '';
        foreach ($languages as $lang) {
            if ($lang['name'] === $target_language) {
                $target_language_prefix = $lang['prefix'];
                break;
            }
        }
        
        if (empty($target_language_prefix)) {
            return new WP_Error('invalid_language', __('Invalid target language.', 'xf-translator'));
        }
        
        // Check if translated menu already exists
        $translated_menu_id = get_term_meta($menu_id, '_xf_translator_menu_' . $target_language_prefix, true);
        $translated_menu = $translated_menu_id ? wp_get_nav_menu_object($translated_menu_id) : false;
        
        // Create or get translated menu
        if (!$translated_menu) {
            // Don't translate menu name - just use original name with language prefix
            $translated_menu_name = $menu->name . ' (' . strtoupper($target_language_prefix) . ')';
            
            $translated_menu_result = wp_create_nav_menu($translated_menu_name);
            
            if (is_wp_error($translated_menu_result)) {
                return $translated_menu_result;
            }
            
            $translated_menu_id = $translated_menu_result;
            
            // Store relationship
            update_term_meta($menu_id, '_xf_translator_menu_' . $target_language_prefix, $translated_menu_id);
            update_term_meta($translated_menu_id, '_xf_translator_original_menu_id', $menu_id);
            update_term_meta($translated_menu_id, '_xf_translator_language', $target_language_prefix);
        } else {
            $translated_menu_id = $translated_menu->term_id;
            
            // Clear existing menu items to prevent duplicates when re-translating
            $existing_items = wp_get_nav_menu_items($translated_menu_id);
            if (!empty($existing_items)) {
                foreach ($existing_items as $existing_item) {
                    wp_delete_post($existing_item->ID, true);
                }
            }
        }
        
        // Get menu items
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        if (empty($menu_items)) {
            return $translated_menu_id; // Menu is empty, return translated menu ID
        }
        
        // Translate each menu item
        $parent_map = array(); // Map original menu item IDs to translated menu item IDs
        $items_translated = 0;
        $items_failed = 0;
        
        foreach ($menu_items as $menu_item) {
            error_log('XF Translator: Translating menu item: ' . $menu_item->title . ' (ID: ' . $menu_item->ID . ')');
            $translated_item_id = $this->translate_menu_item_to_menu($menu_item, $translated_menu_id, $target_language, $target_language_prefix, $parent_map);
            
            if (!is_wp_error($translated_item_id)) {
                $parent_map[$menu_item->ID] = $translated_item_id;
                $items_translated++;
                error_log('XF Translator: Successfully translated menu item: ' . $menu_item->title);
            } else {
                $items_failed++;
                error_log('XF Translator: Failed to translate menu item: ' . $menu_item->title . ' - Error: ' . $translated_item_id->get_error_message());
            }
        }
        
        error_log('XF Translator: Menu translation complete. Items translated: ' . $items_translated . ', Items failed: ' . $items_failed);
        
        return $translated_menu_id;
    }
    
    /**
     * Translate a menu item and add it to a translated menu
     *
     * @param WP_Post $menu_item Original menu item
     * @param int $translated_menu_id Translated menu ID
     * @param string $target_language Target language name
     * @param string $target_language_prefix Target language prefix
     * @param array $parent_map Map of original item IDs to translated item IDs
     * @return int|WP_Error Translated menu item ID or WP_Error
     */
    private function translate_menu_item_to_menu($menu_item, $translated_menu_id, $target_language, $target_language_prefix, $parent_map) {
        // Translate menu item title
        $translated_title = $this->translate_text($menu_item->title, $target_language);
        
        if (is_wp_error($translated_title)) {
            $translated_title = $menu_item->title; // Fallback to original
        }
        
        // Prepare menu item data
        $menu_item_data = array(
            'menu-item-title' => $translated_title,
            'menu-item-type' => $menu_item->type,
            'menu-item-status' => 'publish',
        );
        
        // Handle different menu item types
        if ($menu_item->type === 'post_type' || $menu_item->type === 'post_type_archive') {
            // Get translated post/page ID
            $original_post_id = $menu_item->object_id;
            $translated_post_id = $this->get_translated_post_id($original_post_id, $target_language_prefix);
            
            if ($translated_post_id) {
                $menu_item_data['menu-item-object-id'] = $translated_post_id;
                $menu_item_data['menu-item-object'] = $menu_item->object;
            } else {
                // Use original if translation not found
                $menu_item_data['menu-item-object-id'] = $menu_item->object_id;
                $menu_item_data['menu-item-object'] = $menu_item->object;
            }
        } elseif ($menu_item->type === 'taxonomy') {
            // For taxonomies, try to find translated term or use original
            $menu_item_data['menu-item-object-id'] = $menu_item->object_id;
            $menu_item_data['menu-item-object'] = $menu_item->object;
        } elseif ($menu_item->type === 'custom') {
            // For custom links, translate the URL if it points to a translated post
            $url = $menu_item->url;
            $translated_url = $this->translate_custom_url($url, $target_language_prefix);
            $menu_item_data['menu-item-url'] = $translated_url;
        }
        
        // Handle parent relationship
        if ($menu_item->menu_item_parent > 0 && isset($parent_map[$menu_item->menu_item_parent])) {
            $menu_item_data['menu-item-parent-id'] = $parent_map[$menu_item->menu_item_parent];
        }
        
        // Add menu item to translated menu
        $translated_item_id = wp_update_nav_menu_item($translated_menu_id, 0, $menu_item_data);
        
        if (is_wp_error($translated_item_id)) {
            return $translated_item_id;
        }
        
        // Copy menu item meta
        $meta_keys = array('_menu_item_classes', '_menu_item_xfn', '_menu_item_target', '_menu_item_attr_title');
        foreach ($meta_keys as $meta_key) {
            $meta_value = get_post_meta($menu_item->ID, $meta_key, true);
            if ($meta_value !== false) {
                update_post_meta($translated_item_id, $meta_key, $meta_value);
            }
        }
        
        // Store relationship
        update_post_meta($translated_item_id, '_xf_translator_original_menu_item_id', $menu_item->ID);
        update_post_meta($translated_item_id, '_xf_translator_language', $target_language_prefix);
        
        return $translated_item_id;
    }
    
    /**
     * Translate a single menu item
     *
     * @param int $menu_item_id Menu item ID
     * @param string $target_language Target language name
     * @return bool|WP_Error Success or WP_Error
     */
    public function translate_menu_item($menu_item_id, $target_language) {
        $menu_item = get_post($menu_item_id);
        
        if (!$menu_item || $menu_item->post_type !== 'nav_menu_item') {
            return new WP_Error('invalid_menu_item', __('Invalid menu item.', 'xf-translator'));
        }
        
        // Get the menu this item belongs to
        $menu_terms = wp_get_object_terms($menu_item_id, 'nav_menu');
        if (empty($menu_terms)) {
            return new WP_Error('no_menu', __('Menu item does not belong to any menu.', 'xf-translator'));
        }
        
        $menu = $menu_terms[0];
        
        // Translate the menu item title
        $translated_title = $this->translate_text($menu_item->post_title, $target_language);
        
        if (is_wp_error($translated_title)) {
            return $translated_title;
        }
        
        // Update menu item title
        wp_update_post(array(
            'ID' => $menu_item_id,
            'post_title' => $translated_title
        ));
        
        return true;
    }
    
    /**
     * Translate menu name
     *
     * @param string $menu_name Original menu name
     * @param string $target_language Target language
     * @return string|WP_Error Translated menu name or WP_Error
     */
    private function translate_menu_name($menu_name, $target_language) {
        return $this->translate_text($menu_name, $target_language);
    }
    
    /**
     * Translate text using the translation API
     *
     * @param string $text Text to translate
     * @param string $target_language Target language name
     * @return string|WP_Error Translated text or WP_Error
     */
    private function translate_text($text, $target_language) {
        if (empty($text)) {
            return $text;
        }
        
        // Get language prefix and description
        $languages = $this->settings->get('languages', array());
        $target_language_prefix = '';
        $language_description = '';
        foreach ($languages as $lang) {
            if ($lang['name'] === $target_language) {
                $target_language_prefix = $lang['prefix'];
                $language_description = isset($lang['description']) ? $lang['description'] : '';
                break;
            }
        }
        
        if (empty($target_language_prefix)) {
            return new WP_Error('invalid_language', __('Invalid target language.', 'xf-translator'));
        }
        
        // Get glossary terms for exclusion
        $glossary_terms = $this->settings->get('glossary_terms', array());
        $glossary_list = '';
        if (!empty($glossary_terms)) {
            $glossary_items = array();
            foreach ($glossary_terms as $term_data) {
                $glossary_items[] = $term_data['term'];
            }
            $glossary_list = implode(', ', $glossary_items);
        }
        
        // Build short, dedicated prompt for menu translation
        $prompt = "Translate the following menu item text to {$target_language}: {$text}";
        
        // Add language description if available
        if (!empty($language_description)) {
            $prompt .= "\n\nLanguage context: {$language_description}";
        }
        
        // Add glossary exclusion if terms exist
        if (!empty($glossary_list)) {
            $prompt .= "\n\nDo not translate these words: {$glossary_list}";
        }
        
        $prompt .= "\n\nReturn only the translated text, nothing else.";
        
        // Call API using reflection to access private method
        $reflection = new ReflectionClass($this->translation_processor);
        $method = $reflection->getMethod('call_translation_api');
        $method->setAccessible(true);
        
        $translation_result = $method->invoke($this->translation_processor, $prompt, $target_language_prefix, 0, 0);
        
        if ($translation_result === false) {
            return new WP_Error('translation_failed', __('Translation API call failed.', 'xf-translator'));
        }
        
        // Clean up the response - remove any labels or formatting
        $translated_text = trim($translation_result);
        
        // Remove common label patterns if present
        $translated_text = preg_replace('/^(Title|Content|Excerpt|Label|Text):\s*/i', '', $translated_text);
        
        return $translated_text;
    }
    
    /**
     * Get translated post ID for a given post ID and language
     *
     * @param int $post_id Original post ID
     * @param string $language_prefix Language prefix
     * @return int|false Translated post ID or false if not found
     */
    private function get_translated_post_id($post_id, $language_prefix) {
        // Check for translated post using meta
        $translated_post_id = get_post_meta($post_id, '_xf_translator_translated_post_' . $language_prefix, true);
        
        if ($translated_post_id && get_post($translated_post_id)) {
            return intval($translated_post_id);
        }
        
        // Also check reverse lookup
        global $wpdb;
        $translated_post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
                AND pm1.meta_key = '_xf_translator_original_post_id' 
                AND pm1.meta_value = %d
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
                AND pm2.meta_key = '_xf_translator_language' 
                AND pm2.meta_value = %s
            WHERE p.post_status = 'publish'
            LIMIT 1",
            $post_id,
            $language_prefix
        ));
        
        return $translated_post_id ? intval($translated_post_id) : false;
    }
    
    /**
     * Translate custom URL if it points to a translated post
     *
     * @param string $url Original URL
     * @param string $language_prefix Language prefix
     * @return string Translated URL
     */
    private function translate_custom_url($url, $language_prefix) {
        // Parse URL to get post slug
        $parsed_url = parse_url($url);
        $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
        
        if (empty($path)) {
            return $url;
        }
        
        // Try to find post by slug
        $post = get_page_by_path($path, OBJECT, array('post', 'page'));
        
        if ($post) {
            $translated_post_id = $this->get_translated_post_id($post->ID, $language_prefix);
            
            if ($translated_post_id) {
                $translated_url = get_permalink($translated_post_id);
                return $translated_url;
            }
        }
        
        // If URL already has language prefix, keep it
        if (strpos($path, $language_prefix . '/') === 0) {
            return $url;
        }
        
        // Add language prefix to URL
        $home_url = home_url();
        $url_without_home = str_replace($home_url, '', $url);
        $url_without_home = ltrim($url_without_home, '/');
        
        return $home_url . '/' . $language_prefix . '/' . $url_without_home;
    }
}

