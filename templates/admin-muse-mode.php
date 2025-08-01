<?php
/**
 * Admin Muse Mode Settings page template for Club members.
 * Evolved into the "Digital Doula" experience with advanced, intuitive settings.
 * This version includes the new element order and dynamic archetype prompts.
 *
 * *** UPDATED: Includes new top bar for "Download", "Research Online", and "Audio" buttons. ***
 */

if (!defined('ABSPATH')) exit;

// Check if this is demo version
$is_demo_version = defined('AIOHM_KB_VERSION') && AIOHM_KB_VERSION === 'DEMO';

// Fetch all settings and then get the specific part for Muse Mode
$all_settings = AIOHM_KB_Assistant::get_settings();
$settings = $all_settings['muse_mode'] ?? [];
$global_settings = $all_settings;

// Check if user has private access for Ollama
$has_private_access = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_private_access();


// --- START: Archetype Prompts ---
$archetype_prompts = [
    'the_creator' => "You are The Creator, an innovative and imaginative brand assistant. Your purpose is to help build things of enduring value. You speak with authenticity and a visionary spirit, inspiring new ideas and artistic expression. You avoid generic language and focus on originality and the creative process.",
    'the_sage' => "You are The Sage, a wise and knowledgeable brand assistant. Your goal is to seek the truth and share it with others. You communicate with clarity, accuracy, and thoughtful insight. You avoid hype and superficiality, instead focusing on providing well-researched, objective information and wisdom.",
    'the_innocent' => "You are The Innocent, an optimistic and pure brand assistant. Your purpose is to spread happiness and see the good in everything. You speak with simple, honest, and positive language. You avoid cynicism and complexity, focusing on straightforward, wholesome, and uplifting messages.",
    'the_explorer' => "You are The Explorer, an adventurous and independent brand assistant. Your mission is to help others experience a more authentic and fulfilling life by pushing boundaries. You speak with a rugged, open-minded, and daring tone. You avoid conformity and rigid rules, focusing on freedom, discovery, and the journey.",
    'the_ruler' => "You are The Ruler, an authoritative and confident brand assistant. Your purpose is to create order and build a prosperous community. You speak with a commanding, polished, and articulate voice. You avoid chaos and mediocrity, focusing on leadership, quality, and control.",
    'the_hero' => "You are The Hero, a courageous and determined brand assistant. Your mission is to inspire others to triumph over adversity. You speak with a bold, confident, and motivational tone. You avoid negativity and weakness, focusing on mastery, ambition, and overcoming challenges.",
    'the_lover' => "You are The Lover, an intimate and empathetic brand assistant. Your goal is to help people feel appreciated and connected. You speak with a warm, sensual, and passionate voice. You avoid conflict and isolation, focusing on relationships, intimacy, and creating blissful experiences.",
    'the_jester' => "You are The Jester, a playful and fun-loving brand assistant. Your purpose is to bring joy to the world and live in the moment. You speak with a witty, humorous, and lighthearted tone. You avoid boredom and seriousness, focusing on entertainment, cleverness, and seeing the funny side of life.",
    'the_everyman' => "You are The Everyman, a relatable and down-to-earth brand assistant. Your goal is to belong and connect with others on a human level. You speak with a friendly, humble, and authentic voice. You avoid elitism and pretense, focusing on empathy, realism, and shared values.",
    'the_caregiver' => "You are The Caregiver, a compassionate and nurturing brand assistant. Your purpose is to protect and care for others. You speak with a warm, reassuring, and supportive tone. You avoid selfishness and trouble, focusing on generosity, empathy, and providing a sense of security.",
    'the_magician' => "You are The Magician, a visionary and charismatic brand assistant. Your purpose is to make dreams come true and create something special. You speak with a mystical, inspiring, and transformative voice. You avoid the mundane and doubt, focusing on moments of wonder, vision, and the power of belief.",
    'the_outlaw' => "You are The Outlaw, a rebellious and revolutionary brand assistant. Your mission is to challenge the status quo and break the rules. You speak with a raw, disruptive, and unapologetic voice. You avoid conformity and powerlessness, focusing on liberation, revolution, and radical freedom.",
];
$default_prompt = "You are Muse, a private brand assistant. Your role is to help the user develop their brand by using the provided context, which includes public information and the user's private 'Brand Soul' answers. Synthesize this information to provide creative ideas, answer strategic questions, and help draft content. Always prioritize the private 'Brand Soul' context when available.";
$system_prompt = !empty($settings['system_prompt']) ? $settings['system_prompt'] : $default_prompt;

// Archetypes for the dropdown
$brand_archetypes = [
    'the_creator' => 'The Creator', 'the_sage' => 'The Sage', 'the_innocent' => 'The Innocent', 'the_explorer' => 'The Explorer', 'the_ruler' => 'The Ruler', 'the_hero' => 'The Hero', 'the_lover' => 'The Lover', 'the_jester' => 'The Jester', 'the_everyman' => 'The Everyman', 'the_caregiver' => 'The Caregiver', 'the_magician' => 'The Magician', 'the_outlaw' => 'The Outlaw',
];
// --- END: Archetype Prompts ---
?>

<div class="wrap aiohm-settings-page aiohm-muse-mode-page">
    <h1><?php esc_html_e('Muse Mode Customization', 'aiohm-knowledge-assistant'); ?></h1>
    <p class="page-description"><?php esc_html_e('Here, you attune your AI to be a true creative partner. Define its energetic signature and workflow to transform your brand dialogue.', 'aiohm-knowledge-assistant'); ?></p>


    <div id="aiohm-admin-notice" class="notice is-dismissible" style="display:none; margin-top: 10px;" tabindex="-1" role="alert" aria-live="polite"></div>

    <div class="aiohm-muse-mode-layout">
        
        <div class="aiohm-settings-form-wrapper">
            <form id="muse-mode-settings-form">
                <?php wp_nonce_field('aiohm_muse_mode_nonce', 'aiohm_muse_mode_nonce_field'); ?>
                
                <div class="aiohm-settings-section">

                    <div class="aiohm-setting-block">
                        <label for="assistant_name"><?php esc_html_e('1. Brand Assistant Name', 'aiohm-knowledge-assistant'); ?></label>
                        <input type="text" id="assistant_name" name="aiohm_kb_settings[muse_mode][assistant_name]" value="<?php echo esc_attr($settings['assistant_name'] ?? 'Muse'); ?>">
                    </div>

                    <div class="aiohm-setting-block">
                        <label for="brand_archetype"><?php esc_html_e('2. Brand Archetype', 'aiohm-knowledge-assistant'); ?></label>
                        <select id="brand_archetype" name="aiohm_kb_settings[muse_mode][brand_archetype]">
                            <option value=""><?php esc_html_e('-- Select an Archetype --', 'aiohm-knowledge-assistant'); ?></option>
                            <?php foreach ($brand_archetypes as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['brand_archetype'] ?? '', $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Select an archetype to give your Muse a foundational personality.', 'aiohm-knowledge-assistant'); ?></p>
                    </div>

                    <div class="aiohm-setting-block">
                        <div class="aiohm-setting-header">
                            <label for="system_prompt"><?php esc_html_e('3. Soul Signature Brand Assistant', 'aiohm-knowledge-assistant'); ?></label>
                            <button type="button" id="reset-prompt-btn" class="button-link"><?php esc_html_e('Reset to Default', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                        <textarea id="system_prompt" name="aiohm_kb_settings[muse_mode][system_prompt]" rows="20" style="font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.4; white-space: pre-wrap;"><?php echo esc_textarea($system_prompt); ?></textarea>
                        <p class="description"><?php esc_html_e('This is the core instruction set for your AI. Selecting an archetype will provide a starting template.', 'aiohm-knowledge-assistant'); ?></p>
                    </div>

                    <div class="aiohm-setting-block">
                        <div class="aiohm-setting-header">
                            <label for="ai_model_selector"><?php esc_html_e('4. AI Model', 'aiohm-knowledge-assistant'); ?></label>
                        </div>
                        <select id="ai_model_selector" name="aiohm_kb_settings[muse_mode][ai_model]">
                            <?php if (!empty($global_settings['openai_api_key'])): ?>
                                <option value="gpt-3.5-turbo" <?php selected($settings['ai_model'] ?? 'gpt-3.5-turbo', 'gpt-3.5-turbo'); ?>>OpenAI: GPT-3.5 Turbo</option>
                                <option value="gpt-4" <?php selected($settings['ai_model'] ?? '', 'gpt-4'); ?>>OpenAI: GPT-4</option>
                            <?php endif; ?>
                            <?php if (!empty($global_settings['gemini_api_key'])): ?>
                                <option value="gemini-pro" <?php selected($settings['ai_model'] ?? '', 'gemini-pro'); ?>>Google: Gemini Pro</option>
                            <?php endif; ?>
                            <?php if (!empty($global_settings['claude_api_key'])): ?>
                                <option value="claude-3-sonnet" <?php selected($settings['ai_model'] ?? '', 'claude-3-sonnet'); ?>>Anthropic: Claude 3 Sonnet</option>
                            <?php endif; ?>
                            <?php if (!empty($global_settings['shareai_api_key'])): ?>
                                <option value="shareai-llama4:17b-scout-16e-instruct-fp16" <?php selected($settings['ai_model'] ?? '', 'shareai-llama4:17b-scout-16e-instruct-fp16'); ?>>ShareAI</option>
                            <?php endif; ?>
                            <?php if ($has_private_access && !empty($global_settings['private_llm_server_url'])): ?>
                                <option value="ollama" <?php selected($settings['ai_model'] ?? '', 'ollama'); ?>>Ollama: <?php echo esc_html($global_settings['private_llm_model'] ?? 'Private Server'); ?></option>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Choose which AI model powers your brand assistant.', 'aiohm-knowledge-assistant'); ?></p>
                    </div>

                    <div class="aiohm-setting-block">
                        <label for="temperature"><?php esc_html_e('5. Temperature:', 'aiohm-knowledge-assistant'); ?> <span class="temp-value"><?php echo esc_attr($settings['temperature'] ?? '0.7'); ?></span></label>
                        <input type="range" id="temperature" name="aiohm_kb_settings[muse_mode][temperature]" value="<?php echo esc_attr($settings['temperature'] ?? '0.7'); ?>" min="0" max="1" step="0.1">
                        <p class="description"><?php esc_html_e('Lower is more predictable; higher is more creative.', 'aiohm-knowledge-assistant'); ?></p>
                    </div>
                    
                    <div class="aiohm-setting-block">
                        <label for="start_fullscreen">
                            <input type="checkbox" id="start_fullscreen" name="aiohm_kb_settings[muse_mode][start_fullscreen]" value="1" <?php checked($settings['start_fullscreen'] ?? false); ?>>
                            <?php esc_html_e('Start in Fullscreen Mode', 'aiohm-knowledge-assistant'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Check this to make the private assistant always open in fullscreen.', 'aiohm-knowledge-assistant'); ?></p>
                    </div>

                </div>
                <div class="form-actions">
                    <button type="button" id="save-muse-mode-settings" class="button button-primary"><?php esc_html_e('Save Muse Settings', 'aiohm-knowledge-assistant'); ?></button>
                </div>
            </form>
        </div>
        
        <div class="aiohm-test-column">
            <div class="aiohm-test-header">
                <h3><?php esc_html_e('Live Preview', 'aiohm-knowledge-assistant'); ?></h3>
                <p class="description"><?php esc_html_e('Changes on the left are applied instantly here. Test your assistant before saving.', 'aiohm-knowledge-assistant'); ?></p>
                <div class="aiohm-shortcode-info">
                    <strong><?php esc_html_e('Usage:', 'aiohm-knowledge-assistant'); ?></strong> 
                    <code>[aiohm_private_assistant]</code>
                    <span class="aiohm-copy-shortcode" title="<?php esc_attr_e('Click to copy', 'aiohm-knowledge-assistant'); ?>">📋</span>
                </div>
            </div>
            <div id="aiohm-test-chat" class="aiohm-chat-container">
                <div class="aiohm-chat-header">
                    <div class="aiohm-chat-title-preview"><?php echo esc_html($settings['assistant_name'] ?? 'Live Preview'); ?></div>
                    <div class="aiohm-chat-status">
                        <span class="aiohm-status-indicator" data-status="ready"></span>
                        <span class="aiohm-status-text">Ready</span>
                    </div>
                </div>
                <div class="aiohm-chat-messages">
                    <div class="aiohm-message aiohm-message-bot">
                        <div class="aiohm-message-avatar">
                            <?php
                            echo wp_kses_post(AIOHM_KB_Core_Init::render_image(
                                AIOHM_KB_PLUGIN_URL . 'assets/images/OHM-logo.png',
                                esc_attr__('AI Avatar', 'aiohm-knowledge-assistant'),
                                ['class' => 'aiohm-avatar-preview']
                            ));
                            ?>
                        </div>
                        <div class="aiohm-message-bubble"><div class="aiohm-message-content">Ask a question to test the settings from the left. Your changes are applied instantly here without saving.</div></div>
                    </div>
                </div>
                <div class="aiohm-chat-input-container">
                    <div class="aiohm-chat-input-wrapper">
                        <textarea class="aiohm-chat-input" placeholder="Ask your question here..." rows="1"></textarea>
                        <button type="button" class="aiohm-chat-send-btn" disabled><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></button>
                    </div>
                </div>
                <div class="aiohm-chat-footer-preview">
                </div>
            </div>

            <div class="aiohm-muse-tools-overview">
                <h3><?php esc_html_e('Your Private Creative Partner', 'aiohm-knowledge-assistant'); ?></h3>
                <p class="description"><?php esc_html_e('Your private AI assistant comes with powerful creative tools designed for focus, flow, and brand alignment:', 'aiohm-knowledge-assistant'); ?></p>
                
                <table class="aiohm-features-table widefat">
                    <tbody>
                        <tr>
                            <td class="aiohm-icon-col">
                                <span class="dashicons dashicons-search" style="font-size: 20px; color: #1f5014;"></span>
                            </td>
                            <td class="aiohm-feature-col">
                                <strong><?php esc_html_e('Research Online with Different Persona', 'aiohm-knowledge-assistant'); ?></strong>
                            </td>
                            <td class="aiohm-desc-col">
                                <?php esc_html_e('Research websites with expert perspectives like journalist, graphic designer, or industry specialist.', 'aiohm-knowledge-assistant'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="aiohm-icon-col">
                                <span class="dashicons dashicons-download" style="font-size: 20px; color: #1f5014;"></span>
                            </td>
                            <td class="aiohm-feature-col">
                                <strong><?php esc_html_e('Download', 'aiohm-knowledge-assistant'); ?></strong>
                            </td>
                            <td class="aiohm-desc-col">
                                <?php esc_html_e('Save your entire conversation for future reference or client handover.', 'aiohm-knowledge-assistant'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="aiohm-icon-col">
                                <span class="dashicons dashicons-database-add" style="font-size: 20px; color: #1f5014;"></span>
                            </td>
                            <td class="aiohm-feature-col">
                                <strong><?php esc_html_e('One-Click Save to Knowledge Base', 'aiohm-knowledge-assistant'); ?></strong>
                            </td>
                            <td class="aiohm-desc-col">
                                <?php esc_html_e('Turn great ideas into permanent resources instantly.', 'aiohm-knowledge-assistant'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="aiohm-icon-col">
                                <span class="dashicons dashicons-edit" style="font-size: 20px; color: #1f5014;"></span>
                            </td>
                            <td class="aiohm-feature-col">
                                <strong><?php esc_html_e('Smart Notes Panel', 'aiohm-knowledge-assistant'); ?></strong>
                            </td>
                            <td class="aiohm-desc-col">
                                <?php esc_html_e('Capture insights and organize thoughts as you create.', 'aiohm-knowledge-assistant'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="aiohm-icon-col">
                                <span class="dashicons dashicons-fullscreen-alt" style="font-size: 20px; color: #1f5014;"></span>
                            </td>
                            <td class="aiohm-feature-col">
                                <strong><?php esc_html_e('Distraction-Free Fullscreen Mode', 'aiohm-knowledge-assistant'); ?></strong>
                            </td>
                            <td class="aiohm-desc-col">
                                <?php esc_html_e('Go deep into your creative zone.', 'aiohm-knowledge-assistant'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="aiohm-icon-col">
                                <span class="dashicons dashicons-groups" style="font-size: 20px; color: #1f5014;"></span>
                            </td>
                            <td class="aiohm-feature-col">
                                <strong><?php esc_html_e('Project Management Shortcuts', 'aiohm-knowledge-assistant'); ?></strong>
                            </td>
                            <td class="aiohm-desc-col">
                                <?php esc_html_e('Attach tasks, tag ideas, and keep strategy aligned.', 'aiohm-knowledge-assistant'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="aiohm-icon-col">
                                <span class="dashicons dashicons-yes-alt" style="font-size: 20px; color: #1f5014;"></span>
                            </td>
                            <td class="aiohm-feature-col">
                                <strong><?php esc_html_e('Context-Aware Responses', 'aiohm-knowledge-assistant'); ?></strong>
                            </td>
                            <td class="aiohm-desc-col">
                                <?php esc_html_e('Every answer reflects your brand voice and knowledge base, not generic AI fluff.', 'aiohm-knowledge-assistant'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test if JavaScript is loading
    console.log('Muse Mode JavaScript loaded');
    
    // If external JS isn't loading, add essential functionality inline
    if (typeof aiohm_admin_modes_ajax === 'undefined') {
        console.log('External JS not loaded - adding inline functionality');
        
        // Save settings button
        $('#save-muse-mode-settings').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Saving...');
            
            const formData = $('#muse-mode-settings-form').serialize();
            
            $.post(ajaxurl, formData + '&action=aiohm_save_settings&nonce=<?php echo esc_attr(wp_create_nonce('aiohm_muse_mode_nonce')); ?>').done(function(response) {
                if (response.success) {
                    $('#aiohm-admin-notice').removeClass('notice-error').addClass('notice-success').show().html('<p>Settings saved successfully!</p>');
                } else {
                    $('#aiohm-admin-notice').removeClass('notice-success').addClass('notice-error').show().html('<p>Error: ' + (response.data || 'Could not save settings') + '</p>');
                }
            }).fail(function() {
                $('#aiohm-admin-notice').removeClass('notice-success').addClass('notice-error').show().html('<p>An unexpected error occurred.</p>');
            }).always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        });

        // Reset prompt button
        $('#reset-prompt-btn').on('click', function(e) {
            e.preventDefault();
            const defaultPrompt = "You are Muse, a private brand assistant. Your role is to help the user develop their brand by using the provided context, which includes public information and the user's private 'Brand Soul' answers. Synthesize this information to provide creative ideas, answer strategic questions, and help draft content. Always prioritize the private 'Brand Soul' context when available.";
            $('#system_prompt').val(defaultPrompt);
        });

        // Brand archetype selector
        $('#brand_archetype').on('change', function() {
            const archetype = $(this).val();
            if (archetype) {
                const archetypePrompts = {
                    'the_creator': "You are The Creator, an innovative and imaginative brand assistant. Your purpose is to help build things of enduring value. You speak with authenticity and a visionary spirit, inspiring new ideas and artistic expression. You avoid generic language and focus on originality and the creative process.",
                    'the_sage': "You are The Sage, a wise and knowledgeable brand assistant. Your goal is to seek the truth and share it with others. You communicate with clarity, accuracy, and thoughtful insight. You avoid hype and superficiality, instead focusing on providing well-researched, objective information and wisdom.",
                    'the_innocent': "You are The Innocent, an optimistic and pure brand assistant. Your purpose is to spread happiness and see the good in everything. You speak with simple, honest, and positive language. You avoid cynicism and complexity, focusing on straightforward, wholesome, and uplifting messages.",
                    'the_explorer': "You are The Explorer, an adventurous and independent brand assistant. Your mission is to help others experience a more authentic and fulfilling life by pushing boundaries. You speak with a rugged, open-minded, and daring tone. You avoid conformity and rigid rules, focusing on freedom, discovery, and the journey.",
                    'the_ruler': "You are The Ruler, an authoritative and confident brand assistant. Your purpose is to create order and build a prosperous community. You speak with a commanding, polished, and articulate voice. You avoid chaos and mediocrity, focusing on leadership, quality, and control.",
                    'the_hero': "You are The Hero, a courageous and determined brand assistant. Your mission is to inspire others to triumph over adversity. You speak with a bold, confident, and motivational tone. You avoid negativity and weakness, focusing on mastery, ambition, and overcoming challenges.",
                    'the_lover': "You are The Lover, an intimate and empathetic brand assistant. Your goal is to help people feel appreciated and connected. You speak with a warm, sensual, and passionate voice. You avoid conflict and isolation, focusing on relationships, intimacy, and creating blissful experiences.",
                    'the_jester': "You are The Jester, a playful and fun-loving brand assistant. Your purpose is to bring joy to the world and live in the moment. You speak with a witty, humorous, and lighthearted tone. You avoid boredom and seriousness, focusing on entertainment, cleverness, and seeing the funny side of life.",
                    'the_everyman': "You are The Everyman, a relatable and down-to-earth brand assistant. Your goal is to belong and connect with others on a human level. You speak with a friendly, humble, and authentic voice. You avoid elitism and pretense, focusing on empathy, realism, and shared values.",
                    'the_caregiver': "You are The Caregiver, a compassionate and nurturing brand assistant. Your purpose is to protect and care for others. You speak with a warm, reassuring, and supportive tone. You avoid selfishness and trouble, focusing on generosity, empathy, and providing a sense of security.",
                    'the_magician': "You are The Magician, a visionary and charismatic brand assistant. Your purpose is to make dreams come true and create something special. You speak with a mystical, inspiring, and transformative voice. You avoid the mundane and doubt, focusing on moments of wonder, vision, and the power of belief.",
                    'the_outlaw': "You are The Outlaw, a rebellious and revolutionary brand assistant. Your mission is to challenge the status quo and break the rules. You speak with a raw, disruptive, and unapologetic voice. You avoid conformity and powerlessness, focusing on liberation, revolution, and radical freedom."
                };
                
                if (archetypePrompts[archetype]) {
                    $('#system_prompt').val(archetypePrompts[archetype]);
                }
            }
        });

        // Temperature slider
        $('#temperature').on('input', function() {
            $('.temp-value').text($(this).val());
        });
    }
});
</script>
