<?php
/**
 * User Meta Translation Tab - Unified Bulk Translation Interface
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$languages = $settings->get('languages', array());
$selected_language_filter = isset($_GET['filter_language']) ? sanitize_text_field($_GET['filter_language']) : '';
$translatable_user_meta = $settings->get_translatable_user_meta_fields();
?>

<div class="api-translator-section">
    <h2><?php _e('User Meta Translation', 'xf-translator'); ?></h2>
    <p><?php _e('Scan user meta fields, select fields to translate, choose a language, and translate all selected fields for all users at once. You can then view and edit the translations below.', 'xf-translator'); ?></p>
    
    <?php settings_errors('api_translator_messages'); ?>
    
    <!-- Step 1: Scan User Meta Fields -->
    <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('Scan User Meta Fields', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('Discover available user meta fields in your database.', 'xf-translator'); ?></p>
        
        <div style="margin: 20px 0;">
            <button type="button" id="scan-user-meta-btn" class="button button-primary">
                <?php _e('Scan User Meta Fields', 'xf-translator'); ?>
            </button>
            <span id="scan-user-meta-loading" style="display: none; margin-left: 10px;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <?php _e('Scanning...', 'xf-translator'); ?>
            </span>
        </div>
        
        <div id="user-meta-fields-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fff; margin-top: 15px; display: none;">
            <p id="user-meta-fields-empty" style="color: #666;">
                <?php _e('No fields found. Click "Scan User Meta Fields" to discover available meta fields.', 'xf-translator'); ?>
            </p>
            <div id="user-meta-fields-list"></div>
        </div>
    </div>
    
    <!-- Step 2: Select Fields and Language -->
    <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('Select Fields and Language', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('Select which fields to translate and choose the target language.', 'xf-translator'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="selected-meta-fields"><?php _e('Selected Fields', 'xf-translator'); ?></label>
                </th>
                <td>
                    <div id="selected-fields-display" style="min-height: 50px; padding: 10px; border: 1px solid #ddd; background: #fff; border-radius: 3px;">
                        <p style="color: #666; margin: 0;"><?php _e('No fields selected. Select fields from the scan results above.', 'xf-translator'); ?></p>
                    </div>
                    <input type="hidden" id="selected-meta-fields" name="selected_meta_fields" value="">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="translate-language"><?php _e('Target Language', 'xf-translator'); ?></label>
                </th>
                <td>
                    <select id="translate-language" name="translate_language" class="regular-text">
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
            <button type="button" id="translate-bulk-btn" class="button button-primary" disabled>
                <?php _e('Translate All Selected Fields for All Users', 'xf-translator'); ?>
            </button>
            <span id="translate-bulk-loading" style="display: none; margin-left: 10px;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <?php _e('Translating... This may take a while.', 'xf-translator'); ?>
            </span>
        </p>
    </div>
    
    <!-- Step 3: View and Edit Translations -->
    <div class="api-translator-section" style="margin-top: 30px; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
        <h3><?php _e('View and Edit Translations', 'xf-translator'); ?></h3>
        <p class="description"><?php _e('View all translations and edit them as needed. Use the language filter to view translations for a specific language.', 'xf-translator'); ?></p>
        
        <div style="margin: 20px 0;">
            <label for="filter-language" style="font-weight: bold; margin-right: 10px;">
                <?php _e('Filter by Language:', 'xf-translator'); ?>
            </label>
            <select id="filter-language" name="filter_language" style="min-width: 200px;">
                <option value=""><?php _e('All Languages', 'xf-translator'); ?></option>
                <?php foreach ($languages as $lang) : ?>
                    <option value="<?php echo esc_attr($lang['prefix']); ?>" <?php selected($selected_language_filter, $lang['prefix']); ?>>
                        <?php echo esc_html($lang['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="apply-filter-btn" class="button" style="margin-left: 10px;">
                <?php _e('Apply Filter', 'xf-translator'); ?>
            </button>
        </div>
        
        <div id="translations-results-container">
            <p style="color: #666;"><?php _e('Translations will appear here after you translate fields. Use the language filter above to view specific translations.', 'xf-translator'); ?></p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var selectedFields = [];
    var allScannedFields = [];
    var translationsData = {};
    
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
                    allScannedFields = response.data.fields;
                    displayScannedFields(response.data.fields);
                    $('#user-meta-fields-container').show();
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
    
    // Display scanned fields
    function displayScannedFields(fields) {
        var html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px;">';
        fields.forEach(function(field) {
            var checked = selectedFields.indexOf(field.key) !== -1 ? 'checked' : '';
            var label = field.label || field.key;
            var sample = field.sample ? ' <span style="color: #666; font-size: 11px;">(' + escapeHtml(field.sample.substring(0, 50)) + '...)</span>' : '';
            var keyDisplay = field.key !== label ? ' <span style="color: #999; font-size: 10px; font-family: monospace;">(' + escapeHtml(field.key) + ')</span>' : '';
            html += '<label style="display: block; padding: 8px; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; background: #fff;">';
            html += '<input type="checkbox" class="user-meta-field-checkbox" value="' + escapeHtml(field.key) + '" ' + checked + ' style="margin-right: 8px;"> ';
            html += '<strong>' + escapeHtml(label) + '</strong>' + keyDisplay + sample;
            html += '</label>';
        });
        html += '</div>';
        $('#user-meta-fields-list').html(html);
        $('#user-meta-fields-empty').hide();
        
        // Bind checkbox change events
        $('.user-meta-field-checkbox').on('change', function() {
            var field = $(this).val();
            if ($(this).is(':checked')) {
                if (selectedFields.indexOf(field) === -1) {
                    selectedFields.push(field);
                }
            } else {
                var index = selectedFields.indexOf(field);
                if (index !== -1) {
                    selectedFields.splice(index, 1);
                }
            }
            updateSelectedFieldsDisplay();
            updateTranslateButton();
        });
    }
    
    // Update selected fields display
    function updateSelectedFieldsDisplay() {
        $('#selected-meta-fields').val(selectedFields.join(','));
        
        if (selectedFields.length === 0) {
            $('#selected-fields-display').html('<p style="color: #666; margin: 0;"><?php _e('No fields selected. Select fields from the scan results above.', 'xf-translator'); ?></p>');
            return;
        }
        
        var html = '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
        selectedFields.forEach(function(field) {
            var fieldObj = allScannedFields.find(f => f.key === field);
            var label = fieldObj ? (fieldObj.label || field) : field;
            html += '<span style="background: #2271b1; color: #fff; padding: 5px 10px; border-radius: 3px; font-size: 12px;">';
            html += escapeHtml(label);
            html += ' <span style="cursor: pointer; margin-left: 5px;" class="remove-field" data-field="' + escapeHtml(field) + '">×</span>';
            html += '</span>';
        });
        html += '</div>';
        $('#selected-fields-display').html(html);
        
        // Bind remove buttons
        $('.remove-field').on('click', function() {
            var field = $(this).data('field');
            var index = selectedFields.indexOf(field);
            if (index !== -1) {
                selectedFields.splice(index, 1);
                $('.user-meta-field-checkbox[value="' + escapeHtml(field) + '"]').prop('checked', false);
                updateSelectedFieldsDisplay();
                updateTranslateButton();
            }
        });
    }
    
    // Update translate button state
    function updateTranslateButton() {
        var hasFields = selectedFields.length > 0;
        var hasLanguage = $('#translate-language').val() !== '';
        $('#translate-bulk-btn').prop('disabled', !hasFields || !hasLanguage);
    }
    
    $('#translate-language').on('change', function() {
        updateTranslateButton();
    });
    
    // Translate bulk
    $('#translate-bulk-btn').on('click', function() {
        if (selectedFields.length === 0 || $('#translate-language').val() === '') {
            alert('Please select fields and a language first.');
            return;
        }
        
        if (!confirm('This will translate all selected fields for ALL users. This may take a while. Continue?')) {
            return;
        }
        
        $('#translate-bulk-loading').show();
        $('#translate-bulk-btn').prop('disabled', true);
        
        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_translate_user_meta_bulk',
                nonce: apiTranslator.nonce,
                fields: selectedFields,
                language_prefix: $('#translate-language').val()
            },
            success: function(response) {
                if (response.success && response.data.results) {
                    translationsData = response.data;
                    displayTranslations(response.data.results, response.data.language_prefix);
                    $('#translate-bulk-loading').hide();
                    $('#translate-bulk-btn').prop('disabled', false);
                } else {
                    alert('Error translating: ' + (response.data.message || 'Unknown error'));
                    $('#translate-bulk-loading').hide();
                    $('#translate-bulk-btn').prop('disabled', false);
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
                console.error('Translation AJAX Error:', xhr, status, error);
                $('#translate-bulk-loading').hide();
                $('#translate-bulk-btn').prop('disabled', false);
            }
        });
    });
    
    // Display translations
    function displayTranslations(results, languagePrefix) {
        var html = '<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">';
        html += '<thead><tr>';
        html += '<th style="width: 150px;"><?php _e('User', 'xf-translator'); ?></th>';
        html += '<th style="width: 200px;"><?php _e('Field', 'xf-translator'); ?></th>';
        html += '<th><?php _e('Original', 'xf-translator'); ?></th>';
        html += '<th><?php _e('Translated', 'xf-translator'); ?></th>';
        html += '<th style="width: 100px;"><?php _e('Actions', 'xf-translator'); ?></th>';
        html += '</tr></thead><tbody>';
        
        if (results.length === 0) {
            html += '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #666;"><?php _e('No translations found.', 'xf-translator'); ?></td></tr>';
        } else {
            results.forEach(function(userResult) {
                userResult.fields.forEach(function(fieldResult) {
                    var rowId = 'translation-' + userResult.user_id + '-' + fieldResult.field + '-' + languagePrefix;
                    html += '<tr id="' + rowId + '">';
                    html += '<td><strong>' + escapeHtml(userResult.user_name) + '</strong><br><small>' + escapeHtml(userResult.user_email) + '</small></td>';
                    html += '<td><code>' + escapeHtml(fieldResult.field) + '</code><br><small>' + escapeHtml(fieldResult.field_label) + '</small></td>';
                    html += '<td><div style="max-height: 100px; overflow-y: auto; padding: 5px; background: #f0f0f1; border-radius: 3px;">' + escapeHtml(fieldResult.original) + '</div></td>';
                    
                    if (fieldResult.success) {
                        html += '<td><textarea class="translation-edit" data-user-id="' + userResult.user_id + '" data-meta-key="' + escapeHtml(fieldResult.field) + '" data-language-prefix="' + languagePrefix + '" rows="3" style="width: 100%;">' + escapeHtml(fieldResult.translated) + '</textarea></td>';
                        html += '<td><button type="button" class="button button-small save-translation-btn" data-row-id="' + rowId + '"><?php _e('Save', 'xf-translator'); ?></button></td>';
                    } else {
                        html += '<td><span style="color: #dc3232;"><?php _e('Error:', 'xf-translator'); ?> ' + escapeHtml(fieldResult.error || 'Translation failed') + '</span></td>';
                        html += '<td>-</td>';
                    }
                    
                    html += '</tr>';
                });
            });
        }
        
        html += '</tbody></table>';
        $('#translations-results-container').html(html);
        
        // Bind save buttons
        $('.save-translation-btn').on('click', function() {
            var rowId = $(this).data('row-id');
            var row = $('#' + rowId);
            var textarea = row.find('.translation-edit');
            var userId = textarea.data('user-id');
            var metaKey = textarea.data('meta-key');
            var langPrefix = textarea.data('language-prefix');
            var translatedValue = textarea.val();
            
            var btn = $(this);
            btn.prop('disabled', true).text('<?php _e('Saving...', 'xf-translator'); ?>');
            
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_save_user_meta_translation',
                    nonce: apiTranslator.nonce,
                    user_id: userId,
                    meta_key: metaKey,
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
    
    // Apply language filter - load translations from database via AJAX
    $('#apply-filter-btn').on('click', function() {
        var langPrefix = $('#filter-language').val();
        if (!langPrefix) {
            alert('<?php _e('Please select a language to filter.', 'xf-translator'); ?>');
            return;
        }
        
        loadTranslationsFromDatabase(langPrefix);
    });
    
    // Load translations from database via AJAX
    function loadTranslationsFromDatabase(langPrefix) {
        $('#translations-results-container').html('<p style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float: none; margin: 0;"></span> <?php _e('Loading translations...', 'xf-translator'); ?></p>');
        
        var fieldsToLoad = selectedFields.length > 0 ? selectedFields : <?php echo json_encode($translatable_user_meta); ?>;
        
        if (fieldsToLoad.length === 0) {
            $('#translations-results-container').html('<p style="color: #666;"><?php _e('No fields selected. Please scan and select fields first.', 'xf-translator'); ?></p>');
            return;
        }
        
        $.ajax({
            url: apiTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_load_user_meta_translations',
                nonce: apiTranslator.nonce,
                language_prefix: langPrefix,
                fields: fieldsToLoad
            },
            success: function(response) {
                if (response.success && response.data.results) {
                    translationsData = response.data;
                    displayTranslations(response.data.results, response.data.language_prefix);
                } else {
                    $('#translations-results-container').html('<p style="color: #dc3232;"><?php _e('Error loading translations:', 'xf-translator'); ?> ' + (response.data.message || 'Unknown error') + '</p>');
                }
            },
            error: function() {
                $('#translations-results-container').html('<p style="color: #dc3232;"><?php _e('AJAX error occurred while loading translations.', 'xf-translator'); ?></p>');
            }
        });
    }
    
    // Load translations on page load if filter is set
    <?php if ($selected_language_filter) : ?>
    $(document).ready(function() {
        $('#filter-language').val('<?php echo esc_js($selected_language_filter); ?>');
        loadTranslationsFromDatabase('<?php echo esc_js($selected_language_filter); ?>');
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
</script>
