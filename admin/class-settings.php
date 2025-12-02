<?php
/**
 * Settings Management Class
 *
 * Handles all plugin settings using WordPress options API
 *
 * @package API_Translator
 */

class Settings {
    
    /**
     * Option name
     *
     * @var string
     */
    private $option_name = 'api_translator_settings';
    
    /**
     * Default settings
     *
     * @var array
     */
    private $defaults = array(
        'api_key' => '',
        'deepseek_api_key' => '',
        'selected_model' => 'gpt-4o',
        'languages' => array(),
        'brand_tone' => '',
        'exclude_paths' => array(),
        'glossary_terms' => array(),
        'processing_delay_minutes' => 0,
        'translatable_post_meta_fields' => array(),
        'translatable_user_meta_fields' => array('description', 'user_description'),
        'translatable_acf_fields' => array()
    );
    
    /**
     * Get all settings
     *
     * @return array
     */
    public function get_all() {
        $settings = get_option($this->option_name, $this->defaults);
        return wp_parse_args($settings, $this->defaults);
    }
    
    /**
     * Get a specific setting
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get($key, $default = null) {
        $settings = $this->get_all();
        
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        
        return $default !== null ? $default : (isset($this->defaults[$key]) ? $this->defaults[$key] : null);
    }
    
    /**
     * Update a setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public function update($key, $value) {
        $settings = $this->get_all();
        $settings[$key] = $value;
        return update_option($this->option_name, $settings);
    }
    
    /**
     * Update all settings
     *
     * @param array $settings Settings array
     * @return bool
     */
    public function update_all($settings) {
        $current = $this->get_all();
        $merged = wp_parse_args($settings, $current);
        return update_option($this->option_name, $merged);
    }
    
    /**
     * Add a language
     *
     * @param string $name Language name
     * @param string $prefix Language prefix (e.g., 'en', 'es')
     * @param string $path Language path for URL (e.g., 'fr', 'Ar')
     * @param string $description Language description
     * @return bool
     */
    public function add_language($name, $prefix, $path = '', $description = '') {
        $languages = $this->get('languages', array());
        
        // Check if prefix already exists
        foreach ($languages as $lang) {
            if (isset($lang['prefix']) && $lang['prefix'] === $prefix) {
                return false; // Prefix already exists
            }
        }
        
        // If path is not provided, use prefix as fallback
        if (empty($path)) {
            $path = $prefix;
        }
        
        // Check if path already exists (after determining final path value)
        if ($this->path_exists($path)) {
            return false; // Path already exists
        }
        
        $languages[] = array(
            'name' => sanitize_text_field($name),
            'prefix' => sanitize_text_field($prefix),
            'path' => sanitize_text_field($path),
            'description' => sanitize_textarea_field($description)
        );
        
        return $this->update('languages', $languages);
    }
    
    /**
     * Check if prefix is already used
     *
     * @param string $prefix Prefix to check
     * @param int $exclude_index Optional index to exclude from check (for edit)
     * @return bool True if prefix exists, false otherwise
     */
    public function prefix_exists($prefix, $exclude_index = null) {
        $languages = $this->get('languages', array());
        
        foreach ($languages as $index => $lang) {
            // Skip the current language being edited
            if ($exclude_index !== null && $index === $exclude_index) {
                continue;
            }
            
            if (isset($lang['prefix']) && $lang['prefix'] === $prefix) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if path is already used
     *
     * @param string $path Path to check
     * @param int $exclude_index Optional index to exclude from check (for edit)
     * @return bool True if path exists, false otherwise
     */
    public function path_exists($path, $exclude_index = null) {
        if (empty($path)) {
            return false; // Empty paths are allowed (will use prefix as fallback)
        }
        
        $languages = $this->get('languages', array());
        
        foreach ($languages as $index => $lang) {
            // Skip the current language being edited
            if ($exclude_index !== null && $index === $exclude_index) {
                continue;
            }
            
            // Get the path for this language (use path if set, otherwise prefix)
            $lang_path = '';
            if (isset($lang['path']) && !empty($lang['path'])) {
                $lang_path = $lang['path'];
            } elseif (isset($lang['prefix'])) {
                $lang_path = $lang['prefix'];
            }
            
            // Normalize paths for comparison (remove non-alphanumeric, case-insensitive)
            $normalized_path = preg_replace('/[^A-Za-z0-9]/', '', strtolower($path));
            $normalized_lang_path = preg_replace('/[^A-Za-z0-9]/', '', strtolower($lang_path));
            
            if ($normalized_path === $normalized_lang_path && !empty($normalized_path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Update a language
     *
     * @param int $index Language index
     * @param string $name Language name
     * @param string $prefix Language prefix
     * @param string $path Language path for URL
     * @param string $description Language description
     * @return bool
     */
    public function update_language($index, $name, $prefix, $path = '', $description = '') {
        $languages = $this->get('languages', array());
        
        if (!isset($languages[$index])) {
            return false;
        }
        
        // Check if prefix is already used by another language
        if ($this->prefix_exists($prefix, $index)) {
            return false;
        }
        
        // Preserve existing path and description if not provided
        $existing = $languages[$index];
        if (empty($path) && isset($existing['path'])) {
            $path = $existing['path'];
        }
        if (empty($description) && isset($existing['description'])) {
            $description = $existing['description'];
        }
        
        // If path is still not provided, use prefix as fallback
        if (empty($path)) {
            $path = $prefix;
        }
        
        // Check if path is already used by another language
        if ($this->path_exists($path, $index)) {
            return false;
        }
        
        $languages[$index] = array(
            'name' => sanitize_text_field($name),
            'prefix' => sanitize_text_field($prefix),
            'path' => sanitize_text_field($path),
            'description' => sanitize_textarea_field($description)
        );
        
        return $this->update('languages', $languages);
    }
    
    /**
     * Remove a language
     *
     * @param int $index Language index
     * @return bool
     */
    public function remove_language($index) {
        $languages = $this->get('languages', array());
        
        if (!isset($languages[$index])) {
            return false;
        }
        
        unset($languages[$index]);
        $languages = array_values($languages); // Re-index array
        
        return $this->update('languages', $languages);
    }
    
    /**
     * Add exclude path
     *
     * @param string $path URL path to exclude
     * @return bool
     */
    public function add_exclude_path($path) {
        $paths = $this->get('exclude_paths', array());
        
        $path = sanitize_text_field($path);
        if (in_array($path, $paths)) {
            return false; // Path already exists
        }
        
        $paths[] = $path;
        return $this->update('exclude_paths', $paths);
    }
    
    /**
     * Remove exclude path
     *
     * @param int $index Path index
     * @return bool
     */
    public function remove_exclude_path($index) {
        $paths = $this->get('exclude_paths', array());
        
        if (!isset($paths[$index])) {
            return false;
        }
        
        unset($paths[$index]);
        $paths = array_values($paths);
        
        return $this->update('exclude_paths', $paths);
    }
    
    /**
     * Add glossary term
     *
     * @param string $term Term to add
     * @param string $context Optional context
     * @return bool
     */
    public function add_glossary_term($term, $context = '') {
        $terms = $this->get('glossary_terms', array());
        
        $term_data = array(
            'term' => sanitize_text_field($term),
            'context' => sanitize_text_field($context)
        );
        
        // Check if term already exists
        foreach ($terms as $existing) {
            if ($existing['term'] === $term_data['term'] && $existing['context'] === $term_data['context']) {
                return false; // Term already exists
            }
        }
        
        $terms[] = $term_data;
        return $this->update('glossary_terms', $terms);
    }
    
    /**
     * Remove glossary term
     *
     * @param int $index Term index
     * @return bool
     */
    public function remove_glossary_term($index) {
        $terms = $this->get('glossary_terms', array());
        
        if (!isset($terms[$index])) {
            return false;
        }
        
        unset($terms[$index]);
        $terms = array_values($terms);
        
        return $this->update('glossary_terms', $terms);
    }
    
    /**
     * Update brand tone
     *
     * @param string $tone Tone prompt
     * @return bool
     */
    public function update_brand_tone($tone) {
        return $this->update('brand_tone', sanitize_textarea_field($tone));
    }
    
    /**
     * Get translatable post meta fields
     *
     * @return array Array of meta field keys
     */
    public function get_translatable_post_meta_fields() {
        return $this->get('translatable_post_meta_fields', array());
    }
    
    /**
     * Get translatable user meta fields
     *
     * @return array Array of meta field keys
     */
    public function get_translatable_user_meta_fields() {
        return $this->get('translatable_user_meta_fields', array('description', 'user_description'));
    }
    
    /**
     * Update translatable post meta fields
     *
     * @param array $fields Array of meta field keys
     * @return bool
     */
    public function update_translatable_post_meta_fields($fields) {
        $sanitized = array_map('sanitize_text_field', $fields);
        return $this->update('translatable_post_meta_fields', array_unique($sanitized));
    }
    
    /**
     * Update translatable user meta fields
     *
     * @param array $fields Array of meta field keys
     * @return bool
     */
    public function update_translatable_user_meta_fields($fields) {
        $sanitized = array_map('sanitize_text_field', $fields);
        return $this->update('translatable_user_meta_fields', array_unique($sanitized));
    }

    /**
     * Get translatable ACF fields
     *
     * @return array Array of ACF field keys
     */
    public function get_translatable_acf_fields() {
        return $this->get('translatable_acf_fields', array());
    }

    /**
     * Update translatable ACF fields
     *
     * @param array $fields Array of ACF field keys
     * @return bool
     */
    public function update_translatable_acf_fields($fields) {
        $sanitized = array_map('sanitize_text_field', $fields);
        return $this->update('translatable_acf_fields', array_unique($sanitized));
    }
}

