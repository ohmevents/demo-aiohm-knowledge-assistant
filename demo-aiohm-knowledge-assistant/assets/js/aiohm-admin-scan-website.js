jQuery(document).ready(function($) {
    const nonce = aiohm_scan_website_ajax.nonce;
    let noticeTimer;

    // Function to get friendly file type display names
    function getFileTypeDisplay(mimeType) {
        const typeMap = {
            'application/pdf': 'PDF',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'WORD',
            'application/msword': 'WORD',
            'text/plain': 'TEXT',
            'application/json': 'JSON',
            'text/csv': 'CSV',
            'text/markdown': 'MD',
            'application/vnd.ms-excel': 'EXCEL',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'EXCEL'
        };
        
        return typeMap[mimeType] || mimeType.split('/')[1]?.toUpperCase() || 'FILE';
    }

    // Function to get CSS class for file types
    function getFileTypeClass(mimeType) {
        const classMap = {
            'application/pdf': 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'word',
            'application/msword': 'word',
            'text/plain': 'text',
            'application/json': 'text',
            'text/csv': 'text',
            'text/markdown': 'text'
        };
        
        return classMap[mimeType] || 'file';
    }
    
    // Enhanced admin notice function with accessibility features
    function showAdminNotice(message, type = 'success', persistent = false) {
        clearTimeout(noticeTimer);
        let $noticeDiv = $('#aiohm-admin-notice');
        
        // Create notice div if it doesn't exist
        if ($noticeDiv.length === 0) {
            $('<div id="aiohm-admin-notice" class="notice is-dismissible admin-notice-hidden" tabindex="-1" role="alert" aria-live="polite"></div>').insertAfter('h1');
            $noticeDiv = $('#aiohm-admin-notice');
        }
        
        // Clear existing classes and add new type
        $noticeDiv.removeClass('notice-success notice-error notice-warning').addClass('notice-' + type);
        
        // Set message content
        // Set message content - create p element if it doesn't exist
        let $p = $noticeDiv.find('p');
        if ($p.length === 0) {
            $p = $('<p></p>').appendTo($noticeDiv);
        }
        $p.html(message);
        
        // Show notice with fade in effect
        $noticeDiv.fadeIn(300, function() {
            // Auto-focus for accessibility after fade in completes
            $noticeDiv.focus();
            
            // Announce to screen readers
            if (type === 'error') {
                $noticeDiv.attr('aria-live', 'assertive');
            } else {
                $noticeDiv.attr('aria-live', 'polite');
            }
        });
        
        // Handle dismiss button
        $noticeDiv.off('click.notice-dismiss').on('click.notice-dismiss', '.notice-dismiss', function() {
            $noticeDiv.fadeOut(300);
            // Return focus to the previously focused element or main content
            $('h1').focus();
        });
        
        // Auto-hide after timeout (unless persistent)
        if (!persistent) {
            noticeTimer = setTimeout(() => {
                if ($noticeDiv.is(':visible')) {
                    $noticeDiv.fadeOut(300, function() {
                        // Return focus to main content when auto-hiding
                        $('h1').focus();
                    });
                }
            }, 7000); // Increased to 7 seconds for better UX
        }
    }

    function renderItemsTable(items, containerSelector, checkboxName, isUploads = false) {
        const $container = $(containerSelector);
        const $addButton = isUploads ? $('#add-uploads-to-kb-btn') : $('#add-selected-to-kb-btn');
        let hasPendingItems = false;
        let tableHtml = `<table class="wp-list-table widefat striped"><thead><tr><td class="manage-column column-cb check-column"><input type="checkbox"></td><th>Title</th><th>Type</th><th>Status</th></tr></thead><tbody>`;
        if (items && items.length > 0) {
            items.forEach(function(item) {
                let checkboxDisabled = (item.status === 'Knowledge Base' || item.status === 'Failed to Add') ? 'disabled' : '';
                if (!checkboxDisabled) { hasPendingItems = true; }
                
                let statusContent = item.status;
                let statusClass = item.status.toLowerCase().replace(/\s+/g, '-');
                
                if (item.status === 'Ready to Add') {
                    statusContent = `<a href="#" class="add-single-item-link" data-id="${item.id}" data-type="${isUploads ? 'upload' : 'website'}">${item.status}</a>`;
                } else if (item.status === 'Failed to Add') {
                    statusContent = `<span class="status-failed-to-add">${item.status}</span>`;
                    statusClass = 'failed-to-add';
                }
                
                let typeDisplay = isUploads ? getFileTypeDisplay(item.type) : (item.type.charAt(0).toUpperCase() + item.type.slice(1));
                let typeClass = isUploads ? getFileTypeClass(item.type) : item.type;
                tableHtml += `<tr><th scope="row" class="check-column"><input type="checkbox" name="${checkboxName}" value="${item.id}" ${checkboxDisabled}></th><td><a href="${item.link}" target="_blank">${item.title}</a></td><td><span class="aiohm-content-type-badge type-${typeClass}">${typeDisplay}</span></td><td><span class="status-${statusClass}">${statusContent}</span></td></tr>`;
            });
        } else {
            tableHtml += `<tr><td colspan="4" class="table-empty-message">No scannable items found.</td></tr>`;
        }
        tableHtml += `</tbody><tfoot><tr><td class="manage-column column-cb check-column"><input type="checkbox"></td><th>Title</th><th>Type</th><th>Status</th></tr></tfoot></table>`;
        $container.html(tableHtml);
        
        // **MODIFIED LINE**: Disable the button instead of hiding it.
        $addButton.prop('disabled', !hasPendingItems);

        $container.find('thead input:checkbox, tfoot input:checkbox').prop('checked', false);
    }

    function handleBatchProcessing(buttonSelector, containerSelector, addScanType, findScanType, isUploads) {
        const $addBtn = $(buttonSelector);
        const selectedIds = $(`${containerSelector} input:checkbox:checked[name]`).map(function() { return this.value; }).get().filter(id => id && id !== '' && id !== '0');
        if (selectedIds.length === 0) { showAdminNotice('Please select at least one item.', 'warning'); return; }
        
        $addBtn.prop('disabled', true);
        const progressSelector = isUploads ? '#uploads-scan-progress' : '#website-scan-progress';
        const $progress = $(progressSelector);
        const $progressBar = $progress.find('.progress-bar-inner');
        const $progressPercentage = $progress.find('.progress-percentage');
        const $progressLabel = $progress.find('.progress-label');
        
        $progress.show(); 
        $progressBar.css('width', '0%'); 
        $progressPercentage.text('0%');
        $progressLabel.text('Starting batch processing...');
        
        let processedCount = 0; 
        let successCount = 0;
        let errorCount = 0;
        const totalSelected = selectedIds.length; 
        const batchSize = 5;
        const errors = [];
        
        function processBatch(batch) {
            if (batch.length === 0) {
                // Show completion summary
                const successRate = Math.round((successCount / totalSelected) * 100);
                let summaryMessage;
                
                if (errorCount === 0) {
                    summaryMessage = `🎉 Perfect! All ${totalSelected} items successfully added to knowledge base.`;
                    showAdminNotice(summaryMessage, 'success');
                } else {
                    summaryMessage = `⚠️ Processing complete: ${successCount} successful, ${errorCount} failed out of ${totalSelected} total items.`;
                    showAdminNotice(summaryMessage, 'warning');
                    
                    // Show detailed error information
                    if (errors.length > 0) {
                        const errorDetails = errors.slice(0, 3).join('<br>'); // Show first 3 errors
                        const moreErrors = errors.length > 3 ? `<br><em>...and ${errors.length - 3} more errors</em>` : '';
                        showAdminNotice(`Detailed errors:<br>${errorDetails}${moreErrors}`, 'error', true);
                    }
                }
                
                // Refresh the table to show updated statuses (with delay for cache clearing)
                const refreshTable = (attempt = 1) => {
                    $.post(ajaxurl, { 
                        action: 'aiohm_progressive_scan', 
                        scan_type: findScanType, 
                        nonce: nonce,
                        cache_bust: Date.now() + '_' + attempt // Force fresh data with attempt number
                    }).done(r => {
                        if (r.success) { 
                            renderItemsTable(r.data.items, containerSelector, isUploads ? 'upload_items[]' : 'items[]', isUploads);
                            // Check if any items are still showing as "Ready to Add" when they should be "Knowledge Base"
                            const stillPending = r.data.items.filter(item => item.status === 'Ready to Add').length;
                            if (stillPending > 0 && attempt < 3) {
                                // Try refreshing again after another delay
                                setTimeout(() => refreshTable(attempt + 1), 1500);
                                return;
                            }
                        }
                        // Final cleanup
                        $addBtn.prop('disabled', false); 
                        setTimeout(() => $progress.fadeOut(), 2000);
                    }).fail(() => {
                        // On failure, still enable button and hide progress
                        $addBtn.prop('disabled', false); 
                        setTimeout(() => $progress.fadeOut(), 2000);
                    });
                };
                
                setTimeout(() => refreshTable(1), 2000); // 2 second initial delay
                
                return;
            }
            
            const currentBatch = batch.slice(0, batchSize); 
            const remainingBatch = batch.slice(batchSize);
            
            $progressLabel.text(`Processing batch ${Math.ceil((processedCount + 1) / batchSize)} of ${Math.ceil(totalSelected / batchSize)}...`);
            
            $.post(ajaxurl, { action: 'aiohm_progressive_scan', scan_type: addScanType, item_ids: currentBatch, nonce: nonce })
            .done(r => {
                processedCount += currentBatch.length;
                let percentage = Math.round((processedCount / totalSelected) * 100);
                $progressBar.css('width', `${percentage}%`); 
                $progressPercentage.text(`${percentage}%`);
                
                if (r.success) {
                    successCount += currentBatch.length;
                } else {
                    // Handle partial success/failure responses
                    if (r.data && r.data.successes && r.data.errors) {
                        successCount += r.data.successes.length;
                        errorCount += r.data.errors.length;
                        // Add detailed error messages
                        r.data.errors.forEach(error => {
                            const errorMsg = error.error_message || error.error || 'Unknown error';
                            errors.push(`${error.title}: ${errorMsg}`);
                        });
                    } else {
                        errorCount += currentBatch.length;
                        const errorMsg = r.data?.message || 'Unknown error occurred';
                        errors.push(`Batch error: ${errorMsg}`);
                    }
                }
                
                processBatch(remainingBatch);
            })
            .fail((xhr, status, error) => { 
                processedCount += currentBatch.length;
                errorCount += currentBatch.length;
                errors.push(`Server error: ${error}`);
                
                let percentage = Math.round((processedCount / totalSelected) * 100);
                $progressBar.css('width', `${percentage}%`); 
                $progressPercentage.text(`${percentage}%`);
                
                processBatch(remainingBatch);
            });
        }
        
        processBatch(selectedIds);
    }
    
    function setupScanButton(buttonId, findType, containerSelector, checkboxName, isUploads) {
        $(buttonId).data('original-text', $(buttonId).text()).on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Scanning...');
            $.post(ajaxurl, { action: 'aiohm_progressive_scan', scan_type: findType, nonce: nonce })
            .done(r => { if (r.success) { renderItemsTable(r.data.items, containerSelector, checkboxName, isUploads); showAdminNotice('Scan complete.', 'success'); } else { showAdminNotice(r.data.message || 'Scan failed.', 'error'); } })
            .fail(() => showAdminNotice('A server error occurred.', 'error'))
            .always(() => $btn.prop('disabled', false).text($btn.data('original-text')));
        });
    }

    setupScanButton('#scan-website-btn', 'website_find', '#scan-results-container', 'items[]', false);
    setupScanButton('#scan-uploads-btn', 'uploads_find', '#scan-uploads-container', 'upload_items[]', true);
    $('#add-selected-to-kb-btn').on('click', () => handleBatchProcessing('#add-selected-to-kb-btn', '#scan-results-container', 'website_add', 'website_find', false));
    $('#add-uploads-to-kb-btn').on('click', () => handleBatchProcessing('#add-uploads-to-kb-btn', '#scan-uploads-container', 'uploads_add', 'uploads_find', true));
    
    $(document).on('click', '.add-single-item-link', function(e) { 
        e.preventDefault(); 
        const $link = $(this); 
        const itemId = $link.data('id'); 
        const itemType = $link.data('type');
        const originalText = $link.text();
        
        $link.html('<span class="spinner is-active spinner-loading"></span>');
        
        const addScanType = itemType === 'website' ? 'website_add' : 'uploads_add';
        const findScanType = itemType === 'website' ? 'website_find' : 'uploads_find';
        const container = itemType === 'website' ? '#scan-results-container' : '#scan-uploads-container';
        const checkboxName = itemType === 'website' ? 'items[]' : 'upload_items[]';

        $.post(ajaxurl, { action: 'aiohm_progressive_scan', scan_type: addScanType, item_ids: [itemId], nonce: nonce })
        .done(r => { 
            if (r.success) {
                let message = 'Item successfully added to knowledge base!';
                if (r.data && r.data.message) {
                    message = r.data.message;
                }
                
                showAdminNotice(`✅ ${message}`, 'success');
            } else {
                const errorMsg = r.data?.message || 'Unknown error occurred';
                showAdminNotice(`❌ Failed to add item to knowledge base: ${errorMsg}`, 'error');
                $link.html(`<span class="status-failed-to-add">Failed to Add</span>`);
                return;
            }
            
            // Refresh the table to show updated status (with delay for cache clearing)
            const refreshSingleItem = (attempt = 1) => {
                $.post(ajaxurl, { 
                    action: 'aiohm_progressive_scan', 
                    scan_type: findScanType, 
                    nonce: nonce,
                    cache_bust: Date.now() + '_single_' + attempt // Force fresh data
                })
                .done(r => {
                    if (r.success) {
                        renderItemsTable(r.data.items, container, checkboxName, itemType === 'upload');
                        // Check if the specific item is still showing as "Ready to Add"
                        const updatedItem = r.data.items.find(item => item.id == itemId);
                        if (updatedItem && updatedItem.status === 'Ready to Add' && attempt < 3) {
                            // Try refreshing again
                            setTimeout(() => refreshSingleItem(attempt + 1), 1000);
                        }
                    }
                });
            };
            
            setTimeout(() => refreshSingleItem(1), 1500); // 1.5 second initial delay
        })
        .fail((xhr, status, error) => { 
            let errorMsg = `Server error occurred: ${error}`;
            
            // Try to get more details from the response
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMsg = xhr.responseJSON.data.message;
            }
            
            showAdminNotice(`❌ ${errorMsg}`, 'error');
            $link.html(`<span class="status-failed-to-add">Failed to Add</span>`);
        }); 
    });
    
    $(document).on('click', '.aiohm-scan-section thead input:checkbox, .aiohm-scan-section tfoot input:checkbox', function(){ 
        const isChecked = this.checked;
        const $table = $(this).closest('table');
        $table.find('tbody input:checkbox:not(:disabled)').prop('checked', isChecked);
        $table.find('thead input:checkbox, tfoot input:checkbox').prop('checked', isChecked);
    });

    // Initial table loads
    $.post(ajaxurl, { action: 'aiohm_progressive_scan', scan_type: 'website_find', nonce: nonce }).done(r => r.success && renderItemsTable(r.data.items, '#scan-results-container', 'items[]', false));
    $.post(ajaxurl, { action: 'aiohm_progressive_scan', scan_type: 'uploads_find', nonce: nonce }).done(r => r.success && renderItemsTable(r.data.items, '#scan-uploads-container', 'upload_items[]', true));
});