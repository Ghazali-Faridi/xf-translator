<?php
/**
 * Admin Settings Page Template
 *
 * @package API_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get settings instance from admin object
global $api_translator_admin;
$settings = $api_translator_admin->settings;
$languages = $settings->get('languages', array());
$brand_tone = $settings->get('brand_tone', '');
$exclude_paths = $settings->get('exclude_paths', array());
$glossary_terms = $settings->get('glossary_terms', array());
$api_key = $settings->get('api_key', '');
$deepseek_api_key = $settings->get('deepseek_api_key', '');
$selected_model = $settings->get('selected_model', 'gpt-4o');
$processing_delay_minutes = $settings->get('processing_delay_minutes', 0);
?>

<div class="wrap api-translator-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_label) : ?>
            <a href="?page=xf-translator&tab=<?php echo esc_attr($tab_key); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="api-translator-content">
        <?php if ($current_tab === 'general') : ?>
            <?php include plugin_dir_path( __FILE__ ) . 'tab-general.php'; ?>
        <?php elseif ($current_tab === 'test-translation') : ?>
            <?php include plugin_dir_path( __FILE__ ) . 'tab-test-translation.php'; ?>
        <?php elseif ($current_tab === 'queue') : ?>
            <?php include plugin_dir_path( __FILE__ ) . 'tab-queue.php'; ?>
        <?php elseif ($current_tab === 'existing-queue') : ?>
            <?php include plugin_dir_path( __FILE__ ) . 'tab-existing-queue.php'; ?>
        <?php elseif ($current_tab === 'translation-rules') : ?>
            <?php include plugin_dir_path( __FILE__ ) . 'tab-translation-rules.php'; ?>
        <?php elseif ($current_tab === 'menu-translation') : ?>
            <?php include plugin_dir_path( __FILE__ ) . 'tab-menu-translation.php'; ?>
        <?php elseif ($current_tab === 'taxonomy-translation') : ?>
            <?php include plugin_dir_path( __FILE__ ) . 'tab-taxonomy-translation.php'; ?>
        <?php endif; ?>
    </div>
</div>

