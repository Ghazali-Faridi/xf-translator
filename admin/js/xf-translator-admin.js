/**
 * Admin JavaScript for API Translator
 *
 * @package API_Translator
 */

// Immediate check - will run even if jQuery isn't ready
console.log('XF Translator Admin JS: Script loaded');

(function($) {
    'use strict';
    
    console.log('XF Translator Admin JS: jQuery wrapper executing');
    console.log('XF Translator Admin JS: apiTranslator defined?', typeof apiTranslator !== 'undefined');
    if (typeof apiTranslator !== 'undefined') {
        console.log('XF Translator Admin JS: apiTranslator.ajaxUrl =', apiTranslator.ajaxUrl);
    }
    
    $(document).ready(function() {
        console.log('XF Translator Admin JS: Document ready');
        
        // Edit Language Modal
        $('.edit-language').on('click', function() {
            var index = $(this).data('index');
            var name = $(this).data('name');
            var prefix = $(this).data('prefix');
            var path = $(this).data('path') || '';
            var description = $(this).data('description') || '';
            
            $('#edit_language_index').val(index);
            $('#edit_language_name').val(name);
            $('#edit_language_prefix').val(prefix);
            $('#edit_language_path').val(path);
            $('#edit_language_description').val(description);
            
            // Clear any previous error messages
            $('#edit-prefix-error').hide().text('');
            $('#edit-path-error').hide().text('');
            
            $('#edit-language-modal').fadeIn();
        });
        
        // Close Modal
        $('.api-translator-modal-close, .cancel-edit').on('click', function() {
            $('#edit-language-modal').fadeOut();
            $('#error-detail-modal').fadeOut();
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(event) {
            if ($(event.target).hasClass('api-translator-modal')) {
                $(event.target).fadeOut();
            }
        });
        
        // Real-time prefix validation for add language form
        var prefixCheckTimeout;
        $('#language_prefix').on('input', function() {
            var $input = $(this);
            var prefix = $input.val().trim();
            var $error = $('#prefix-error');
            
            // Clear previous timeout
            clearTimeout(prefixCheckTimeout);
            
            // Hide error if empty
            if (!prefix) {
                $error.hide().text('');
                return;
            }
            
            // Debounce the check
            prefixCheckTimeout = setTimeout(function() {
                checkPrefixAvailability(prefix, null, $error);
            }, 500);
        });
        
        // Real-time prefix validation for edit language form
        var editPrefixCheckTimeout;
        $('#edit_language_prefix').on('input', function() {
            var $input = $(this);
            var prefix = $input.val().trim();
            var index = $('#edit_language_index').val();
            var $error = $('#edit-prefix-error');
            
            // Clear previous timeout
            clearTimeout(editPrefixCheckTimeout);
            
            // Hide error if empty
            if (!prefix) {
                $error.hide().text('');
                return;
            }
            
            // Debounce the check
            editPrefixCheckTimeout = setTimeout(function() {
                checkPrefixAvailability(prefix, index, $error);
            }, 500);
        });
        
        // Real-time path validation for add language form
        var pathCheckTimeout;
        $('#language_path').on('input', function() {
            var $input = $(this);
            var path = $input.val().trim();
            var prefix = $('#language_prefix').val().trim();
            var $error = $('#path-error');
            
            // Clear previous timeout
            clearTimeout(pathCheckTimeout);
            
            // If path is empty, it will use prefix, so check prefix instead
            if (!path && prefix) {
                path = prefix;
            }
            
            // Hide error if empty
            if (!path) {
                $error.hide().text('');
                return;
            }
            
            // Debounce the check
            pathCheckTimeout = setTimeout(function() {
                checkPathAvailability(path, prefix, null, $error);
            }, 500);
        });
        
        // Real-time path validation for edit language form
        var editPathCheckTimeout;
        $('#edit_language_path').on('input', function() {
            var $input = $(this);
            var path = $input.val().trim();
            var prefix = $('#edit_language_prefix').val().trim();
            var index = $('#edit_language_index').val();
            var $error = $('#edit-path-error');
            
            // Clear previous timeout
            clearTimeout(editPathCheckTimeout);
            
            // If path is empty, it will use prefix, so check prefix instead
            if (!path && prefix) {
                path = prefix;
            }
            
            // Hide error if empty
            if (!path) {
                $error.hide().text('');
                return;
            }
            
            // Debounce the check
            editPathCheckTimeout = setTimeout(function() {
                checkPathAvailability(path, prefix, index, $error);
            }, 500);
        });
        
        // Also check path when prefix changes (since path can fallback to prefix)
        $('#language_prefix').on('input', function() {
            var path = $('#language_path').val().trim();
            var prefix = $(this).val().trim();
            
            // If path is empty, trigger path validation with prefix value
            if (!path && prefix) {
                $('#language_path').trigger('input');
            }
        });
        
        $('#edit_language_prefix').on('input', function() {
            var path = $('#edit_language_path').val().trim();
            var prefix = $(this).val().trim();
            
            // If path is empty, trigger path validation with prefix value
            if (!path && prefix) {
                $('#edit_language_path').trigger('input');
            }
        });
        
        // Function to check prefix availability via AJAX
        function checkPrefixAvailability(prefix, excludeIndex, $errorElement) {
            if (!prefix) {
                $errorElement.hide().text('');
                return;
            }
            
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_check_prefix_availability',
                    prefix: prefix,
                    exclude_index: excludeIndex,
                    nonce: apiTranslator.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            $errorElement.hide().text('');
                        } else {
                            $errorElement.text('This prefix is already in use.').show();
                        }
                    } else {
                        // On error, don't show error message (might be network issue)
                        $errorElement.hide().text('');
                    }
                },
                error: function() {
                    // On AJAX error, don't show error message
                    $errorElement.hide().text('');
                }
            });
        }
        
        // Function to check path availability via AJAX
        function checkPathAvailability(path, prefix, excludeIndex, $errorElement) {
            if (!path) {
                $errorElement.hide().text('');
                return;
            }
            
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_check_path_availability',
                    path: path,
                    prefix: prefix,
                    exclude_index: excludeIndex,
                    nonce: apiTranslator.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            $errorElement.hide().text('');
                        } else {
                            $errorElement.text('This path is already in use by another language.').show();
                        }
                    } else {
                        // On error, don't show error message (might be network issue)
                        $errorElement.hide().text('');
                    }
                },
                error: function() {
                    // On AJAX error, don't show error message
                    $errorElement.hide().text('');
                }
            });
        }
        
        // Error Detail Modal
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
        
        // Form validation
        $('form').on('submit', function(e) {
            var $form = $(this);
            var action = $form.find('input[name="api_translator_action"]').val();
            
            // Validate language forms
            if (action === 'add_language' || action === 'edit_language') {
                var name = $form.find('input[name="language_name"]').val().trim();
                var prefix = $form.find('input[name="language_prefix"]').val().trim();
                
                if (!name || !prefix) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                if (prefix.length > 10) {
                    e.preventDefault();
                    alert('Language prefix must be 10 characters or less.');
                    return false;
                }
                
                // Check for prefix errors
                var $prefixError = action === 'add_language' ? $('#prefix-error') : $('#edit-prefix-error');
                if ($prefixError.is(':visible') && $prefixError.text().length > 0) {
                    e.preventDefault();
                    alert('Please fix the prefix error before submitting.');
                    return false;
                }
                
                // Check for path errors
                var $pathError = action === 'add_language' ? $('#path-error') : $('#edit-path-error');
                if ($pathError.is(':visible') && $pathError.text().length > 0) {
                    e.preventDefault();
                    alert('Please fix the path error before submitting.');
                    return false;
                }
            }
            
            // Validate exclude path
            if (action === 'add_exclude_path') {
                var path = $form.find('input[name="exclude_path"]').val().trim();
                
                if (!path) {
                    e.preventDefault();
                    alert('Please enter a path to exclude.');
                    return false;
                }
                
                if (!path.startsWith('/')) {
                    e.preventDefault();
                    alert('Path must start with a forward slash (/).');
                    return false;
                }
            }
            
            // Validate glossary term
            if (action === 'add_glossary_term') {
                var term = $form.find('input[name="glossary_term"]').val().trim();
                
                if (!term) {
                    e.preventDefault();
                    alert('Please enter a glossary term.');
                    return false;
                }
            }
        });
        
        // Bulk Scan & Translation
        var bulkScanInProgress = false;
        var bulkScanInterval = null;
        
        $('#api-translator-bulk-scan-btn').on('click', function() {
            if (bulkScanInProgress) {
                return;
            }
            
            if (!confirm('This will scan all posts and pages and queue untranslated content for translation. This may take a while. Continue?')) {
                return;
            }
            
            bulkScanInProgress = true;
            $(this).prop('disabled', true).text('Starting...');
            $('#api-translator-bulk-progress').show();
            $('#api-translator-bulk-messages').show().empty();
            
            // Start scan
            startBulkScan();
        });
        
        function startBulkScan() {
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'api_translator_bulk_scan',
                    nonce: apiTranslator.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateProgress(response.data);
                        
                        // Continue scanning if needed
                        if (response.data.status === 'scanning') {
                            continueBulkScan();
                        } else if (response.data.status === 'scan_complete') {
                            startBulkQueue();
                        }
                    } else {
                        showError(response.data.message || 'Scan failed');
                        resetBulkScan();
                    }
                },
                error: function() {
                    showError('AJAX error occurred');
                    resetBulkScan();
                }
            });
        }
        
        function continueBulkScan() {
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'api_translator_bulk_progress',
                    nonce: apiTranslator.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateProgress(response.data);
                        
                        if (response.data.status === 'scanning') {
                            // Continue scanning next page
                            setTimeout(continueBulkScan, 500);
                        } else if (response.data.status === 'scan_complete') {
                            // Start queuing
                            startBulkQueue();
                        }
                    } else {
                        showError(response.data.message || 'Progress check failed');
                        resetBulkScan();
                    }
                },
                error: function() {
                    showError('AJAX error occurred');
                    resetBulkScan();
                }
            });
        }
        
        function startBulkQueue() {
            addMessage('Scan complete! Starting to queue posts for translation...', 'success');
            queueNextPost(0);
        }
        
        function queueNextPost(index) {
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'api_translator_bulk_queue',
                    nonce: apiTranslator.nonce,
                    post_index: index
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'complete') {
                            // All done
                            addMessage(response.data.message, 'success');
                            $('#api-translator-bulk-status').text('Complete!');
                            updateProgressBar(100);
                            resetBulkScan();
                        } else {
                            // Queue next post
                            var postTitle = response.data.post_title || 'Unknown';
                            var queued = response.data.queued || 0;
                            var languages = response.data.languages || [];
                            
                            addMessage('Queued: ' + postTitle + ' (' + queued + ' languages: ' + languages.join(', ') + ')', 'info');
                            
                            // Update queued count
                            var currentQueued = parseInt($('#api-translator-queued').text()) || 0;
                            $('#api-translator-queued').text(currentQueued + queued);
                            
                            // Update progress
                            var total = response.data.total || 1;
                            var current = response.data.current || 0;
                            var progress = Math.round((current / total) * 100);
                            updateProgressBar(progress);
                            
                            $('#api-translator-current-post-title').text(postTitle);
                            $('#api-translator-bulk-current-post').show();
                            
                            // Queue next post (with small delay to prevent server overload)
                            setTimeout(function() {
                                queueNextPost(index + 1);
                            }, 300);
                        }
                    } else {
                        showError(response.data.message || 'Queue failed');
                        resetBulkScan();
                    }
                },
                error: function() {
                    showError('AJAX error occurred');
                    resetBulkScan();
                }
            });
        }
        
        function updateProgress(data) {
            $('#api-translator-bulk-status').text(getStatusText(data.status));
            $('#api-translator-total-posts').text(data.total_posts || 0);
            $('#api-translator-scanned').text(data.scanned || 0);
            $('#api-translator-needs-translation').text(data.needs_translation || 0);
            $('#api-translator-excluded').text(data.excluded || 0);
            
            if (data.total_posts > 0) {
                var progress = Math.round((data.scanned / data.total_posts) * 100);
                updateProgressBar(progress);
            }
        }
        
        function updateProgressBar(percent) {
            percent = Math.min(100, Math.max(0, percent));
            $('#api-translator-bulk-progress-bar').css('width', percent + '%');
            $('#api-translator-bulk-progress-text').text(percent + '%');
        }
        
        function getStatusText(status) {
            var statusTexts = {
                'scanning': 'Scanning posts...',
                'scan_complete': 'Scan complete, queuing translations...',
                'queuing': 'Queuing posts...',
                'complete': 'Complete!'
            };
            return statusTexts[status] || status;
        }
        
        function addMessage(message, type) {
            var $messages = $('#api-translator-bulk-messages');
            var className = type === 'success' ? 'color: green;' : type === 'error' ? 'color: red;' : 'color: #666;';
            var time = new Date().toLocaleTimeString();
            $messages.append('<div style="' + className + ' margin-bottom: 5px;">[' + time + '] ' + message + '</div>');
            $messages.scrollTop($messages[0].scrollHeight);
        }
        
        function showError(message) {
            addMessage('ERROR: ' + message, 'error');
            $('#api-translator-bulk-status').text('Error');
        }
        
        function resetBulkScan() {
            bulkScanInProgress = false;
            $('#api-translator-bulk-scan-btn').prop('disabled', false).text('Start Bulk Scan & Translation');
            if (bulkScanInterval) {
                clearInterval(bulkScanInterval);
                bulkScanInterval = null;
            }
        }
        
        // Translation Jobs Data Table (for Translation Queue tab)
        var translationJobsState = {
            currentPage: 1,
            perPage: 50,
            status: '',
            search: ''
        };
        
        function loadTranslationJobs() {
            console.log('XF Translator: loadTranslationJobs called');
            
            // Check if apiTranslator is available
            if (typeof apiTranslator === 'undefined') {
                console.error('XF Translator: apiTranslator object is not defined. Make sure the script is properly enqueued.');
                $('#jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232; font-weight: bold;">Error: JavaScript configuration not loaded. The apiTranslator object is missing. Please refresh the page or contact support.</td></tr>');
                $('#jobs-loading').hide();
                return;
            }
            
            console.log('XF Translator: Making AJAX request to', apiTranslator.ajaxUrl);
            
            var $loading = $('#jobs-loading');
            var $tableBody = $('#jobs-table-body');
            var $pagination = $('#jobs-pagination');
            
            $loading.show();
            $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 20px;">' + 
                '<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + 
                'Loading jobs...</td></tr>');
            
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_get_translation_jobs',
                    page: translationJobsState.currentPage,
                    per_page: translationJobsState.perPage,
                    status: translationJobsState.status,
                    search: translationJobsState.search,
                    nonce: apiTranslator.nonce
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response && response.success && response.data && response.data.jobs) {
                        renderTranslationJobsTable(response.data.jobs);
                        renderTranslationJobsPagination(response.data.pagination);
                    } else {
                        var errorMsg = 'Failed to load jobs';
                        if (response && response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response && !response.success) {
                            errorMsg = response.data && response.data.message ? response.data.message : 'Server returned an error';
                        }
                        console.error('XF Translator: Failed to load jobs', response);
                        $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232;">' + errorMsg + '</td></tr>');
                        $pagination.hide();
                    }
                },
                error: function(xhr, status, error) {
                    $loading.hide();
                    console.error('XF Translator: AJAX error loading jobs', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    var errorMsg = 'Error loading jobs. ';
                    if (xhr.status === 0) {
                        errorMsg += 'Network error or request blocked.';
                    } else if (xhr.status === 403) {
                        errorMsg += 'Permission denied. Please check your user permissions.';
                    } else if (xhr.status === 500) {
                        errorMsg += 'Server error. Please check the server logs.';
                    } else {
                        errorMsg += 'Status: ' + xhr.status + '. Please try again.';
                    }
                    $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232;">' + errorMsg + '</td></tr>');
                    $pagination.hide();
                }
            });
        }
        
        function renderTranslationJobsTable(jobs) {
            var $tbody = $('#jobs-table-body');
            
            if (jobs.length === 0) {
                $tbody.html('<tr><td colspan="6" style="text-align: center; padding: 20px;">No jobs found.</td></tr>');
                return;
            }
            
            var html = '';
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
                        escapeHtml(job.post_title) + '</a><br>' +
                        '<small style="color: #666;">Post ID: ' + job.parent_post_id + '</small>';
                } else {
                    postCell = escapeHtml(job.post_title) + '<br>' +
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
                    '<td>' + escapeHtml(job.lng) + '</td>' +
                    '<td><span style="padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px;">' + 
                        escapeHtml(job.type) + '</span></td>' +
                    '<td><span style="padding: 3px 8px; background: ' + job.status_color + '; color: #fff; border-radius: 3px; font-size: 11px; margin-right: 5px;">' + 
                        escapeHtml(job.status.charAt(0).toUpperCase() + job.status.slice(1)) + '</span>' + statusActions + '</td>' +
                    '<td>' + escapeHtml(job.created) + '</td>' +
                    '</tr>';
            });
            
            $tbody.html(html);
        }
        
        function renderTranslationJobsPagination(pagination) {
            var $pagination = $('#jobs-pagination');
            var $info = $('#jobs-pagination-info');
            var $prev = $('#jobs-prev-page');
            var $next = $('#jobs-next-page');
            var $pageNumbers = $('#jobs-page-numbers');
            
            if (pagination.total_pages <= 1) {
                $pagination.hide();
                return;
            }
            
            $pagination.show();
            
            var start = (pagination.current_page - 1) * pagination.per_page + 1;
            var end = Math.min(pagination.current_page * pagination.per_page, pagination.total_items);
            $info.text('Showing ' + start + ' - ' + end + ' of ' + pagination.total_items + ' jobs');
            
            $prev.prop('disabled', pagination.current_page <= 1);
            $next.prop('disabled', pagination.current_page >= pagination.total_pages);
            
            // Render page numbers (show max 5 pages)
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
        
        // Event handlers for Translation Queue
        $('#job-status-filter').on('change', function() {
            translationJobsState.status = $(this).val();
            translationJobsState.currentPage = 1;
            loadTranslationJobs();
        });
        
        var searchTimeout;
        $('#job-search').on('input', function() {
            clearTimeout(searchTimeout);
            var searchValue = $(this).val();
            searchTimeout = setTimeout(function() {
                translationJobsState.search = searchValue;
                translationJobsState.currentPage = 1;
                loadTranslationJobs();
            }, 500);
        });
        
        $('#clear-filters').on('click', function() {
            $('#job-status-filter').val('');
            $('#job-search').val('');
            translationJobsState.status = '';
            translationJobsState.search = '';
            translationJobsState.currentPage = 1;
            loadTranslationJobs();
        });
        
        $(document).on('click', '#jobs-prev-page', function() {
            if (translationJobsState.currentPage > 1) {
                translationJobsState.currentPage--;
                loadTranslationJobs();
            }
        });
        
        $(document).on('click', '#jobs-next-page', function() {
            translationJobsState.currentPage++;
            loadTranslationJobs();
        });
        
        $(document).on('click', '.jobs-page-btn', function() {
            translationJobsState.currentPage = parseInt($(this).data('page'));
            loadTranslationJobs();
        });
        
        // Load jobs on page load if we're on the queue tab
        // Use multiple initialization attempts to handle different loading scenarios
        var translationJobsInitAttempts = 0;
        var maxInitAttempts = 10;
        
        function initTranslationJobs() {
            translationJobsInitAttempts++;
            var $table = $('#translation-jobs-table');
            if ($table.length) {
                console.log('XF Translator: Found translation jobs table, initializing... (attempt ' + translationJobsInitAttempts + ')');
                if (typeof apiTranslator !== 'undefined') {
                    console.log('XF Translator: apiTranslator is defined, calling loadTranslationJobs');
                    loadTranslationJobs();
                } else {
                    if (translationJobsInitAttempts < maxInitAttempts) {
                        console.warn('XF Translator: apiTranslator not ready, retrying in 200ms...');
                        setTimeout(initTranslationJobs, 200);
                    } else {
                        console.error('XF Translator: Failed to initialize after ' + maxInitAttempts + ' attempts. apiTranslator is not defined.');
                        $('#jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232; font-weight: bold;">Error: JavaScript configuration failed to load. The apiTranslator object is missing. Please refresh the page.</td></tr>');
                        $('#jobs-loading').hide();
                    }
                }
            } else {
                console.log('XF Translator: Translation jobs table not found on page');
            }
        }
        
        // Try immediate initialization
        console.log('XF Translator: Attempting initial load of translation jobs');
        initTranslationJobs();
        
        // Also try after a short delay (for slow loading)
        setTimeout(function() {
            console.log('XF Translator: Retrying translation jobs load after 100ms');
            initTranslationJobs();
        }, 100);
        
        // And on window load as fallback
        $(window).on('load', function() {
            console.log('XF Translator: Window loaded, retrying translation jobs load');
            setTimeout(initTranslationJobs, 50);
        });
        
        // Timeout fallback - show error if nothing happens after 5 seconds
        setTimeout(function() {
            if ($('#jobs-table-body').html().indexOf('Loading jobs...') !== -1) {
                console.error('XF Translator: Timeout - jobs still loading after 5 seconds');
                $('#jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232; font-weight: bold;">Error: Request timed out. Please check your browser console for errors and refresh the page.</td></tr>');
                $('#jobs-loading').hide();
            }
        }, 5000);
        
        // Existing Jobs Data Table (for Existing Post Queue tab)
        var existingJobsState = {
            currentPage: 1,
            perPage: 50,
            status: '',
            search: ''
        };
        
        function loadExistingJobs() {
            console.log('XF Translator: loadExistingJobs called');
            
            // Check if apiTranslator is available
            if (typeof apiTranslator === 'undefined') {
                console.error('XF Translator: apiTranslator object is not defined. Make sure the script is properly enqueued.');
                $('#existing-jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232; font-weight: bold;">Error: JavaScript configuration not loaded. The apiTranslator object is missing. Please refresh the page or contact support.</td></tr>');
                $('#existing-jobs-loading').hide();
                return;
            }
            
            console.log('XF Translator: Making AJAX request to', apiTranslator.ajaxUrl);
            
            var $loading = $('#existing-jobs-loading');
            var $tableBody = $('#existing-jobs-table-body');
            var $pagination = $('#existing-jobs-pagination');
            
            $loading.show();
            $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 20px;">' + 
                '<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + 
                'Loading jobs...</td></tr>');
            
            $.ajax({
                url: apiTranslator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'xf_get_existing_jobs',
                    page: existingJobsState.currentPage,
                    per_page: existingJobsState.perPage,
                    status: existingJobsState.status,
                    search: existingJobsState.search,
                    nonce: apiTranslator.nonce
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response && response.success && response.data && response.data.jobs) {
                        renderExistingJobsTable(response.data.jobs);
                        renderExistingJobsPagination(response.data.pagination);
                    } else {
                        var errorMsg = 'Failed to load jobs';
                        if (response && response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response && !response.success) {
                            errorMsg = response.data && response.data.message ? response.data.message : 'Server returned an error';
                        }
                        console.error('XF Translator: Failed to load existing jobs', response);
                        $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232;">' + errorMsg + '</td></tr>');
                        $pagination.hide();
                    }
                },
                error: function(xhr, status, error) {
                    $loading.hide();
                    console.error('XF Translator: AJAX error loading existing jobs', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    var errorMsg = 'Error loading jobs. ';
                    if (xhr.status === 0) {
                        errorMsg += 'Network error or request blocked.';
                    } else if (xhr.status === 403) {
                        errorMsg += 'Permission denied. Please check your user permissions.';
                    } else if (xhr.status === 500) {
                        errorMsg += 'Server error. Please check the server logs.';
                    } else {
                        errorMsg += 'Status: ' + xhr.status + '. Please try again.';
                    }
                    $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232;">' + errorMsg + '</td></tr>');
                    $pagination.hide();
                }
            });
        }
        
        function renderExistingJobsTable(jobs) {
            var $tbody = $('#existing-jobs-table-body');
            
            if (jobs.length === 0) {
                $tbody.html('<tr><td colspan="6" style="text-align: center; padding: 20px;">No jobs found.</td></tr>');
                return;
            }
            
            var html = '';
            jobs.forEach(function(job) {
                var postCell = '';
                if (job.post_edit_link !== '#') {
                    postCell = '<a href="' + job.post_edit_link + '" target="_blank">' + 
                        escapeHtml(job.post_title) + '</a><br>' +
                        '<small style="color: #666;">Post ID: ' + job.parent_post_id + '</small>';
                } else {
                    postCell = escapeHtml(job.post_title) + '<br>' +
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
                    '<td>' + escapeHtml(job.lng) + '</td>' +
                    '<td><span style="padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 11px;">' + 
                        escapeHtml(job.type) + '</span></td>' +
                    '<td><span style="padding: 3px 8px; background: ' + job.status_color + '; color: #fff; border-radius: 3px; font-size: 11px; margin-right: 5px;">' + 
                        escapeHtml(job.status.charAt(0).toUpperCase() + job.status.slice(1)) + '</span>' + statusActions + '</td>' +
                    '<td>' + escapeHtml(job.created) + '</td>' +
                    '</tr>';
            });
            
            $tbody.html(html);
        }
        
        function renderExistingJobsPagination(pagination) {
            var $pagination = $('#existing-jobs-pagination');
            var $info = $('#existing-jobs-pagination-info');
            var $prev = $('#existing-jobs-prev-page');
            var $next = $('#existing-jobs-next-page');
            var $pageNumbers = $('#existing-jobs-page-numbers');
            
            if (pagination.total_pages <= 1) {
                $pagination.hide();
                return;
            }
            
            $pagination.show();
            
            var start = (pagination.current_page - 1) * pagination.per_page + 1;
            var end = Math.min(pagination.current_page * pagination.per_page, pagination.total_items);
            $info.text('Showing ' + start + ' - ' + end + ' of ' + pagination.total_items + ' jobs');
            
            $prev.prop('disabled', pagination.current_page <= 1);
            $next.prop('disabled', pagination.current_page >= pagination.total_pages);
            
            // Render page numbers (show max 5 pages)
            var html = '';
            var startPage = Math.max(1, pagination.current_page - 2);
            var endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            if (startPage > 1) {
                html += '<button type="button" class="button existing-jobs-page-btn" data-page="1">1</button>';
                if (startPage > 2) {
                    html += '<span>...</span>';
                }
            }
            
            for (var i = startPage; i <= endPage; i++) {
                if (i === pagination.current_page) {
                    html += '<button type="button" class="button button-primary" disabled>' + i + '</button>';
                } else {
                    html += '<button type="button" class="button existing-jobs-page-btn" data-page="' + i + '">' + i + '</button>';
                }
            }
            
            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) {
                    html += '<span>...</span>';
                }
                html += '<button type="button" class="button existing-jobs-page-btn" data-page="' + pagination.total_pages + '">' + pagination.total_pages + '</button>';
            }
            
            $pageNumbers.html(html);
        }
        
        // Event handlers for Existing Post Queue
        $('#existing-job-status-filter').on('change', function() {
            existingJobsState.status = $(this).val();
            existingJobsState.currentPage = 1;
            loadExistingJobs();
        });
        
        var existingSearchTimeout;
        $('#existing-job-search').on('input', function() {
            clearTimeout(existingSearchTimeout);
            var searchValue = $(this).val();
            existingSearchTimeout = setTimeout(function() {
                existingJobsState.search = searchValue;
                existingJobsState.currentPage = 1;
                loadExistingJobs();
            }, 500);
        });
        
        $('#clear-existing-filters').on('click', function() {
            $('#existing-job-status-filter').val('');
            $('#existing-job-search').val('');
            existingJobsState.status = '';
            existingJobsState.search = '';
            existingJobsState.currentPage = 1;
            loadExistingJobs();
        });
        
        $(document).on('click', '#existing-jobs-prev-page', function() {
            if (existingJobsState.currentPage > 1) {
                existingJobsState.currentPage--;
                loadExistingJobs();
            }
        });
        
        $(document).on('click', '#existing-jobs-next-page', function() {
            existingJobsState.currentPage++;
            loadExistingJobs();
        });
        
        $(document).on('click', '.existing-jobs-page-btn', function() {
            existingJobsState.currentPage = parseInt($(this).data('page'));
            loadExistingJobs();
        });
        
        // Load jobs on page load if we're on the existing queue tab
        // Use multiple initialization attempts to handle different loading scenarios
        var existingJobsInitAttempts = 0;
        var maxExistingInitAttempts = 10;
        
        function initExistingJobs() {
            existingJobsInitAttempts++;
            var $table = $('#existing-translation-jobs-table');
            if ($table.length) {
                console.log('XF Translator: Found existing jobs table, initializing... (attempt ' + existingJobsInitAttempts + ')');
                if (typeof apiTranslator !== 'undefined') {
                    console.log('XF Translator: apiTranslator is defined, calling loadExistingJobs');
                    loadExistingJobs();
                } else {
                    if (existingJobsInitAttempts < maxExistingInitAttempts) {
                        console.warn('XF Translator: apiTranslator not ready, retrying in 200ms...');
                        setTimeout(initExistingJobs, 200);
                    } else {
                        console.error('XF Translator: Failed to initialize after ' + maxExistingInitAttempts + ' attempts. apiTranslator is not defined.');
                        $('#existing-jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232; font-weight: bold;">Error: JavaScript configuration failed to load. The apiTranslator object is missing. Please refresh the page.</td></tr>');
                        $('#existing-jobs-loading').hide();
                    }
                }
            } else {
                console.log('XF Translator: Existing jobs table not found on page');
            }
        }
        
        // Try immediate initialization
        console.log('XF Translator: Attempting initial load of existing jobs');
        initExistingJobs();
        
        // Also try after a short delay (for slow loading)
        setTimeout(function() {
            console.log('XF Translator: Retrying existing jobs load after 100ms');
            initExistingJobs();
        }, 100);
        
        // And on window load as fallback
        $(window).on('load', function() {
            console.log('XF Translator: Window loaded, retrying existing jobs load');
            setTimeout(initExistingJobs, 50);
        });
        
        // Timeout fallback - show error if nothing happens after 5 seconds
        setTimeout(function() {
            if ($('#existing-jobs-table-body').html().indexOf('Loading jobs...') !== -1) {
                console.error('XF Translator: Timeout - existing jobs still loading after 5 seconds');
                $('#existing-jobs-table-body').html('<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3232; font-weight: bold;">Error: Request timed out. Please check your browser console for errors and refresh the page.</td></tr>');
                $('#existing-jobs-loading').hide();
            }
        }, 5000);
        
        // Utility function to escape HTML
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
        }
        
    });
    
})(jQuery);

