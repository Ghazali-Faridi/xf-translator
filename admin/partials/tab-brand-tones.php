<?php
/**
 * Brand Tones Management Tab
 *
 * @package API_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<form method="post" action="">
    <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
    <input type="hidden" name="api_translator_action" value="save_brand_tones">
    
    <p class="description">
        <?php _e('Configure brand tone prompts for each domain. These prompts will be used to guide the translation style for each site.', 'api-translator'); ?>
    </p>
    
    <?php for ($i = 1; $i <= 4; $i++) : ?>
        <?php 
        $site_key = 'site' . $i;
        $tone_value = isset($brand_tones[$site_key]) ? $brand_tones[$site_key] : '';
        ?>
        <div class="api-translator-section">
            <h2><?php printf(__('Site %d Brand Tone', 'api-translator'), $i); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="brand_tone_<?php echo esc_attr($site_key); ?>">
                            <?php printf(__('Tone Prompt for Site %d', 'api-translator'), $i); ?>
                        </label>
                    </th>
                    <td>
                        <textarea id="brand_tone_<?php echo esc_attr($site_key); ?>" 
                                  name="brand_tone_<?php echo esc_attr($site_key); ?>" 
                                  rows="5" 
                                  class="large-text"
                                  placeholder="<?php esc_attr_e('e.g., Professional, friendly, and approachable tone suitable for a business audience...', 'api-translator'); ?>"><?php echo esc_textarea($tone_value); ?></textarea>
                        <p class="description">
                            <?php _e('Describe the desired tone, style, and voice for translations on this site.', 'api-translator'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    <?php endfor; ?>
    
    <?php submit_button(__('Save Brand Tones', 'api-translator')); ?>
</form>

