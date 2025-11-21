<?php
/**
 * Test Translation Tab
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get all published posts (only English/original posts, exclude translated posts)
$posts = get_posts(array(
    'post_type' => 'any',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'meta_query' => array(
        array(
            'key' => '_xf_translator_language',
            'compare' => 'NOT EXISTS'
        )
    )
));

$languages = $settings->get('languages', array());
$selected_model = $settings->get('selected_model', 'gpt-4o');
$brand_tone = $settings->get('brand_tone', '');

// Available models
$openai_models = array(
    'gpt-4o' => 'GPT-4o',
    'gpt-4o-mini' => 'GPT-4o Mini',
    'gpt-4-turbo' => 'GPT-4 Turbo',
    'gpt-4' => 'GPT-4',
    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
    'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16k'
);

$deepseek_models = array(
    'deepseek-chat' => 'DeepSeek Chat',
    'deepseek-coder' => 'DeepSeek Coder',
    'deepseek-chat-32k' => 'DeepSeek Chat 32k'
);
?>

<div class="api-translator-section">
    <h2><?php _e('Test Translation', 'xf-translator'); ?></h2>
    <p><?php _e('Test different models and prompts on a single post before translating all posts. Compare results to find the best settings for your content.', 'xf-translator'); ?></p>
    
    <?php settings_errors('api_translator_messages'); ?>
    
    <div id="test-translation-form" style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ddd;">
        <form id="xf-test-translation-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="test_post_id"><?php _e('Select Post', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <select id="test_post_id" name="post_id" class="regular-text" required style="width: 100%; max-width: 500px;">
                            <option value=""><?php _e('-- Select a Post --', 'xf-translator'); ?></option>
                            <?php foreach ($posts as $post) : ?>
                                <option value="<?php echo esc_attr($post->ID); ?>">
                                    <?php echo esc_html($post->post_title); ?> (<?php echo esc_html($post->post_type); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select a post to test translation on.', 'xf-translator'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_target_language"><?php _e('Target Language', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <select id="test_target_language" name="target_language" class="regular-text" required style="width: 100%; max-width: 300px;">
                            <option value=""><?php _e('-- Select Language --', 'xf-translator'); ?></option>
                            <?php foreach ($languages as $language) : ?>
                                <option value="<?php echo esc_attr($language['name']); ?>">
                                    <?php echo esc_html($language['name']); ?> (<?php echo esc_html($language['prefix']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php _e('Test Models', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <fieldset style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">
                            <legend style="font-weight: bold;"><?php _e('OpenAI Models', 'xf-translator'); ?></legend>
                            <?php foreach ($openai_models as $model_value => $model_label) : ?>
                                <label style="display: block; margin: 5px 0;">
                                    <input type="checkbox" name="test_models[]" value="<?php echo esc_attr($model_value); ?>" class="test-model-checkbox">
                                    <?php echo esc_html($model_label); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <fieldset style="border: 1px solid #ddd; padding: 10px;">
                            <legend style="font-weight: bold;"><?php _e('DeepSeek Models', 'xf-translator'); ?></legend>
                            <?php foreach ($deepseek_models as $model_value => $model_label) : ?>
                                <label style="display: block; margin: 5px 0;">
                                    <input type="checkbox" name="test_models[]" value="<?php echo esc_attr($model_value); ?>" class="test-model-checkbox">
                                    <?php echo esc_html($model_label); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description"><?php _e('Select one or more models to test. Results will be compared side-by-side.', 'xf-translator'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="test_prompt_template"><?php _e('Prompt Template', 'xf-translator'); ?></label>
                    </th>
                    <td>
                        <select id="test_prompt_template" name="prompt_template" class="regular-text" style="width: 100%; max-width: 300px;">
                            <option value="current"><?php _e('Use Current Brand Tone', 'xf-translator'); ?></option>
                            <option value="custom"><?php _e('Test Custom Prompt', 'xf-translator'); ?></option>
                        </select>
                        <div id="custom-prompt-container" style="display: none; margin-top: 10px;">
                            <textarea id="test_custom_prompt" name="custom_prompt" rows="5" class="large-text" placeholder="<?php esc_attr_e('Enter your custom prompt template here. Use {content} for the content placeholder and {lng} for target language.', 'xf-translator'); ?>"><?php echo esc_textarea($brand_tone); ?></textarea>
                            <p class="description"><?php _e('Use {content} for the content placeholder and {lng} for target language. Use {glossy} for glossary terms if needed.', 'xf-translator'); ?></p>
                        </div>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" id="test-translation-btn" class="button button-primary">
                    <?php _e('Test Translation', 'xf-translator'); ?>
                </button>
                <span id="test-loading" style="display: none; margin-left: 10px;">
                    <span class="spinner is-active" style="float: none; margin: 0;"></span>
                    <?php _e('Testing...', 'xf-translator'); ?>
                </span>
            </p>
        </form>
    </div>
    
    <!-- Original Content Preview -->
    <div id="original-content-preview" style="display: none; margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">
        <h3><?php _e('Original Content', 'xf-translator'); ?></h3>
        <div id="original-content-display"></div>
    </div>
    
    <!-- Translation Results -->
    <div id="translation-results" style="display: none; margin-top: 30px;">
        <h3><?php _e('Translation Results', 'xf-translator'); ?></h3>
        <div id="results-container"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide custom prompt field
    $('#test_prompt_template').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#custom-prompt-container').show();
        } else {
            $('#custom-prompt-container').hide();
        }
    });
    
    // Handle form submission
    $('#xf-test-translation-form').on('submit', function(e) {
        e.preventDefault();
        
        var postId = $('#test_post_id').val();
        var targetLanguage = $('#test_target_language').val();
        var selectedModels = $('input[name="test_models[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        var promptTemplate = $('#test_prompt_template').val();
        var customPrompt = $('#test_custom_prompt').val();
        
        if (!postId || !targetLanguage || selectedModels.length === 0) {
            alert('<?php _e('Please select a post, target language, and at least one model.', 'xf-translator'); ?>');
            return;
        }
        
        // Show loading
        $('#test-loading').show();
        $('#test-translation-btn').prop('disabled', true);
        $('#translation-results').hide();
        $('#original-content-preview').hide();
        $('#results-container').empty();
        
        // Get original post content first
        $.ajax({
            url: xfTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_get_post_content',
                nonce: xfTranslator.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    // Display original content
                    var originalHtml = '<p><strong>Title:</strong> ' + escapeHtml(response.data.title) + '</p>';
                    originalHtml += '<p><strong>Content:</strong> ' + escapeHtml(response.data.content.substring(0, 500)) + (response.data.content.length > 500 ? '...' : '') + '</p>';
                    if (response.data.excerpt) {
                        originalHtml += '<p><strong>Excerpt:</strong> ' + escapeHtml(response.data.excerpt) + '</p>';
                    }
                    $('#original-content-display').html(originalHtml);
                    $('#original-content-preview').show();
                    
                    // Now test translations for each model sequentially
                    testModelsSequentially(selectedModels, postId, targetLanguage, promptTemplate, customPrompt, 0);
                } else {
                    alert('<?php _e('Error loading post content.', 'xf-translator'); ?>');
                    $('#test-loading').hide();
                    $('#test-translation-btn').prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php _e('Error loading post content.', 'xf-translator'); ?>');
                $('#test-loading').hide();
                $('#test-translation-btn').prop('disabled', false);
            }
        });
    });
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function testModelsSequentially(models, postId, targetLanguage, promptTemplate, customPrompt, index) {
        if (index >= models.length) {
            // All models tested
            $('#test-loading').hide();
            $('#test-translation-btn').prop('disabled', false);
            return;
        }
        
        var model = models[index];
        
        $.ajax({
            url: xfTranslator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'xf_test_translation',
                nonce: xfTranslator.nonce,
                post_id: postId,
                target_language: targetLanguage,
                model: model,
                prompt_template: promptTemplate,
                custom_prompt: customPrompt
            },
            success: function(response) {
                if (response.success) {
                    displayTestResult(model, response.data);
                } else {
                    displayTestError(model, response.data.message || '<?php _e('Translation failed', 'xf-translator'); ?>');
                }
                
                // Test next model
                testModelsSequentially(models, postId, targetLanguage, promptTemplate, customPrompt, index + 1);
            },
            error: function() {
                displayTestError(model, '<?php _e('API Error', 'xf-translator'); ?>');
                testModelsSequentially(models, postId, targetLanguage, promptTemplate, customPrompt, index + 1);
            }
        });
    }
    
    function displayTestResult(model, data) {
        var modelLabel = getModelLabel(model);
        var resultHtml = '<div class="test-result-box" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #0073aa;">';
        resultHtml += '<h4 style="margin-top: 0;">' + escapeHtml(modelLabel) + '</h4>';
        resultHtml += '<p><strong>Title:</strong> ' + escapeHtml(data.translated_title || 'N/A') + '</p>';
        resultHtml += '<p><strong>Content:</strong> ' + escapeHtml(data.translated_content ? (data.translated_content.substring(0, 300) + (data.translated_content.length > 300 ? '...' : '')) : 'N/A') + '</p>';
        if (data.translated_excerpt) {
            resultHtml += '<p><strong>Excerpt:</strong> ' + escapeHtml(data.translated_excerpt) + '</p>';
        }
        if (data.tokens_used) {
            resultHtml += '<p><small style="color: #666;"><strong>Tokens Used:</strong> ' + data.tokens_used + '</small></p>';
        }
        if (data.response_time) {
            resultHtml += '<p><small style="color: #666;"><strong>Response Time:</strong> ' + data.response_time + 's</small></p>';
        }
        resultHtml += '<button class="button button-small view-full-translation" data-model="' + escapeHtml(model) + '" data-title="' + escapeHtml(data.translated_title || '') + '" data-content="' + escapeHtml(data.translated_content || '') + '" data-excerpt="' + escapeHtml(data.translated_excerpt || '') + '" style="margin-top: 10px;"><?php _e('View Full Translation', 'xf-translator'); ?></button>';
        resultHtml += '<button class="button button-small save-as-default" data-model="' + escapeHtml(model) + '" style="margin-top: 10px; margin-left: 5px;"><?php _e('Save as Default', 'xf-translator'); ?></button>';
        resultHtml += '</div>';
        
        $('#results-container').append(resultHtml);
        $('#translation-results').show();
    }
    
    function displayTestError(model, message) {
        var modelLabel = getModelLabel(model);
        var errorHtml = '<div class="test-result-box" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #dc3232; border-left: 4px solid #dc3232;">';
        errorHtml += '<h4 style="margin-top: 0; color: #dc3232;">' + escapeHtml(modelLabel) + ' - Error</h4>';
        errorHtml += '<p style="color: #dc3232;">' + escapeHtml(message) + '</p>';
        errorHtml += '</div>';
        
        $('#results-container').append(errorHtml);
        $('#translation-results').show();
    }
    
    function getModelLabel(model) {
        var labels = {
            'gpt-4o': 'GPT-4o',
            'gpt-4o-mini': 'GPT-4o Mini',
            'gpt-4-turbo': 'GPT-4 Turbo',
            'gpt-4': 'GPT-4',
            'gpt-3.5-turbo': 'GPT-3.5 Turbo',
            'gpt-3.5-turbo-16k': 'GPT-3.5 Turbo 16k',
            'deepseek-chat': 'DeepSeek Chat',
            'deepseek-coder': 'DeepSeek Coder',
            'deepseek-chat-32k': 'DeepSeek Chat 32k'
        };
        return labels[model] || model;
    }
    
    // Handle view full translation
    $(document).on('click', '.view-full-translation', function() {
        var model = $(this).data('model');
        var title = $(this).data('title');
        var content = $(this).data('content');
        var excerpt = $(this).data('excerpt');
        
        var fullHtml = '<div style="max-width: 800px; max-height: 600px; overflow-y: auto;">';
        fullHtml += '<h3>' + escapeHtml(getModelLabel(model)) + ' - Full Translation</h3>';
        fullHtml += '<p><strong>Title:</strong><br>' + escapeHtml(title) + '</p>';
        fullHtml += '<p><strong>Content:</strong><br>' + escapeHtml(content) + '</p>';
        if (excerpt) {
            fullHtml += '<p><strong>Excerpt:</strong><br>' + escapeHtml(excerpt) + '</p>';
        }
        fullHtml += '</div>';
        
        // Create modal
        var modal = $('<div class="xf-translation-modal" style="display: block; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);"><div style="background-color: #fff; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 900px; position: relative;"><span class="xf-modal-close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>' + fullHtml + '</div></div>');
        $('body').append(modal);
        
        $('.xf-modal-close').on('click', function() {
            modal.remove();
        });
        
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('xf-translation-modal')) {
                modal.remove();
            }
        });
    });
    
    // Handle save as default
    $(document).on('click', '.save-as-default', function() {
        var model = $(this).data('model');
        if (confirm('<?php _e('Save this model as your default translation model?', 'xf-translator'); ?>')) {
            $.ajax({
                url: xfTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_save_default_model',
                    nonce: xfTranslator.nonce,
                    model: model
                },
                success: function(response) {
                    if (response.success) {
                        alert('<?php _e('Default model saved successfully!', 'xf-translator'); ?>');
                    } else {
                        alert('<?php _e('Failed to save default model.', 'xf-translator'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('Error saving default model.', 'xf-translator'); ?>');
                }
            });
        }
    });
    
    // Clear results when post changes
    $('#test_post_id').on('change', function() {
        $('#results-container').empty();
        $('#translation-results').hide();
        $('#original-content-preview').hide();
    });
});
</script>

