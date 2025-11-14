<?php
/**
 * Translation Rules Tab
 *
 * @package API_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="api-translator-section">
    <h2><?php _e('Exclude Paths', 'api-translator'); ?></h2>
    <p class="description">
        <?php _e('Add URL paths that should not be translated. These paths will be excluded from translation processing.', 'api-translator'); ?>
    </p>
    
    <form method="post" action="" class="api-translator-inline-form">
        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
        <input type="hidden" name="api_translator_action" value="add_exclude_path">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="exclude_path"><?php _e('Path to Exclude', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="exclude_path" 
                           name="exclude_path" 
                           class="regular-text" 
                           required
                           placeholder="<?php esc_attr_e('e.g., /admin/, /wp-admin/, /checkout/', 'api-translator'); ?>">
                    <p class="description">
                        <?php _e('Enter a URL path (e.g., /admin/ or /checkout/)', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Add Exclude Path', 'api-translator'), 'primary', 'submit', false); ?>
    </form>
    
    <?php if (!empty($exclude_paths)) : ?>
        <h3><?php _e('Current Exclude Paths', 'api-translator'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Path', 'api-translator'); ?></th>
                    <th scope="col" class="actions-col"><?php _e('Actions', 'api-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exclude_paths as $index => $path) : ?>
                    <tr>
                        <td>
                            <code><?php echo esc_html($path); ?></code>
                        </td>
                        <td>
                            <form method="post" action="" style="display: inline;">
                                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                <input type="hidden" name="api_translator_action" value="delete_exclude_path">
                                <input type="hidden" name="path_index" value="<?php echo esc_attr($index); ?>">
                                <button type="submit" 
                                        class="button button-small button-link-delete" 
                                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove this exclude path?', 'api-translator'); ?>');">
                                    <?php _e('Remove', 'api-translator'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php _e('No exclude paths configured.', 'api-translator'); ?></p>
    <?php endif; ?>
</div>

<div class="api-translator-section">
    <h2><?php _e('Glossary / Do-Not-Translate Terms', 'api-translator'); ?></h2>
    <p class="description">
        <?php _e('Add terms that should not be translated. These terms will be preserved in their original form during translation.', 'api-translator'); ?>
    </p>
    
    <form method="post" action="" class="api-translator-inline-form">
        <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
        <input type="hidden" name="api_translator_action" value="add_glossary_term">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="glossary_term"><?php _e('Term', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="glossary_term" 
                           name="glossary_term" 
                           class="regular-text" 
                           required
                           placeholder="<?php esc_attr_e('e.g., WordPress, API, JavaScript', 'api-translator'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="glossary_context"><?php _e('Context (Optional)', 'api-translator'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="glossary_context" 
                           name="glossary_context" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('e.g., Technical term, Brand name', 'api-translator'); ?>">
                    <p class="description">
                        <?php _e('Optional context to help identify when to use this term.', 'api-translator'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Add Glossary Term', 'api-translator'), 'primary', 'submit', false); ?>
    </form>
    
    <?php if (!empty($glossary_terms)) : ?>
        <h3><?php _e('Current Glossary Terms', 'api-translator'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Term', 'api-translator'); ?></th>
                    <th scope="col"><?php _e('Context', 'api-translator'); ?></th>
                    <th scope="col" class="actions-col"><?php _e('Actions', 'api-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($glossary_terms as $index => $term_data) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($term_data['term']); ?></strong>
                        </td>
                        <td>
                            <?php echo esc_html($term_data['context'] ? $term_data['context'] : '—'); ?>
                        </td>
                        <td>
                            <form method="post" action="" style="display: inline;">
                                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                <input type="hidden" name="api_translator_action" value="delete_glossary_term">
                                <input type="hidden" name="term_index" value="<?php echo esc_attr($index); ?>">
                                <button type="submit" 
                                        class="button button-small button-link-delete" 
                                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove this glossary term?', 'api-translator'); ?>');">
                                    <?php _e('Remove', 'api-translator'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php _e('No glossary terms added yet.', 'api-translator'); ?></p>
    <?php endif; ?>
</div>

