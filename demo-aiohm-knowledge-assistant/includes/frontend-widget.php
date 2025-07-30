<?php
/**
 * Frontend widget functionality - enqueue scripts and styles.
 * This version is complete and uses the central settings function.
 */
if (!defined('ABSPATH')) exit;

class AIOHM_KB_Frontend_Widget {
    
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        // Only load assets if they should be loaded (checked in should_load_assets)
        if (!self::should_load_assets()) {
            return;
        }
        
        wp_enqueue_script(
            'aiohm-chat',
            AIOHM_KB_PLUGIN_URL . 'assets/js/aiohm-chat-shortcode.js',
            array('jquery'),
            AIOHM_KB_VERSION,
            true
        );
        
        wp_enqueue_style(
            'aiohm-chat',
            AIOHM_KB_PLUGIN_URL . 'assets/css/aiohm-chat.css',
            array(),
            AIOHM_KB_VERSION
        );
        
        // Pass settings to frontend JavaScript
        $settings = AIOHM_KB_Assistant::get_settings();
        wp_localize_script('aiohm-chat', 'aiohm_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiohm_chat_nonce'),
            'chat_enabled' => $settings['chat_enabled'] ?? true,
        ));
    }
    
    /**
     * Check if assets should be loaded on current page
     */
    private static function should_load_assets() {
        // Do not load on admin pages
        if (is_admin()) {
            return false;
        }

        // Do not load if Elementor editor is active
        if (did_action('elementor/loaded') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return false;
        }
        
        global $post;
        $settings = AIOHM_KB_Assistant::get_settings();
        
        // Check for chat shortcode and chat enable setting
        if (($post && has_shortcode($post->post_content, 'aiohm_chat')) && ($settings['chat_enabled'] ?? true)) {
            return true;
        }
        
        // Check for search shortcode
        if ($post && has_shortcode($post->post_content, 'aiohm_search')) {
            return true;
        }
        
        return false;
    }
}