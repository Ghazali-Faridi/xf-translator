<?php
/**
 * Taxonomy Translation Processor Class
 *
 * Handles translation of WordPress taxonomies and terms
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Xf_Translator_Taxonomy_Processor {
    
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
     * Translate all terms in a taxonomy
     *
     * @param string $taxonomy Taxonomy name
     * @param string $target_language Target language name
     * @return array|WP_Error Array of translated term IDs or WP_Error
     */
    public function translate_taxonomy($taxonomy, $target_language) {
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('invalid_taxonomy', __('Invalid taxonomy.', 'xf-translator'));
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
        
        // Get all terms in the taxonomy
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));
        
        if (is_wp_error($terms) || empty($terms)) {
            return array(); // No terms to translate
        }
        
        // Sort terms by hierarchy (parents first)
        $terms_by_parent = array();
        foreach ($terms as $term) {
            $parent_id = $term->parent ? $term->parent : 0;
            if (!isset($terms_by_parent[$parent_id])) {
                $terms_by_parent[$parent_id] = array();
            }
            $terms_by_parent[$parent_id][] = $term;
        }
        
        $translated_term_ids = array();
        $parent_map = array(); // Map original term IDs to translated term IDs
        
        // Translate terms level by level (parents first)
        $this->translate_terms_recursive($terms_by_parent, 0, $taxonomy, $target_language, $target_language_prefix, $parent_map, $translated_term_ids);
        
        return $translated_term_ids;
    }
    
    /**
     * Recursively translate terms maintaining hierarchy
     *
     * @param array $terms_by_parent Terms grouped by parent ID
     * @param int $parent_id Current parent ID
     * @param string $taxonomy Taxonomy name
     * @param string $target_language Target language name
     * @param string $target_language_prefix Target language prefix
     * @param array $parent_map Map of original to translated term IDs
     * @param array $translated_term_ids Array to collect translated term IDs
     */
    private function translate_terms_recursive($terms_by_parent, $parent_id, $taxonomy, $target_language, $target_language_prefix, &$parent_map, &$translated_term_ids) {
        if (!isset($terms_by_parent[$parent_id]) || empty($terms_by_parent[$parent_id])) {
            return;
        }
        
        foreach ($terms_by_parent[$parent_id] as $term) {
            // Translate the term
            $translated_term_id = $this->translate_term($term->term_id, $target_language);
            
            if (!is_wp_error($translated_term_id) && $translated_term_id) {
                $parent_map[$term->term_id] = $translated_term_id;
                $translated_term_ids[] = $translated_term_id;
                
                // Set parent relationship if needed
                if ($term->parent > 0 && isset($parent_map[$term->parent])) {
                    wp_update_term($translated_term_id, $taxonomy, array(
                        'parent' => $parent_map[$term->parent]
                    ));
                }
                
                // Recursively translate children
                if (isset($terms_by_parent[$term->term_id])) {
                    $this->translate_terms_recursive($terms_by_parent, $term->term_id, $taxonomy, $target_language, $target_language_prefix, $parent_map, $translated_term_ids);
                }
            }
        }
    }
    
    /**
     * Translate a single term
     *
     * @param int $term_id Original term ID
     * @param string $target_language Target language name
     * @return int|WP_Error Translated term ID or WP_Error
     */
    public function translate_term($term_id, $target_language) {
        $term = get_term($term_id);
        
        if (!$term || is_wp_error($term)) {
            return new WP_Error('invalid_term', __('Term not found.', 'xf-translator'));
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
        
        // Check if translated term already exists
        $translated_term_id = get_term_meta($term_id, '_xf_translator_term_' . $target_language_prefix, true);
        $translated_term = $translated_term_id ? get_term($translated_term_id) : false;
        
        if ($translated_term && !is_wp_error($translated_term)) {
            return $translated_term_id; // Already translated
        }
        
        // Translate term name
        $translated_name = $this->translate_text($term->name, $target_language);
        if (is_wp_error($translated_name)) {
            return $translated_name;
        }
        
        // Translate term description
        $translated_description = '';
        if (!empty($term->description)) {
            $translated_description = $this->translate_text($term->description, $target_language);
            if (is_wp_error($translated_description)) {
                $translated_description = $term->description; // Fallback
            }
        }
        
        // Generate slug from translated name
        $translated_slug = sanitize_title($translated_name);
        
        // Create translated term
        $translated_term_result = wp_insert_term(
            $translated_name,
            $term->taxonomy,
            array(
                'slug' => $translated_slug,
                'description' => $translated_description,
            )
        );
        
        if (is_wp_error($translated_term_result)) {
            return $translated_term_result;
        }
        
        $translated_term_id = $translated_term_result['term_id'];
        
        // Store relationship
        update_term_meta($term_id, '_xf_translator_term_' . $target_language_prefix, $translated_term_id);
        update_term_meta($translated_term_id, '_xf_translator_original_term_id', $term_id);
        update_term_meta($translated_term_id, '_xf_translator_language', $target_language_prefix);
        
        return $translated_term_id;
    }
    
    /**
     * Get translated term ID for a given term ID and language
     *
     * @param int $term_id Original term ID
     * @param string $language_prefix Language prefix
     * @return int|false Translated term ID or false if not found
     */
    public function get_translated_term_id($term_id, $language_prefix) {
        $translated_term_id = get_term_meta($term_id, '_xf_translator_term_' . $language_prefix, true);
        
        if ($translated_term_id && get_term($translated_term_id)) {
            return intval($translated_term_id);
        }
        
        return false;
    }
    
    /**
     * Get translated term IDs for an array of term IDs
     *
     * @param array $term_ids Array of original term IDs
     * @param string $language_prefix Language prefix
     * @return array Array of translated term IDs (original IDs as keys)
     */
    public function get_translated_term_ids($term_ids, $language_prefix) {
        $translated_ids = array();
        
        foreach ($term_ids as $term_id) {
            $translated_id = $this->get_translated_term_id($term_id, $language_prefix);
            if ($translated_id) {
                $translated_ids[$term_id] = $translated_id;
            }
        }
        
        return $translated_ids;
    }
    
    /**
     * Translate text using the translation API
     * This is specifically optimized for taxonomy/category names (short text)
     *
     * @param string $text Text to translate
     * @param string $target_language Target language name
     * @return string|WP_Error Translated text or WP_Error
     */
    private function translate_text($text, $target_language) {
        if (empty($text)) {
            return $text;
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
        
        // Build simple, direct prompt for taxonomy translation
        // This is much simpler than post translation since we're just translating category/taxonomy names
        $prompt = "Translate the following category/taxonomy name to {$target_language}. Return ONLY the translated text, nothing else.\n\n";
        $prompt .= "Text to translate: {$text}\n\n";
        $prompt .= "Translation:";
        
        // Get glossary terms to exclude from translation
        $glossary_terms = $this->settings->get('glossary_terms', array());
        if (!empty($glossary_terms)) {
            $glossary_items = array();
            foreach ($glossary_terms as $term_data) {
                $glossary_items[] = $term_data['term'];
            }
            $glossary_list = implode(', ', $glossary_items);
            $prompt = "Translate the following category/taxonomy name to {$target_language}. Do not translate the words {$glossary_list} or any occurrence of it. Return ONLY the translated text, nothing else.\n\n";
            $prompt .= "Text to translate: {$text}\n\n";
            $prompt .= "Translation:";
        }
        
        // Call API using reflection to access private method
        $reflection = new ReflectionClass($this->translation_processor);
        $method = $reflection->getMethod('call_translation_api');
        $method->setAccessible(true);
        
        $translation_result = $method->invoke($this->translation_processor, $prompt, $target_language_prefix, 0, 0);
        
        if ($translation_result === false) {
            return new WP_Error('translation_failed', __('Translation API call failed.', 'xf-translator'));
        }
        
        // Clean up the response - remove any labels, formatting, or extra text
        $translated_text = trim($translation_result);
        
        // Remove common prefixes/patterns that LLMs might add
        $translated_text = preg_replace('/^(Translation|Translated|Result|Answer|Category|Taxonomy|Name|Text):\s*/i', '', $translated_text);
        
        // Remove quotes if the entire response is wrapped in quotes
        $translated_text = trim($translated_text, '"\'');
        
        // Remove any explanatory text after the translation (common LLM behavior)
        // Look for common patterns like "Of course!", "Here is", etc.
        $translated_text = preg_replace('/^(Of course!|Here is|Here\'s|The translation is|Translated to|In .+ it is|It means):\s*/i', '', $translated_text);
        
        // Remove anything after a newline (LLMs sometimes add explanations)
        if (strpos($translated_text, "\n") !== false) {
            $lines = explode("\n", $translated_text);
            $translated_text = trim($lines[0]);
        }
        
        // Final cleanup - remove any remaining explanatory text
        $translated_text = preg_replace('/\s*\(.*?\)\s*$/', '', $translated_text); // Remove parenthetical explanations
        
        return trim($translated_text);
    }
}

