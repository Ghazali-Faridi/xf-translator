<?php
/**
 * ACF Field Translation Tab - Unified Bulk Translation Interface
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$languages = $settings->get('languages', array());
$selected_language_filter = isset($_GET['filter_language']) ? sanitize_text_field($_GET['filter_language']) : '';
$translatable_acf_fields = $settings->get_translatable_acf_fields();
?>

<div class="api-translator-section">
    <h2><?php _e('ACF Field Translation Settings', 'xf-translator'); ?></h2>
    <p><?php _e('Select ACF fields from field groups below. Save your selection, and these fields will be automatically translated when you translate posts or pages.', 'xf-translator'); ?></p>

    <?php settings_errors('api_translator_messages'); ?>

    <!-- Step 1: Select ACF Field Group -->
    <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('Select ACF Field Group', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('Choose an ACF field group to view and select fields for translation. Only text-based fields (text, textarea, wysiwyg, email, url, number) will be shown.', 'xf-translator'); ?></p>

        <div style="margin: 20px 0;">
            <select id="acf-field-group-select" style="min-width: 300px;">
                <option value=""><?php _e('Select a field group...', 'xf-translator'); ?></option>
            </select>
            <button type="button" id="load-acf-group-btn" class="button button-primary" style="margin-left: 10px;">
                <?php _e('Load Field Group', 'xf-translator'); ?>
            </button>
            <button type="button" id="test-acf-detection-btn" class="button" style="margin-left: 10px;">
                <?php _e('Test ACF Detection', 'xf-translator'); ?>
            </button>
            <span id="load-acf-group-loading" style="display: none; margin-left: 10px;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <?php _e('Loading...', 'xf-translator'); ?>
            </span>
        </div>

        <div id="acf-fields-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fff; margin-top: 15px; display: none;">
            <p id="acf-fields-empty" style="color: #666;">
                <?php _e('Select a field group above to view available fields.', 'xf-translator'); ?>
            </p>
            <div id="acf-fields-list"></div>
        </div>
    </div>

    <!-- Step 2: Selected Fields Display -->
    <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('Selected ACF Fields', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('Fields you have selected from the field groups above. Click "Save Selected Fields" to save them for automatic translation.', 'xf-translator'); ?></p>
        
        <div id="selected-acf-fields-display" style="min-height: 50px; border: 1px solid #ddd; padding: 15px; background: #fff; margin: 15px 0;">
            <p style="color: #666; margin: 0;"><?php _e('No fields selected yet. Select fields from a field group above.', 'xf-translator'); ?></p>
        </div>
        
        <button type="button" id="save-selected-acf-fields-btn" class="button button-primary" style="display: none;">
            <?php _e('Save Selected Fields', 'xf-translator'); ?>
        </button>
        <span id="save-acf-fields-loading" style="display: none; margin-left: 10px;">
            <span class="spinner is-active" style="float: none; margin: 0;"></span>
            <?php _e('Saving...', 'xf-translator'); ?>
        </span>
    </div>

    <!-- Step 3: Saved Fields Display -->
    <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('Saved ACF Fields', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('These are the ACF fields that will be automatically translated when you translate posts or pages.', 'xf-translator'); ?></p>
        
        <div id="saved-acf-fields-display" style="min-height: 50px; border: 1px solid #ddd; padding: 15px; background: #fff; margin: 15px 0;">
                            <?php
                            $translatable_acf = $settings->get_translatable_acf_fields();
                            if (!empty($translatable_acf)) {
                echo '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
                                foreach ($translatable_acf as $field) {
                    echo '<span style="background: #00a32a; color: #fff; padding: 5px 10px; border-radius: 3px; font-size: 12px;">';
                                    echo esc_html($field);
                    echo ' <a href="#" class="remove-saved-acf-field" data-field="' . esc_attr($field) . '" style="color: #ffcccc; text-decoration: none; margin-left: 5px;">×</a>';
                                    echo '</span>';
                                }
                                echo '</div>';
            } else {
                echo '<p style="color: #666; margin: 0;">' . __('No fields saved yet. Select fields above and click "Save Selected Fields".', 'xf-translator') . '</p>';
                            }
                            ?>
                        </div>
    </div>

    <!-- Bulk Translate ACF Fields for Existing Translated Posts -->
    <div class="api-translator-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2><?php _e('Translate ACF Fields for Existing Translated Posts', 'xf-translator'); ?></h2>
        <p>
            <?php _e('This tool will translate ACF fields for existing translated posts in batches. It will find all translated posts and translate their ACF fields (like "top_quote") that were previously just copied from the original posts.', 'xf-translator'); ?>
        </p>
        <p class="description" style="color: #d63638; font-weight: 600;">
            <?php _e('⚠️ Important: This will use your API credits to translate ACF fields. Posts will be processed in batches of 300 to avoid timeouts. Make sure you have configured translatable ACF fields above before running this.', 'xf-translator'); ?>
        </p>
        
        <?php
        // Check if this is a continuation
        $is_continuation = isset($_GET['continue_acf_bulk']) && $_GET['continue_acf_bulk'] == '1';
        $current_offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $batch_size = isset($_GET['batch_size']) ? (int) $_GET['batch_size'] : 300;
        
        if ($is_continuation) {
            echo '<div class="notice notice-info" style="margin: 15px 0;"><p>';
            echo sprintf(__('Continuing from post %d. Processing next batch of %d posts...', 'xf-translator'), $current_offset, $batch_size);
            echo '</p></div>';
        }
        ?>
        
        <form method="post" action="" style="margin-top: 15px;">
            <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
            <input type="hidden" name="api_translator_action" value="bulk_translate_acf_fields">
            <input type="hidden" name="batch_size" value="<?php echo esc_attr($batch_size); ?>">
            <input type="hidden" name="offset" value="<?php echo esc_attr($current_offset); ?>">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="batch_size_input"><?php _e('Batch Size', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="batch_size_input" 
                               name="batch_size" 
                               value="<?php echo esc_attr($batch_size); ?>" 
                               min="50" 
                               max="500" 
                               step="50"
                               style="width: 100px;">
                        <p class="description"><?php _e('Number of posts to process per batch (recommended: 300).', 'xf-translator'); ?></p>
                    </td>
                </tr>
            </table>
            
            <button type="submit" 
                    class="button button-primary" 
                    onclick="return confirm('<?php 
                        if ($is_continuation) {
                            esc_attr_e('This will continue translating ACF fields for the next batch of translated posts. Continue?', 'xf-translator');
                        } else {
                            esc_attr_e('This will translate ACF fields for existing translated posts in batches. This may take a long time and use API credits. Are you sure you want to continue?', 'xf-translator');
                        }
                    ?>');">
                <?php 
                if ($is_continuation) {
                    _e('Continue Next Batch', 'xf-translator');
                } else {
                    _e('Start Translating ACF Fields (First Batch)', 'xf-translator');
                }
                ?>
            </button>
        </form>
        
        <?php
        // Show admin notices if any
        settings_errors('xf_translator_messages');
        ?>
    </div>

    <!-- Translate ACF Options Fields Only -->
    <div class="api-translator-section" style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffc107; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2><?php _e('Translate ACF Options Fields', 'xf-translator'); ?></h2>
        <p>
            <?php _e('This tool will translate ACF fields that are stored in ACF Options Pages (not attached to individual posts). These fields appear on all posts/pages, like "default_top_quote" or global site settings.', 'xf-translator'); ?>
        </p>
        <p class="description" style="color: #856404; font-weight: 600;">
            <?php _e('⚠️ Important: This will translate options fields for ALL configured languages. Make sure you have configured translatable ACF fields above before running this.', 'xf-translator'); ?>
        </p>
        
        <?php
        // Show which fields are configured
        $translatable_acf_fields = $settings->get_translatable_acf_fields();
        if (!empty($translatable_acf_fields)) {
            echo '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; margin: 15px 0; border-radius: 3px;">';
            echo '<strong>' . __('Configured ACF Fields:', 'xf-translator') . '</strong> ';
            echo '<span style="color: #666;">' . implode(', ', array_map('esc_html', $translatable_acf_fields)) . '</span>';
            echo '</div>';
        }
        
        // Show which languages will be processed
        $languages = $settings->get('languages', array());
        if (!empty($languages)) {
            echo '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; margin: 15px 0; border-radius: 3px;">';
            echo '<strong>' . __('Languages to translate:', 'xf-translator') . '</strong> ';
            $lang_names = array();
            foreach ($languages as $lang) {
                $lang_names[] = $lang['name'] . ' (' . $lang['prefix'] . ')';
            }
            echo '<span style="color: #666;">' . implode(', ', array_map('esc_html', $lang_names)) . '</span>';
            echo '</div>';
        }
        ?>
        
        <form method="post" action="" style="margin-top: 15px;">
            <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
            <input type="hidden" name="api_translator_action" value="translate_acf_options_fields">
            <button type="submit" 
                    class="button button-primary" 
                    style="background: #ffc107; border-color: #ffc107; color: #000; font-weight: 600;"
                    onclick="return confirm('<?php esc_attr_e('This will translate ACF options fields for all configured languages. This will use API credits. Are you sure you want to continue?', 'xf-translator'); ?>');">
                <?php _e('Translate ACF Options Fields for All Languages', 'xf-translator'); ?>
            </button>
        </form>
        
        <?php
        // Show admin notices if any
        settings_errors('xf_translator_messages');
        ?>
    </div>

    <!-- Step 2: Select Fields and Language -->
    <!-- <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('Select Fields and Language', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('Select which ACF fields to translate and choose the target language.', 'xf-translator'); ?></p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="selected-acf-fields"><?php _e('Selected Fields', 'xf-translator'); ?></label>
                </th>
                <td>
                    <div id="selected-acf-fields-display" style="min-height: 50px; padding: 10px; border: 1px solid #ddd; background: #fff; border-radius: 3px;">
                        <p style="color: #666; margin: 0;"><?php _e('No fields selected. Select fields from the scan results above.', 'xf-translator'); ?></p>
                    </div>
                    <input type="hidden" id="selected-acf-fields" name="selected_acf_fields" value="">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="translate-acf-language"><?php _e('Target Language', 'xf-translator'); ?></label>
                </th>
                <td>
                    <select id="translate-acf-language" name="translate_acf_language" class="regular-text">
                        <option value=""><?php _e('Select a language...', 'xf-translator'); ?></option>
                        <?php foreach ($languages as $lang) : ?>
                            <option value="<?php echo esc_attr($lang['prefix']); ?>">
                                <?php echo esc_html($lang['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="button" id="translate-acf-bulk-btn" class="button button-primary" disabled>
                <?php _e('Translate All Selected ACF Fields for All Posts', 'xf-translator'); ?>
            </button>
            <span id="translate-acf-bulk-loading" style="display: none; margin-left: 10px;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <?php _e('Translating... This may take a while.', 'xf-translator'); ?>
            </span>
        </p>
    </div> -->

    <!-- Step 3: View and Edit Translations -->
    <!-- <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('View Translations', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('View all ACF field translations and edit them as needed. Use the language filter to view translations for a specific language.', 'xf-translator'); ?></p>

        <div style="margin: 20px 0;">
            <label for="filter-acf-language" style="font-weight: bold; margin-right: 10px;">
                <?php _e('Filter by Language:', 'xf-translator'); ?>
            </label>
            <select id="filter-acf-language" name="filter_acf_language" style="min-width: 200px;">
                <option value=""><?php _e('All Languages', 'xf-translator'); ?></option>
                <?php foreach ($languages as $lang) : ?>
                    <option value="<?php echo esc_attr($lang['prefix']); ?>" <?php selected($selected_language_filter, $lang['prefix']); ?>>
                        <?php echo esc_html($lang['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="apply-acf-filter-btn" class="button" style="margin-left: 10px;">
                <?php _e('Apply Filter', 'xf-translator'); ?>
            </button>
        </div>

        <div id="acf-translations-results-container">
            <p style="color: #666;"><?php _e('Translations will appear here after you translate fields. Use the language filter above to view specific translations.', 'xf-translator'); ?></p>
        </div>
    </div> -->
</div>

<script>
jQuery(document).ready(function($) {
    var selectedAcfFields = [];
    var savedAcfFields = <?php echo json_encode($translatable_acf_fields); ?>;
    var allScannedAcfFields = [];
    var acfTranslationsData = {};

    // Initialize selected fields with saved fields
    selectedAcfFields = savedAcfFields.slice();
    updateSelectedAcfFieldsDisplay();
    updateSavedAcfFieldsDisplay();

    // Load ACF field groups on page load
    loadAcfFieldGroups();

    function loadAcfFieldGroups() {
        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_get_acf_field_groups',
                nonce: apiTranslator.nonce
            },
            success: function(response) {
                if (response.success && response.data.groups) {
                    var select = $('#acf-field-group-select');
                    select.empty();
                    select.append('<option value=""><?php _e('Select a field group...', 'xf-translator'); ?></option>');

                    response.data.groups.forEach(function(group) {
                        select.append('<option value="' + escapeHtml(group.key) + '">' + escapeHtml(group.title) + '</option>');
                    });

                    console.log('ACF Field Groups loaded:', response.data.groups.length);
                    console.log('Field groups:', response.data.groups);
                } else {
                    console.error('Error loading ACF field groups:', response);
                    $('#acf-field-group-select').after('<p style="color: #dc3232; font-size: 12px;">Error loading ACF field groups. Check that ACF is active and you have field groups created.</p>');
                }
            },
            error: function() {
                console.error('AJAX error loading ACF field groups');
            }
        });
    }

    // Test ACF detection
    $('#test-acf-detection-btn').on('click', function() {
        if (!confirm('This will test ACF field detection across your site. It may take a moment. Continue?')) {
            return;
        }

        $('#test-acf-detection-btn').prop('disabled', true).text('<?php _e('Testing...', 'xf-translator'); ?>');

        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_test_acf_detection',
                nonce: apiTranslator.nonce
            },
            success: function(response) {
                console.log('ACF Detection Test Results:', response);

                var message = 'ACF Detection Test Results:\n\n';
                if (response.success && response.data) {
                    message += 'ACF Active: ' + (response.data.acf_active ? 'Yes' : 'No') + '\n';
                    message += 'Field Groups Found: ' + response.data.field_groups_count + '\n';
                    message += 'Options Pages Checked: ' + response.data.options_pages_checked.join(', ') + '\n';
                    message += 'Fields Found in Options: ' + response.data.fields_in_options + '\n';
                    message += 'Posts Checked: ' + response.data.posts_checked + '\n';
                    message += 'Post Types Found: ' + JSON.stringify(response.data.post_types_found, null, 2) + '\n';
                }

                alert(message);
            },
            error: function() {
                alert('AJAX error during ACF detection test');
            },
            complete: function() {
                $('#test-acf-detection-btn').prop('disabled', false).text('<?php _e('Test ACF Detection', 'xf-translator'); ?>');
            }
        });
    });

    // Load fields when field group is selected
    $('#load-acf-group-btn').on('click', function() {
        var groupKey = $('#acf-field-group-select').val();
        if (!groupKey) {
            alert('<?php _e('Please select a field group first.', 'xf-translator'); ?>');
            return;
        }

        $('#load-acf-group-loading').show();
        $('#load-acf-group-btn').prop('disabled', true);

        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_scan_acf_fields_by_group',
                nonce: apiTranslator.nonce,
                group_key: groupKey
            },
            success: function(response) {
                if (response.success && response.data.fields) {
                    allScannedAcfFields = response.data.fields;
                    displayScannedAcfFields(response.data.fields);
                    $('#acf-fields-container').show();

                    // Show success info to user
                    var infoMsg = 'Loaded ' + response.data.fields.length + ' translatable fields from "' + $('#acf-field-group-select option:selected').text() + '"';
                    $('#acf-fields-empty').after('<p style="color: #00a32a; font-size: 12px; margin-top: 10px;">' + infoMsg + '</p>');

                    console.log('ACF Fields loaded:', response.data.fields.length);
                    console.log('Fields:', response.data.fields);
                } else {
                    var errorMsg = 'Error loading ACF fields: ' + (response.data.message || 'Unknown error');
                    $('#acf-fields-empty').after('<p style="color: #dc3232; font-size: 12px; margin-top: 10px;">' + errorMsg + '</p>');
                    console.error('ACF Load Error:', response);
                }
            },
            error: function() {
                alert('AJAX error occurred while loading ACF fields');
            },
            complete: function() {
                $('#load-acf-group-loading').hide();
                $('#load-acf-group-btn').prop('disabled', false);
            }
        });
    });

    // Display scanned ACF fields
    function displayScannedAcfFields(fields) {
        var html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px;">';
        fields.forEach(function(field) {
            // Check if field is in selected list (which includes saved fields on load)
            var checked = selectedAcfFields.indexOf(field.key) !== -1 ? 'checked' : '';
            var label = field.label || field.key;
            var sample = field.sample ? ' <span style="color: #666; font-size: 11px;">(' + escapeHtml(field.sample.substring(0, 50)) + '...)</span>' : '';
            var keyDisplay = field.key !== label ? ' <span style="color: #999; font-size: 10px; font-family: monospace;">(' + escapeHtml(field.key) + ')</span>' : '';
            html += '<label style="display: block; padding: 8px; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; background: #fff;">';
            var fieldType = field.type ? ' <span style="color: #999; font-size: 10px; font-family: monospace;">(' + escapeHtml(field.type) + ')</span>' : '';
            html += '<input type="checkbox" class="acf-field-checkbox" value="' + escapeHtml(field.key) + '" ' + checked + ' style="margin-right: 8px;"> ';
            html += '<strong>' + escapeHtml(label) + '</strong>' + keyDisplay + fieldType + sample;
            html += '</label>';
        });
        html += '</div>';
        $('#acf-fields-list').html(html);
        $('#acf-fields-empty').hide();

        // Bind checkbox change events
        $('.acf-field-checkbox').on('change', function() {
            var field = $(this).val();
            if ($(this).is(':checked')) {
                if (selectedAcfFields.indexOf(field) === -1) {
                    selectedAcfFields.push(field);
                }
            } else {
                var index = selectedAcfFields.indexOf(field);
                if (index !== -1) {
                    selectedAcfFields.splice(index, 1);
                }
            }
            updateSelectedAcfFieldsDisplay();
        });
    }

    // Update selected ACF fields display
    function updateSelectedAcfFieldsDisplay() {
        if (selectedAcfFields.length === 0) {
            $('#selected-acf-fields-display').html('<p style="color: #666; margin: 0;"><?php _e('No fields selected yet. Select fields from a field group above.', 'xf-translator'); ?></p>');
            $('#save-selected-acf-fields-btn').hide();
            return;
        }

        var html = '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
        selectedAcfFields.forEach(function(field) {
            var fieldObj = allScannedAcfFields.find(f => f.key === field);
            var label = fieldObj ? (fieldObj.label || field) : field;
            html += '<span style="background: #2271b1; color: #fff; padding: 5px 10px; border-radius: 3px; font-size: 12px;">';
            html += escapeHtml(label);
            html += ' <span style="cursor: pointer; margin-left: 5px; color: #ffcccc;" class="remove-selected-acf-field" data-field="' + escapeHtml(field) + '">×</span>';
            html += '</span>';
        });
        html += '</div>';
        $('#selected-acf-fields-display').html(html);
        $('#save-selected-acf-fields-btn').show();

        // Bind remove buttons
        $('.remove-selected-acf-field').on('click', function() {
            var field = $(this).data('field');
            var index = selectedAcfFields.indexOf(field);
            if (index !== -1) {
                selectedAcfFields.splice(index, 1);
                $('.acf-field-checkbox[value="' + escapeHtml(field) + '"]').prop('checked', false);
                updateSelectedAcfFieldsDisplay();
                updateAcfTranslateButton();
            }
        });
    }

    // Update saved ACF fields display
    function updateSavedAcfFieldsDisplay() {
        if (savedAcfFields.length === 0) {
            $('#saved-acf-fields-display').html('<p style="color: #666; margin: 0;"><?php _e('No fields saved yet. Select fields above and click "Save Selected Fields".', 'xf-translator'); ?></p>');
            return;
        }

        var html = '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
        savedAcfFields.forEach(function(field) {
            html += '<span style="background: #00a32a; color: #fff; padding: 5px 10px; border-radius: 3px; font-size: 12px;">';
            html += escapeHtml(field);
            html += ' <a href="#" class="remove-saved-acf-field" data-field="' + escapeHtml(field) + '" style="color: #ffcccc; text-decoration: none; margin-left: 5px;">×</a>';
            html += '</span>';
        });
        html += '</div>';
        $('#saved-acf-fields-display').html(html);

        // Bind remove buttons
        $('.remove-saved-acf-field').on('click', function(e) {
            e.preventDefault();
            var field = $(this).data('field');
            var index = savedAcfFields.indexOf(field);
            if (index !== -1) {
                savedAcfFields.splice(index, 1);
                // Also remove from selected if it's there
                var selectedIndex = selectedAcfFields.indexOf(field);
                if (selectedIndex !== -1) {
                    selectedAcfFields.splice(selectedIndex, 1);
                    $('.acf-field-checkbox[value="' + escapeHtml(field) + '"]').prop('checked', false);
                    updateSelectedAcfFieldsDisplay();
                }
                // Save the updated list
                saveAcfFieldsToSettings();
            }
        });
    }

    // Save selected ACF fields to settings
    function saveAcfFieldsToSettings() {
        $('#save-acf-fields-loading').show();
        $('#save-selected-acf-fields-btn').prop('disabled', true);

        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_save_acf_settings',
                nonce: apiTranslator.nonce,
                fields: savedAcfFields
            },
            success: function(response) {
                if (response.success) {
                    // Update saved fields from response
                    if (response.data.fields) {
                        savedAcfFields = response.data.fields;
                    }
                    updateSavedAcfFieldsDisplay();
                    alert('<?php _e('ACF fields saved successfully! These fields will be automatically translated when you translate posts.', 'xf-translator'); ?>');
                } else {
                    alert('<?php _e('Error saving fields:', 'xf-translator'); ?> ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('<?php _e('AJAX error occurred while saving fields.', 'xf-translator'); ?>');
            },
            complete: function() {
                $('#save-acf-fields-loading').hide();
                $('#save-selected-acf-fields-btn').prop('disabled', false);
            }
        });
    }

    // Save button click handler
    $('#save-selected-acf-fields-btn').on('click', function() {
        // Update saved fields with current selected fields
        savedAcfFields = selectedAcfFields.slice();
        saveAcfFieldsToSettings();
    });

    // Update translate button state
    function updateAcfTranslateButton() {
        var hasFields = selectedAcfFields.length > 0;
        var hasLanguage = $('#translate-acf-language').val() !== '';
        $('#translate-acf-bulk-btn').prop('disabled', !hasFields || !hasLanguage);
    }

    $('#translate-acf-language').on('change', function() {
        updateAcfTranslateButton();
    });

    // Translate bulk ACF fields
    $('#translate-acf-bulk-btn').on('click', function() {
        if (selectedAcfFields.length === 0) {
            alert('Please select ACF fields first.');
            return;
        }

        if ($('#translate-acf-language').val() === '') {
            alert('Please select a target language first.');
            return;
        }

        console.log('Starting ACF translation with fields:', selectedAcfFields);

        if (!confirm('This will translate all selected ACF fields for ALL posts. This may take a while. Continue?')) {
            return;
        }

        $('#translate-acf-bulk-loading').show();
        $('#translate-acf-bulk-btn').prop('disabled', true);

        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_translate_acf_bulk',
                nonce: apiTranslator.nonce,
                fields: selectedAcfFields,
                language_prefix: $('#translate-acf-language').val()
            },
            success: function(response) {
                console.log('ACF Translation Response:', response);

                if (response.data.debug) {
                    console.log('Debug Info:', response.data.debug);
                    console.log('Posts checked:', response.data.debug.posts_checked);
                    console.log('Posts with fields:', response.data.debug.posts_with_fields);
                    console.log('Selected fields:', response.data.debug.selected_fields);
                }

                if (response.success && response.data.results && response.data.results.length > 0) {
                    // Display results for both options pages and regular posts
                        acfTranslationsData = response.data;
                        displayAcfTranslations(response.data.results, response.data.language_prefix);
                        $('#translate-acf-bulk-loading').hide();
                        $('#translate-acf-bulk-btn').prop('disabled', false);
                } else if (response.success && response.data.results && response.data.results.length === 0) {
                    // No posts found with the selected fields
                    var debugInfo = response.data.debug;
                    var debugHtml = '';
                    if (debugInfo) {
                        debugHtml = '<br><small style="color: #856404;">Debug info: Checked ' + debugInfo.posts_checked + ' posts, found ' + debugInfo.posts_with_fields + ' with fields. Post types: ' + JSON.stringify(debugInfo.post_types_found) + '</small>';
                    }

                    $('#acf-translations-results-container').html('<div style="text-align: center; padding: 40px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;"><h4 style="color: #856404; margin: 0 0 10px 0;">No posts found with selected ACF fields</h4><p style="color: #856404; margin: 0;">The selected ACF fields were not found in any published posts. This could mean:<br>• The fields exist but have no content<br>• The fields are not assigned to any posts<br>• The field group is not active on any post types</p>' + debugHtml + '</div>');
                    $('#translate-acf-bulk-loading').hide();
                    $('#translate-acf-bulk-btn').prop('disabled', false);
                } else {
                    alert('Error translating: ' + (response.data.message || 'Unknown error'));
                    $('#translate-acf-bulk-loading').hide();
                    $('#translate-acf-bulk-btn').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'AJAX error occurred while translating';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    } catch(e) {
                        errorMessage = 'Error: ' + error + ' (Status: ' + status + ')';
                    }
                }
                alert(errorMessage);
                console.error('ACF Translation AJAX Error:', xhr, status, error);
                $('#translate-acf-bulk-loading').hide();
                $('#translate-acf-bulk-btn').prop('disabled', false);
            }
        });
    });

    // Display ACF translations
    function displayAcfTranslations(results, languagePrefix) {
        var html = '<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">';
        html += '<thead><tr>';
        html += '<th style="width: 150px;"><?php _e('Post', 'xf-translator'); ?></th>';
        html += '<th style="width: 200px;"><?php _e('Field', 'xf-translator'); ?></th>';
        html += '<th><?php _e('Original', 'xf-translator'); ?></th>';
        html += '<th><?php _e('Translated', 'xf-translator'); ?></th>';
        html += '<th style="width: 100px;"><?php _e('Actions', 'xf-translator'); ?></th>';
        html += '</tr></thead><tbody>';

        if (results.length === 0) {
            html += '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #666;"><?php _e('No translations found.', 'xf-translator'); ?></td></tr>';
        } else {
            results.forEach(function(postResult) {
                // Check if this is an options page result
                var isOptionsPage = (postResult.post_id === 0 || postResult.post_id === "0");

                postResult.fields.forEach(function(fieldResult) {
                    var rowId = 'acf-translation-' + (isOptionsPage ? 'options' : postResult.post_id) + '-' + fieldResult.field + '-' + languagePrefix;
                    html += '<tr id="' + rowId + '">';

                    if (isOptionsPage) {
                        html += '<td><strong>' + escapeHtml(fieldResult.option_name || 'Options Page') + '</strong><br><small>ACF Options</small></td>';
                    } else {
                        html += '<td><strong>' + escapeHtml(postResult.post_title) + '</strong><br><small>ID: ' + postResult.post_id + '</small></td>';
                    }

                    html += '<td><code>' + escapeHtml(fieldResult.field) + '</code><br><small>' + escapeHtml(fieldResult.field_label) + '</small></td>';
                    // Show original with HTML rendered (it's safe as it's from ACF)
                    var originalHtml = fieldResult.original || '';
                    html += '<td><div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #f0f0f1; border-radius: 3px;">' + originalHtml + '</div></td>';

                    if (fieldResult.success) {
                        if (isOptionsPage) {
                            // Options pages can't be edited inline yet - show translated HTML
                            var translatedHtml = fieldResult.translated || '';
                            html += '<td><div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #e8f5e8; border-radius: 3px;">' + translatedHtml + '</div><br><small style="color: #28a745;">✓ Translated and saved</small></td>';
                            html += '<td><span style="color: #28a745;">✓ Saved</span></td>';
                        } else {
                            // For posts, use textarea with escaped content for editing
                            html += '<td><textarea class="acf-translation-edit" data-post-id="' + postResult.post_id + '" data-field-key="' + escapeHtml(fieldResult.field) + '" data-language-prefix="' + languagePrefix + '" rows="3" style="width: 100%;">' + escapeHtml(fieldResult.translated || '') + '</textarea></td>';
                            html += '<td><button type="button" class="button button-small save-acf-translation-btn" data-row-id="' + rowId + '"><?php _e('Save', 'xf-translator'); ?></button></td>';
                        }
                    } else {
                        html += '<td><span style="color: #dc3232;"><?php _e('Error:', 'xf-translator'); ?> ' + escapeHtml(fieldResult.error || 'Translation failed') + '</span></td>';
                        html += '<td>-</td>';
                    }

                    html += '</tr>';
                });
            });
        }

        html += '</tbody></table>';
        $('#acf-translations-results-container').html(html);

        // Bind save buttons (only for post/page ACF fields, not options pages)
        $('.save-acf-translation-btn').on('click', function() {
            var rowId = $(this).data('row-id');
            var row = $('#' + rowId);
            var textarea = row.find('.acf-translation-edit');
            var postId = textarea.data('post-id');

            // Skip if this is an options page (postId will be 0 or missing)
            if (!postId || postId === '0') {
                return;
            }

            var fieldKey = textarea.data('field-key');
            var langPrefix = textarea.data('language-prefix');
            var translatedValue = textarea.val();

            var btn = $(this);
            btn.prop('disabled', true).text('<?php _e('Saving...', 'xf-translator'); ?>');

            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_save_acf_translation',
                    nonce: apiTranslator.nonce,
                    post_id: postId,
                    field_key: fieldKey,
                    language_prefix: langPrefix,
                    translated_value: translatedValue
                },
                success: function(response) {
                    if (response.success) {
                        btn.prop('disabled', false).text('<?php _e('Saved', 'xf-translator'); ?>').css('background', '#00a32a').css('color', '#fff');
                        setTimeout(function() {
                            btn.text('<?php _e('Save', 'xf-translator'); ?>').css('background', '').css('color', '');
                        }, 2000);
                    } else {
                        alert('Error saving: ' + (response.data.message || 'Unknown error'));
                        btn.prop('disabled', false).text('<?php _e('Save', 'xf-translator'); ?>');
                    }
                },
                error: function() {
                    alert('AJAX error occurred while saving');
                    btn.prop('disabled', false).text('<?php _e('Save', 'xf-translator'); ?>');
                }
            });
        });
    }

    // Apply language filter - load ACF translations from database via AJAX
    $('#apply-acf-filter-btn').on('click', function() {
        var langPrefix = $('#filter-acf-language').val();
        if (!langPrefix) {
            alert('<?php _e('Please select a language to filter.', 'xf-translator'); ?>');
            return;
        }

        loadAcfTranslationsFromDatabase(langPrefix);
    });

    // Load ACF translations from database via AJAX
    function loadAcfTranslationsFromDatabase(langPrefix) {
        $('#acf-translations-results-container').html('<p style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float: none; margin: 0;"></span> <?php _e('Loading translations...', 'xf-translator'); ?></p>');

        var fieldsToLoad = selectedAcfFields.length > 0 ? selectedAcfFields : <?php echo json_encode($translatable_acf_fields); ?>;

        if (fieldsToLoad.length === 0) {
            $('#acf-translations-results-container').html('<p style="color: #666;"><?php _e('No fields selected. Please scan and select fields first.', 'xf-translator'); ?></p>');
            return;
        }

        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_load_acf_translations',
                nonce: apiTranslator.nonce,
                language_prefix: langPrefix,
                fields: fieldsToLoad
            },
            success: function(response) {
                if (response.success && response.data.results) {
                    acfTranslationsData = response.data;
                    displayAcfTranslations(response.data.results, response.data.language_prefix);
                } else {
                    $('#acf-translations-results-container').html('<p style="color: #dc3232;"><?php _e('Error loading translations:', 'xf-translator'); ?> ' + (response.data.message || 'Unknown error') + '</p>');
                }
            },
            error: function() {
                $('#acf-translations-results-container').html('<p style="color: #dc3232;"><?php _e('AJAX error occurred while loading translations.', 'xf-translator'); ?></p>');
            }
        });
    }

    // Load translations on page load if filter is set
    <?php if ($selected_language_filter) : ?>
    $(document).ready(function() {
        $('#filter-acf-language').val('<?php echo esc_js($selected_language_filter); ?>');
        loadAcfTranslationsFromDatabase('<?php echo esc_js($selected_language_filter); ?>');
    });
    <?php endif; ?>

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});

// ACF Settings Management
$('#add-acf-settings-field-btn').on('click', function() {
    var fieldName = $('#new-acf-settings-field').val().trim();
    if (!fieldName) {
        alert('<?php _e('Please enter a field name.', 'xf-translator'); ?>');
        return;
    }

    var currentFields = $('#translatable_acf_fields_input').val().split(',').filter(function(f) { return f.trim() !== ''; });
    if (currentFields.indexOf(fieldName) !== -1) {
        alert('<?php _e('This field is already added.', 'xf-translator'); ?>');
        return;
    }

    currentFields.push(fieldName);
    $('#translatable_acf_fields_input').val(currentFields.join(','));
    updateAcfSettingsFieldsDisplay();
    $('#new-acf-settings-field').val('');
});

$(document).on('click', '.remove-acf-settings-field', function(e) {
    e.preventDefault();
    var fieldName = $(this).data('field');
    var currentFields = $('#translatable_acf_fields_input').val().split(',').filter(function(f) { return f.trim() !== ''; });
    var index = currentFields.indexOf(fieldName);
    if (index !== -1) {
        currentFields.splice(index, 1);
        $('#translatable_acf_fields_input').val(currentFields.join(','));
        updateAcfSettingsFieldsDisplay();
    }
});

function updateAcfSettingsFieldsDisplay() {
    var fields = $('#translatable_acf_fields_input').val().split(',').filter(function(f) { return f.trim() !== ''; });
    var html = '';

    if (fields.length > 0) {
        html += '<div style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px;">';
        fields.forEach(function(field) {
            html += '<span style="background: #2271b1; color: #fff; padding: 5px 10px; border-radius: 3px; font-size: 12px;">';
            html += escapeHtml(field);
            html += ' <a href="#" class="remove-acf-settings-field" data-field="' + escapeHtml(field) + '" style="color: #ffcccc; text-decoration: none;">×</a>';
            html += '</span>';
        });
        html += '</div>';
    }

    html += '<input type="text" id="new-acf-settings-field" placeholder="<?php esc_attr_e('Enter ACF field name...', 'xf-translator'); ?>" style="width: 200px;">';
    html += ' <button type="button" id="add-acf-settings-field-btn" class="button button-small"><?php _e('Add Field', 'xf-translator'); ?></button>';

    $('#acf-settings-fields-list').html(html);
}

$('#new-acf-settings-field').on('keypress', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        $('#add-acf-settings-field-btn').click();
    }
});
</script>
