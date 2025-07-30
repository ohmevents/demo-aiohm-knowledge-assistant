(function($) {
    // MCP Settings Toggle (Auto-save) - Handle both enabled and disabled forms
    $('.mcp-settings-form input[name="mcp_enabled"], #mcp-settings-form input[name="mcp_enabled"]').on('change', function() {
        const $form = $(this).closest('form');
        const $toggle = $(this);
        const isEnabled = $toggle.is(':checked');
        
        // Update toggle label immediately
        const $label = $form.find('.toggle-label');
        $label.text(aiohm_mcp_ajax.strings.saving);
        
        $.post(aiohm_mcp_ajax.ajax_url, {
            action: 'aiohm_save_mcp_settings',
            nonce: $form.find('[name="mcp_settings_nonce"]').val(),
            data: $form.serialize()
        }).done(function(response) {
            if (response.success) {
                showAdminNotice(aiohm_mcp_ajax.strings.settings_saved, 'success');
                // Reload page if MCP was enabled/disabled
                if (response.data.reload_needed) {
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $label.text(isEnabled ? aiohm_mcp_ajax.strings.mcp_enabled : aiohm_mcp_ajax.strings.enable_mcp);
                }
            } else {
                // Revert toggle state
                $toggle.prop('checked', !isEnabled);
                $label.text(!isEnabled ? aiohm_mcp_ajax.strings.mcp_enabled : aiohm_mcp_ajax.strings.enable_mcp);
                showAdminNotice(aiohm_mcp_ajax.strings.error_prefix + (response.data.message || aiohm_mcp_ajax.strings.settings_error), 'error');
            }
        }).fail(function() {
            // Revert toggle state
            $toggle.prop('checked', !isEnabled);
            $label.text(!isEnabled ? aiohm_mcp_ajax.strings.mcp_enabled : aiohm_mcp_ajax.strings.enable_mcp);
            showAdminNotice(aiohm_mcp_ajax.strings.server_error, 'error');
        });
    });


    // Create Token Form Handler
    $('#create-token-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.text();
        
        $submitBtn.prop('disabled', true).text(aiohm_mcp_ajax.strings.generating);
        
        const formData = {
            action: 'aiohm_generate_mcp_token',
            nonce: $form.find('input[name="nonce"]').val(),
            token_name: $form.find('input[name="token_name"]').val(),
            token_type: $form.find('input[name="token_type"]:checked').val(),
            permissions: $form.find('input[name="permissions[]"]:checked').map(function() {
                return $(this).val();
            }).get(),
            expires_days: $form.find('select[name="expires_days"]').val()
        };
        
        $.post(aiohm_mcp_ajax.ajax_url, formData).done(function(response) {
            if (response.success) {
                showGeneratedToken(response.data.token);
                $form[0].reset();
                $form.find('input[name="token_type"][value="private"]').prop('checked', true);
                $form.find('input[name="permissions[]"][value="read_kb"]').prop('checked', true);
                loadTokensList();
            } else {
                showAdminNotice(aiohm_mcp_ajax.strings.error_prefix + (response.data.message || aiohm_mcp_ajax.strings.token_error), 'error');
            }
        }).fail(function() {
            showAdminNotice(aiohm_mcp_ajax.strings.server_error, 'error');
        }).always(function() {
            $submitBtn.prop('disabled', false).text(originalText);
        });
    });

    // Show Generated Token Section
    function showGeneratedToken(token) {
        $('#generated-token').val(token);
        $('#token-success-display').show();
        
        // Scroll to the token display and focus
        $('html, body').animate({
            scrollTop: $('#token-success-display').offset().top - 100
        }, 500);
        $('#token-success-display').focus();
    }

    // Hide Token Success Display
    $('#hide-token-success').on('click', function() {
        $('#token-success-display').hide();
    });

    // Copy Token Button
    $('#copy-token').on('click', function() {
        const tokenInput = document.getElementById('generated-token');
        tokenInput.select();
        tokenInput.setSelectionRange(0, 99999);
        document.execCommand('copy');
        
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.text(aiohm_mcp_ajax.strings.copied);
        setTimeout(function() {
            $btn.text(originalText);
        }, 2000);
    });

    // Copy Endpoint URL (both buttons)
    $('#copy-endpoint-url, #copy-endpoint-url-2').on('click', function() {
        const btnId = $(this).attr('id');
        const urlElementId = btnId === 'copy-endpoint-url-2' ? 'mcp-endpoint-url-2' : 'mcp-endpoint-url';
        const url = document.getElementById(urlElementId).textContent;
        navigator.clipboard.writeText(url).then(function() {
            const $btn = $('#' + btnId);
            const originalText = $btn.text();
            $btn.text('Copied!');
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        });
    });

    // Load Tokens List
    function loadTokensList() {
        $('#tokens-list').html('<div class="loading-tokens">' + aiohm_mcp_ajax.strings.loading_tokens + '</div>');
        
        $.post(aiohm_mcp_ajax.ajax_url, {
            action: 'aiohm_list_mcp_tokens',
            nonce: aiohm_mcp_ajax.nonce
        }).done(function(response) {
            if (response.success) {
                displayTokensList(response.data.tokens);
            } else {
                $('#tokens-list').html('<p>' + aiohm_mcp_ajax.strings.tokens_error + '</p>');
            }
        }).fail(function(xhr, status, error) {
            $('#tokens-list').html('<p>' + aiohm_mcp_ajax.strings.tokens_error + '</p>');
        });
    }

    // Display Tokens List
    function displayTokensList(tokens) {
        if (tokens.length === 0) {
            $('#tokens-list').html('<p>' + aiohm_mcp_ajax.strings.no_tokens + '</p>');
            return;
        }

        let html = '<table class="tokens-table"><thead><tr>';
        html += '<th>' + aiohm_mcp_ajax.strings.name + '</th>';
        html += '<th>' + aiohm_mcp_ajax.strings.type + '</th>';
        html += '<th>' + aiohm_mcp_ajax.strings.permissions + '</th>';
        html += '<th>' + aiohm_mcp_ajax.strings.status + '</th>';
        html += '<th>' + aiohm_mcp_ajax.strings.created + '</th>';
        html += '<th>' + aiohm_mcp_ajax.strings.expires + '</th>';
        html += '<th>' + aiohm_mcp_ajax.strings.actions + '</th>';
        html += '</tr></thead><tbody>';

        tokens.forEach(function(token) {
            const isExpired = token.expires_at && new Date(token.expires_at) < new Date();
            // Handle both string and integer values for is_active
            const isActive = parseInt(token.is_active) === 1;
            const status = !isActive ? 'inactive' : (isExpired ? 'expired' : 'active');
            const statusText = status === 'active' ? aiohm_mcp_ajax.strings.active : 
                             status === 'expired' ? aiohm_mcp_ajax.strings.expired : 
                             aiohm_mcp_ajax.strings.inactive;

            html += '<tr data-token-id="' + token.id + '">';
            html += '<td><strong>' + escapeHtml(token.token_name) + '</strong></td>';
            
            // Add token type column with styling
            const tokenType = token.token_type || 'private';
            const tokenTypeClass = tokenType === 'public' ? 'token-type-public' : 'token-type-private';
            const tokenTypeText = tokenType.charAt(0).toUpperCase() + tokenType.slice(1);
            html += '<td><span class="' + tokenTypeClass + '">' + tokenTypeText + '</span></td>';
            
            html += '<td>' + (token.permissions ? token.permissions.join(', ') : 'N/A') + '</td>';
            html += '<td><span class="token-status ' + status + '">' + statusText + '</span></td>';
            html += '<td>' + (token.created_at ? new Date(token.created_at).toLocaleDateString() : 'N/A') + '</td>';
            html += '<td>' + (token.expires_at ? new Date(token.expires_at).toLocaleDateString() : aiohm_mcp_ajax.strings.never) + '</td>';
            html += '<td>';
            html += '<button class="button button-small view-token button-margin-right-5" data-token-id="' + token.id + '">' + aiohm_mcp_ajax.strings.view + '</button>';
            // Show revoke button for active tokens, remove button for inactive tokens
            if (isActive && !isExpired) {
                html += '<button class="button button-small revoke-token" data-token-id="' + token.id + '">' + aiohm_mcp_ajax.strings.revoke + '</button>';
            } else {
                html += '<button class="button button-small button-secondary remove-token" data-token-id="' + token.id + '">' + aiohm_mcp_ajax.strings.remove + '</button>';
            }
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#tokens-list').html(html);
    }

    // Revoke Token Handler
    $(document).on('click', '.revoke-token', function() {
        const $btn = $(this);
        const tokenId = $btn.data('token-id');
        const tokenName = $btn.closest('tr').find('td:first strong').text();
        
        // Show confirmation notice instead of popup
        showAdminNotice(
            aiohm_mcp_ajax.strings.revoke_confirm.replace('%s', tokenName) +
            '<button id="confirm-revoke" data-token-id="' + tokenId + '" class="button button-primary button-margin-10">' + aiohm_mcp_ajax.strings.yes_revoke + '</button>' +
            '<button id="cancel-revoke" class="button button-secondary">' + aiohm_mcp_ajax.strings.cancel + '</button>',
            'warning',
            true // persistent
        );
    });
    
    // Handle revoke confirmation
    $(document).on('click', '#confirm-revoke', function() {
        const tokenId = $(this).data('token-id');
        const $btn = $('.revoke-token[data-token-id="' + tokenId + '"]');
        const originalText = $btn.text();
        
        // Hide confirmation notice
        $('#aiohm-admin-notice').fadeOut();
        
        $btn.prop('disabled', true).text(aiohm_mcp_ajax.strings.revoking);

        // Get the correct nonce value
        const nonceValue = aiohm_mcp_ajax.nonce;
        
        const requestData = {
            action: 'aiohm_revoke_mcp_token',
            nonce: nonceValue,
            token_id: tokenId
        };
        

        $.post(aiohm_mcp_ajax.ajax_url, requestData).done(function(response) {
            if (response.success) {
                // Remove the token row immediately for better UX
                $btn.closest('tr').fadeOut(400, function() {
                    $(this).remove();
                    // Check if tokens list is empty now
                    if ($('.tokens-table tbody tr').length === 0) {
                        $('#tokens-list').html('<p>' + aiohm_mcp_ajax.strings.no_tokens + '</p>');
                    }
                });
                showAdminNotice(aiohm_mcp_ajax.strings.token_revoked, 'success');
            } else {
                showAdminNotice(aiohm_mcp_ajax.strings.error_prefix + (response.data ? response.data.message : aiohm_mcp_ajax.strings.revoke_error), 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        }).fail(function(xhr, status, error) {
            
            let errorMsg = aiohm_mcp_ajax.strings.server_error;
            if (xhr.responseText) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    if (errorData.data && errorData.data.message) {
                        errorMsg = errorData.data.message;
                    }
                } catch (e) {
                    errorMsg += ' Response: ' + xhr.responseText.substring(0, 100);
                }
            }
            
            showAdminNotice(errorMsg, 'error');
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Handle revoke cancellation
    $(document).on('click', '#cancel-revoke', function() {
        $('#aiohm-admin-notice').fadeOut();
    });
    
    // Remove Token Handler (for inactive tokens)
    $(document).on('click', '.remove-token', function() {
        const $btn = $(this);
        const tokenId = $btn.data('token-id');
        const tokenName = $btn.closest('tr').find('td:first strong').text();
        
        // Show confirmation notice for removal
        showAdminNotice(
            aiohm_mcp_ajax.strings.remove_confirm.replace('%s', tokenName) +
            '<button id="confirm-remove" data-token-id="' + tokenId + '" class="button button-primary button-margin-10">' + aiohm_mcp_ajax.strings.yes_remove + '</button>' +
            '<button id="cancel-remove" class="button button-secondary">' + aiohm_mcp_ajax.strings.cancel + '</button>',
            'warning',
            true // persistent
        );
    });
    
    // Handle remove confirmation
    $(document).on('click', '#confirm-remove', function() {
        const tokenId = $(this).data('token-id');
        const $btn = $('.remove-token[data-token-id="' + tokenId + '"]');
        const originalText = $btn.text();
        
        // Hide confirmation notice
        $('#aiohm-admin-notice').fadeOut();
        
        $btn.prop('disabled', true).text(aiohm_mcp_ajax.strings.removing);

        const nonceValue = aiohm_mcp_ajax.nonce;
        
        const requestData = {
            action: 'aiohm_remove_mcp_token',
            nonce: nonceValue,
            token_id: tokenId
        };
        

        $.post(aiohm_mcp_ajax.ajax_url, requestData).done(function(response) {
            if (response.success) {
                // Remove the token row immediately for better UX
                $btn.closest('tr').fadeOut(400, function() {
                    $(this).remove();
                    // Check if tokens list is empty now
                    if ($('.tokens-table tbody tr').length === 0) {
                        $('#tokens-list').html('<p>' + aiohm_mcp_ajax.strings.no_tokens + '</p>');
                    }
                });
                showAdminNotice(aiohm_mcp_ajax.strings.token_removed, 'success');
            } else {
                showAdminNotice(aiohm_mcp_ajax.strings.error_prefix + (response.data ? response.data.message : aiohm_mcp_ajax.strings.remove_error), 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        }).fail(function(xhr, status, error) {
            showAdminNotice(aiohm_mcp_ajax.strings.server_error, 'error');
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Handle remove cancellation
    $(document).on('click', '#cancel-remove', function() {
        $('#aiohm-admin-notice').fadeOut();
    });

    // View Token Event Handler
    $(document).on('click', '.view-token', function() {
        const $btn = $(this);
        const tokenId = $btn.data('token-id');
        
        
        $.post(aiohm_mcp_ajax.ajax_url, {
            action: 'aiohm_view_mcp_token',
            nonce: aiohm_mcp_ajax.nonce,
            token_id: tokenId
        }).done(function(response) {
            if (response.success) {
                showTokenDetails(response.data.token_details);
            } else {
                showAdminNotice(aiohm_mcp_ajax.strings.error_prefix + (response.data.message || aiohm_mcp_ajax.strings.view_error), 'error');
            }
        }).fail(function(xhr, status, error) {
            showAdminNotice(aiohm_mcp_ajax.strings.server_error, 'error');
        });
    });

    // Documentation Tabs
    $('.docs-tab-button').on('click', function() {
        const targetTab = $(this).data('tab');
        
        $('.docs-tab-button').removeClass('active');
        $(this).addClass('active');
        
        $('.docs-tab-content').removeClass('active');
        $('#docs-' + targetTab).addClass('active');
    });

    // Admin Notice Function
    function showAdminNotice(message, type = 'success', persistent = false) {
        let $noticeDiv = $('#aiohm-admin-notice');
        
        if ($noticeDiv.length === 0) {
            $('<div id="aiohm-admin-notice" class="notice is-dismissible admin-notice-hidden" tabindex="-1" role="alert" aria-live="polite"></div>').insertAfter('h1');
            $noticeDiv = $('#aiohm-admin-notice');
        }
        
        $noticeDiv.removeClass('notice-success notice-error notice-warning notice-info').addClass('notice-' + type);
        // Set message content - create p element if it doesn't exist
        let $p = $noticeDiv.find('p');
        if ($p.length === 0) {
            $p = $('<p></p>').appendTo($noticeDiv);
        }
        $p.html(message);
        $noticeDiv.fadeIn(300);
        
        // Scroll to notice and focus for accessibility
        $('html, body').animate({
            scrollTop: $noticeDiv.offset().top - 50
        }, 300);
        $noticeDiv.focus();
        
        if (!persistent) {
            setTimeout(function() {
                if ($noticeDiv.is(':visible')) {
                    $noticeDiv.fadeOut(300);
                }
            }, 5000);
        }
    }

    // Show Token Details
    function showTokenDetails(tokenDetails) {
        
        // Populate detail fields
        $('#detail-token-name').text(tokenDetails.token_name || 'N/A');
        $('#detail-token-preview').text(tokenDetails.token_preview || 'N/A');
        
        // Format token type with proper styling
        const tokenType = tokenDetails.token_type || 'private';
        const tokenTypeClass = tokenType === 'public' ? 'token-type-public' : 'token-type-private';
        const tokenTypeText = tokenType.charAt(0).toUpperCase() + tokenType.slice(1);
        $('#detail-token-type').html('<span class="' + tokenTypeClass + '">' + tokenTypeText + '</span>');
        
        $('#detail-token-permissions').text(tokenDetails.permissions ? tokenDetails.permissions.join(', ') : 'N/A');
        
        // Determine status
        const isExpired = tokenDetails.expires_at && new Date(tokenDetails.expires_at) < new Date();
        const status = !tokenDetails.is_active ? aiohm_mcp_ajax.strings.inactive : 
                      (isExpired ? aiohm_mcp_ajax.strings.expired : 
                      aiohm_mcp_ajax.strings.active);
        $('#detail-token-status').text(status);
        
        $('#detail-token-creator').text(tokenDetails.created_by_name || 'Unknown');
        $('#detail-token-created').text(tokenDetails.created_at ? new Date(tokenDetails.created_at).toLocaleDateString() : 'N/A');
        $('#detail-token-last-used').text(tokenDetails.last_used_at ? 
            new Date(tokenDetails.last_used_at).toLocaleDateString() : 
            aiohm_mcp_ajax.strings.never);
        $('#detail-token-expires').text(tokenDetails.expires_at ? 
            new Date(tokenDetails.expires_at).toLocaleDateString() : 
            aiohm_mcp_ajax.strings.never);
        
        // Show details in the collapsible box and show the view button
        $('#view-token-details-btn').show();
        $('#token-details-box').slideDown(300);
        
        // Scroll to the details display and focus
        $('html, body').animate({
            scrollTop: $('#token-details-box').offset().top - 100
        }, 500);
        $('#token-details-box').focus();
    }

    // View Token Details Button Handler
    $('#view-token-details-btn').on('click', function() {
        $('#token-details-box').slideToggle(300);
    });

    // Hide Token Details Box
    $('#hide-token-details-box').on('click', function() {
        $('#token-details-box').slideUp(300);
    });
    

    // Utility function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Initialize tokens list if the tokens list element exists and user has access
    if ($('#tokens-list').length > 0) {
        loadTokensList();
    }

})(jQuery);