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

class Xf_Translator_Processor
{

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
     * Last test tokens used (for test translation)
     *
     * @var int
     */
    private $last_test_tokens = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settings = new Settings();
    }

    /**
     * Process the next pending translation in queue
     *
     * @param string $type Optional. Filter by type ('NEW' or 'OLD'). If empty, processes any type.
     * @return array|false Processing result or false on failure
     */
    public function process_next_translation($type = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xf_translate_queue';

        // Get processing delay setting (only applies to NEW type entries)
        $processing_delay_minutes = $this->settings->get('processing_delay_minutes', 0);
        
        // Build query with optional type filter
        $query = "SELECT * FROM $table_name 
                  WHERE status = 'pending'";

        if (!empty($type)) {
            $query .= $wpdb->prepare(" AND type = %s", $type);
        }
        
        // Apply delay check for NEW type entries only
        // Only process entries that are at least X minutes old
        if (($type === 'NEW' || empty($type)) && $processing_delay_minutes > 0) {
            // Calculate the minimum created time (current time minus delay minutes)
            $min_created_time = date('Y-m-d H:i:s', strtotime("-{$processing_delay_minutes} minutes"));
            $query .= $wpdb->prepare(" AND created <= %s", $min_created_time);
        }

        $query .= " ORDER BY id ASC LIMIT 1";

        // Get the latest pending entry
        $queue_entry = $wpdb->get_row($query, ARRAY_A);

        if (!$queue_entry) {
            if (!empty($type)) {
                $this->last_error = "No pending entries found in queue with type '{$type}'";
                // If delay is set and we're looking for NEW entries, mention it might be due to delay
                if (($type === 'NEW' || empty($type)) && $processing_delay_minutes > 0) {
                    $this->last_error .= " (or entries are not yet {$processing_delay_minutes} minutes old)";
                }
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
    private function get_post_data($post_id)
    {
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        $data = array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt
        );

        // Get translatable meta fields from settings
        $translatable_meta_fields = $this->settings->get_translatable_post_meta_fields();
        
        // If no fields configured, use default SEO fields for backward compatibility
        if (empty($translatable_meta_fields)) {
            $translatable_meta_fields = array(
                '_yoast_wpseo_title',
                '_yoast_wpseo_metadesc',
                'rank_math_title',
                'rank_math_description',
                '_aioseo_title',
                '_aioseo_description',
                '_seopress_titles_title',
                '_seopress_titles_desc',
                '_meta_title',
                '_meta_description',
                'meta_title',
                'meta_description'
            );
        }

        // Get selected meta fields
        if (!empty($translatable_meta_fields)) {
            foreach ($translatable_meta_fields as $meta_key) {
                $value = get_post_meta($post_id, $meta_key, true);
                
                // Only include if value is not empty and is a string/numeric
                if (!empty($value) && (is_string($value) || is_numeric($value))) {
                    $data['meta_' . $meta_key] = $value;
                }
            }
        }

        return $data;
    }
    
    /**
     * Get user data including selected user meta fields
     *
     * @param int $user_id User ID
     * @return array|false User data or false on failure
     */
    private function get_user_data($user_id)
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return false;
        }

        $data = array();

        // Get translatable user meta fields from settings
        $translatable_meta_fields = $this->settings->get_translatable_user_meta_fields();
        
        // Default fields if none configured
        if (empty($translatable_meta_fields)) {
            $translatable_meta_fields = array('description', 'user_description');
        }

        // Get selected user meta fields
        foreach ($translatable_meta_fields as $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            
            // Only include if value is not empty and is a string/numeric
            if (!empty($value) && (is_string($value) || is_numeric($value))) {
                $data['user_meta_' . $meta_key] = $value;
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
    private function format_field_value($value)
    {
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

    // (chunked translation helpers removed)

    /**
     * Extract and replace URLs with placeholders
     * HTML tags are kept as-is in the content
     *
     * @param string $content Content to process
     * @return array Array with 'content' (processed) and 'placeholders' (map to restore)
     */
    private function protect_urls($content)
    {
        $placeholders = array();
        $placeholder_index = 0;

        // Protect URLs (http://, https://, www.)
        $content = preg_replace_callback(
            '/(https?:\/\/[^\s<>"\'\)]+|www\.[^\s<>"\'\)]+)/i',
            function ($matches) use (&$placeholders, &$placeholder_index) {
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
     * Extract and replace HTML tags, URLs, and images with placeholders
     * This preserves them so they won't be translated
     * DEPRECATED: Use protect_urls() instead - HTML tags are now kept as-is
     *
     * @param string $content Content to process
     * @return array Array with 'content' (processed) and 'placeholders' (map to restore)
     */
    private function protect_html_and_urls($content)
    {
        // For backward compatibility, only protect URLs
        return $this->protect_urls($content);
    }

    /**
     * Restore URLs from placeholders
     * HTML tags are no longer stored as placeholders, they remain in the content
     *
     * @param string $content Content with placeholders
     * @param array $placeholders Map of placeholders to original content (only URLs now)
     * @return string Content with placeholders restored
     */
    private function restore_html_and_urls($content, $placeholders)
    {
        // Restore in reverse order to handle nested placeholders correctly
        // Only URLs are in placeholders now, HTML tags remain in content
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
    private function build_translation_prompt($post_data, $target_language)
    {
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

        // Build content string with all fields, keeping HTML tags as-is, only protecting URLs
        $content_parts = array();
        $field_labels_list = array(); // Track field labels for example
        $placeholders_map = array(); // Store placeholders for each field (only URLs now)

        foreach ($post_data as $field => $value) {
            if (!empty($value)) {
                $field_label = ucfirst(str_replace(array('acf_', 'meta_'), '', $field));

                // Only protect URLs, keep HTML tags as-is
                $protected = $this->protect_urls($value);
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

        // Get language description for {desc} placeholder
        $language_description = '';
        $languages = $this->settings->get('languages', array());
        foreach ($languages as $lang) {
            if (isset($lang['name']) && $lang['name'] === $target_language) {
                $language_description = isset($lang['description']) ? $lang['description'] : '';
                break;
            }
        }

        // Replace placeholders
        $prompt = str_replace('{content}', $content_string, $brand_tone_template);
        $prompt = str_replace('{lng}', $language_name, $prompt);

        // Replace {desc} placeholder with language description
        if (!empty($language_description)) {
            $prompt = str_replace('{desc}', $language_description, $prompt);
        } else {
            $prompt = str_replace('{desc}', '', $prompt);
        }

        // Replace {glossy} placeholder with just the glossary list
        // The instruction text should already be in the template around {glossy}
        if (!empty($glossary_list)) {
            $prompt = str_replace('{glossy}', $glossary_list, $prompt);
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
        $prompt = $prompt . "\n\nCRITICAL INSTRUCTIONS:\n1. You MUST maintain the exact same structure as the input.\n2. Each field must start with its label followed by a colon and space (e.g., 'Title: ', 'Content: ', 'Excerpt: ').\n3. Do NOT provide just the translated text without labels.\n4. Do NOT add any explanations, comments, or additional text.\n5. Provide ONLY the translated content in the structured format.\n6. IMPORTANT: Do NOT translate any placeholders like {{URL_0}}, {{URL_1}}, etc. Keep them exactly as they appear.\n7. CRITICAL: Do NOT translate HTML tags. Keep all HTML tags exactly as they appear in the original content, including all attributes, opening tags, closing tags, and self-closing tags.\n8. CRITICAL: Do NOT translate images. Keep all image tags (<img>), image URLs, image attributes (src, alt, etc.), and any image references exactly as they appear in the original content.\n9. Only translate the actual text content that appears between HTML tags or outside of HTML tags. HTML tags and images must remain completely unchanged." . $example_format;

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
    private function call_translation_api($prompt, $target_language_prefix, $queue_id = 0, $post_id = 0)
    {
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

        // Get max_tokens based on model capabilities
        $max_tokens = $this->get_max_tokens_for_model($model);

        // Prepare messages array
        $messages = array();

        // Add system message for OpenAI to clarify context (only for OpenAI, not DeepSeek)
        if (!$is_deepseek) {
        }

        // Add user message with translation prompt
        $messages[] = array(
            'role' => 'user',
            'content' => $prompt
        );


        // Prepare request body
        $body = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.3
        );
        
        // Some newer models (like gpt-5.1) require max_completion_tokens instead of max_tokens
        // Check if model name contains version 5 or higher
        if (preg_match('/gpt-[5-9]|o[5-9]/i', $model)) {
            $body['max_completion_tokens'] = $max_tokens;
        } else {
            $body['max_tokens'] = $max_tokens;
        }

        // Calculate timeout based on content size
        // Note: Cloudflare has a 100-second timeout limit, so we cap at 90 seconds to stay under it
        $content_length = strlen($prompt);
        // Base timeout: 30 seconds, add 5 seconds per 1000 characters
        $calculated_timeout = 30 + intval($content_length / 1000) * 5;
        // Cap at 90 seconds to stay under Cloudflare's 100-second limit
        $timeout = min($calculated_timeout, 90);
        
        // Warn if content is very large (might need chunking)
        if ($content_length > 50000) {
            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::warning('Large content detected (' . number_format($content_length) . ' chars). Translation may timeout. Consider breaking content into smaller chunks.');
            }
        }

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
            'content_length' => $content_length,
            'timeout' => $timeout,
            'request_body' => $body
        );
        // Log to plugin-specific log file
        if (class_exists('Xf_Translator_Logger')) {
            Xf_Translator_Logger::log_api('REQUEST', $request_log);
        } else {
            error_log('XF Translator API Request: ' . json_encode($request_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

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
                if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::error('Failed to save API log to post meta. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id . ', Key: ' . $log_key);
            } else {
                error_log('XF Translator: Failed to save API log to post meta. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id . ', Key: ' . $log_key);
            }
            } else {
                if (class_exists('Xf_Translator_Logger')) {
                    Xf_Translator_Logger::debug('API log saved successfully. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id);
                } else {
                    error_log('XF Translator: API log saved successfully. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id);
                }
            }
        } else {
            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::warning('Cannot save API log - missing post_id or queue_id. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id);
            } else {
                error_log('XF Translator: Cannot save API log - missing post_id or queue_id. Post ID: ' . $post_id . ', Queue ID: ' . $queue_id);
            }
        }

        if (empty($api_key)) {
            $api_type = $is_deepseek ? 'DeepSeek' : 'OpenAI';
            $this->last_error = "{$api_type} API key is not configured. Please add your API key in the plugin settings.";
            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::error('API Error: ' . $this->last_error);
            } else {
                error_log('XF Translator API Error: ' . $this->last_error);
            }

            // Update log with error
            if ($post_id && $queue_id) {
                $api_log['error'] = $this->last_error;
                $api_log['response_code'] = 0;
                $api_log['response_body'] = '';
                update_post_meta($post_id, '_xf_translator_api_log_' . $queue_id, json_encode($api_log, JSON_PRETTY_PRINT));
            }

            return false;
        }

        // Increase PHP execution time limit to allow for API requests
        // Set to timeout + 30 seconds buffer to ensure the request can complete
        $php_time_limit = $timeout + 30;
        if (function_exists('set_time_limit')) {
            @set_time_limit($php_time_limit);
        }
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', $php_time_limit);
        }

        // Log PHP execution time settings
        if (class_exists('Xf_Translator_Logger')) {
            Xf_Translator_Logger::debug('PHP max_execution_time set to: ' . $php_time_limit . ' seconds (timeout: ' . $timeout . ' seconds, content length: ' . number_format($content_length) . ' chars)');
        } else {
            error_log('XF Translator: PHP max_execution_time set to: ' . $php_time_limit . ' seconds (timeout: ' . $timeout . ' seconds)');
        }

        // Make API request
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($body),
            'timeout' => $timeout
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

        // Log to plugin-specific log file
        if (class_exists('Xf_Translator_Logger')) {
            Xf_Translator_Logger::log_api('RESPONSE', $response_log);
        } else {
            error_log('XF Translator API Response: ' . json_encode($response_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

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
            $error_code = $response->get_error_code();
            $current_max_execution_time = ini_get('max_execution_time');

            // Check if it's a timeout error (including Cloudflare 524)
            $is_timeout = strpos($error_message, 'timeout') !== false || 
                         strpos($error_message, 'timed out') !== false || 
                         strpos($error_message, '524') !== false ||
                         $error_code === 'http_request_failed' ||
                         $response_code === 524;
            
            if ($is_timeout) {
                $this->last_error = "Translation request timed out (Cloudflare/Server timeout). The content may be too large (" . number_format($content_length) . " characters). ";
                $this->last_error .= "Try breaking the content into smaller chunks or the API may be experiencing high load. ";
                $this->last_error .= "Request timeout was set to {$timeout} seconds.";
            } else {
                $this->last_error = "API request error: {$error_message} (Error code: {$error_code})";
            }

            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::error('API Error: ' . $this->last_error);
                Xf_Translator_Logger::error('Timeout used: ' . $timeout . ' seconds, Content length: ' . $content_length . ' characters');
                Xf_Translator_Logger::error('PHP max_execution_time: ' . $current_max_execution_time . ' seconds');
                Xf_Translator_Logger::error('Raw error: ' . $error_message . ' (Code: ' . $error_code . ', Response Code: ' . $response_code . ')');
            } else {
                error_log('XF Translator API Error: ' . $this->last_error);
                error_log('XF Translator: Timeout used: ' . $timeout . ' seconds, Content length: ' . $content_length . ' characters');
                error_log('XF Translator: PHP max_execution_time: ' . $current_max_execution_time . ' seconds');
                error_log('XF Translator: Raw error message: ' . $error_message . ' (Code: ' . $error_code . ', Response Code: ' . $response_code . ')');
            }
            return false;
        }

        $data = json_decode($response_body, true);
        
        // Check for Cloudflare timeout (524) or other HTTP errors
        if ($response_code === 524) {
            $this->last_error = "Cloudflare timeout (524): The request took longer than 100 seconds. Content length: " . number_format($content_length) . " characters. The content may be too large - consider breaking it into smaller chunks.";
            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::error('Cloudflare timeout (524) - Content too large: ' . number_format($content_length) . ' characters');
            } else {
                error_log('XF Translator API Error: ' . $this->last_error);
            }
            return false;
        }
        
        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API error';
            $this->last_error = "API returned error code {$response_code}: {$error_message}";
            if (class_exists('Xf_Translator_Logger')) {
                Xf_Translator_Logger::error('API Error: ' . $this->last_error);
                Xf_Translator_Logger::error('API Response: ' . substr($response_body, 0, 1000));
            } else {
                error_log('XF Translator API Error: ' . $this->last_error);
                error_log('XF Translator API Response: ' . $response_body);
            }
            return false;
        }

        // Extract translation
        if (isset($data['choices'][0]['message']['content'])) {
            $translation = trim($data['choices'][0]['message']['content']);

            // Check for API refusal messages
            $refusal_indicators = array(
                "I'm sorry, I can't assist",
                "I cannot assist",
                "I can't help",
                "I'm unable to",
                "I cannot provide",
                "I'm not able to",
                "I apologize, but I cannot",
                "I'm not programmed to"
            );

            $is_refusal = false;
            foreach ($refusal_indicators as $indicator) {
                if (stripos($translation, $indicator) !== false) {
                    $is_refusal = true;
                    break;
                }
            }

            // Also check for refusal field in response
            if (isset($data['choices'][0]['message']['refusal']) && !empty($data['choices'][0]['message']['refusal'])) {
                $is_refusal = true;
            }

            if ($is_refusal) {
                $this->last_error = "Translation failed: The content contains restricted text that cannot be translated by the API.";
                error_log('XF Translator API Error: ' . $this->last_error);
                error_log('XF Translator API Refusal Response: ' . $translation);
                return false;
            }

            // Save token usage for test translations (when post_id is set but queue_id is 0)
            if ($post_id && $queue_id == 0 && isset($data['usage'])) {
                update_post_meta($post_id, '_xf_translator_test_tokens_' . time(), $data['usage']['total_tokens']);
                // Also store in a way we can retrieve it
                $this->last_test_tokens = $data['usage']['total_tokens'];
            }

            return $translation;
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
    private function parse_translation_response($translation_response, $original_data)
    {
        $parsed = array();

        // Normalize line endings
        $translation_response = str_replace(array("\r\n", "\r"), "\n", $translation_response);
        $translation_response = trim($translation_response);

        // Log the raw response for debugging
        error_log('XF Translator: Parsing translation response, length: ' . strlen($translation_response));
        error_log('XF Translator: Response preview (first 500 chars): ' . substr($translation_response, 0, 500));

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
        // Use a more specific pattern that matches field labels at the start of lines
        // This ensures we don't split on colons inside HTML attributes
        $sections = preg_split('/(?=^[A-Za-z][A-Za-z0-9_\s]*:\s)/m', $translation_response, -1, PREG_SPLIT_NO_EMPTY);

        // If splitting didn't work well, try by double newlines
        if (count($sections) === 1) {
            $sections = preg_split('/\n\s*\n/', $translation_response, -1, PREG_SPLIT_NO_EMPTY);
        }

        // Log sections found
        error_log('XF Translator: Split into ' . count($sections) . ' sections');
        foreach ($sections as $idx => $section) {
            error_log('XF Translator: Section ' . $idx . ' preview (first 200 chars): ' . substr(trim($section), 0, 200));
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
                    // Only trim leading/trailing whitespace, preserve all content including HTML
                    $response_value = trim($response_value);

                    // Log for debugging HTML content
                    if ($matched_field === 'content' && strpos($response_value, '<table') !== false) {
                        error_log('XF Translator: Found table HTML in content field, length: ' . strlen($response_value));
                        error_log('XF Translator: Table HTML preview: ' . substr($response_value, strpos($response_value, '<table'), 200));
                    }

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
            // But be careful not to remove colons that are part of HTML attributes (e.g., style="color: white;")
            // Only remove if it's at the very start and looks like a label (word characters followed by colon)
            if (preg_match('/^[A-Za-z0-9_\s]+:\s*/u', $value, $label_match)) {
                // Check if this looks like HTML content (starts with <) - if so, don't remove
                $after_label = substr($value, strlen($label_match[0]));
                if (strpos(trim($after_label), '<') !== 0) {
                    // Not HTML, safe to remove label pattern
                    $value = preg_replace('/^[^:]+:\s*/u', '', $value, 1);
                }
            }

            $found_fields[$field_key] = trim($value);

            // Log if content field has table HTML to verify it's preserved
            if ($field_key === 'content' && strpos($value, '<table') !== false) {
                error_log('XF Translator: Final parsed content has table HTML, length: ' . strlen($value));
            }
        }

        // Use found fields as parsed result
        $parsed = $found_fields;

        // Log what fields were found
        error_log('XF Translator: Parsed fields: ' . implode(', ', array_keys($parsed)));
        foreach ($parsed as $field_key => $value) {
            $preview = substr($value, 0, 200);
            error_log('XF Translator: Field "' . $field_key . '" length: ' . strlen($value) . ', preview: ' . $preview);
            if ($field_key === 'content' && strpos($value, '<table') !== false) {
                error_log('XF Translator: Content field contains table HTML');
            }
        }

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
    private function match_label_to_field($response_label, $original_labels)
    {
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
            if (
                mb_strpos($response_label_lower, $original_label_lower, 0, 'UTF-8') !== false ||
                mb_strpos($original_label_lower, $response_label_lower, 0, 'UTF-8') !== false
            ) {
                return $field_key;
            }
        }

        // Third, try matching by field key name (for ACF fields)
        // If response label matches the field key name (without acf_ prefix)
        foreach ($original_labels as $field_key => $original_label) {
            $field_name = str_replace(array('acf_', 'meta_'), '', $field_key);
            $field_name_lower = mb_strtolower($field_name, 'UTF-8');

            if (
                $response_label_lower === $field_name_lower ||
                mb_strpos($response_label_lower, $field_name_lower, 0, 'UTF-8') !== false ||
                mb_strpos($field_name_lower, $response_label_lower, 0, 'UTF-8') !== false
            ) {
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
    private function create_translated_post($original_post_id, $target_language, $translated_data, $original_data)
    {
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

        // Store original post status - we'll create as draft first if publish to avoid featured image requirement
        $original_post_status = $original_post->post_status;

        // Prepare post data
        $post_data = array(
            'post_type' => $original_post->post_type,
            'post_status' => ($original_post_status === 'publish') ? 'draft' : $original_post_status,
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
            // Preserve HTML structure - WordPress will sanitize but we want to keep tables and other HTML
            // Use wp_kses_post to allow standard HTML including tables, but bypass if content is already sanitized
            $post_data['post_content'] = $translated_data['content'];

            // Log content length for debugging
            error_log('XF Translator: Setting post_content, length: ' . strlen($translated_data['content']) . ' chars');
            error_log('XF Translator: Content preview (first 500 chars): ' . substr($translated_data['content'], 0, 500));

            // Check for table HTML
            if (strpos($translated_data['content'], '<table') !== false) {
                error_log('XF Translator: Content contains <table> tag - HTML should be preserved');
                $table_pos = strpos($translated_data['content'], '<table');
                error_log('XF Translator: Table starts at position: ' . $table_pos);
                error_log('XF Translator: Table HTML preview: ' . substr($translated_data['content'], $table_pos, 300));
            } else {
                error_log('XF Translator: WARNING - Content does NOT contain <table> tag!');
            }

            // Check language - log first few words to verify
            $first_words = substr(strip_tags($translated_data['content']), 0, 100);
            error_log('XF Translator: Content first words (no HTML): ' . $first_words);
        } else {
            error_log('XF Translator: WARNING - No translated content found, using original post content');
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
            // For updates, keep original status (don't change to draft)
            $post_data['post_status'] = $original_post_status;
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

        // Verify table HTML was preserved after saving
        if (isset($translated_data['content']) && strpos($translated_data['content'], '<table') !== false) {
            $saved_post = get_post($translated_post_id);
            if ($saved_post) {
                $saved_content = $saved_post->post_content;
                if (strpos($saved_content, '<table') === false) {
                    error_log('XF Translator: WARNING - Table HTML was stripped during save! Original had table, saved content does not.');
                    error_log('XF Translator: Attempting to re-save with table HTML preserved...');

                    // Try to re-save with the original content that has tables
                    // Use wp_update_post with the raw content
                    $update_data = array(
                        'ID' => $translated_post_id,
                        'post_content' => $translated_data['content']
                    );
                    wp_update_post($update_data);

                    // Verify again
                    $saved_post_after = get_post($translated_post_id);
                    if ($saved_post_after && strpos($saved_post_after->post_content, '<table') !== false) {
                        error_log('XF Translator: Successfully re-saved with table HTML preserved.');
                    } else {
                        error_log('XF Translator: ERROR - Table HTML still missing after re-save. WordPress may be stripping table tags.');
                    }
                } else {
                    error_log('XF Translator: Table HTML successfully preserved in saved post.');
                }
            }
        }

        // Link translated post to original (use prefix for meta keys)
        update_post_meta($translated_post_id, '_xf_translator_original_post_id', $original_post_id);
        update_post_meta($translated_post_id, '_xf_translator_language', $language_prefix);
        update_post_meta($original_post_id, '_xf_translator_translated_post_' . $language_prefix, $translated_post_id);

        // Copy featured image from original post
        $thumbnail_id = get_post_thumbnail_id($original_post_id);
        if ($thumbnail_id) {
            $thumbnail_set = set_post_thumbnail($translated_post_id, $thumbnail_id);
            if (!$thumbnail_set) {
                error_log('XF Translator: Failed to set featured image for translated post ID: ' . $translated_post_id . ' from original post ID: ' . $original_post_id);
            }
        } else {
            error_log('XF Translator: Original post ID: ' . $original_post_id . ' does not have a featured image. Translated post may fail to publish if featured image is required.');
        }

        // If original post was published, update status back to publish (after featured image is set)
        if ($original_post_status === 'publish') {
            // Verify featured image was set before publishing (if original had one)
            if ($thumbnail_id) {
                $verify_thumbnail = get_post_thumbnail_id($translated_post_id);
                if (!$verify_thumbnail) {
                    error_log('XF Translator: Warning - Featured image not found on translated post before publishing. Post ID: ' . $translated_post_id);
                }
            }

            $update_result = wp_update_post(array(
                'ID' => $translated_post_id,
                'post_status' => 'publish'
            ), true);

            if (is_wp_error($update_result)) {
                error_log('XF Translator: Failed to update post status to publish. Error: ' . $update_result->get_error_message());
            }
        }

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
            } elseif (strpos($field_key, 'meta_') === 0) {
                // Regular post meta - save both to translated post and with language prefix for frontend filtering
                update_post_meta($translated_post_id, $actual_field_name, $translated_value);
                
                // Also store with language prefix on original post for frontend filter retrieval
                $translated_meta_key = '_xf_translator_meta_' . $actual_field_name . '_' . $language_prefix;
                update_post_meta($original_post_id, $translated_meta_key, $translated_value);
            }
        }

        // Translate user meta fields (author bio, etc.) if configured
        $author_id = $original_post->post_author;
        if ($author_id) {
            $this->translate_user_meta_fields($author_id, $target_language, $language_prefix);
        }

        // Copy taxonomies from original post, using translated terms if available
        $taxonomies = get_object_taxonomies($original_post->post_type);
        require_once plugin_dir_path(__FILE__) . 'class-taxonomy-translation-processor.php';
        $taxonomy_processor = new Xf_Translator_Taxonomy_Processor();

        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($original_post_id, $taxonomy, array('fields' => 'ids'));
            if (!is_wp_error($terms) && !empty($terms)) {
                // Get translated term IDs
                $translated_term_ids = $taxonomy_processor->get_translated_term_ids($terms, $language_prefix);

                // Use translated terms if available, otherwise use original terms
                $terms_to_assign = array();
                foreach ($terms as $term_id) {
                    if (isset($translated_term_ids[$term_id])) {
                        $terms_to_assign[] = $translated_term_ids[$term_id];
                    } else {
                        // If no translation exists, use original term
                        $terms_to_assign[] = $term_id;
                    }
                }

                if (!empty($terms_to_assign)) {
                    wp_set_post_terms($translated_post_id, $terms_to_assign, $taxonomy);
                }
            }
        }

        return $translated_post_id;
    }

    /**
     * Check if we're currently creating a translated post
     *
     * @return bool
     */
    public static function is_creating_translated_post()
    {
        return self::$creating_translated_post;
    }

    /**
     * Get the last error message
     *
     * @return string
     */
    public function get_last_error()
    {
        return $this->last_error;
    }

    /**
     * Process a specific queue entry by ID
     *
     * @param int $queue_entry_id Queue entry ID
     * @return array|false Processing result or false on failure
     */
    public function process_queue_entry_by_id($queue_entry_id)
    {
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

        // Only process if status is pending, failed (for retry), or processing (for stuck items)
        if ($queue_entry['status'] !== 'pending' && $queue_entry['status'] !== 'failed' && $queue_entry['status'] !== 'processing') {
            $this->last_error = "Queue entry #{$queue_entry_id} is not in a processable state (current status: {$queue_entry['status']})";
            return false;
        }
        
        // If status is processing, it might be stuck - reset to pending first
        if ($queue_entry['status'] === 'processing') {
            $wpdb->update(
                $table_name,
                array('status' => 'pending'),
                array('id' => $queue_entry_id),
                array('%s'),
                array('%d')
            );
            // Update the local variable
            $queue_entry['status'] = 'pending';
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

        $edited_fields = !empty($parsed_translation) ? array_keys($parsed_translation) : array();

        if ($translated_post_id && get_post($translated_post_id)) {
            // Update existing translated post with the fields we actually translated
            $updated_post_id = $this->update_translated_post($translated_post_id, $parsed_translation, $edited_fields);
        } else {
            // Create new translated post using the translated data (title/content/etc)
            $updated_post_id = $this->create_translated_post($post_id, $target_language_name, $parsed_translation, $post_data);
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
    private function process_edit_translation($queue_entry)
    {
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
    private function update_translated_post($translated_post_id, $translated_data, $edited_fields)
    {
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

    /**
     * Test translation without creating posts
     * Used for testing different models and prompts
     *
     * @param int $post_id Original post ID
     * @param string $target_language Target language name
     * @param string $model Model to use for testing
     * @param string $prompt_template 'current' to use current brand tone, 'custom' to use custom prompt
     * @param string $custom_prompt Custom prompt template (if prompt_template is 'custom')
     * @return array|WP_Error Translated content array or WP_Error
     */
    public function test_translation($post_id, $target_language, $model, $prompt_template = 'current', $custom_prompt = '')
    {
        // Get post data
        $post_data = $this->get_post_data($post_id);

        if (!$post_data) {
            return new WP_Error('no_post_data', __('Post data not found.', 'xf-translator'));
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

        // Build prompt
        if ($prompt_template === 'custom' && !empty($custom_prompt)) {
            // Use custom prompt
            $prompt = str_replace('{content}', $this->build_content_string($post_data), $custom_prompt);
            $prompt = str_replace('{lng}', $target_language, $prompt);

            // Get language description for {desc} placeholder
            $language_description = '';
            $languages = $this->settings->get('languages', array());
            foreach ($languages as $lang) {
                if (isset($lang['name']) && $lang['name'] === $target_language) {
                    $language_description = isset($lang['description']) ? $lang['description'] : '';
                    break;
                }
            }
            
            // Replace {desc} placeholder with language description
            if (!empty($language_description)) {
                $prompt = str_replace('{desc}', $language_description, $prompt);
            } else {
                $prompt = str_replace('{desc}', '', $prompt);
            }

            // Add glossary terms if configured
            $glossary_terms = $this->settings->get('glossary_terms', array());
            if (!empty($glossary_terms)) {
                $glossary_items = array();
                foreach ($glossary_terms as $term_data) {
                    $glossary_items[] = $term_data['term'];
                }
                $glossary_list = implode(', ', $glossary_items);
                // Replace {glossy} with just the glossary list
                // The instruction text should already be in the template around {glossy}
                $prompt = str_replace('{glossy}', $glossary_list, $prompt);
            } else {
                $prompt = str_replace('{glossy}', '', $prompt);
            }
        } else {
            // Use current brand tone template
            $prompt_data = $this->build_translation_prompt($post_data, $target_language);
            $prompt = $prompt_data['prompt'];
        }

        // Temporarily override model for this test
        $original_model = $this->settings->get('selected_model', 'gpt-4o');
        $this->settings->update('selected_model', $model);

        // Call API with test mode (no post creation, no queue)
        $translation_result = $this->call_translation_api($prompt, $target_language_prefix, 0, $post_id);

        // Restore original model
        $this->settings->update('selected_model', $original_model);

        if ($translation_result === false) {
            return new WP_Error('api_error', $this->last_error ?: __('Translation API call failed.', 'xf-translator'));
        }

        // Parse the translation response
        $parsed_translation = $this->parse_translation_response($translation_result, $post_data);

        if (!$parsed_translation) {
            return new WP_Error('parse_error', __('Failed to parse translation response.', 'xf-translator'));
        }

        // Get token usage from the API call
        $tokens_used = $this->last_test_tokens;

        // Create a test post with the translated content
        $test_post_id = $this->create_test_translated_post($post_id, $target_language, $parsed_translation, $post_data);

        if ($test_post_id === false) {
            // If post creation fails, still return the translation data
            return array(
                'title' => $parsed_translation['title'] ?? '',
                'content' => $parsed_translation['content'] ?? '',
                'excerpt' => $parsed_translation['excerpt'] ?? '',
                'tokens_used' => $tokens_used,
                'test_post_id' => false,
                'test_post_url' => ''
            );
        }

        // Get the post URL
        $test_post_url = get_permalink($test_post_id);

        return array(
            'title' => $parsed_translation['title'] ?? '',
            'content' => $parsed_translation['content'] ?? '',
            'excerpt' => $parsed_translation['excerpt'] ?? '',
            'tokens_used' => $tokens_used,
            'test_post_id' => $test_post_id,
            'test_post_url' => $test_post_url
        );
    }
    
    /**
     * Create a test post with translated content
     * This is similar to create_translated_post but creates a draft post marked as test
     *
     * @param int $original_post_id Original post ID
     * @param string $target_language Target language name
     * @param array $translated_data Parsed translated data
     * @param array $original_data Original post data
     * @return int|false Test post ID or false on failure
     */
    private function create_test_translated_post($original_post_id, $target_language, $translated_data, $original_data)
    {
        $original_post = get_post($original_post_id);

        if (!$original_post) {
            return false;
        }

        // Get language prefix for meta key
        $languages = $this->settings->get('languages', array());
        $language_prefix = '';
        foreach ($languages as $lang) {
            if ($lang['name'] === $target_language) {
                $language_prefix = $lang['prefix'];
                break;
            }
        }

        // Prepare post data - always create as draft for test posts
        $post_data = array(
            'post_type' => $original_post->post_type,
            'post_status' => 'draft', // Always draft for test posts
            'post_author' => $original_post->post_author,
            'post_parent' => $original_post->post_parent,
            'menu_order' => $original_post->menu_order,
            'comment_status' => $original_post->comment_status,
            'ping_status' => $original_post->ping_status,
        );

        // Set translated title with test indicator
        if (isset($translated_data['title']) && !empty($translated_data['title'])) {
            $post_data['post_title'] = $translated_data['title'] . ' [TEST]';
        } else {
            $post_data['post_title'] = $original_post->post_title . ' (' . $target_language . ') [TEST]';
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

        // Generate unique slug for test post
        $original_slug = $original_post->post_name;
        if (empty($original_slug)) {
            $original_slug = sanitize_title($original_post->post_title);
        }
        
        // Add test suffix to slug to make it unique
        $test_slug = $original_slug . '-test-' . time();
        $post_data['post_name'] = $test_slug;

        // Copy categories and tags
        $categories = wp_get_post_categories($original_post_id, array('fields' => 'ids'));
        if (!empty($categories)) {
            $post_data['post_category'] = $categories;
        }

        $tags = wp_get_post_tags($original_post_id, array('fields' => 'names'));
        if (!empty($tags)) {
            $post_data['tags_input'] = $tags;
        }

        // Set flag to prevent post listener from processing this test post
        self::$creating_translated_post = true;

        // Create test post
        $test_post_id = wp_insert_post($post_data, true);

        // Clear flag
        self::$creating_translated_post = false;

        if (is_wp_error($test_post_id)) {
            return false;
        }

        // Mark this as a test post and link to original
        update_post_meta($test_post_id, '_xf_translator_is_test_post', true);
        update_post_meta($test_post_id, '_xf_translator_original_post_id', $original_post_id);
        update_post_meta($test_post_id, '_xf_translator_language', $language_prefix);
        update_post_meta($test_post_id, '_xf_translator_test_timestamp', time());

        // Also store on original post for reference
        update_post_meta($original_post_id, '_xf_translator_test_post_' . $language_prefix, $test_post_id);

        return $test_post_id;
    }

    /**
     * Build content string from post data for custom prompts
     *
     * @param array $post_data Post data array
     * @return string Content string
     */
    private function build_content_string($post_data)
    {
        $content_parts = array();

        foreach ($post_data as $field => $value) {
            if (!empty($value)) {
                $field_label = ucfirst(str_replace(array('acf_', 'meta_'), '', $field));
                $content_parts[] = "{$field_label}: {$value}";
            }
        }

        return implode("\n\n", $content_parts);
    }

    /**
     * Get max_tokens limit for a specific model
     *
     * @param string $model Model name
     * @return int Max tokens allowed
     */
    private function get_max_tokens_for_model($model)
    {
        // Model-specific max_tokens limits
        $model_limits = array(
            // GPT-4 models
            'gpt-4o' => 16384,           // GPT-4o supports up to 16k output tokens
            'gpt-4o-mini' => 16384,      // GPT-4o Mini supports up to 16k output tokens
            'gpt-4-turbo' => 4096,       // GPT-4 Turbo supports up to 4k output tokens
            'gpt-4' => 4096,             // GPT-4 supports up to 4k output tokens
            'gpt-4-32k' => 32768,        // GPT-4 32k supports up to 32k output tokens

            // GPT-3.5 models
            'gpt-3.5-turbo' => 4096,     // GPT-3.5 Turbo supports up to 4k output tokens
            'gpt-3.5-turbo-16k' => 16384, // GPT-3.5 Turbo 16k supports up to 16k output tokens

            // DeepSeek models
            'deepseek-chat' => 8192,     // DeepSeek Chat supports up to 8k output tokens
            'deepseek-coder' => 8192,    // DeepSeek Coder supports up to 8k output tokens
            'deepseek-chat-32k' => 32768, // DeepSeek Chat 32k supports up to 32k output tokens
        );

        // Return model-specific limit or default to 4000 for safety
        if (isset($model_limits[$model])) {
            return $model_limits[$model];
        }

        // Default to 4000 for unknown models (safe default)
        return 4000;
    }
    
    /**
     * Translate user meta fields for a specific user
     *
     * @param int $user_id User ID
     * @param string $target_language Target language name
     * @param string $language_prefix Language prefix
     * @return bool Success status
     */
    private function translate_user_meta_fields($user_id, $target_language, $language_prefix)
    {
        // Get user data with selected meta fields
        $user_data = $this->get_user_data($user_id);
        
        if (empty($user_data)) {
            return true; // No user meta fields to translate
        }
        
        // Build translation prompt for user meta
        $content_parts = array();
        foreach ($user_data as $field => $value) {
            if (!empty($value)) {
                $field_label = ucfirst(str_replace('user_meta_', '', $field));
                $content_parts[] = "{$field_label}: {$value}";
            }
        }
        
        if (empty($content_parts)) {
            return true; // No content to translate
        }
        
        $content_string = implode("\n\n", $content_parts);
        
        // Build simple prompt for user meta translation
        $brand_tone_template = $this->settings->get('brand_tone', '');
        if (empty($brand_tone_template)) {
            $prompt = "Translate the following user information to {$target_language}:\n\n{$content_string}\n\nProvide the translation in the same format with field labels.";
        } else {
            $prompt = str_replace('{content}', $content_string, $brand_tone_template);
            $prompt = str_replace('{lng}', $target_language, $prompt);
            
            // Get language description for {desc} placeholder
            $languages = $this->settings->get('languages', array());
            $language_description = '';
            foreach ($languages as $lang) {
                if (isset($lang['name']) && $lang['name'] === $target_language) {
                    $language_description = isset($lang['description']) ? $lang['description'] : '';
                    break;
                }
            }
            
            if (!empty($language_description)) {
                $prompt = str_replace('{desc}', $language_description, $prompt);
            } else {
                $prompt = str_replace('{desc}', '', $prompt);
            }
        }
        
        // Call translation API
        $translation_result = $this->call_translation_api($prompt, $language_prefix, 0, 0);
        
        if ($translation_result === false) {
            error_log('XF Translator: Failed to translate user meta fields for user ID: ' . $user_id);
            return false;
        }
        
        // Parse translation response
        $parsed_translation = $this->parse_translation_response($translation_result, $user_data);
        
        if (!$parsed_translation) {
            error_log('XF Translator: Failed to parse user meta translation response for user ID: ' . $user_id);
            return false;
        }
        
        // Save translated user meta fields
        foreach ($parsed_translation as $field_key => $translated_value) {
            // Remove prefix to get actual meta key
            $actual_meta_key = str_replace('user_meta_', '', $field_key);
            
            // Store translated value with language prefix
            $translated_meta_key = '_xf_translator_user_meta_' . $actual_meta_key . '_' . $language_prefix;
            update_user_meta($user_id, $translated_meta_key, $translated_value);
        }
        
        return true;
    }
}


