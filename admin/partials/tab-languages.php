<?php
/**
 * Languages Management Tab
 *
 * @package API_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="api-translator-section">
    <h2><?php _e('Add New Language', 'api-translator'); ?></h2>
    
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
                        <?php _e('ISO language code (e.g., en, es, fr, de)', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Add Language', 'api-translator'), 'primary', 'submit', false); ?>
    </form>
</div>

<div class="api-translator-section">
    <h2><?php _e('Manage Languages', 'api-translator'); ?></h2>
    
    <?php if (empty($languages)) : ?>
        <p><?php _e('No languages added yet.', 'api-translator'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Language Name', 'api-translator'); ?></th>
                    <th scope="col"><?php _e('Prefix', 'api-translator'); ?></th>
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
                            <button type="button" 
                                    class="button button-small edit-language" 
                                    data-index="<?php echo esc_attr($index); ?>"
                                    data-name="<?php echo esc_attr($language['name']); ?>"
                                    data-prefix="<?php echo esc_attr($language['prefix']); ?>">
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

