<?php
/**
 * Admin settings template - Reorganized with OHM branding and separated free/paid services.
 */
if (!defined('ABSPATH')) exit;

// --- Start: Data Fetching and Status Checks ---
$settings = wp_parse_args(AIOHM_KB_Assistant::get_settings(), []);
$can_access_settings = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_club_access();
$has_private_access = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_private_access();
// --- End: Data Fetching and Status Checks ---
?>

<div class="wrap aiohm-settings-page">
    <h1><?php esc_html_e('AIOHM Settings', 'aiohm-knowledge-assistant'); ?></h1>
    
    <p class="page-description"><?php esc_html_e('Manage your AI provider connections, enable Mirror Mode, Muse Mode, and now MCP (Model Context Protocol) for seamless integration with external tools.', 'aiohm-knowledge-assistant'); ?></p>
    
    <div id="aiohm-admin-notice" class="notice is-dismissible" style="display:none; margin-top: 10px;" tabindex="-1" role="alert" aria-live="polite"></div>

    <?php 
    // Display WordPress settings notices
    settings_errors('aiohm_kb_settings'); 
    ?>

    <form method="post" action="options.php">
        <?php settings_fields('aiohm_kb_settings'); ?>

        <!-- Configuration & Privacy Section -->
        <div class="aiohm-card">
            <h2><?php esc_html_e('Configuration & Privacy Settings', 'aiohm-knowledge-assistant'); ?></h2>
            <p class="aiohm-section-description"><?php esc_html_e('Configure your primary AI provider and privacy consent preferences.', 'aiohm-knowledge-assistant'); ?></p>
            
            <div class="aiohm-shareai-two-column-layout">
                <!-- Left Column: Default AI Provider -->
                <div class="aiohm-shareai-settings-column">
                    <div class="aiohm-shareai-settings-box">
                        <?php
                        // Check if user has any API keys configured
                        $has_openai = !empty($settings['openai_api_key']);
                        $has_gemini = !empty($settings['gemini_api_key']);
                        $has_claude = !empty($settings['claude_api_key']);
                        $has_shareai = !empty($settings['shareai_api_key']);
                        $has_ollama = $has_private_access && !empty($settings['private_llm_server_url']);
                        $has_any_api = $has_openai || $has_gemini || $has_claude || $has_shareai || $has_ollama;
                        ?>
                        
                        <?php if ($has_any_api): ?>
                        <h3 class="aiohm-service-title">
                            🤖 <?php esc_html_e('Default AI Provider', 'aiohm-knowledge-assistant'); ?>
                        </h3>
                        
                        <div class="shareai-setting-row">
                            <label for="default_ai_provider" class="shareai-inline-label"><?php esc_html_e('Primary AI Service:', 'aiohm-knowledge-assistant'); ?></label>
                            <div class="aiohm-api-key-wrapper">
                                <select id="default_ai_provider" name="aiohm_kb_settings[default_ai_provider]" class="regular-text">
                                    <?php if ($has_shareai || $has_ollama): ?>
                                    <optgroup label="<?php esc_attr_e('Free AI Services', 'aiohm-knowledge-assistant'); ?>">
                                        <?php if ($has_shareai): ?>
                                        <option value="shareai" <?php selected($settings['default_ai_provider'] ?? '', 'shareai'); ?>>ShareAI</option>
                                        <?php endif; ?>
                                        <?php if ($has_ollama): ?>
                                        <option value="ollama" <?php selected($settings['default_ai_provider'] ?? '', 'ollama'); ?>>Ollama Server</option>
                                        <?php endif; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                    <?php if ($has_openai || $has_gemini || $has_claude): ?>
                                    <optgroup label="<?php esc_attr_e('Premium AI Services', 'aiohm-knowledge-assistant'); ?>">
                                        <?php if ($has_openai): ?>
                                        <option value="openai" <?php selected($settings['default_ai_provider'] ?? 'openai', 'openai'); ?>>OpenAI</option>
                                        <?php endif; ?>
                                        <?php if ($has_gemini): ?>
                                        <option value="gemini" <?php selected($settings['default_ai_provider'] ?? '', 'gemini'); ?>>Gemini</option>
                                        <?php endif; ?>
                                        <?php if ($has_claude): ?>
                                        <option value="claude" <?php selected($settings['default_ai_provider'] ?? '', 'claude'); ?>>Claude</option>
                                        <?php endif; ?>
                                    </optgroup>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="button aiohm-shareai-btn aiohm-save-default-provider"><?php esc_html_e('Save', 'aiohm-knowledge-assistant'); ?></button>
                            </div>
                        </div>
                        
                        <p class="description">
                            <?php esc_html_e('Select your primary AI service from the providers you have configured below.', 'aiohm-knowledge-assistant'); ?>
                        </p>
                        <?php else: ?>
                        <div class="aiohm-notice-box">
                            <h3><?php esc_html_e('No AI Providers Configured', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Please configure at least one AI provider below to select a default service.', 'aiohm-knowledge-assistant'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column: Privacy & Consent -->
                <div class="aiohm-shareai-info-column">
                    <div class="aiohm-model-info-box">
                        <h3 class="aiohm-service-title">
                            🔒 <?php esc_html_e('Privacy & Consent', 'aiohm-knowledge-assistant'); ?>
                        </h3>
                        
                        <div class="shareai-setting-row" style="margin-bottom: 15px;">
                            <label for="external_api_consent" style="display: flex; align-items: flex-start; gap: 8px;">
                                <input type="checkbox" id="external_api_consent" name="aiohm_kb_settings[external_api_consent]" value="1" <?php checked(isset($settings['external_api_consent']) ? $settings['external_api_consent'] : false); ?> style="margin-top: 2px;" />
                                <span>
                                    <strong><?php esc_html_e('I consent to making external API calls to AI providers', 'aiohm-knowledge-assistant'); ?></strong>
                                </span>
                            </label>
                        </div>
                        
                        <div class="shareai-setting-row">
                            <button type="button" class="button aiohm-shareai-btn aiohm-save-privacy-consent"><?php esc_html_e('Save Privacy Settings', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                        
                        <div class="aiohm-privacy-notice">
                            <p><strong><?php esc_html_e('Data Processing Notice:', 'aiohm-knowledge-assistant'); ?></strong></p>
                            <p><?php esc_html_e('This plugin makes API calls to external AI services (OpenAI, Google Gemini, Claude, ShareAI, etc.) to process your content and provide AI responses.', 'aiohm-knowledge-assistant'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Free AI Services Section -->
        <div class="aiohm-card">
            <h2><?php esc_html_e('Free AI Services & Connections', 'aiohm-knowledge-assistant'); ?></h2>
            <p class="aiohm-section-description"><?php esc_html_e('Get started with AI for free using these services. Perfect for testing and development.', 'aiohm-knowledge-assistant'); ?></p>
            
            <!-- ShareAI Configuration -->
            <h3 class="aiohm-service-title">
                <img src="<?php echo esc_url(AIOHM_KB_PLUGIN_URL . 'assets/images/shareai-icon.jpeg'); ?>" alt="ShareAI" class="aiohm-service-icon" />
                <?php esc_html_e('ShareAI', 'aiohm-knowledge-assistant'); ?>
            </h3>
            <div class="aiohm-shareai-two-column-layout">
                            <!-- Left Column: Settings -->
                            <div class="aiohm-shareai-settings-column">
                                <div class="aiohm-shareai-settings-box">
                                    <div class="shareai-setting-row">
                                        <label for="shareai_api_key" class="shareai-inline-label"><?php esc_html_e('API Key:', 'aiohm-knowledge-assistant'); ?></label>
                                        <div class="aiohm-api-key-wrapper">
                                            <input type="password" id="shareai_api_key" name="aiohm_kb_settings[shareai_api_key]" value="<?php echo esc_attr($settings['shareai_api_key'] ?? ''); ?>" placeholder="Enter your ShareAI API key" class="regular-text">
                                            <button type="button" class="button button-secondary aiohm-show-hide-key" data-target="shareai_api_key"><span class="dashicons dashicons-visibility"></span></button>
                                            <button type="button" class="button aiohm-shareai-btn aiohm-test-api-key" data-target="shareai_api_key" data-type="shareai"><?php esc_html_e('Test API & Usage', 'aiohm-knowledge-assistant'); ?></button>
                                            <button type="button" class="button aiohm-shareai-btn aiohm-save-api-key" data-target="shareai_api_key" data-type="shareai"><?php esc_html_e('Save', 'aiohm-knowledge-assistant'); ?></button>
                                        </div>
                                    </div>
                                    
                                    <div class="shareai-setting-row">
                                        <label for="shareai_model" class="shareai-inline-label"><?php esc_html_e('Model:', 'aiohm-knowledge-assistant'); ?></label>
                                        <select id="shareai_model" name="aiohm_kb_settings[shareai_model]" class="regular-text">
                                            <optgroup label="Command Models">
                                                <option value="deepseek-r1:7b" <?php selected($settings['shareai_model'] ?? '', 'deepseek-r1:7b'); ?>>DeepSeek R1 7B</option>
                                                <option value="deepseek-r1:14b" <?php selected($settings['shareai_model'] ?? '', 'deepseek-r1:14b'); ?>>DeepSeek R1 14B</option>
                                                <option value="deepseek-r1:32b" <?php selected($settings['shareai_model'] ?? '', 'deepseek-r1:32b'); ?>>DeepSeek R1 32B</option>
                                                <option value="deepseek-r1:671b" <?php selected($settings['shareai_model'] ?? '', 'deepseek-r1:671b'); ?>>DeepSeek R1 671B</option>
                                                <option value="llama4:17b-scout-16e-instruct-fp16" <?php selected($settings['shareai_model'] ?? 'llama4:17b-scout-16e-instruct-fp16', 'llama4:17b-scout-16e-instruct-fp16'); ?>>Llama 4 17B Scout (Recommended)</option>
                                                <option value="llama4:90b-instruct-fp16" <?php selected($settings['shareai_model'] ?? '', 'llama4:90b-instruct-fp16'); ?>>Llama 4 90B Instruct</option>
                                                <option value="qwen2.5:0.5b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5:0.5b'); ?>>Qwen 2.5 0.5B</option>
                                                <option value="qwen2.5:1.5b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5:1.5b'); ?>>Qwen 2.5 1.5B</option>
                                                <option value="qwen2.5:3b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5:3b'); ?>>Qwen 2.5 3B</option>
                                                <option value="qwen2.5:7b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5:7b'); ?>>Qwen 2.5 7B</option>
                                                <option value="qwen2.5:14b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5:14b'); ?>>Qwen 2.5 14B</option>
                                                <option value="qwen2.5:32b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5:32b'); ?>>Qwen 2.5 32B</option>
                                                <option value="qwen2.5:72b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5:72b'); ?>>Qwen 2.5 72B</option>
                                            </optgroup>
                                            <optgroup label="Chat Models">
                                                <option value="llama3.2:1b" <?php selected($settings['shareai_model'] ?? '', 'llama3.2:1b'); ?>>Llama 3.2 1B</option>
                                                <option value="llama3.2:3b" <?php selected($settings['shareai_model'] ?? '', 'llama3.2:3b'); ?>>Llama 3.2 3B</option>
                                                <option value="llama3.3:70b" <?php selected($settings['shareai_model'] ?? '', 'llama3.3:70b'); ?>>Llama 3.3 70B</option>
                                                <option value="qwen2.5-coder:1.5b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5-coder:1.5b'); ?>>Qwen 2.5 Coder 1.5B</option>
                                                <option value="qwen2.5-coder:7b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5-coder:7b'); ?>>Qwen 2.5 Coder 7B</option>
                                                <option value="qwen2.5-coder:14b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5-coder:14b'); ?>>Qwen 2.5 Coder 14B</option>
                                                <option value="qwen2.5-coder:32b" <?php selected($settings['shareai_model'] ?? '', 'qwen2.5-coder:32b'); ?>>Qwen 2.5 Coder 32B</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    
                                    <div class="shareai-usage-notice">
                                        <p><strong><?php esc_html_e('Affordable AI tokens with ShareAI', 'aiohm-knowledge-assistant'); ?></strong><br>
                                        <?php esc_html_e('ShareAI connects you to a global compute network, making AI more affordable or free if you share your device\'s idle power.', 'aiohm-knowledge-assistant'); ?></p>
                                        
                                        <p><strong><?php esc_html_e('To get started:', 'aiohm-knowledge-assistant'); ?></strong><br>
                                        <?php 
                                            printf(
                                                wp_kses(
                                                    // translators: %s is the URL to the ShareAI Console
                                                    __('Generate your API key in the <a href="%s" target="_blank">ShareAI Console</a>, add it to AIOHM settings, and ensure your chosen model is active.', 'aiohm-knowledge-assistant'),
                                                    array(
                                                        'a' => array(
                                                            'href' => array(),
                                                            'target' => array()
                                                        )
                                                    )
                                                ),
                                                esc_url('https://console.shareai.now/app/api-key/?utm_source=AIOHM&utm_medium=plugin&utm_campaign=shareai_integration')
                                            ); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Model Information -->
                            <div class="aiohm-shareai-info-column">
                                <div class="aiohm-model-info-box">
                                    <h4 class="aiohm-model-info-title"><?php esc_html_e('Model Information', 'aiohm-knowledge-assistant'); ?></h4>
                                    <div id="aiohm-model-info-content" class="aiohm-model-info-content">
                                        <!-- Default placeholder content -->
                                        <div class="aiohm-model-placeholder">
                                            <div class="aiohm-placeholder-icon">📋</div>
                                            <h5><?php esc_html_e('Select a Model and Save', 'aiohm-knowledge-assistant'); ?></h5>
                                            <p><?php esc_html_e('Choose your preferred ShareAI model from the dropdown and click "Save" to view detailed information about the model\'s capabilities, training, and best use cases.', 'aiohm-knowledge-assistant'); ?></p>
                                            <div class="aiohm-placeholder-steps">
                                                <div class="aiohm-step">
                                                    <span class="aiohm-step-number">1</span>
                                                    <span><?php esc_html_e('Select model from dropdown', 'aiohm-knowledge-assistant'); ?></span>
                                                </div>
                                                <div class="aiohm-step">
                                                    <span class="aiohm-step-number">2</span>
                                                    <span><?php esc_html_e('Click "Save" button', 'aiohm-knowledge-assistant'); ?></span>
                                                </div>
                                                <div class="aiohm-step">
                                                    <span class="aiohm-step-number">3</span>
                                                    <span><?php esc_html_e('View model details here', 'aiohm-knowledge-assistant'); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
        </div>

        <!-- Ollama Server Section with Lock Overlay -->
        <div class="aiohm-premium-settings-wrapper <?php if (!$has_private_access) echo esc_attr('is-locked'); ?>">
            <?php if (!$has_private_access): ?>
                <div class="aiohm-settings-locked-overlay">
                    <div class="lock-content">
                        <div class="lock-icon">🔒</div>
                        <h2><?php esc_html_e('Unlock AIOHM Private Features', 'aiohm-knowledge-assistant'); ?></h2>
                        <p><?php esc_html_e('Ollama server configuration requires AIOHM Private membership. Upgrade to use self-hosted AI models for maximum privacy and control.', 'aiohm-knowledge-assistant'); ?></p>
                        <div class="unlock-features-list">
                            <h4><?php esc_html_e('Private membership also unlocks:', 'aiohm-knowledge-assistant'); ?></h4>
                            <ul>
                                <li>🖥️ <?php esc_html_e('Unlock AIOHM Private Features', 'aiohm-knowledge-assistant'); ?></li>
                                <li>🔌 <?php esc_html_e('MCP Feature - Model Context Protocol API access', 'aiohm-knowledge-assistant'); ?></li>
                                <li>🔐 <?php esc_html_e('Full Privacy & Confidentiality', 'aiohm-knowledge-assistant'); ?></li>
                                <li>🧠 <?php esc_html_e('Personalized LLM Connection', 'aiohm-knowledge-assistant'); ?></li>
                            </ul>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-license&tab=club')); ?>" class="button button-primary"><?php esc_html_e('Upgrade to Private', 'aiohm-knowledge-assistant'); ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="aiohm-card">
                <h3 class="aiohm-service-title">
                    <img src="<?php echo esc_url(AIOHM_KB_PLUGIN_URL . 'assets/images/ollama-icon.png'); ?>" alt="Ollama" class="aiohm-service-icon" />
                    <?php esc_html_e('Ollama Server (Private)', 'aiohm-knowledge-assistant'); ?>
                </h3>
                <div class="aiohm-shareai-two-column-layout">
                    <!-- Left Column: Settings -->
                    <div class="aiohm-shareai-settings-column">
                        <div class="aiohm-shareai-settings-box">
                            <div class="shareai-setting-row">
                                <label for="server_preset" class="shareai-inline-label"><?php esc_html_e('Server Type:', 'aiohm-knowledge-assistant'); ?></label>
                                <select id="server_preset" class="server-preset-select regular-text" <?php disabled(!$has_private_access); ?>>
                                    <option value="custom"><?php esc_html_e('Custom Server', 'aiohm-knowledge-assistant'); ?></option>
                                    <option value="localhost"><?php esc_html_e('Local Ollama (Download Required)', 'aiohm-knowledge-assistant'); ?></option>
                                    <option value="servbay"><?php esc_html_e('ServBay Ollama (ollama.servbay.host)', 'aiohm-knowledge-assistant'); ?></option>
                                </select>
                            </div>
                            
                            <div class="shareai-setting-row">
                                <label for="private_llm_server_url" class="shareai-inline-label"><?php esc_html_e('Server URL:', 'aiohm-knowledge-assistant'); ?></label>
                                <div class="aiohm-api-key-wrapper">
                                    <input type="url" id="private_llm_server_url" name="aiohm_kb_settings[private_llm_server_url]" value="<?php echo esc_attr($settings['private_llm_server_url'] ?? ''); ?>" class="regular-text" placeholder="http://localhost:11434" <?php disabled(!$has_private_access); ?>>
                                    <button type="button" class="button aiohm-shareai-btn aiohm-test-api-key" data-target="private_llm_server_url" data-type="ollama" <?php disabled(!$has_private_access); ?>><?php esc_html_e('Test Server', 'aiohm-knowledge-assistant'); ?></button>
                                    <button type="button" class="button aiohm-shareai-btn aiohm-save-api-key" data-target="private_llm_server_url" data-type="ollama" <?php disabled(!$has_private_access); ?>><?php esc_html_e('Save', 'aiohm-knowledge-assistant'); ?></button>
                                </div>
                            </div>
                            
                            <div class="shareai-setting-row">
                                <label for="private_llm_model" class="shareai-inline-label"><?php esc_html_e('Model Name:', 'aiohm-knowledge-assistant'); ?></label>
                                <input type="text" id="private_llm_model" name="aiohm_kb_settings[private_llm_model]" value="<?php echo esc_attr($settings['private_llm_model'] ?? 'llama3.2'); ?>" class="regular-text" <?php disabled(!$has_private_access); ?>>
                            </div>
                            
                            <div class="shareai-usage-notice">
                                <p><strong><?php esc_html_e('Private AI with maximum control', 'aiohm-knowledge-assistant'); ?></strong><br>
                                <?php esc_html_e('Run AI models locally or on your own server for complete data privacy and unlimited usage without external API dependencies.', 'aiohm-knowledge-assistant'); ?></p>
                                
                                <p><strong><?php esc_html_e('Ollama Server Options:', 'aiohm-knowledge-assistant'); ?></strong><br>
                                • <strong><?php esc_html_e('ServBay:', 'aiohm-knowledge-assistant'); ?></strong> <?php 
                                    printf(
                                        wp_kses(
                                            // translators: %s is the URL to ServBay
                                            __('Local development environment with built-in Ollama - <a href="%s" target="_blank">Download ServBay</a>', 'aiohm-knowledge-assistant'),
                                            array(
                                                'a' => array(
                                                    'href' => array(),
                                                    'target' => array()
                                                )
                                            )
                                        ),
                                        esc_url('https://www.servbay.com/')
                                    ); ?><br>
                                • <strong><?php esc_html_e('Local Ollama:', 'aiohm-knowledge-assistant'); ?></strong> <?php 
                                // translators: %s is a link to download Ollama
                                printf(
                                    /* translators: %s is a link to download Ollama */
                                    esc_html__('Self-hosted local installation - %s', 'aiohm-knowledge-assistant'),
                                    '<a href="' . esc_url('https://ollama.com/download') . '" target="_blank" rel="noopener">' . esc_html__('Download Ollama', 'aiohm-knowledge-assistant') . '</a>'
                                ); ?><br>
                                • <strong><?php esc_html_e('LM Studio:', 'aiohm-knowledge-assistant'); ?></strong> <?php 
                                    printf(
                                        wp_kses(
                                            // translators: %s is the URL to LM Studio
                                            __('Alternative local AI server - <a href="%s" target="_blank">Download LM Studio</a>', 'aiohm-knowledge-assistant'),
                                            array(
                                                'a' => array(
                                                    'href' => array(),
                                                    'target' => array()
                                                )
                                            )
                                        ),
                                        esc_url('https://lmstudio.ai/')
                                    ); ?><br>
                                • <strong><?php esc_html_e('Custom Server:', 'aiohm-knowledge-assistant'); ?></strong> <?php esc_html_e('Remote server or custom URL', 'aiohm-knowledge-assistant'); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Server Information -->
                    <div class="aiohm-shareai-info-column">
                        <div class="aiohm-model-info-box">
                            <h4 class="aiohm-model-info-title"><?php esc_html_e('Server Information', 'aiohm-knowledge-assistant'); ?></h4>
                            <div id="aiohm-ollama-server-info-content" class="aiohm-model-info-content">
                                <!-- Default informational content -->
                                <div class="aiohm-model-placeholder">
                                    <h5><?php esc_html_e('What is Ollama & Local LLMs?', 'aiohm-knowledge-assistant'); ?></h5>
                                    <p><?php esc_html_e('Ollama is a powerful tool that lets you run Large Language Models (LLMs) locally on your own computer or server, giving you complete privacy and control over your AI conversations.', 'aiohm-knowledge-assistant'); ?></p>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                                        <!-- Left Column -->
                                        <div>
                                            <div class="aiohm-info-section">
                                                <h6><?php esc_html_e('🔒 Why Choose Local LLMs?', 'aiohm-knowledge-assistant'); ?></h6>
                                                <ul style="text-align: left; margin: 5px 0; font-size: 13px;">
                                                    <li><?php esc_html_e('Complete privacy - data never leaves your server', 'aiohm-knowledge-assistant'); ?></li>
                                                    <li><?php esc_html_e('No usage limits or monthly costs', 'aiohm-knowledge-assistant'); ?></li>
                                                    <li><?php esc_html_e('Works offline without internet', 'aiohm-knowledge-assistant'); ?></li>
                                                    <li><?php esc_html_e('Full control over models', 'aiohm-knowledge-assistant'); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <!-- Right Column -->
                                        <div>
                                            <div class="aiohm-info-section">
                                                <h6><?php esc_html_e('🚀 Popular Models', 'aiohm-knowledge-assistant'); ?></h6>
                                                <ul style="text-align: left; margin: 5px 0; font-size: 13px;">
                                                    <li><strong>llama3.2:3b</strong> - <?php esc_html_e('Speed & quality balance', 'aiohm-knowledge-assistant'); ?></li>
                                                    <li><strong>mistral:7b</strong> - <?php esc_html_e('General conversations', 'aiohm-knowledge-assistant'); ?></li>
                                                    <li><strong>codellama:7b</strong> - <?php esc_html_e('Coding assistance', 'aiohm-knowledge-assistant'); ?></li>
                                                    <li><strong>qwen2.5:7b</strong> - <?php esc_html_e('Advanced reasoning', 'aiohm-knowledge-assistant'); ?></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Premium AI Services Section -->
        <div class="aiohm-card">
            <h2><?php esc_html_e('Premium AI Services & Connections', 'aiohm-knowledge-assistant'); ?></h2>
            <p class="aiohm-section-description"><?php esc_html_e('Professional AI services with advanced features. Requires paid API keys but offers superior performance and capabilities.', 'aiohm-knowledge-assistant'); ?></p>
            
            <!-- OpenAI -->
            <h3 class="aiohm-service-title"><?php esc_html_e('OpenAI API Key', 'aiohm-knowledge-assistant'); ?></h3>
            <div class="aiohm-service-content">
                <div class="aiohm-api-key-wrapper">
                            <input type="password" id="openai_api_key" name="aiohm_kb_settings[openai_api_key]" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" class="regular-text">
                            <button type="button" class="button button-secondary aiohm-show-hide-key" data-target="openai_api_key"><span class="dashicons dashicons-visibility"></span></button>
                            <button type="button" class="button button-secondary aiohm-test-api-key" data-target="openai_api_key" data-type="openai"><?php esc_html_e('Test API', 'aiohm-knowledge-assistant'); ?></button>
                            <button type="button" class="button button-secondary aiohm-save-api-key" data-target="openai_api_key" data-type="openai"><?php esc_html_e('Save', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                <p class="description"><?php 
                    printf(
                        wp_kses(
                            // translators: %s is the URL to the OpenAI API keys page
                            __('You can get your OpenAI API key from the <a href="%s" target="_blank">OpenAI API keys page</a>.', 'aiohm-knowledge-assistant'),
                            array(
                                'a' => array(
                                    'href' => array(),
                                    'target' => array()
                                )
                            )
                        ),
                        esc_url('https://platform.openai.com/account/api-keys')
                    ); ?></p>
            </div>

            <!-- Gemini -->
            <h3 class="aiohm-service-title"><?php esc_html_e('Gemini API Key', 'aiohm-knowledge-assistant'); ?></h3>
            <div class="aiohm-service-content">
                <div class="aiohm-api-key-wrapper">
                            <input type="password" id="gemini_api_key" name="aiohm_kb_settings[gemini_api_key]" value="<?php echo esc_attr($settings['gemini_api_key'] ?? ''); ?>" class="regular-text">
                            <button type="button" class="button button-secondary aiohm-show-hide-key" data-target="gemini_api_key"><span class="dashicons dashicons-visibility"></span></button>
                            <button type="button" class="button button-secondary aiohm-test-api-key" data-target="gemini_api_key" data-type="gemini"><?php esc_html_e('Test API', 'aiohm-knowledge-assistant'); ?></button>
                            <button type="button" class="button button-secondary aiohm-save-api-key" data-target="gemini_api_key" data-type="gemini"><?php esc_html_e('Save', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                <p class="description"><?php 
                    printf(
                        wp_kses(
                            // translators: %s is the URL to the Google AI Studio API keys page
                            __('You can get your Gemini API key from the <a href="%s" target="_blank">Google AI Studio</a>.', 'aiohm-knowledge-assistant'),
                            array(
                                'a' => array(
                                    'href' => array(),
                                    'target' => array()
                                )
                            )
                        ),
                        esc_url('https://aistudio.google.com/app/apikey')
                    ); ?></p>
            </div>

            <!-- Claude -->
            <h3 class="aiohm-service-title"><?php esc_html_e('Claude API Key', 'aiohm-knowledge-assistant'); ?></h3>
            <div class="aiohm-service-content">
                <div class="aiohm-api-key-wrapper">
                            <input type="password" id="claude_api_key" name="aiohm_kb_settings[claude_api_key]" value="<?php echo esc_attr($settings['claude_api_key'] ?? ''); ?>" class="regular-text">
                            <button type="button" class="button button-secondary aiohm-show-hide-key" data-target="claude_api_key"><span class="dashicons dashicons-visibility"></span></button>
                            <button type="button" class="button button-secondary aiohm-test-api-key" data-target="claude_api_key" data-type="claude"><?php esc_html_e('Test API', 'aiohm-knowledge-assistant'); ?></button>
                            <button type="button" class="button button-secondary aiohm-save-api-key" data-target="claude_api_key" data-type="claude"><?php esc_html_e('Save', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                <p class="description"><?php 
                    printf(
                        wp_kses(
                            // translators: %s is the URL to the Anthropic Account Settings page
                            __('You can get your Claude API key from your <a href="%s" target="_blank">Anthropic Account Settings</a>.', 'aiohm-knowledge-assistant'),
                            array(
                                'a' => array(
                                    'href' => array(),
                                    'target' => array()
                                )
                            )
                        ),
                        esc_url('https://console.anthropic.com/account/keys')
                    ); ?></p>
            </div>
        </div>

        <!-- Free Features Section -->
        <div class="aiohm-card">
            <h2><?php esc_html_e('Free Features', 'aiohm-knowledge-assistant'); ?></h2>
            <p class="aiohm-section-description"><?php esc_html_e('These features are available to all users without membership requirements.', 'aiohm-knowledge-assistant'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Search Shortcode', 'aiohm-knowledge-assistant'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aiohm_kb_settings[enable_search_shortcode]" value="1" <?php checked($settings['enable_search_shortcode'] ?? false); ?> />
                            <?php 
                            // translators: %s is the shortcode for knowledge base search
                            printf(esc_html__('Enable the %s shortcode for knowledge base search.', 'aiohm-knowledge-assistant'), '<code>[aiohm_search]</code>'); ?>
                        </label>
                    </td>
                </tr>
                
            </table>
        </div>

        <!-- AI Usage Overview Section -->
        <div class="aiohm-card aiohm-usage-overview">
            <h2><?php esc_html_e('AI Usage Overview', 'aiohm-knowledge-assistant'); ?></h2>
            <p class="aiohm-section-description"><?php esc_html_e('Monitor your AI usage, track costs, and analyze performance across all providers.', 'aiohm-knowledge-assistant'); ?></p>
            
            <div class="aiohm-usage-stats-grid">
                <div class="usage-stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <h3><?php esc_html_e('Total Tokens (30 Days)', 'aiohm-knowledge-assistant'); ?></h3>
                        <div class="stat-value" id="total-tokens-30d">-</div>
                        <div class="stat-subtext"><?php esc_html_e('All providers combined', 'aiohm-knowledge-assistant'); ?></div>
                    </div>
                </div>
                
                <div class="usage-stat-card">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-content">
                        <h3><?php esc_html_e('Today\'s Usage', 'aiohm-knowledge-assistant'); ?></h3>
                        <div class="stat-value" id="tokens-today">-</div>
                        <div class="stat-subtext"><?php esc_html_e('Current day total', 'aiohm-knowledge-assistant'); ?></div>
                    </div>
                </div>
                
                <div class="usage-stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <h3><?php esc_html_e('Estimated Cost', 'aiohm-knowledge-assistant'); ?></h3>
                        <div class="stat-value" id="estimated-cost">-</div>
                        <div class="stat-subtext"><?php esc_html_e('Last 30 days (USD)', 'aiohm-knowledge-assistant'); ?></div>
                    </div>
                </div>
                
                <div class="usage-stat-card">
                    <div class="stat-icon">
                        <?php 
                        $provider = $settings['default_ai_provider'] ?? 'openai';
                        if ($provider === 'shareai'): ?>
                            <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Static admin interface icon ?>
                            <img src="<?php echo esc_url(AIOHM_KB_PLUGIN_URL . 'assets/images/shareai-icon.jpeg'); ?>" alt="ShareAI" class="provider-icon-img">
                        <?php else: ?>
                            ⚡
                        <?php endif; ?>
                    </div>
                    <div class="stat-content">
                        <h3><?php esc_html_e('Active Provider', 'aiohm-knowledge-assistant'); ?></h3>
                        <div class="stat-value" id="active-provider">
                            <?php 
                            $provider_name = $settings['default_ai_provider'] ?? 'OpenAI';
                            if ($provider_name === 'shareai') {
                                echo esc_html('ShareAI');
                            } else {
                                echo esc_html(ucfirst($provider_name));
                            }
                            ?>
                        </div>
                        <div class="stat-subtext"><?php esc_html_e('Primary AI service', 'aiohm-knowledge-assistant'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="aiohm-usage-breakdown">
                <h3><?php esc_html_e('Usage Breakdown by Provider', 'aiohm-knowledge-assistant'); ?></h3>
                <div class="usage-breakdown-table">
                    <div class="breakdown-header">
                        <span><?php esc_html_e('Provider', 'aiohm-knowledge-assistant'); ?></span>
                        <span><?php esc_html_e('Tokens (30d)', 'aiohm-knowledge-assistant'); ?></span>
                        <span><?php esc_html_e('Requests', 'aiohm-knowledge-assistant'); ?></span>
                        <span><?php esc_html_e('Est. Cost', 'aiohm-knowledge-assistant'); ?></span>
                    </div>
                    <div class="breakdown-row" data-provider="openai">
                        <span class="provider-name">
                            <span class="provider-icon">🤖</span>
                            OpenAI
                        </span>
                        <span class="tokens-count" id="openai-tokens">-</span>
                        <span class="requests-count" id="openai-requests">-</span>
                        <span class="cost-estimate" id="openai-cost">-</span>
                    </div>
                    <div class="breakdown-row" data-provider="gemini">
                        <span class="provider-name">
                            <span class="provider-icon">💎</span>
                            Gemini
                        </span>
                        <span class="tokens-count" id="gemini-tokens">-</span>
                        <span class="requests-count" id="gemini-requests">-</span>
                        <span class="cost-estimate" id="gemini-cost">-</span>
                    </div>
                    <div class="breakdown-row" data-provider="claude">
                        <span class="provider-name">
                            <span class="provider-icon">🧠</span>
                            Claude
                        </span>
                        <span class="tokens-count" id="claude-tokens">-</span>
                        <span class="requests-count" id="claude-requests">-</span>
                        <span class="cost-estimate" id="claude-cost">-</span>
                    </div>
                    <div class="breakdown-row" data-provider="shareai">
                        <span class="provider-name">
                            <?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Static admin interface icon ?>
                            <img src="<?php echo esc_url(AIOHM_KB_PLUGIN_URL . 'assets/images/shareai-icon.jpeg'); ?>" alt="ShareAI" class="provider-breakdown-icon">
                            ShareAI
                        </span>
                        <span class="tokens-count" id="shareai-tokens">-</span>
                        <span class="requests-count" id="shareai-requests">-</span>
                        <span class="cost-estimate" id="shareai-cost">-</span>
                    </div>
                </div>
                <button type="button" id="refresh-usage-stats" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Refresh Stats', 'aiohm-knowledge-assistant'); ?>
                </button>
            </div>
        </div>

        <!-- Premium Features Wrapper -->
        <div class="aiohm-premium-settings-wrapper <?php if (!$can_access_settings) echo esc_attr('is-locked'); ?>">
            <?php if (!$can_access_settings) : ?>
                <div class="aiohm-settings-locked-overlay">
                    <div class="lock-content">
                        <div class="lock-icon">🔒</div>
                        <h2><?php esc_html_e('Unlock AIOHM Club Features', 'aiohm-knowledge-assistant'); ?></h2>
                        <p><?php esc_html_e('These settings require an AIOHM Club membership to configure. Join the Club to access Mirror Mode and Muse Mode.', 'aiohm-knowledge-assistant'); ?></p>
                        <div class="unlock-features-list">
                            <h4><?php esc_html_e('Club membership unlocks:', 'aiohm-knowledge-assistant'); ?></h4>
                            <ul>
                                <li>✨ <?php esc_html_e('Mirror Mode (Q&A Chatbot)', 'aiohm-knowledge-assistant'); ?></li>
                                <li>🎨 <?php esc_html_e('Muse Mode (Brand Assistant)', 'aiohm-knowledge-assistant'); ?></li>
                                <li>⚙️ <?php esc_html_e('Advanced Configuration Options', 'aiohm-knowledge-assistant'); ?></li>
                                <li>🧠 <?php esc_html_e('AI Brand Core Access', 'aiohm-knowledge-assistant'); ?></li>
                            </ul>
                        </div>
                        <a href="https://www.aiohm.app/club" target="_blank" class="button button-primary"><?php esc_html_e('Join AIOHM Club', 'aiohm-knowledge-assistant'); ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Q&A Chatbot Settings -->
            <div class="aiohm-card">
                <h2><?php esc_html_e('Q&A Chatbot Settings (Public)', 'aiohm-knowledge-assistant'); ?></h2>
                <p class="aiohm-section-description"><?php esc_html_e('Configure your public-facing chatbot that visitors can interact with on your website.', 'aiohm-knowledge-assistant'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Q&A Chatbot', 'aiohm-knowledge-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aiohm_kb_settings[chat_enabled]" value="1" <?php checked($settings['chat_enabled'] ?? false); disabled(!$can_access_settings); ?> />
                                <?php 
                                // translators: %s is the shortcode for the Q&A chatbot
                                printf(esc_html__('Enable the %s shortcode.', 'aiohm-knowledge-assistant'), '<code>[aiohm_chat]</code>'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Private Brand Assistant Settings -->
            <div class="aiohm-card">
                <h2><?php esc_html_e('Private Brand Assistant (Members Only)', 'aiohm-knowledge-assistant'); ?></h2>
                <p class="aiohm-section-description"><?php esc_html_e('Configure your private AI assistant available only to authenticated members.', 'aiohm-knowledge-assistant'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Private Assistant', 'aiohm-knowledge-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="aiohm_kb_settings[enable_private_assistant]" value="1" <?php checked($settings['enable_private_assistant'] ?? true); disabled(!$can_access_settings); ?> />
                                <?php 
                                // translators: %s is the shortcode for the private brand assistant
                                printf(esc_html__('Enable the %s shortcode.', 'aiohm-knowledge-assistant'), '<code>[aiohm_private_assistant]</code>'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Scheduled Content Scan -->
            <div class="aiohm-card">
                <h2><?php esc_html_e('Scheduled Content Scan', 'aiohm-knowledge-assistant'); ?></h2>
                <p class="aiohm-section-description"><?php esc_html_e('Automatically scan and update your knowledge base on a regular schedule.', 'aiohm-knowledge-assistant'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="scan_schedule"><?php esc_html_e('Scan Frequency', 'aiohm-knowledge-assistant'); ?></label></th>
                        <td>
                            <select id="scan_schedule" name="aiohm_kb_settings[scan_schedule]" <?php disabled(!$can_access_settings); ?>>
                                <option value="none" <?php selected($settings['scan_schedule'] ?? 'none', 'none'); ?>>None</option>
                                <option value="daily" <?php selected($settings['scan_schedule'], 'daily'); ?>>Once Daily</option>
                                <option value="weekly" <?php selected($settings['scan_schedule'], 'weekly'); ?>>Once Weekly</option>
                                <option value="monthly" <?php selected($settings['scan_schedule'], 'monthly'); ?>>Once Monthly</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Plugin Data Management -->
        <div class="aiohm-card">
            <h2><?php esc_html_e('Plugin Data Management', 'aiohm-knowledge-assistant'); ?></h2>
            <p class="aiohm-section-description"><?php esc_html_e('Control how your plugin data is handled during uninstallation.', 'aiohm-knowledge-assistant'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Uninstall Behavior', 'aiohm-knowledge-assistant'); ?></th>
                    <td>
                        <fieldset>
                            <label for="delete_data_on_uninstall">
                                <input type="checkbox" id="delete_data_on_uninstall" name="aiohm_kb_settings[delete_data_on_uninstall]" value="1" <?php checked($settings['delete_data_on_uninstall'] ?? false); ?>>
                                <?php esc_html_e('Delete all plugin data when uninstalling', 'aiohm-knowledge-assistant'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When checked, all plugin data will be permanently deleted when you uninstall the plugin. This includes:', 'aiohm-knowledge-assistant'); ?>
                                <br>• <?php esc_html_e('All API keys and settings', 'aiohm-knowledge-assistant'); ?>
                                <br>• <?php esc_html_e('Knowledge base entries and vector embeddings', 'aiohm-knowledge-assistant'); ?>
                                <br>• <?php esc_html_e('Chat conversations and messages', 'aiohm-knowledge-assistant'); ?>
                                <br>• <?php esc_html_e('Project data and custom database tables', 'aiohm-knowledge-assistant'); ?>
                                <br><strong style="color: #d63638;"><?php esc_html_e('Warning: This action cannot be undone!', 'aiohm-knowledge-assistant'); ?></strong>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('Save All Settings'); ?>
    </form>
</div>


