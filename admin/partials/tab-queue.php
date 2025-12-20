<?php
/**
 * Translation Queue Dashboard Tab
 *
 * @package Xf_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'xf_translate_queue';

// Get queue statistics (only NEW and EDIT types, exclude OLD)
$pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending' AND type IN ('NEW', 'EDIT')");
$processing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'processing' AND type IN ('NEW', 'EDIT')");
$completed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed' AND type IN ('NEW', 'EDIT')");
$failed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed' AND type IN ('NEW', 'EDIT')");

// Jobs are now loaded via AJAX for better performance and pagination
?>

<div class="api-translator-section">
    <h2><?php _e('Queue Statistics', 'xf-translator'); ?></h2>
    
    <div class="api-translator-stats" style="display: flex; gap: 20px; margin: 20px 0;">
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #f0ad4e;">
            <h3 style="margin: 0; font-size: 32px; color: #f0ad4e;"><?php echo number_format($pending_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Pending Jobs', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #0073aa;">
            <h3 style="margin: 0; font-size: 32px; color: #0073aa;"><?php echo number_format($processing_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Processing', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #46b450;">
            <h3 style="margin: 0; font-size: 32px; color: #46b450;"><?php echo number_format($completed_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Completed', 'xf-translator'); ?></p>
        </div>
        <div class="api-translator-stat-box" style="flex: 1; padding: 15px; background: #fff; border: 1px solid #ddd; border-left: 4px solid #dc3232;">
            <h3 style="margin: 0; font-size: 32px; color: #dc3232;"><?php echo number_format($failed_count); ?></h3>
            <p style="margin: 5px 0 0 0;"><?php _e('Failed', 'xf-translator'); ?></p>
        </div>
    </div>
    
    <?php if ($processing_count > 0) : ?>
        <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa;">
            <h3 style="margin-top: 0;"><?php _e('Reset Stuck Processing Jobs', 'xf-translator'); ?></h3>
            <p style="margin: 10px 0; color: #666;">
                <?php _e('If any jobs are stuck in "processing" status for more than 5 minutes, you can reset them back to "pending" so they can be retried.', 'xf-translator'); ?>
            </p>
            <form method="post" action="" style="margin-top: 10px;">
                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                <input type="hidden" name="api_translator_action" value="reset_stuck_processing">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset all processing jobs that have been stuck for more than 5 minutes?', 'xf-translator')); ?>');">
                    <?php _e('Reset Stuck Processing Jobs', 'xf-translator'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
    
    <?php if ($failed_count > 0) : ?>
        <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #dc3232;">
            <h3 style="margin-top: 0;"><?php _e('Reset Failed Queue', 'xf-translator'); ?></h3>
            <p style="margin: 10px 0; color: #666;">
                <?php _e('Reset all failed translation jobs back to "pending" status so they can be retried by the background queue processor.', 'xf-translator'); ?>
            </p>
            <form method="post" action="" style="margin-top: 10px;">
                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                <input type="hidden" name="api_translator_action" value="reset_failed_queue">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(sprintf(__('Are you sure you want to reset all %d failed job(s) back to pending status?', 'xf-translator'), $failed_count)); ?>');">
                    <?php _e('Reset Failed Queue', 'xf-translator'); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
    
    <?php if ($pending_count > 0) : ?>
        <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #0073aa;">
            <h3 style="margin-top: 0;"><?php _e('Process Pending Jobs', 'xf-translator'); ?></h3>
            <!-- <p><?php _e('Click the button below to process the next pending translation job. You can also access the processing script directly or set it up as a cron job.', 'xf-translator'); ?></p> -->
            <form method="post" action="" style="margin-top: 10px;">
                <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                <input type="hidden" name="api_translator_action" value="process_queue">
                <!-- <?php submit_button(__('Process Next Pending Job', 'xf-translator'), 'primary', 'submit', false); ?> -->
            </form>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <?php _e('Processing URL:', 'xf-translator'); ?> 
                <code><?php echo esc_url(plugin_dir_url(dirname(dirname(__FILE__))) . 'process-translation.php'); ?></code>
            </p>
        </div>
    <?php endif; ?>
    
    <?php settings_errors('api_translator_messages'); ?>
</div>

<div class="api-translator-section">
    <h2><?php _e('Translation Jobs', 'xf-translator'); ?></h2>
    
    <!-- Filters and Search -->
    <div style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <div>
                <label for="job-status-filter" style="display: block; margin-bottom: 5px; font-weight: bold;">
                    <?php _e('Filter by Status:', 'xf-translator'); ?>
                </label>
                <select id="job-status-filter" style="min-width: 150px;">
                    <option value=""><?php _e('All Statuses', 'xf-translator'); ?></option>
                    <option value="pending"><?php _e('Pending', 'xf-translator'); ?></option>
                    <option value="processing"><?php _e('Processing', 'xf-translator'); ?></option>
                    <option value="completed"><?php _e('Completed', 'xf-translator'); ?></option>
                    <option value="failed"><?php _e('Failed', 'xf-translator'); ?></option>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label for="job-search" style="display: block; margin-bottom: 5px; font-weight: bold;">
                    <?php _e('Search by Post Name:', 'xf-translator'); ?>
                </label>
                <input type="text" id="job-search" placeholder="<?php esc_attr_e('Enter post name...', 'xf-translator'); ?>" style="width: 100%; max-width: 300px;">
            </div>
            <div style="align-self: flex-end;">
                <button type="button" id="clear-filters" class="button" style="margin-top: 20px;">
                    <?php _e('Clear Filters', 'xf-translator'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Loading indicator -->
    <div id="jobs-loading" style="text-align: center; padding: 20px; display: none;">
        <span class="spinner is-active" style="float: none; margin: 0;"></span>
        <p><?php _e('Loading jobs...', 'xf-translator'); ?></p>
    </div>
    
    <!-- Jobs Table Container -->
    <div id="jobs-table-container">
        <table class="wp-list-table widefat fixed striped" id="translation-jobs-table">
            <thead>
                <tr>
                    <th scope="col"><?php _e('ID', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Post', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Language', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Type', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Status', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Created', 'xf-translator'); ?></th>
                </tr>
            </thead>
            <tbody id="jobs-table-body">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">
                        <?php _e('Loading jobs...', 'xf-translator'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="jobs-pagination" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <span id="jobs-pagination-info"></span>
            </div>
            <div style="display: flex; gap: 5px;">
                <button type="button" id="jobs-prev-page" class="button" disabled>
                    <?php _e('Previous', 'xf-translator'); ?>
                </button>
                <span id="jobs-page-numbers" style="display: flex; align-items: center; gap: 5px;"></span>
                <button type="button" id="jobs-next-page" class="button" disabled>
                    <?php _e('Next', 'xf-translator'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inline Script for Translation Jobs Table (Fallback if external JS doesn't load) -->
<script type="text/javascript">
(function() {
    'use strict';
    
    // Check if jQuery is available
    if (typeof jQuery === 'undefined') {
        console.error('XF Translator: jQuery is not loaded');
        return;
    }
    
    var $ = jQuery;
    
    // Wait for DOM to be ready
    $(document).ready(function() {
        console.log('XF Translator Queue Tab: Inline script executing');
        
        // Check if apiTranslator is defined (from external script)
        if (typeof apiTranslator === 'undefined') {
            console.warn('XF Translator: apiTranslator not defined, creating fallback');
            // Create fallback apiTranslator object
            window.apiTranslator = {
                ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('xf_translator_ajax')); ?>'
            };
            console.log('XF Translator: Fallback apiTranslator created', window.apiTranslator);
        }
        
        // Initialize translation jobs if table exists
        if ($('#translation-jobs-table').length) {
            console.log('XF Translator: Found translation jobs table, will initialize via external script or fallback');
            
            // If external script hasn't loaded after 1 second, use fallback
            setTimeout(function() {
                if ($('#jobs-table-body').html().indexOf('Loading jobs...') !== -1 && typeof loadTranslationJobs === 'undefined') {
                    console.log('XF Translator: External script not loaded, using inline fallback');
                    loadTranslationJobsInline();
                }
            }, 1000);
        }
        
        // State management
        var translationJobsState = {
            currentPage: 1,
            perPage: 50,
            status: '',
            search: ''
        };
        
        function escapeHtml(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        function loadTranslationJobsInline() {
            $('#jobs-loading').show();
            $('#jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px;">Loading jobs...</td></tr>');
            
            $.ajax({
                url: window.apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_get_translation_jobs',
                    page: translationJobsState.currentPage,
                    per_page: translationJobsState.perPage,
                    status: translationJobsState.status,
                    search: translationJobsState.search,
                    nonce: window.apiTranslator.nonce
                },
                success: function(response) {
                    $('#jobs-loading').hide();
                    console.log('XF Translator: AJAX response received', response);
                    
                    if (response && response.success && response.data && response.data.jobs) {
                        renderTranslationJobsTable(response.data.jobs);
                        if (response.data.pagination) {
                            renderTranslationJobsPagination(response.data.pagination);
                        }
                    } else {
                        var errorMsg = response && response.data && response.data.message ? response.data.message : 'Failed to load jobs';
                        $('#jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232;">' + errorMsg + '</td></tr>');
                        $('#jobs-pagination').hide();
                    }
                },
                error: function(xhr, status, error) {
                    $('#jobs-loading').hide();
                    console.error('XF Translator: AJAX error', {status: status, error: error, xhr: xhr});
                    $('#jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232;">Error loading jobs. Status: ' + xhr.status + '</td></tr>');
                    $('#jobs-pagination').hide();
                }
            });
        }
        
        function renderTranslationJobsTable(jobs) {
            var html = '';
            if (jobs.length === 0) {
                html = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No jobs found.</td></tr>';
            } else {
                jobs.forEach(function(job) {
                    var postCell = '';
                    if (job.translated_post_title && job.status === 'completed') {
                        postCell = '<a href="' + job.translated_post_link + '" target="_blank" style="font-weight: bold;">' + 
                            escapeHtml(job.translated_post_title) + '</a><br>' +
                            '<small style="color: #666;">Translated Post ID: ' + job.translated_post_id;
                        if (job.post_title) {
                            postCell += ' | Original: <a href="' + job.post_edit_link + '" target="_blank" style="color: #666;">' + 
                                escapeHtml(job.post_title) + '</a>';
                        }
                        postCell += '</small>';
                    } else if (job.post_edit_link !== '#') {
                        postCell = '<a href="' + job.post_edit_link + '" target="_blank">' + 
                            escapeHtml(job.post_title || 'Post not found') + '</a><br>' +
                            '<small style="color: #666;">Post ID: ' + job.parent_post_id + '</small>';
                    } else {
                        postCell = escapeHtml(job.post_title || 'Post not found') + '<br>' +
                            '<small style="color: #666;">Post ID: ' + job.parent_post_id + '</small>';
                    }
                    
                    var statusActions = '';
                    if (job.status === 'failed') {
                        statusActions = '<div style="margin-top: 5px;">';
                        if (job.error_message) {
                            statusActions += '<button type="button" class="button button-small view-error-detail" ' +
                                'data-error-message="' + escapeHtml(job.error_message) + '" ' +
                                'data-queue-id="' + job.id + '" style="margin-right: 5px; font-size: 11px;">View Detail</button>';
                        }
                        statusActions += '<form method="post" action="" style="display: inline-block; margin: 0;">' +
                            '<input type="hidden" name="api_translator_action" value="retry_queue_entry">' +
                            '<input type="hidden" name="queue_entry_id" value="' + job.id + '">' +
                            '<input type="hidden" name="api_translator_nonce" value="' + $('input[name="api_translator_nonce"]').val() + '">' +
                            '<button type="submit" class="button button-small" style="background: #46b450; color: #fff; border-color: #46b450; font-size: 11px;">Retry</button>' +
                            '</form></div>';
                    }
                    
                    html += '<tr>' +
                        '<td><strong>#' + job.id + '</strong></td>' +
                        '<td>' + postCell + '</td>' +
                        '<td>' + escapeHtml(job.lng || '') + '</td>' +
                        '<td><span style="padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px;">' + 
                            escapeHtml(job.type || 'NEW') + '</span></td>' +
                        '<td><span style="padding: 3px 8px; background: ' + job.status_color + '; color: #fff; border-radius: 3px; font-size: 11px; margin-right: 5px;">' + 
                            escapeHtml(job.status ? job.status.charAt(0).toUpperCase() + job.status.slice(1) : '') + '</span>' + statusActions + '</td>' +
                        '<td>' + escapeHtml(job.created || '') + '</td>' +
                        '</tr>';
                });
            }
            $('#jobs-table-body').html(html);
        }
        
        function renderTranslationJobsPagination(pagination) {
            var $pagination = $('#jobs-pagination');
            var $info = $('#jobs-pagination-info');
            var $prev = $('#jobs-prev-page');
            var $next = $('#jobs-next-page');
            var $pageNumbers = $('#jobs-page-numbers');
            
            if (!pagination || pagination.total_pages <= 1) {
                $pagination.hide();
                return;
            }
            
            $pagination.show();
            
            var start = (pagination.current_page - 1) * pagination.per_page + 1;
            var end = Math.min(pagination.current_page * pagination.per_page, pagination.total_items);
            $info.text('Showing ' + start + ' - ' + end + ' of ' + pagination.total_items + ' jobs');
            
            $prev.prop('disabled', pagination.current_page <= 1);
            $next.prop('disabled', pagination.current_page >= pagination.total_pages);
            
            // Render page numbers
            var html = '';
            var startPage = Math.max(1, pagination.current_page - 2);
            var endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            if (startPage > 1) {
                html += '<button type="button" class="button jobs-page-btn" data-page="1">1</button>';
                if (startPage > 2) {
                    html += '<span>...</span>';
                }
            }
            
            for (var i = startPage; i <= endPage; i++) {
                if (i === pagination.current_page) {
                    html += '<button type="button" class="button button-primary" disabled>' + i + '</button>';
                } else {
                    html += '<button type="button" class="button jobs-page-btn" data-page="' + i + '">' + i + '</button>';
                }
            }
            
            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) {
                    html += '<span>...</span>';
                }
                html += '<button type="button" class="button jobs-page-btn" data-page="' + pagination.total_pages + '">' + pagination.total_pages + '</button>';
            }
            
            $pageNumbers.html(html);
        }
        
        // Event handlers for filters and pagination
        $('#job-status-filter').on('change', function() {
            translationJobsState.status = $(this).val();
            translationJobsState.currentPage = 1;
            loadTranslationJobsInline();
        });
        
        var searchTimeout;
        $('#job-search').on('input', function() {
            clearTimeout(searchTimeout);
            var searchValue = $(this).val();
            searchTimeout = setTimeout(function() {
                translationJobsState.search = searchValue;
                translationJobsState.currentPage = 1;
                loadTranslationJobsInline();
            }, 500);
        });
        
        $('#clear-filters').on('click', function() {
            $('#job-status-filter').val('');
            $('#job-search').val('');
            translationJobsState.status = '';
            translationJobsState.search = '';
            translationJobsState.currentPage = 1;
            loadTranslationJobsInline();
        });
        
        $(document).on('click', '#jobs-prev-page', function() {
            if (translationJobsState.currentPage > 1) {
                translationJobsState.currentPage--;
                loadTranslationJobsInline();
            }
        });
        
        $(document).on('click', '#jobs-next-page', function() {
            translationJobsState.currentPage++;
            loadTranslationJobsInline();
        });
        
        $(document).on('click', '.jobs-page-btn', function() {
            translationJobsState.currentPage = parseInt($(this).data('page'));
            loadTranslationJobsInline();
        });
        
        // Error Detail Modal Handler
        $(document).on('click', '.view-error-detail', function() {
            var errorMessage = $(this).data('error-message');
            var queueId = $(this).data('queue-id');
            
            if (!errorMessage || errorMessage.trim() === '') {
                errorMessage = 'No error message available.';
            }
            
            $('#error-detail-content').html(
                '<div style="margin-bottom: 15px;"><strong>Queue Entry ID:</strong> #' + queueId + '</div>' +
                '<div style="margin-bottom: 10px;"><strong>Error Message:</strong></div>' +
                '<div>' + $('<div>').text(errorMessage).html() + '</div>'
            );
            
            $('#error-detail-modal').fadeIn();
        });
        
        // Close modal handlers
        $(document).on('click', '.api-translator-modal-close', function() {
            $('#error-detail-modal').fadeOut();
        });
        
        // Close modal when clicking outside
        $(document).on('click', '#error-detail-modal', function(e) {
            if ($(e.target).hasClass('api-translator-modal')) {
                $(this).fadeOut();
            }
        });
    });
})();
</script>

<!-- <div class="api-translator-section">
    <h2><?php _e('Recent Queue Activity', 'xf-translator'); ?></h2>
    
    <?php if (empty($recent_jobs)) : ?>
        <p><?php _e('No recent activity.', 'xf-translator'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('ID', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Post', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Language', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Type', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Status', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Error/Logs', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Translated Post', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Actions', 'xf-translator'); ?></th>
                    <th scope="col"><?php _e('Created', 'xf-translator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_jobs as $job) : ?>
                    <?php
                    $post = get_post($job['parent_post_id']);
                    $post_title = $post ? $post->post_title : __('Post not found', 'xf-translator');
                    $post_edit_link = $post ? get_edit_post_link($job['parent_post_id']) : '#';
                    
                    $translated_post = $job['translated_post_id'] ? get_post($job['translated_post_id']) : null;
                    $translated_post_title = $translated_post ? $translated_post->post_title : '';
                    $translated_post_edit_link = $translated_post ? get_edit_post_link($job['translated_post_id']) : '#';
                    
                    $status_color = '#46b450'; // green
                    if ($job['status'] === 'failed') {
                        $status_color = '#dc3232'; // red
                    } elseif ($job['status'] === 'processing') {
                        $status_color = '#0073aa'; // blue
                    }
                    
                    // Get error message if failed
                    $error_message = isset($job['error_message']) ? $job['error_message'] : '';
                    
                    // Get API log (always try to retrieve, even if post doesn't exist)
                    $api_log = null;
                    $log_meta_key = '_xf_translator_api_log_' . $job['id'];
                    $api_log_json = get_post_meta($job['parent_post_id'], $log_meta_key, true);
                    
                    if ($api_log_json) {
                        $api_log = json_decode($api_log_json, true);
                        // If JSON decode failed, silently handle it (don't log on every page load)
                        // Only log if WP_DEBUG is enabled and it's a critical issue
                        if ($api_log === null && json_last_error() !== JSON_ERROR_NONE) {
                            // Silently set to null - corrupted log data, not a critical error
                            $api_log = null;
                            // Only log once per queue entry to avoid spam (check if we've logged this before)
                            $error_logged_key = '_xf_translator_log_error_logged_' . $job['id'];
                            if (!get_post_meta($job['parent_post_id'], $error_logged_key, true) && defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('XF Translator: Failed to decode API log JSON for queue ID ' . $job['id'] . ': ' . json_last_error_msg());
                                error_log('XF Translator: Raw JSON (first 500 chars): ' . substr($api_log_json, 0, 500));
                                // Mark as logged to avoid repeated logs
                                update_post_meta($job['parent_post_id'], $error_logged_key, '1');
                            }
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <strong>#<?php echo esc_html($job['id']); ?></strong>
                        </td>
                        <td>
                            <?php if ($post) : ?>
                                <a href="<?php echo esc_url($post_edit_link); ?>" target="_blank">
                                    <?php echo esc_html($post_title); ?>
                                </a>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['parent_post_id']); ?></small>
                            <?php else : ?>
                                <?php echo esc_html($post_title); ?>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['parent_post_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($job['lng']); ?>
                        </td>
                        <td>
                            <span style="padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html($job['type'] ?: 'NEW'); ?>
                            </span>
                        </td>
                        <td>
                            <span style="padding: 3px 8px; background: <?php echo esc_attr($status_color); ?>; color: #fff; border-radius: 3px; font-size: 11px;">
                                <?php echo esc_html(ucfirst($job['status'])); ?>
                            </span>
                        </td>
                        <td style="max-width: 300px;">
                            <?php if ($error_message) : ?>
                                <div style="margin-bottom: 10px;">
                                    <strong style="color: #dc3232;"><?php _e('Error:', 'xf-translator'); ?></strong>
                                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                        <?php echo esc_html($error_message); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($api_log) : ?>
                                <details style="margin-top: 10px;" open>
                                    <summary style="cursor: pointer; color: #0073aa; font-size: 12px; font-weight: bold;">
                                        <?php _e('View API Log', 'xf-translator'); ?>
                                    </summary>
                                    <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 11px; max-height: 400px; overflow-y: auto;">
                                        <?php if (isset($api_log['endpoint'])) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <strong><?php _e('Endpoint:', 'xf-translator'); ?></strong> 
                                                <code><?php echo esc_html($api_log['endpoint']); ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['model'])) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <strong><?php _e('Model:', 'xf-translator'); ?></strong> 
                                                <code><?php echo esc_html($api_log['model']); ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <div style="margin-bottom: 10px;">
                                            <strong><?php _e('Request Body:', 'xf-translator'); ?></strong>
                                            <pre style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; overflow-x: auto; font-size: 10px; max-height: 250px; white-space: pre-wrap; word-wrap: break-word;"><?php 
                                                $request_body = isset($api_log['request']) ? $api_log['request'] : array();
                                                echo esc_html(json_encode($request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                                            ?></pre>
                                        </div>
                                        <?php if (isset($api_log['error'])) : ?>
                                            <div style="margin-bottom: 10px; padding: 8px; background: #ffe6e6; border-left: 3px solid #dc3232; border-radius: 3px;">
                                                <strong style="color: #dc3232;"><?php _e('Error:', 'xf-translator'); ?></strong>
                                                <div style="margin-top: 5px; color: #666;">
                                                    <?php echo esc_html($api_log['error']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['response_code'])) : ?>
                                            <div style="margin-bottom: 10px;">
                                                <strong><?php _e('Response Code:', 'xf-translator'); ?></strong> 
                                                <code style="padding: 2px 6px; background: <?php echo $api_log['response_code'] == 200 ? '#d4edda' : '#f8d7da'; ?>; border-radius: 3px;">
                                                    <?php echo esc_html($api_log['response_code']); ?>
                                                </code>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['response_body']) && !empty($api_log['response_body'])) : ?>
                                            <div>
                                                <strong><?php _e('Response Body:', 'xf-translator'); ?></strong>
                                                <pre style="background: #fff; padding: 8px; border: 1px solid #ddd; border-radius: 3px; overflow-x: auto; font-size: 10px; max-height: 250px; white-space: pre-wrap; word-wrap: break-word;"><?php 
                                                    $response_body = $api_log['response_body'];
                                                    // Try to pretty print if it's JSON
                                                    $decoded = json_decode($response_body, true);
                                                    if ($decoded) {
                                                        echo esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                                    } else {
                                                        echo esc_html($response_body);
                                                    }
                                                ?></pre>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($api_log['timestamp'])) : ?>
                                            <div style="margin-top: 10px; font-size: 10px; color: #999;">
                                                <?php _e('Timestamp:', 'xf-translator'); ?> <?php echo esc_html($api_log['timestamp']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php else : ?>
                                <?php if ($job['status'] === 'completed' || $job['status'] === 'failed' || $job['status'] === 'processing') : ?>
                                    <div style="font-size: 12px; color: #999;">
                                        <?php _e('No API log available', 'xf-translator'); ?>
                                        <?php if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) : ?>
                                            <br><small style="color: #666;">
                                                Debug: Post ID: <?php echo esc_html($job['parent_post_id']); ?>, 
                                                Queue ID: <?php echo esc_html($job['id']); ?>, 
                                                Meta Key: _xf_translator_api_log_<?php echo esc_html($job['id']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($translated_post) : ?>
                                <a href="<?php echo esc_url($translated_post_edit_link); ?>" target="_blank">
                                    <?php echo esc_html($translated_post_title); ?>
                                </a>
                                <br>
                                <small style="color: #666;"><?php _e('Post ID:', 'xf-translator'); ?> <?php echo esc_html($job['translated_post_id']); ?></small>
                            <?php else : ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($job['status'] === 'failed') : ?>
                                <form method="post" action="" style="display: inline-block; margin: 0;">
                                    <?php wp_nonce_field('api_translator_settings', 'api_translator_nonce'); ?>
                                    <input type="hidden" name="api_translator_action" value="retry_queue_entry">
                                    <input type="hidden" name="queue_entry_id" value="<?php echo esc_attr($job['id']); ?>">
                                    <button type="submit" class="button button-small" style="background: #46b450; color: #fff; border-color: #46b450;">
                                        <?php _e('Retry', 'xf-translator'); ?>
                                    </button>
                                </form>
                            <?php else : ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($job['created']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div> -->

<!-- Error Detail Modal -->
<div id="error-detail-modal" class="api-translator-modal" style="display: none;">
    <div class="api-translator-modal-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
        <div class="api-translator-modal-header" style="padding: 15px 20px; background: #dc3232; color: #fff; border-bottom: 1px solid #ddd;">
            <h2 style="margin: 0; font-size: 18px;"><?php _e('Translation Error Details', 'xf-translator'); ?></h2>
            <span class="api-translator-modal-close" style="float: right; cursor: pointer; font-size: 24px; line-height: 1; opacity: 0.8;">&times;</span>
        </div>
        <div class="api-translator-modal-body" style="padding: 20px;">
            <div id="error-detail-content">
                <p style="margin: 0; color: #666;"><?php _e('Loading error details...', 'xf-translator'); ?></p>
            </div>
        </div>
        <div class="api-translator-modal-footer" style="padding: 15px 20px; background: #f9f9f9; border-top: 1px solid #ddd; text-align: right;">
            <button type="button" class="button api-translator-modal-close"><?php _e('Close', 'xf-translator'); ?></button>
        </div>
    </div>
</div>

<style>
.api-translator-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.api-translator-modal-content {
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    position: relative;
}

.api-translator-modal-close {
    cursor: pointer;
}

.api-translator-modal-close:hover {
    opacity: 1 !important;
}

#error-detail-content {
    word-wrap: break-word;
    white-space: pre-wrap;
    font-family: monospace;
    font-size: 13px;
    line-height: 1.6;
    color: #333;
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border-left: 4px solid #dc3232;
}
</style>

