<?php
/**
 * General Settings Tab - Combined (API Key, Languages, Brand Tones)
 *
 * @package API_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- API Keys and Model Selection Section -->
<div class="api-translator-section">
    <h2><?php _e('API Configuration', 'api-translator'); ?></h2>
    
    <form method="post" action="" class="api-translator-inline-form">
        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
        <input type="hidden" name="api_translator_action" value="save_general">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_key"><?php _e('OpenAI API Key', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="api_key" 
                           name="api_key" 
                           value="<?php echo esc_attr($api_key); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('sk-...', 'api-translator'); ?>">
                    <p class="description">
                        <?php _e('Enter your OpenAI API key. This will be used for translation requests when OpenAI models are selected.', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="deepseek_api_key"><?php _e('DeepSeek API Key', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="deepseek_api_key" 
                           name="deepseek_api_key" 
                           value="<?php echo esc_attr($deepseek_api_key); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('sk-...', 'api-translator'); ?>">
                    <p class="description">
                        <?php _e('Enter your DeepSeek API key. This will be used for translation requests when DeepSeek models are selected.', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="selected_model"><?php _e('Translation Model', 'api-translator'); ?></label>
                </th>
                <td>
                    <select id="selected_model" name="selected_model" class="regular-text">
                        <optgroup label="<?php esc_attr_e('OpenAI Models', 'api-translator'); ?>">
                            <option value="gpt-4o" <?php selected($selected_model, 'gpt-4o'); ?>>GPT-4o</option>
                            <option value="gpt-4o-mini" <?php selected($selected_model, 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                            <option value="gpt-4-turbo" <?php selected($selected_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                            <option value="gpt-4" <?php selected($selected_model, 'gpt-4'); ?>>GPT-4</option>
                            <option value="gpt-3.5-turbo" <?php selected($selected_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            <option value="gpt-3.5-turbo-16k" <?php selected($selected_model, 'gpt-3.5-turbo-16k'); ?>>GPT-3.5 Turbo 16k</option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('DeepSeek Models', 'api-translator'); ?>">
                            <option value="deepseek-chat" <?php selected($selected_model, 'deepseek-chat'); ?>>DeepSeek Chat</option>
                            <option value="deepseek-coder" <?php selected($selected_model, 'deepseek-coder'); ?>>DeepSeek Coder</option>
                            <option value="deepseek-chat-32k" <?php selected($selected_model, 'deepseek-chat-32k'); ?>>DeepSeek Chat 32k</option>
                        </optgroup>
                    </select>
                    <p class="description">
                        <?php _e('Select the AI model to use for translations. Make sure you have the corresponding API key configured.', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="processing_delay_minutes"><?php _e('Processing Delay (Minutes)', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="processing_delay_minutes" 
                           name="processing_delay_minutes" 
                           value="<?php echo esc_attr($processing_delay_minutes); ?>" 
                           class="small-text"
                           min="0"
                           step="1"
                           placeholder="0">
                    <p class="description">
                        <?php _e('Delay before processing new translation queue entries. When a new post is created, queue entries will only be processed if they are at least this many minutes old. Set to 0 to process immediately. This helps prevent processing posts that are still being edited.', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save API Settings', 'api-translator'), 'primary', 'submit', false); ?>
    </form>
</div>

<!-- Languages Management Section -->
<div class="api-translator-section">
    <h2><?php _e('Language Management', 'api-translator'); ?></h2>
    
    <h3><?php _e('Add New Language', 'api-translator'); ?></h3>
    
    <form method="post" action="" class="api-translator-inline-form">
        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
        <input type="hidden" name="api_translator_action" value="add_language">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="language_name"><?php _e('Language Name', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="language_name" 
                           name="language_name" 
                           class="regular-text" 
                           required
                           placeholder="<?php esc_attr_e('e.g., English, Spanish', 'api-translator'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="language_prefix"><?php _e('Language Prefix', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="language_prefix" 
                           name="language_prefix" 
                           class="regular-text" 
                           required
                           maxlength="10"
                           placeholder="<?php esc_attr_e('e.g., en, es, fr', 'api-translator'); ?>">
                    <p class="description">
                        <?php _e('ISO language code (e.g., en, es, fr, de). Must be unique.', 'api-translator'); ?>
                    </p>
                    <span id="prefix-error" class="error-message" style="color: #dc3232; display: none;"></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="language_path"><?php _e('Language Path', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="language_path" 
                           name="language_path" 
                           class="regular-text" 
                           maxlength="20"
                           placeholder="<?php esc_attr_e('e.g., fr, Ar', 'api-translator'); ?>">
                    <p class="description">
                        <?php _e('URL path used in post slugs (e.g., fr, Ar). If empty, prefix will be used. Must be unique.', 'api-translator'); ?>
                    </p>
                    <span id="path-error" class="error-message" style="color: #dc3232; display: none;"></span>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="language_description"><?php _e('Language Description', 'api-translator'); ?></label>
                </th>
                <td>
                    <textarea id="language_description" 
                              name="language_description" 
                              class="large-text" 
                              rows="3"
                              placeholder="<?php esc_attr_e('Optional description for this language', 'api-translator'); ?>"></textarea>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Add Language', 'api-translator'), 'primary', 'submit', false); ?>
    </form>
    
    <h3><?php _e('Manage Languages', 'api-translator'); ?></h3>
    
    <?php if (empty($languages)) : ?>
        <p><?php _e('No languages added yet.', 'api-translator'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Language Name', 'api-translator'); ?></th>
                    <th scope="col"><?php _e('Prefix', 'api-translator'); ?></th>
                    <th scope="col"><?php _e('Path', 'api-translator'); ?></th>
                    <th scope="col"><?php _e('Description', 'api-translator'); ?></th>
                    <th scope="col" class="actions-col"><?php _e('Actions', 'api-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($languages as $index => $language) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($language['name']); ?></strong>
                        </td>
                        <td>
                            <code><?php echo esc_html($language['prefix']); ?></code>
                        </td>
                        <td>
                            <code><?php echo esc_html(isset($language['path']) && !empty($language['path']) ? $language['path'] : $language['prefix']); ?></code>
                        </td>
                        <td>
                            <?php echo esc_html(isset($language['description']) ? $language['description'] : ''); ?>
                        </td>
                        <td>
                            <button type="button" 
                                    class="button button-small edit-language" 
                                    data-index="<?php echo esc_attr($index); ?>"
                                    data-name="<?php echo esc_attr($language['name']); ?>"
                                    data-prefix="<?php echo esc_attr($language['prefix']); ?>"
                                    data-path="<?php echo esc_attr(isset($language['path']) ? $language['path'] : ''); ?>"
                                    data-description="<?php echo esc_attr(isset($language['description']) ? $language['description'] : ''); ?>">
                                <?php _e('Edit', 'api-translator'); ?>
                            </button>
                            
                            <form method="post" action="" style="display: inline;">
                                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                <input type="hidden" name="api_translator_action" value="delete_language">
                                <input type="hidden" name="language_index" value="<?php echo esc_attr($index); ?>">
                                <button type="submit" 
                                        class="button button-small button-link-delete" 
                                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this language?', 'api-translator'); ?>');">
                                    <?php _e('Delete', 'api-translator'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Brand Tone Section -->
<div class="api-translator-section">
    <h2><?php _e('Brand Tone Management', 'api-translator'); ?></h2>
    
    <p class="description">
        <?php _e('Configure the brand tone prompt. This prompt will be used to guide the translation style and voice for all translations.', 'api-translator'); ?>
    </p>
    
    <form method="post" action="">
        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
        <input type="hidden" name="api_translator_action" value="save_brand_tones">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brand_tone"><?php _e('Brand Tone Prompt', 'api-translator'); ?></label>
                </th>
                <td>
                    <textarea id="brand_tone" 
                              name="brand_tone" 
                              rows="8" 
                              class="large-text"
                              placeholder="<?php esc_attr_e('e.g., Professional, friendly, and approachable tone suitable for a business audience. Use clear and concise language while maintaining a warm and engaging voice...', 'api-translator'); ?>"><?php echo esc_textarea($brand_tone); ?></textarea>
                    <p class="description">
                        <?php _e('Describe the desired tone, style, and voice for translations. This will be applied to all translation requests.', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Brand Tone', 'api-translator')); ?>
    </form>
</div>

<!-- Meta Fields Translation Section -->
<?php
$translatable_post_meta = $settings->get_translatable_post_meta_fields();
$translatable_user_meta = $settings->get_translatable_user_meta_fields();
?>
<div class="api-translator-section">
    <h2><?php _e('Meta Fields Translation Settings', 'xf-translator'); ?></h2>
    <p><?php _e('Select which post meta fields and user meta fields should be translated. These fields will be included when translating posts and will be automatically displayed in the correct language on the frontend.', 'xf-translator'); ?></p>
    
    <form method="post" action="" id="meta-fields-form">
        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
        <input type="hidden" name="api_translator_action" value="save_meta_fields">
        
        <!-- Post Meta Fields Section -->
        <div class="api-translator-section" style="margin-top: 30px;">
            <h3><?php _e('Post Meta Fields', 'xf-translator'); ?></h3>
            <p class="description"><?php _e('Select which post meta fields should be translated. These are custom fields attached to posts and pages.', 'xf-translator'); ?></p>
            
            <div style="margin: 20px 0;">
                <button type="button" id="scan-post-meta-btn" class="button">
                    <?php _e('Scan Post Meta Fields', 'xf-translator'); ?>
                </button>
                <span id="scan-post-meta-loading" style="display: none; margin-left: 10px;">
                    <span class="spinner is-active" style="float: none; margin: 0;"></span>
                    <?php _e('Scanning...', 'xf-translator'); ?>
                </span>
            </div>
            
            <div id="post-meta-fields-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
                <p id="post-meta-fields-empty" style="color: #666;">
                    <?php _e('Click "Scan Post Meta Fields" to discover available meta fields, or manually add fields below.', 'xf-translator'); ?>
                </p>
                <div id="post-meta-fields-list"></div>
            </div>
            
            <div style="margin-top: 15px;">
                <label for="manual-post-meta-field">
                    <strong><?php _e('Add Custom Post Meta Field:', 'xf-translator'); ?></strong>
                </label>
                <div style="display: flex; gap: 10px; margin-top: 5px;">
                    <input type="text" 
                           id="manual-post-meta-field" 
                           placeholder="<?php esc_attr_e('e.g., _custom_field_name', 'xf-translator'); ?>" 
                           class="regular-text">
                    <button type="button" id="add-post-meta-field-btn" class="button">
                        <?php _e('Add Field', 'xf-translator'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- User Meta Fields Section -->
        <div class="api-translator-section" style="margin-top: 30px;">
            <h3><?php _e('User Meta Fields', 'xf-translator'); ?></h3>
            <p class="description"><?php _e('Select which user meta fields should be translated. This includes author biographical info and other user profile fields.', 'xf-translator'); ?></p>
            
            <div style="margin: 20px 0;">
                <button type="button" id="scan-user-meta-btn" class="button">
                    <?php _e('Scan User Meta Fields', 'xf-translator'); ?>
                </button>
                <span id="scan-user-meta-loading" style="display: none; margin-left: 10px;">
                    <span class="spinner is-active" style="float: none; margin: 0;"></span>
                    <?php _e('Scanning...', 'xf-translator'); ?>
                </span>
            </div>
            
            <div id="user-meta-fields-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
                <p id="user-meta-fields-empty" style="color: #666;">
                    <?php _e('Click "Scan User Meta Fields" to discover available meta fields, or manually add fields below.', 'xf-translator'); ?>
                </p>
                <div id="user-meta-fields-list"></div>
            </div>
            
            <div style="margin-top: 15px;">
                <label for="manual-user-meta-field">
                    <strong><?php _e('Add Custom User Meta Field:', 'xf-translator'); ?></strong>
                </label>
                <div style="display: flex; gap: 10px; margin-top: 5px;">
                    <input type="text" 
                           id="manual-user-meta-field" 
                           placeholder="<?php esc_attr_e('e.g., description, occupation', 'xf-translator'); ?>" 
                           class="regular-text">
                    <button type="button" id="add-user-meta-field-btn" class="button">
                        <?php _e('Add Field', 'xf-translator'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Hidden inputs to store selected fields -->
        <input type="hidden" name="selected_post_meta_fields" id="selected-post-meta-fields" value="<?php echo esc_attr(implode(',', $translatable_post_meta)); ?>">
        <input type="hidden" name="selected_user_meta_fields" id="selected-user-meta-fields" value="<?php echo esc_attr(implode(',', $translatable_user_meta)); ?>">
        
        <p class="submit" style="margin-top: 30px;">
            <?php submit_button(__('Save Meta Fields Settings', 'xf-translator'), 'primary', 'submit', false); ?>
        </p>
    </form>
</div>

<!-- Edit Language Modal -->
<div id="edit-language-modal" class="api-translator-modal" style="display: none;">
    <div class="api-translator-modal-content">
        <span class="api-translator-modal-close">&times;</span>
        <h2><?php _e('Edit Language', 'api-translator'); ?></h2>
        
        <form method="post" action="" id="edit-language-form">
            <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
            <input type="hidden" name="api_translator_action" value="edit_language">
            <input type="hidden" name="language_index" id="edit_language_index">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="edit_language_name"><?php _e('Language Name', 'api-translator'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="edit_language_name" 
                               name="language_name" 
                               class="regular-text" 
                               required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="edit_language_prefix"><?php _e('Language Prefix', 'api-translator'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="edit_language_prefix" 
                               name="language_prefix" 
                               class="regular-text" 
                               required
                               maxlength="10">
                        <p class="description">
                            <?php _e('ISO language code. Must be unique.', 'api-translator'); ?>
                        </p>
                        <span id="edit-prefix-error" class="error-message" style="color: #dc3232; display: none;"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="edit_language_path"><?php _e('Language Path', 'api-translator'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="edit_language_path" 
                               name="language_path" 
                               class="regular-text" 
                               maxlength="20"
                               placeholder="<?php esc_attr_e('e.g., fr, Ar', 'api-translator'); ?>">
                        <p class="description">
                            <?php _e('URL path used in post slugs. If empty, prefix will be used. Must be unique.', 'api-translator'); ?>
                        </p>
                        <span id="edit-path-error" class="error-message" style="color: #dc3232; display: none;"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="edit_language_description"><?php _e('Language Description', 'api-translator'); ?></label>
                    </th>
                    <td>
                        <textarea id="edit_language_description" 
                                  name="language_description" 
                                  class="large-text" 
                                  rows="3"
                                  placeholder="<?php esc_attr_e('Optional description for this language', 'api-translator'); ?>"></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <?php submit_button(__('Update Language', 'api-translator'), 'primary', 'submit', false); ?>
                <button type="button" class="button cancel-edit"><?php _e('Cancel', 'api-translator'); ?></button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var selectedPostMetaFields = <?php echo json_encode($translatable_post_meta); ?>;
    var selectedUserMetaFields = <?php echo json_encode($translatable_user_meta); ?>;
    
    // Initialize with saved fields
    updatePostMetaFieldsDisplay();
    updateUserMetaFieldsDisplay();
    
    // Scan Post Meta Fields
    $('#scan-post-meta-btn').on('click', function() {
        $('#scan-post-meta-loading').show();
        $('#scan-post-meta-btn').prop('disabled', true);
        
        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_scan_post_meta_fields',
                nonce: apiTranslator.nonce
            },
            success: function(response) {
                if (response.success && response.data.fields) {
                    displayPostMetaFields(response.data.fields);
                } else {
                    alert('Error scanning post meta fields: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred while scanning post meta fields');
            },
            complete: function() {
                $('#scan-post-meta-loading').hide();
                $('#scan-post-meta-btn').prop('disabled', false);
            }
        });
    });
    
    // Scan User Meta Fields
    $('#scan-user-meta-btn').on('click', function() {
        $('#scan-user-meta-loading').show();
        $('#scan-user-meta-btn').prop('disabled', true);
        
        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_scan_user_meta_fields',
                nonce: apiTranslator.nonce
            },
            success: function(response) {
                if (response.success && response.data.fields) {
                    displayUserMetaFields(response.data.fields);
                } else {
                    alert('Error scanning user meta fields: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred while scanning user meta fields');
            },
            complete: function() {
                $('#scan-user-meta-loading').hide();
                $('#scan-user-meta-btn').prop('disabled', false);
            }
        });
    });
    
    // Add manual post meta field
    $('#add-post-meta-field-btn').on('click', function() {
        var field = $('#manual-post-meta-field').val().trim();
        if (field) {
            if (selectedPostMetaFields.indexOf(field) === -1) {
                selectedPostMetaFields.push(field);
                updatePostMetaFieldsDisplay();
                $('#manual-post-meta-field').val('');
            }
        }
    });
    
    // Add manual user meta field
    $('#add-user-meta-field-btn').on('click', function() {
        var field = $('#manual-user-meta-field').val().trim();
        if (field) {
            if (selectedUserMetaFields.indexOf(field) === -1) {
                selectedUserMetaFields.push(field);
                updateUserMetaFieldsDisplay();
                $('#manual-user-meta-field').val('');
            }
        }
    });
    
    // Display post meta fields
    function displayPostMetaFields(fields) {
        var html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px;">';
        fields.forEach(function(field) {
            var checked = selectedPostMetaFields.indexOf(field.key) !== -1 ? 'checked' : '';
            var sample = field.sample ? ' <span style="color: #666; font-size: 11px;">(' + escapeHtml(field.sample.substring(0, 50)) + '...)</span>' : '';
            html += '<label style="display: block; padding: 5px;">';
            html += '<input type="checkbox" class="post-meta-checkbox" value="' + escapeHtml(field.key) + '" ' + checked + '> ';
            html += '<code>' + escapeHtml(field.key) + '</code>' + sample;
            html += '</label>';
        });
        html += '</div>';
        $('#post-meta-fields-list').html(html);
        $('#post-meta-fields-empty').hide();
        
        // Bind checkbox change events
        $('.post-meta-checkbox').on('change', function() {
            var field = $(this).val();
            if ($(this).is(':checked')) {
                if (selectedPostMetaFields.indexOf(field) === -1) {
                    selectedPostMetaFields.push(field);
                }
            } else {
                var index = selectedPostMetaFields.indexOf(field);
                if (index !== -1) {
                    selectedPostMetaFields.splice(index, 1);
                }
            }
            updatePostMetaFieldsDisplay();
        });
    }
    
    // Display user meta fields
    function displayUserMetaFields(fields) {
        var html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px;">';
        fields.forEach(function(field) {
            var checked = selectedUserMetaFields.indexOf(field.key) !== -1 ? 'checked' : '';
            var sample = field.sample ? ' <span style="color: #666; font-size: 11px;">(' + escapeHtml(field.sample.substring(0, 50)) + '...)</span>' : '';
            html += '<label style="display: block; padding: 5px;">';
            html += '<input type="checkbox" class="user-meta-checkbox" value="' + escapeHtml(field.key) + '" ' + checked + '> ';
            html += '<code>' + escapeHtml(field.key) + '</code>' + sample;
            html += '</label>';
        });
        html += '</div>';
        $('#user-meta-fields-list').html(html);
        $('#user-meta-fields-empty').hide();
        
        // Bind checkbox change events
        $('.user-meta-checkbox').on('change', function() {
            var field = $(this).val();
            if ($(this).is(':checked')) {
                if (selectedUserMetaFields.indexOf(field) === -1) {
                    selectedUserMetaFields.push(field);
                }
            } else {
                var index = selectedUserMetaFields.indexOf(field);
                if (index !== -1) {
                    selectedUserMetaFields.splice(index, 1);
                }
            }
            updateUserMetaFieldsDisplay();
        });
    }
    
    // Update post meta fields display
    function updatePostMetaFieldsDisplay() {
        $('#selected-post-meta-fields').val(selectedPostMetaFields.join(','));
        $('.post-meta-checkbox').each(function() {
            var field = $(this).val();
            $(this).prop('checked', selectedPostMetaFields.indexOf(field) !== -1);
        });
    }
    
    // Update user meta fields display
    function updateUserMetaFieldsDisplay() {
        $('#selected-user-meta-fields').val(selectedUserMetaFields.join(','));
        $('.user-meta-checkbox').each(function() {
            var field = $(this).val();
            $(this).prop('checked', selectedUserMetaFields.indexOf(field) !== -1);
        });
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
