jQuery(document).ready(function($) {
    let debugInfo = {};
    
    // Initialize system information
    const systemInfo = JSON.parse($('#system-info').val() || '{}');
    
    // Utility function to show messages
    function showMessage(message, type = 'success') {
        const $messagesContainer = $('#support-messages');
        const messageHtml = `
            <div class="support-message ${type}">
                <span class="dashicons ${type === 'success' ? 'dashicons-yes-alt' : type === 'error' ? 'dashicons-warning' : 'dashicons-info'}"></span>
                ${message}
            </div>
        `;
        
        $messagesContainer.show().append(messageHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            $messagesContainer.find('.support-message').last().fadeOut(300, function() {
                $(this).remove();
                if ($messagesContainer.find('.support-message').length === 0) {
                    $messagesContainer.hide();
                }
            });
        }, 5000);
    }
    
    // Collect Debug Information
    $('#collect-debug-info').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.addClass('loading').prop('disabled', true);
        
        // Collect comprehensive debug information
        collectDebugInformation().then(function(debug) {
            debugInfo = debug;
            
            const debugText = formatDebugInformation(debug);
            $('#debug-information').val(debugText);
            
            showMessage('Debug information collected successfully!', 'success');
        }).catch(function(error) {
            showMessage('Error collecting debug information: ' + error.message, 'error');
        }).finally(function() {
            $btn.removeClass('loading').prop('disabled', false).html(originalText);
        });
    });
    
    // Test API Connections
    $('#check-api-connections').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.addClass('loading').prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'aiohm_test_all_api_connections',
            nonce: aiohm_admin_help_ajax?.nonce || ''
        }).done(function(response) {
            if (response.success) {
                const results = response.data;
                let resultText = '=== API Connection Test Results ===\\n\\n';
                
                Object.keys(results).forEach(provider => {
                    const result = results[provider];
                    resultText += `${provider.toUpperCase()}:\\n`;
                    resultText += `Status: ${result.status}\\n`;
                    resultText += `Message: ${result.message}\\n\\n`;
                });
                
                $('#debug-text').val(resultText);
                $('#debug-output').slideDown(300);
                showMessage('API connection tests completed!', 'success');
            } else {
                showMessage('Error testing API connections: ' + (response.data?.message || 'Unknown error'), 'error');
            }
        }).fail(function() {
            showMessage('Server error while testing API connections', 'error');
        }).finally(function() {
            $btn.removeClass('loading').prop('disabled', false).html(originalText);
        });
    });
    
    // Check Database Health
    $('#check-database-health').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.addClass('loading').prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'aiohm_check_database_health',
            nonce: aiohm_admin_help_ajax?.nonce || ''
        }).done(function(response) {
            if (response.success) {
                const healthData = response.data;
                let healthText = '=== Database Health Check ===\\n\\n';
                
                Object.keys(healthData).forEach(table => {
                    const data = healthData[table];
                    healthText += `${table}:\\n`;
                    healthText += `Rows: ${data.rows}\\n`;
                    healthText += `Status: ${data.status}\\n`;
                    if (data.issues && data.issues.length > 0) {
                        healthText += `Issues: ${data.issues.join(', ')}\\n`;
                    }
                    healthText += '\\n';
                });
                
                $('#debug-text').val(healthText);
                $('#debug-output').slideDown(300);
                showMessage('Database health check completed!', 'success');
            } else {
                showMessage('Error checking database health: ' + (response.data?.message || 'Unknown error'), 'error');
            }
        }).fail(function() {
            showMessage('Server error while checking database health', 'error');
        }).finally(function() {
            $btn.removeClass('loading').prop('disabled', false).html(originalText);
        });
    });
    
    // Copy Debug Info to Clipboard
    $('#copy-debug-info').on('click', function() {
        const debugText = $('#debug-text').val();
        if (!debugText) {
            showMessage('No debug information to copy', 'error');
            return;
        }
        
        navigator.clipboard.writeText(debugText).then(function() {
            showMessage('Debug information copied to clipboard!', 'success');
        }).catch(function() {
            // Fallback for older browsers
            $('#debug-text').select();
            document.execCommand('copy');
            showMessage('Debug information copied to clipboard!', 'success');
        });
    });
    
    // Download Debug Info as File
    $('#download-debug-info').on('click', function() {
        const debugText = $('#debug-text').val();
        if (!debugText) {
            showMessage('No debug information to download', 'error');
            return;
        }
        
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const filename = `aiohm-debug-${timestamp}.txt`;
        
        const blob = new Blob([debugText], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        showMessage('Debug file downloaded successfully!', 'success');
    });
    
    // Combined Support Form
    $('#combined-support-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.html();
        
        $submitBtn.addClass('loading').prop('disabled', true);
        
        const reportType = $('#report-type').val();
        const isFeatureRequest = reportType === 'feature-request';
        
        const formData = {
            action: isFeatureRequest ? 'aiohm_submit_feature_request' : 'aiohm_submit_support_request',
            nonce: aiohm_admin_help_ajax?.nonce || '',
            email: $('#support-email').val(),
            title: $('#support-title').val(),
            type: reportType,
            description: $('#support-description').val(),
            debug_information: $('#debug-information').val(),
            include_debug: $('#include-debug-info').is(':checked'),
            system_info: systemInfo
        };
        
        // For feature requests, map fields to expected structure
        if (isFeatureRequest) {
            formData.category = reportType;
        } else {
            formData.subject = reportType;
        }
        
        if (formData.include_debug && Object.keys(debugInfo).length > 0) {
            formData.debug_info = debugInfo;
        }
        
        $.post(ajaxurl, formData)
        .done(function(response) {
            if (response.success) {
                const messageType = isFeatureRequest ? 'Feature request' : 'Support request';
                showMessage(`${messageType} submitted successfully! We'll get back to you soon.`, 'success');
                $form[0].reset();
                $('#debug-output').slideUp(300);
            } else {
                showMessage('Error submitting request: ' + (response.data?.message || 'Unknown error'), 'error');
            }
        })
        .fail(function() {
            showMessage('Server error while submitting request', 'error');
        })
        .finally(function() {
            $submitBtn.removeClass('loading').prop('disabled', false).html(originalText);
        });
    });
    
    // Collect comprehensive debug information
    function collectDebugInformation() {
        return new Promise(function(resolve, reject) {
            const debug = {
                timestamp: new Date().toISOString(),
                system: systemInfo,
                browser: {
                    userAgent: navigator.userAgent,
                    language: navigator.language,
                    platform: navigator.platform,
                    cookieEnabled: navigator.cookieEnabled,
                    onLine: navigator.onLine
                },
                screen: {
                    width: screen.width,
                    height: screen.height,
                    colorDepth: screen.colorDepth,
                    pixelDepth: screen.pixelDepth
                },
                window: {
                    innerWidth: window.innerWidth,
                    innerHeight: window.innerHeight,
                    devicePixelRatio: window.devicePixelRatio || 1
                },
                wordpress: {
                    adminUrl: ajaxurl?.replace('admin-ajax.php', '') || 'Unknown',
                    currentPage: window.location.href,
                    referrer: document.referrer || 'None'
                }
            };
            
            // Collect plugin-specific information via AJAX
            $.post(ajaxurl, {
                action: 'aiohm_get_debug_info',
                nonce: aiohm_admin_help_ajax?.nonce || ''
            }).done(function(response) {
                if (response.success) {
                    debug.plugin = response.data;
                }
                resolve(debug);
            }).fail(function() {
                // Continue even if plugin debug info fails
                debug.plugin = { error: 'Failed to collect plugin debug information' };
                resolve(debug);
            });
        });
    }
    
    // Format debug information for display
    function formatDebugInformation(debug) {
        let output = '=== AIOHM Knowledge Assistant Debug Information ===\\n';
        output += `Generated: ${debug.timestamp}\\n\\n`;
        
        output += '=== System Information ===\\n';
        output += `Plugin Version: ${debug.system.plugin_version}\\n`;
        output += `WordPress Version: ${debug.system.wp_version}\\n`;
        output += `PHP Version: ${debug.system.php_version}\\n`;
        output += `Site URL: ${debug.system.site_url}\\n`;
        output += `Active AI Provider: ${debug.system.active_provider}\\n\\n`;
        
        output += '=== Browser Information ===\\n';
        output += `User Agent: ${debug.browser.userAgent}\\n`;
        output += `Language: ${debug.browser.language}\\n`;
        output += `Platform: ${debug.browser.platform}\\n`;
        output += `Cookies Enabled: ${debug.browser.cookieEnabled}\\n`;
        output += `Online: ${debug.browser.onLine}\\n\\n`;
        
        output += '=== Display Information ===\\n';
        output += `Screen: ${debug.screen.width}x${debug.screen.height}\\n`;
        output += `Window: ${debug.window.innerWidth}x${debug.window.innerHeight}\\n`;
        output += `Device Pixel Ratio: ${debug.window.devicePixelRatio}\\n`;
        output += `Color Depth: ${debug.screen.colorDepth}\\n\\n`;
        
        output += '=== WordPress Information ===\\n';
        output += `Admin URL: ${debug.wordpress.adminUrl}\\n`;
        output += `Current Page: ${debug.wordpress.currentPage}\\n`;
        output += `Referrer: ${debug.wordpress.referrer}\\n\\n`;
        
        if (debug.plugin && !debug.plugin.error) {
            output += '=== Plugin Information ===\\n';
            if (debug.plugin.settings) {
                output += 'Settings:\\n';
                Object.keys(debug.plugin.settings).forEach(key => {
                    let value = debug.plugin.settings[key];
                    // Hide sensitive information
                    if (key.includes('key') || key.includes('token')) {
                        value = value ? '[CONFIGURED]' : '[NOT SET]';
                    }
                    output += `  ${key}: ${value}\\n`;
                });
                output += '\\n';
            }
            
            if (debug.plugin.database) {
                output += 'Database Tables:\\n';
                Object.keys(debug.plugin.database).forEach(table => {
                    const data = debug.plugin.database[table];
                    output += `  ${table}: ${data.exists ? 'EXISTS' : 'MISSING'} (${data.rows || 0} rows)\\n`;
                });
                output += '\\n';
            }
            
            if (debug.plugin.errors && debug.plugin.errors.length > 0) {
                output += 'Recent Errors:\\n';
                debug.plugin.errors.forEach(error => {
                    output += `  ${error}\\n`;
                });
                output += '\\n';
            }
        } else if (debug.plugin?.error) {
            output += '=== Plugin Information ===\\n';
            output += `Error: ${debug.plugin.error}\\n\\n`;
        }
        
        return output;
    }
});

// Make sure ajaxurl is available
if (typeof ajaxurl === 'undefined') {
    var ajaxurl = '/wp-admin/admin-ajax.php';
}