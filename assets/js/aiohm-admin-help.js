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
        let output = '=== AIOHM Knowledge Assistant Comprehensive Debug Information ===\\n';
        output += `Generated: ${debug.timestamp}\\n`;
        output += `Plugin Version: ${debug.plugin?.plugin_version || 'Unknown'}\\n\\n`;
        
        // === SYSTEM INFORMATION ===
        if (debug.plugin?.system) {
            output += '=== SYSTEM INFORMATION ===\\n';
            const sys = debug.plugin.system;
            output += `WordPress Version: ${sys.wordpress_version}\\n`;
            output += `PHP Version: ${sys.php_version}\\n`;
            output += `MySQL Version: ${sys.mysql_version}\\n`;
            output += `Server Software: ${sys.server_software}\\n`;
            output += `Memory Limit: ${sys.memory_limit}\\n`;
            output += `Max Execution Time: ${sys.max_execution_time}s\\n`;
            output += `Upload Max Filesize: ${sys.upload_max_filesize}\\n`;
            output += `Post Max Size: ${sys.post_max_size}\\n`;
            output += `Is Multisite: ${sys.is_multisite ? 'Yes' : 'No'}\\n`;
            output += `Active Theme: ${sys.active_theme}\\n`;
            output += `Debug Mode: ${sys.debug_mode ? 'Enabled' : 'Disabled'}\\n`;
            output += `Debug Log: ${sys.debug_log ? 'Enabled' : 'Disabled'}\\n\\n`;
        }
        
        // === PLUGIN STATUS ===
        if (debug.plugin?.plugin_status) {
            output += '=== PLUGIN STATUS ===\\n';
            const status = debug.plugin.plugin_status;
            output += `Demo Version: ${status.is_demo_version ? 'Yes' : 'No'}\\n`;
            output += `Plugin Path: ${status.plugin_path}\\n`;
            output += `Main File Exists: ${status.main_file_exists ? 'Yes' : 'No'}\\n`;
            output += `Includes Dir Exists: ${status.includes_dir_exists ? 'Yes' : 'No'}\\n`;
            output += `Assets Dir Exists: ${status.assets_dir_exists ? 'Yes' : 'No'}\\n`;
            output += `Templates Dir Exists: ${status.templates_dir_exists ? 'Yes' : 'No'}\\n\\n`;
        }
        
        // === ALL 10 PAGES STATUS ===
        output += '=== ADMIN PAGES STATUS ===\\n';
        
        // Dashboard
        if (debug.plugin?.dashboard) {
            output += 'Dashboard Page:\\n';
            const dash = debug.plugin.dashboard;
            output += `  Template: ${dash.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Robot Script: ${dash.robot_script_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  CSS File: ${dash.dashboard_css_exists ? 'Exists' : 'Missing'}\\n`;
        }
        
        // Settings
        if (debug.plugin?.settings) {
            output += 'Settings Page:\\n';
            const set = debug.plugin.settings;
            output += `  Template: ${set.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Universal Robot: ${set.universal_robot_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Default Provider: ${set.default_provider}\\n`;
            output += `  Configured Providers: `;
            const providers = [];
            Object.keys(set.configured_providers).forEach(provider => {
                if (set.configured_providers[provider]) providers.push(provider.toUpperCase());
            });
            output += providers.length > 0 ? providers.join(', ') : 'None';
            output += '\\n';
        }
        
        // Brand Soul
        if (debug.plugin?.brand_soul) {
            output += 'Brand Soul Page:\\n';
            const bs = debug.plugin.brand_soul;
            output += `  Template: ${bs.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Has Data: ${bs.has_brand_soul_data ? 'Yes' : 'No'}\\n`;
            output += `  Completed Sections: ${bs.completed_sections}\\n`;
        }
        
        // Scan Content
        if (debug.plugin?.scan_content) {
            output += 'Scan Content Page:\\n';
            const sc = debug.plugin.scan_content;
            output += `  Template: ${sc.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Scanner Class: ${sc.scanner_class_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Posts: ${sc.indexed_posts}/${sc.total_posts} indexed\\n`;
            output += `  Pages: ${sc.indexed_pages}/${sc.total_pages} indexed\\n`;
            output += `  Media: ${sc.indexed_media}/${sc.total_media} indexed\\n`;
        }
        
        // Manage KB
        if (debug.plugin?.manage_kb) {
            output += 'Manage Knowledge Base Page:\\n';
            const kb = debug.plugin.manage_kb;
            output += `  Template: ${kb.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  KB Manager Class: ${kb.kb_manager_class_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Total Entries: ${kb.total_entries}\\n`;
            output += `  Has Entries: ${kb.has_entries ? 'Yes' : 'No'}\\n`;
        }
        
        // Mirror Mode
        if (debug.plugin?.mirror_mode) {
            output += 'Mirror Mode Page:\\n';
            const mm = debug.plugin.mirror_mode;
            output += `  Template: ${mm.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Enabled: ${mm.enabled ? 'Yes' : 'No'}\\n`;
            output += `  Floating Chat: ${mm.floating_chat ? 'Yes' : 'No'}\\n`;
            output += `  Has System Message: ${mm.has_system_message ? 'Yes' : 'No'}\\n`;
            output += `  Has Business Name: ${mm.has_business_name ? 'Yes' : 'No'}\\n`;
            output += `  AI Model: ${mm.configured_ai_model}\\n`;
        }
        
        // Muse Mode
        if (debug.plugin?.muse_mode) {
            output += 'Muse Mode Page:\\n';
            const ms = debug.plugin.muse_mode;
            output += `  Template: ${ms.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Enabled: ${ms.enabled ? 'Yes' : 'No'}\\n`;
            output += `  Assistant Name: ${ms.assistant_name}\\n`;
            output += `  Has System Prompt: ${ms.has_system_prompt ? 'Yes' : 'No'}\\n`;
            output += `  Brand Archetype: ${ms.brand_archetype}\\n`;
            output += `  AI Model: ${ms.configured_ai_model}\\n`;
            output += `  Temperature: ${ms.temperature}\\n`;
            output += `  Fullscreen Mode: ${ms.fullscreen_mode ? 'Yes' : 'No'}\\n`;
        }
        
        // MCP API
        if (debug.plugin?.mcp) {
            output += 'MCP API Page:\\n';
            const mcp = debug.plugin.mcp;
            output += `  Template: ${mcp.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  MCP Integration: ${mcp.mcp_integration_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Total Tokens: ${mcp.total_tokens}\\n`;
            output += `  Usage Records: ${mcp.total_usage_records}\\n`;
            output += `  Has Active Tokens: ${mcp.has_active_tokens ? 'Yes' : 'No'}\\n`;
        }
        
        // License
        if (debug.plugin?.license) {
            output += 'License Page:\\n';
            const lic = debug.plugin.license;
            output += `  Template: ${lic.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  PMP Integration: ${lic.pmp_integration_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  AIOHM Email Configured: ${lic.aiohm_email_configured ? 'Yes' : 'No'}\\n`;
        }
        
        // Get Help
        if (debug.plugin?.get_help) {
            output += 'Get Help Page:\\n';
            const help = debug.plugin.get_help;
            output += `  Template: ${help.template_exists ? 'Exists' : 'Missing'}\\n`;
            output += `  Debug Collection: ${help.debug_collection_working ? 'Working' : 'Failed'}\\n`;
            if (help.support_features_available) {
                output += `  Debug Info Collection: ${help.support_features_available.debug_info_collection ? 'Available' : 'Missing'}\\n`;
                output += `  API Connection Test: ${help.support_features_available.api_connection_test ? 'Available' : 'Missing'}\\n`;
                output += `  Database Health Check: ${help.support_features_available.database_health_check ? 'Available' : 'Missing'}\\n`;
            }
        }
        
        output += '\\n';
        
        // === DATABASE STATUS ===
        if (debug.plugin?.database) {
            output += '=== DATABASE STATUS ===\\n';
            Object.keys(debug.plugin.database).forEach(table => {
                const data = debug.plugin.database[table];
                output += `${table}: ${data.exists ? 'EXISTS' : 'MISSING'} (${data.rows || 0} rows)\\n`;
            });
            output += '\\n';
        }
        
        // === CONVERSATIONS & ACTIVITY ===
        if (debug.plugin?.conversations) {
            output += '=== CONVERSATIONS & ACTIVITY ===\\n';
            const conv = debug.plugin.conversations;
            output += `Total Conversations: ${conv.total_conversations}\\n`;
            output += `Total Messages: ${conv.total_messages}\\n`;
            output += `Total Projects: ${conv.total_projects}\\n`;
            output += `Has Conversation Data: ${conv.has_conversation_data ? 'Yes' : 'No'}\\n\\n`;
        }
        
        // === RECENT ACTIVITY ===
        if (debug.plugin?.recent_activity) {
            output += '=== RECENT ACTIVITY (Last 7 Days) ===\\n';
            const activity = debug.plugin.recent_activity;
            output += `New Conversations: ${activity.conversations_last_7_days || 0}\\n`;
            output += `New KB Entries: ${activity.kb_entries_last_7_days || 0}\\n`;
            output += `MCP API Calls: ${activity.mcp_usage_last_7_days || 0}\\n\\n`;
        }
        
        // === AI PROVIDERS ===
        if (debug.plugin?.ai_providers && Object.keys(debug.plugin.ai_providers).length > 0) {
            output += '=== AI PROVIDERS STATUS ===\\n';
            Object.keys(debug.plugin.ai_providers).forEach(provider => {
                const data = debug.plugin.ai_providers[provider];
                output += `${provider.toUpperCase()}: ${data.configured ? 'Configured' : 'Not Configured'} (${data.status})\\n`;
            });
            output += '\\n';
        }
        
        // === FILE PERMISSIONS ===
        if (debug.plugin?.file_permissions) {
            output += '=== FILE PERMISSIONS ===\\n';
            const perms = debug.plugin.file_permissions;
            output += `WP Content Writable: ${perms.wp_content_writable ? 'Yes' : 'No'}\\n`;
            output += `Uploads Dir Writable: ${perms.uploads_dir_writable ? 'Yes' : 'No'}\\n`;
            output += `Plugin Dir Readable: ${perms.plugin_dir_readable ? 'Yes' : 'No'}\\n`;
            output += `Debug Log Writable: ${perms.debug_log_writable ? 'Yes' : 'No'}\\n\\n`;
        }
        
        // === WORDPRESS FEATURES ===
        if (debug.plugin?.wordpress_features) {
            output += '=== WORDPRESS FEATURES ===\\n';
            const wp = debug.plugin.wordpress_features;
            output += `WP Filesystem: ${wp.wp_filesystem_available ? 'Available' : 'Missing'}\\n`;
            output += `WP HTTP: ${wp.wp_http_available ? 'Available' : 'Missing'}\\n`;
            output += `JSON Encode: ${wp.json_encode_available ? 'Available' : 'Missing'}\\n`;
            output += `cURL: ${wp.curl_available ? 'Available' : 'Missing'}\\n`;
            output += `OpenSSL: ${wp.openssl_available ? 'Available' : 'Missing'}\\n`;
            output += `Mbstring: ${wp.mbstring_available ? 'Available' : 'Missing'}\\n\\n`;
        }
        
        // === BROWSER INFORMATION ===
        output += '=== BROWSER INFORMATION ===\\n';
        output += `User Agent: ${debug.browser.userAgent}\\n`;
        output += `Language: ${debug.browser.language}\\n`;
        output += `Platform: ${debug.browser.platform}\\n`;
        output += `Cookies Enabled: ${debug.browser.cookieEnabled}\\n`;
        output += `Online: ${debug.browser.onLine}\\n`;
        output += `Screen: ${debug.screen.width}x${debug.screen.height}\\n`;
        output += `Window: ${debug.window.innerWidth}x${debug.window.innerHeight}\\n`;
        output += `Device Pixel Ratio: ${debug.window.devicePixelRatio}\\n\\n`;
        
        // === RECENT ERRORS ===
        if (debug.plugin?.errors && debug.plugin.errors.length > 0) {
            output += '=== RECENT ERRORS ===\\n';
            debug.plugin.errors.forEach(error => {
                output += `${error}\\n`;
            });
            output += '\\n';
        }
        
        // === CURRENT PAGE INFO ===
        output += '=== CURRENT PAGE INFO ===\\n';
        output += `Admin URL: ${debug.wordpress.adminUrl}\\n`;
        output += `Current Page: ${debug.wordpress.currentPage}\\n`;
        output += `Referrer: ${debug.wordpress.referrer}\\n\\n`;
        
        output += '=== END DEBUG INFORMATION ===';
        
        return output;
    }
});

// Make sure ajaxurl is available
if (typeof ajaxurl === 'undefined') {
    var ajaxurl = '/wp-admin/admin-ajax.php';
}