<?php
/**
 * AIOHM MCP (Model Context Protocol) Admin Page
 * 
 * Manage MCP API tokens, permissions, and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = AIOHM_KB_Assistant::get_settings();
$mcp_enabled = !empty($settings['mcp_enabled']);
$user_id = get_current_user_id();

// MCP enabled state determined from settings

// Check membership access
$has_mcp_access = AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access();
$access_levels = AIOHM_KB_PMP_Integration::get_user_mcp_access_levels();
$membership_details = AIOHM_KB_PMP_Integration::get_user_membership_details();
?>

<div class="wrap aiohm-mcp-page">
    <div class="aiohm-header">
        <h1><?php esc_html_e('MCP (Model Context Protocol) API', 'aiohm-knowledge-assistant'); ?></h1>
        <p class="aiohm-tagline"><?php esc_html_e('Share your knowledge base with external AI assistants and applications through standardized MCP endpoints.', 'aiohm-knowledge-assistant'); ?></p>
    </div>

    <?php if (defined('AIOHM_KB_VERSION') && AIOHM_KB_VERSION === 'DEMO') : ?>
    <!-- Demo Version Banner -->
    <div class="aiohm-demo-banner" style="background: #EBEBEB; border-left: 4px solid #7d9b76; color: #272727; padding: 12px 20px; margin: 15px 0; border-radius: 6px; font-family: 'Montserrat', sans-serif;">
        <p style="margin: 0; font-weight: 600; font-size: 0.95em;">
            <strong style="color: #1f5014;">DEMO VERSION</strong> - You're experiencing AIOHM's interface with simulated responses.
        </p>
    </div>
    <?php endif; ?>

    <div id="aiohm-admin-notice" class="notice is-dismissible" tabindex="-1" role="alert" aria-live="polite"></div>

    <div class="aiohm-mcp-container">
        <?php if (!$has_mcp_access): ?>
        <!-- Access Denied Section -->
        <div class="aiohm-card aiohm-mcp-access-denied">
            <div class="access-denied-content">
                <div class="access-denied-icon">🔒</div>
                <h2><?php esc_html_e('MCP API - Private Members Only', 'aiohm-knowledge-assistant'); ?></h2>
                <p><?php esc_html_e('The MCP (Model Context Protocol) API is an exclusive feature for AIOHM Private level members. Upgrade to Private level to unlock the ability to share your knowledge base with external AI assistants.', 'aiohm-knowledge-assistant'); ?></p>
                
                <div class="upgrade-benefits">
                    <h3><?php esc_html_e('Private Level Benefits:', 'aiohm-knowledge-assistant'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Full MCP API access', 'aiohm-knowledge-assistant'); ?></li>
                        <li><?php esc_html_e('Connect Claude Desktop to your knowledge base', 'aiohm-knowledge-assistant'); ?></li>
                        <li><?php esc_html_e('Share knowledge between multiple sites', 'aiohm-knowledge-assistant'); ?></li>
                        <li><?php esc_html_e('Mobile app integration capabilities', 'aiohm-knowledge-assistant'); ?></li>
                        <li><?php esc_html_e('Automatic access to Club and Tribal level features', 'aiohm-knowledge-assistant'); ?></li>
                        <li><?php esc_html_e('Advanced AI agent ecosystem integration', 'aiohm-knowledge-assistant'); ?></li>
                    </ul>
                </div>
                
                <a href="https://aiohm.app/pricing/" target="_blank" class="button button-primary button-hero"><?php esc_html_e('Upgrade to Private Level', 'aiohm-knowledge-assistant'); ?></a>
            </div>
        </div>
        <?php else: ?>


        <?php if ($mcp_enabled): ?>
        <!-- Two Column Layout for Quick Start Guide and MCP Integration Guide -->
        <div class="aiohm-mcp-guide-content">
            <div class="aiohm-mcp-guide-left">
                <!-- Quick Start Guide -->
                <div class="aiohm-card aiohm-quick-start-detailed">
                    <h2><?php esc_html_e('Quick Start Guide', 'aiohm-knowledge-assistant'); ?></h2>
                    <p><?php esc_html_e('Follow these steps to get started with MCP integration.', 'aiohm-knowledge-assistant'); ?></p>

                    <div class="quick-start-steps-detailed">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4><?php esc_html_e('Enable MCP API', 'aiohm-knowledge-assistant'); ?></h4>
                                <p><?php esc_html_e('Turn on the MCP API to allow external connections.', 'aiohm-knowledge-assistant'); ?></p>
                                <div class="step-action">
                                    <form id="mcp-settings-form" class="inline-form mcp-settings-form">
                                        <?php wp_nonce_field('aiohm_mcp_settings_nonce', 'mcp_settings_nonce'); ?>
                                        <input type="hidden" name="mcp_rate_limit" value="<?php echo esc_attr($settings['mcp_rate_limit'] ?? 1000); ?>">
                                        <input type="hidden" name="mcp_require_https" value="1">
                                        
                                        <label class="aiohm-toggle">
                                            <input type="hidden" name="mcp_enabled" value="0">
                                            <input type="checkbox" name="mcp_enabled" value="1" <?php checked($mcp_enabled); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <span class="toggle-label"><?php echo $mcp_enabled ? esc_html__('MCP API Enabled', 'aiohm-knowledge-assistant') : esc_html__('Enable MCP API', 'aiohm-knowledge-assistant'); ?></span>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4><?php esc_html_e('Generate API Token', 'aiohm-knowledge-assistant'); ?></h4>
                                <p><?php esc_html_e('Create secure tokens for external applications to access your knowledge base.', 'aiohm-knowledge-assistant'); ?></p>
                                
                                <!-- Create New Token Form -->
                                <div class="create-token-section">
                                    <form id="create-token-form">
                                        <?php wp_nonce_field('aiohm_mcp_nonce', 'nonce'); ?>
                                        
                                        <table class="form-table">
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Token Name', 'aiohm-knowledge-assistant'); ?></th>
                                                <td>
                                                    <input type="text" name="token_name" placeholder="<?php esc_attr_e('e.g., Mobile App, Claude Desktop', 'aiohm-knowledge-assistant'); ?>" required maxlength="255">
                                                    <p class="description"><?php esc_html_e('A descriptive name to identify this token', 'aiohm-knowledge-assistant'); ?></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Token Type', 'aiohm-knowledge-assistant'); ?></th>
                                                <td>
                                                    <fieldset>
                                                        <label>
                                                            <input type="radio" name="token_type" value="private" checked>
                                                            <strong><?php esc_html_e('Private', 'aiohm-knowledge-assistant'); ?></strong>
                                                            <p class="description"><?php esc_html_e('Access to all knowledge base content including private posts, drafts, and uploads. Requires active Private level membership.', 'aiohm-knowledge-assistant'); ?></p>
                                                        </label><br>
                                                        <label>
                                                            <input type="radio" name="token_type" value="public">
                                                            <strong><?php esc_html_e('Public', 'aiohm-knowledge-assistant'); ?></strong>
                                                            <p class="description"><?php esc_html_e('Access limited to published WordPress posts and pages only. Works without membership restrictions.', 'aiohm-knowledge-assistant'); ?></p>
                                                        </label>
                                                    </fieldset>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Permissions', 'aiohm-knowledge-assistant'); ?></th>
                                                <td>
                                                    <fieldset>
                                                        <label>
                                                            <input type="checkbox" name="permissions[]" value="read_kb" checked>
                                                            <?php esc_html_e('Read Knowledge Base', 'aiohm-knowledge-assistant'); ?>
                                                            <span class="permission-desc"><?php esc_html_e('Query and retrieve knowledge base entries', 'aiohm-knowledge-assistant'); ?></span>
                                                        </label><br>
                                                        <label>
                                                            <input type="checkbox" name="permissions[]" value="read_write_kb">
                                                            <?php esc_html_e('Read & Write Knowledge Base', 'aiohm-knowledge-assistant'); ?>
                                                            <span class="permission-desc"><?php esc_html_e('Add, update, and delete knowledge base entries', 'aiohm-knowledge-assistant'); ?></span>
                                                        </label>
                                                    </fieldset>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Expiration', 'aiohm-knowledge-assistant'); ?></th>
                                                <td>
                                                    <select name="expires_days">
                                                        <option value=""><?php esc_html_e('Never expires', 'aiohm-knowledge-assistant'); ?></option>
                                                        <option value="7"><?php esc_html_e('7 days', 'aiohm-knowledge-assistant'); ?></option>
                                                        <option value="30"><?php esc_html_e('30 days', 'aiohm-knowledge-assistant'); ?></option>
                                                        <option value="90"><?php esc_html_e('90 days', 'aiohm-knowledge-assistant'); ?></option>
                                                        <option value="365"><?php esc_html_e('1 year', 'aiohm-knowledge-assistant'); ?></option>
                                                    </select>
                                                    <p class="description"><?php esc_html_e('Set token expiration for enhanced security', 'aiohm-knowledge-assistant'); ?></p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p class="submit">
                                            <button type="submit" class="button button-primary"><?php esc_html_e('Generate Token', 'aiohm-knowledge-assistant'); ?></button>
                                        </p>
                                    </form>
                                </div>

                                <!-- Token Success Display Section -->
                                <div id="token-success-display" class="aiohm-card">
                                    <h3><?php esc_html_e('Token Generated Successfully', 'aiohm-knowledge-assistant'); ?></h3>
                                    <p><?php esc_html_e('Please copy and save this token immediately. It will not be shown again for security reasons.', 'aiohm-knowledge-assistant'); ?></p>
                                    
                                    <div class="token-display">
                                        <label><?php esc_html_e('API Token:', 'aiohm-knowledge-assistant'); ?></label>
                                        <div class="token-copy-group">
                                            <input type="text" id="generated-token" readonly>
                                            <button type="button" id="copy-token" class="button button-secondary">
                                                <?php esc_html_e('Copy', 'aiohm-knowledge-assistant'); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="token-usage-info">
                                        <h4><?php esc_html_e('How to Use This Token', 'aiohm-knowledge-assistant'); ?></h4>
                                        <p><?php esc_html_e('Include this token in the Authorization header of your MCP requests:', 'aiohm-knowledge-assistant'); ?></p>
                                        <code>Authorization: Bearer YOUR_TOKEN_HERE</code>
                                        
                                        <h4><?php esc_html_e('Example MCP Manifest URL', 'aiohm-knowledge-assistant'); ?></h4>
                                        <code><?php echo esc_url(home_url('/wp-json/aiohm/mcp/v1/manifest')); ?></code>
                                    </div>
                                    
                                    <button type="button" id="hide-token-success" class="button button-primary"><?php esc_html_e('Close', 'aiohm-knowledge-assistant'); ?></button>
                                </div>

                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4><?php esc_html_e('Use MCP Endpoint', 'aiohm-knowledge-assistant'); ?></h4>
                                <p><?php esc_html_e('Configure your AI assistant with the endpoint URL:', 'aiohm-knowledge-assistant'); ?></p>
                                <div class="connection-info">
                                    <code id="mcp-endpoint-url-2"><?php echo esc_url(home_url('/wp-json/aiohm/mcp/v1/')); ?></code>
                                    <button type="button" id="copy-endpoint-url-2" class="button button-small"><?php esc_html_e('Copy', 'aiohm-knowledge-assistant'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="aiohm-mcp-guide-right">
                <!-- Documentation Section -->
                <div class="aiohm-card aiohm-mcp-docs">
                    <div class="mcp-docs-header">
                        <h2><?php esc_html_e('MCP Integration Guide', 'aiohm-knowledge-assistant'); ?></h2>
                        <button type="button" id="view-token-details-btn" class="button button-secondary" style="display:none;"><?php esc_html_e('View Token Details', 'aiohm-knowledge-assistant'); ?></button>
                    </div>
            
                    <div class="docs-tabs">
                        <button class="docs-tab-button active" data-tab="overview"><?php esc_html_e('Overview', 'aiohm-knowledge-assistant'); ?></button>
                        <button class="docs-tab-button" data-tab="endpoints"><?php esc_html_e('Endpoints', 'aiohm-knowledge-assistant'); ?></button>
                        <button class="docs-tab-button" data-tab="examples"><?php esc_html_e('Examples', 'aiohm-knowledge-assistant'); ?></button>
                    </div>

                    <div class="docs-tab-content active" id="docs-overview">
                        <h4><?php esc_html_e('Getting Started', 'aiohm-knowledge-assistant'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Enable MCP API in the settings above', 'aiohm-knowledge-assistant'); ?></li>
                            <li><?php esc_html_e('Generate an API token with appropriate permissions', 'aiohm-knowledge-assistant'); ?></li>
                            <li><?php esc_html_e('Configure your MCP client to use the manifest URL', 'aiohm-knowledge-assistant'); ?></li>
                            <li><?php esc_html_e('Start making API calls to query or modify your knowledge base', 'aiohm-knowledge-assistant'); ?></li>
                        </ol>

                        <h4><?php esc_html_e('Available Capabilities', 'aiohm-knowledge-assistant'); ?></h4>
                        <ul>
                            <li><strong>queryKB</strong> - <?php esc_html_e('Search knowledge base with semantic understanding', 'aiohm-knowledge-assistant'); ?></li>
                            <li><strong>getKBEntry</strong> - <?php esc_html_e('Retrieve specific knowledge base entry by ID', 'aiohm-knowledge-assistant'); ?></li>
                            <li><strong>listKBEntries</strong> - <?php esc_html_e('List entries with pagination and filtering', 'aiohm-knowledge-assistant'); ?></li>
                            <li><strong>addKBEntry</strong> - <?php esc_html_e('Add new content to knowledge base (requires write permission)', 'aiohm-knowledge-assistant'); ?></li>
                            <li><strong>updateKBEntry</strong> - <?php esc_html_e('Update existing entries (requires write permission)', 'aiohm-knowledge-assistant'); ?></li>
                            <li><strong>deleteKBEntry</strong> - <?php esc_html_e('Delete entries (requires write permission)', 'aiohm-knowledge-assistant'); ?></li>
                        </ul>
                    </div>

                    <div class="docs-tab-content" id="docs-endpoints">
                        <h4><?php esc_html_e('MCP Endpoints', 'aiohm-knowledge-assistant'); ?></h4>
                        
                        <div class="endpoint-item">
                            <div class="endpoint-header">
                                <span class="method get">GET</span>
                                <code>/wp-json/aiohm/mcp/v1/manifest</code>
                            </div>
                            <p><?php esc_html_e('Returns the MCP manifest describing available capabilities and parameters.', 'aiohm-knowledge-assistant'); ?></p>
                        </div>

                        <div class="endpoint-item">
                            <div class="endpoint-header">
                                <span class="method post">POST</span>
                                <code>/wp-json/aiohm/mcp/v1/call</code>
                            </div>
                            <p><?php esc_html_e('Execute MCP actions like queryKB, addKBEntry, etc.', 'aiohm-knowledge-assistant'); ?></p>
                        </div>

                        <div class="endpoint-item">
                            <div class="endpoint-header">
                                <span class="method post">POST</span>
                                <code>/wp-json/aiohm/mcp/v1/validate</code>
                            </div>
                            <p><?php esc_html_e('Validate API token and check permissions.', 'aiohm-knowledge-assistant'); ?></p>
                        </div>
                    </div>

                    <div class="docs-tab-content" id="docs-examples">
                        <h4><?php esc_html_e('Example API Calls', 'aiohm-knowledge-assistant'); ?></h4>
                        
                        <div class="example-item">
                            <h5><?php esc_html_e('Query Knowledge Base', 'aiohm-knowledge-assistant'); ?></h5>
                            <pre><code>curl -X POST "<?php echo esc_url(home_url('/wp-json/aiohm/mcp/v1/call')); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "queryKB",
    "parameters": {
      "query": "How to integrate WordPress",
      "limit": 5
    }
  }'</code></pre>
                        </div>

                        <div class="example-item">
                            <h5><?php esc_html_e('Add Knowledge Base Entry', 'aiohm-knowledge-assistant'); ?></h5>
                            <pre><code>curl -X POST "<?php echo esc_url(home_url('/wp-json/aiohm/mcp/v1/call')); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "addKBEntry",
    "parameters": {
      "title": "Integration Guide",
      "content": "This is how to integrate...",
      "content_type": "mcp_external"
    }
  }'</code></pre>
                        </div>

                        <div class="example-item">
                            <h5><?php esc_html_e('Get MCP Manifest', 'aiohm-knowledge-assistant'); ?></h5>
                            <pre><code>curl -X GET "<?php echo esc_url(home_url('/wp-json/aiohm/mcp/v1/manifest')); ?>" \
  -H "Authorization: Bearer YOUR_TOKEN"</code></pre>
                        </div>
                    </div>
                </div>
                
                <!-- Token Details Collapsible Box - Outside the MCP Integration Guide -->
                <div id="token-details-box" class="aiohm-card token-details-collapsible" style="display:none;">
                    <div class="token-details-content">
                        <div class="token-details-header">
                            <h4><?php esc_html_e('Token Details', 'aiohm-knowledge-assistant'); ?></h4>
                            <button type="button" id="hide-token-details-box" class="button-link"><?php esc_html_e('Hide', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                        <div class="token-details-body">
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Name:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-name"></span>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Token Preview:', 'aiohm-knowledge-assistant'); ?></strong>
                                <code id="detail-token-preview"></code>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Type:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-type"></span>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Permissions:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-permissions"></span>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Status:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-status"></span>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Created By:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-creator"></span>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Created:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-created"></span>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Last Used:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-last-used"></span>
                            </div>
                            <div class="token-detail-row">
                                <strong><?php esc_html_e('Expires:', 'aiohm-knowledge-assistant'); ?></strong>
                                <span id="detail-token-expires"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Tokens Section - Full Width -->
        <div class="aiohm-card aiohm-existing-tokens">
            <h2><?php esc_html_e('Existing Tokens', 'aiohm-knowledge-assistant'); ?></h2>
            <p><?php esc_html_e('Manage your existing API tokens and monitor their usage.', 'aiohm-knowledge-assistant'); ?></p>
            
            <div id="tokens-list">
                <div class="loading-tokens"><?php esc_html_e('Loading tokens...', 'aiohm-knowledge-assistant'); ?></div>
            </div>
        </div>
        <?php else: ?>
        <!-- MCP Disabled State - Show toggle to enable -->
        <div class="aiohm-card">
            <h2><?php esc_html_e('MCP API Settings', 'aiohm-knowledge-assistant'); ?></h2>
            <p><?php esc_html_e('The MCP (Model Context Protocol) API allows external AI assistants to access your knowledge base. Enable it to get started.', 'aiohm-knowledge-assistant'); ?></p>
            
            <div class="mcp-enable-section">
                <form id="mcp-settings-form-disabled" class="inline-form mcp-settings-form">
                    <?php wp_nonce_field('aiohm_mcp_settings_nonce', 'mcp_settings_nonce'); ?>
                    <input type="hidden" name="mcp_rate_limit" value="<?php echo esc_attr($settings['mcp_rate_limit'] ?? 1000); ?>">
                    <input type="hidden" name="mcp_require_https" value="1">
                    
                    <label class="aiohm-toggle">
                        <input type="hidden" name="mcp_enabled" value="0">
                        <input type="checkbox" name="mcp_enabled" value="1" <?php checked($mcp_enabled); ?>>
                        <span class="slider"></span>
                    </label>
                    <span class="toggle-label"><?php echo $mcp_enabled ? esc_html__('MCP API Enabled', 'aiohm-knowledge-assistant') : esc_html__('Enable MCP API', 'aiohm-knowledge-assistant'); ?></span>
                </form>
            </div>
            
            <div class="mcp-disabled-info">
                <h4><?php esc_html_e('When enabled, you will be able to:', 'aiohm-knowledge-assistant'); ?></h4>
                <ul>
                    <li><?php esc_html_e('Connect Claude Desktop to your knowledge base', 'aiohm-knowledge-assistant'); ?></li>
                    <li><?php esc_html_e('Create and manage API tokens', 'aiohm-knowledge-assistant'); ?></li>
                    <li><?php esc_html_e('Share knowledge with external AI tools', 'aiohm-knowledge-assistant'); ?></li>
                    <li><?php esc_html_e('Integrate with third-party applications', 'aiohm-knowledge-assistant'); ?></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; // End main membership check ?>
