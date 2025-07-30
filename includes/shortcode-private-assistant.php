<?php
/**
 * Shortcode for displaying the private assistant interface.
 * v1.2.0 - Enhanced user interface with notes management and improved admin notifications.
 */
if (!defined('ABSPATH')) exit;

class AIOHM_KB_Shortcode_Private_Assistant {

    public static function init() {
        add_shortcode('aiohm_private_assistant', array(__CLASS__, 'render_private_assistant'));
    }

    public static function render_private_assistant($atts = []) {
        if (!is_user_logged_in()) {
            return '<p class="aiohm-auth-notice">Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to access your private assistant.</p>';
        }

        // Check for Club or Private membership access - Muse Mode is premium feature
        $has_club_access = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_club_access();
        $has_private_access = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_private_access();
        
        if (!$has_club_access && !$has_private_access) {
            return '<div class="aiohm-access-denied">
                <h3>' . esc_html__('Premium Feature - Muse Mode', 'aiohm-knowledge-assistant') . '</h3>
                <p>' . esc_html__('Muse Mode is available for AIOHM Club and Private members only.', 'aiohm-knowledge-assistant') . '</p>
                <p><a href="' . esc_url(admin_url('admin.php?page=aiohm-dashboard&tab=club')) . '" class="button button-primary">' . esc_html__('Join AIOHM Club', 'aiohm-knowledge-assistant') . '</a></p>
                <p><a href="' . esc_url(admin_url('admin.php?page=aiohm-dashboard&tab=private')) . '" class="button button-secondary">' . esc_html__('Explore Private', 'aiohm-knowledge-assistant') . '</a></p>
            </div>';
        }

        // **FIX: Corrected the default welcome message text**
        $atts = shortcode_atts([
            'welcome_title'    => 'Welcome! Here’s a quick guide to the buttons:',
            'welcome_message'  => 'Select a project from the sidebar to begin.',
        ], $atts, 'aiohm_private_assistant');

        $all_settings = AIOHM_KB_Assistant::get_settings();
        $muse_settings = $all_settings['muse_mode'] ?? [];
        $assistant_name = !empty($muse_settings['assistant_name']) ? esc_html($muse_settings['assistant_name']) : 'Muse';
        $settings_page_url = admin_url('admin.php?page=aiohm-muse-mode');

        wp_enqueue_style('dashicons');
        wp_enqueue_style('aiohm-private-chat-style', AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-private-chat.css', ['dashicons'], AIOHM_KB_VERSION);
        wp_enqueue_script('aiohm-private-chat-js', AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-private-chat.js', ['jquery'], AIOHM_KB_VERSION, true);
        
        wp_localize_script('aiohm-private-chat-js', 'aiohm_private_chat_params', [
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('aiohm_private_chat_nonce'),
            'user_name'       => wp_get_current_user()->display_name,
            'startFullscreen' => ($muse_settings['start_fullscreen'] ?? false),
            'assistantName'   => $assistant_name,
        ]);

        ob_start();
        ?>
        <div id="aiohm-app-container" class="aiohm-private-assistant-container modern">
            
            <aside class="aiohm-pa-sidebar">
                <div class="aiohm-pa-sidebar-header">
                    <h3><?php echo esc_html($assistant_name); ?></h3>
                </div>

                <nav class="aiohm-pa-menu">
                    <div class="aiohm-pa-menu-item">
                        <button class="aiohm-pa-menu-header active" data-target="projects-content">
                            <span>Projects</span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="aiohm-pa-menu-content" id="projects-content" style="display: block;">
                            <div class="aiohm-pa-project-list"></div>
                        </div>
                    </div>
                    <div class="aiohm-pa-menu-item">
                        <button class="aiohm-pa-menu-header" data-target="conversations-content">
                            <span>Conversations</span>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="aiohm-pa-menu-content" id="conversations-content">
                            <div class="aiohm-pa-conversation-list"></div>
                        </div>
                    </div>
                </nav>

                <div class="aiohm-pa-sidebar-footer">
                    <a href="<?php echo esc_url($settings_page_url); ?>" class="aiohm-footer-settings-link" title="Muse Mode Settings">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </a>
                    <span class="aiohm-footer-version">AIOHM v<?php echo esc_html(AIOHM_KB_VERSION); ?></span>
                </div>
            </aside>

            <main class="aiohm-pa-content-wrapper">
                <div id="aiohm-admin-notice" class="aiohm-admin-notice" style="display:none;" tabindex="-1" role="alert" aria-live="polite"><p></p><span class="aiohm-notice-dismiss">&times;</span></div>
                
                <header class="aiohm-pa-header">
                    <button class="aiohm-pa-header-btn" id="sidebar-toggle" title="Toggle Sidebar">
                        <span class="dashicons dashicons-menu-alt"></span>
                    </button>
                    <h2 class="aiohm-pa-header-title" id="project-title">Select a Project</h2>
                    
                    <div class="aiohm-pa-window-controls">
                        <button class="aiohm-pa-header-btn" id="new-project-btn" title="New Project">
                            <span class="dashicons dashicons-plus"></span>
                        </button>
                        <button class="aiohm-pa-header-btn" id="new-chat-btn" title="New Chat">
                            <span class="dashicons dashicons-format-chat"></span>
                        </button>
                        <button class="aiohm-pa-header-btn" id="upload-file-btn" title="Upload files to project">
                            <span class="dashicons dashicons-upload"></span>
                        </button>
                        <button class="aiohm-pa-header-btn" id="research-online-prompt-btn" title="Research website with expert perspectives">
                            <span class="dashicons dashicons-search"></span>
                        </button>
                        <button class="aiohm-pa-header-btn" id="download-pdf-btn" title="Download chat as PDF">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                        <button class="aiohm-pa-header-btn" id="add-to-kb-btn" title="Add Chat to Knowledge Base" disabled>
                            <span class="dashicons dashicons-database-add"></span>
                        </button>
                        <button class="aiohm-pa-header-btn" id="toggle-notes-btn" title="Toggle Notes">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button class="aiohm-pa-header-btn" id="fullscreen-toggle-btn" title="Toggle Fullscreen">
                            <span class="dashicons dashicons-fullscreen-alt"></span>
                        </button>
                    </div>
                </header>
                
                <div id="aiohm-pa-notification" class="aiohm-pa-notification-bar" style="display: none;">
                    <p></p>
                    <span class="close-btn dashicons dashicons-no-alt"></span>
                </div>

                <div class="conversation-panel" id="conversation-panel">
                    <div class="aiohm-welcome-screen" id="welcome-instructions">
                        <div class="aiohm-welcome-message-area">
                             <div class="message assistant">
                                <p><strong><?php echo esc_html($assistant_name); ?>:</strong> <?php echo esc_html($atts['welcome_message']); ?></p>
                            </div>
                        </div>
                        <div class="aiohm-welcome-guide">
                            
                            <ul class="aiohm-instructions-list">
                                <li><span class="dashicons dashicons-plus"></span> <div><strong>New Project</strong><p>Start a new project to organize your chats.</p></div></li>
                                <li><span class="dashicons dashicons-format-chat"></span> <div><strong>New Chat</strong><p>Begin a new conversation in the current project.</p></div></li>
                                <li><span class="dashicons dashicons-upload"></span> <div><strong>Upload Files</strong><p>Add documents, images, or audio to your project.</p></div></li>
                                <li><span class="dashicons dashicons-search"></span> <div><strong>Research Online</strong><p>Analyze websites from expert perspectives (journalist, SEO, designer, etc.).</p></div></li>
                                <li><span class="dashicons dashicons-download"></span> <div><strong>Download Chat</strong><p>Save your current conversation as a PDF.</p></div></li>
                                <li><span class="dashicons dashicons-database-add"></span> <div><strong>Add to KB</strong><p>Save chat content to your knowledge base.</p></div></li>
                                <li><span class="dashicons dashicons-edit"></span> <div><strong>Toggle Notes</strong><p>Open a sidebar to jot down ideas.</p></div></li>
                                <li><span class="dashicons dashicons-fullscreen-alt"></span> <div><strong>Go Fullscreen</strong><p>Expand the interface to fill the screen.</p></div></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div id="aiohm-chat-loading" style="display: none; text-align: center; padding: 10px;">
                    Thinking...
                </div>

                <div class="aiohm-pa-input-area-wrapper">
                    <form id="private-chat-form">
                        <div class="aiohm-pa-input-area">
                            <textarea id="chat-input" placeholder="Select a project to begin..." rows="1" disabled></textarea>
                            <button id="send-btn" type="submit" disabled>
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                            <button class="aiohm-pa-header-btn" id="activate-audio-btn" type="button" title="Activate voice-to-text">
                                <span class="dashicons dashicons-microphone"></span>
                            </button>
                        </div>
                        <input type="file" id="file-upload-input" multiple accept=".txt,.pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.mp3,.wav,.m4a,.ogg" style="display: none;">
                    </form>
                </div>
            </main>

            <aside class="aiohm-pa-notes-sidebar">
                <div class="aiohm-pa-sidebar-header">
                    <h3>Notes</h3>
                    <button class="aiohm-pa-header-btn" id="close-notes-btn" title="Close Notes">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="aiohm-pa-menu">
                    <textarea id="aiohm-pa-notes-textarea" placeholder="Write your project notes here..."></textarea>
                </div>
                <div class="aiohm-pa-sidebar-footer">
                    <span id="aiohm-notes-status" class="aiohm-footer-status"></span>
                    <button type="button" id="add-note-to-kb-btn" class="aiohm-footer-action-btn">Add to KB</button>
                </div>
            </aside>
        </div>

        <?php
        return ob_get_clean();
    }
}