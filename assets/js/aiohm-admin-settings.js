jQuery(document).ready(function($){
    let noticeTimer;
    
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

    // Show/Hide API Key functionality
    $('.aiohm-show-hide-key').on('click', function(){
        const $input = $('#' + $(this).data('target'));
        const type = $input.attr('type');
        $input.attr('type', type === 'password' ? 'text' : 'password');
    });

    // API Key Testing functionality
    $('.aiohm-test-api-key').on('click', function(){
        const $btn = $(this);
        const targetId = $btn.data('target');
        const keyType = $btn.data('type');
        const originalText = $btn.text();

        let postData = {
            action: 'aiohm_check_api_key',
            nonce: aiohm_admin_settings_ajax.nonce,
            key_type: keyType
        };

        if (keyType === 'ollama') {
            const serverUrl = $('#private_llm_server_url').val();
            const model = $('#private_llm_model').val();
            
            if (!serverUrl) {
                showAdminNotice('Please enter a server URL before testing.', 'warning');
                return;
            }
            
            postData.server_url = serverUrl;
            postData.model = model;
        } else {
            const apiKey = $('#' + targetId).val();
            
            if (!apiKey) {
                showAdminNotice('Please enter an API key before testing.', 'warning');
                return;
            }
            
            postData.api_key = apiKey;
        }

        $btn.prop('disabled', true).html('<span class="spinner is-active spinner-with-margin"></span>');

        $.post(ajaxurl, postData)
        .done(function(response) {
            if (response.success) {
                showAdminNotice(response.data.message, 'success');
            } else {
                showAdminNotice(response.data.message || 'An unknown error occurred.', 'error');
            }
        })
        .fail(function() {
            showAdminNotice('A server error occurred. Please try again.', 'error');
        })
        .always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // AI Usage Statistics functionality
    function loadUsageStats() {
        $.post(ajaxurl, {
            action: 'aiohm_get_usage_stats',
            nonce: aiohm_admin_settings_ajax.nonce
        })
        .done(function(response) {
            if (response.success && response.data) {
                updateUsageDisplay(response.data);
            } else {
                console.warn('Failed to load usage stats:', response.data?.message || 'Unknown error');
                showUsageError();
            }
        })
        .fail(function() {
            console.error('Server error while loading usage stats');
            showUsageError();
        });
    }

    function updateUsageDisplay(data) {
        // Update main stats cards
        $('#total-tokens-30d').text(formatNumber(data.total_tokens_30d || 0));
        $('#tokens-today').text(formatNumber(data.tokens_today || 0));
        $('#estimated-cost').text('$' + (data.estimated_cost || '0.00'));
        
        // Update breakdown table
        const providers = ['openai', 'gemini', 'claude', 'shareai'];
        providers.forEach(provider => {
            const providerData = data.providers?.[provider] || {};
            $('#' + provider + '-tokens').text(formatNumber(providerData.tokens || 0));
            $('#' + provider + '-requests').text(formatNumber(providerData.requests || 0));
            $('#' + provider + '-cost').text('$' + (providerData.cost || '0.00'));
        });
    }

    function showUsageError() {
        $('.stat-value, .tokens-count, .requests-count, .cost-estimate').text('Error');
    }

    function formatNumber(num) {
        if (num === 0) return '0';
        if (num < 1000) return num.toString();
        if (num < 1000000) return (num / 1000).toFixed(1) + 'K';
        return (num / 1000000).toFixed(1) + 'M';
    }

    // Refresh usage stats button
    $('#refresh-usage-stats').on('click', function() {
        const $btn = $(this);
        const originalHtml = $btn.html();
        
        $btn.prop('disabled', true).html('<span class="spinner is-active spinner-with-margin"></span> Loading...');
        
        loadUsageStats();
        
        setTimeout(() => {
            $btn.prop('disabled', false).html(originalHtml);
        }, 2000);
    });

    // Load usage stats on page load
    loadUsageStats();

    // Server preset selection functionality
    const $serverPreset = $('#server_preset');
    const $serverUrl = $('#private_llm_server_url');
    
    // Server presets
    const serverPresets = {
        'localhost': 'http://localhost:11434',
        'servbay': 'https://ollama.servbay.host/',
        'custom': ''
    };
    
    // Initialize preset selection based on current URL
    function initializeServerPreset() {
        const currentUrl = $serverUrl.val();
        let selectedPreset = 'custom';
        
        for (const [preset, url] of Object.entries(serverPresets)) {
            if (url && currentUrl === url) {
                selectedPreset = preset;
                break;
            }
        }
        
        $serverPreset.val(selectedPreset);
        updateServerUrlField(selectedPreset);
    }
    
    // Update server URL field based on preset selection
    function updateServerUrlField(preset) {
        if (preset === 'custom') {
            $serverUrl.prop('readonly', false).attr('placeholder', 'http://your-server.com:8080');
        } else if (preset === 'localhost') {
            $serverUrl.prop('readonly', false).attr('placeholder', 'http://localhost:11434');
            // Set default if empty
            if (!$serverUrl.val()) {
                $serverUrl.val(serverPresets[preset]);
            }
        } else {
            $serverUrl.prop('readonly', true).val(serverPresets[preset]);
        }
    }
    
    // Handle preset selection change
    $serverPreset.on('change', function() {
        const selectedPreset = $(this).val();
        updateServerUrlField(selectedPreset);
        
        if (selectedPreset === 'localhost') {
            // Only set default if field is empty
            if (!$serverUrl.val()) {
                $serverUrl.val(serverPresets[selectedPreset]);
            }
        } else if (selectedPreset === 'servbay') {
            $serverUrl.val(serverPresets[selectedPreset]);
        }
        // For custom, don't change the value
    });
    
    // Initialize on page load
    if ($serverPreset.length && $serverUrl.length) {
        initializeServerPreset();
    }

    // Save Default AI Provider functionality
    $('.aiohm-save-default-provider').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalText = $btn.text();
        const selectedProvider = $('#default_ai_provider').val();
        
        if (!selectedProvider) {
            showAdminNotice('Please select an AI provider before saving.', 'warning');
            return;
        }
        
        // Disable button and show loading state
        $btn.prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, {
            action: 'aiohm_save_setting',
            nonce: aiohm_admin_settings_ajax.nonce,
            setting_key: 'default_ai_provider',
            setting_value: selectedProvider
        })
        .done(function(response) {
            if (response.success) {
                showAdminNotice('Default AI provider saved successfully!', 'success');
            } else {
                const errorMessage = response.data?.message || response.data || 'Unknown error';
                showAdminNotice('Error saving default AI provider: ' + errorMessage, 'error');
            }
        })
        .fail(function() {
            showAdminNotice('Failed to save default AI provider. Please try again.', 'error');
        })
        .always(function() {
            // Reset button state
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // Save Privacy Consent functionality
    $('.aiohm-save-privacy-consent').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalText = $btn.text();
        const consentValue = $('#external_api_consent').is(':checked') ? '1' : '0';
        
        // Disable button and show loading state
        $btn.prop('disabled', true).text('Saving...');
        
        $.post(ajaxurl, {
            action: 'aiohm_save_setting',
            nonce: aiohm_admin_settings_ajax.nonce,
            setting_key: 'external_api_consent',
            setting_value: consentValue
        })
        .done(function(response) {
            if (response.success) {
                if (consentValue === '1') {
                    showAdminNotice('Privacy consent enabled - External API calls are now allowed.', 'success');
                } else {
                    showAdminNotice('Privacy consent disabled - External API calls are now blocked.', 'success');
                }
            } else {
                const errorMessage = response.data?.message || response.data || 'Unknown error';
                showAdminNotice('Error saving privacy settings: ' + errorMessage, 'error');
            }
        })
        .fail(function() {
            showAdminNotice('Failed to save privacy settings. Please try again.', 'error');
        })
        .always(function() {
            // Reset button state
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Handle main form submission feedback
    $('form[action="options.php"]').on('submit', function() {
        const $submitBtn = $(this).find('input[type="submit"]');
        const originalValue = $submitBtn.val();
        
        // Show loading state
        $submitBtn.prop('disabled', true).val('Saving...');
        
        // The form will redirect/reload the page, so we can't show success message here
        // But we can provide immediate feedback that the submission is being processed
        showAdminNotice('Saving settings...', 'info', true);
    });
});