<?php
/**
 * Menu Translation Tab
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get all menus, but filter out translated menus (only show original English menus)
$all_menus = wp_get_nav_menus();
$menus = array();

foreach ($all_menus as $menu) {
    // Check if this is a translated menu (has original_menu_id meta)
    $original_menu_id = get_term_meta($menu->term_id, '_xf_translator_original_menu_id', true);
    
    // Only include menus that are NOT translations (original menus)
    if (empty($original_menu_id)) {
        $menus[] = $menu;
    }
}

$languages = $settings->get('languages', array());
?>

<div class="api-translator-section">
    <h2><?php _e('Menu Translation', 'xf-translator'); ?></h2>
    <p><?php _e('Translate your navigation menus into different languages. Each language will have its own menu with translated menu items.', 'xf-translator'); ?></p>
    
    <?php settings_errors('api_translator_messages'); ?>
    
    <?php if (empty($menus)) : ?>
        <div class="notice notice-info">
            <p><?php _e('No menus found. Please create a menu first in Appearance > Menus.', 'xf-translator'); ?></p>
        </div>
    <?php elseif (empty($languages)) : ?>
        <div class="notice notice-warning">
            <p><?php _e('No languages configured. Please add languages in the Settings tab first.', 'xf-translator'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Menu Name', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Items', 'xf-translator'); ?></th>
                    <?php foreach ($languages as $language) : ?>
                        <th scope="col"><?php echo esc_html($language['name']); ?></th>
                    <?php endforeach; ?>
                    <th scope="col"><?php _e('Actions', 'xf-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menus as $menu) : ?>
                    <?php
                    $menu_items = wp_get_nav_menu_items($menu->term_id);
                    $item_count = is_array($menu_items) ? count($menu_items) : 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($menu->name); ?></strong>
                            <br>
                            <small style="color: #666;"><?php _e('ID:', 'xf-translator'); ?> <?php echo esc_html($menu->term_id); ?></small>
                        </td>
                        <td>
                            <?php echo number_format($item_count); ?>
                        </td>
                        <?php foreach ($languages as $language) : ?>
                            <?php
                            // Check if translated menu exists
                            $translated_menu_id = get_term_meta($menu->term_id, '_xf_translator_menu_' . $language['prefix'], true);
                            $translated_menu = $translated_menu_id ? wp_get_nav_menu_object($translated_menu_id) : false;
                            
                            // Clean up orphaned meta if menu was deleted
                            if ($translated_menu_id && !$translated_menu) {
                                delete_term_meta($menu->term_id, '_xf_translator_menu_' . $language['prefix']);
                            }
                            ?>
                            <td>
                                <?php if ($translated_menu) : ?>
                                    <span style="color: #46b450; font-weight: bold;"><?php _e('Translated', 'xf-translator'); ?></span>
                                    <br>
                                    <small>
                                        <a href="<?php echo admin_url('nav-menus.php?action=edit&menu=' . $translated_menu->term_id); ?>" target="_blank">
                                            <?php _e('Edit', 'xf-translator'); ?>
                                        </a>
                                    </small>
                                <?php else : ?>
                                    <span style="color: #f0ad4e;"><?php _e('Not Translated', 'xf-translator'); ?></span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <form method="post" action="" style="display: inline-block; margin: 0;">
                                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                <input type="hidden" name="api_translator_action" value="translate_menu">
                                <input type="hidden" name="menu_id" value="<?php echo esc_attr($menu->term_id); ?>">
                                <select name="target_language" required style="margin-right: 5px;">
                                    <option value=""><?php _e('Select Language', 'xf-translator'); ?></option>
                                    <?php foreach ($languages as $language) : ?>
                                        <?php
                                        $translated_menu_id = get_term_meta($menu->term_id, '_xf_translator_menu_' . $language['prefix'], true);
                                        $translated_menu = $translated_menu_id ? wp_get_nav_menu_object($translated_menu_id) : false;
                                        // Only show language if translated menu doesn't exist (or was deleted)
                                        if (!$translated_menu) :
                                            // Clean up orphaned meta if menu was deleted
                                            if ($translated_menu_id) {
                                                delete_term_meta($menu->term_id, '_xf_translator_menu_' . $language['prefix']);
                                            }
                                        ?>
                                            <option value="<?php echo esc_attr($language['name']); ?>">
                                                <?php echo esc_html($language['name']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button button-primary button-small">
                                    <?php _e('Translate', 'xf-translator'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
            <h3><?php _e('How Menu Translation Works', 'xf-translator'); ?></h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php _e('Each language gets its own separate menu with translated menu item labels.', 'xf-translator'); ?></li>
                <li><?php _e('Menu item URLs are automatically updated to point to translated posts/pages when available.', 'xf-translator'); ?></li>
                <li><?php _e('The translated menus are automatically displayed on the frontend based on the current language.', 'xf-translator'); ?></li>
                <li><?php _e('You can edit translated menus in Appearance > Menus after translation.', 'xf-translator'); ?></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

