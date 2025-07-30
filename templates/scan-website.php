<?php
/**
 * Scan website content template - Branded Version with all functionality and styles restored.
 */
if (!defined('ABSPATH')) exit;

// The controller (settings-page.php) prepares these variables before including this template.
$settings = AIOHM_KB_Assistant::get_settings();
$default_provider = $settings['default_ai_provider'] ?? 'openai';

// Check if the selected default provider has an API key configured
$api_key_exists = false;
switch ($default_provider) {
    case 'openai':
        $api_key_exists = !empty($settings['openai_api_key']);
        break;
    case 'gemini':
        $api_key_exists = !empty($settings['gemini_api_key']);
        break;
    case 'claude':
        $api_key_exists = !empty($settings['claude_api_key']);
        break;
    case 'shareai':
        $api_key_exists = !empty($settings['shareai_api_key']);
        break;
    case 'ollama':
        $api_key_exists = !empty($settings['private_llm_server_url']);
        break;
    default:
        // For unknown providers, don't assume OpenAI - require proper configuration
        $api_key_exists = false;
}

// Also get provider display name for better error messaging
$provider_names = [
    'openai' => 'OpenAI',
    'gemini' => 'Gemini',
    'claude' => 'Claude',
    'shareai' => 'ShareAI',
    'ollama' => 'Ollama'
];
$current_provider_name = $provider_names[$default_provider] ?? 'Unknown Provider';
$total_links = ($site_stats['posts']['total'] ?? 0) + ($site_stats['pages']['total'] ?? 0);
?>
<div class="wrap aiohm-scan-page" id="aiohm-scan-page">
    <h1><?php esc_html_e('Build Your Knowledge Base', 'aiohm-knowledge-assistant'); ?></h1>
    <p class="page-description"><?php esc_html_e('Scan your website\'s posts, pages, and media library to add content to your AI\'s knowledge base.', 'aiohm-knowledge-assistant'); ?></p>

    <?php if (defined('AIOHM_KB_VERSION') && AIOHM_KB_VERSION === 'DEMO') : ?>
    <!-- Demo Version Banner -->
    <div class="aiohm-demo-banner" style="background: #EBEBEB; border-left: 4px solid #7d9b76; color: #272727; padding: 12px 20px; margin: 15px 0; border-radius: 6px; font-family: 'Montserrat', sans-serif;">
        <p style="margin: 0; font-weight: 600; font-size: 0.95em;">
            <strong style="color: #1f5014;">DEMO VERSION</strong> - You're experiencing AIOHM's interface with simulated responses.
        </p>
    </div>
    <?php endif; ?>

    <div id="aiohm-admin-notice" class="notice is-dismissible" style="display:none; margin-top: 10px;" tabindex="-1" role="alert" aria-live="polite"></div>

    <?php if (!$api_key_exists) : ?>
        <div class="notice notice-warning" style="padding: 15px; border-left-width: 4px;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Action Required: Add Your API Key', 'aiohm-knowledge-assistant'); ?></h3>
            <p><?php 
                /* translators: %s is the name of the AI provider (e.g., OpenAI, Gemini, Claude) */
                printf(esc_html__('Content scanning is disabled because your %s API key has not been configured. Please add your key to enable this feature.', 'aiohm-knowledge-assistant'), 
                    esc_html($current_provider_name)
                ); 
            ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-settings')); ?>" class="button button-primary"><?php esc_html_e('Go to Settings', 'aiohm-knowledge-assistant'); ?></a>
        </div>
    <?php endif; ?>

    <div class="aiohm-scan-section-wrapper" style="margin-bottom: 20px;">
        <div class="aiohm-scan-section">
            <h2><?php esc_html_e('Content Stats', 'aiohm-knowledge-assistant'); ?></h2>
            <p><?php esc_html_e('An overview of all scannable content from your website and Media Library.', 'aiohm-knowledge-assistant'); ?></p>
            
            <div class="aiohm-stats-boxes">
                <div class="aiohm-stats-box">
                    <div class="stats-box-header">
                        <h4><?php esc_html_e('Website Content Breakdown', 'aiohm-knowledge-assistant'); ?></h4>
                    </div>
                    <div class="stats-box-content">
                        <div class="stat-item total-stat">
                            <strong><?php esc_html_e('Total Website Content:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span class="stat-number"><?php echo esc_html($total_links); ?></span>
                            <span class="stat-label">(Posts + Pages)</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php esc_html_e('Posts:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span><?php 
                                // translators: %1$d is total posts, %2$d is indexed posts, %3$d is pending posts
                                printf(esc_html__('%1$d total, %2$d indexed, %3$d pending', 'aiohm-knowledge-assistant'), 
                                    esc_html($site_stats['posts']['total'] ?? 0), 
                                    esc_html($site_stats['posts']['indexed'] ?? 0), 
                                    esc_html($site_stats['posts']['pending'] ?? 0)
                                ); ?></span>
                        </div>
                        <div class="stat-item">
                            <strong><?php esc_html_e('Pages:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span><?php 
                                // translators: %1$d is total pages, %2$d is indexed pages, %3$d is pending pages
                                printf(esc_html__('%1$d total, %2$d indexed, %3$d pending', 'aiohm-knowledge-assistant'), 
                                    esc_html($site_stats['pages']['total'] ?? 0), 
                                    esc_html($site_stats['pages']['indexed'] ?? 0), 
                                    esc_html($site_stats['pages']['pending'] ?? 0)
                                ); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="aiohm-stats-box">
                    <div class="stats-box-header">
                        <h4><?php esc_html_e('Media Library Breakdown', 'aiohm-knowledge-assistant'); ?></h4>
                    </div>
                    <div class="stats-box-content">
                        <div class="stat-item total-stat">
                            <strong><?php esc_html_e('Total Media Files:', 'aiohm-knowledge-assistant'); ?></strong>
                            <span class="stat-number"><?php echo esc_html($uploads_stats['total_files'] ?? 0); ?></span>
                            <span class="stat-label">(Indexed: <?php echo esc_html($uploads_stats['indexed_files'] ?? 0); ?>, Pending: <?php echo esc_html($uploads_stats['pending_files'] ?? 0); ?>)</span>
                        </div>
                    </div>
                    <?php if (!empty($uploads_stats['by_type'])) { 
                        foreach($uploads_stats['by_type'] as $type => $data) { 
                            $size_formatted = size_format($data['size'] ?? 0); 
                            echo '<div class="stat-item"><strong>' . esc_html(strtoupper($type)) . ' Files:</strong> <span>' . esc_html(
                                // translators: %1$d is total files, %2$d is indexed files, %3$d is pending files, %4$s is file size
                                sprintf(__('%1$d total, %2$d indexed, %3$d pending (%4$s)', 'aiohm-knowledge-assistant'), $data['count'] ?? 0, $data['indexed'] ?? 0, $data['pending'] ?? 0, $size_formatted)
                            ) . '</span></div>'; 
                        } 
                    } else { 
                        echo '<p>' . esc_html__('Supported files include .txt, .json, .csv, .pdf, .doc, .docx, and .md from your Media Library.', 'aiohm-knowledge-assistant') . '</p>'; 
                    } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="aiohm-scan-columns-wrapper">
        <div class="aiohm-scan-column">
            <div class="aiohm-scan-section">
                <h2><?php esc_html_e('Website Content Scanner', 'aiohm-knowledge-assistant'); ?></h2>
                <p><?php esc_html_e('Use the button to find or re-scan your posts and pages.', 'aiohm-knowledge-assistant'); ?></p>
                <button type="button" class="button button-primary" id="scan-website-btn" <?php disabled(!$api_key_exists); ?>><?php esc_html_e('Re-Scan Posts & Pages', 'aiohm-knowledge-assistant'); ?></button>
                <div id="pending-content-area" style="margin-top: 20px;">
                    <h3><?php esc_html_e('Scan Results', 'aiohm-knowledge-assistant'); ?></h3>
                    <div id="scan-results-container"></div>
                    <button type="button" class="button button-primary" id="add-selected-to-kb-btn" style="margin-top: 15px;" <?php disabled(!$api_key_exists); ?>><?php esc_html_e('Add Selected to KB', 'aiohm-knowledge-assistant'); ?></button>
                    <div id="website-scan-progress" class="aiohm-scan-progress" style="display: none;"><div class="progress-info"><span class="progress-label">Processing...</span><span class="progress-percentage">0%</span></div><div class="progress-bar-wrapper"><div class="progress-bar-inner"></div></div></div>
                </div>
            </div>
        </div>
        <div class="aiohm-scan-column">
            <div class="aiohm-scan-section">
                <h2><?php esc_html_e('Upload Folder Scanner', 'aiohm-knowledge-assistant'); ?></h2>
                <p><?php 
                    // translators: %s is the WordPress Media Library text that will be bolded
                    printf(esc_html__('Scan your %s for readable files like .txt, .json, .csv, and .pdf.', 'aiohm-knowledge-assistant'), '<strong>' . esc_html__('WordPress Media Library', 'aiohm-knowledge-assistant') . '</strong>'); ?></p>
                <button type="button" class="button button-primary" id="scan-uploads-btn" <?php disabled(!$api_key_exists); ?>><?php esc_html_e('Re-Scan Media Library', 'aiohm-knowledge-assistant'); ?></button>
                <div id="pending-uploads-area" style="margin-top: 20px;">
                    <h3><?php esc_html_e('Uploads Scan Results', 'aiohm-knowledge-assistant'); ?></h3>
                    <div id="scan-uploads-container"></div>
                    <button type="button" class="button button-primary" id="add-uploads-to-kb-btn" style="margin-top: 15px;" <?php disabled(!$api_key_exists); ?>><?php esc_html_e('Add Selected to KB', 'aiohm-knowledge-assistant'); ?></button>
                    <div id="uploads-scan-progress" class="aiohm-scan-progress" style="display: none;"><div class="progress-info"><span class="progress-label">Processing...</span><span class="progress-percentage">0%</span></div><div class="progress-bar-wrapper"><div class="progress-bar-inner"></div></div></div>
                </div>
            </div>
        </div>
    </div>
</div>