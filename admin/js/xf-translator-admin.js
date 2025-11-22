/**
 * Admin JavaScript for API Translator
 *
 * @package API_Translator
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Edit Language Modal
        $('.edit-language').on('click', function() {
            var index = $(this).data('index');
            var name = $(this).data('name');
            var prefix = $(this).data('prefix');
            
            $('#edit_language_index').val(index);
            $('#edit_language_name').val(name);
            $('#edit_language_prefix').val(prefix);
            
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
        
    });
    
})(jQuery);

