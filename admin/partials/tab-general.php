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
            <tr>
                <th scope="row">
                    <?php _e('Background Processing', 'api-translator'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="enable_new_translations_cron" 
                                   value="1" 
                                   <?php checked($enable_new_cron, true); ?>>
                            <?php _e('Enable automatic processing of NEW translations (runs every 1 minute)', 'api-translator'); ?>
                        </label>
                        <p class="description" style="margin-left: 25px; margin-top: 5px;">
                            <?php _e('When enabled, the system will automatically process NEW translation queue entries every minute via wp-cron.', 'api-translator'); ?>
                        </p>
                    </fieldset>
                    <fieldset style="margin-top: 15px;">
                        <label>
                            <input type="checkbox" 
                                   name="enable_old_translations_cron" 
                                   value="1" 
                                   <?php checked($enable_old_cron, true); ?>>
                            <?php _e('Enable automatic processing of OLD translations (runs every 1 minute)', 'api-translator'); ?>
                        </label>
                        <p class="description" style="margin-left: 25px; margin-top: 5px;">
                            <?php _e('When enabled, the system will automatically process OLD translation queue entries every minute via wp-cron.', 'api-translator'); ?>
                        </p>
                    </fieldset>
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

<!-- Slug Cleanup Section -->
<!-- <div class="api-translator-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
    <h2><?php _e('Fix Old Translated Post Slugs', 'xf-translator'); ?></h2>
    <p>
        <?php _e('This tool will fix translated posts that have "-2", "-3", etc. appended to their slugs. It will update them to match the original English post slugs.', 'xf-translator'); ?>
    </p>
    <p class="description" style="color: #d63638; font-weight: 600;">
        <?php _e('⚠️ Important: This will modify post URLs. Make sure you have a backup before proceeding.', 'xf-translator'); ?>
    </p>
    
    <form method="post" action="" style="margin-top: 15px;">
        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
        <input type="hidden" name="api_translator_action" value="fix_old_slugs">
        <button type="submit" 
                class="button button-primary" 
                onclick="return confirm('<?php esc_attr_e('Are you sure you want to fix all old translated post slugs? This will modify post URLs. Make sure you have a backup.', 'xf-translator'); ?>');">
            <?php _e('Fix Old Translated Post Slugs', 'xf-translator'); ?>
        </button>
    </form>
    
    <?php
    // Show admin notices if any
    settings_errors('xf_translator_messages');
    ?>
</div> -->
