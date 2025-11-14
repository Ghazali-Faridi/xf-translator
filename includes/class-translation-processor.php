<?php
/**
 * Translation Processor Class
 *
 * Handles processing of translation queue entries
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Xf_Translator_Processor {
    
    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;
    
    /**
     * Flag to prevent translated posts from triggering queue creation
     *
     * @var bool
     */
    private static $creating_translated_post = false;
    
    /**
     * Last error message
     *
     * @var string
     */
    private $last_error = '';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new Settings();
    }
    
    /**
     * Process the next pending translation in queue
     *
     * @param string $type Optional. Filter by type ('NEW' or 'OLD'). If empty, processes any type.
     * @return array|false Processing result or false on failure
     */
    public function process_next_translation($type = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Build query with optional type filter
        $query = "SELECT * FROM $table_name 
                  WHERE status = 'pending'";
        
        if (!empty($type)) {
            $query .= $wpdb->prepare(" AND type = %s", $type);
        }
        
        $query .= " ORDER BY id ASC LIMIT 1";
        
        // Get the latest pending entry
        $queue_entry = $wpdb->get_row($query, ARRAY_A);
        
        if (!$queue_entry) {
            if (!empty($type)) {
                $this->last_error = "No pending entries found in queue with type '{$type}'";
            } else {
                $this->last_error = 'No pending entries found in queue';
            }
            return false; // No pending entries
        }
        
        // Update status to processing
        $wpdb->update(
            $table_name,
            array('status' => 'processing'),
            array('id' => $queue_entry['id']),
            array('%s'),
            array('%d')
        );
        
        $post_id = intval($queue_entry['parent_post_id']);
        $target_language_name = $queue_entry['lng']; // This is now the language name
        
        // Get language prefix from name
        $languages = $this->settings->get('languages', array());
        $target_language_prefix = '';
        foreach ($languages as $lang) {
            if ($lang['name'] === $target_language_name) {
                $target_language_prefix = $lang['prefix'];
                break;
            }
        }
        
        if (empty($target_language_prefix)) {
            $this->last_error = "Language prefix not found for language name: {$target_language_name}";
            error_log('XF Translator Error: ' . $this->last_error);
            // Update status to failed with error message
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Check if this is an EDIT type entry
        if (isset($queue_entry['type']) && $queue_entry['type'] === 'EDIT') {
            return $this->process_edit_translation($queue_entry);
        }
        
        // Get post data
        $post_data = $this->get_post_data($post_id);
        
        if (!$post_data) {
            $this->last_error = "Post data not found for post ID: {$post_id}";
            error_log('XF Translator Error: ' . $this->last_error);
            // Update status to failed with error message
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Build prompt (pass language name for display, but we'll use prefix for API if needed)
        $prompt_data = $this->build_translation_prompt($post_data, $target_language_name);
        $prompt = $prompt_data['prompt'];
        $placeholders_map = $prompt_data['placeholders_map'];
        
        // Call API (use prefix for API calls) - API logging is handled inside the function
        $translation_result = $this->call_translation_api($prompt, $target_language_prefix, $queue_entry['id'], $post_id);
        
        if ($translation_result === false) {
            // Get the detailed error from the API call
            $detailed_error = $this->last_error ?: "API translation call failed. Check API key and model settings.";
            error_log('XF Translator Error: ' . $detailed_error);
            // Update status to failed with error message
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $detailed_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Parse the structured translation response
        $parsed_translation = $this->parse_translation_response($translation_result, $post_data);
        
        // Restore HTML tags and URLs from placeholders
        if (!empty($parsed_translation) && !empty($placeholders_map)) {
            foreach ($parsed_translation as $field => $value) {
                if (isset($placeholders_map[$field]) && !empty($placeholders_map[$field])) {
                    $parsed_translation[$field] = $this->restore_html_and_urls($value, $placeholders_map[$field]);
                }
            }
        }
        
        if (!$parsed_translation) {
            $this->last_error = "Failed to parse translation response. Response format may be incorrect.";
            error_log('XF Translator Error: ' . $this->last_error);
            error_log('XF Translator: Full translation response length: ' . strlen($translation_result));
            error_log('XF Translator: Translation response (first 1000 chars): ' . substr($translation_result, 0, 1000));
            error_log('XF Translator: Original post data fields: ' . implode(', ', array_keys($post_data)));
            
            // Save the raw response for manual inspection
            update_post_meta($post_id, '_xf_translator_raw_response_' . $queue_entry['id'], $translation_result);
            
            // Update status to failed with error message
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Create translated post (pass language name)
        $translated_post_id = $this->create_translated_post($post_id, $target_language_name, $parsed_translation, $post_data);
        
        if ($translated_post_id === false) {
            $this->last_error = "Failed to create translated post. Check WordPress permissions and post data.";
            error_log('XF Translator Error: ' . $this->last_error);
            // Update status to failed with error message
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Update status to completed and store translated post ID
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'translated_post_id' => $translated_post_id
            ),
            array('id' => $queue_entry['id']),
            array('%s', '%d'),
            array('%d')
        );
        
        return array(
            'queue_id' => $queue_entry['id'],
            'post_id' => $post_id,
            'translated_post_id' => $translated_post_id,
            'language' => $target_language_name,
            'translated_content' => $parsed_translation
        );
    }
    
    /**
     * Get post data including title, content, excerpt, and ACF fields
     *
     * @param int $post_id Post ID
     * @return array|false Post data or false on failure
     */
    private function get_post_data($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        $data = array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt
        );
        
        // Get ACF fields if ACF is active
        if (function_exists('get_fields')) {
            $acf_fields = get_fields($post_id);
            if ($acf_fields) {
                // Filter out ACF internal fields (starting with _)
                foreach ($acf_fields as $key => $value) {
                    if (strpos($key, '_') !== 0 && !empty($value)) {
                        // Handle different field types
                        if (is_string($value) || is_numeric($value)) {
                            $data['acf_' . $key] = $value;
                        } elseif (is_array($value)) {
                            // For array fields, convert to string representation
                            $data['acf_' . $key] = $this->format_field_value($value);
                        }
                    }
                }
            }
        }
        
        // Also get custom fields (post meta) that might be ACF fields or other custom fields
        $custom_fields = get_post_meta($post_id);
        foreach ($custom_fields as $key => $values) {
            // Skip internal WordPress fields and ACF internal fields
            if (strpos($key, '_') === 0) {
                continue;
            }
            
            // Skip if already added as ACF field
            if (isset($data['acf_' . $key])) {
                continue;
            }
            
            // Get the first value (most custom fields have single values)
            $value = is_array($values) ? $values[0] : $values;
            
            // Handle different value types
            if (is_string($value) || is_numeric($value)) {
                $data['meta_' . $key] = $value;
            } elseif (is_array($value)) {
                $data['meta_' . $key] = $this->format_field_value($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Format field value for translation
     *
     * @param mixed $value Field value
     * @return string Formatted value
     */
    private function format_field_value($value) {
        if (is_array($value)) {
            // Handle array values - convert to readable format
            $formatted = array();
            foreach ($value as $k => $v) {
                if (is_string($v) || is_numeric($v)) {
                    $formatted[] = $v;
                } elseif (is_array($v)) {
                    $formatted[] = $this->format_field_value($v);
                }
            }
            return implode(', ', $formatted);
        }
        return (string) $value;
    }
    
    /**
     * Extract and replace HTML tags, URLs, and images with placeholders
     * This preserves them so they won't be translated
     *
     * @param string $content Content to process
     * @return array Array with 'content' (processed) and 'placeholders' (map to restore)
     */
    private function protect_html_and_urls($content) {
        $placeholders = array();
        $placeholder_index = 0;
        
        // Protect full HTML tags (including attributes) - this includes img tags, links, etc.
        // Pattern matches: <tag attributes>content</tag> or <self-closing-tag />
        $content = preg_replace_callback(
            '/<[^>]+>/i',
            function($matches) use (&$placeholders, &$placeholder_index) {
                $placeholder = '{{HTML_TAG_' . $placeholder_index . '}}';
                $placeholders[$placeholder] = $matches[0];
                $placeholder_index++;
                return $placeholder;
            },
            $content
        );
        
        // Protect URLs (http://, https://, www.)
        $content = preg_replace_callback(
            '/(https?:\/\/[^\s<>"\'\)]+|www\.[^\s<>"\'\)]+)/i',
            function($matches) use (&$placeholders, &$placeholder_index) {
                $placeholder = '{{URL_' . $placeholder_index . '}}';
                $placeholders[$placeholder] = $matches[0];
                $placeholder_index++;
                return $placeholder;
            },
            $content
        );
        
        return array(
            'content' => $content,
            'placeholders' => $placeholders
        );
    }
    
    /**
     * Restore HTML tags and URLs from placeholders
     *
     * @param string $content Content with placeholders
     * @param array $placeholders Map of placeholders to original content
     * @return string Content with placeholders restored
     */
    private function restore_html_and_urls($content, $placeholders) {
        // Restore in reverse order to handle nested placeholders correctly
        foreach (array_reverse($placeholders, true) as $placeholder => $original) {
            $content = str_replace($placeholder, $original, $content);
        }
        return $content;
    }
    
    /**
     * Build translation prompt from Brand Tone template
     *
     * @param array $post_data Post data array
     * @param string $target_language Target language name
     * @return array Array with 'prompt' and 'placeholders_map' (field => placeholders)
     */
    private function build_translation_prompt($post_data, $target_language) {
        // Get brand tone prompt template
        $brand_tone_template = $this->settings->get('brand_tone', '');
        
        // Get glossary terms
        $glossary_terms = $this->settings->get('glossary_terms', array());
        $glossary_list = '';
        if (!empty($glossary_terms)) {
            $glossary_items = array();
            foreach ($glossary_terms as $term_data) {
                $glossary_items[] = $term_data['term'];
            }
            $glossary_list = implode(', ', $glossary_items);
        }
        
        // $target_language is now the language name directly
        $language_name = $target_language;
        
        // Build content string with all fields, protecting HTML tags and URLs
        $content_parts = array();
        $field_labels_list = array(); // Track field labels for example
        $placeholders_map = array(); // Store placeholders for each field
        
        foreach ($post_data as $field => $value) {
            if (!empty($value)) {
                $field_label = ucfirst(str_replace(array('acf_', 'meta_'), '', $field));
                
                // Protect HTML tags and URLs in the value
                $protected = $this->protect_html_and_urls($value);
                $protected_value = $protected['content'];
                $placeholders_map[$field] = $protected['placeholders'];
                
                $content_parts[] = "{$field_label}: {$protected_value}";
                $field_labels_list[] = $field_label;
            }
        }
        $content_string = implode("\n\n", $content_parts);
        
        // If brand tone template is empty, use default
        if (empty($brand_tone_template)) {
            $brand_tone_template = "You need to translate the following {content} in {lng} but dont translate any brand name / company name you are expert content translator your tone should be professional & should seo optimized dont translate any [] square brackets and square bracket's inside content";
        }
        
        // Replace placeholders
        $prompt = str_replace('{content}', $content_string, $brand_tone_template);
        $prompt = str_replace('{lng}', $language_name, $prompt);
        
        // Replace {glossy} placeholder
        if (!empty($glossary_list)) {
            $prompt = str_replace('{glossy}', "Do not translate these terms: {$glossary_list}", $prompt);
        } else {
            $prompt = str_replace('{glossy}', '', $prompt);
        }
        
        // Build example format based on actual fields being translated
        $example_format = '';
        if (count($field_labels_list) === 1) {
            // Single field example
            $example_label = $field_labels_list[0];
            $example_format = "\n\nIMPORTANT: You MUST respond in the following exact format:\n{$example_label}: [translated text]\n\nExample:\n{$example_label}: [your translation here]";
        } else {
            // Multiple fields example
            $example_parts = array();
            foreach ($field_labels_list as $label) {
                $example_parts[] = "{$label}: [translated {$label} here]";
            }
            $example_format = "\n\nIMPORTANT: You MUST respond in the following exact format, maintaining the same structure with field labels:\n" . implode("\n\n", $example_parts) . "\n\nEach field must be on a separate line with its label followed by a colon and space, then the translated content.";
        }
        
        // Add strict formatting instructions with example
        $prompt = $prompt . "\n\nCRITICAL INSTRUCTIONS:\n1. You MUST maintain the exact same structure as the input.\n2. Each field must start with its label followed by a colon and space (e.g., 'Title: ', 'Content: ', 'Excerpt: ').\n3. Do NOT provide just the translated text without labels.\n4. Do NOT add any explanations, comments, or additional text.\n5. Provide ONLY the translated content in the structured format.\n6. IMPORTANT: Do NOT translate any placeholders like {{HTML_TAG_0}}, {{URL_1}}, etc. Keep them exactly as they appear.\n7. Do NOT translate HTML tags, URLs, or image references. Only translate the actual text content." . $example_format;
        
        return array(
            'prompt' => $prompt,
            'placeholders_map' => $placeholders_map
        );
    }
    
    /**
     * Call translation API (OpenAI or DeepSeek)
     *
     * @param string $prompt Translation prompt
     * @param string $target_language_prefix Target language prefix/code
     * @param int $queue_id Queue entry ID for logging
     * @param int $post_id Post ID for logging
     * @return string|false Translated content or false on failure
     */
    private function call_translation_api($prompt, $target_language_prefix, $queue_id = 0, $post_id = 0) {
        $model = $this->settings->get('selected_model', 'gpt-4o');
        $is_deepseek = strpos($model, 'deepseek') !== false;
        
        // Get API key
        $api_key = $is_deepseek 
            ? $this->settings->get('deepseek_api_key', '')
            : $this->settings->get('api_key', '');
        
        // Determine endpoint
        $endpoint = $is_deepseek 
            ? 'https://api.deepseek.com/v1/chat/completions'
            : 'https://api.openai.com/v1/chat/completions';
        
        // Prepare request body
        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.3,
            'max_tokens' => 8000
        );
        
        // Log API request to debug.log
        $request_log = array(
            'type' => 'API_REQUEST',
            'timestamp' => current_time('mysql'),
            'endpoint' => $endpoint,
            'model' => $model,
            'api_type' => $is_deepseek ? 'DeepSeek' : 'OpenAI',
            'post_id' => $post_id,
            'queue_id' => $queue_id,
            'target_language' => $target_language_prefix,
            'request_body' => $body
        );
        error_log('XF Translator API Request: ' . json_encode($request_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Save API request to post meta BEFORE making the call (so we always have the request)
        if ($post_id && $queue_id) {
            $api_log = array(
                'request' => $body,
                'endpoint' => $endpoint,
                'model' => $model,
                'api_key_configured' => !empty($api_key),
                'timestamp' => current_time('mysql')
            );
            
            // Save initial log with request
            $log_key = '_xf_translator_api_log_' . $queue_id;
            $log_saved = update_post_meta($post_id, $log_key, json_encode($api_log, JSON_PRETTY_PRINT));
            
            // Debug: Log if save failed
            if (!$log_saved) {
                error_log('XF Translator: Failed to save API log to post meta. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id . ', Key: ' . $log_key);
            } else {
                error_log('XF Translator: API log saved successfully. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id);
            }
        } else {
            error_log('XF Translator: Cannot save API log - missing post_id or queue_id. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id);
        }
        
        if (empty($api_key)) {
            $api_type = $is_deepseek ? 'DeepSeek' : 'OpenAI';
            $this->last_error = "{$api_type} API key is not configured. Please add your API key in the plugin settings.";
            error_log('XF Translator API Error: ' . $this->last_error);
            
            // Update log with error
            if ($post_id && $queue_id) {
                $api_log['error'] = $this->last_error;
                $api_log['response_code'] = 0;
                $api_log['response_body'] = '';
                update_post_meta($post_id, '_xf_translator_api_log_' . $queue_id, json_encode($api_log, JSON_PRETTY_PRINT));
            }
            
            return false;
        }
        
        // Make API request
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($body),
            'timeout' => 120
        ));
        
        // Get response details for logging (do this before error checks)
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = is_wp_error($response) ? '' : wp_remote_retrieve_body($response);
        
        // Log API response to debug.log
        $response_log = array(
            'type' => 'API_RESPONSE',
            'timestamp' => current_time('mysql'),
            'endpoint' => $endpoint,
            'model' => $model,
            'api_type' => $is_deepseek ? 'DeepSeek' : 'OpenAI',
            'post_id' => $post_id,
            'queue_id' => $queue_id,
            'target_language' => $target_language_prefix,
            'response_code' => $response_code ?: 0,
            'response_body' => $response_body,
            'is_error' => is_wp_error($response)
        );
        
        if (is_wp_error($response)) {
            $response_log['error'] = $response->get_error_message();
        }
        
        // Try to decode response for better logging
        $decoded_response = json_decode($response_body, true);
        if ($decoded_response !== null) {
            $response_log['response_body_parsed'] = $decoded_response;
        }
        
        error_log('XF Translator API Response: ' . json_encode($response_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Update API log with response (always save, even on error)
        if ($post_id && $queue_id) {
            // Retrieve existing log or create new one
            $log_key = '_xf_translator_api_log_' . $queue_id;
            $existing_log_json = get_post_meta($post_id, $log_key, true);
            if ($existing_log_json) {
                $api_log = json_decode($existing_log_json, true);
                if ($api_log === null) {
                    // If decode failed, create new log
                    $api_log = array(
                        'request' => $body,
                        'endpoint' => $endpoint,
                        'model' => $model,
                        'timestamp' => current_time('mysql')
                    );
                }
            } else {
                // If no existing log, create new one
                $api_log = array(
                    'request' => $body,
                    'endpoint' => $endpoint,
                    'model' => $model,
                    'timestamp' => current_time('mysql')
                );
            }
            
            $api_log['response_code'] = $response_code ?: 0;
            $api_log['response_body'] = $response_body;
            
            if (is_wp_error($response)) {
                $api_log['error'] = $response->get_error_message();
            }
            
            update_post_meta($post_id, $log_key, json_encode($api_log, JSON_PRETTY_PRINT));
        }
        
        // Handle response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->last_error = "API request error: {$error_message}";
            error_log('XF Translator API Error: ' . $this->last_error);
            return false;
        }
        
        $data = json_decode($response_body, true);
        
        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API error';
            $this->last_error = "API returned error code {$response_code}: {$error_message}";
            error_log('XF Translator API Error: ' . $this->last_error);
            error_log('XF Translator API Response: ' . $response_body);
            return false;
        }
        
        // Extract translation
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
        
        $this->last_error = "Invalid API response format. No translation content found.";
        error_log('XF Translator API Error: ' . $this->last_error);
        error_log('XF Translator API Response: ' . print_r($data, true));
        return false;
    }
    
    /**
     * Parse structured translation response and extract fields
     *
     * @param string $translation_response Raw translation response from API
     * @param array $original_data Original post data structure
     * @return array|false Parsed translation data or false on failure
     */
    private function parse_translation_response($translation_response, $original_data) {
        $parsed = array();
        
        // Normalize line endings
        $translation_response = str_replace(array("\r\n", "\r"), "\n", $translation_response);
        $translation_response = trim($translation_response);
        
        // If response is empty, return false
        if (empty($translation_response)) {
            return false;
        }
        
        // Build a mapping of original field labels to field keys
        // This is what we sent to the API, so we know the structure
        $original_labels = array();
        $field_order = array(); // Track the order of fields we sent
        foreach ($original_data as $field_key => $value) {
            if (!empty($value)) {
                // Generate the label we used when sending to API (same as in build_translation_prompt)
                $field_label = ucfirst(str_replace(array('acf_', 'meta_'), '', $field_key));
                $original_labels[$field_key] = $field_label;
                $field_order[] = $field_key; // Track order
            }
        }
        
        // SPECIAL CASE: If only one field is being translated and response doesn't have a label,
        // treat the entire response as the value for that field
        // This handles cases where API returns just the translated text without "FieldName: " prefix
        if (count($field_order) === 1 && !preg_match('/^[^\n]+:\s*/', $translation_response)) {
            $single_field_key = $field_order[0];
            $parsed[$single_field_key] = $translation_response;
            
            // Validate we got something
            if (!empty($parsed[$single_field_key])) {
                return $parsed;
            }
        }
        
        // Split response by label: patterns (works with any script/encoding)
        // Pattern: any text followed by colon and optional space
        $sections = preg_split('/(?=^[^\n]+:\s*)/m', $translation_response, -1, PREG_SPLIT_NO_EMPTY);
        
        // If splitting didn't work well, try by double newlines
        if (count($sections) === 1) {
            $sections = preg_split('/\n\s*\n/', $translation_response, -1, PREG_SPLIT_NO_EMPTY);
        }
        
        // Parse each section to find label: value pairs
        $found_fields = array();
        $found_labels = array(); // Track which labels we found and their order
        
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) {
                continue;
            }
            
            // Try to match label: value pattern (works with any encoding)
            if (preg_match('/^([^:]+):\s*(.*)$/s', $section, $matches)) {
                $response_label = trim($matches[1]);
                $response_value = trim($matches[2]);
                
                // Try to match this response label to an original field
                $matched_field = $this->match_label_to_field($response_label, $original_labels);
                
                if ($matched_field && !empty($response_value)) {
                    // Remove the label from the value if it's still there
                    $response_value = preg_replace('/^' . preg_quote($response_label, '/') . '\s*:\s*/u', '', $response_value, 1);
                    $response_value = trim($response_value);
                    
                    // If we already have this field, append (might be multi-line)
                    if (isset($found_fields[$matched_field])) {
                        $found_fields[$matched_field] .= "\n" . $response_value;
                    } else {
                        $found_fields[$matched_field] = $response_value;
                        $found_labels[] = $matched_field; // Track order
                    }
                } elseif (empty($matched_field)) {
                    // Label didn't match - store for positional matching
                    $found_labels[] = null; // Placeholder for unmatched label
                }
            }
        }
        
        // If we didn't find fields by sections, try line-by-line parsing
        if (empty($found_fields)) {
            $lines = explode("\n", $translation_response);
            $current_field = null;
            $current_value = array();
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if (empty($line)) {
                    // Empty line - save current field if exists
                    if ($current_field !== null && !empty($current_value)) {
                        $found_fields[$current_field] = trim(implode("\n", $current_value));
                        $current_field = null;
                        $current_value = array();
                    }
                    continue;
                }
                
                // Check if line starts with a label: pattern
                if (preg_match('/^([^:]+):\s*(.*)$/u', $line, $matches)) {
                    $response_label = trim($matches[1]);
                    $response_value = trim($matches[2]);
                    
                    // Match to original field
                    $matched_field = $this->match_label_to_field($response_label, $original_labels);
                    
                    if ($matched_field) {
                        // Save previous field
                        if ($current_field !== null && !empty($current_value)) {
                            $found_fields[$current_field] = trim(implode("\n", $current_value));
                        }
                        
                        // Start new field
                        $current_field = $matched_field;
                        $current_value = array();
                        
                        if (!empty($response_value)) {
                            $current_value[] = $response_value;
                        }
                        $found_labels[] = $matched_field;
                    } else {
                        // Unknown label - treat as continuation if we have a current field
                        if ($current_field !== null) {
                            $current_value[] = $line;
                        } else {
                            // No current field - might be first field, use positional matching
                            $found_labels[] = null;
                        }
                    }
                } else {
                    // No label: pattern - continuation of current field
                    if ($current_field !== null) {
                        $current_value[] = $line;
                    }
                }
            }
            
            // Save last field
            if ($current_field !== null && !empty($current_value)) {
                $found_fields[$current_field] = trim(implode("\n", $current_value));
            }
        }
        
        // Positional matching fallback: if we found labels but couldn't match them,
        // match by position (first label = first field, second label = second field, etc.)
        if (count($found_labels) > 0 && count($found_labels) <= count($field_order)) {
            $unmatched_sections = preg_split('/(?=^[^\n]+:\s*)/m', $translation_response, -1, PREG_SPLIT_NO_EMPTY);
            if (count($unmatched_sections) === 1) {
                $unmatched_sections = preg_split('/\n\s*\n/', $translation_response, -1, PREG_SPLIT_NO_EMPTY);
            }
            
            foreach ($unmatched_sections as $index => $section) {
                $section = trim($section);
                if (empty($section)) {
                    continue;
                }
                
                // Extract label and value
                if (preg_match('/^([^:]+):\s*(.*)$/s', $section, $matches)) {
                    $response_label = trim($matches[1]);
                    $response_value = trim($matches[2]);
                    
                    // Remove label from value
                    $response_value = preg_replace('/^' . preg_quote($response_label, '/') . '\s*:\s*/u', '', $response_value, 1);
                    $response_value = trim($response_value);
                    
                    // Match by position if we haven't matched this field yet
                    if ($index < count($field_order) && !isset($found_fields[$field_order[$index]])) {
                        $found_fields[$field_order[$index]] = $response_value;
                    }
                }
            }
        }
        
        // Clean up values - remove any remaining labels at the start
        foreach ($found_fields as $field_key => $value) {
            // Get the original label we sent
            $original_label = isset($original_labels[$field_key]) ? $original_labels[$field_key] : '';
            
            // Remove the label if it appears at the start of the value
            if (!empty($original_label)) {
                $value = preg_replace('/^' . preg_quote($original_label, '/') . '\s*:\s*/iu', '', $value, 1);
            }
            
            // Also try to remove any label: pattern at the start (handles translated labels)
            $value = preg_replace('/^[^:]+:\s*/u', '', $value, 1);
            
            $found_fields[$field_key] = trim($value);
        }
        
        // Use found fields as parsed result
        $parsed = $found_fields;
        
        // If no fields were found and we only sent one field, treat the entire response as that field
        // This handles cases where API returns just the translated text without labels
        if (empty($parsed) && count($original_data) === 1) {
            $single_field_key = key($original_data); // Get first key
            $parsed[$single_field_key] = trim($translation_response);
        }
        
        // If still empty and we have a simple response (no colons, no newlines), try positional matching
        if (empty($parsed) && strpos($translation_response, ':') === false && strpos($translation_response, "\n") === false) {
            // Simple response - likely just one field translated
            // Match by position (first field in original_data)
            if (!empty($field_order) && isset($field_order[0])) {
                $first_field = $field_order[0];
                $parsed[$first_field] = trim($translation_response);
            }
        }
        
        // Ensure we have at least some content
        if (empty($parsed)) {
            return false;
        }
        
        // Final validation - ensure we have at least one field with non-empty content
        // This works for any field name (title, content, excerpt, ACF fields, etc.)
        foreach ($parsed as $key => $value) {
            if (!empty($value)) {
                // Found at least one field with content - validation passed
                return $parsed;
            }
        }
        
        // No valid fields found (all were empty)
        return false;
    }
    
    /**
     * Match a response label to an original field key
     * Uses fuzzy matching to handle translated labels
     *
     * @param string $response_label Label from API response
     * @param array $original_labels Array of field_key => original_label
     * @return string|null Matched field key or null
     */
    private function match_label_to_field($response_label, $original_labels) {
        $response_label_trimmed = trim($response_label);
        
        // First, try exact match (case-insensitive, works with UTF-8)
        foreach ($original_labels as $field_key => $original_label) {
            if (mb_strtolower($original_label, 'UTF-8') === mb_strtolower($response_label_trimmed, 'UTF-8')) {
                return $field_key;
            }
        }
        
        // Second, try partial match (label contains original or vice versa)
        // Use mb_ functions for proper UTF-8 handling
        $response_label_lower = mb_strtolower($response_label_trimmed, 'UTF-8');
        foreach ($original_labels as $field_key => $original_label) {
            $original_label_lower = mb_strtolower($original_label, 'UTF-8');
            
            // Check if labels are similar (one contains the other)
            if (mb_strpos($response_label_lower, $original_label_lower, 0, 'UTF-8') !== false || 
                mb_strpos($original_label_lower, $response_label_lower, 0, 'UTF-8') !== false) {
                return $field_key;
            }
        }
        
        // Third, try matching by field key name (for ACF fields)
        // If response label matches the field key name (without acf_ prefix)
        foreach ($original_labels as $field_key => $original_label) {
            $field_name = str_replace(array('acf_', 'meta_'), '', $field_key);
            $field_name_lower = mb_strtolower($field_name, 'UTF-8');
            
            if ($response_label_lower === $field_name_lower || 
                mb_strpos($response_label_lower, $field_name_lower, 0, 'UTF-8') !== false ||
                mb_strpos($field_name_lower, $response_label_lower, 0, 'UTF-8') !== false) {
                return $field_key;
            }
        }
        
        // Fourth, try character similarity (for translated labels)
        // This helps when "Title" becomes "Titel" (Afrikaans), "Título" (Spanish), or "Заглавие" (Bulgarian)
        $best_match = null;
        $best_similarity = 0;
        
        foreach ($original_labels as $field_key => $original_label) {
            $original_label_lower = mb_strtolower($original_label, 'UTF-8');
            
            // Calculate similarity using similar_text (works with UTF-8)
            similar_text($response_label_lower, $original_label_lower, $similarity);
            
            // For non-Latin scripts, similarity might be low, so we also check length
            $length_diff = abs(mb_strlen($response_label_lower, 'UTF-8') - mb_strlen($original_label_lower, 'UTF-8'));
            $max_length = max(mb_strlen($response_label_lower, 'UTF-8'), mb_strlen($original_label_lower, 'UTF-8'));
            
            // If lengths are similar and similarity is reasonable, consider it a match
            if ($max_length > 0 && ($length_diff / $max_length) < 0.5 && $similarity > 40) {
                if ($similarity > $best_similarity) {
                    $best_similarity = $similarity;
                    $best_match = $field_key;
                }
            }
        }
        
        // Lower threshold for non-Latin scripts (Cyrillic, etc.)
        if ($best_match && $best_similarity > 40) {
            return $best_match;
        }
        
        return null;
    }
    
    /**
     * Create a new WordPress post with translated content
     *
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language name
     * @param array $translated_data Parsed translated data
     * @param array $original_data Original post data
     * @return int|false Translated post ID or false on failure
     */
    private function create_translated_post($original_post_id, $target_language, $translated_data, $original_data) {
        $original_post = get_post($original_post_id);
        
        if (!$original_post) {
            return false;
        }
        
        // Get language prefix for meta key (use prefix for consistency)
        $languages = $this->settings->get('languages', array());
        $language_prefix = '';
        foreach ($languages as $lang) {
            if ($lang['name'] === $target_language) {
                $language_prefix = $lang['prefix'];
                break;
            }
        }
        
        // Check if translated post already exists (use prefix for meta key)
        $existing_translated_post_id = get_post_meta($original_post_id, '_xf_translator_translated_post_' . $language_prefix, true);
        
        // Prepare post data
        $post_data = array(
            'post_type' => $original_post->post_type,
            'post_status' => $original_post->post_status,
            'post_author' => $original_post->post_author,
            'post_parent' => $original_post->post_parent,
            'menu_order' => $original_post->menu_order,
            'comment_status' => $original_post->comment_status,
            'ping_status' => $original_post->ping_status,
        );
        
        // Set translated title
        if (isset($translated_data['title']) && !empty($translated_data['title'])) {
            $post_data['post_title'] = $translated_data['title'];
        } else {
            $post_data['post_title'] = $original_post->post_title . ' (' . $target_language . ')';
        }
        
        // Set translated content
        if (isset($translated_data['content']) && !empty($translated_data['content'])) {
            $post_data['post_content'] = $translated_data['content'];
        } else {
            $post_data['post_content'] = $original_post->post_content;
        }
        
        // Set translated excerpt
        if (isset($translated_data['excerpt']) && !empty($translated_data['excerpt'])) {
            $post_data['post_excerpt'] = $translated_data['excerpt'];
        } else {
            $post_data['post_excerpt'] = $original_post->post_excerpt;
        }
        
        // Generate slug: Store only original English slug (without prefix)
        // WordPress sanitizes slugs and converts slashes to dashes, so we'll use
        // a permalink filter to add the language prefix to the URL
        $original_slug = $original_post->post_name;
        if (empty($original_slug)) {
            // Fallback: generate slug from original title if post_name is empty
            $original_slug = sanitize_title($original_post->post_title);
        }
        
        // Store just the original slug (prefix will be added via permalink filter)
        $post_data['post_name'] = $original_slug;
        
        // Copy categories and tags
        $categories = wp_get_post_categories($original_post_id, array('fields' => 'ids'));
        if (!empty($categories)) {
            $post_data['post_category'] = $categories;
        }
        
        $tags = wp_get_post_tags($original_post_id, array('fields' => 'names'));
        if (!empty($tags)) {
            $post_data['tags_input'] = $tags;
        }
        
        // Set flag to prevent post listener from processing this new translated post
        self::$creating_translated_post = true;
        
        // Create or update post
        if ($existing_translated_post_id && get_post($existing_translated_post_id)) {
            // Update existing translated post
            $post_data['ID'] = $existing_translated_post_id;
            $translated_post_id = wp_update_post($post_data, true);
        } else {
            // Create new translated post
            $translated_post_id = wp_insert_post($post_data, true);
        }
        
        // Clear flag
        self::$creating_translated_post = false;
        
        if (is_wp_error($translated_post_id)) {
            return false;
        }
        
        // Link translated post to original (use prefix for meta keys)
        update_post_meta($translated_post_id, '_xf_translator_original_post_id', $original_post_id);
        update_post_meta($translated_post_id, '_xf_translator_language', $language_prefix);
        update_post_meta($original_post_id, '_xf_translator_translated_post_' . $language_prefix, $translated_post_id);
        
        // Copy and translate custom fields/ACF fields
        foreach ($original_data as $field_key => $original_value) {
            // Skip standard WordPress fields
            if (in_array($field_key, array('title', 'content', 'excerpt'))) {
                continue;
            }
            
            // Get translated value if available
            $translated_value = isset($translated_data[$field_key]) ? $translated_data[$field_key] : $original_value;
            
            // Remove prefix to get actual field name
            $actual_field_name = str_replace(array('acf_', 'meta_'), '', $field_key);
            
            // Update the field
            if (strpos($field_key, 'acf_') === 0 && function_exists('update_field')) {
                // ACF field
                update_field($actual_field_name, $translated_value, $translated_post_id);
            } else {
                // Regular post meta
                update_post_meta($translated_post_id, $actual_field_name, $translated_value);
            }
        }
        
        // Copy taxonomies from original post
        $taxonomies = get_object_taxonomies($original_post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($original_post_id, $taxonomy, array('fields' => 'ids'));
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_post_terms($translated_post_id, $terms, $taxonomy);
            }
        }
        
        return $translated_post_id;
    }
    
    /**
     * Check if we're currently creating a translated post
     *
     * @return bool
     */
    public static function is_creating_translated_post() {
        return self::$creating_translated_post;
    }
    
    /**
     * Get the last error message
     *
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Process a specific queue entry by ID
     *
     * @param int $queue_entry_id Queue entry ID
     * @return array|false Processing result or false on failure
     */
    public function process_queue_entry_by_id($queue_entry_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        // Get the specific queue entry
        $queue_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $queue_entry_id
        ), ARRAY_A);
        
        if (!$queue_entry) {
            $this->last_error = "Queue entry not found for ID: {$queue_entry_id}";
            return false;
        }
        
        // Only process if status is pending or failed (for retry)
        if ($queue_entry['status'] !== 'pending' && $queue_entry['status'] !== 'failed') {
            $this->last_error = "Queue entry #{$queue_entry_id} is not in a processable state (current status: {$queue_entry['status']})";
            return false;
        }
        
        // Update status to processing
        $wpdb->update(
            $table_name,
            array('status' => 'processing'),
            array('id' => $queue_entry_id),
            array('%s'),
            array('%d')
        );
        
        $post_id = intval($queue_entry['parent_post_id']);
        $target_language_name = $queue_entry['lng'];
        
        // Get language prefix from name
        $languages = $this->settings->get('languages', array());
        $target_language_prefix = '';
        foreach ($languages as $lang) {
            if ($lang['name'] === $target_language_name) {
                $target_language_prefix = $lang['prefix'];
                break;
            }
        }
        
        if (empty($target_language_prefix)) {
            $this->last_error = "Language prefix not found for language name: {$target_language_name}";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry_id),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Check if this is an EDIT type entry
        if (isset($queue_entry['type']) && $queue_entry['type'] === 'EDIT') {
            return $this->process_edit_translation($queue_entry);
        }
        
        // Get post data
        $post_data = $this->get_post_data($post_id);
        
        if (!$post_data) {
            $this->last_error = "Post data not found for post ID: {$post_id}";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry_id),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Build prompt
        $prompt_data = $this->build_translation_prompt($post_data, $target_language_name);
        $prompt = $prompt_data['prompt'];
        $placeholders_map = $prompt_data['placeholders_map'];
        
        // Call API (use prefix for API calls) - API logging is handled inside the function
        $translation_result = $this->call_translation_api($prompt, $target_language_prefix, $queue_entry_id, $post_id);
        
        if ($translation_result === false) {
            $detailed_error = $this->last_error ?: "API translation call failed. Check API key and model settings.";
            error_log('XF Translator Error: ' . $detailed_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $detailed_error
                ),
                array('id' => $queue_entry_id),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Parse the structured translation response
        $parsed_translation = $this->parse_translation_response($translation_result, $post_data);
        
        // Restore HTML tags and URLs from placeholders
        if (!empty($parsed_translation) && !empty($placeholders_map)) {
            foreach ($parsed_translation as $field => $value) {
                if (isset($placeholders_map[$field]) && !empty($placeholders_map[$field])) {
                    $parsed_translation[$field] = $this->restore_html_and_urls($value, $placeholders_map[$field]);
                }
            }
        }
        
        if (!$parsed_translation) {
            $this->last_error = "Failed to parse translation response. Response format may be incorrect.";
            error_log('XF Translator Error: ' . $this->last_error);
            error_log('XF Translator: Full translation response length: ' . strlen($translation_result));
            error_log('XF Translator: Translation response (first 1000 chars): ' . substr($translation_result, 0, 1000));
            error_log('XF Translator: Original post data fields: ' . implode(', ', array_keys($post_data)));
            
            // Save the raw response for manual inspection
            update_post_meta($post_id, '_xf_translator_raw_response_' . $queue_entry_id, $translation_result);
            
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry_id),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Check if translated post already exists
        $translated_post_id = get_post_meta($post_id, '_xf_translator_translated_post_' . $target_language_prefix, true);
        
        if ($translated_post_id && get_post($translated_post_id)) {
            // Update existing translated post
            $updated_post_id = $this->update_translated_post($translated_post_id, $parsed_translation, $queue_entry_id);
        } else {
            // Create new translated post
            $updated_post_id = $this->create_translated_post($post_id, $parsed_translation, $target_language_prefix, $queue_entry_id);
        }
        
        if (!$updated_post_id) {
            $this->last_error = "Failed to create or update translated post";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry_id),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Update queue entry with translated post ID and mark as completed
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'translated_post_id' => $updated_post_id,
                'error_message' => null
            ),
            array('id' => $queue_entry_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        return array(
            'success' => true,
            'queue_entry_id' => $queue_entry_id,
            'translated_post_id' => $updated_post_id,
            'post_id' => $post_id,
            'language' => $target_language_name
        );
    }
    
    /**
     * Process EDIT type translation - update existing translated post with only edited fields
     *
     * @param array $queue_entry Queue entry array
     * @return array|false Processing result or false on failure
     */
    private function process_edit_translation($queue_entry) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';
        
        $post_id = intval($queue_entry['parent_post_id']);
        $translated_post_id = intval($queue_entry['translated_post_id']);
        $target_language_name = $queue_entry['lng'];
        
        // Get edited fields from queue entry
        $edited_fields_json = isset($queue_entry['edited_fields']) ? $queue_entry['edited_fields'] : '';
        $edited_fields = !empty($edited_fields_json) ? json_decode($edited_fields_json, true) : array();
        
        if (empty($edited_fields)) {
            $this->last_error = "No edited fields found in queue entry";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Get language prefix
        $languages = $this->settings->get('languages', array());
        $target_language_prefix = '';
        foreach ($languages as $lang) {
            if ($lang['name'] === $target_language_name) {
                $target_language_prefix = $lang['prefix'];
                break;
            }
        }
        
        if (empty($target_language_prefix)) {
            $this->last_error = "Language prefix not found for language name: {$target_language_name}";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Get full post data
        $full_post_data = $this->get_post_data($post_id);
        
        if (!$full_post_data) {
            $this->last_error = "Post data not found for post ID: {$post_id}";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Filter post data to only include edited fields
        $edited_post_data = array();
        foreach ($edited_fields as $field) {
            if (isset($full_post_data[$field])) {
                $edited_post_data[$field] = $full_post_data[$field];
            }
        }
        
        if (empty($edited_post_data)) {
            $this->last_error = "No matching post data found for edited fields";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Build prompt with only edited fields
        $prompt_data = $this->build_translation_prompt($edited_post_data, $target_language_name);
        $prompt = $prompt_data['prompt'];
        $placeholders_map = $prompt_data['placeholders_map'];
        
        // Call API
        $translation_result = $this->call_translation_api($prompt, $target_language_prefix, $queue_entry['id'], $post_id);
        
        if ($translation_result === false) {
            $detailed_error = $this->last_error ?: "API translation call failed. Check API key and model settings.";
            error_log('XF Translator Error: ' . $detailed_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $detailed_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Parse the structured translation response
        $parsed_translation = $this->parse_translation_response($translation_result, $edited_post_data);
        
        // Restore HTML tags and URLs from placeholders
        if (!empty($parsed_translation) && !empty($placeholders_map)) {
            foreach ($parsed_translation as $field => $value) {
                if (isset($placeholders_map[$field]) && !empty($placeholders_map[$field])) {
                    $parsed_translation[$field] = $this->restore_html_and_urls($value, $placeholders_map[$field]);
                }
            }
        }
        
        if (!$parsed_translation) {
            $this->last_error = "Failed to parse translation response. Response format may be incorrect.";
            error_log('XF Translator Error: ' . $this->last_error);
            update_post_meta($post_id, '_xf_translator_raw_response_' . $queue_entry['id'], $translation_result);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Update existing translated post (not create new)
        $update_result = $this->update_translated_post($translated_post_id, $parsed_translation, $edited_fields);
        
        if ($update_result === false) {
            $this->last_error = "Failed to update translated post. Check WordPress permissions and post data.";
            error_log('XF Translator Error: ' . $this->last_error);
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $this->last_error
                ),
                array('id' => $queue_entry['id']),
                array('%s', '%s'),
                array('%d')
            );
            return false;
        }
        
        // Update status to completed
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed'
            ),
            array('id' => $queue_entry['id']),
            array('%s'),
            array('%d')
        );
        
        return array(
            'queue_id' => $queue_entry['id'],
            'post_id' => $post_id,
            'translated_post_id' => $translated_post_id,
            'language' => $target_language_name,
            'translated_content' => $parsed_translation,
            'edited_fields' => $edited_fields
        );
    }
    
    /**
     * Update existing translated post with new translated content
     *
     * @param int $translated_post_id Translated post ID
     * @param array $translated_data Parsed translated data (only edited fields)
     * @param array $edited_fields Array of field names that were edited
     * @return bool Success status
     */
    private function update_translated_post($translated_post_id, $translated_data, $edited_fields) {
        $translated_post = get_post($translated_post_id);
        
        if (!$translated_post) {
            return false;
        }
        
        // Get original post ID to preserve slug format
        $original_post_id = get_post_meta($translated_post_id, '_xf_translator_original_post_id', true);
        if (!$original_post_id) {
            $original_post_id = get_post_meta($translated_post_id, '_api_translator_original_post_id', true);
        }
        
        // Get language prefix
        $language_prefix = get_post_meta($translated_post_id, '_xf_translator_language', true);
        
        // Prepare update data
        $update_data = array('ID' => $translated_post_id);
        
        // Update only the edited fields
        foreach ($edited_fields as $field) {
            if ($field === 'title' && isset($translated_data['title'])) {
                $update_data['post_title'] = $translated_data['title'];
                // Don't update slug - keep it as {language-prefix}/{original-slug}
                // Slug should remain unchanged even when title is updated
            } elseif ($field === 'content' && isset($translated_data['content'])) {
                $update_data['post_content'] = $translated_data['content'];
            } elseif ($field === 'excerpt' && isset($translated_data['excerpt'])) {
                $update_data['post_excerpt'] = $translated_data['excerpt'];
            } elseif (strpos($field, 'acf_') === 0 && isset($translated_data[$field])) {
                // ACF field update
                $acf_field_name = str_replace('acf_', '', $field);
                if (function_exists('update_field')) {
                    update_field($acf_field_name, $translated_data[$field], $translated_post_id);
                }
            } elseif (strpos($field, 'meta_') === 0 && isset($translated_data[$field])) {
                // Meta field update
                $meta_field_name = str_replace('meta_', '', $field);
                update_post_meta($translated_post_id, $meta_field_name, $translated_data[$field]);
            }
        }
        
        // Update the post
        if (count($update_data) > 1) { // More than just ID
            $result = wp_update_post($update_data, true);
            if (is_wp_error($result)) {
                return false;
            }
        }
        
        return true;
    }
}


