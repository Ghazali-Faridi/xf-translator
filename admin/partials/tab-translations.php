<?php
/**
 * Translations View Tab
 *
 * @package API_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get main plugin instance
global $api_translator;
if (!isset($api_translator) || !is_object($api_translator)) {
    echo '<p>' . __('Translation engine not initialized.', 'api-translator') . '</p>';
    return;
}

// Check if database instance exists
if (!isset($api_translator->database)) {
    echo '<p>' . __('Database not initialized.', 'api-translator') . '</p>';
    return;
}

$database = $api_translator->database;

// Get filter parameters
$filter_post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
$filter_language = isset($_GET['language']) ? sanitize_text_field($_GET['language']) : null;

// Get translations
$translations = $database->get_translations(array(
    'post_id' => $filter_post_id,
    'language' => $filter_language,
    'status' => 'completed',
    'limit' => 100
));

// Get all languages for filter
$settings = $api_translator_admin->settings;
$languages = $settings->get('languages', array());
?>

<div class="api-translator-section">
    <h2><?php _e('Saved Translations', 'api-translator'); ?></h2>
    
    <div class="api-translator-filters" style="margin-bottom: 20px;">
        <form method="get" action="" style="display: flex; gap: 10px; align-items: flex-end;">
            <input type="hidden" name="page" value="<?php echo esc_attr($api_translator_admin->menu_slug); ?>">
            <input type="hidden" name="tab" value="translations">
            
            <div>
                <label for="filter_post_id"><?php _e('Post ID:', 'api-translator'); ?></label>
                <input type="number" 
                       id="filter_post_id" 
                       name="post_id" 
                       value="<?php echo $filter_post_id ? esc_attr($filter_post_id) : ''; ?>" 
                       placeholder="<?php esc_attr_e('All posts', 'api-translator'); ?>"
                       style="width: 100px;">
            </div>
            
            <div>
                <label for="filter_language"><?php _e('Language:', 'api-translator'); ?></label>
                <select id="filter_language" name="language">
                    <option value=""><?php _e('All languages', 'api-translator'); ?></option>
                    <?php foreach ($languages as $lang) : ?>
                        <option value="<?php echo esc_attr($lang['prefix']); ?>" 
                                <?php selected($filter_language, $lang['prefix']); ?>>
                            <?php echo esc_html($lang['name'] . ' (' . $lang['prefix'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <?php submit_button(__('Filter', 'api-translator'), 'secondary', 'submit', false); ?>
                <?php if ($filter_post_id || $filter_language) : ?>
                    <a href="?page=<?php echo esc_attr($api_translator_admin->menu_slug); ?>&tab=translations" 
                       class="button">
                        <?php _e('Clear', 'api-translator'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if (empty($translations)) : ?>
        <p><?php _e('No translations found.', 'api-translator'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 80px;"><?php _e('Post ID', 'api-translator'); ?></th>
                    <th scope="col" style="width: 150px;"><?php _e('Post Title', 'api-translator'); ?></th>
                    <th scope="col" style="width: 100px;"><?php _e('Language', 'api-translator'); ?></th>
                    <th scope="col" style="width: 120px;"><?php _e('Field', 'api-translator'); ?></th>
                    <th scope="col"><?php _e('Translated Content', 'api-translator'); ?></th>
                    <th scope="col" style="width: 150px;"><?php _e('Updated', 'api-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($translations as $translation) : 
                    $content_data = json_decode($translation['content'], true);
                    $field = isset($content_data['field']) ? $content_data['field'] : 'unknown';
                    $translated_text = isset($content_data['translated_text']) ? $content_data['translated_text'] : '';
                    
                    $post = get_post($translation['post_id']);
                    $post_title = $post ? $post->post_title : __('Post not found', 'api-translator');
                    $post_edit_link = $post ? get_edit_post_link($translation['post_id']) : '#';
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($post_edit_link); ?>" target="_blank">
                                #<?php echo esc_html($translation['post_id']); ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($post_edit_link); ?>" target="_blank">
                                <?php echo esc_html($post_title); ?>
                            </a>
                        </td>
                        <td>
                            <strong><?php echo esc_html(strtoupper($translation['language'])); ?></strong>
                        </td>
                        <td>
                            <code><?php echo esc_html($field); ?></code>
                        </td>
                        <td>
                            <div style="max-height: 100px; overflow-y: auto;">
                                <?php 
                                if ($field === 'post_content') {
                                    echo wp_kses_post(wp_trim_words($translated_text, 30));
                                } else {
                                    echo esc_html(wp_trim_words($translated_text, 20));
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <?php echo esc_html($translation['updated_at']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

