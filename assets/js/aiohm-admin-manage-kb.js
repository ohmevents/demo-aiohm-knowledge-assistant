jQuery(document).ready(function($) {
    const nonce = aiohm_manage_kb_ajax.nonce;

    // Custom bulk action handler
    $('#aiohm-bulk-action-btn').on('click', function() {
        const action = $('#aiohm-bulk-action-select').val();
        const checkedBoxes = $('input[name="entry_ids[]"]:checked');
        
        if (!action) {
            alert('Please select an action.');
            return;
        }
        
        if (checkedBoxes.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        
        // Confirm action
        const confirmMsg = action === 'delete' ? 
            'Are you sure you want to delete the selected items?' : 
            'Are you sure you want to ' + action.replace('_', ' ') + ' the selected items?';
            
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Prepare form data
        $('#aiohm-bulk-action-input').val(action);
        $('#aiohm-bulk-ids').empty();
        
        checkedBoxes.each(function() {
            $('#aiohm-bulk-ids').append('<input type="hidden" name="entry_ids[]" value="' + $(this).val() + '">');
        });
        
        // Update status
        $('#aiohm-bulk-status').text('Processing...');
        
        // Submit the hidden form
        $('#aiohm-bulk-form').submit();
    });

    // Function to display admin notices - moved to bottom for consolidation


    // Export Public button
    $('#export-public-btn').on('click', function(){
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active spinner-inline"></span> Exporting...');

        $.post(ajaxurl, {
            action: 'aiohm_export_kb',
            scope: 'public',
            nonce: nonce
        }).done(function(response){
            if (response.success) {
                const data = response.data.data;
                const filename = response.data.filename;
                const blob = new Blob([data], {type: 'application/json'});
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                showAdminNotice('Public knowledge base exported successfully!', 'success');
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Could not export.'), 'error');
            }
        }).fail(function(){
            showAdminNotice('An unexpected server error occurred during export.', 'error');
        }).always(function(){
            $btn.prop('disabled', false).html(originalText);
        });
    });

    // Export Private button
    $('#export-private-btn').on('click', function(){
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active spinner-inline"></span> Exporting...');

        $.post(ajaxurl, {
            action: 'aiohm_export_kb',
            scope: 'private',
            nonce: nonce
        }).done(function(response){
            if (response.success) {
                const data = response.data.data;
                const filename = response.data.filename;
                const blob = new Blob([data], {type: 'application/json'});
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                showAdminNotice('Private knowledge base exported successfully!', 'success');
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Could not export.'), 'error');
            }
        }).fail(function(){
            showAdminNotice('An unexpected server error occurred during export.', 'error');
        }).always(function(){
            $btn.prop('disabled', false).html(originalText);
        });
    });

    $('#reset-kb-btn').on('click', function(){
        // Use persistent admin notice for important confirmations
        showAdminNotice('Are you absolutely sure you want to delete all knowledge base data? This cannot be undone. <button id="confirm-reset-kb" class="button button-small button-margin-left-10">Confirm Reset</button> <button id="cancel-reset-kb" class="button button-secondary button-small button-margin-left-5">Cancel</button>', 'warning', true);

        // Handle confirm button
        $(document).off('click.reset-confirm').on('click.reset-confirm', '#confirm-reset-kb', function() {
            const $btn = $(this);
            const originalText = $('#reset-kb-btn').html(); // Store original button text/html
            $('#reset-kb-btn').prop('disabled', true).html('<span class="spinner is-active spinner-inline"></span> Resetting...');
            $('#aiohm-admin-notice').fadeOut(300); // Hide the confirmation notice

            $.post(ajaxurl, {
                action: 'aiohm_reset_kb',
                nonce: nonce
            }).done(function(response){
                if (response.success) {
                    showAdminNotice(response.data.message, 'success');
                    // Reload the page to reflect the reset data, as all entries are removed.
                    window.location.reload();
                } else {
                    showAdminNotice('Error: ' + response.data.message, 'error');
                }
            }).fail(function(){
                showAdminNotice('An unexpected server error occurred.', 'error');
            }).always(function(){
                $('#reset-kb-btn').prop('disabled', false).html(originalText); // Restore original button text
            });
        });

        // Handle cancel button
        $(document).off('click.reset-cancel').on('click.reset-cancel', '#cancel-reset-kb', function() {
            $('#aiohm-admin-notice').fadeOut(300, function() {
                $('#reset-kb-btn').focus(); // Return focus to the original button
            });
        });
    });

    // Handle single scope toggle (Make Public/Private)
    $(document).on('click', '.scope-toggle-btn', function(e){
        e.preventDefault();
        const $btn = $(this);
        const contentId = $btn.data('content-id');
        const newScope = $btn.data('new-scope');
        const $row = $btn.closest('tr');
        const $visibilityCell = $row.find('.column-user_id .visibility-text');
        const originalBtnText = $btn.text(); // Store original button text

        $btn.prop('disabled', true).text('Saving...');

        $.post(ajaxurl, {
            action: 'aiohm_toggle_kb_scope',
            nonce: nonce,
            content_id: contentId,
            new_scope: newScope
        }).done(function(response){
            if (response.success) {
                $visibilityCell.text(response.data.new_visibility_text);
                $visibilityCell.removeClass('visibility-public visibility-private').addClass('visibility-' + response.data.new_visibility_text.toLowerCase());

                const oppositeScope = newScope === 'private' ? 'public' : 'private';
                const newButtonText = newScope === 'private' ? 'Make Public' : 'Make Private';
                const newButtonClass = newScope === 'private' ? 'make-public' : 'make-private';
                $btn.data('new-scope', oppositeScope).text(newButtonText);
                $btn.removeClass('make-public make-private').addClass(newButtonClass);
                showAdminNotice('Entry scope updated to ' + response.data.new_visibility_text + '.', 'success');
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Could not update scope.'), 'error');
                $btn.text(originalBtnText); // Revert button text on error
            }
        }).fail(function(){
            showAdminNotice('An unexpected server error occurred.', 'error');
            $btn.text(originalBtnText); // Revert button text on failure
        }).always(function(){
            $btn.prop('disabled', false);
        });
    });

    // Handle single delete link
    // Delegated event listener for dynamically loaded content
    $(document).on('click', 'a.button-link-delete', function(e) {
        e.preventDefault();
        const $link = $(this);
        const contentId = $link.closest('tr').find('input[name="entry_ids[]"]').val(); // Get content_id from checkbox

        // Use persistent admin notice for confirmation
        showAdminNotice('Are you sure you want to delete this entry? <button id="confirm-delete-entry" class="button button-small button-margin-left-10">Confirm Delete</button> <button id="cancel-delete-entry" class="button button-secondary button-small button-margin-left-5">Cancel</button>', 'warning', true);

        // Handle confirm button
        $(document).off('click.delete-confirm').on('click.delete-confirm', '#confirm-delete-entry', function() {
            const $row = $link.closest('tr');
            const originalLinkText = $link.text();

            $link.prop('disabled', true).text('Deleting...');
            $('#aiohm-admin-notice').fadeOut(300); // Hide the confirmation notice

            // Perform AJAX request for delete
            $.post(ajaxurl, {
                action: 'aiohm_delete_kb_entry', // This action is now handled in core-init.php
                nonce: nonce, // Use the main admin nonce
                content_id: contentId
            }).done(function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        showAdminNotice('Entry deleted successfully!', 'success');
                        // Optionally update pagination/total count here if needed without reload
                    });
                } else {
                    showAdminNotice('Error: ' + (response.data.message || 'Could not delete entry.'), 'error');
                    $link.prop('disabled', false).text(originalLinkText); // Revert link text on error
                }
            }).fail(function() {
                showAdminNotice('An unexpected server error occurred during deletion.', 'error');
                $link.prop('disabled', false).text(originalLinkText); // Revert link text on failure
            });
        });

        // Handle cancel button
        $(document).off('click.delete-cancel').on('click.delete-cancel', '#cancel-delete-entry', function() {
            $('#aiohm-admin-notice').fadeOut(300, function() {
                $link.focus(); // Return focus to the original delete link
            });
        });
    });

    $('#restore-kb-file').on('change', function(e) {
        const file = e.target.files[0];
        if (file && file.type === 'application/json') {
            $('#restore-file-name').text(file.name);
            $('#restore-kb-btn').prop('disabled', false);
        } else {
            $('#restore-file-name').text('');
            $('#restore-kb-btn').prop('disabled', true);
            if (file) {
                showAdminNotice('Please select a valid .json file.', 'warning');
            }
        }
    });

    $('#restore-kb-btn').on('click', function() {
        // Use persistent admin notice for important confirmations
        showAdminNotice('Are you sure you want to restore? This will overwrite all current global knowledge base entries. <button id="confirm-restore-kb" class="button button-small button-margin-left-10">Confirm Restore</button> <button id="cancel-restore-kb" class="button button-secondary button-small button-margin-left-5">Cancel</button>', 'warning', true);
        
        // Handle confirm button
        $(document).off('click.restore-confirm').on('click.restore-confirm', '#confirm-restore-kb', function() {
            const $btn = $('#restore-kb-btn');
            const file = $('#restore-kb-file')[0].files[0];
            const reader = new FileReader();
            const originalText = $btn.html(); // Store original button text/html

            $btn.prop('disabled', true).html('<span class="spinner is-active spinner-inline"></span> Restoring...');
            $('#aiohm-admin-notice').fadeOut(300); // Hide the confirmation notice

            reader.onload = function(e) {
                const jsonData = e.target.result;
                $.post(ajaxurl, {
                    action: 'aiohm_restore_kb',
                    nonce: nonce,
                    json_data: jsonData
                }).done(function(response){
                    if (response.success) {
                        showAdminNotice(response.data.message, 'success');
                        // Reload the page to reflect the restored data, which might involve many new/changed entries
                        window.location.reload();
                    } else {
                        showAdminNotice('Error: ' + (response.data.message || 'Could not restore.'), 'error');
                    }
                }).fail(function(){
                    showAdminNotice('An unexpected server error occurred during restore.', 'error');
                }).always(function(){
                    $btn.prop('disabled', false).html(originalText); // Restore original button text
                });
            };

            if (file) {
                reader.readAsText(file);
            } else {
                showAdminNotice('No file selected for restore.', 'error');
                $btn.prop('disabled', false).html(originalText);
            }
        });

        // Handle cancel button
        $(document).off('click.restore-cancel').on('click.restore-cancel', '#cancel-restore-kb', function() {
            $('#aiohm-admin-notice').fadeOut(300, function() {
                $('#restore-kb-btn').focus(); // Return focus to the original button
            });
        });
    });

    // Bulk actions
    // Note: For bulk actions, a full page reload is typically acceptable
    // due to the potential for many changes impacting pagination and filtering.
    $('#doaction, #doaction2').on('click', function(e) {
        e.preventDefault(); // Prevent default form submission
        const action = $(this).siblings('select[name^="action"]').val();
        
        // Only proceed if a specific bulk action is chosen (not '-1')
        if (action === '-1') {
            showAdminNotice('Please select a bulk action from the dropdown.', 'warning');
            return false;
        }

        const selectedIds = $('input[name="entry_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            showAdminNotice('Please select at least one entry for bulk action.', 'warning');
            return false;
        }

        let confirmationMessage = '';
        let confirmBtnText = '';
        let ajaxAction = '';

        if (action === 'bulk-delete') {
            confirmationMessage = 'Are you sure you want to delete the selected entries? This cannot be undone.';
            confirmBtnText = 'Confirm Delete';
            ajaxAction = 'aiohm_bulk_delete_kb'; // Assuming this action exists
        } else if (action === 'make-public' || action === 'make-private') {
            confirmationMessage = 'Are you sure you want to ' + action.replace('-', ' ') + ' the selected entries?';
            confirmBtnText = 'Confirm ' + action.replace('-', ' ');
            ajaxAction = 'aiohm_bulk_toggle_kb_scope';
        } else {
            // Should not happen if select value is validated
            showAdminNotice('Invalid bulk action selected.', 'error');
            return false;
        }
        
        // Use persistent admin notice for important confirmations
        showAdminNotice(`${confirmationMessage} <button id="confirm-bulk-action" class="button button-small button-margin-left-10">${confirmBtnText}</button> <button id="cancel-bulk-action" class="button button-secondary button-small button-margin-left-5">Cancel</button>`, 'warning', true);

        // Handle confirm button
        $(document).off('click.bulk-confirm').on('click.bulk-confirm', '#confirm-bulk-action', function() {
            const $btn = $(this);
            const originalBtnText = $('#doaction').val(); // Get text from top bulk action button
            $('#doaction, #doaction2').prop('disabled', true).val('Processing...'); // Disable both bulk action buttons
            $('#aiohm-admin-notice').fadeOut(300); // Hide the confirmation notice

            $.post(ajaxurl, {
                action: ajaxAction,
                nonce: nonce,
                content_ids: selectedIds,
                new_scope: (action === 'make-public' || action === 'make-private') ? action.replace('make-', '') : undefined // Only send new_scope for toggle actions
            }).done(function(response) {
                if (response.success) {
                    showAdminNotice(response.data.message, 'success');
                    window.location.reload(); // Reload to reflect changes
                } else {
                    showAdminNotice('Error: ' + (response.data.message || 'Bulk action failed.'), 'error');
                }
            }).fail(function() {
                showAdminNotice('An unexpected server error occurred during bulk action.', 'error');
            }).always(function() {
                $('#doaction, #doaction2').prop('disabled', false).val(originalBtnText); // Re-enable and restore text
            });
        });

        // Handle cancel button
        $(document).off('click.bulk-cancel').on('click.bulk-cancel', '#cancel-bulk-action', function() {
            $('#aiohm-admin-notice').fadeOut(300, function() {
                $('#doaction').focus(); // Return focus to the bulk action button
            });
        });

        return false; // Prevent default form submission initially
    });

    // Handle View Content button (for Brand Soul, Brand Core, GitHub, Contact, etc.)
    $(document).on('click', '.view-content-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const contentId = $btn.data('content-id');
        const contentType = $btn.data('content-type');
        
        // Show modal with content
        showContentModal(contentId, contentType);
    });

    // Backward compatibility for old Brand Soul button
    $(document).on('click', '.view-brand-soul-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const contentId = $btn.data('content-id');
        
        // Show modal with Brand Soul content
        showContentModal(contentId, 'brand-soul');
    });

    // Function to show content in a modal
    function showContentModal(contentId, contentType) {
        // Determine modal title based on content type
        const modalTitles = {
            'brand-soul': 'Brand Soul Content',
            'brand_soul': 'Brand Soul Content',
            'brand-core': 'Brand Core Content',
            'brand_core': 'Brand Core Content',
            'github': 'GitHub Content',
            'repository': 'Repository Content',
            'contact': 'Contact Information',
            'contact_type': 'Contact Information',
            'application/pdf': 'PDF Document Content',
            'application/json': 'JSON File Content',
            'text/csv': 'CSV File Content',
            'text/plain': 'Text File Content'
        };
        const modalTitle = modalTitles[contentType] || 'Content Details';

        // Create modal if it doesn't exist
        if ($('#content-modal').length === 0) {
            $('body').append(`
                <div id="content-modal" class="aiohm-modal modal-hidden">
                    <div class="aiohm-modal-backdrop"></div>
                    <div class="aiohm-modal-content">
                        <div class="aiohm-modal-header">
                            <h2 class="modal-title">${modalTitle}</h2>
                            <button class="aiohm-modal-close" type="button">&times;</button>
                        </div>
                        <div class="aiohm-modal-body">
                            <div class="content-loading">Loading...</div>
                            <div class="content-display content-display-hidden"></div>
                        </div>
                    </div>
                </div>
            `);
        }
        
        const $modal = $('#content-modal');
        const $loading = $modal.find('.content-loading');
        const $content = $modal.find('.content-display');
        const $title = $modal.find('.modal-title');
        
        // Update modal title
        $title.text(modalTitle);
        
        // Show modal and reset state
        $modal.show();
        $loading.show();
        $content.hide().empty();
        
        // Fetch content
        $.post(ajaxurl, {
            action: 'aiohm_get_content_for_view',
            nonce: nonce,
            content_id: contentId,
            content_type: contentType
        }).done(function(response) {
            if (response.success && response.data) {
                const contentType = response.data.content_type || '';
                let displayContent = response.data.content;
                let cssClass = '';
                
                // Apply special formatting based on content type
                if (contentType === 'application/json' || contentType === 'text/json' || contentType === 'JSON') {
                    cssClass = 'json-content';
                    displayContent = '<div class="content-type-header">📄 JSON Content <button id="edit-json-btn" class="button button-small editor-controls">Edit JSON</button></div>' + displayContent;
                } else if (contentType === 'TXT' || contentType === 'text/plain') {
                    cssClass = 'txt-content';
                    displayContent = '<div class="content-type-header">📄 Text Content <button id="edit-txt-btn" class="button button-small editor-controls">Edit Text</button></div>' + displayContent;
                } else if (contentType === 'MD' || contentType === 'text/markdown') {
                    cssClass = 'md-content';
                    displayContent = '<div class="content-type-header">📝 Markdown Content <button id="edit-md-btn" class="button button-small editor-controls">Edit Markdown</button></div>' + displayContent;
                } else if (contentType === 'text/csv' || contentType === 'CSV') {
                    cssClass = 'csv-content';
                    displayContent = '<div class="content-type-header">📊 CSV Content</div>' + formatCSVAsTable(response.data.content);
                } else if (contentType === 'application/pdf') {
                    cssClass = 'pdf-content';
                    displayContent = '<div class="content-type-header">📋 PDF Content</div>' + displayContent;
                } else if (contentType === 'brand-soul' || contentType === 'brand_soul') {
                    cssClass = 'brand-soul-content';
                    displayContent = '<div class="content-type-header">✨ Brand Soul</div>' + displayContent;
                } else if (contentType === 'brand-core' || contentType === 'brand_core') {
                    cssClass = 'brand-core-content';
                    displayContent = '<div class="content-type-header">🎯 Brand Core</div>' + displayContent;
                } else if (contentType === 'project_note') {
                    cssClass = 'note-content';
                    displayContent = '<div class="content-type-header">📝 Note Content <button id="edit-note-btn" class="button button-small editor-controls">Edit Note</button></div>' + displayContent;
                }
                
                $content.html('<pre class="formatted-content formatted-content-preformatted ' + cssClass + '">' + displayContent + '</pre>');
                
                // Store content data for editing
                $content.data('content-id', contentId);
                $content.data('content-type', contentType);
                $content.data('raw-content', response.data.content);
                
                $loading.hide();
                $content.show();
            } else {
                $content.html('<p>Error loading content.</p>');
                $loading.hide();
                $content.show();
            }
        }).fail(function() {
            $content.html('<p>Failed to load content.</p>');
            $loading.hide();
            $content.show();
        });
    }

    // Handle modal close
    $(document).on('click', '.aiohm-modal-close, .aiohm-modal-backdrop', function() {
        $('#content-modal').hide();
        // Backward compatibility
        $('#brand-soul-modal').hide();
    });

    // Close modal with ESC key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) {
            if ($('#content-modal').is(':visible')) {
                $('#content-modal').hide();
            }
            // Backward compatibility
            if ($('#brand-soul-modal').is(':visible')) {
                $('#brand-soul-modal').hide();
            }
        }
    });

    // Handle JSON editing
    $(document).on('click', '#edit-json-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        const rawContent = $content.data('raw-content');
        
        if (!contentId || !rawContent) {
            showAdminNotice('Error: Content data not available for editing.', 'error');
            return;
        }
        
        // Replace content with editable textarea
        $content.html(`
            <div class="content-type-header">📄 JSON Content - Editing
                <div class="editor-controls">
                    <button id="save-json-btn" class="button button-primary button-small">Save Changes</button>
                    <button id="cancel-json-btn" class="button button-secondary button-small editor-button-margin">Cancel</button>
                </div>
            </div>
            <textarea id="json-editor" class="editor-textarea">${rawContent}</textarea>
            <p class="editor-help-text">
                <strong>Tip:</strong> Make sure your JSON is valid before saving. Invalid JSON will be rejected.
            </p>
        `);
    });

    // Handle JSON save
    $(document).on('click', '#save-json-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const newContent = $('#json-editor').val();
        
        if (!newContent.trim()) {
            showAdminNotice('Error: Content cannot be empty.', 'error');
            return;
        }
        
        // Validate JSON before sending
        try {
            JSON.parse(newContent);
        } catch (err) {
            showAdminNotice('Error: Invalid JSON format. Please check your syntax.', 'error');
            return;
        }
        
        const $saveBtn = $(this);
        const originalText = $saveBtn.text();
        $saveBtn.prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, {
            action: 'aiohm_update_json_content',
            nonce: nonce,
            content_id: contentId,
            content: newContent
        }).done(function(response) {
            if (response.success) {
                showAdminNotice('JSON content updated successfully!', 'success');
                // Update stored content and reload view
                $content.data('raw-content', newContent);
                $('#content-modal').hide();
                // Optionally refresh the page to show updated content
                location.reload();
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Failed to update content'), 'error');
            }
        }).fail(function() {
            showAdminNotice('Server error occurred while saving.', 'error');
        }).always(function() {
            $saveBtn.prop('disabled', false).text(originalText);
        });
    });

    // Handle JSON edit cancel
    $(document).on('click', '#cancel-json-btn', function(e) {
        e.preventDefault();
        // Reload the original view
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        showContentModal(contentId, contentType);
    });

    // Handle TXT file editing (following JSON pattern)
    $(document).on('click', '#edit-txt-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        const rawContent = $content.data('raw-content');
        
        if (!contentId || !rawContent) {
            showAdminNotice('Error: Content data not available for editing.', 'error');
            return;
        }
        
        // Replace content with editable textarea
        $content.html(`
            <div class="content-type-header">📄 Text Content - Editing
                <div class="editor-controls">
                    <button id="save-txt-btn" class="button button-primary button-small">Save Changes</button>
                    <button id="cancel-txt-btn" class="button button-secondary button-small editor-button-margin">Cancel</button>
                </div>
            </div>
            <textarea id="txt-editor" class="editor-textarea">${rawContent}</textarea>
            <div class="editor-div-margin">
                Plain text content - no special formatting will be preserved.
            </div>
        `);
    });

    // Handle MD file editing (following JSON pattern)
    $(document).on('click', '#edit-md-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        const rawContent = $content.data('raw-content');
        
        if (!contentId || !rawContent) {
            showAdminNotice('Error: Content data not available for editing.', 'error');
            return;
        }
        
        // Replace content with editable textarea
        $content.html(`
            <div class="content-type-header">📝 Markdown Content - Editing
                <div class="editor-controls">
                    <button id="save-md-btn" class="button button-primary button-small">Save Changes</button>
                    <button id="cancel-md-btn" class="button button-secondary button-small editor-button-margin">Cancel</button>
                </div>
            </div>
            <textarea id="md-editor" class="editor-textarea">${rawContent}</textarea>
            <div class="editor-div-margin">
                Tip: You can use Markdown syntax for formatting (headers, lists, links, etc.)
            </div>
        `);
    });

    // Handle Note file editing (following TXT pattern)
    $(document).on('click', '#edit-note-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        const rawContent = $content.data('raw-content');
        
        if (!contentId || !rawContent) {
            showAdminNotice('Error: Content data not available for editing.', 'error');
            return;
        }
        
        // Replace content with editable textarea
        $content.html(`
            <div class="content-type-header">📝 Note Content - Editing
                <div class="editor-controls">
                    <button id="save-note-btn" class="button button-primary button-small">Save Changes</button>
                    <button id="cancel-note-btn" class="button button-secondary button-small editor-button-margin">Cancel</button>
                </div>
            </div>
            <textarea id="note-editor" class="editor-textarea">${rawContent}</textarea>
            <div class="editor-div-margin">
                Note content - you can use plain text or basic formatting.
            </div>
        `);
    });

    // Handle TXT save
    $(document).on('click', '#save-txt-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        const newContent = $('#txt-editor').val();
        
        if (!newContent.trim()) {
            showAdminNotice('Content cannot be empty.', 'error');
            return;
        }
        
        // Show saving state
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, {
            action: 'aiohm_update_text_content',
            content_id: contentId,
            content_type: contentType,
            content: newContent,
            nonce: nonce
        }).done(function(response) {
            if (response.success) {
                showAdminNotice('Content updated successfully!', 'success');
                // Reload the view with updated content
                showContentModal(contentId, contentType);
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Failed to update content.'), 'error');
            }
        }).fail(function() {
            showAdminNotice('An unexpected server error occurred while saving.', 'error');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // Handle MD save
    $(document).on('click', '#save-md-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        const newContent = $('#md-editor').val();
        
        if (!newContent.trim()) {
            showAdminNotice('Content cannot be empty.', 'error');
            return;
        }
        
        // Show saving state
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, {
            action: 'aiohm_update_text_content',
            content_id: contentId,
            content_type: contentType,
            content: newContent,
            nonce: nonce
        }).done(function(response) {
            if (response.success) {
                showAdminNotice('Content updated successfully!', 'success');
                // Reload the view with updated content
                showContentModal(contentId, contentType);
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Failed to update content.'), 'error');
            }
        }).fail(function() {
            showAdminNotice('An unexpected server error occurred while saving.', 'error');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // Handle Note save
    $(document).on('click', '#save-note-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        const newContent = $('#note-editor').val();
        
        if (!newContent.trim()) {
            showAdminNotice('Content cannot be empty.', 'error');
            return;
        }
        
        // Show saving state
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, {
            action: 'aiohm_update_text_content',
            content_id: contentId,
            content_type: contentType,
            content: newContent,
            nonce: nonce
        }).done(function(response) {
            if (response.success) {
                showAdminNotice('Note updated successfully!', 'success');
                // Reload the view with updated content
                showContentModal(contentId, contentType);
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Failed to update note.'), 'error');
            }
        }).fail(function() {
            showAdminNotice('An unexpected server error occurred while saving.', 'error');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // Handle Note cancel
    $(document).on('click', '#cancel-note-btn', function(e) {
        e.preventDefault();
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        showContentModal(contentId, contentType);
    });

    // Handle TXT cancel
    $(document).on('click', '#cancel-txt-btn', function(e) {
        e.preventDefault();
        // Reload the original view
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        showContentModal(contentId, contentType);
    });

    // Handle MD cancel
    $(document).on('click', '#cancel-md-btn', function(e) {
        e.preventDefault();
        // Reload the original view
        const $content = $('#content-modal .content-display');
        const contentId = $content.data('content-id');
        const contentType = $content.data('content-type');
        showContentModal(contentId, contentType);
    });


    // Function to format CSV content as an HTML table
    function formatCSVAsTable(csvContent) {
        try {
            const lines = csvContent.trim().split('\n');
            if (lines.length === 0) return '<p>Empty CSV file</p>';
            
            let html = '<div class="csv-table-container"><table class="csv-table">';
            
            // Process each line
            lines.forEach((line, index) => {
                if (!line.trim()) return; // Skip empty lines
                
                // Simple CSV parsing (handles basic cases)
                const cells = parseCSVLine(line);
                const tag = index === 0 ? 'th' : 'td';
                const rowClass = index === 0 ? '' : (index % 2 === 0 ? '' : 'csv-row-even');
                
                html += `<tr${rowClass ? ` class="${rowClass}"` : ''}>`;
                cells.forEach(cell => {
                    const cellClass = index === 0 ? 'csv-cell-header' : 'csv-cell-data';
                    html += `<${tag} class="${cellClass}">${escapeHtml(cell.trim())}</${tag}>`;
                });
                html += '</tr>';
            });
            
            html += '</table></div>';
            
            // Add row count info
            const dataRows = lines.length - 1; // Exclude header
            html += `<div class="csv-stats">Showing ${dataRows} data row${dataRows !== 1 ? 's' : ''} (plus header)</div>`;
            
            return html;
        } catch (error) {
            return `<div class="csv-error-container">
                <p>Unable to parse CSV content as table</p>
                <details class="csv-raw-content-details">
                    <summary class="csv-raw-content-summary">Show raw content</summary>
                    <pre class="csv-raw-content-pre">${escapeHtml(csvContent)}</pre>
                </details>
            </div>`;
        }
    }

    // Simple CSV line parser (handles quotes and commas)
    function parseCSVLine(line) {
        const cells = [];
        let current = '';
        let inQuotes = false;
        
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            const nextChar = line[i + 1];
            
            if (char === '"') {
                if (inQuotes && nextChar === '"') {
                    // Escaped quote
                    current += '"';
                    i++; // Skip next quote
                } else {
                    // Toggle quote state
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                // End of cell
                cells.push(current);
                current = '';
            } else {
                current += char;
            }
        }
        
        // Add final cell
        cells.push(current);
        return cells;
    }

    // Utility function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Enhanced admin notice function with accessibility features
    function showAdminNotice(message, type = 'success', persistent = false) {
        let $noticeDiv = $('#aiohm-admin-notice');
        
        // Create notice div if it doesn't exist
        if ($noticeDiv.length === 0) {
            $('<div id="aiohm-admin-notice" class="notice is-dismissible admin-notice-hidden" tabindex="-1" role="alert" aria-live="polite"></div>').insertAfter('h1.wp-heading-inline');
            $noticeDiv = $('#aiohm-admin-notice');
        }
        
        // Clear existing classes and add new type
        $noticeDiv.removeClass('notice-success notice-error notice-warning').addClass('notice-' + type);
        
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
            $('h1.wp-heading-inline').focus();
        });
        
        // Auto-hide after timeout (unless persistent)
        if (!persistent) {
            setTimeout(() => {
                if ($noticeDiv.is(':visible')) {
                    $noticeDiv.fadeOut(300, function() {
                        // Return focus to main content when auto-hiding
                        $('h1.wp-heading-inline').focus();
                    });
                }
            }, 7000); // Increased to 7 seconds for better UX
        }
    }

    // File Upload Modal functionality
    $('#add-content-btn').on('click', function() {
        showFileUploadModal();
    });

    function showFileUploadModal() {
        // Remove any existing modal
        $('#file-upload-modal').remove();
        
        // Create upload modal
        $('body').append(`
            <div id="file-upload-modal" class="aiohm-modal modal-display-flex">
                <div class="aiohm-modal-backdrop"></div>
                <div class="aiohm-modal-content modal-max-width-600">
                    <div class="aiohm-modal-header">
                        <h2>Upload Files to Knowledge Base</h2>
                        <button type="button" class="aiohm-modal-close">&times;</button>
                    </div>
                    <div class="aiohm-modal-body">
                        <div id="upload-section">
                            <p>Upload documents directly to your knowledge base. Supported formats: .txt, .json, .csv, .pdf, .doc, .docx, .md</p>
                            
                            <div class="modal-margin-bottom-20">
                                <label for="kb-scope" class="modal-label-bold">Knowledge Base Scope:</label>
                                <select id="kb-scope" class="modal-select-full-width">
                                    <option value="public">Public (Mirror Mode - visible to all visitors)</option>
                                    <option value="private">Private (Muse Mode - visible only to you)</option>
                                </select>
                            </div>
                            
                            <input type="file" id="file-input" multiple accept=".txt,.json,.csv,.pdf,.doc,.docx,.md" class="modal-file-input-hidden">
                            <div id="drop-zone" class="drop-zone">
                                <p class="drop-zone-title"><strong>Drop files here or click to browse</strong></p>
                                <p class="drop-zone-subtitle">Maximum file size: 10MB per file</p>
                            </div>
                            
                            <div id="file-list" class="file-list-container"></div>
                            
                            <div class="modal-buttons-right">
                                <button type="button" id="cancel-upload" class="button aiohm-btn-secondary button-margin-right-10">Cancel</button>
                                <button type="button" id="start-upload" class="button aiohm-btn-primary" disabled>Upload to Knowledge Base</button>
                            </div>
                        </div>
                        
                        <div id="upload-progress" class="upload-progress-hidden">
                            <h3>Processing Files...</h3>
                            <div id="progress-list"></div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    // Handle file upload modal interactions
    $(document).on('click', '#drop-zone', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const fileInput = document.getElementById('file-input');
        if (fileInput) {
            fileInput.click();
        }
    });

    $(document).on('change', '#file-input', function() {
        handleFileSelection(this.files);
    });

    $(document).on('dragover', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).css('background', '#f0f8f4');
    });

    $(document).on('dragleave', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).css('background', '#f8fbf9');
    });

    $(document).on('drop', '#drop-zone', function(e) {
        e.preventDefault();
        $(this).css('background', '#f8fbf9');
        handleFileSelection(e.originalEvent.dataTransfer.files);
    });

    $(document).on('click', '#cancel-upload', function() {
        $('#file-upload-modal').remove();
    });

    $(document).on('click', '#start-upload', function() {
        startFileUpload();
    });

    function handleFileSelection(files) {
        const fileList = $('#file-list');
        const startBtn = $('#start-upload');
        
        fileList.empty();
        
        if (files.length === 0) {
            startBtn.prop('disabled', true);
            return;
        }

        const allowedTypes = ['txt', 'json', 'csv', 'pdf', 'doc', 'docx', 'md'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        let validFiles = [];

        Array.from(files).forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            const isValidType = allowedTypes.includes(ext);
            const isValidSize = file.size <= maxSize;
            
            const status = isValidType && isValidSize ? 'valid' : 'invalid';
            const statusText = !isValidType ? 'Unsupported file type' : !isValidSize ? 'File too large (max 10MB)' : 'Ready to upload';
            const statusClass = status === 'valid' ? 'status-color-green' : 'status-color-red';
            
            fileList.append(`
                <div class="file-item" data-valid="${status === 'valid'}">
                    <div class="file-item-container">
                        <span class="file-item-name">${file.name}</span>
                        <span class="file-item-status ${statusClass}">${statusText}</span>
                    </div>
                </div>
            `);
            
            if (status === 'valid') {
                validFiles.push(file);
            }
        });

        startBtn.prop('disabled', validFiles.length === 0);
        window.selectedFiles = validFiles;
    }

    function startFileUpload() {
        const scope = $('#kb-scope').val();
        const files = window.selectedFiles || [];
        
        if (files.length === 0) {
            showAdminNotice('No valid files selected.', 'error');
            return;
        }

        // Show progress section
        $('#upload-section').hide();
        $('#upload-progress').show();
        
        const progressList = $('#progress-list');
        progressList.empty();

        // Add progress items for each file
        files.forEach((file, index) => {
            progressList.append(`
                <div id="progress-${index}" class="progress-item">
                    <div class="progress-header">
                        <span>${file.name}</span>
                        <span class="status">Preparing...</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar"></div>
                    </div>
                    <div class="progress-stages">
                        <span class="stage-upload">📤 Upload</span> → 
                        <span class="stage-process">⚙️ Process</span> → 
                        <span class="stage-index">📚 Index</span>
                    </div>
                </div>
            `);
        });

        // Upload files one by one
        uploadFilesSequentially(files, scope, 0);
    }

    function uploadFilesSequentially(files, scope, index) {
        if (index >= files.length) {
            // All files uploaded
            setTimeout(() => {
                $('#file-upload-modal').remove();
                showAdminNotice(`Successfully uploaded ${files.length} file(s) to the knowledge base!`, 'success');
                location.reload(); // Refresh the page to show new entries
            }, 1000);
            return;
        }

        const file = files[index];
        const formData = new FormData();
        formData.append('action', 'aiohm_kb_file_upload');
        formData.append('nonce', nonce); // Use the existing nonce
        formData.append('scope', scope);
        formData.append('files', file);

        const progressItem = $(`#progress-${index}`);
        let uploadComplete = false;
        
        // Estimate processing time based on file size (rough estimate)
        const fileSizeMB = file.size / (1024 * 1024);
        const estimatedProcessingTime = Math.max(2000, fileSizeMB * 1000); // At least 2 seconds, +1 second per MB
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: Math.max(30000, fileSizeMB * 10000), // At least 30 seconds, +10 seconds per MB
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const uploadPercent = (e.loaded / e.total) * 30; // Upload is only 30% of total process
                        progressItem.find('.progress-bar').css('width', uploadPercent + '%');
                        progressItem.find('.status').text('Uploading... ' + Math.round(uploadPercent) + '%');
                        progressItem.find('.stage-upload').css('font-weight', 'bold').css('color', '#457d58');
                    }
                });
                xhr.upload.addEventListener('load', function() {
                    uploadComplete = true;
                    progressItem.find('.progress-bar').css('width', '30%');
                    progressItem.find('.status').text('Processing file...');
                    progressItem.find('.stage-upload').css('color', '#28a745');
                    progressItem.find('.stage-process').css('font-weight', 'bold').css('color', '#457d58');
                    
                    // Simulate processing progress
                    let processProgress = 30;
                    const progressInterval = setInterval(() => {
                        processProgress += 5;
                        if (processProgress <= 85) {
                            progressItem.find('.progress-bar').css('width', processProgress + '%');
                            progressItem.find('.status').text('Processing file... ' + Math.round(processProgress) + '%');
                        }
                    }, estimatedProcessingTime / 15); // Spread over estimated time
                    
                    // Clear interval when response arrives
                    progressItem.data('progressInterval', progressInterval);
                });
                return xhr;
            },
            success: function(response) {
                // Clear progress interval
                const progressInterval = progressItem.data('progressInterval');
                if (progressInterval) {
                    clearInterval(progressInterval);
                }
                
                if (response.success) {
                    // Final indexing stage
                    progressItem.find('.status').text('Indexing in knowledge base...');
                    progressItem.find('.stage-process').css('color', '#28a745');
                    progressItem.find('.stage-index').css('font-weight', 'bold').css('color', '#457d58');
                    progressItem.find('.progress-bar').css('width', '90%');
                    
                    // Complete after short delay
                    setTimeout(() => {
                        progressItem.find('.status').text('✓ Successfully added to knowledge base').css('color', '#457d58');
                        progressItem.find('.progress-bar').css('width', '100%');
                        progressItem.find('.stage-index').css('color', '#28a745');
                        
                        // Upload next file
                        setTimeout(() => uploadFilesSequentially(files, scope, index + 1), 500);
                    }, 1000);
                } else {
                    progressItem.find('.status').text('✗ Failed: ' + (response.data.message || 'Unknown error')).css('color', '#dc3545');
                    progressItem.find('.progress-bar').css('background', '#dc3545').css('width', '100%');
                    progressItem.find('.progress-stages').hide();
                    
                    // Upload next file
                    setTimeout(() => uploadFilesSequentially(files, scope, index + 1), 500);
                }
            },
            error: function(xhr, status, error) {
                // Clear progress interval
                const progressInterval = progressItem.data('progressInterval');
                if (progressInterval) {
                    clearInterval(progressInterval);
                }
                
                progressItem.find('.status').text('✗ Upload failed: ' + status).css('color', '#dc3545');
                progressItem.find('.progress-bar').css('background', '#dc3545').css('width', '100%');
                progressItem.find('.progress-stages').hide();
                
                // Upload next file anyway
                setTimeout(() => uploadFilesSequentially(files, scope, index + 1), 500);
            }
        });
    }

    // Custom bulk action handler for aiohm-bulk-action-btn
    $('#aiohm-bulk-action-btn').on('click', function() {
        const action = $('#aiohm-bulk-action-select').val();
        const checkedBoxes = $('input[name="entry_ids[]"]:checked');
        
        if (!action) {
            showAdminNotice('Please select an action.', 'warning');
            return;
        }
        
        if (checkedBoxes.length === 0) {
            showAdminNotice('Please select at least one item.', 'warning');
            return;
        }
        
        // Use admin notice for confirmation instead of popup
        const confirmMsg = action === 'delete' ? 
            'Are you sure you want to delete the selected items?' : 
            'Are you sure you want to ' + action.replace('_', ' ') + ' the selected items?';
        
        const confirmBtnText = action === 'delete' ? 'Confirm Delete' : 'Confirm';
        
        showAdminNotice(`${confirmMsg} <button id="confirm-bulk-action" class="button button-small button-margin-left-10">${confirmBtnText}</button> <button id="cancel-bulk-action" class="button button-secondary button-small button-margin-left-5">Cancel</button>`, 'warning', true);
        
        // Handle confirm button
        $(document).off('click.bulk-confirm').on('click.bulk-confirm', '#confirm-bulk-action', function() {
            $('#aiohm-admin-notice').fadeOut(300); // Hide the confirmation notice
            
            // Prepare form data
            $('#aiohm-bulk-action-input').val(action);
            $('#aiohm-bulk-ids').empty();
            
            checkedBoxes.each(function() {
                $('#aiohm-bulk-ids').append('<input type="hidden" name="entry_ids[]" value="' + $(this).val() + '">');
            });
            
            // Update status
            $('#aiohm-bulk-status').text('Processing...');
            
            // Submit the hidden form
            $('#aiohm-bulk-form').submit();
        });
        
        // Handle cancel button
        $(document).off('click.bulk-cancel').on('click.bulk-cancel', '#cancel-bulk-action', function() {
            $('#aiohm-admin-notice').fadeOut(300, function() {
                $('#aiohm-bulk-action-btn').focus(); // Return focus to the bulk action button
            });
        });
    });

    // Close modal on backdrop click or close button
    $(document).on('click', '.aiohm-modal-backdrop, .aiohm-modal-close', function() {
        $('#file-upload-modal').remove();
    });
});