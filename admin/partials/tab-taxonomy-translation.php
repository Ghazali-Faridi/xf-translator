<?php
/**
 * Taxonomy Translation Tab
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get all public taxonomies
$taxonomies = get_taxonomies(array('public' => true), 'objects');
$languages = $settings->get('languages', array());
?>

<div class="api-translator-section">
    <h2><?php _e('Taxonomy Translation', 'xf-translator'); ?></h2>
    <p><?php _e('Translate your categories, tags, and custom taxonomies into different languages. Each language will have its own translated terms.', 'xf-translator'); ?></p>
    
    <?php settings_errors('api_translator_messages'); ?>
    
    <?php if (empty($taxonomies)) : ?>
        <div class="notice notice-info">
            <p><?php _e('No taxonomies found.', 'xf-translator'); ?></p>
        </div>
    <?php elseif (empty($languages)) : ?>
        <div class="notice notice-warning">
            <p><?php _e('No languages configured. Please add languages in the Settings tab first.', 'xf-translator'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('Taxonomy', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Terms', 'xf-translator'); ?></th>
                    <?php foreach ($languages as $language) : ?>
                        <th scope="col"><?php echo esc_html($language['name']); ?></th>
                    <?php endforeach; ?>
                    <th scope="col"><?php _e('Actions', 'xf-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taxonomies as $taxonomy_name => $taxonomy_obj) : ?>
                    <?php
                    // Get all terms for this taxonomy
                    $all_terms = get_terms(array(
                        'taxonomy' => $taxonomy_name,
                        'hide_empty' => false,
                    ));
                    
                    // Filter out translated terms - only show original (English) terms
                    $terms = array();
                    if (is_array($all_terms) && !empty($all_terms)) {
                        foreach ($all_terms as $term) {
                            // Skip if this is a translated term (has original_term_id meta)
                            $original_term_id = get_term_meta($term->term_id, '_xf_translator_original_term_id', true);
                            if (empty($original_term_id)) {
                                $terms[] = $term;
                            }
                        }
                    }
                    
                    $term_count = count($terms);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($taxonomy_obj->label); ?></strong>
                            <br>
                            <small style="color: #666;"><?php echo esc_html($taxonomy_name); ?></small>
                        </td>
                        <td>
                            <?php echo number_format($term_count); ?>
                        </td>
                        <?php foreach ($languages as $language) : ?>
                            <?php
                            // Count translated terms for this language
                            $translated_count = 0;
                            if (is_array($terms) && !empty($terms)) {
                                foreach ($terms as $term) {
                                    $translated_term_id = get_term_meta($term->term_id, '_xf_translator_term_' . $language['prefix'], true);
                                    if ($translated_term_id) {
                                        $translated_count++;
                                    }
                                }
                            }
                            $translation_status = $translated_count . '/' . $term_count;
                            ?>
                            <td>
                                <?php if ($term_count > 0) : ?>
                                    <?php if ($translated_count === $term_count) : ?>
                                        <span style="color: #46b450; font-weight: bold;"><?php echo esc_html($translation_status); ?></span>
                                        <br>
                                        <small><?php _e('Complete', 'xf-translator'); ?></small>
                                    <?php elseif ($translated_count > 0) : ?>
                                        <span style="color: #f0ad4e;"><?php echo esc_html($translation_status); ?></span>
                                        <br>
                                        <small><?php _e('Partial', 'xf-translator'); ?></small>
                                    <?php else : ?>
                                        <span style="color: #999;"><?php echo esc_html($translation_status); ?></span>
                                        <br>
                                        <small><?php _e('Not Started', 'xf-translator'); ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <?php if ($term_count > 0) : ?>
                                <form method="post" action="" style="display: inline-block; margin: 0;">
                                    <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                    <input type="hidden" name="api_translator_action" value="translate_taxonomy">
                                    <input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy_name); ?>">
                                    <select name="target_language" required style="margin-right: 5px;">
                                        <option value=""><?php _e('Select Language', 'xf-translator'); ?></option>
                                        <?php foreach ($languages as $language) : ?>
                                            <option value="<?php echo esc_attr($language['name']); ?>">
                                                <?php echo esc_html($language['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button button-primary button-small">
                                        <?php _e('Translate All', 'xf-translator'); ?>
                                    </button>
                                </form>
                                <br style="margin: 5px 0;">
                                <a href="?page=xf-translator&tab=taxonomy-translation&taxonomy=<?php echo esc_attr($taxonomy_name); ?>" class="button button-small">
                                    <?php _e('View Terms', 'xf-translator'); ?>
                                </a>
                            <?php else : ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Show terms for a specific taxonomy if requested
        $selected_taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';
        if ($selected_taxonomy && taxonomy_exists($selected_taxonomy)) :
            $taxonomy_obj = get_taxonomy($selected_taxonomy);
            $all_terms = get_terms(array(
                'taxonomy' => $selected_taxonomy,
                'hide_empty' => false,
            ));
            
            // Filter out translated terms - only show original (English) terms
            $terms = array();
            if (is_array($all_terms) && !empty($all_terms)) {
                foreach ($all_terms as $term) {
                    // Skip if this is a translated term (has original_term_id meta)
                    $original_term_id = get_term_meta($term->term_id, '_xf_translator_original_term_id', true);
                    if (empty($original_term_id)) {
                        $terms[] = $term;
                    }
                }
            }
            ?>
            <div style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ddd;">
                <h3><?php echo esc_html($taxonomy_obj->label); ?> - <?php _e('Terms', 'xf-translator'); ?></h3>
                <?php if (is_array($terms) && !empty($terms)) : ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('Term Name', 'xf-translator'); ?></th>
                                <th scope="col"><?php _e('Slug', 'xf-translator'); ?></th>
                                <?php foreach ($languages as $language) : ?>
                                    <th scope="col"><?php echo esc_html($language['name']); ?></th>
                                <?php endforeach; ?>
                                <th scope="col"><?php _e('Actions', 'xf-translator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($terms as $term) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($term->name); ?></strong>
                                        <?php if ($term->parent > 0) : ?>
                                            <?php $parent = get_term($term->parent); ?>
                                            <br>
                                            <small style="color: #666;"><?php _e('Parent:', 'xf-translator'); ?> <?php echo esc_html($parent->name); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo esc_html($term->slug); ?></code>
                                    </td>
                                    <?php foreach ($languages as $language) : ?>
                                        <?php
                                        $translated_term_id = get_term_meta($term->term_id, '_xf_translator_term_' . $language['prefix'], true);
                                        $translated_term = $translated_term_id ? get_term($translated_term_id) : false;
                                        ?>
                                        <td>
                                            <?php if ($translated_term) : ?>
                                                <span style="color: #46b450; font-weight: bold;"><?php echo esc_html($translated_term->name); ?></span>
                                                <br>
                                                <small><code><?php echo esc_html($translated_term->slug); ?></code></small>
                                            <?php else : ?>
                                                <span style="color: #999;"><?php _e('Not Translated', 'xf-translator'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <form method="post" action="" style="display: inline-block; margin: 0;">
                                            <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                            <input type="hidden" name="api_translator_action" value="translate_term">
                                            <input type="hidden" name="term_id" value="<?php echo esc_attr($term->term_id); ?>">
                                            <select name="target_language" required style="margin-right: 5px; font-size: 11px;">
                                                <option value=""><?php _e('Select', 'xf-translator'); ?></option>
                                                <?php foreach ($languages as $language) : ?>
                                                    <?php
                                                    $translated_term_id = get_term_meta($term->term_id, '_xf_translator_term_' . $language['prefix'], true);
                                                    if (!$translated_term_id) :
                                                    ?>
                                                        <option value="<?php echo esc_attr($language['name']); ?>">
                                                            <?php echo esc_html($language['name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="button button-small" style="font-size: 11px;">
                                                <?php _e('Translate', 'xf-translator'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('No terms found in this taxonomy.', 'xf-translator'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
            <h3><?php _e('How Taxonomy Translation Works', 'xf-translator'); ?></h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php _e('Each language gets its own translated terms with translated names, slugs, and descriptions.', 'xf-translator'); ?></li>
                <li><?php _e('When translating posts, translated terms are automatically assigned instead of original terms.', 'xf-translator'); ?></li>
                <li><?php _e('Term hierarchies (parent-child relationships) are preserved in translations.', 'xf-translator'); ?></li>
                <li><?php _e('Term archive URLs are automatically updated with language prefixes on the frontend.', 'xf-translator'); ?></li>
                <li><?php _e('You can translate entire taxonomies at once or individual terms.', 'xf-translator'); ?></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

