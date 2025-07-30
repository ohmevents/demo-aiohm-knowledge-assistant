<?php
/**
 * Settings Page controller for AIOHM Knowledge Assistant.
 * This version contains the corrected class definition, sanitization functions,
 * and admin page hook registrations for enqueuing scripts and styles.
 */
if (!defined('ABSPATH')) exit;

class AIOHM_KB_Settings_Page {
    private static $instance = null;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        add_action('admin_menu', array(self::$instance, 'register_admin_pages'));
        add_action('admin_init', array(self::$instance, 'register_settings'));
        add_action('admin_enqueue_scripts', array(self::$instance, 'enqueue_admin_scripts'));
        
        // Brand Soul AJAX handlers
        add_action('wp_ajax_aiohm_save_brand_soul', array(self::$instance, 'ajax_save_brand_soul'));
        add_action('wp_ajax_aiohm_add_brand_soul_to_kb', array(self::$instance, 'ajax_add_brand_soul_to_kb'));
        
        // Settings AJAX handlers
        add_action('wp_ajax_aiohm_save_setting', array(self::$instance, 'ajax_save_setting'));
        
        // Brand Soul PDF download handler
        add_action('admin_init', array(self::$instance, 'handle_brand_soul_pdf_download'));
    }

    private function include_header() {
        include_once AIOHM_KB_PLUGIN_DIR . 'templates/partials/header.php';
    }

    private function include_footer() {
        include_once AIOHM_KB_PLUGIN_DIR . 'templates/partials/footer.php';
    }

    /**
     * Get the appropriate menu icon based on admin color scheme
     * @return string Base64 encoded SVG data URI
     */
    private function get_menu_icon() {
        // Detect admin color scheme for dynamic theming
        $admin_color = get_user_option('admin_color');
        $is_dark_theme = in_array($admin_color, ['midnight', 'blue', 'coffee', 'ectoplasm', 'ocean']);
        
        // Try to load and optimize the actual OHM logo first
        $logo_path = $is_dark_theme 
            ? AIOHM_KB_PLUGIN_DIR . 'assets/images/OHM_logo-white.svg'
            : AIOHM_KB_PLUGIN_DIR . 'assets/images/OHM_logo.svg';
            
        if (file_exists($logo_path)) {
            // Load and optimize the OHM logo for menu use using WP_Filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $svg_content = $wp_filesystem->get_contents($logo_path);
            if ($svg_content !== false) {
                // Create optimized version by extracting key elements and simplifying
                $optimized_svg = $this->optimize_logo_for_menu($svg_content, $is_dark_theme);
                return 'data:image/svg+xml;base64,' . base64_encode($optimized_svg);
            }
        }
        
        // Professional AI brain/knowledge icon
        if ($is_dark_theme) {
            // Light icon for dark themes
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">
                <path d="M10 2C6.686 2 4 4.686 4 8c0 1.5.5 2.9 1.3 4L10 18l4.7-6c.8-1.1 1.3-2.5 1.3-4 0-3.314-2.686-6-6-6z" fill="rgba(255,255,255,0.85)"/>
                <circle cx="10" cy="7.5" r="1.5" fill="rgba(30,30,30,0.8)"/>
                <circle cx="7.5" cy="6" r="0.7" fill="rgba(30,30,30,0.8)"/>
                <circle cx="12.5" cy="6" r="0.7" fill="rgba(30,30,30,0.8)"/>
                <path d="M8 9.5c.5.3 1.2.5 2 .5s1.5-.2 2-.5" stroke="rgba(30,30,30,0.8)" stroke-width="0.8" stroke-linecap="round"/>
            </svg>';
        } else {
            // Dark icon for light themes
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">
                <path d="M10 2C6.686 2 4 4.686 4 8c0 1.5.5 2.9 1.3 4L10 18l4.7-6c.8-1.1 1.3-2.5 1.3-4 0-3.314-2.686-6-6-6z" fill="#1f5014"/>
                <circle cx="10" cy="7.5" r="1.5" fill="rgba(255,255,255,0.9)"/>
                <circle cx="7.5" cy="6" r="0.7" fill="rgba(255,255,255,0.9)"/>
                <circle cx="12.5" cy="6" r="0.7" fill="rgba(255,255,255,0.9)"/>
                <path d="M8 9.5c.5.3 1.2.5 2 .5s1.5-.2 2-.5" stroke="rgba(255,255,255,0.9)" stroke-width="0.8" stroke-linecap="round"/>
            </svg>';
        }
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Optimize the full OHM logo for use as a 20x20 menu icon
     * @param string $svg_content Original SVG content
     * @param bool $is_dark_theme Whether we're using dark theme
     * @return string Optimized SVG
     */
    private function optimize_logo_for_menu($svg_content, $is_dark_theme) {
        // Create a simplified version of the actual OHM mandala logo for the menu
        $fill_color = $is_dark_theme ? 'rgba(255,255,255,0.85)' : '#000000';
        $stroke_color = $is_dark_theme ? 'rgba(255,255,255,0.85)' : '#000000';
        
        $optimized_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none">
            <!-- Central circle -->
            <circle cx="10" cy="10" r="2.5" fill="white" stroke="' . $stroke_color . '" stroke-width="0.6"/>
            
            <!-- 8 simplified petal shapes -->
            <path d="M10 7.5 Q8.5 5 10 3.5 Q11.5 5 10 7.5" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            <path d="M12.5 10 Q15 8.5 16.5 10 Q15 11.5 12.5 10" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            <path d="M10 12.5 Q11.5 15 10 16.5 Q8.5 15 10 12.5" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            <path d="M7.5 10 Q5 11.5 3.5 10 Q5 8.5 7.5 10" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            <path d="M11.8 8.2 Q14.5 5.5 16.2 7.2 Q14.5 9.9 11.8 8.2" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            <path d="M11.8 11.8 Q14.5 14.5 12.8 16.2 Q10.1 14.5 11.8 11.8" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            <path d="M8.2 11.8 Q5.5 14.5 3.8 12.8 Q5.5 10.1 8.2 11.8" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            <path d="M8.2 8.2 Q5.5 5.5 7.2 3.8 Q9.9 5.5 8.2 8.2" fill="' . $fill_color . '" stroke="' . $stroke_color . '" stroke-width="0.3"/>
            
            <!-- Small dots at the tips -->
            <circle cx="10" cy="3" r="0.6" fill="' . $fill_color . '"/>
            <circle cx="17" cy="10" r="0.6" fill="' . $fill_color . '"/>
            <circle cx="10" cy="17" r="0.6" fill="' . $fill_color . '"/>
            <circle cx="3" cy="10" r="0.6" fill="' . $fill_color . '"/>
            <circle cx="14.5" cy="5.5" r="0.6" fill="' . $fill_color . '"/>
            <circle cx="14.5" cy="14.5" r="0.6" fill="' . $fill_color . '"/>
            <circle cx="5.5" cy="14.5" r="0.6" fill="' . $fill_color . '"/>
            <circle cx="5.5" cy="5.5" r="0.6" fill="' . $fill_color . '"/>
        </svg>';
        
        return $optimized_svg;
    }

    public function register_admin_pages() {
        // Main Menu Page - using dynamic SVG icon
        add_menu_page('AIOHM Assistant', 'AIOHM', 'manage_options', 'aiohm-dashboard', array($this, 'render_dashboard_page'), $this->get_menu_icon(), 2);

        // Submenu Pages
        add_submenu_page('aiohm-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'aiohm-dashboard', array($this, 'render_dashboard_page'));
        // Settings Section - moved to second position
        add_submenu_page('aiohm-dashboard', 'AIOHM Settings', 'Settings', 'manage_options', 'aiohm-settings', array($this, 'render_form_settings_page'));
        add_submenu_page('aiohm-dashboard', 'AI Brand Core', 'AI Brand Core', 'read', 'aiohm-brand-soul', array($this, 'render_brand_soul_page'));
        
        // Knowledge Base Section
        add_submenu_page('aiohm-dashboard', 'Scan Content', 'Scan Content', 'manage_options', 'aiohm-scan-content', array($this, 'render_scan_page'));
        add_submenu_page('aiohm-dashboard', 'Manage Knowledge Base', 'Manage KB', 'manage_options', 'aiohm-manage-kb', array($this, 'render_manage_kb_page'));
        
        // Conditionally add Mirror and Muse modes if user has access
        if (class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_club_access()) {
            add_submenu_page('aiohm-dashboard', 'Mirror Mode Settings', 'Mirror Mode', 'read', 'aiohm-mirror-mode', array($this, 'render_mirror_mode_page'));
            add_submenu_page('aiohm-dashboard', 'Muse: Brand Assistant', 'Muse Mode', 'read', 'aiohm-muse-mode', array($this, 'render_muse_mode_page'));
        }

        // MCP API page - only for Private level members
        if (AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access()) {
            add_submenu_page('aiohm-dashboard', 'MCP API', 'MCP API', 'read', 'aiohm-mcp', array($this, 'render_mcp_page'));
        }
        add_submenu_page('aiohm-dashboard', 'License', 'License', 'manage_options', 'aiohm-license', array($this, 'render_license_page'));
        add_submenu_page('aiohm-dashboard', 'Get Help', 'Get Help', 'manage_options', 'aiohm-get-help', array($this, 'render_help_page'));
    }

    public function enqueue_admin_scripts($hook) {
        
        // Load global admin styles on all AIOHM pages
        if (strpos($hook, 'aiohm-') !== false) {
            wp_enqueue_style('aiohm-admin-global-styles', AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-chat.css', array(), AIOHM_KB_VERSION);
            wp_enqueue_style('aiohm-admin-header-styles', AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-header.css', array(), AIOHM_KB_VERSION);
        }
        
        $mirror_mode_hook = 'aiohm_page_aiohm-mirror-mode';
        $muse_mode_hook = 'aiohm_page_aiohm-muse-mode';
        $settings_hook = 'aiohm_page_aiohm-settings';
        $license_hook = 'aiohm_page_aiohm-license';
        $dashboard_hook = 'aiohm_page_aiohm-dashboard';
        $toplevel_dashboard_hook = 'toplevel_page_aiohm-dashboard';
        $help_hook = 'aiohm_page_aiohm-get-help';
        $scan_hook = 'aiohm_page_aiohm-scan-content';
        $manage_kb_hook = 'aiohm_page_aiohm-manage-kb';
        $brand_soul_hook = 'aiohm_page_aiohm-brand-soul';
        $mcp_hook = 'aiohm_page_aiohm-mcp';

        // Enqueue specific assets only on the Mirror, Muse mode, or Settings pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter safe for admin asset loading
        if ($hook === $mirror_mode_hook || $hook === $muse_mode_hook || $hook === $settings_hook || (isset($_GET['page']) && in_array(sanitize_text_field(wp_unslash($_GET['page'])), ['aiohm-mirror-mode', 'aiohm-muse-mode', 'aiohm-settings']))) {
            
            wp_enqueue_style(
                'aiohm-admin-modes-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-modes.css',
                [],
                AIOHM_KB_VERSION
            );

            wp_enqueue_script(
                'aiohm-admin-modes-script',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-modes.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true // Load in footer
            );
            
            // Prepare and localize data to pass from PHP to our JavaScript file
            $localized_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'pluginUrl' => AIOHM_KB_PLUGIN_URL,
            ];

            if ($hook === $mirror_mode_hook) {
                wp_enqueue_media(); // Needed for the media uploader
                $localized_data['mode'] = 'mirror';
                $localized_data['formId'] = 'mirror-mode-settings-form';
                $localized_data['saveButtonId'] = 'save-mirror-mode-settings';
                $localized_data['saveAction'] = 'aiohm_save_mirror_mode_settings';
                $localized_data['testChatAction'] = 'aiohm_test_mirror_mode_chat';
                $localized_data['nonceFieldId'] = 'aiohm_mirror_mode_nonce_field';
                $localized_data['defaultPrompt'] = "You are the official AI Knowledge Assistant for \"%site_name%\".\n\nYour core mission is to embody our brand's tagline: \"%site_tagline%\".\n\nYou are to act as a thoughtful and emotionally intelligent guide for all website visitors, reflecting the unique voice of the brand. You should be aware that today is %day_of_week%, %current_date%.\n\nCore Instructions:\n\n1. Primary Directive:\n   Your primary goal is to answer the user's question by grounding your response in the context provided below. This context is your main source of truth.\n\n2. Tone & Personality:\n   • Speak with emotional clarity, not robotic formality\n   • Sound like a thoughtful assistant, not a sales rep\n   • Be concise, but not curt — useful, but never cold\n   • Your purpose is to express with presence, not persuasion\n\n3. Formatting Rules:\n   • Use only basic HTML tags for clarity (like <strong> or <em> if needed). Do not use Markdown\n   • Never end your response with a question like \"Do you need help with anything else?\"\n\n4. Fallback Response (Crucial):\n   If the provided context does not contain enough information to answer the user's question, you MUST respond with this exact phrase: \"Hmm… I don't want to guess here. This might need a human's wisdom. You can connect with the person behind this site on the contact page. They'll know exactly how to help.\"\n\nPrimary Context for Answering the User's Question:\n{context}";
            }

            if ($hook === $muse_mode_hook) {
                $localized_data['mode'] = 'muse';
                $localized_data['formId'] = 'muse-mode-settings-form';
                $localized_data['saveButtonId'] = 'save-muse-mode-settings';
                $localized_data['saveAction'] = 'aiohm_save_muse_mode_settings';
                $localized_data['testChatAction'] = 'aiohm_test_muse_mode_chat';
                $localized_data['nonceFieldId'] = 'aiohm_muse_mode_nonce_field';
                $localized_data['promptTextareaId'] = 'system_prompt';
                $localized_data['defaultPrompt'] = "You are Muse, a private brand assistant. Your role is to help the user develop their brand by using the provided context, which includes public information and the user's private 'Brand Soul' answers. Synthesize this information to provide creative ideas, answer strategic questions, and help draft content. Always prioritize the private 'Brand Soul' context when available.";
                $localized_data['archetypePrompts'] = [
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
            }

            if ($hook === $settings_hook) {
                $localized_data['mode'] = 'settings';
                $localized_data['ajax_url'] = admin_url('admin-ajax.php');
                $localized_data['nonce'] = wp_create_nonce('aiohm_admin_nonce');
            }

            wp_localize_script('aiohm-admin-modes-script', 'aiohm_admin_modes_data', $localized_data);
        }

        // Enqueue license page specific styles and scripts
        if ($hook === $license_hook) {
            wp_enqueue_style(
                'aiohm-admin-license-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-license.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-license',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-license.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            wp_localize_script('aiohm-admin-license', 'aiohm_license_ajax', array(
                'nonce' => wp_create_nonce('aiohm_license_verification')
            ));
        }

        // Enqueue dashboard page specific styles
        if ($hook === $dashboard_hook || $hook === $toplevel_dashboard_hook) {
            wp_enqueue_style(
                'aiohm-admin-dashboard-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-dashboard.css',
                [],
                AIOHM_KB_VERSION
            );
        }

        // Enqueue help page specific styles and scripts
        if ($hook === $help_hook) {
            wp_enqueue_style(
                'aiohm-admin-help-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-help.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-help-script',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-help.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            // Localize script for AJAX
            wp_localize_script(
                'aiohm-admin-help-script',
                'aiohm_admin_help_ajax',
                [
                    'nonce' => wp_create_nonce('aiohm_admin_nonce')
                ]
            );
        }

        // Enqueue muse mode specific scripts (in addition to existing modes script)
        if ($hook === $muse_mode_hook) {
            wp_enqueue_script(
                'aiohm-admin-muse-mode',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-muse-mode.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
        }

        // Enqueue scan website page specific styles and scripts
        if ($hook === $scan_hook) {
            wp_enqueue_style(
                'aiohm-admin-scan-website-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-scan-website.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-scan-website',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-scan-website.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            wp_localize_script('aiohm-admin-scan-website', 'aiohm_scan_website_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiohm_admin_nonce')
            ));
        }

        // Enqueue manage KB page specific styles and scripts
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter safe for admin asset loading
        if ($hook === $manage_kb_hook || (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'aiohm-manage-kb')) {
            wp_enqueue_style(
                'aiohm-admin-manage-kb-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-manage-kb.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-manage-kb',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-manage-kb.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            wp_localize_script('aiohm-admin-manage-kb', 'aiohm_manage_kb_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiohm_admin_nonce')
            ));
        }

        // Enqueue brand soul page specific styles and scripts
        if ($hook === $brand_soul_hook) {
            wp_enqueue_style(
                'aiohm-admin-brand-soul-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-brand-soul.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-brand-soul',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-brand-soul.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            wp_localize_script('aiohm-admin-brand-soul', 'aiohm_brand_soul_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiohm_brand_soul_nonce'),
                'save_answers_text' => __('Save Answers', 'aiohm-knowledge-assistant'),
                'add_to_kb_text' => __('Add to Knowledge Base', 'aiohm-knowledge-assistant'),
                'download_pdf_text' => __('Download', 'aiohm-knowledge-assistant'),
                'saving_text' => __('Saving...', 'aiohm-knowledge-assistant'),
                'download_pdf_url' => wp_nonce_url(admin_url('admin.php?page=aiohm-brand-soul&action=download_brand_soul_pdf'), 'download_brand_soul_pdf')
            ));
        }

        // Enqueue MCP page specific styles and scripts
        if ($hook === $mcp_hook) {
            wp_enqueue_style(
                'aiohm-admin-mcp-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-mcp.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-mcp',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-mcp.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            wp_localize_script('aiohm-admin-mcp', 'aiohm_mcp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiohm_mcp_nonce'),
                'mcp_enabled' => !empty($settings['mcp_enabled']),
                'strings' => array(
                    'saving' => __('Saving...', 'aiohm-knowledge-assistant'),
                    'settings_saved' => __('Settings saved successfully!', 'aiohm-knowledge-assistant'),
                    'settings_error' => __('Failed to save settings', 'aiohm-knowledge-assistant'),
                    'mcp_enabled' => __('MCP Enabled', 'aiohm-knowledge-assistant'),
                    'enable_mcp' => __('Enable MCP', 'aiohm-knowledge-assistant'),
                    'error_prefix' => __('Error: ', 'aiohm-knowledge-assistant'),
                    'server_error' => __('Server error occurred. Please try again.', 'aiohm-knowledge-assistant'),
                    'generating' => __('Generating...', 'aiohm-knowledge-assistant'),
                    'token_error' => __('Failed to generate token', 'aiohm-knowledge-assistant'),
                    'copied' => __('Copied!', 'aiohm-knowledge-assistant'),
                    'loading_tokens' => __('Loading tokens...', 'aiohm-knowledge-assistant'),
                    'tokens_error' => __('Failed to load tokens', 'aiohm-knowledge-assistant'),
                    'no_tokens' => __('No tokens found', 'aiohm-knowledge-assistant'),
                    'name' => __('Name', 'aiohm-knowledge-assistant'),
                    'permissions' => __('Permissions', 'aiohm-knowledge-assistant'),
                    'status' => __('Status', 'aiohm-knowledge-assistant'),
                    'created' => __('Created', 'aiohm-knowledge-assistant'),
                    'expires' => __('Expires', 'aiohm-knowledge-assistant'),
                    'actions' => __('Actions', 'aiohm-knowledge-assistant'),
                    'active' => __('Active', 'aiohm-knowledge-assistant'),
                    'expired' => __('Expired', 'aiohm-knowledge-assistant'),
                    'inactive' => __('Inactive', 'aiohm-knowledge-assistant'),
                    'never' => __('Never', 'aiohm-knowledge-assistant'),
                    'view' => __('View', 'aiohm-knowledge-assistant'),
                    'revoke' => __('Revoke', 'aiohm-knowledge-assistant'),
                    'remove' => __('Remove', 'aiohm-knowledge-assistant'),
                    // translators: %s is the token name
                    'revoke_confirm' => __('Are you sure you want to revoke the token "%s"? This cannot be undone.', 'aiohm-knowledge-assistant'),
                    'yes_revoke' => __('Yes, Revoke', 'aiohm-knowledge-assistant'),
                    'cancel' => __('Cancel', 'aiohm-knowledge-assistant'),
                    'revoking' => __('Revoking...', 'aiohm-knowledge-assistant'),
                    'token_revoked' => __('Token revoked successfully', 'aiohm-knowledge-assistant'),
                    'revoke_error' => __('Failed to revoke token', 'aiohm-knowledge-assistant'),
                    // translators: %s is the token name
                    'remove_confirm' => __('Are you sure you want to permanently remove the token "%s"? This cannot be undone.', 'aiohm-knowledge-assistant'),
                    'yes_remove' => __('Yes, Remove', 'aiohm-knowledge-assistant'),
                    'removing' => __('Removing...', 'aiohm-knowledge-assistant'),
                    'token_removed' => __('Token removed successfully', 'aiohm-knowledge-assistant'),
                    'remove_error' => __('Failed to remove token', 'aiohm-knowledge-assistant'),
                    'view_error' => __('Failed to view token details', 'aiohm-knowledge-assistant')
                )
            ));
        }

        // Enqueue settings backup page specific styles and scripts
        if ($hook === 'aiohm_page_aiohm-settings-backup') {
            wp_enqueue_style(
                'aiohm-admin-settings-backup-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-settings-backup.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-settings-backup',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-settings-backup.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
        }

        // Enqueue main settings page specific styles and scripts
        if ($hook === $settings_hook) {
            wp_enqueue_style(
                'aiohm-admin-settings-style',
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-settings.css',
                [],
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-settings',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-settings.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            wp_localize_script('aiohm-admin-settings', 'aiohm_admin_settings_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiohm_admin_nonce')
            ));
        }

        // Brand Soul page scripts
        if ($hook == 'aiohm_page_aiohm-brand-soul') {
            wp_enqueue_style(
                'aiohm-admin-brand-soul', 
                AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-admin-brand-soul.css', 
                array(), 
                AIOHM_KB_VERSION
            );
            
            wp_enqueue_script(
                'aiohm-admin-brand-soul',
                AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-admin-brand-soul.js',
                ['jquery'],
                AIOHM_KB_VERSION,
                true
            );
            
            wp_localize_script('aiohm-admin-brand-soul', 'aiohm_brand_soul_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aiohm_brand_soul_nonce'),
                'download_pdf_url' => add_query_arg([
                    'action' => 'aiohm_download_brand_soul_pdf',
                    'nonce' => wp_create_nonce('aiohm_brand_soul_pdf')
                ], admin_url('admin.php')),
                'save_answers_text' => esc_html__('Save Answers', 'aiohm-knowledge-assistant'),
                'add_to_kb_text' => esc_html__('Add to Knowledge Base', 'aiohm-knowledge-assistant'),
                'download_pdf_text' => esc_html__('Download', 'aiohm-knowledge-assistant'),
                'saving_text' => esc_html__('Saving...', 'aiohm-knowledge-assistant'),
                'adding_text' => esc_html__('Adding to KB...', 'aiohm-knowledge-assistant'),
                'save_success_text' => esc_html__('Brand soul answers saved successfully!', 'aiohm-knowledge-assistant'),
                'progress_save_success_text' => esc_html__('Progress saved!', 'aiohm-knowledge-assistant'),
                'save_error_text' => esc_html__('Failed to save answers. Please try again.', 'aiohm-knowledge-assistant'),
                'add_to_kb_success_text' => esc_html__('Brand soul successfully added to your private knowledge base!', 'aiohm-knowledge-assistant'),
                'add_to_kb_error_text' => esc_html__('Failed to add to knowledge base. Please try again.', 'aiohm-knowledge-assistant'),
                'server_error_text' => esc_html__('Server error. Please try again later.', 'aiohm-knowledge-assistant')
            ));
        }
    }

    public function render_dashboard_page() {
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-dashboard.php';
        $this->include_footer();
    }

    public function render_form_settings_page() {
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-settings.php';
        $this->include_footer();
    }
    
    public function render_scan_page() {
        $site_crawler = new AIOHM_KB_Site_Crawler();
        $uploads_crawler = new AIOHM_KB_Uploads_Crawler();
        $site_stats = $site_crawler->get_scan_stats();
        $uploads_stats = $uploads_crawler->get_stats();
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/scan-website.php';
        $this->include_footer();
    }
    
    public function render_manage_kb_page() {
        $this->include_header();
        $manager = new AIOHM_KB_Manager();
        $manager->display_page();
        $this->include_footer();
    }

    public function render_brand_soul_page() {
        if (!current_user_can('read')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiohm-knowledge-assistant'));
        }
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-brand-soul.php';
        $this->include_footer();
    }

    public function render_mirror_mode_page() {
        if (!class_exists('AIOHM_KB_PMP_Integration') || !AIOHM_KB_PMP_Integration::aiohm_user_has_club_access()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiohm-knowledge-assistant'));
        }
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-mirror-mode.php';
        $this->include_footer();
    }
    
    public function render_muse_mode_page() {
        if (!current_user_can('read')) {
             wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiohm-knowledge-assistant'));
        }
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-muse-mode.php';
        $this->include_footer();
    }

    public function render_help_page() {
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-help.php';
        $this->include_footer();
    }

    public function render_mcp_page() {
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-mcp.php';
        $this->include_footer();
    }

    public function render_license_page() {
        $this->include_header();
        include AIOHM_KB_PLUGIN_DIR . 'templates/admin-license.php';
        $this->include_footer();
    }

    public function register_settings() {
        register_setting('aiohm_kb_settings', 'aiohm_kb_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $old_settings = get_option('aiohm_kb_settings', []);
        $sanitized = $old_settings;

        // Sanitize API keys and encrypt them for storage
        $api_keys = ['openai_api_key', 'gemini_api_key', 'claude_api_key'];
        foreach($api_keys as $field) {
            if (isset($input[$field])) {
                $sanitized_key = sanitize_text_field(trim($input[$field]));
                // Only encrypt if not empty and different from existing
                if (!empty($sanitized_key) && $sanitized_key !== $old_settings[$field]) {
                    $sanitized[$field] = AIOHM_KB_Assistant::encrypt_api_key($sanitized_key);
                } elseif (empty($sanitized_key)) {
                    $sanitized[$field] = '';
                }
            }
        }
        
        // ShareAI API key and model are handled via AJAX - skip processing in settings page
        // This prevents conflicts between the settings form handler and the AJAX handler
        if (isset($input['shareai_api_key'])) {
            // Check if AJAX is currently saving to avoid conflicts
            $ajax_saving_key = get_transient('aiohm_ajax_saving_shareai_api_key');
            $ajax_saving_model = get_transient('aiohm_ajax_saving_shareai_model');
            
            // ShareAI settings are handled via AJAX to prevent conflicts
            // Don't process ShareAI settings here to avoid conflicts with AJAX handler
        }
        
        // Sanitize other text fields (shareai_model handled via AJAX)
        $text_fields = ['aiohm_app_email', 'private_llm_server_url', 'private_llm_model'];
        foreach($text_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field(trim($input[$field]));
            }
        }
        
        // Sanitize select fields
        if (isset($input['default_ai_provider'])) {
            $sanitized['default_ai_provider'] = sanitize_text_field($input['default_ai_provider']);
        }
        if (isset($input['scan_schedule'])) { 
            $allowed_schedules = ['none', 'daily', 'weekly', 'monthly'];
            $sanitized['scan_schedule'] = in_array($input['scan_schedule'], $allowed_schedules) ? sanitize_key($input['scan_schedule']) : 'none';
        }

        // Sanitize checkboxes
        $checkboxes = ['chat_enabled', 'show_floating_chat', 'enable_private_assistant', 'enable_search_shortcode', 'delete_data_on_uninstall', 'mcp_enabled', 'mcp_require_https'];
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($input[$checkbox]) && !empty($input[$checkbox]) && $input[$checkbox] !== '0';
        }
        
        // Sanitize MCP rate limit
        if (isset($input['mcp_rate_limit'])) {
            $sanitized['mcp_rate_limit'] = min(10000, max(100, intval($input['mcp_rate_limit'])));
        }
        
        // Handle nested Mirror Mode settings
        if (isset($input['mirror_mode'])) {
            $mirror_mode = $input['mirror_mode'];
            
            // Sanitize text fields
            $mirror_text_fields = ['business_name', 'qa_system_message', 'ai_model', 'qa_temperature', 'primary_color', 'background_color', 'text_color', 'ai_avatar', 'welcome_message'];
            foreach($mirror_text_fields as $field) {
                if (isset($mirror_mode[$field])) {
                    $sanitized['mirror_mode'][$field] = sanitize_text_field(trim($mirror_mode[$field]));
                }
            }
            
            // Sanitize URL with proper validation
            if (isset($mirror_mode['meeting_button_url'])) {
                $url = trim($mirror_mode['meeting_button_url']);
                if (!empty($url)) {
                    // Add protocol if missing
                    if (!preg_match('/^https?:\/\//', $url)) {
                        $url = 'https://' . $url;
                    }
                    $sanitized['mirror_mode']['meeting_button_url'] = esc_url_raw($url);
                } else {
                    $sanitized['mirror_mode']['meeting_button_url'] = '';
                }
            }
        }
        
        return $sanitized;
    }


    /**
     * Handle AJAX request to save brand soul answers
     */
    public function ajax_save_brand_soul() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_brand_soul_nonce')) {
            wp_die(json_encode(['success' => false, 'data' => ['message' => 'Security check failed']]));
        }

        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(json_encode(['success' => false, 'data' => ['message' => 'Insufficient permissions']]));
        }

        // Parse the form data
        if (!isset($_POST['data'])) {
            wp_die(json_encode(['success' => false, 'data' => ['message' => 'No data provided']]));
        }
        parse_str(sanitize_textarea_field(wp_unslash($_POST['data'])), $form_data);
        $answers = isset($form_data['answers']) ? $form_data['answers'] : [];

        // Sanitize answers
        $sanitized_answers = [];
        foreach ($answers as $key => $value) {
            $sanitized_answers[sanitize_key($key)] = sanitize_textarea_field($value);
        }

        // Save to user meta
        $user_id = get_current_user_id();
        $saved = update_user_meta($user_id, 'aiohm_brand_soul_answers', $sanitized_answers);

        if ($saved !== false) {
            wp_send_json_success(['message' => __('Brand soul answers saved successfully!', 'aiohm-knowledge-assistant')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save answers. Please try again.', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Handle AJAX request to add brand soul to knowledge base
     */
    public function ajax_add_brand_soul_to_kb() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_brand_soul_nonce')) {
            wp_die(json_encode(['success' => false, 'data' => ['message' => 'Security check failed']]));
        }

        // Check user permissions
        if (!current_user_can('read')) {
            wp_die(json_encode(['success' => false, 'data' => ['message' => 'Insufficient permissions']]));
        }

        try {
            // Parse the form data
            if (!isset($_POST['data'])) {
                wp_die(json_encode(['success' => false, 'data' => ['message' => 'No data provided']]));
            }
            parse_str(sanitize_textarea_field(wp_unslash($_POST['data'])), $form_data);
            $answers = isset($form_data['answers']) ? $form_data['answers'] : [];

            if (empty($answers)) {
                wp_send_json_error(['message' => 'No answers to add to knowledge base.']);
                return;
            }

            // Format answers for knowledge base
            $content = "# Brand Soul Questionnaire Answers\n\n";
            $content .= "Generated on: " . gmdate('Y-m-d H:i:s') . " UTC\n\n";

            // Define question categories
            $questions = [
                '✨ Foundation' => [
                    'foundation_1' => "What's the deeper purpose behind your brand — beyond profit?",
                    'foundation_2' => "What life experiences shaped this work you now do?",
                    'foundation_3' => "Who were you before this calling emerged?",
                    'foundation_4' => "If your brand had a soul story, how would you tell it?",
                    'foundation_5' => "What's one transformation you've witnessed that reminds you why you do this?",
                ],
                '🌀 Energy' => [
                    'energy_1' => "What 3 words describe the emotional tone of your brand voice?",
                    'energy_2' => "How do you want your audience to feel after encountering your message?",
                    'energy_3' => "What do you not want to sound like?",
                    'energy_4' => "Do you prefer poetic, punchy, playful, or professional language?",
                    'energy_5' => "Share a quote, phrase, or piece of content that feels like you.",
                ],
                '🎨 Expression' => [
                    'expression_1' => "What are your brand's primary colors (and any specific hex codes)?",
                    'expression_2' => "What font(s) do you use — or wish to use — for headers and body text?",
                    'expression_3' => "Is there a visual theme (earthy, cosmic, minimalist, ornate) that matches your brand essence?",
                    'expression_4' => "Are there any logos, patterns, or symbols that hold meaning for your brand?",
                    'expression_5' => "What offerings are you currently sharing with the world — and how are they priced or exchanged?",
                ],
                '🚀 Direction' => [
                    'direction_1' => "What's your current main offer or project you want support with?",
                    'direction_2' => "Who is your dream client? Describe them with emotion and detail.",
                    'direction_3' => "What are 3 key goals you have for the next 6 months?",
                    'direction_4' => "Where do you feel stuck, overwhelmed, or unsure — and where would you love AI support?",
                    'direction_5' => "If this AI assistant could speak your soul fluently, what would you want it to never forget?",
                ],
            ];

            foreach ($questions as $section_title => $section_questions) {
                $content .= "## " . $section_title . "\n\n";
                foreach ($section_questions as $key => $question) {
                    if (!empty($answers[$key])) {
                        $content .= "### " . $question . "\n\n";
                        $content .= $answers[$key] . "\n\n";
                    }
                }
            }

            // Add to knowledge base using RAG engine
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $user_id = get_current_user_id();
            
            $result = $rag_engine->add_entry(
                $content,
                'brand_soul',
                'Brand Soul Questionnaire',
                [
                    'source_type' => 'brand_soul',
                    'user_id' => $user_id,
                    'created_date' => gmdate('Y-m-d H:i:s')
                ],
                $user_id,
                0
            );

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => 'Failed to add to knowledge base: ' . $result->get_error_message()]);
            } else {
                wp_send_json_success(['message' => __('Brand soul successfully added to your private knowledge base!', 'aiohm-knowledge-assistant')]);
            }

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate and download brand soul PDF
     */
    public function download_brand_soul_pdf() {
        // Check user permissions
        if (!current_user_can('read')) {
            wp_die('Insufficient permissions');
        }

        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'aiohm_brand_soul_pdf')) {
            wp_die('Security check failed');
        }

        try {
            $user_id = get_current_user_id();
            $answers = get_user_meta($user_id, 'aiohm_brand_soul_answers', true);

            if (empty($answers)) {
                wp_die('No brand soul answers found. Please complete the questionnaire first.');
            }

            // Load PDF library
            require_once AIOHM_KB_PLUGIN_DIR . 'includes/simple-pdf-generator.php';
            
            // Create simple PDF instance
            $pdf = new AIOHM_Simple_PDF_Generator('Brand Soul Questionnaire');
            
            // Add title
            $pdf->ChapterTitle('Brand Soul Questionnaire');

            // Define questions for PDF
            $questions = [
                '✨ Foundation' => [
                    'foundation_1' => "What's the deeper purpose behind your brand — beyond profit?",
                    'foundation_2' => "What life experiences shaped this work you now do?",
                    'foundation_3' => "Who were you before this calling emerged?",
                    'foundation_4' => "If your brand had a soul story, how would you tell it?",
                    'foundation_5' => "What's one transformation you've witnessed that reminds you why you do this?",
                ],
                '🌀 Energy' => [
                    'energy_1' => "What 3 words describe the emotional tone of your brand voice?",
                    'energy_2' => "How do you want your audience to feel after encountering your message?",
                    'energy_3' => "What do you not want to sound like?",
                    'energy_4' => "Do you prefer poetic, punchy, playful, or professional language?",
                    'energy_5' => "Share a quote, phrase, or piece of content that feels like you.",
                ],
                '🎨 Expression' => [
                    'expression_1' => "What are your brand's primary colors (and any specific hex codes)?",
                    'expression_2' => "What font(s) do you use — or wish to use — for headers and body text?",
                    'expression_3' => "Is there a visual theme (earthy, cosmic, minimalist, ornate) that matches your brand essence?",
                    'expression_4' => "Are there any logos, patterns, or symbols that hold meaning for your brand?",
                    'expression_5' => "What offerings are you currently sharing with the world — and how are they priced or exchanged?",
                ],
                '🚀 Direction' => [
                    'direction_1' => "What's your current main offer or project you want support with?",
                    'direction_2' => "Who is your dream client? Describe them with emotion and detail.",
                    'direction_3' => "What are 3 key goals you have for the next 6 months?",
                    'direction_4' => "Where do you feel stuck, overwhelmed, or unsure — and where would you love AI support?",
                    'direction_5' => "If this AI assistant could speak your soul fluently, what would you want it to never forget?",
                ],
            ];

            // Add content to PDF
            foreach ($questions as $section_title => $section_questions) {
                $pdf->ChapterTitle($section_title);
                foreach ($section_questions as $key => $question) {
                    if (!empty($answers[$key])) {
                        $pdf->MessageBlock('question', $question, gmdate('Y-m-d H:i:s'));
                        $pdf->MessageBlock('answer', $answers[$key], gmdate('Y-m-d H:i:s'));
                    }
                }
            }

            // Output PDF
            $filename = 'brand-soul-questionnaire-' . gmdate('Y-m-d') . '.pdf';
            $pdf->Output($filename, 'D');

        } catch (Exception $e) {
            wp_die('Error generating PDF: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * AJAX handler for saving individual settings
     */
    public function ajax_save_setting() {
        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'aiohm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $setting_key = sanitize_text_field(wp_unslash($_POST['setting_key'] ?? ''));
        $setting_value = sanitize_text_field(wp_unslash($_POST['setting_value'] ?? ''));

        if (empty($setting_key)) {
            wp_send_json_error('Setting key is required');
            return;
        }

        // Get current settings
        $current_settings = AIOHM_KB_Assistant::get_settings();
        
        // Update the specific setting
        $current_settings[$setting_key] = $setting_value;
        
        // Save the updated settings
        $result = update_option('aiohm_kb_settings', $current_settings);
        
        if ($result !== false) {
            wp_send_json_success('Setting saved successfully');
        } else {
            wp_send_json_error('Failed to save setting');
        }
    }

    /**
     * Handle brand soul PDF download request
     */
    public function handle_brand_soul_pdf_download() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is handled in download_brand_soul_pdf() method
        if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'aiohm_download_brand_soul_pdf') {
            $this->download_brand_soul_pdf();
        }
    }
}