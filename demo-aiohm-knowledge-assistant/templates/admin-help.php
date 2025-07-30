<?php
/**
 * Admin Help page template - Support Center with User Journey
 * OHM branded design with debug tools and user journey
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current user info for pre-filling forms
$current_user = wp_get_current_user();
$user_email = $current_user->user_email;
$site_url = get_site_url();
$plugin_version = AIOHM_KB_VERSION;
$wp_version = get_bloginfo('version');
$php_version = PHP_VERSION;

// Get plugin settings for debugging
$settings = AIOHM_KB_Assistant::get_settings();
$active_provider = $settings['default_ai_provider'] ?? 'not set';
?>

<div class="wrap aiohm-help-page">

    <div class="aiohm-help-container">
        
        <!-- Left Column: Debug & Support Tools -->
        <div class="aiohm-help-main">
            
            <!-- System Information -->
            <div class="support-card">
                <div class="card-header">
                    <span class="dashicons dashicons-info card-icon" style="color: #1f5014;"></span>
                    <h2 style="color: #1f5014;"><?php esc_html_e('System Information', 'aiohm-knowledge-assistant'); ?></h2>
                </div>
                <div class="card-content">
                    <div class="system-info-grid">
                        <div class="info-item">
                            <strong><?php esc_html_e('Plugin Version:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span><?php echo esc_html($plugin_version); ?></span>
                        </div>
                        <div class="info-item">
                            <strong><?php esc_html_e('WordPress Version:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span><?php echo esc_html($wp_version); ?></span>
                        </div>
                        <div class="info-item">
                            <strong><?php esc_html_e('PHP Version:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span><?php echo esc_html($php_version); ?></span>
                        </div>
                        <div class="info-item">
                            <strong><?php esc_html_e('Active AI Provider:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span><?php echo esc_html($active_provider); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Issue Section -->
            <div class="support-card">
                <div class="card-header">
                    <span class="dashicons dashicons-admin-tools card-icon" style="color: #1f5014;"></span>
                    <h2 style="color: #1f5014;"><?php esc_html_e('Report Issue', 'aiohm-knowledge-assistant'); ?></h2>
                </div>
                <div class="card-content">
                    <div class="form-row-columns">
                        <!-- Left Column: Debug Tools -->
                        <div class="form-column">
                            <h3 style="color: #1f5014;"><?php esc_html_e('Debug Tools', 'aiohm-knowledge-assistant'); ?></h3>
                            <div class="debug-buttons" style="display: flex; flex-direction: column; gap: 10px;">
                                <button id="check-api-connections" class="button button-secondary">
                                    <span class="dashicons dashicons-cloud"></span>
                                    <?php esc_html_e('Test API Connections', 'aiohm-knowledge-assistant'); ?>
                                </button>
                                
                                <button id="collect-debug-info" class="button button-primary">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Collect Debug Information', 'aiohm-knowledge-assistant'); ?>
                                </button>
                            </div>
                            
                            <!-- Debug Output Area -->
                            <div id="debug-output" class="debug-output-area" style="display: none;">
                                <h4><?php esc_html_e('Debug Information', 'aiohm-knowledge-assistant'); ?></h4>
                                <textarea id="debug-text" rows="12" readonly></textarea>
                                <div class="debug-actions-bottom">
                                    <button id="copy-debug-info" class="button button-secondary">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        <?php esc_html_e('Copy to Clipboard', 'aiohm-knowledge-assistant'); ?>
                                    </button>
                                    <button id="download-debug-info" class="button button-secondary">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php esc_html_e('Download as File', 'aiohm-knowledge-assistant'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Report Form -->
                        <div class="form-column">
                            <h3 style="color: #1f5014;"><?php esc_html_e('Basic Information', 'aiohm-knowledge-assistant'); ?></h3>
                            <form id="combined-support-form" class="support-form">
                                <div class="form-row">
                                    <label for="support-email"><?php esc_html_e('Your Email:', 'aiohm-knowledge-assistant'); ?></label>
                                    <input type="email" id="support-email" name="email" value="<?php echo esc_attr($user_email); ?>" required>
                                </div>
                                
                                <div class="form-row">
                                    <label for="report-type"><?php esc_html_e('Type:', 'aiohm-knowledge-assistant'); ?></label>
                                    <select id="report-type" name="type" required>
                                        <option value=""><?php esc_html_e('Select type...', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="bug-report"><?php esc_html_e('🐛 Bug Report', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="feature-request"><?php esc_html_e('💡 Feature Request', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="setup-help"><?php esc_html_e('🔧 Setup Help', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="api-issues"><?php esc_html_e('🌐 API Connection Issues', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="chat-not-working"><?php esc_html_e('💬 Chat/Assistant Not Working', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="knowledge-base"><?php esc_html_e('📚 Knowledge Base Issues', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="performance"><?php esc_html_e('⚡ Performance Issues', 'aiohm-knowledge-assistant'); ?></option>
                                        <option value="other"><?php esc_html_e('❓ Other', 'aiohm-knowledge-assistant'); ?></option>
                                    </select>
                                </div>
                        </div>
                    </div>
                    
                    <!-- Title Section -->
                    <div class="support-form-section" style="margin-top: 20px;">
                        <h3 style="color: #1f5014;"><?php esc_html_e('Title', 'aiohm-knowledge-assistant'); ?></h3>
                        <div class="form-row">
                            <input type="text" id="support-title" name="title" placeholder="Brief description of the issue or feature..." required>
                        </div>
                    </div>
                    
                    <!-- Description and Debug Information Section -->
                    <div class="support-form-section" style="margin-top: 20px;">
                        <h3 style="color: #1f5014;"><?php esc_html_e('Description and Debug Information', 'aiohm-knowledge-assistant'); ?></h3>
                        <div class="form-row form-row-columns">
                            <div class="form-column">
                                <label for="debug-information"><?php esc_html_e('Debug Information:', 'aiohm-knowledge-assistant'); ?></label>
                                <textarea id="debug-information" name="debug_info" rows="8" readonly>This will be automatically filled when you click the "Collect Debug Information" button above.</textarea>
                            </div>
                            <div class="form-column">
                                <label for="support-description"><?php esc_html_e('Detailed Description:', 'aiohm-knowledge-assistant'); ?></label>
                                <textarea id="support-description" name="description" rows="8" placeholder="For Bug Reports:
- What you were trying to do
- What happened instead
- Any error messages you saw
- Steps to reproduce the issue

For Feature Requests:
- What problem would this solve?
- How would it work?
- Who would benefit from this feature?
- Any examples or references?" required></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row checkbox-row" style="margin: 20px 0;">
                            <label class="checkbox-label">
                                <input type="checkbox" id="include-debug-info" name="include_debug" checked>
                                <?php esc_html_e('Include debug information with this report', 'aiohm-knowledge-assistant'); ?>
                            </label>
                        </div>
                        
                        <div style="text-align: right;">
                            <button type="submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php esc_html_e('Send Report', 'aiohm-knowledge-assistant'); ?>
                            </button>
                        </div>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Quick Resources -->
            <div class="support-card">
                <div class="card-header">
                    <span class="dashicons dashicons-media-document card-icon" style="color: #1f5014;"></span>
                    <h2 style="color: #1f5014;"><?php esc_html_e('Quick Resources', 'aiohm-knowledge-assistant'); ?></h2>
                </div>
                <div class="card-content">
                    <div class="resource-links">
                        <a href="https://aiohm.app/docs" target="_blank" class="resource-link">
                            <span class="dashicons dashicons-media-document"></span>
                            <div>
                                <strong><?php esc_html_e('Documentation', 'aiohm-knowledge-assistant'); ?></strong>
                                <p><?php esc_html_e('Complete setup and usage guides', 'aiohm-knowledge-assistant'); ?></p>
                            </div>
                        </a>
                        <a href="https://chat.whatsapp.com/I9A1LfBfW4i5dv4UWS27qD" target="_blank" class="resource-link">
                            <span class="dashicons dashicons-groups"></span>
                            <div>
                                <strong><?php esc_html_e('WhatsApp Support Group', 'aiohm-knowledge-assistant'); ?></strong>
                                <p><?php esc_html_e('Join our community for quick help and tips', 'aiohm-knowledge-assistant'); ?></p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: User Journey -->
        <div class="aiohm-help-sidebar">
            <div class="journey-card">
                <div class="journey-header">
                    <h2 style="color: #1f5014;"><?php esc_html_e('Your AIOHM Journey', 'aiohm-knowledge-assistant'); ?></h2>
                    <p><?php esc_html_e('From installation to MCP server - your path to AI transformation', 'aiohm-knowledge-assistant'); ?></p>
                </div>

                <div class="journey-steps">
                    <!-- Step 1: Installation & Setup -->
                    <div class="journey-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3><?php esc_html_e('Installation & Setup', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Install AIOHM plugin and configure your AI provider API keys.', 'aiohm-knowledge-assistant'); ?></p>
                            <div class="step-features">
                                <span class="feature-tag">✓ Plugin Installation</span>
                                <span class="feature-tag">✓ API Configuration</span>
                                <span class="feature-tag">✓ Basic Settings</span>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-settings')); ?>" class="step-button"><?php esc_html_e('Configure Settings', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>

                    <!-- Step 2: Knowledge Base Building -->
                    <div class="journey-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3><?php esc_html_e('Knowledge Base Building', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Scan your content and define your brand voice for personalized AI responses.', 'aiohm-knowledge-assistant'); ?></p>
                            <div class="step-features">
                                <span class="feature-tag">✓ Content Scanning</span>
                                <span class="feature-tag">✓ Brand Soul Definition</span>
                                <span class="feature-tag">✓ Knowledge Management</span>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-manage-kb')); ?>" class="step-button"><?php esc_html_e('Manage Knowledge', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>

                    <!-- Step 3: Dual-Mode Deployment -->
                    <div class="journey-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3><?php esc_html_e('Dual-Mode Deployment', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Deploy Mirror Mode for public visitors and Muse Mode for private creativity.', 'aiohm-knowledge-assistant'); ?></p>
                            <div class="step-features">
                                <span class="feature-tag">✓ Mirror Mode (Public)</span>
                                <span class="feature-tag">✓ Muse Mode (Private)</span>
                                <span class="feature-tag">✓ Shortcode Integration</span>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-mirror-mode')); ?>" class="step-button"><?php esc_html_e('Configure Modes', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>

                    <!-- Step 4: MCP Server Transformation -->
                    <div class="journey-step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3><?php esc_html_e('MCP Server Transformation', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Transform your WordPress into a Model Context Protocol server for external AI integration.', 'aiohm-knowledge-assistant'); ?></p>
                            <div class="step-features">
                                <span class="feature-tag">✓ MCP API Enable</span>
                                <span class="feature-tag">✓ Token Management</span>
                                <span class="feature-tag">✓ External AI Access</span>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-mcp')); ?>" class="step-button"><?php esc_html_e('Setup MCP Server', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>
                </div>

                <div class="journey-footer">
                    <p><?php esc_html_e('Need help with any step? Use the support form to get personalized assistance.', 'aiohm-knowledge-assistant'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="support-messages" class="support-messages" style="display: none;"></div>
</div>

<!-- Hidden fields for system information -->
<input type="hidden" id="system-info" value="<?php echo esc_attr(wp_json_encode([
    'plugin_version' => $plugin_version,
    'wp_version' => $wp_version,
    'php_version' => $php_version,
    'site_url' => $site_url,
    'active_provider' => $active_provider,
    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'Unknown'
])); ?>">