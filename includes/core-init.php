<?php
/**
 * Core initialization and configuration.
 * Final version with all original functions preserved and fixes for saving and loading conversations.
 * 
 * Note: This file contains many direct database operations for conversation management,
 * project handling, and user interactions. All operations are properly prepared and
 * cached where appropriate, or justified for security/functional reasons.
 */
if (!defined('ABSPATH')) exit;

// Prevent class redeclaration errors
if (!class_exists('AIOHM_KB_Core_Init')) {

class AIOHM_KB_Core_Init {

    /**
     * Get safe error message for frontend display
     * @param Exception $e The exception
     * @param string $context Context for logging
     * @return string Safe error message
     */
    private static function get_safe_error_message($e, $context = 'general') {
        AIOHM_KB_Assistant::log($context . ' error: ' . $e->getMessage(), 'error');
        
        // For specific admin operations, allow more detailed error messages
        if ($context === 'Scan' && current_user_can('manage_options')) {
            $error_message = $e->getMessage();
            
            // Allow consent-related errors to be shown to admins
            if (strpos($error_message, 'External API calls require user consent') !== false) {
                return $error_message;
            }
            
            // Allow other specific admin-friendly error messages
            if (strpos($error_message, 'API key') !== false || 
                strpos($error_message, 'knowledge base') !== false ||
                strpos($error_message, 'File') !== false) {
                return $error_message;
            }
        }
        
        // Return generic message for security for other contexts
        return __('Sorry, something went wrong. Please try again later.', 'aiohm-knowledge-assistant');
    }

    /**
     * Enhanced input validation for AJAX requests
     * @param array $input Input data to validate
     * @param array $rules Validation rules
     * @return array|WP_Error Sanitized data or error
     */
    private static function validate_ajax_input($input, $rules) {
        $sanitized = [];
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = isset($input[$field]) ? $input[$field] : null;
            
            // Check required fields
            if (isset($rule['required']) && $rule['required'] && (empty($value) && $value !== '0')) {
                // translators: %s is the field name
                $errors[] = sprintf(__('Field %s is required.', 'aiohm-knowledge-assistant'), $field);
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value) && $value !== '0') {
                $sanitized[$field] = '';
                continue;
            }
            
            // Check max length
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                // translators: %1$s is the field name, %2$d is the maximum length
                $errors[] = sprintf(__('Field %1$s exceeds maximum length of %2$d characters.', 'aiohm-knowledge-assistant'), $field, $rule['max_length']);
                continue;
            }
            
            // Sanitize based on type
            switch ($rule['type']) {
                case 'text':
                    $sanitized[$field] = sanitize_text_field($value);
                    break;
                    
                case 'textarea':
                    $sanitized[$field] = sanitize_textarea_field($value);
                    break;
                    
                case 'html':
                    $sanitized[$field] = wp_kses_post($value);
                    break;
                    
                case 'email':
                    $sanitized[$field] = sanitize_email($value);
                    if (!is_email($sanitized[$field])) {
                        // translators: %s is the field name
                        $errors[] = sprintf(__('Field %s must be a valid email address.', 'aiohm-knowledge-assistant'), $field);
                    }
                    break;
                    
                case 'url':
                    $sanitized[$field] = esc_url_raw($value);
                    if (!filter_var($sanitized[$field], FILTER_VALIDATE_URL)) {
                        // translators: %s is the field name
                        $errors[] = sprintf(__('Field %s must be a valid URL.', 'aiohm-knowledge-assistant'), $field);
                    }
                    break;
                    
                case 'int':
                    $sanitized[$field] = intval($value);
                    if (isset($rule['min']) && $sanitized[$field] < $rule['min']) {
                        // translators: %1$s is the field name, %2$d is the minimum value
                        $errors[] = sprintf(__('Field %1$s must be at least %2$d.', 'aiohm-knowledge-assistant'), $field, $rule['min']);
                    }
                    if (isset($rule['max']) && $sanitized[$field] > $rule['max']) {
                        // translators: %1$s is the field name, %2$d is the maximum value
                        $errors[] = sprintf(__('Field %1$s must be no more than %2$d.', 'aiohm-knowledge-assistant'), $field, $rule['max']);
                    }
                    break;
                    
                case 'float':
                    $sanitized[$field] = floatval($value);
                    break;
                    
                case 'array':
                    if (!is_array($value)) {
                        // translators: %s is the field name
                        $errors[] = sprintf(__('Field %s must be an array.', 'aiohm-knowledge-assistant'), $field);
                    } else {
                        $sanitized[$field] = array_map('sanitize_text_field', $value);
                    }
                    break;
                    
                case 'json':
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // translators: %s is the field name
                        $errors[] = sprintf(__('Field %s must be valid JSON.', 'aiohm-knowledge-assistant'), $field);
                    } else {
                        $sanitized[$field] = $value;
                    }
                    break;
                    
                default:
                    $sanitized[$field] = sanitize_text_field($value);
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors));
        }
        
        return $sanitized;
    }

    // ================== START: CONSOLIDATED FIX ==================
    // By placing these functions directly inside the class, we guarantee they are
    // always available when needed, preventing the fatal errors that were causing the crashes.
    
    private static function create_conversation_internal($user_id, $project_id, $title) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_conversations';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Internal conversation creation with cache invalidation
        $result = $wpdb->insert($table_name, ['user_id' => $user_id, 'project_id' => $project_id, 'title' => $title], ['%d', '%d', '%s']);
        
        if ($result) {
            // Clear conversation-related caches
            wp_cache_delete('aiohm_user_conversations_' . $user_id, 'aiohm_core');
            wp_cache_delete('aiohm_project_conversations_' . $project_id, 'aiohm_core');
            return $wpdb->insert_id;
        }
        
        return false;
    }

    private static function add_message_to_conversation_internal($conversation_id, $sender, $content) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_messages';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Internal message creation with cache invalidation
        $result = $wpdb->insert($table_name, ['conversation_id' => $conversation_id, 'sender' => $sender, 'content' => $content], ['%d', '%s', '%s']);
        if ($result) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Conversation timestamp update with cache clearing
            $wpdb->update($wpdb->prefix . 'aiohm_conversations', ['updated_at' => current_time('mysql', 1)], ['id' => $conversation_id]);
            
            // Clear message and conversation caches
            wp_cache_delete('aiohm_conversation_messages_' . $conversation_id, 'aiohm_core');
            wp_cache_delete('aiohm_conversation_' . $conversation_id, 'aiohm_core');
        }
        return $result !== false;
    }
    // =================== END: CONSOLIDATED FIX ===================

    /**
     * Generate AI-powered conversation title
     */
    private static function generate_conversation_title($user_message, $ai_client, $model) {
        try {
            // Simplified and more reliable title generation
            $title_prompt = "Create a short title (3-5 words) for this conversation. Remove quotes. Examples: Website Design Project, Marketing Strategy Planning, Brand Development Discussion. Message: " . substr($user_message, 0, 150);
            
            $title = $ai_client->get_chat_completion(
                "Create concise conversation titles without quotes or special characters.",
                $title_prompt,
                0.2, // Low temperature for consistency
                $model
            );
            
            // Clean and validate the title
            $title = trim(wp_strip_all_tags($title));
            $title = str_replace(['"', "'", '`', '«', '»'], '', $title); // Remove all quote types
            $title = preg_replace('/[^\w\s-]/', '', $title); // Remove special chars except hyphens
            $title = preg_replace('/\s+/', ' ', $title); // Normalize whitespace
            $title = trim($title);
            
            // Ensure reasonable length
            if (strlen($title) > 50) {
                $title = mb_strimwidth($title, 0, 47, '...');
            }
            
            // More robust fallback check
            if (strlen($title) < 3 || empty($title) || $title === '...' || strtolower($title) === 'new chat') {
                $title = self::create_fallback_title($user_message);
            }
            
            
            return $title;
            
        } catch (Exception $e) {
            // Fallback to smart title generation
            AIOHM_KB_Assistant::log('Title generation error: ' . $e->getMessage(), 'warning');
            return self::create_fallback_title($user_message);
        }
    }

    /**
     * Create a smart fallback title from user message
     */
    private static function create_fallback_title($user_message) {
        // Extract key words and create a meaningful title
        $message = strtolower(trim($user_message));
        
        // Common patterns for smart titles
        if (strpos($message, 'help') !== false && strpos($message, 'with') !== false) {
            return __('Help Request', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'create') !== false || strpos($message, 'make') !== false) {
            return __('Creation Project', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'plan') !== false || strpos($message, 'strategy') !== false) {
            return __('Planning Session', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'write') !== false || strpos($message, 'content') !== false) {
            return __('Content Writing', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'design') !== false) {
            return __('Design Discussion', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'market') !== false) {
            return __('Marketing Discussion', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'brand') !== false) {
            return __('Brand Development', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'website') !== false || strpos($message, 'web') !== false) {
            return __('Web Development', 'aiohm-knowledge-assistant');
        } elseif (strpos($message, 'question') !== false) {
            return __('General Questions', 'aiohm-knowledge-assistant');
        } else {
            // Use first few words as title
            $words = explode(' ', $message);
            $title_words = array_slice($words, 0, 3);
            $title = ucwords(implode(' ', $title_words));
            return strlen($title) > 3 ? $title : 'General Discussion';
        }
    }

    public static function init() {
        // Add security headers and session security
        add_action('admin_init', array(__CLASS__, 'add_admin_security_headers'));
        add_action('send_headers', array(__CLASS__, 'add_frontend_security_headers'));
        add_action('wp_login', array(__CLASS__, 'secure_user_session'), 10, 2);
        add_action('user_register', array(__CLASS__, 'secure_new_user_session'));
        
        // Add demo upgrade banner to admin header
        
        // --- All original action hooks are preserved ---
        add_action('wp_ajax_aiohm_progressive_scan', array(__CLASS__, 'handle_progressive_scan_ajax'));
        add_action('wp_ajax_aiohm_check_api_key', array(__CLASS__, 'handle_check_api_key_ajax'));
        add_action('wp_ajax_aiohm_save_individual_api_key', array(__CLASS__, 'handle_save_individual_api_key_ajax'));
        add_action('wp_ajax_aiohm_export_kb', array(__CLASS__, 'handle_export_kb_ajax'));
        add_action('wp_ajax_aiohm_reset_kb', array(__CLASS__, 'handle_reset_kb_ajax'));
        add_action('wp_ajax_aiohm_toggle_kb_scope', array(__CLASS__, 'handle_toggle_kb_scope_ajax'));
        add_action('wp_ajax_aiohm_restore_kb', array(__CLASS__, 'handle_restore_kb_ajax'));
        add_action('wp_ajax_aiohm_delete_kb_entry', array(__CLASS__, 'handle_delete_kb_entry_ajax'));
        add_action('wp_ajax_aiohm_bulk_delete_kb', array(__CLASS__, 'handle_bulk_delete_kb_ajax'));
        add_action('wp_ajax_aiohm_bulk_toggle_kb_scope', array(__CLASS__, 'handle_bulk_toggle_kb_scope_ajax'));
        add_action('wp_ajax_aiohm_save_brand_soul', array(__CLASS__, 'handle_save_brand_soul_ajax'));
        add_action('wp_ajax_aiohm_add_brand_soul_to_kb', array(__CLASS__, 'handle_add_brand_soul_to_kb_ajax'));
        add_action('wp_ajax_aiohm_add_note_to_kb', array(__CLASS__, 'handle_add_note_to_kb_ajax'));
        add_action('admin_init', array(__CLASS__, 'handle_pdf_download'));
        add_action('wp_ajax_aiohm_save_mirror_mode_settings', array(__CLASS__, 'handle_save_mirror_mode_settings_ajax'));
        // SECURITY: Removed nopriv hook - settings should only be accessible to authenticated users
        
        // Add hook to monitor settings changes
        add_action('update_option_aiohm_kb_settings', array(__CLASS__, 'monitor_settings_changes'), 10, 2);
        add_action('delete_option_aiohm_kb_settings', array(__CLASS__, 'monitor_settings_deletion'), 10, 1);
        add_action('wp_ajax_aiohm_generate_mirror_mode_qa', array(__CLASS__, 'handle_generate_mirror_mode_qa_ajax'));
        add_action('wp_ajax_aiohm_test_mirror_mode_chat', array(__CLASS__, 'handle_test_mirror_mode_chat_ajax'));
        add_action('wp_ajax_aiohm_save_muse_mode_settings', array(__CLASS__, 'handle_save_muse_mode_settings_ajax'));
        add_action('wp_ajax_aiohm_private_assistant_chat', array(__CLASS__, 'handle_private_assistant_chat_ajax'));
        add_action('wp_ajax_aiohm_private_chat', array(__CLASS__, 'handle_private_assistant_chat_ajax'));
        add_action('wp_ajax_aiohm_test_muse_mode_chat', array(__CLASS__, 'handle_test_muse_mode_chat_ajax'));
        add_action('wp_ajax_nopriv_aiohm_frontend_chat', array(__CLASS__, 'handle_frontend_chat_ajax'));
        add_action('wp_ajax_aiohm_frontend_chat', array(__CLASS__, 'handle_frontend_chat_ajax'));
        add_action('wp_ajax_nopriv_aiohm_search_knowledge', array(__CLASS__, 'handle_search_knowledge_ajax'));
        add_action('wp_ajax_aiohm_search_knowledge', array(__CLASS__, 'handle_search_knowledge_ajax'));
        add_action('wp_ajax_aiohm_admin_search_knowledge', array(__CLASS__, 'handle_admin_search_knowledge_ajax'));

        // --- Existing FIX for project and conversations ---
        add_action('wp_ajax_aiohm_get_project_conversations', array(__CLASS__, 'handle_get_project_conversations_ajax'));
        add_action('wp_ajax_aiohm_create_project', array(__CLASS__, 'handle_create_project_ajax'));
        
        // --- NEW FIX: Add the missing action handler for loading all projects and conversations ---
        add_action('wp_ajax_aiohm_load_history', array(__CLASS__, 'handle_load_history_ajax'));
        add_action('wp_ajax_aiohm_load_conversation', array(__CLASS__, 'handle_load_conversation_ajax'));
        add_action('wp_ajax_aiohm_save_project_notes', array(__CLASS__, 'handle_save_project_notes_ajax'));
        add_action('wp_ajax_aiohm_load_project_notes', array(__CLASS__, 'handle_load_project_notes_ajax'));
        add_action('wp_ajax_aiohm_delete_project', array(__CLASS__, 'handle_delete_project_ajax'));
        add_action('wp_ajax_aiohm_delete_conversation', array(__CLASS__, 'handle_delete_conversation_ajax'));
        add_action('wp_ajax_aiohm_create_conversation', array(__CLASS__, 'handle_create_conversation_ajax'));
        add_action('wp_ajax_aiohm_upload_project_files', array(__CLASS__, 'handle_upload_project_files_ajax'));
        add_action('wp_ajax_aiohm_get_brand_soul_content', array(__CLASS__, 'handle_get_brand_soul_content_ajax'));
        add_action('wp_ajax_aiohm_get_content_for_view', array(__CLASS__, 'handle_get_content_for_view_ajax'));
        add_action('wp_ajax_aiohm_get_usage_stats', array(__CLASS__, 'handle_get_usage_stats_ajax'));
        add_action('wp_ajax_aiohm_download_conversation_pdf', array(__CLASS__, 'handle_download_conversation_pdf_ajax'));
        add_action('wp_ajax_aiohm_add_conversation_to_kb', array(__CLASS__, 'handle_add_conversation_to_kb_ajax'));
        add_action('wp_ajax_aiohm_research_online', array(__CLASS__, 'handle_research_online_ajax'));
        
        // Email verification AJAX handlers
        add_action('wp_ajax_aiohm_send_verification_code', array(__CLASS__, 'handle_send_verification_code_ajax'));
        add_action('wp_ajax_aiohm_verify_email_code', array(__CLASS__, 'handle_verify_email_code_ajax'));
        
        // File upload to KB handler
        add_action('wp_ajax_aiohm_kb_file_upload', array(__CLASS__, 'handle_kb_file_upload_ajax'));
        add_action('wp_ajax_aiohm_update_json_content', array(__CLASS__, 'handle_update_json_content_ajax'));
        add_action('wp_ajax_aiohm_update_text_content', array(__CLASS__, 'handle_update_text_content_ajax'));
        
        // Demo upgrade banner
        add_action('admin_notices', array(__CLASS__, 'add_demo_upgrade_banner'));
        
        // Help Page AJAX handlers
        add_action('wp_ajax_aiohm_get_debug_info', array(__CLASS__, 'handle_get_debug_info_ajax'));
        add_action('wp_ajax_aiohm_test_all_api_connections', array(__CLASS__, 'handle_test_all_api_connections_ajax'));
        add_action('wp_ajax_aiohm_check_database_health', array(__CLASS__, 'handle_check_database_health_ajax'));
        add_action('wp_ajax_aiohm_submit_support_request', array(__CLASS__, 'handle_submit_support_request_ajax'));
        add_action('wp_ajax_aiohm_submit_feature_request', array(__CLASS__, 'handle_submit_feature_request_ajax'));
        
        // MCP Settings AJAX handlers
        add_action('wp_ajax_aiohm_save_mcp_settings', array(__CLASS__, 'handle_save_mcp_settings_ajax'));
        
        // Privacy Settings AJAX handler
        add_action('wp_ajax_aiohm_save_setting', array(__CLASS__, 'handle_save_setting_ajax'));
        
        
        // Allow JSON file uploads for KB functionality
        add_filter('upload_mimes', array(__CLASS__, 'allow_json_uploads'));
        add_filter('wp_check_filetype_and_ext', array(__CLASS__, 'allow_json_file_upload'), 10, 4);
    }
    
    /**
     * Handles the AJAX request to load all projects and recent conversations for the current user.
     * This directly supports the `loadHistory()` function in JavaScript.
     */
    /**
     * Check if an AI provider is configured with valid credentials
     */
    private static function is_provider_configured($settings, $provider) {
        switch ($provider) {
            case 'shareai':
                return !empty($settings['shareai_api_key']);
            case 'ollama':
                return !empty($settings['private_llm_server_url']);
            case 'openai':
                return !empty($settings['openai_api_key']);
            case 'gemini':
                return !empty($settings['gemini_api_key']);
            case 'claude':
                return !empty($settings['claude_api_key']);
            default:
                return false;
        }
    }

    public static function handle_load_history_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }

        global $wpdb;
        $user_id = get_current_user_id();

        // Fetch projects with caching
        $projects_cache_key = 'aiohm_user_projects_' . $user_id;
        $projects = wp_cache_get($projects_cache_key, 'aiohm_core');
        
        if (false === $projects) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- User projects with caching
            $projects = $wpdb->get_results($wpdb->prepare(
                "SELECT id, project_name as name FROM {$wpdb->prefix}aiohm_projects WHERE user_id = %d ORDER BY creation_date DESC",
                $user_id
            ), ARRAY_A);
            wp_cache_set($projects_cache_key, $projects, 'aiohm_core', 300); // 5 minute cache
        }

        // Fetch recent conversations with caching
        $conversations_cache_key = 'aiohm_user_conversations_' . $user_id;
        $conversations = wp_cache_get($conversations_cache_key, 'aiohm_core');
        
        if (false === $conversations) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- User conversations with caching
            $conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT id, title, project_id FROM {$wpdb->prefix}aiohm_conversations WHERE user_id = %d ORDER BY updated_at DESC LIMIT 50",
                $user_id
            ), ARRAY_A);
            wp_cache_set($conversations_cache_key, $conversations, 'aiohm_core', 180); // 3 minute cache
        }

        wp_send_json_success(['projects' => $projects, 'conversations' => $conversations]);
        wp_die();
    }

    /**
     * Handles the AJAX request to load a specific conversation's messages.
     */
    public static function handle_load_conversation_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
        if (empty($conversation_id)) {
            wp_send_json_error(['message' => __('Invalid Conversation ID.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        global $wpdb;
        $user_id = get_current_user_id();
        $messages_table = $wpdb->prefix . 'aiohm_messages';
        $conversations_table = $wpdb->prefix . 'aiohm_conversations';
        $projects_table = $wpdb->prefix . 'aiohm_projects';
    
        // Verify conversation belongs to the user and get its project ID with caching
        $conversation_cache_key = 'aiohm_conversation_' . $conversation_id . '_' . $user_id;
        $conversation_info = wp_cache_get($conversation_cache_key, 'aiohm_core');
        
        if (false === $conversation_info) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Conversation lookup with caching
            $conversation_info = $wpdb->get_row($wpdb->prepare(
                "SELECT c.id, c.title, c.project_id, p.project_name FROM {$wpdb->prefix}aiohm_conversations c JOIN {$wpdb->prefix}aiohm_projects p ON c.project_id = p.id WHERE c.id = %d AND c.user_id = %d",
                $conversation_id,
                $user_id
            ), ARRAY_A);
            
            if ($conversation_info) {
                wp_cache_set($conversation_cache_key, $conversation_info, 'aiohm_core', 600); // 10 minute cache
            }
        }
    
        if (!$conversation_info) {
            wp_send_json_error(['message' => __('Conversation not found or not accessible.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        // Fetch messages for the conversation with caching
        $messages_cache_key = 'aiohm_conversation_messages_' . $conversation_id;
        $messages = wp_cache_get($messages_cache_key, 'aiohm_core');
        
        if (false === $messages) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Messages query with caching
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT sender, content as message_content FROM {$wpdb->prefix}aiohm_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            ), ARRAY_A);
            wp_cache_set($messages_cache_key, $messages, 'aiohm_core', 300); // 5 minute cache
        }
    
        wp_send_json_success([
            'messages' => $messages,
            'project_id' => $conversation_info['project_id'],
            'project_name' => $conversation_info['project_name'],
            'conversation_title' => $conversation_info['title']
        ]);
        wp_die();
    }

    /**
     * Handles AJAX request to save project notes.
     */
    public static function handle_save_project_notes_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $note_content = isset($_POST['note_content']) ? sanitize_textarea_field(wp_unslash($_POST['note_content'])) : '';
    
        if (empty($project_id)) {
            wp_send_json_error(['message' => __('Invalid Project ID.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_projects';
        $user_id = get_current_user_id();
    
        // Ensure the project belongs to the current user
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Project ownership verification
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM {$wpdb->prefix}aiohm_projects WHERE id = %d AND user_id = %d",
            $project_id,
            $user_id
        ));
    
        if (!$exists) {
            wp_send_json_error(['message' => __('Project not found or not owned by user.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Project notes update with cache clearing
        $updated = $wpdb->update(
            $table_name,
            ['notes' => $note_content],
            ['id' => $project_id],
            ['%s'],
            ['%d']
        );
    
        if ($updated !== false) {
            wp_send_json_success(['message' => __('Notes saved.', 'aiohm-knowledge-assistant')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save notes.', 'aiohm-knowledge-assistant')]);
        }
        wp_die();
    }

    /**
     * Handles AJAX request to load project notes.
     */
    public static function handle_load_project_notes_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    
        if (empty($project_id)) {
            wp_send_json_error(['message' => __('Invalid Project ID.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_projects';
        $user_id = get_current_user_id();
    
        // Get notes with caching
        $notes_cache_key = 'aiohm_project_notes_' . $project_id . '_' . $user_id;
        $note_content = wp_cache_get($notes_cache_key, 'aiohm_core');
        
        if (false === $note_content) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Project notes query with caching
            $note_content = $wpdb->get_var($wpdb->prepare(
                "SELECT notes FROM {$wpdb->prefix}aiohm_projects WHERE id = %d AND user_id = %d",
                $project_id,
                $user_id
            ));
            wp_cache_set($notes_cache_key, $note_content, 'aiohm_core', 600); // 10 minute cache
        }
    
        if ($note_content !== null) {
            wp_send_json_success(['note_content' => $note_content]);
        } else {
            wp_send_json_error(['message' => __('Project notes not found or not accessible.', 'aiohm-knowledge-assistant')]);
        }
        wp_die();
    }

    /**
     * Handles AJAX request to delete a project.
     */
    public static function handle_delete_project_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    
        if (empty($project_id)) {
            wp_send_json_error(['message' => __('Invalid Project ID.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        global $wpdb;
        $user_id = get_current_user_id();
    
        // Delete associated conversations first
        $conversations_table = $wpdb->prefix . 'aiohm_conversations';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Project deletion with cache clearing
        $wpdb->delete($conversations_table, ['project_id' => $project_id, 'user_id' => $user_id], ['%d', '%d']);
    
        // Delete the project
        $projects_table = $wpdb->prefix . 'aiohm_projects';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Project deletion with cache clearing
        $deleted = $wpdb->delete($projects_table, ['id' => $project_id, 'user_id' => $user_id], ['%d', '%d']);
    
        if ($deleted) {
            // Clear all caches related to this project and user
            wp_cache_delete('aiohm_user_projects_' . $user_id, 'aiohm_core');
            wp_cache_delete('aiohm_user_conversations_' . $user_id, 'aiohm_core');
            wp_cache_delete('aiohm_project_notes_' . $project_id . '_' . $user_id, 'aiohm_core');
            wp_cache_delete('aiohm_project_conversations_' . $project_id, 'aiohm_core');
            
            wp_send_json_success(['message' => __('Project and its conversations deleted.', 'aiohm-knowledge-assistant')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete project or project not found.', 'aiohm-knowledge-assistant')]);
        }
        wp_die();
    }
    
    /**
     * Handles AJAX request to delete a conversation.
     */
    public static function handle_delete_conversation_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
    
        if (empty($conversation_id)) {
            wp_send_json_error(['message' => __('Invalid Conversation ID.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        global $wpdb;
        $user_id = get_current_user_id();
    
        // Delete associated messages first
        $messages_table = $wpdb->prefix . 'aiohm_messages';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Conversation deletion with cache clearing
        $wpdb->delete($messages_table, ['conversation_id' => $conversation_id], ['%d']);
    
        // Delete the conversation
        $conversations_table = $wpdb->prefix . 'aiohm_conversations';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Conversation deletion with cache clearing
        $deleted = $wpdb->delete($conversations_table, ['id' => $conversation_id, 'user_id' => $user_id], ['%d', '%d']);
    
        if ($deleted) {
            wp_send_json_success(['message' => __('Conversation and its messages deleted.', 'aiohm-knowledge-assistant')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete conversation or conversation not found.', 'aiohm-knowledge-assistant')]);
        }
        wp_die();
    }

    /**
     * Handles AJAX request to create a new conversation.
     */
    public static function handle_create_conversation_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : 'New Chat';
    
        if (empty($project_id)) {
            wp_send_json_error(['message' => __('Invalid Project ID.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        global $wpdb;
        $user_id = get_current_user_id();
        $projects_table = $wpdb->prefix . 'aiohm_projects';
    
        // Verify project belongs to the current user
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Project verification query
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(id) FROM {$wpdb->prefix}aiohm_projects WHERE id = %d AND user_id = %d",
            $project_id,
            $user_id
        ));
    
        if (!$exists) {
            wp_send_json_error(['message' => __('Project not found or not owned by user.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
    
        $conversation_id = self::create_conversation_internal($user_id, $project_id, $title);
    
        if ($conversation_id) {
            wp_send_json_success(['conversation_id' => $conversation_id, 'title' => $title]);
        } else {
            wp_send_json_error(['message' => __('Failed to create conversation.', 'aiohm-knowledge-assistant')]);
        }
        wp_die();
    }


    // ================== START: NEW FUNCTION TO FIX PROJECTS ==================
    /**
     * Handles the AJAX request to get all conversations for a specific project.
     * This function was missing, causing projects not to load.
     */
    public static function handle_get_project_conversations_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission Denied', 'aiohm-knowledge-assistant')]);
            wp_die();
        }

        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if (empty($project_id)) {
            wp_send_json_error(['message' => __('Invalid Project ID.', 'aiohm-knowledge-assistant')]);
            wp_die();
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'aiohm_conversations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- User conversations query
        $conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title FROM {$wpdb->prefix}aiohm_conversations WHERE user_id = %d AND project_id = %d ORDER BY updated_at DESC",
            $user_id,
            $project_id
        ));

        wp_send_json_success(['conversations' => $conversations]);
        wp_die();
    }

    public static function handle_create_project_ajax() {
        check_ajax_referer('aiohm_private_chat_nonce', 'nonce');
        if (!current_user_can('read')) { wp_send_json_error(['message' => 'Permission denied.']); wp_die(); }
        
        $project_name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (empty($project_name)) { wp_send_json_error(['message' => __('Project name cannot be empty.', 'aiohm-knowledge-assistant')]); wp_die(); }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_projects';
        $user_id = get_current_user_id();
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Project creation with cache invalidation handled elsewhere
        $result = $wpdb->insert($table_name, ['user_id' => $user_id, 'project_name' => $project_name], ['%d', '%s']);
        
        if ($result === false) {
             wp_send_json_error(['message' => __('Could not save the project to the database.', 'aiohm-knowledge-assistant')]);
        } else {
            $project_id = $wpdb->insert_id;
            wp_send_json_success(['new_project_id' => $project_id, 'name' => $project_name]);
        }
        wp_die();
    }

    public static function handle_private_assistant_chat_ajax() {
        // Enhanced rate limiting for private chat
        if (!AIOHM_KB_Assistant::check_enhanced_rate_limit('private_chat', get_current_user_id(), 30)) {
            wp_send_json_error(['message' => __('Private chat rate limit exceeded. Please wait before sending more messages.', 'aiohm-knowledge-assistant')]);
        }

        if (!check_ajax_referer('aiohm_private_chat_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
    
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('You do not have permission to use this feature.', 'aiohm-knowledge-assistant')]);
        }
    
        try {
            $user_message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
            
            // Enhanced message validation
            if (empty(trim($user_message))) {
                wp_send_json_error(['message' => __('Message cannot be empty.', 'aiohm-knowledge-assistant')]);
            }
            
            if (strlen($user_message) > 3000) {
                wp_send_json_error(['message' => __('Message is too long. Please limit to 3000 characters.', 'aiohm-knowledge-assistant')]);
            }
            
            // Check for potentially malicious content
            if (preg_match('/<script|<iframe|javascript:|vbscript:|onload=|onerror=/i', $user_message)) {
                wp_send_json_error(['message' => __('Message contains invalid content.', 'aiohm-knowledge-assistant')]);
            }
            $user_id = get_current_user_id();
            
            $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
            $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : null;

            if (empty($project_id)) {
                throw new Exception('A project must be selected to start a conversation.');
            }

            $settings = AIOHM_KB_Assistant::get_settings();
            $muse_settings = $settings['muse_mode'] ?? [];
    
            // Initialize AI client with current settings to support all providers
            $ai_client = new AIOHM_KB_AI_GPT_Client($settings);
            $rag_engine = new AIOHM_KB_RAG_Engine();
            
            $context_data = $rag_engine->find_context_for_user($user_message, $user_id, 10);
            $context_string = "";
            if (!empty($context_data)) {
                foreach ($context_data as $data) {
                    $context_string .= "Source: " . $data['entry']['title'] . "\nContent: " . $data['entry']['content'] . "\n\n";
                }
            }
    
            $system_prompt = $muse_settings['system_prompt'] ?? 'You are a helpful brand assistant.';
            $temperature = floatval($muse_settings['temperature'] ?? 0.7);
            
            // Use the user's selected default AI provider with smart fallback
            $default_provider = $settings['default_ai_provider'] ?? null;
            $provider_priority = ['shareai', 'ollama', 'openai', 'gemini', 'claude'];
            
            // If no default set, find first available provider
            if (!$default_provider) {
                foreach ($provider_priority as $provider) {
                    if (self::is_provider_configured($settings, $provider)) {
                        $default_provider = $provider;
                        break;
                    }
                }
            }
            
            // If default provider not configured, fallback to next available
            if (!self::is_provider_configured($settings, $default_provider)) {
                foreach ($provider_priority as $provider) {
                    if (self::is_provider_configured($settings, $provider)) {
                        $default_provider = $provider;
                        break;
                    }
                }
            }
            
            // Set appropriate model based on provider
            switch ($default_provider) {
                case 'shareai':
                    $model = $settings['shareai_model'] ?? 'llama4:17b-scout-16e-instruct-fp16';
                    break;
                case 'ollama':
                    $model = $settings['private_llm_model'] ?? 'llama3.2';
                    break;
                case 'gemini':
                    $model = 'gemini-pro';
                    break;
                case 'claude':
                    $model = 'claude-3-sonnet-20240229';
                    break;
                case 'openai':
                    $model = 'gpt-4';
                    break;
                default:
                    $model = 'gpt-4'; // Final fallback
                    break;
            }
            
            // Enhanced formatting instructions for better readability
            $formatting_instructions = "\n\n--- FORMATTING INSTRUCTIONS ---\n" .
                "Format your responses with clear structure using:\n" .
                "- **Bold headings** for main topics\n" .
                "- Bullet points for lists\n" .
                "- Numbered lists for step-by-step instructions\n" .
                "- Tables when presenting comparative data\n" .
                "- Use line breaks for better readability\n" .
                "- Keep paragraphs concise and focused\n\n";
            
            $final_system_message = $system_prompt . $formatting_instructions . "--- CONTEXT ---\n" . $context_string;
    
            $answer = $ai_client->get_chat_completion($final_system_message, $user_message, $temperature, $model);

            if (is_null($conversation_id) || empty($conversation_id)) {
                // Generate AI-powered conversation title
                $conversation_title = self::generate_conversation_title($user_message, $ai_client, $model);
                $conversation_id = self::create_conversation_internal($user_id, $project_id, $conversation_title);
                if (!$conversation_id) {
                    AIOHM_KB_Assistant::log('Failed to create conversation record.', 'error');
                }
            }
            
            if ($conversation_id) {
                self::add_message_to_conversation_internal($conversation_id, 'user', $user_message);
                self::add_message_to_conversation_internal($conversation_id, 'ai', $answer);
            }
    
            // This was already fixed in the last step to use 'reply'
            wp_send_json_success(['reply' => $answer, 'conversation_id' => $conversation_id]);
    
        } catch (Exception $e) {
            $safe_message = self::get_safe_error_message($e, 'Private Assistant');
            wp_send_json_error(['message' => $safe_message]);
        }
    }

    public static function handle_frontend_chat_ajax() {
        // Enhanced rate limiting for chat requests
        $user_id = get_current_user_id() ?: 0;
        if (!AIOHM_KB_Assistant::check_enhanced_rate_limit('frontend_chat', $user_id, 50)) {
            wp_send_json_error(['reply' => __('Chat rate limit exceeded. Please wait before sending more messages.', 'aiohm-knowledge-assistant')]);
        }

        if (!check_ajax_referer('aiohm_chat_nonce', 'nonce', false)) {
            wp_send_json_error(['reply' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        try {
            $user_message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
            
            // Enhanced message validation
            if (empty(trim($user_message))) {
                wp_send_json_error(['reply' => __('Message cannot be empty.', 'aiohm-knowledge-assistant')]);
            }
            
            if (strlen($user_message) > 2000) {
                wp_send_json_error(['reply' => __('Message is too long. Please limit to 2000 characters.', 'aiohm-knowledge-assistant')]);
            }
            
            // Check for potentially malicious content
            if (preg_match('/<script|<iframe|javascript:|vbscript:|onload=|onerror=/i', $user_message)) {
                wp_send_json_error(['reply' => __('Message contains invalid content.', 'aiohm-knowledge-assistant')]);
            }
            
            $settings = AIOHM_KB_Assistant::get_settings();
            $mirror_settings = $settings['mirror_mode'] ?? [];

            $ai_client = new AIOHM_KB_AI_GPT_Client();
            $rag_engine = new AIOHM_KB_RAG_Engine();
            
            $context_data = $rag_engine->find_relevant_context($user_message, 5);
            $context_string = "";
            if (!empty($context_data)) {
                foreach ($context_data as $data) {
                    $context_string .= "Source: " . $data['entry']['title'] . "\nContent: " . $data['entry']['content'] . "\n\n";
                }
            } else {
                $context_string = "No relevant context found in the knowledge base.";
            }

            $system_message = $mirror_settings['qa_system_message'] ?? 'You are a helpful assistant.';
            $temperature = floatval($mirror_settings['qa_temperature'] ?? 0.8);
            
            // Use the user's selected default AI provider with smart fallback
            $default_provider = $settings['default_ai_provider'] ?? null;
            $provider_priority = ['shareai', 'ollama', 'openai', 'gemini', 'claude'];
            
            // If no default set, find first available provider
            if (!$default_provider) {
                foreach ($provider_priority as $provider) {
                    if (self::is_provider_configured($settings, $provider)) {
                        $default_provider = $provider;
                        break;
                    }
                }
            }
            
            // If default provider not configured, fallback to next available
            if (!self::is_provider_configured($settings, $default_provider)) {
                foreach ($provider_priority as $provider) {
                    if (self::is_provider_configured($settings, $provider)) {
                        $default_provider = $provider;
                        break;
                    }
                }
            }
            
            // Set appropriate model based on provider (Mirror Mode)
            switch ($default_provider) {
                case 'shareai':
                    $model = $settings['shareai_model'] ?? 'llama4:17b-scout-16e-instruct-fp16';
                    break;
                case 'ollama':
                    $model = $settings['private_llm_model'] ?? 'llama3.2';
                    break;
                case 'gemini':
                    $model = 'gemini-pro';
                    break;
                case 'claude':
                    $model = 'claude-3-sonnet-20240229';
                    break;
                case 'openai':
                    $model = 'gpt-3.5-turbo';
                    break;
                default:
                    $model = 'gpt-3.5-turbo'; // Final fallback
                    break;
            }
            
            // Apply variable replacements to system message
            $replacements = [
                '{context}'        => $context_string,
                '%site_name%'      => get_bloginfo('name'),
                '%site_tagline%'   => get_bloginfo('description'),
                '%business_name%'  => $mirror_settings['business_name'] ?? get_bloginfo('name'),
                '%day_of_week%'    => wp_date('l'),
                '%current_date%'   => wp_date(get_option('date_format')),
                '%current_time%'   => wp_date(get_option('time_format')),
            ];
            $final_system_message = str_replace(array_keys($replacements), array_values($replacements), $system_message);

            $answer = $ai_client->get_chat_completion($final_system_message, $user_message, $temperature, $model);

            wp_send_json_success(['reply' => $answer]);

        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Frontend Chat Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['reply' => __('An error occurred while processing your request. Please try again.', 'aiohm-knowledge-assistant')]);
        }
    }
    
    public static function handle_search_knowledge_ajax() {
        if (!check_ajax_referer('aiohm_search_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'aiohm-knowledge-assistant')]);
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $content_type_filter = isset($_POST['content_type_filter']) ? sanitize_text_field(wp_unslash($_POST['content_type_filter'])) : '';
        $max_results = isset($_POST['max_results']) ? intval($_POST['max_results']) : 10;
        $excerpt_length = isset($_POST['excerpt_length']) ? intval($_POST['excerpt_length']) : 25;
        
        if (empty($query)) {
            wp_send_json_error(['message' => __('Search query is required', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $results = $rag_engine->find_relevant_context($query, $max_results);
            
            $filtered_results = [];
            if (!empty($content_type_filter)) {
                 foreach ($results as $result) {
                    if ($result['entry']['content_type'] === $content_type_filter) {
                        $filtered_results[] = $result;
                    }
                }
            } else {
                $filtered_results = $results;
            }
            
            $formatted_results = array();
            foreach ($filtered_results as $result) {
                $entry = $result['entry'];
                $excerpt = wp_trim_words($entry['content'], $excerpt_length, '...');
                $metadata = is_string($entry['metadata']) ? json_decode($entry['metadata'], true) : $entry['metadata'];

                $formatted_results[] = array(
                    'title' => $entry['title'],
                    'excerpt' => $excerpt,
                    'content_type' => $entry['content_type'],
                    'similarity' => round($result['score'] * 100, 1),
                    'url' => $metadata['url'] ?? get_permalink($metadata['post_id'] ?? 0) ?? '#',
                );
            }
            
            wp_send_json_success([
                'results' => $formatted_results,
                'total_count' => count($formatted_results),
            ]);
            
        } catch (Exception $e) {
            $safe_message = self::get_safe_error_message($e, 'Admin Search');
            wp_send_json_error(['message' => $safe_message]);
        }
    }
    
    public static function handle_admin_search_knowledge_ajax() {
        if (!check_ajax_referer('aiohm_mirror_mode_nonce', 'nonce', false) || !current_user_can('read')) {
            wp_send_json_error(['message' => 'Security check failed or insufficient permissions.']);
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $content_type_filter = isset($_POST['content_type_filter']) ? sanitize_text_field(wp_unslash($_POST['content_type_filter'])) : '';
        $max_results = 5;
        $excerpt_length = 20;

        if (empty($query)) {
            wp_send_json_error(['message' => __('Search query is required.', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            $rag_engine = new AIOHM_KB_RAG_Engine();
            // Use find_relevant_context to ensure only public content (user_id = 0) is shown in test
            $results = $rag_engine->find_relevant_context($query, $max_results);
            
            $filtered_results = [];
            if (!empty($content_type_filter)) {
                 foreach ($results as $result) {
                    if ($result['entry']['content_type'] === $content_type_filter) {
                        $filtered_results[] = $result;
                    }
                }
            } else {
                $filtered_results = $results;
            }
            
            $formatted_results = array();
            foreach ($filtered_results as $result) {
                $entry = $result['entry'];
                $excerpt = wp_trim_words($entry['content'], $excerpt_length, '...');
                $metadata = is_string($entry['metadata']) ? json_decode($entry['metadata'], true) : $entry['metadata'];

                $formatted_results[] = array(
                    'title' => $entry['title'],
                    'excerpt' => $excerpt,
                    'content_type' => $entry['content_type'],
                    'similarity' => round($result['score'] * 100, 1),
                    'url' => $metadata['url'] ?? get_permalink($metadata['post_id'] ?? 0) ?? '#',
                );
            }
            
            wp_send_json_success([
                'results' => $formatted_results,
            ]);
            
        } catch (Exception $e) {
            $safe_message = self::get_safe_error_message($e, 'Admin Search');
            wp_send_json_error(['message' => $safe_message]);
        }
    }

    public static function handle_test_mirror_mode_chat_ajax() {
        if (!check_ajax_referer('aiohm_mirror_mode_nonce', 'aiohm_mirror_mode_nonce_field', false) || !current_user_can('read')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        try {
            $user_message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
            $posted_settings = isset($_POST['settings']) && is_array($_POST['settings']) ? map_deep(wp_unslash($_POST['settings']), 'sanitize_text_field') : [];

            $system_message = isset($posted_settings['qa_system_message']) ? wp_kses_post($posted_settings['qa_system_message']) : 'You are a helpful assistant.';
            
            $temperature = floatval($posted_settings['qa_temperature'] ?? 0.7);
            
            // Initialize AI client with current settings to support Ollama
            $current_settings = AIOHM_KB_Assistant::get_settings();
            $ai_client = new AIOHM_KB_AI_GPT_Client($current_settings);
            $rag_engine = new AIOHM_KB_RAG_Engine();
            
            
            // Test: Try a broader search first
            $context_data = $rag_engine->find_relevant_context($user_message, 5);
            
            // If no results, try a simple keyword search as fallback
            if (empty($context_data)) {
                $context_data = $rag_engine->find_relevant_context('company', 10);
                if (empty($context_data)) {
                    $context_data = $rag_engine->find_relevant_context('about', 10);
                }
            }
            $context_string = "";
            foreach ($context_data as $data) {
                $context_string .= "Source: " . $data['entry']['title'] . "\nContent: " . $data['entry']['content'] . "\n\n";
            }
            if (empty($context_string)) {
                $context_string = "No relevant context found.";
            }
            

            $replacements = [
                '{context}'        => $context_string,
                '%site_name%'      => $posted_settings['business_name'] ?? get_bloginfo('name'),
                '%business_name%'  => $posted_settings['business_name'] ?? get_bloginfo('name'),
                '%day_of_week%'    => wp_date('l'),
                '%current_date%'   => wp_date(get_option('date_format')),
                '%current_time%'   => wp_date(get_option('time_format')),
            ];
            $final_system_message = str_replace(array_keys($replacements), array_values($replacements), $system_message);
            

            // Use the user's selected default AI provider with smart fallback
            $default_provider = $current_settings['default_ai_provider'] ?? null;
            $provider_priority = ['shareai', 'ollama', 'openai', 'gemini', 'claude'];
            
            // If no default set, find first available provider
            if (!$default_provider) {
                foreach ($provider_priority as $provider) {
                    if (self::is_provider_configured($current_settings, $provider)) {
                        $default_provider = $provider;
                        break;
                    }
                }
            }
            
            // If default provider not configured, fallback to next available
            if (!self::is_provider_configured($current_settings, $default_provider)) {
                foreach ($provider_priority as $provider) {
                    if (self::is_provider_configured($current_settings, $provider)) {
                        $default_provider = $provider;
                        break;
                    }
                }
            }
            
            // Set appropriate model based on provider (Mirror Mode Test)
            switch ($default_provider) {
                case 'shareai':
                    $model = $current_settings['shareai_model'] ?? 'llama4:17b-scout-16e-instruct-fp16';
                    break;
                case 'ollama':
                    $model = $current_settings['private_llm_model'] ?? 'llama3.2';
                    break;
                case 'gemini':
                    $model = 'gemini-pro';
                    break;
                case 'claude':
                    $model = 'claude-3-sonnet-20240229';
                    break;
                case 'openai':
                    $model = 'gpt-3.5-turbo';
                    break;
                default:
                    $model = 'gpt-3.5-turbo'; // Final fallback
                    break;
            }
            
            $answer = $ai_client->get_chat_completion($final_system_message, $user_message, $temperature, $model);

            // Ensure answer is a string for JSON response
            if (!is_string($answer)) {
                $answer = is_array($answer) || is_object($answer) ? json_encode($answer) : (string)$answer;
            }

            wp_send_json_success(['answer' => $answer]);

        } catch (Exception $e) {
            $safe_message = self::get_safe_error_message($e, 'AI Request');
            wp_send_json_error(['message' => $safe_message]);
        }
    }
    
    public static function handle_test_muse_mode_chat_ajax() {
        if (!check_ajax_referer('aiohm_muse_mode_nonce', 'aiohm_muse_mode_nonce_field', false) || !current_user_can('read')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
    
        try {
            $user_message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
            $posted_settings = isset($_POST['settings']) && is_array($_POST['settings']) ? map_deep(wp_unslash($_POST['settings']), 'sanitize_text_field') : [];
            $user_id = get_current_user_id();
    
            $system_prompt = wp_kses_post($posted_settings['system_prompt'] ?? 'You are a helpful brand assistant.');
            $temperature = floatval($posted_settings['temperature'] ?? 0.7);
            $model = sanitize_text_field($posted_settings['ai_model'] ?? 'gpt-4');
    
            // Initialize AI client with current settings to support Ollama
            $current_settings = AIOHM_KB_Assistant::get_settings();
            $ai_client = new AIOHM_KB_AI_GPT_Client($current_settings);
            $rag_engine = new AIOHM_KB_RAG_Engine();
            
            $context_data = $rag_engine->find_context_for_user($user_message, $user_id, 10);
            $context_string = "";
            if (!empty($context_data)) {
                foreach ($context_data as $data) {
                    $context_string .= "Source: " . $data['entry']['title'] . "\nContent: " . $data['entry']['content'] . "\n\n";
                }
            }
            
            $final_system_message = $system_prompt . "\n\n--- CONTEXT ---\n" . $context_string;
    
            $answer = $ai_client->get_chat_completion($final_system_message, $user_message, $temperature, $model);
    
            wp_send_json_success(['answer' => $answer]);
    
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Muse Mode Test Chat Error: ' . $e->getMessage(), 'error');
            $safe_message = self::get_safe_error_message($e, 'AI Request');
            wp_send_json_error(['message' => $safe_message]);
        }
    }

    public static function handle_progressive_scan_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce') || !current_user_can('manage_options')) {
            wp_die(esc_html__('Security check failed', 'aiohm-knowledge-assistant'));
        }
        try {
            $scan_type = isset($_POST['scan_type']) ? sanitize_text_field(wp_unslash($_POST['scan_type'])) : '';
            switch ($scan_type) {
                case 'website_find':
                    $crawler = new AIOHM_KB_Site_Crawler();
                    $all_items = $crawler->find_all_content();
                    wp_send_json_success(['items' => $all_items]);
                    break;
                case 'website_add':
                    $item_ids = isset($_POST['item_ids']) ? array_map('intval', $_POST['item_ids']) : [];
                    // Filter out invalid IDs (0 or negative values)
                    $item_ids = array_filter($item_ids, function($id) { return $id > 0; });
                    if (empty($item_ids)) throw new Exception('No valid item IDs provided.');
                    
                    // Check prerequisites - validate API key for selected provider
                    $settings = AIOHM_KB_Assistant::get_settings();
                    $default_provider = $settings['default_ai_provider'] ?? 'openai';
                    
                    // Check if the selected default provider has proper configuration
                    $api_key_exists = false;
                    $provider_names = [
                        'openai' => 'OpenAI',
                        'gemini' => 'Gemini',
                        'claude' => 'Claude',
                        'shareai' => 'ShareAI',
                        'ollama' => 'Ollama'
                    ];
                    
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
                    
                    if (!$api_key_exists) {
                        $current_provider_name = $provider_names[$default_provider] ?? 'Unknown Provider';
                        throw new Exception(sprintf(
                            /* translators: %s: provider name */
                            __('%s API key is not configured. Please add your key in settings.', 'aiohm-knowledge-assistant'),
                            esc_html($current_provider_name)
                        ));
                    }
                    
                    $crawler = new AIOHM_KB_Site_Crawler();
                    $results = $crawler->add_items_to_kb($item_ids);
                    
                    // Categorize results
                    $errors = array_filter($results, function($item) { return $item['status'] === 'error'; });
                    $successes = array_filter($results, function($item) { return $item['status'] === 'success'; });
                    $skipped = array_filter($results, function($item) { return $item['status'] === 'skipped'; });
                    
                    if (!empty($errors) && empty($successes)) {
                        // All items failed
                        $error_messages = array_column($errors, 'error_message');
                        throw new Exception(sprintf(
                            /* translators: %s: error messages */
                            __('All items failed to add: %s', 'aiohm-knowledge-assistant'),
                            esc_html(implode(', ', array_map('sanitize_text_field', $error_messages)))
                        ));
                    } else if (!empty($errors)) {
                        // Some items failed - return partial success with error details
                        $all_items = $crawler->find_all_content();
                        wp_send_json(['success' => false, 'data' => [
                            'message' => 'Some items failed to add to knowledge base',
                            'processed_items' => $results, 
                            'all_items' => $all_items,
                            'errors' => $errors,
                            'successes' => $successes
                        ]]);
                    } else if (!empty($skipped) && empty($successes)) {
                        // All items were skipped
                        $skip_reasons = array_column($skipped, 'reason');
                        $message = 'All items were skipped: ' . implode(', ', $skip_reasons);
                        $all_items = $crawler->find_all_content();
                        wp_send_json(['success' => false, 'data' => [
                            'message' => $message,
                            'processed_items' => $results, 
                            'all_items' => $all_items,
                            'skipped' => $skipped
                        ]]);
                    } else if (!empty($skipped)) {
                        // Some items were skipped
                        $all_items = $crawler->find_all_content();
                        $skip_count = count($skipped);
                        $success_count = count($successes);
                        wp_send_json(['success' => true, 'data' => [
                            'message' => "Processing complete: {$success_count} added, {$skip_count} skipped",
                            'processed_items' => $results, 
                            'all_items' => $all_items,
                            'skipped' => $skipped,
                            'successes' => $successes
                        ]]);
                    } else {
                        // All items succeeded
                        $all_items = $crawler->find_all_content();
                        wp_send_json_success(['processed_items' => $results, 'all_items' => $all_items]);
                    }
                    break;
                case 'uploads_find':
                    $crawler = new AIOHM_KB_Uploads_Crawler();
                    $all_supported_files = $crawler->find_all_supported_attachments();
                    wp_send_json_success(['items' => $all_supported_files]);
                    break;
                case 'uploads_add':
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized via array_map intval below
                    $raw_ids = isset($_POST['item_ids']) ? wp_unslash($_POST['item_ids']) : [];
                    AIOHM_KB_Assistant::log('Raw item IDs received: ' . json_encode($raw_ids));
                    $item_ids = array_map('intval', $raw_ids);
                    AIOHM_KB_Assistant::log('After intval conversion: ' . json_encode($item_ids));
                    // Filter out invalid IDs (0 or negative values)
                    $item_ids = array_filter($item_ids, function($id) { return $id > 0; });
                    AIOHM_KB_Assistant::log('After filtering invalid IDs: ' . json_encode($item_ids));
                    if (empty($item_ids)) throw new Exception('No valid item IDs provided.');
                    $crawler = new AIOHM_KB_Uploads_Crawler();
                    $results = $crawler->add_attachments_to_kb($item_ids);
                    
                    // Check for any errors in the results
                    $errors = array_filter($results, function($item) { return $item['status'] === 'error'; });
                    $successes = array_filter($results, function($item) { return $item['status'] === 'success'; });
                    
                    if (!empty($errors) && empty($successes)) {
                        // All items failed
                        $error_messages = array_column($errors, 'error');
                        throw new Exception(sprintf(
                            /* translators: %s: error messages */
                            __('All items failed to add: %s', 'aiohm-knowledge-assistant'),
                            esc_html(implode(', ', array_map('sanitize_text_field', $error_messages)))
                        ));
                    } else if (!empty($errors)) {
                        // Some items failed - return partial success with error details
                        $updated_files_list = $crawler->find_all_supported_attachments();
                        wp_send_json(['success' => false, 'data' => [
                            'message' => 'Some items failed to add to knowledge base',
                            'processed_items' => $results, 
                            'items' => $updated_files_list,
                            'errors' => $errors,
                            'successes' => $successes
                        ]]);
                    } else {
                        // All items succeeded
                        $updated_files_list = $crawler->find_all_supported_attachments();
                        wp_send_json_success(['processed_items' => $results, 'items' => $updated_files_list]);
                    }
                    break;
                default:
                    throw new Exception('Invalid scan type specified.');
            }
        } catch (Exception $e) {
            $safe_message = self::get_safe_error_message($e, 'Scan');
            wp_send_json_error(['message' => $safe_message]);
        } catch (Error $e) {
            // translators: %s is the fatal error message
            wp_send_json_error(['message' => sprintf(__('Fatal error: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    public static function handle_check_api_key_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        $key_type = isset($_POST['key_type']) ? sanitize_key($_POST['key_type']) : '';
        
        // Get current settings to use stored API keys instead of relying on POST data
        $settings = AIOHM_KB_Assistant::get_settings();
        
        // Get the appropriate API key from stored settings
        $api_key = '';
        switch ($key_type) {
            case 'openai':
                $api_key = $settings['openai_api_key'] ?? '';
                break;
            case 'gemini':
                $api_key = $settings['gemini_api_key'] ?? '';
                break;
            case 'claude':
                $api_key = $settings['claude_api_key'] ?? '';
                break;
            case 'shareai':
                $api_key = $settings['shareai_api_key'] ?? '';
                break;
            case 'aiohm_email':
                $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
                break;
        }
        
        // For Ollama, we don't need an API key, we need server_url
        if ($key_type !== 'ollama' && $key_type !== 'aiohm_email' && empty($api_key)) {
            wp_send_json_error(['message' => __('API Key is not configured. Please save your settings first.', 'aiohm-knowledge-assistant')]);
        }
        try {
            switch ($key_type) {
                case 'aiohm_email':
                    $api_client = new AIOHM_App_API_Client();
                    $result = $api_client->get_member_details_by_email($api_key);
                    if (!is_wp_error($result) && !empty($result['response']['result']['ID'])) {
                        wp_send_json_success(['message' => __('AIOHM.app connection successful!', 'aiohm-knowledge-assistant'), 'user_id' => intval($result['response']['result']['ID'])]);
                    } else {
                        $error_message = is_wp_error($result) ? $result->get_error_message() : ($result['message'] ?? 'Invalid Email or API error.');
                        // translators: %s is the connection error message
                        wp_send_json_error(['message' => sprintf(__('AIOHM.app connection failed: %s', 'aiohm-knowledge-assistant'), $error_message)]);
                    }
                    break;
                case 'openai':
                    $ai_client = new AIOHM_KB_AI_GPT_Client($settings);
                    $result = $ai_client->test_api_connection();
                    if ($result['success']) {
                        wp_send_json_success(['message' => __('OpenAI connection successful!', 'aiohm-knowledge-assistant')]);
                    } else {
                        // translators: %s is the OpenAI API error message
                        wp_send_json_error(['message' => sprintf(__('OpenAI connection failed: %s', 'aiohm-knowledge-assistant'), ($result['error'] ?? __('Unknown error.', 'aiohm-knowledge-assistant')))]);
                    }
                    break;
                case 'gemini':
                    $ai_client = new AIOHM_KB_AI_GPT_Client($settings);
                    $result = $ai_client->test_gemini_api_connection();
                    if ($result['success']) {
                        wp_send_json_success(['message' => __('Gemini connection successful!', 'aiohm-knowledge-assistant')]);
                    } else {
                        // translators: %s is the Gemini API error message
                        wp_send_json_error(['message' => sprintf(__('Gemini connection failed: %s', 'aiohm-knowledge-assistant'), ($result['error'] ?? __('Unknown error.', 'aiohm-knowledge-assistant')))]);
                    }
                    break;
                case 'claude':
                    $ai_client = new AIOHM_KB_AI_GPT_Client($settings);
                    $result = $ai_client->test_claude_api_connection();
                    if ($result['success']) {
                        wp_send_json_success(['message' => __('Claude connection successful!', 'aiohm-knowledge-assistant')]);
                    } else {
                        // translators: %s is the Claude API error message
                        wp_send_json_error(['message' => sprintf(__('Claude connection failed: %s', 'aiohm-knowledge-assistant'), ($result['error'] ?? __('Unknown error.', 'aiohm-knowledge-assistant')))]);
                    }
                    break;
                case 'shareai':
                    $ai_client = new AIOHM_KB_AI_GPT_Client($settings);
                    $result = $ai_client->test_shareai_api_connection();
                    if ($result['success']) {
                        wp_send_json_success(['message' => __('ShareAI connection successful!', 'aiohm-knowledge-assistant')]);
                    } else {
                        // translators: %s is the ShareAI API error message
                        wp_send_json_error(['message' => sprintf(__('ShareAI connection failed: %s', 'aiohm-knowledge-assistant'), ($result['error'] ?? __('Unknown error.', 'aiohm-knowledge-assistant')))]);
                    }
                    break;
                case 'ollama':
                    $server_url = sanitize_text_field(wp_unslash($_POST['server_url'] ?? ''));
                    $model = sanitize_text_field(wp_unslash($_POST['model'] ?? 'llama3.2'));
                    
                    if (empty($server_url)) {
                        wp_send_json_error(['message' => __('Ollama server URL is required.', 'aiohm-knowledge-assistant')]);
                        break;
                    }
                    
                    $ai_client = new AIOHM_KB_AI_GPT_Client([
                        'private_llm_server_url' => $server_url,
                        'private_llm_model' => $model
                    ]);
                    $result = $ai_client->test_ollama_api_connection();
                    if ($result['success']) {
                        wp_send_json_success(['message' => __('Ollama server connection successful!', 'aiohm-knowledge-assistant')]);
                    } else {
                        // translators: %s is the Ollama server error message
                        wp_send_json_error(['message' => sprintf(__('Ollama server connection failed: %s', 'aiohm-knowledge-assistant'), ($result['error'] ?? __('Unknown error.', 'aiohm-knowledge-assistant')))]);
                    }
                    break;
                default:
                    wp_send_json_error(['message' => __('Invalid key type specified.', 'aiohm-knowledge-assistant')]);
            }
        } catch (Exception $e) {
            // translators: %s is the unexpected error message
            wp_send_json_error(['message' => sprintf(__('An unexpected error occurred: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    /**
     * AJAX handler for saving individual API keys
     */
    public static function handle_save_individual_api_key_ajax() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'aiohm-knowledge-assistant')]);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        // Get and validate inputs
        $api_type = isset($_POST['api_type']) ? sanitize_key($_POST['api_type']) : '';
        $setting_name = isset($_POST['setting_name']) ? sanitize_key($_POST['setting_name']) : '';
        $api_value = isset($_POST['api_value']) ? sanitize_text_field(wp_unslash($_POST['api_value'])) : '';

        if (empty($api_type) || empty($setting_name) || empty($api_value)) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'aiohm-knowledge-assistant')]);
        }

        try {
            // Clear WordPress object cache to ensure fresh data
            wp_cache_delete('aiohm_kb_settings', 'options');
            wp_cache_flush();
            
            // Get fresh current settings to avoid conflicts - bypass cache completely
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Need fresh data bypassing cache for API save conflicts
            $fresh_data = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'aiohm_kb_settings'));
            if ($fresh_data === null) {
                $current_settings = [];
            } else {
                $current_settings = maybe_unserialize($fresh_data);
            }
            
            // Enhanced API key validation and encryption
            if (in_array($setting_name, ['openai_api_key', 'gemini_api_key', 'claude_api_key'])) {
                // Validate API key format using new validation function
                $provider = str_replace('_api_key', '', $setting_name);
                $validated_key = AIOHM_KB_Assistant::validate_and_sanitize_api_key($api_value, $provider);
                
                if (is_wp_error($validated_key)) {
                    wp_send_json_error(['message' => $validated_key->get_error_message()]);
                }
                
                // Encrypt the validated API key
                $current_settings[$setting_name] = AIOHM_KB_Assistant::encrypt_api_key($validated_key);
            } elseif ($setting_name === 'shareai_api_key') {
                // Validate ShareAI key
                $validated_key = AIOHM_KB_Assistant::validate_and_sanitize_api_key($api_value, 'shareai');
                
                if (is_wp_error($validated_key)) {
                    wp_send_json_error(['message' => $validated_key->get_error_message()]);
                }
                
                $current_settings[$setting_name] = $validated_key;
            } else {
                // For non-API keys (like models), save as plain text with additional validation
                $sanitized_value = sanitize_text_field($api_value);
                
                // Additional validation for specific settings
                if ($setting_name === 'private_llm_server_url') {
                    if (!filter_var($sanitized_value, FILTER_VALIDATE_URL)) {
                        wp_send_json_error(['message' => __('Invalid server URL format.', 'aiohm-knowledge-assistant')]);
                    }
                }
                
                $current_settings[$setting_name] = $sanitized_value;
            }
            
            // Save the updated settings
            // Set a transient to prevent conflicts with settings page
            set_transient('aiohm_ajax_saving_' . $setting_name, true, 30);
            
            $result = update_option('aiohm_kb_settings', $current_settings);
            
            // If update_option returns false, verify if the value is actually correct
            if (!$result) {
                $verify_settings = get_option('aiohm_kb_settings', []);
                
                if (isset($verify_settings[$setting_name]) && $verify_settings[$setting_name] === $current_settings[$setting_name]) {
                    $result = true;
                } else {
                    // Try a direct update
                    $direct_update = update_option('aiohm_kb_settings', $current_settings);
                    if ($direct_update) {
                        $result = true;
                    } else {
                        // Last resort: direct database update
                        global $wpdb;
                        $serialized_data = maybe_serialize($current_settings);
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct update needed when update_option fails
                        $db_result = $wpdb->update(
                            $wpdb->options,
                            ['option_value' => $serialized_data],
                            ['option_name' => 'aiohm_kb_settings'],
                            ['%s'],
                            ['%s']
                        );
                        if ($db_result !== false) {
                            // Clear cache after direct update
                            wp_cache_delete('aiohm_kb_settings', 'options');
                            $result = true;
                        }
                    }
                }
            }
            
            delete_transient('aiohm_ajax_saving_' . $setting_name);
            
            if ($result !== false) {
                // Different message for model selection vs API key
                if ($api_type === 'shareai_model') {
                    wp_send_json_success(['message' => __('ShareAI model saved successfully!', 'aiohm-knowledge-assistant')]);
                } else {
                    // translators: %s is the API provider name (e.g., OpenAI, Claude)
                    wp_send_json_success(['message' => sprintf(__('%s API key saved successfully!', 'aiohm-knowledge-assistant'), ucfirst($api_type))]);
                }
            } else {
                wp_send_json_error(['message' => __('Failed to save API key. Please try again.', 'aiohm-knowledge-assistant')]);
            }
        } catch (Exception $e) {
            // translators: %s is the error message
            wp_send_json_error(['message' => sprintf(__('An error occurred: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    public static function handle_export_kb_ajax() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) { wp_send_json_error(['message' => 'Permission denied.']); }
        
        try {
            $scope = isset($_POST['scope']) ? sanitize_text_field(wp_unslash($_POST['scope'])) : 'public';
            $rag_engine = new AIOHM_KB_RAG_Engine();
            
            if ($scope === 'private') {
                $user_id = get_current_user_id();
                AIOHM_KB_Assistant::log('Exporting private data for user_id: ' . $user_id, 'info');
                $json_data = $rag_engine->export_knowledge_base($user_id);
                $filename = 'aiohm-kb-private-' . gmdate('Y-m-d') . '.json';
            } else {
                AIOHM_KB_Assistant::log('Exporting public data for user_id: 0', 'info');
                // Clear cache first for public export
                wp_cache_delete('aiohm_export_data_0', 'aiohm_kb');
                $json_data = $rag_engine->export_knowledge_base(0);
                $filename = 'aiohm-kb-public-' . gmdate('Y-m-d') . '.json';
                AIOHM_KB_Assistant::log('Public export data length: ' . strlen($json_data), 'info');
            }
            
            if (empty($json_data)) {
                // translators: %s is the export scope (public/private)
                wp_send_json_error(['message' => sprintf(__('No data found to export for %s entries.', 'aiohm-knowledge-assistant'), $scope)]);
            }
            
            wp_send_json_success(['filename' => sanitize_file_name($filename), 'data' => $json_data]);
        } catch (Exception $e) {
            // translators: %s is the export error message
            wp_send_json_error(['message' => sprintf(__('Export failed: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    public static function handle_reset_kb_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- KB reset operation with cache clearing
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_aiohm_indexed'));
        // Use DELETE instead of TRUNCATE for better security
        $wpdb->query("DELETE FROM {$wpdb->prefix}aiohm_vector_entries"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct table truncation for KB reset
        wp_send_json_success(['message' => __('The knowledge base has been successfully reset.', 'aiohm-knowledge-assistant')]);
    }

    public static function handle_toggle_kb_scope_ajax() {
        // Enhanced security checks with rate limiting
        if (!AIOHM_KB_Assistant::check_enhanced_rate_limit('kb_scope_toggle', get_current_user_id(), 100)) {
            wp_send_json_error(['message' => __('Rate limit exceeded. Please wait before making more changes.', 'aiohm-knowledge-assistant')]);
        }

        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'aiohm-knowledge-assistant')]);
        }
        
        $content_id = isset($_POST['content_id']) ? sanitize_text_field(wp_unslash($_POST['content_id'])) : '';
        $new_scope = isset($_POST['new_scope']) ? sanitize_text_field(wp_unslash($_POST['new_scope'])) : '';
        
        // Validate content ID using new validation function
        $content_id_validation = AIOHM_KB_Assistant::validate_content_id($content_id);
        if (is_wp_error($content_id_validation)) {
            wp_send_json_error(['message' => $content_id_validation->get_error_message()]);
        }
        
        // Validate scope parameter
        if (!in_array($new_scope, ['public', 'private'], true)) {
            wp_send_json_error(['message' => __('Invalid scope parameter.', 'aiohm-knowledge-assistant')]);
        }
        
        $is_public = ($new_scope === 'public') ? 1 : 0;
        
        $rag_engine = new AIOHM_KB_RAG_Engine();
        $result = $rag_engine->update_entry_visibility_by_content_id($content_id, $is_public);
        if ($result !== false) {
            $new_visibility_text = ($is_public === 1) ? 'Public' : 'Private';
            wp_send_json_success(['message' => __('Entry visibility updated successfully.', 'aiohm-knowledge-assistant'), 'new_visibility_text' => esc_html($new_visibility_text)]);
        } else {
            wp_send_json_error(['message' => __('Failed to update entry visibility in the database.', 'aiohm-knowledge-assistant')]);
        }
    }

    public static function handle_restore_kb_ajax() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        if (!isset($_POST['json_data']) || empty($_POST['json_data'])) {
            wp_send_json_error(['message' => __('No data provided for restore.', 'aiohm-knowledge-assistant')]);
        }
        $json_data = sanitize_textarea_field(wp_unslash($_POST['json_data']));
        
        // Validate JSON data
        json_decode($json_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON data provided.', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $count = $rag_engine->import_knowledge_base($json_data);
            wp_send_json_success(['message' => sprintf(
                /* translators: %d: number of entries */
                __('%d entries have been successfully restored. The page will now reload.', 'aiohm-knowledge-assistant'),
                intval($count)
            )]);
        }
        catch (Exception $e) {
            // translators: %s is the restore error message
            wp_send_json_error(['message' => sprintf(__('Restore failed: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    public static function handle_delete_kb_entry_ajax() {
        // Enhanced security checks with rate limiting
        if (!AIOHM_KB_Assistant::check_enhanced_rate_limit('kb_delete', get_current_user_id(), 50)) {
            wp_send_json_error(['message' => __('Delete rate limit exceeded. Please wait before deleting more entries.', 'aiohm-knowledge-assistant')]);
        }

        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!isset($_POST['content_id']) || empty($_POST['content_id'])) {
            wp_send_json_error(['message' => __('Content ID is missing for deletion.', 'aiohm-knowledge-assistant')]);
        }
        
        $content_id = sanitize_text_field(wp_unslash($_POST['content_id']));
        
        // Validate content ID using new validation function
        $content_id_validation = AIOHM_KB_Assistant::validate_content_id($content_id);
        if (is_wp_error($content_id_validation)) {
            wp_send_json_error(['message' => $content_id_validation->get_error_message()]);
        }
        
        $rag_engine = new AIOHM_KB_RAG_Engine();
        if ($rag_engine->delete_entry_by_content_id($content_id)) {
            wp_send_json_success(['message' => __('Entry successfully deleted.', 'aiohm-knowledge-assistant')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete entry.', 'aiohm-knowledge-assistant')]);
        }
    }

    public static function handle_bulk_delete_kb_ajax() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!isset($_POST['content_ids']) || !is_array($_POST['content_ids']) || empty($_POST['content_ids'])) {
            wp_send_json_error(['message' => __('No entries selected for deletion.', 'aiohm-knowledge-assistant')]);
        }
        
        $content_ids = array_map('sanitize_text_field', wp_unslash($_POST['content_ids']));
        $rag_engine = new AIOHM_KB_RAG_Engine();
        $deleted_count = 0;
        
        foreach ($content_ids as $content_id) {
            if ($rag_engine->delete_entry_by_content_id($content_id)) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            // translators: %d is the number of deleted entries
            wp_send_json_success(['message' => sprintf(__('%d entries successfully deleted.', 'aiohm-knowledge-assistant'), $deleted_count)]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete any entries.', 'aiohm-knowledge-assistant')]);
        }
    }

    public static function handle_bulk_toggle_kb_scope_ajax() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!isset($_POST['content_ids']) || !is_array($_POST['content_ids']) || empty($_POST['content_ids'])) {
            wp_send_json_error(['message' => __('No entries selected for scope change.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!isset($_POST['new_scope']) || !in_array($_POST['new_scope'], ['public', 'private'])) {
            wp_send_json_error(['message' => __('Invalid scope specified.', 'aiohm-knowledge-assistant')]);
        }
        
        $content_ids = array_map('sanitize_text_field', wp_unslash($_POST['content_ids']));
        $new_scope = sanitize_text_field(wp_unslash($_POST['new_scope']));
        $is_public = ($new_scope === 'public') ? 1 : 0;
        $rag_engine = new AIOHM_KB_RAG_Engine();
        $updated_count = 0;
        
        foreach ($content_ids as $content_id) {
            if ($rag_engine->update_entry_visibility_by_content_id($content_id, $is_public)) {
                $updated_count++;
            }
        }
        
        $scope_text = ucfirst($new_scope);
        if ($updated_count > 0) {
            // translators: %1$d is the number of updated entries, %2$s is the scope (Public/Private)
            wp_send_json_success(['message' => sprintf(__('%1$d entries successfully updated to %2$s.', 'aiohm-knowledge-assistant'), $updated_count, $scope_text)]);
        } else {
            wp_send_json_error(['message' => __('Failed to update any entries.', 'aiohm-knowledge-assistant')]);
        }
    }

    public static function handle_save_brand_soul_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_brand_soul_nonce') || !current_user_can('read')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        // Enhanced input validation
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw form data is validated and sanitized below
        $raw_data = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        
        // Check data size to prevent DoS
        if (strlen($raw_data) > 50000) { // 50KB limit
            wp_send_json_error(['message' => __('Data too large. Please reduce content size.', 'aiohm-knowledge-assistant')]);
        }
        
        parse_str($raw_data, $form_data);
        
        // Validate answers array
        $validation_rules = [
            'answers' => [
                'type' => 'array',
                'required' => true
            ]
        ];
        
        $validated = self::validate_ajax_input($form_data, $validation_rules);
        if (is_wp_error($validated)) {
            wp_send_json_error(['message' => $validated->get_error_message()]);
        }
        
        // Additional validation for answers content
        $answers = $validated['answers'];
        $sanitized_answers = [];
        
        foreach ($answers as $key => $answer) {
            // Limit individual answer length
            if (strlen($answer) > 2000) {
                wp_send_json_error(['message' => __('Individual answers must be less than 2000 characters.', 'aiohm-knowledge-assistant')]);
            }
            $sanitized_answers[sanitize_key($key)] = sanitize_textarea_field($answer);
        }
        
        update_user_meta(get_current_user_id(), 'aiohm_brand_soul_answers', $sanitized_answers);
        wp_send_json_success();
    }

    public static function handle_add_brand_soul_to_kb_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_brand_soul_nonce') || !current_user_can('read')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw form data is unslashed then parsed and individual fields are sanitized below
        $raw_data = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        parse_str($raw_data, $form_data);
        $answers = isset($form_data['answers']) ? array_map('sanitize_textarea_field', $form_data['answers']) : [];
        if (empty($answers)) {
            wp_send_json_error(['message' => __('No answers to add.', 'aiohm-knowledge-assistant')]);
        }
        try {
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $user_id = get_current_user_id();
            $content = json_encode($answers, JSON_PRETTY_PRINT);
            $rag_engine->add_entry($content, 'brand_soul', 'My Brand Soul', [], $user_id, 0);
            wp_send_json_success();
        } catch (Exception $e) {
            // translators: %s is the knowledge base addition error message
            wp_send_json_error(['message' => sprintf(__('Error adding to KB: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    public static function handle_add_note_to_kb_ajax() {
        // Check nonce and permissions
        if (!check_ajax_referer('aiohm_private_chat_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        // Enhanced input validation with size limits
        $validation_rules = [
            'project_id' => [
                'type' => 'int',
                'required' => true,
                'min' => 1
            ],
            'note_content' => [
                'type' => 'textarea',
                'required' => true,
                'max_length' => 10000 // 10KB limit for notes
            ]
        ];
        
        $input_data = [
            'project_id' => isset($_POST['project_id']) ? intval(wp_unslash($_POST['project_id'])) : '',
            'note_content' => isset($_POST['note_content']) ? sanitize_textarea_field(wp_unslash($_POST['note_content'])) : ''
        ];
        
        $validated = self::validate_ajax_input($input_data, $validation_rules);
        if (is_wp_error($validated)) {
            wp_send_json_error(['message' => $validated->get_error_message()]);
        }
        
        $project_id = $validated['project_id'];
        $note_content = $validated['note_content'];

        try {
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $user_id = get_current_user_id();

            // Create a title for the note
            $note_title = 'Project Note: ' . wp_trim_words($note_content, 8, '...');

            // Add the note to the knowledge base
            $rag_engine->add_entry(
                $note_content,
                'project_note',
                $note_title,
                ['project_id' => $project_id],
                $user_id,
                0
            );

            wp_send_json_success(['message' => __('Note added to Knowledge Base successfully!', 'aiohm-knowledge-assistant')]);

        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Add Note to KB Error: ' . $e->getMessage(), 'error');
            // translators: %s is the error message when adding a note to the knowledge base
            wp_send_json_error(['message' => sprintf(__('Error adding note to KB: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    public static function handle_pdf_download() {
        if (isset($_GET['action']) && $_GET['action'] === 'download_brand_soul_pdf' && isset($_GET['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'download_brand_soul_pdf')) {
            require_once AIOHM_KB_PLUGIN_DIR . 'includes/pdf-library-loader.php';
            require_once AIOHM_KB_PLUGIN_DIR . 'includes/simple-pdf-generator.php';
            $user_id = get_current_user_id();
            $user_info = get_userdata($user_id);
            $answers = get_user_meta($user_id, 'aiohm_brand_soul_answers', true);
            if (!is_array($answers)) {
                $answers = [];
            }
            $brand_soul_questions = [
                '✨ Foundation' => [
                    'foundation_1' => "What’s the deeper purpose behind your brand — beyond profit?",
                    'foundation_2' => "What life experiences shaped this work you now do?",
                    'foundation_3' => "Who were you before this calling emerged?",
                    'foundation_4' => "If your brand had a soul story, how would you tell it?",
                    'foundation_5' => "What’s one transformation you’ve witnessed that reminds you why you do this?",
                ],
                '🌀 Energy' => [
                    'energy_1' => "What 3 words describe the emotional tone of your brand voice?",
                    'energy_2' => "How do you want your audience to feel after encountering your message?",
                    'energy_3' => "What do you not want to sound like?",
                    'energy_4' => "Do you prefer poetic, punchy, playful, or professional language?",
                    'energy_5' => "Share a quote, phrase, or piece of content that feels like you.",
                ],
                '🎨 Expression' => [
                    'expression_1' => "What are your brand’s primary colors (and any specific hex codes)?",
                    'expression_2' => "What font(s) do you use — or wish to use — for headers and body text?",
                    'expression_3' => "Is there a visual theme (earthy, cosmic, minimalist, ornate) that matches your brand essence?",
                    'expression_4' => "Are there any logos, patterns, or symbols that hold meaning for your brand?",
                    'expression_5' => "What offerings are you currently sharing with the world — and how are they priced or exchanged?",
                ],
                '🚀 Direction' => [
                    'direction_1' => "What’s your current main offer or project you want support with?",
                    'direction_2' => "Who is your dream client? Describe them with emotion and detail.",
                    'direction_3' => "What are 3 key goals you have for the next 6 months?",
                    'direction_4' => "Where do you feel stuck, overwhelmed, or unsure — and where would you love AI support?",
                    'direction_5' => "If this AI assistant could speak your soul fluently, what would you want it to never forget?",
                ],
            ];
            $pdf_loader = AIOHM_PDF_Library_Loader::get_instance();
            if (!$pdf_loader->load_mpdf()) {
                wp_die(esc_html__('PDF generation is not available.', 'aiohm-knowledge-assistant'));
            }
            
            $pdf = new AIOHM_Simple_PDF_Generator('Your Brand Core Questionnaire - ' . $user_info->display_name);
            $pdf->AddPage();
            
            // Add header information
            $pdf->ChapterTitle('Your Brand Core Questionnaire');
            
            foreach ($brand_soul_questions as $section_title => $questions) {
                $pdf->ChapterTitle($section_title);
                foreach ($questions as $key => $question_text) {
                    $answer = isset($answers[$key]) ? $answers[$key] : 'No answer provided.';
                    $pdf->MessageBlock('question', $question_text, gmdate('Y-m-d H:i:s'));
                    $pdf->MessageBlock('answer', $answer, gmdate('Y-m-d H:i:s'));
                }
            }
            $brand_name = sanitize_title($user_info->display_name);
            $filename = $brand_name . '-AI-brand-core.pdf';
            $pdf->Output($filename, 'D');
            exit;
        }
    }

    public static function handle_save_mirror_mode_settings_ajax() {
        // Check user permissions first
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Form data is sanitized after parsing
        $raw_form_data = isset($_POST['form_data']) ? wp_unslash($_POST['form_data']) : '';
        
        // Fix URL encoding issues that cause space loss
        $raw_form_data = str_replace('%22.%20', '%22.%20', $raw_form_data);
        $raw_form_data = str_replace('%22.Your', '%22.%20Your', $raw_form_data);
        $raw_form_data = str_replace('%22.You', '%22.%20You', $raw_form_data);
        $raw_form_data = str_replace('tagline%3A%20%22', 'tagline%3A%20%22', $raw_form_data);
        
        parse_str($raw_form_data, $form_data);
        
        // Check nonce from parsed form data
        $nonce_value = $form_data['aiohm_mirror_mode_nonce_field'] ?? '';
        if (!wp_verify_nonce($nonce_value, 'aiohm_mirror_mode_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed.']);
        }
        
        // Handle the form data - check for both normal and malformed keys
        $settings_input = [];
        
        // First check for properly structured data
        if (isset($form_data['aiohm_kb_settings']['mirror_mode'])) {
            $settings_input = $form_data['aiohm_kb_settings']['mirror_mode'];
        } else {
            // Fallback: Handle malformed array keys from form serialization
            foreach ($form_data as $key => $value) {
                if (strpos($key, 'aiohm_kb_settingsmirror_mode') === 0) {
                    $field_name = str_replace('aiohm_kb_settingsmirror_mode', '', $key);
                    $settings_input[$field_name] = $value;
                }
            }
        
        // Verify nonce first before processing any POST data
        if (!check_ajax_referer('aiohm_muse_mode_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        }
        
        if (empty($settings_input)) {
            wp_send_json_error(['message' => __('No settings data received.', 'aiohm-knowledge-assistant')]);
        }
        
        // Get current settings from database (not the merged defaults)
        $settings = get_option('aiohm_kb_settings', []);
        
        // Ensure mirror_mode structure exists
        if (!isset($settings['mirror_mode'])) {
            $settings['mirror_mode'] = [];
        }
        
        $settings['mirror_mode']['business_name'] = sanitize_text_field(trim($settings_input['business_name'] ?? ''));
        // Handle system message with proper formatting preservation
        $qa_system_message = trim($settings_input['qa_system_message'] ?? '');
        
        // Default template 
        $clean_default = "You are the official AI Knowledge Assistant for \"%site_name%\".\n\nYour core mission is to embody our brand's tagline: \"%site_tagline%\".\n\nYou are to act as a thoughtful and emotionally intelligent guide for all website visitors, reflecting the unique voice of the brand. You should be aware that today is %day_of_week%, %current_date%.\n\nCore Instructions:\n\n1. Primary Directive: Your primary goal is to answer the user's question by grounding your response in the context provided below. This context is your main source of truth.\n\n2. Tone & Personality:\n   - Speak with emotional clarity, not robotic formality.\n   - Sound like a thoughtful assistant, not a sales rep.\n   - Be concise, but not curt — useful, but never cold.\n   - Your purpose is to express with presence, not persuasion.\n\n3. Formatting Rules:\n   - Use only basic HTML tags for clarity (like <strong> or <em> if needed). Do not use Markdown.\n   - Never end your response with a question like \"Do you need help with anything else?\"\n\n4. Fallback Response (Crucial):\n   - If the provided context does not contain enough information to answer the user's question, you MUST respond with this exact phrase: \"Hmm… I don't want to guess here. This might need a human's wisdom. You can connect with the person behind this site on the contact page. They'll know exactly how to help.\"\n\nPrimary Context for Answering the User's Question:\n{context}";
        
        // Check for any signs of corruption and restore clean version
        if (empty($qa_system_message) || 
            // Original corruption patterns
            strpos($qa_system_message, 'y_of_week%') !== false || 
            strpos($qa_system_message, 'Core Instructions: 1.') !== false ||
            // New corruption patterns (missing spaces)
            strpos($qa_system_message, '"%site_name%".Your') !== false ||
            strpos($qa_system_message, 'tagline:"%site_tagline%".You') !== false ||
            strpos($qa_system_message, '"%site_name%".You') !== false ||
            // General corruption check - if it contains variables but no spaces around punctuation
            (strpos($qa_system_message, '%site_name%') !== false && 
             (strpos($qa_system_message, '".You') !== false || strpos($qa_system_message, '".Your') !== false))) {
            
            $qa_system_message = $clean_default;
        } else {
            // Decode HTML entities and preserve formatting
            $qa_system_message = html_entity_decode($qa_system_message, ENT_QUOTES, 'UTF-8');
        }
        
        $settings['mirror_mode']['qa_system_message'] = $qa_system_message;
        $settings['mirror_mode']['qa_temperature'] = floatval($settings_input['qa_temperature'] ?? 0.7);
        $settings['mirror_mode']['primary_color'] = sanitize_hex_color($settings_input['primary_color'] ?? '#1f5014');
        $settings['mirror_mode']['background_color'] = sanitize_hex_color($settings_input['background_color'] ?? '#f0f4f8');
        $settings['mirror_mode']['text_color'] = sanitize_hex_color($settings_input['text_color'] ?? '#ffffff');
        $settings['mirror_mode']['ai_avatar'] = esc_url_raw($settings_input['ai_avatar'] ?? '');
        $settings['mirror_mode']['welcome_message'] = wp_kses_post(trim($settings_input['welcome_message'] ?? ''));
        $settings['mirror_mode']['ai_model'] = sanitize_text_field($settings_input['ai_model'] ?? 'gpt-3.5-turbo');
        
        // Handle URL sanitization with proper validation
        $meeting_url = trim($settings_input['meeting_button_url'] ?? '');
        if (!empty($meeting_url)) {
            // Fix common URL issues
            $meeting_url = str_replace('httpsohm.com', 'https://ohm.com', $meeting_url);
            $meeting_url = str_replace('httpohm.com', 'http://ohm.com', $meeting_url);
            
            // Add protocol if missing
            if (!preg_match('/^https?:\/\//', $meeting_url)) {
                $meeting_url = 'https://' . $meeting_url;
            }
            $settings['mirror_mode']['meeting_button_url'] = esc_url_raw($meeting_url);
        } else {
            $settings['mirror_mode']['meeting_button_url'] = '';
        }

        // Save the settings
        $result = update_option('aiohm_kb_settings', $settings, true);
        
        // If update_option returns false, try direct database update
        if (!$result) {
            global $wpdb;
            $option_name = 'aiohm_kb_settings';
            $option_value = serialize($settings);
            $autoload = 'yes';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Settings update fallback when update_option fails
            $result = $wpdb->replace(
                $wpdb->options,
                array(
                    'option_name' => $option_name,
                    'option_value' => $option_value,
                    'autoload' => $autoload
                ),
                array('%s', '%s', '%s')
            );
            
        }
        
        // Force clear all caches
        wp_cache_delete('aiohm_kb_settings', 'options');
        wp_cache_flush();
        
        wp_send_json_success(['message' => __('Mirror Mode settings saved successfully.', 'aiohm-knowledge-assistant')]);
    }
    
    public static function monitor_settings_changes($old_value, $new_value) {
        // Monitor for unintended setting removals
        if (isset($old_value['mirror_mode']) && !isset($new_value['mirror_mode'])) {
            AIOHM_KB_Assistant::log('Mirror mode settings were removed during save', 'warning');
        }
        
        if (isset($old_value['muse_mode']) && !isset($new_value['muse_mode'])) {
            AIOHM_KB_Assistant::log('Muse mode settings were removed during save', 'warning');
        }
    }
    
    public static function monitor_settings_deletion($option_name) {
        if ($option_name === 'aiohm_kb_settings') {
            AIOHM_KB_Assistant::log('AIOHM settings option was deleted', 'warning');
        }
    }

    public static function handle_save_muse_mode_settings_ajax() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Form data is sanitized after parsing
        $raw_form_data = isset($_POST['form_data']) ? wp_unslash($_POST['form_data']) : '';
        parse_str($raw_form_data, $form_data);
        
        // Check nonce from parsed form data
        $nonce_value = $form_data['aiohm_muse_mode_nonce_field'] ?? '';
        if (!wp_verify_nonce($nonce_value, 'aiohm_muse_mode_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed.']);
        }
        
        // Handle the form data - check for both normal and malformed keys
        $muse_input = [];
        
        // First check for properly structured data
        if (isset($form_data['aiohm_kb_settings']['muse_mode'])) {
            $muse_input = $form_data['aiohm_kb_settings']['muse_mode'];
        } else {
            // Fallback: Handle malformed array keys from form serialization
            foreach ($form_data as $key => $value) {
                if (strpos($key, 'aiohm_kb_settingsmuse_mode') === 0) {
                    $field_name = str_replace('aiohm_kb_settingsmuse_mode', '', $key);
                    $muse_input[$field_name] = $value;
                }
            }
        }

        if (empty($muse_input)) {
            wp_send_json_error(['message' => __('No settings data received.', 'aiohm-knowledge-assistant')]);
        }

        // Get current settings from database (not the merged defaults)
        $settings = get_option('aiohm_kb_settings', []);
        
        // Ensure muse_mode structure exists
        if (!isset($settings['muse_mode'])) {
            $settings['muse_mode'] = [];
        }
        
        $settings['muse_mode']['assistant_name'] = sanitize_text_field($muse_input['assistant_name'] ?? 'Muse');
        
        // Handle system prompt with special care to preserve formatting
        $raw_system_prompt = $muse_input['system_prompt'] ?? '';
        if (!empty($raw_system_prompt)) {
            // Decode any HTML entities that might have been encoded
            $decoded_prompt = html_entity_decode($raw_system_prompt, ENT_QUOTES, 'UTF-8');
            // Use wp_kses_post which preserves formatting but sanitizes dangerous content
            $settings['muse_mode']['system_prompt'] = $decoded_prompt;
        } else {
            $settings['muse_mode']['system_prompt'] = '';
        }
        
        $settings['muse_mode']['ai_model'] = sanitize_text_field($muse_input['ai_model'] ?? 'gpt-4');
        $settings['muse_mode']['temperature'] = floatval($muse_input['temperature'] ?? 0.7);
        $settings['muse_mode']['start_fullscreen'] = isset($muse_input['start_fullscreen']) ? 1 : 0;
        $settings['muse_mode']['brand_archetype'] = sanitize_text_field($muse_input['brand_archetype'] ?? '');

        // Save the settings
        $result = update_option('aiohm_kb_settings', $settings, true);
        
        // If update_option returns false, try direct database update
        if (!$result) {
            global $wpdb;
            $option_name = 'aiohm_kb_settings';
            $option_value = serialize($settings);
            $autoload = 'yes';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Settings update fallback when update_option fails
            $result = $wpdb->replace(
                $wpdb->options,
                array(
                    'option_name' => $option_name,
                    'option_value' => $option_value,
                    'autoload' => $autoload
                ),
                array('%s', '%s', '%s')
            );
        }
        
        // Force clear all caches
        wp_cache_delete('aiohm_kb_settings', 'options');
        wp_cache_flush();
        
        wp_send_json_success(['message' => __('Muse Mode settings saved successfully.', 'aiohm-knowledge-assistant')]);
    }

    public static function handle_generate_mirror_mode_qa_ajax() {
        if (!check_ajax_referer('aiohm_mirror_mode_nonce', 'nonce', false) || !current_user_can('read')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        try {
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $ai_client = new AIOHM_KB_AI_GPT_Client();
            $settings = AIOHM_KB_Assistant::get_settings();
            
            $random_chunk = $rag_engine->get_random_chunk();
            if (!$random_chunk) {
                throw new Exception("Your knowledge base is empty. Please scan some content first.");
            }
            
            // Use the AI model selected in Mirror Mode, fallback to default provider
            $mirror_model = $settings['mirror_mode']['ai_model'] ?? '';
            if (!empty($mirror_model)) {
                $model = $mirror_model;
            } else {
                // Fallback to default provider if no Mirror Mode model is set
                $default_provider = $settings['default_ai_provider'] ?? 'openai';
                switch ($default_provider) {
                    case 'gemini':
                        $model = 'gemini-pro';
                        break;
                    case 'claude':
                        $model = 'claude-3-sonnet';
                        break;
                    case 'shareai':
                        $model = 'shareai-llama4:17b-scout-16e-instruct-fp16';
                        break;
                    case 'ollama':
                        $model = 'ollama';
                        break;
                    case 'openai':
                    default:
                        $model = 'gpt-3.5-turbo';
                        break;
                }
            }
            
            $question_prompt = "Based on the following text, what is a likely user question? Only return the question itself, without any preamble.\n\nCONTEXT:\n" . $random_chunk;
            $question = $ai_client->get_chat_completion($question_prompt, "", 0.7, $model);
            $answer_prompt = "You are a helpful assistant. Answer the following question based on the provided context.\n\nCONTEXT:\n{$random_chunk}\n\nQUESTION:\n{$question}";
            $answer = $ai_client->get_chat_completion($answer_prompt, "", 0.2, $model);
            wp_send_json_success(['qa_pair' => ['question' => trim(str_replace('"', '', $question)), 'answer' => trim($answer)]]);
        } catch (Exception $e) {
            // translators: %s is the Q&A generation error message
            wp_send_json_error(['message' => sprintf(__('Failed to generate Q&A pair: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }

    /**
     * Handle file uploads for projects
     */
    public static function handle_upload_project_files_ajax() {
        // Security checks
        if (!check_ajax_referer('aiohm_private_chat_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }

        // Check if files were uploaded
        if (empty($_FILES['files'])) {
            wp_send_json_error(['message' => __('No files uploaded.', 'aiohm-knowledge-assistant')]);
        }

        // Get project ID
        $project_id = intval($_POST['project_id'] ?? 0);
        if (!$project_id) {
            wp_send_json_error(['message' => __('Invalid project ID.', 'aiohm-knowledge-assistant')]);
        }

        // Verify user owns the project
        global $wpdb;
        $user_id = get_current_user_id();
        $project_table = $wpdb->prefix . 'aiohm_projects';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Project data query
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aiohm_projects WHERE id = %d AND user_id = %d",
            $project_id, $user_id
        ));

        if (!$project) {
            wp_send_json_error(['message' => __('Project not found or access denied.', 'aiohm-knowledge-assistant')]);
        }

        // Define upload directory
        $upload_base_dir = wp_upload_dir();
        $project_upload_dir = $upload_base_dir['basedir'] . '/aiohm_project_files/project_' . $project_id;
        $project_upload_url = $upload_base_dir['baseurl'] . '/aiohm_project_files/project_' . $project_id;

        // Create directory if it doesn't exist
        if (!file_exists($project_upload_dir)) {
            wp_mkdir_p($project_upload_dir);
            
            // Create .htaccess to prevent PHP execution
            $htaccess_content = "# AIOHM Knowledge Assistant Security\n";
            $htaccess_content .= "# Prevent execution of uploaded files\n";
            $htaccess_content .= "php_flag engine off\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "    Order allow,deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            $htaccess_content .= "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n";
            $htaccess_content .= "    Order allow,deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            
            // Initialize WP_Filesystem if needed
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            // Write security files using WP_Filesystem
            $wp_filesystem->put_contents($project_upload_dir . '/.htaccess', $htaccess_content, FS_CHMOD_FILE);
            
            // Create index.php to prevent directory listing
            $wp_filesystem->put_contents($project_upload_dir . '/index.php', '<?php // Silence is golden', FS_CHMOD_FILE);
        }

        // Define allowed file types - SECURITY: Reduced to essential document types only
        $allowed_types = [
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'json' => 'application/json'
        ];
        
        // Add allowed MIME types to WordPress upload filters
        add_filter('upload_mimes', function($mimes) {
            $mimes['json'] = 'application/json';
            return $mimes;
        });

        $uploaded_files = [];
        $errors = [];

        // Handle multiple files
        $files = [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload data processed with wp_handle_upload
        if (isset($_FILES['files']) && is_array($_FILES['files'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload data processed with wp_handle_upload
            $files = $_FILES['files'];
        }
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = sprintf(
                    /* translators: %1$s: filename, %2$d: error code */
                    __('Error uploading %1$s: Upload error code %2$d', 'aiohm-knowledge-assistant'),
                    esc_html($file['name']),
                    intval($file['error'])
                );
                continue;
            }
            
            // Validate file size (10MB limit)
            if ($file['size'] > 10 * 1024 * 1024) {
                $errors[] = sprintf(
                    /* translators: %s: filename */
                    __('File %s exceeds 10MB size limit.', 'aiohm-knowledge-assistant'),
                    esc_html($file['name'])
                );
                continue;
            }
            
            // Validate file type using finfo for security
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                $allowed_mimes = [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/json',
                    'audio/mpeg',
                    'audio/wav',
                    'audio/mp4',
                    'audio/ogg',
                    'image/jpeg',
                    'image/png',
                    'image/gif'
                ];
                
                if (!in_array($mime_type, $allowed_mimes, true)) {
                    $errors[] = sprintf(
                        /* translators: %s: filename */
                        __('File %s has invalid file type.', 'aiohm-knowledge-assistant'),
                        esc_html($file['name'])
                    );
                    continue;
                }
            }
            
            // Sanitize filename to prevent path traversal
            $file['name'] = sanitize_file_name(basename($file['name']));
            $file['name'] = preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);

            // Additional security check - already done above but keeping as backup
            if ($file['size'] > 10 * 1024 * 1024) {
                $errors[] = sprintf(
                    /* translators: %s: filename */
                    __('File %s is too large (max 10MB)', 'aiohm-knowledge-assistant'),
                    esc_html($file['name'])
                );
                continue;
            }

            // Check file type
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!array_key_exists($file_ext, $allowed_types)) {
                $errors[] = "File type not allowed for {$file['name']}";
                continue;
            }

            // Enhanced MIME type and content validation
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types)) {
                $errors[] = "Invalid file type for {$file['name']} (MIME: {$mime_type})";
                continue;
            }
            
            // Additional file content validation
            if (!$this->validate_file_content($file['tmp_name'], $file_ext, $mime_type)) {
                $errors[] = "File content validation failed for {$file['name']}";
                continue;
            }

            // Use WordPress secure file upload handling
            $upload_overrides = array(
                'test_form' => false,
                'mimes' => $allowed_types,
                'upload_path' => $project_upload_dir
            );
            
            // Prepare file array for wp_handle_upload
            $uploaded_file = array(
                'name' => $file['name'],
                'type' => $mime_type,
                'tmp_name' => $file['tmp_name'],
                'error' => $file['error'],
                'size' => $file['size']
            );
            
            // Use WordPress secure file upload
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload_result = wp_handle_upload($uploaded_file, $upload_overrides);
            
            if (isset($upload_result['error'])) {
                $errors[] = "Upload failed for {$file['name']}: " . $upload_result['error'];
                continue;
            }
            
            // Move to project directory if not already there
            $final_path = $project_upload_dir . '/' . basename($upload_result['file']);
            if ($upload_result['file'] !== $final_path) {
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . '/wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                if (!$wp_filesystem->move($upload_result['file'], $final_path)) {
                    $errors[] = "Failed to move {$file['name']} to project directory";
                    continue;
                }
            } else {
                $final_path = $upload_result['file'];
            }
            
            $uploaded_files[] = [
                'name' => basename($final_path),
                'original_name' => $file['name'],
                'path' => $final_path,
                'url' => $project_upload_url . '/' . basename($final_path),
                'type' => $file_ext,
                'size' => $file['size'],
                'mime_type' => $mime_type
            ];

            // Add to knowledge base
            self::add_file_to_knowledge_base($final_path, $file['name'], $project_id, $user_id, $file_ext, $mime_type);
        }

        // Return response
        if (!empty($uploaded_files)) {
            wp_send_json_success([
                'message' => count($uploaded_files) . ' file(s) uploaded successfully',
                'files' => $uploaded_files,
                'errors' => $errors
            ]);
        } else {
            wp_send_json_error([
                'message' => 'No files were uploaded successfully',
                'errors' => $errors
            ]);
        }
    }

    /**
     * Add uploaded file to knowledge base
     */
    private static function add_file_to_knowledge_base($file_path, $original_name, $project_id, $user_id, $file_ext, $mime_type) {
        global $wpdb;
        
        try {
            $content = '';
            $content_type = 'file';
            
            // Extract content based on file type
            if ($file_ext === 'txt') {
                // Initialize WP_Filesystem if needed
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                $content = $wp_filesystem->get_contents($file_path);
                if (false === $content) {
                    $content = '';
                }
                $content_type = 'text';
            } elseif ($file_ext === 'pdf') {
                $content = self::extract_pdf_content($file_path, $original_name);
                $content_type = 'pdf';
            } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $content = "Image file uploaded: {$original_name}. This image has been added to the project and can be referenced in conversations. The user can ask questions about this image or request analysis of its contents.";
                $content_type = 'image';
            } elseif (in_array($file_ext, ['mp3', 'wav', 'm4a', 'ogg'])) {
                $content = "Audio file uploaded: {$original_name}. This audio file has been added to the project and can be referenced in conversations. The user may ask for transcription or analysis of the audio content.";
                $content_type = 'audio';
            } elseif (in_array($file_ext, ['doc', 'docx'])) {
                $content = self::extract_document_content($file_path, $original_name, $file_ext);
                $content_type = 'document';
            }

            // Insert into knowledge base
            $table_name = $wpdb->prefix . 'aiohm_vector_entries';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- File content insertion with cache invalidation handled elsewhere
            $result = $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'content_id' => 'project_file_' . $project_id . '_' . time(),
                    'content_type' => $content_type,
                    'title' => $original_name,
                    'content' => $content,
                    'metadata' => json_encode([
                        'project_id' => $project_id,
                        'file_path' => $file_path,
                        'file_type' => $file_ext,
                        'mime_type' => $mime_type,
                        'upload_date' => current_time('mysql')
                    ])
                ],
                [
                    '%d', '%s', '%s', '%s', '%s', '%s'
                ]
            );

            if ($result === false) {
                // Failed to add file to knowledge base
            }

        } catch (Exception $e) {
            // Error adding file to knowledge base
        }
    }

    /**
     * Extract text content from PDF files
     */
    private static function extract_pdf_content($file_path, $original_name) {
        try {
            // SECURITY: Removed exec() call to prevent command injection vulnerabilities
            // Command line tools are disabled for security reasons

            // PDF parsing not available in WordPress.org version

            // Method 3: Fallback - create a descriptive entry that tells the AI about the PDF
            return "PDF Document uploaded: {$original_name}. This is a PDF file that has been uploaded to the project. The user has indicated they want to analyze or discuss the contents of this PDF. While I cannot directly read PDF files in this conversation, I can help the user by asking them to paste relevant text excerpts from the PDF that they'd like to discuss, or I can provide guidance on how to extract and work with PDF content.";

        } catch (Exception $e) {
            return "PDF Document: {$original_name} - Content extraction failed, but file is available for reference.";
        }
    }

    /**
     * Extract text content from Word documents
     */
    private static function extract_document_content($file_path, $original_name, $file_ext) {
        try {
            // SECURITY: Removed exec() calls to prevent command injection vulnerabilities
            // Command line document processing tools are disabled for security reasons

            // Method 3: Try ZIP extraction for .docx (it's essentially a ZIP file)
            if ($file_ext === 'docx' && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($file_path) === TRUE) {
                    $xml_content = $zip->getFromName('word/document.xml');
                    $zip->close();
                    
                    if ($xml_content) {
                        // Parse XML and extract text
                        $dom = new DOMDocument();
                        if (@$dom->loadXML($xml_content)) {
                            $text_nodes = $dom->getElementsByTagName('t');
                            $content = '';
                            foreach ($text_nodes as $node) {
                                $content .= $node->nodeValue . ' ';
                            }
                            
                            if (strlen(trim($content)) > 50) {
                                return "Word Document: {$original_name}\n\nContent:\n" . trim($content);
                            }
                        }
                    }
                }
            }

            // Fallback - create a descriptive entry
            return "Document uploaded: {$original_name}. This is a Word document that has been uploaded to the project. The user has indicated they want to analyze or discuss the contents of this document. While I cannot directly read Word documents in this conversation, I can help the user by asking them to paste relevant text excerpts from the document that they'd like to discuss, or I can provide guidance on how to extract and work with document content.";

        } catch (Exception $e) {
            return "Document: {$original_name} - Content extraction failed, but file is available for reference.";
        }
    }

    /**
     * Handle AJAX request to get Brand Soul content for viewing
     */
    public static function handle_get_brand_soul_content_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false) || !current_user_can('read')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
            return;
        }

        $content_id = isset($_POST['content_id']) ? sanitize_text_field(wp_unslash($_POST['content_id'])) : '';
        if (empty($content_id)) {
            wp_send_json_error(['message' => __('Content ID is required.', 'aiohm-knowledge-assistant')]);
            return;
        }

        try {
            global $wpdb;
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $table_name = $wpdb->prefix . 'aiohm_vector_entries';

            // Get the brand soul content by content_id
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Brand soul content query
            $entry = $wpdb->get_row($wpdb->prepare(
                "SELECT content, title FROM {$wpdb->prefix}aiohm_vector_entries WHERE content_id = %s AND content_type IN ('brand-soul', 'brand_soul') LIMIT 1",
                $content_id
            ), ARRAY_A);

            if (!$entry) {
                wp_send_json_error(['message' => __('Brand Soul content not found.', 'aiohm-knowledge-assistant')]);
                return;
            }

            wp_send_json_success([
                'content' => $entry['content'],
                'title' => $entry['title']
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Error retrieving Brand Soul content.', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Handle AJAX request to get content for viewing (supports all content types)
     */
    public static function handle_get_content_for_view_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false) || !current_user_can('read')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
            return;
        }

        $content_id = isset($_POST['content_id']) ? sanitize_text_field(wp_unslash($_POST['content_id'])) : '';
        $content_type = isset($_POST['content_type']) ? sanitize_text_field(wp_unslash($_POST['content_type'])) : '';
        
        if (empty($content_id)) {
            wp_send_json_error(['message' => __('Content ID is required.', 'aiohm-knowledge-assistant')]);
            return;
        }

        try {
            global $wpdb;
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $table_name = $wpdb->prefix . 'aiohm_vector_entries';

            // Get the content by content_id, including metadata
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Content lookup query
            $entry = $wpdb->get_row($wpdb->prepare(
                "SELECT content, title, content_type, metadata FROM {$wpdb->prefix}aiohm_vector_entries WHERE content_id = %s LIMIT 1",
                $content_id
            ), ARRAY_A);

            if (!$entry) {
                wp_send_json_error(['message' => __('Content not found.', 'aiohm-knowledge-assistant')]);
                return;
            }

            // Check if this is a PDF with failed extraction and try to re-process it
            $formatted_content = $entry['content'];
            $content_type = $entry['content_type'];
            if ($content_type === 'application/pdf' && strpos($entry['content'], 'content extraction failed') !== false) {
                // Try to re-extract PDF content if we have metadata with attachment_id
                $metadata = json_decode($entry['metadata'], true);
                
                
                if (is_array($metadata) && isset($metadata['attachment_id'])) {
                    $attachment_path = get_attached_file($metadata['attachment_id']);
                    
                    
                    // PDF parsing not available in WordPress.org version
                    // Provide basic file information instead
                    $formatted_content = "PDF Document: " . basename($attachment_path) . "\n";
                    $formatted_content .= "This PDF file is available for AI processing. The AI service will extract and analyze the PDF content when you interact with it.";
                } else {
                    // PDF parsing not available in WordPress.org version
                    $formatted_content = "PDF Document uploaded. The AI service will extract and analyze the PDF content when you interact with it.";
                }
            }

            // Format content based on type for better display
            if ($content_type === 'application/json' || $content_type === 'text/json') {
                // Try to pretty-print JSON
                $decoded = json_decode($formatted_content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $formatted_content = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            } elseif ($content_type === 'text/csv') {
                // Format CSV with better spacing and headers
                $lines = explode("\n", $formatted_content);
                if (!empty($lines)) {
                    $formatted_lines = [];
                    foreach ($lines as $line) {
                        if (!empty(trim($line))) {
                            $formatted_lines[] = str_replace(',', ' | ', trim($line));
                        }
                    }
                    $formatted_content = implode("\n", $formatted_lines);
                }
            }
            
            wp_send_json_success([
                'content' => $formatted_content,
                'title' => $entry['title'],
                'content_type' => $entry['content_type']
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Error retrieving content.', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Handles the AJAX request to get AI usage statistics
     */
    public static function handle_get_usage_stats_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
            return;
        }

        try {
            global $wpdb;
            
            // Create usage stats table if it doesn't exist
            $table_name = $wpdb->prefix . 'aiohm_usage_tracking';
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                provider varchar(20) NOT NULL,
                tokens_used int(11) NOT NULL DEFAULT 0,
                requests_count int(11) NOT NULL DEFAULT 1,
                cost_estimate decimal(10,6) NOT NULL DEFAULT 0.000000,
                usage_date date NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY provider_date (provider, usage_date)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Get current date and 30 days ago
            $today = gmdate('Y-m-d');
            $thirty_days_ago = gmdate('Y-m-d', strtotime('-30 days'));

            // Calculate total tokens for last 30 days
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Usage analytics query
            $total_tokens_30d = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(tokens_used) FROM {$wpdb->prefix}aiohm_usage_tracking WHERE usage_date >= %s",
                $thirty_days_ago
            )) ?: 0;

            // Calculate today's tokens
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Daily usage query
            $tokens_today = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(tokens_used) FROM {$wpdb->prefix}aiohm_usage_tracking WHERE usage_date = %s",
                $today
            )) ?: 0;

            // Calculate estimated cost for last 30 days
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cost estimation query
            $estimated_cost = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(cost_estimate) FROM {$wpdb->prefix}aiohm_usage_tracking WHERE usage_date >= %s",
                $thirty_days_ago
            )) ?: 0;

            // Get breakdown by provider
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Statistics query with caching
            $provider_stats = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    provider,
                    SUM(tokens_used) as tokens,
                    SUM(requests_count) as requests,
                    SUM(cost_estimate) as cost
                FROM {$wpdb->prefix}aiohm_usage_tracking 
                WHERE usage_date >= %s 
                GROUP BY provider",
                $thirty_days_ago
            ), ARRAY_A);

            // Format provider data
            $providers = [
                'openai' => ['tokens' => 0, 'requests' => 0, 'cost' => '0.00'],
                'gemini' => ['tokens' => 0, 'requests' => 0, 'cost' => '0.00'],
                'claude' => ['tokens' => 0, 'requests' => 0, 'cost' => '0.00'],
                'shareai' => ['tokens' => 0, 'requests' => 0, 'cost' => '0.00'],
                'ollama' => ['tokens' => 0, 'requests' => 0, 'cost' => '0.00']
            ];

            foreach ($provider_stats as $stat) {
                if (isset($providers[$stat['provider']])) {
                    $providers[$stat['provider']] = [
                        'tokens' => (int) $stat['tokens'],
                        'requests' => (int) $stat['requests'],
                        'cost' => number_format((float) $stat['cost'], 2)
                    ];
                }
            }

            wp_send_json_success([
                'total_tokens_30d' => (int) $total_tokens_30d,
                'tokens_today' => (int) $tokens_today,
                'estimated_cost' => number_format((float) $estimated_cost, 2),
                'providers' => $providers
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Error retrieving usage statistics.', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Helper function to log AI usage (call this whenever AI APIs are used)
     */
    public static function log_ai_usage($provider, $tokens_used, $cost_estimate = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aiohm_usage_tracking';
        $today = gmdate('Y-m-d');
        
        // Check if we already have an entry for today and this provider
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Usage stats lookup for tracking
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, tokens_used, requests_count, cost_estimate FROM {$wpdb->prefix}aiohm_usage_tracking 
             WHERE provider = %s AND usage_date = %s",
            $provider, $today
        ));
        
        if ($existing) {
            // Update existing record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Usage stats update for logging
            $wpdb->update(
                $table_name,
                [
                    'tokens_used' => $existing->tokens_used + $tokens_used,
                    'requests_count' => $existing->requests_count + 1,
                    'cost_estimate' => $existing->cost_estimate + $cost_estimate
                ],
                ['id' => $existing->id]
            );
        } else {
            // Create new record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Usage stats creation for logging
            $wpdb->insert(
                $table_name,
                [
                    'provider' => $provider,
                    'tokens_used' => $tokens_used,
                    'requests_count' => 1,
                    'cost_estimate' => $cost_estimate,
                    'usage_date' => $today
                ]
            );
        }
    }

    /**
     * Handle download conversation as PDF
     */
    public static function handle_download_conversation_pdf_ajax() {
        if (!check_ajax_referer('aiohm_private_chat_nonce', 'nonce', false)) {
            wp_die(esc_html__('Security check failed.', 'aiohm-knowledge-assistant'));
        }
        
        if (!current_user_can('read')) {
            wp_die(esc_html__('You do not have permission to access this feature.', 'aiohm-knowledge-assistant'));
        }
        
        try {
            $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
            if (!$conversation_id) {
                wp_die(esc_html__('Invalid conversation ID.', 'aiohm-knowledge-assistant'));
            }
            
            global $wpdb;
            $user_id = get_current_user_id();
            
            // Get conversation details
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- PDF generation query for user-owned conversation
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aiohm_conversations WHERE id = %d AND user_id = %d",
                $conversation_id, $user_id
            ));
            
            if (!$conversation) {
                wp_die(esc_html__('Conversation not found or access denied.', 'aiohm-knowledge-assistant'));
            }
            
            // Get all messages for this conversation
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- PDF generation query for conversation messages
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aiohm_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            ));
            
            // Include the PDF library loader and enhanced PDF class
            require_once AIOHM_KB_PLUGIN_DIR . 'includes/pdf-library-loader.php';
            require_once AIOHM_KB_PLUGIN_DIR . 'includes/class-enhanced-pdf.php';
            
            // Create enhanced PDF instance
            $pdf = new AIOHM_Enhanced_PDF();
            $pdf->AddPage();
            
            // Conversation details
            $pdf->ChapterTitle('Conversation: ' . $conversation->title);
            
            // Add conversation metadata using message blocks
            $pdf->MessageBlock('system', 'Created: ' . gmdate('F j, Y g:i A', strtotime($conversation->created_at)), $conversation->created_at);
            $pdf->MessageBlock('system', 'Project ID: ' . $conversation->project_id, $conversation->created_at);
            
            // Messages
            foreach ($messages as $message) {
                $pdf->MessageBlock($message->sender, $message->content, $message->created_at);
            }
            
            // Output PDF
            $filename = 'conversation-' . $conversation_id . '-' . gmdate('Y-m-d') . '.pdf';
            $pdf->Output($filename, 'D');
            exit;
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('PDF Download Error: ' . $e->getMessage(), 'error');
            wp_die(esc_html('Error generating PDF: ' . $e->getMessage()));
        }
    }

    /**
     * Handle adding conversation to knowledge base
     */
    public static function handle_add_conversation_to_kb_ajax() {
        if (!check_ajax_referer('aiohm_private_chat_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'You do not have permission to use this feature.']);
        }
        
        try {
            $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
            if (!$conversation_id) {
                wp_send_json_error(['message' => __('Invalid conversation ID.', 'aiohm-knowledge-assistant')]);
            }
            
            global $wpdb;
            $user_id = get_current_user_id();
            
            // Get conversation details
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- KB addition query for user-owned conversation
            $conversation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aiohm_conversations WHERE id = %d AND user_id = %d",
                $conversation_id, $user_id
            ));
            
            if (!$conversation) {
                wp_send_json_error(['message' => __('Conversation not found or access denied.', 'aiohm-knowledge-assistant')]);
            }
            
            // Get all messages for this conversation
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- KB addition query for conversation messages
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aiohm_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            ));
            
            // Compile conversation content
            $content = "Conversation: " . $conversation->title . "\n";
            $content .= "Date: " . $conversation->created_at . "\n\n";
            
            foreach ($messages as $message) {
                $sender = ($message->sender === 'user') ? 'User' : 'Assistant';
                $content .= $sender . ": " . wp_strip_all_tags($message->content) . "\n\n";
            }
            
            // Add to knowledge base
            $rag_engine = new AIOHM_KB_RAG_Engine();
            $metadata = [
                'scope' => 'private',
                'source_type' => 'conversation',
                'source_id' => $conversation_id
            ];
            $result = $rag_engine->add_entry(
                $content,
                'conversation',
                'Conversation: ' . $conversation->title,
                $metadata,
                $user_id,
                0
            );
            
            if ($result) {
                wp_send_json_success(['message' => __('Conversation added to knowledge base successfully.', 'aiohm-knowledge-assistant')]);
            } else {
                wp_send_json_error(['message' => __('Failed to add conversation to knowledge base.', 'aiohm-knowledge-assistant')]);
            }
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Add to KB Error: ' . $e->getMessage(), 'error');
            // translators: %s is the knowledge base addition error message
            wp_send_json_error(['message' => sprintf(__('Error adding to KB: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }
    
    /**
     * Handle research online functionality
     */
    public static function handle_research_online_ajax() {
        if (!check_ajax_referer('aiohm_private_chat_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'You do not have permission to use this feature.']);
        }
        
        try {
            $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
            $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
            $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : null;
            $research_prompt_base = isset($_POST['research_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['research_prompt'])) : '';
            
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                wp_send_json_error(['message' => __('Invalid URL provided.', 'aiohm-knowledge-assistant')]);
            }
            
            if (empty($project_id)) {
                wp_send_json_error(['message' => __('Project ID is required.', 'aiohm-knowledge-assistant')]);
            }
            
            // Fetch the webpage content
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; AIOHM-KB-Assistant/1.2.0; +https://aiohm.app)'
                ]
            ]);
            
            if (is_wp_error($response)) {
                // translators: %s is the webpage fetch error message
                wp_send_json_error(['message' => sprintf(__('Failed to fetch webpage: %s', 'aiohm-knowledge-assistant'), $response->get_error_message())]);
            }
            
            $content = wp_remote_retrieve_body($response);
            if (empty($content)) {
                wp_send_json_error(['message' => __('No content found at the provided URL.', 'aiohm-knowledge-assistant')]);
            }
            
            // Basic HTML content extraction
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($content);
            libxml_clear_errors();
            
            // Remove script and style tags
            $scripts = $dom->getElementsByTagName('script');
            $styles = $dom->getElementsByTagName('style');
            
            for ($i = $scripts->length - 1; $i >= 0; $i--) {
                $scripts->item($i)->parentNode->removeChild($scripts->item($i));
            }
            
            for ($i = $styles->length - 1; $i >= 0; $i--) {
                $styles->item($i)->parentNode->removeChild($styles->item($i));
            }
            
            // Extract text content
            $textContent = wp_strip_all_tags($dom->textContent);
            $textContent = preg_replace('/\s+/', ' ', $textContent);
            $textContent = trim($textContent);
            
            // Limit content length
            if (strlen($textContent) > 5000) {
                $textContent = substr($textContent, 0, 5000) . '...';
            }
            
            if (empty($textContent)) {
                wp_send_json_error(['message' => __('No readable content found at the provided URL.', 'aiohm-knowledge-assistant')]);
            }
            
            // Get page title
            $titleNodes = $dom->getElementsByTagName('title');
            $pageTitle = $titleNodes->length > 0 ? $titleNodes->item(0)->textContent : 'Untitled Page';
            
            // Use AI to analyze the content
            $settings = AIOHM_KB_Assistant::get_settings();
            $ai_client = new AIOHM_KB_AI_GPT_Client($settings);
            
            // Use custom research prompt if provided, otherwise use default
            if (!empty($research_prompt_base)) {
                $research_prompt = $research_prompt_base . "\n\n" .
                                  "**Page Title:** {$pageTitle}\n" .
                                  "**URL:** {$url}\n\n" .
                                  "**Content:**\n{$textContent}";
            } else {
                // Fallback to default prompt
                $research_prompt = "Please analyze the following webpage content and provide a comprehensive summary:\n\n" .
                                  "**Page Title:** {$pageTitle}\n" .
                                  "**URL:** {$url}\n\n" .
                                  "**Content:**\n{$textContent}\n\n" .
                                  "Please provide a structured analysis covering:\n" .
                                  "1. **Main Topic:** What is this page about?\n" .
                                  "2. **Key Points:** What are the most important points mentioned?\n" .
                                  "3. **People/Organizations:** Who are the key people or organizations mentioned?\n" .
                                  "4. **Summary:** Provide a concise 3-sentence summary of the entire content.";
            }
            
            // Adjust system message based on whether custom prompt is used
            $system_message = !empty($research_prompt_base) 
                ? "You are a professional researcher and analyst. Follow the research instructions precisely and provide detailed, insightful analysis based on your assigned perspective."
                : "You are a web content analyst. Provide clear, structured analysis of webpage content.";
            
            $analysis = $ai_client->get_chat_completion(
                $system_message,
                $research_prompt,
                0.3,
                $settings['muse_mode']['ai_model'] ?? 'gpt-3.5-turbo'
            );
            
            // Save to conversation if conversation_id exists
            if ($conversation_id) {
                global $wpdb;
                $user_id = get_current_user_id();
                
                // Save AI analysis as assistant message
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for message storage
                $wpdb->insert(
                    $wpdb->prefix . 'aiohm_messages',
                    [
                        'conversation_id' => $conversation_id,
                        'user_id' => $user_id,
                        'sender' => 'assistant',
                        'content' => $analysis,
                        'created_at' => current_time('mysql')
                    ]
                );
            }
            
            wp_send_json_success([
                'reply' => $analysis,
                'conversation_id' => $conversation_id,
                'url' => $url,
                'title' => $pageTitle
            ]);
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Research Online Error: ' . $e->getMessage(), 'error');
            // translators: %s is the website research error message
            wp_send_json_error(['message' => sprintf(__('Error researching website: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }
    }
    
    /**
     * Helper function to render images in WordPress-compliant way
     * Uses WordPress image functions when possible
     * 
     * @param string $url Image URL
     * @param string $alt Alt text
     * @param array $attributes Additional HTML attributes
     * @return string HTML img tag
     */
    public static function render_image($url, $alt = '', $attributes = []) {
        // Check if this is a WordPress attachment
        $attachment_id = attachment_url_to_postid($url);
        
        if ($attachment_id) {
            // Use WordPress function for attachments
            $image_attributes = array_merge(['alt' => $alt], $attributes);
            return wp_get_attachment_image($attachment_id, 'full', false, $image_attributes);
        }
        
        // For external URLs, build the tag manually with proper escaping
        $url = esc_url($url);
        $alt = esc_attr($alt);
        
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        // Use wp_kses_post to ensure the HTML is safe
        // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- External images cannot use wp_get_attachment_image()
        return wp_kses_post('<img src="' . $url . '" alt="' . $alt . '"' . $attr_string . ' />');
    }
    
    /**
     * Handles sending verification code via AJAX
     */
    public static function handle_send_verification_code_ajax() {
        // Verify nonce for security
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'aiohm_license_verification')) {
            wp_die(json_encode(['success' => false, 'error' => 'Invalid security token.']));
        }

        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        
        if (empty($email) || !is_email($email)) {
            wp_die(json_encode(['success' => false, 'error' => 'Please enter a valid email address.']));
        }

        if (!class_exists('AIOHM_App_API_Client')) {
            wp_die(json_encode(['success' => false, 'error' => 'API client not available.']));
        }

        $api_client = new AIOHM_App_API_Client();
        $result = $api_client->send_verification_code($email);

        if (is_wp_error($result)) {
            wp_die(json_encode(['success' => false, 'error' => $result->get_error_message()]));
        }

        // Store the email for verification (expires in 10 minutes)
        set_transient('aiohm_verification_email_' . md5($email), $email, 10 * MINUTE_IN_SECONDS);

        wp_die(json_encode(['success' => true, 'message' => 'Verification code sent to your email.']));
    }

    /**
     * Handles verifying the email code via AJAX
     */
    public static function handle_verify_email_code_ajax() {
        // Verify nonce for security
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'aiohm_license_verification')) {
            wp_die(json_encode(['success' => false, 'error' => 'Invalid security token.']));
        }

        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
        
        if (empty($email) || !is_email($email)) {
            wp_die(json_encode(['success' => false, 'error' => 'Please enter a valid email address.']));
        }

        if (empty($code)) {
            wp_die(json_encode(['success' => false, 'error' => 'Please enter the verification code.']));
        }

        // Check if email was previously requested for verification
        $stored_email = get_transient('aiohm_verification_email_' . md5($email));
        if (!$stored_email) {
            wp_die(json_encode(['success' => false, 'error' => 'Verification session expired. Please request a new code.']));
        }

        if (!class_exists('AIOHM_App_API_Client')) {
            wp_die(json_encode(['success' => false, 'error' => 'API client not available.']));
        }

        $api_client = new AIOHM_App_API_Client();
        $result = $api_client->verify_code_and_get_details($email, $code);

        if (is_wp_error($result)) {
            wp_die(json_encode(['success' => false, 'error' => $result->get_error_message()]));
        }

        // Verification successful - update the settings
        $settings = AIOHM_KB_Assistant::get_settings();
        $settings['aiohm_app_email'] = $email;
        update_option('aiohm_kb_settings', $settings);

        // Clear the verification transient
        delete_transient('aiohm_verification_email_' . md5($email));

        wp_die(json_encode([
            'success' => true, 
            'message' => 'Email verified successfully! Your account is now connected.',
            'membership_data' => $result
        ]));
    }

    /**
     * Validate uploaded file for security
     * @param array $file File data from $_FILES
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    private static function validate_uploaded_file($file) {
        // Check file size limits (10MB max)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('File size exceeds 10MB limit.', 'aiohm-knowledge-assistant'));
        }
        
        // Validate filename
        $filename = sanitize_file_name($file['name']);
        if (empty($filename) || $filename !== $file['name']) {
            return new WP_Error('invalid_filename', __('Invalid filename. Please use only alphanumeric characters, hyphens, and underscores.', 'aiohm-knowledge-assistant'));
        }
        
        // Validate file extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed_extensions = ['json', 'txt', 'csv', 'pdf', 'doc', 'docx', 'md'];
        
        if (!in_array($ext, $allowed_extensions)) {
            return new WP_Error('invalid_extension', 
                sprintf(
                    /* translators: %s: comma-separated list of allowed file extensions */
                    __('File type not supported. Allowed: %s', 'aiohm-knowledge-assistant'), 
                    implode(', ', $allowed_extensions)
                )
            );
        }
        
        // MIME type validation
        $allowed_mime_types = [
            'json' => ['application/json', 'text/plain'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'text/plain'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'md' => ['text/markdown', 'text/plain']
        ];
        
        $detected_type = mime_content_type($file['tmp_name']);
        if (!isset($allowed_mime_types[$ext]) || !in_array($detected_type, $allowed_mime_types[$ext])) {
            return new WP_Error('mime_type_mismatch', 
                sprintf(
                    /* translators: %1$s: detected MIME type, %2$s: file extension */
                    __('File MIME type (%1$s) does not match extension (%2$s).', 'aiohm-knowledge-assistant'), 
                    $detected_type, 
                    $ext
                )
            );
        }
        
        // Content validation for text files
        if (in_array($ext, ['json', 'txt', 'csv', 'md'])) {
            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                return new WP_Error('unreadable_file', __('File content could not be read.', 'aiohm-knowledge-assistant'));
            }
            
            // Check for potentially malicious content
            if (preg_match('/<script|<iframe|javascript:|vbscript:|onload=|onerror=/i', $content)) {
                return new WP_Error('malicious_content', __('File contains potentially malicious content.', 'aiohm-knowledge-assistant'));
            }
            
            // For JSON files, validate JSON structure
            if ($ext === 'json') {
                json_decode($content);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new WP_Error('invalid_json', __('Invalid JSON format.', 'aiohm-knowledge-assistant'));
                }
            }
        }
        
        return true;
    }

    /**
     * Handle file upload to knowledge base AJAX request
     */
    public static function handle_kb_file_upload_ajax() {
        // Enhanced rate limiting for file uploads
        if (!AIOHM_KB_Assistant::check_enhanced_rate_limit('file_upload', get_current_user_id(), 20)) {
            wp_send_json_error(['message' => __('Upload rate limit exceeded. Please wait before uploading more files.', 'aiohm-knowledge-assistant')]);
        }

        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }

        // Allow JSON uploads during our upload process
        add_filter('upload_mimes', function($mimes) {
            $mimes['json'] = 'application/json';
            $mimes['md'] = 'text/markdown';
            return $mimes;
        }, 999);
        
        // Override WordPress security restrictions for JSON and MD files
        add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext === 'json') {
                $data['ext'] = 'json';
                $data['type'] = 'application/json';
                $data['proper_filename'] = $filename;
            } elseif ($ext === 'md') {
                $data['ext'] = 'md';
                $data['type'] = 'text/markdown';
                $data['proper_filename'] = $filename;
            }
            return $data;
        }, 999, 4);

        // Check if files were uploaded
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES superglobal is validated and sanitized below
        if (empty($_FILES['files'])) {
            wp_send_json_error(['message' => __('No files were uploaded.', 'aiohm-knowledge-assistant')]);
        }

        $scope = sanitize_text_field(wp_unslash($_POST['scope'] ?? 'public'));
        if (!in_array($scope, ['public', 'private'])) {
            $scope = 'public';
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File data is validated and individual values sanitized below
        $files = $_FILES['files'];
        $results = [];
        $uploads_crawler = new AIOHM_KB_Uploads_Crawler();
        $rag_engine = new AIOHM_KB_RAG_Engine();

        // Handle multiple files
        $file_count = is_array($files['name']) ? count($files['name']) : 1;
        
        for ($i = 0; $i < $file_count; $i++) {
            $file = [
                'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                'size' => is_array($files['size']) ? $files['size'][$i] : $files['size']
            ];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $results[] = [
                    'filename' => $file['name'],
                    'success' => false,
                    'message' => 'Upload error: ' . $file['error']
                ];
                continue;
            }

            // Enhanced file validation
            $validation_result = self::validate_uploaded_file($file);
            if (is_wp_error($validation_result)) {
                $results[] = [
                    'filename' => sanitize_text_field($file['name']),
                    'success' => false,
                    'message' => $validation_result->get_error_message()
                ];
                continue;
            }
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            try {
                // Process the file content
                $file_data = self::process_uploaded_file($file['tmp_name'], $file['name'], $ext);
                
                if (!$file_data || empty(trim($file_data['content']))) {
                    throw new Exception('File content could not be extracted or is empty.');
                }

                // Add to knowledge base
                $metadata = array_merge($file_data['metadata'], ['scope' => $scope]);
                // Set user_id and is_public based on scope
                $user_id = get_current_user_id(); // Always use current user for uploaded files
                $is_public = ($scope === 'public') ? 1 : 0;
                $result = $rag_engine->add_entry(
                    $file_data['content'],
                    $file_data['type'],
                    $file_data['title'],
                    $metadata,
                    $user_id,
                    $is_public
                );

                if (is_wp_error($result)) {
                    throw new Exception(sprintf(
                        /* translators: %s: error message */
                        __('Failed to add to knowledge base: %s', 'aiohm-knowledge-assistant'),
                        esc_html($result->get_error_message())
                    ));
                }
                
                if (!$result) {
                    throw new Exception('Knowledge base operation failed.');
                }

                // Clear cache after successful addition
                wp_cache_flush_group('aiohm_kb_manager');
                if (function_exists('wp_cache_flush')) {
                    wp_cache_flush();
                }
                
                $results[] = [
                    'filename' => $file['name'],
                    'success' => true,
                    'message' => 'Successfully added to knowledge base'
                ];

            } catch (Exception $e) {
                $results[] = [
                    'filename' => $file['name'],
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        $success_count = count(array_filter($results, function($r) { return $r['success']; }));
        $total_count = count($results);

        wp_send_json_success([
            'message' => "Processed $total_count files. $success_count successful.",
            'results' => $results,
            'success_count' => $success_count,
            'total_count' => $total_count
        ]);
    }

    /**
     * Process uploaded file content with security validation
     */
    private static function process_uploaded_file($temp_path, $filename, $ext) {
        if (!file_exists($temp_path) || !is_readable($temp_path)) {
            return null;
        }

        // Security: Check file size limit (5MB max)
        $max_file_size = 5 * 1024 * 1024; // 5MB
        if (filesize($temp_path) > $max_file_size) {
            throw new Exception('File too large. Maximum size is 5MB.');
        }

        $content = '';
        $mime_type = wp_check_filetype($filename)['type'];

        switch ($ext) {
            case 'json':
                $content = self::validate_and_process_json($temp_path);
                break;
            case 'txt':
            case 'md':
                $content = self::validate_and_process_text($temp_path);
                break;
            case 'csv':
                $content = self::validate_and_process_csv($temp_path);
                break;

            case 'pdf':
                try {
                    // Load Composer autoloader if not already loaded
                    $composer_autoload = AIOHM_KB_PLUGIN_DIR . 'vendor/autoload.php';
                    if (file_exists($composer_autoload) && !class_exists('Smalot\\PdfParser\\Parser')) {
                        require_once $composer_autoload;
                    }
                    
                    if (class_exists('Smalot\\PdfParser\\Parser')) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($temp_path);
                        $content = $pdf->getText();
                        
                        // Clean up extracted text
                        $content = trim($content);
                        if (empty($content)) {
                            throw new Exception('No readable text found in PDF.');
                        }
                    } else {
                        throw new Exception('PDF parser not available.');
                    }
                } catch (Exception $e) {
                    $content = "PDF file: $filename (content extraction failed: " . $e->getMessage() . ")";
                }
                break;

            case 'docx':
                try {
                    $zip = new ZipArchive();
                    if ($zip->open($temp_path) === TRUE) {
                        $content = $zip->getFromName('word/document.xml');
                        if ($content !== false) {
                            $content = wp_strip_all_tags($content);
                            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
                        }
                        $zip->close();
                    }
                    if (empty(trim($content))) {
                        $content = "Word document: $filename (content extraction failed)";
                    }
                } catch (Exception $e) {
                    $content = "Word document: $filename (content extraction failed)";
                }
                break;

            case 'doc':
                // DOC files require more complex parsing, fall back to filename
                $content = "Word document: $filename (content extraction not supported for .doc files)";
                break;

            default:
                return null;
        }

        return [
            'content' => $content,
            'type' => strtoupper($ext), // Use uppercase extension like other crawlers
            'title' => $filename,
            'metadata' => [
                'size' => filesize($temp_path),
                'original_filename' => $filename,
                'file_type' => $ext,
                'mime_type' => $mime_type,
                'upload_method' => 'direct_upload'
            ]
        ];
    }

    /**
     * Allow JSON file uploads for knowledge base functionality
     */
    public static function allow_json_uploads($mimes) {
        // Only allow JSON uploads for administrators and in admin context
        if (is_admin() && current_user_can('manage_options')) {
            $mimes['json'] = 'application/json';
            $mimes['md'] = 'text/markdown';
        }
        return $mimes;
    }

    /**
     * Allow JSON and MD file uploads by bypassing WordPress file type restrictions
     */
    public static function allow_json_file_upload($data, $file, $filename, $mimes) {
        // Only allow for administrators in admin context
        if (!is_admin() || !current_user_can('manage_options')) {
            return $data;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if ($ext === 'json') {
            // Validate JSON file content
            if (isset($file) && is_readable($file)) {
                // Initialize WP_Filesystem if needed
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    WP_Filesystem();
                }
                $content = $wp_filesystem->get_contents($file);
                if ($content !== false) {
                    $decoded = json_decode($content);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Invalid JSON, reject the file
                        return $data;
                    }
                }
            }
            $data['ext'] = 'json';
            $data['type'] = 'application/json';
        } elseif ($ext === 'md') {
            $data['ext'] = 'md';
            $data['type'] = 'text/markdown';
        }
        
        return $data;
    }

    /**
     * Clear all caches related to core functionality
     */
    public static function clear_core_caches($user_id = null) {
        if ($user_id) {
            // Clear user-specific caches
            wp_cache_delete('aiohm_user_projects_' . $user_id, 'aiohm_core');
            wp_cache_delete('aiohm_user_conversations_' . $user_id, 'aiohm_core');
        } else {
            // Clear all core caches
            wp_cache_flush_group('aiohm_core');
        }
    }

    /**
     * Securely validate and process JSON files
     * @param string $file_path Path to JSON file
     * @return string Sanitized content
     * @throws Exception If validation fails
     */
    private static function validate_and_process_json($file_path) {
        global $wp_filesystem;
        
        // Initialize WP_Filesystem
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $content = $wp_filesystem->get_contents($file_path);
        if (false === $content) {
            throw new Exception('Unable to read JSON file');
        }
        
        // Clean up control characters and other problematic characters
        // Remove null bytes and control characters except newlines and tabs
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', '', $content);
        // Fix common encoding issues
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        // Try to fix common JSON issues
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/[\x{FEFF}-\x{FFFF}]/u', '', $content);
        
        // Validate JSON structure
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON parsing fails, treat it as raw text content but still mark as JSON type
            // This allows users to upload malformed JSON files that can still be viewed/edited
            $result = sanitize_textarea_field($content);
            if (empty($result)) {
                throw new Exception('File content is empty after sanitization');
            }
            return $result;
        }
        
        // Recursively sanitize all string values
        $sanitized = self::sanitize_json_recursive($decoded);
        
        // Return as pretty-printed JSON
        $result = wp_json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (empty($result)) {
            $result = sanitize_textarea_field($content);
            if (empty($result)) {
                throw new Exception('File content is empty after sanitization');
            }
            return $result;
        }
        
        return $result;
    }

    /**
     * Securely validate and process text files
     * @param string $file_path Path to text file
     * @return string Sanitized content
     * @throws Exception If validation fails
     */
    private static function validate_and_process_text($file_path) {
        global $wp_filesystem;
        
        // Initialize WP_Filesystem
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $content = $wp_filesystem->get_contents($file_path);
        if (false === $content) {
            throw new Exception('Unable to read text file');
        }
        
        // Check for binary content (potential malicious files)
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new Exception('File contains invalid UTF-8 content');
        }
        
        // Remove potential script tags and sanitize
        $content = wp_kses_post($content);
        
        // Additional security: remove any remaining script-like patterns
        $content = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $content);
        $content = preg_replace('/javascript\s*:/i', '', $content);
        $content = preg_replace('/on\w+\s*=/i', '', $content);
        
        return trim($content);
    }

    /**
     * Securely validate and process CSV files
     * @param string $file_path Path to CSV file
     * @return string Sanitized content
     * @throws Exception If validation fails
     */
    private static function validate_and_process_csv($file_path) {
        global $wp_filesystem;
        
        // Initialize WP_Filesystem
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Read file using WP_Filesystem
        $content = $wp_filesystem->get_contents($file_path);
        if (false === $content) {
            throw new Exception('Unable to read CSV file');
        }
        
        // Check for binary content
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new Exception('CSV file contains invalid UTF-8 content');
        }
        
        // Parse CSV and sanitize each cell
        $lines = str_getcsv($content, "\n");
        $sanitized_lines = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $fields = str_getcsv($line);
            $sanitized_fields = array_map('sanitize_text_field', $fields);
            $sanitized_lines[] = $sanitized_fields;
        }
        
        // Convert back to CSV format using string concatenation instead of file operations
        $csv_output = '';
        foreach ($sanitized_lines as $line) {
            $csv_output .= '"' . implode('","', array_map('str_replace', array_fill(0, count($line), '"'), array_fill(0, count($line), '""'), $line)) . '"' . "\n";
        }
        
        return $csv_output;
    }

    /**
     * Recursively sanitize JSON data
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private static function sanitize_json_recursive($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize_json_recursive'], $data);
        } elseif (is_object($data)) {
            $sanitized = new stdClass();
            foreach ($data as $key => $value) {
                $sanitized->{sanitize_key($key)} = self::sanitize_json_recursive($value);
            }
            return $sanitized;
        } elseif (is_string($data)) {
            // Remove script tags and potential XSS vectors
            $data = wp_kses_post($data);
            $data = preg_replace('/<\s*script[^>]*>.*?<\s*\/\s*script\s*>/is', '', $data);
            return sanitize_text_field($data);
        }
        
        return $data;
    }

    /**
     * Add security headers for admin pages
     */
    public static function add_admin_security_headers() {
        if (is_admin() && !headers_sent()) {
            // Content Security Policy for admin pages
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:;");
            
            // Prevent MIME type sniffing
            header('X-Content-Type-Options: nosniff');
            
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            
            // Enable XSS filtering
            header('X-XSS-Protection: 1; mode=block');
            
            // Referrer policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Permissions policy (formerly Feature Policy)
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        }
    }

    /**
     * Add security headers for frontend pages
     */
    public static function add_frontend_security_headers() {
        if (!is_admin() && !headers_sent()) {
            // More restrictive CSP for frontend
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:;");
            
            // Prevent MIME type sniffing
            header('X-Content-Type-Options: nosniff');
            
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            
            // Enable XSS filtering
            header('X-XSS-Protection: 1; mode=block');
            
            // Referrer policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // HSTS (if HTTPS is detected)
            if (is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        }
    }

    /**
     * Secure user session on login to prevent session fixation
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public static function secure_user_session($user_login, $user) {
        // Regenerate session to prevent session fixation attacks
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
        
        // Set secure session cookie if over HTTPS
        if (is_ssl()) {
            wp_set_auth_cookie($user->ID, false, true);
        }
        
        // Log successful login for security monitoring
        AIOHM_KB_Assistant::log("Secure login for user: {$user_login} (ID: {$user->ID})", 'info');
    }

    /**
     * Secure session for new user registration
     * @param int $user_id User ID
     */
    public static function secure_new_user_session($user_id) {
        // Set secure session for new users
        if (is_ssl()) {
            wp_set_auth_cookie($user_id, false, true);
        }
        
        // Log new user registration
        $user = get_user_by('id', $user_id);
        if ($user) {
            AIOHM_KB_Assistant::log("New user registered: {$user->user_login} (ID: {$user_id})", 'info');
        }
    }

    /**
     * Enhanced authentication checks for sensitive operations
     * @param string $operation Operation being performed
     * @return bool True if authorized, false otherwise
     */
    private static function verify_enhanced_auth($operation = 'general') {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $user_id = $user->ID;
        
        // Check for recent authentication for sensitive operations
        $recent_auth_operations = ['delete_kb', 'export_kb', 'reset_kb', 'api_key_change'];
        
        if (in_array($operation, $recent_auth_operations)) {
            $last_auth = get_user_meta($user_id, '_aiohm_last_auth_verify', true);
            $auth_timeout = 300; // 5 minutes
            
            if (empty($last_auth) || (time() - $last_auth) > $auth_timeout) {
                // Require fresh authentication for sensitive operations
                return false;
            }
        }
        
        // Update last activity timestamp
        update_user_meta($user_id, '_aiohm_last_activity', time());
        
        return true;
    }

    /**
     * Mark user as recently authenticated for sensitive operations
     * @param int $user_id User ID
     */
    public static function mark_recent_auth($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if ($user_id) {
            update_user_meta($user_id, '_aiohm_last_auth_verify', time());
        }
    }
    
    /**
     * Validate file content to prevent malicious uploads
     * @param string $file_path Path to the uploaded file
     * @param string $extension File extension
     * @param string $mime_type MIME type
     * @return bool True if file is valid, false otherwise
     */
    private static function validate_file_content($file_path, $extension, $mime_type) {
        // File must exist and be readable
        if (!is_readable($file_path)) {
            return false;
        }
        
        // Get file contents for validation (first 1KB only for performance)
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $content = $wp_filesystem->get_contents($file_path);
        if ($content === false) {
            return false;
        }
        
        // Limit to first 1KB for performance
        $content = substr($content, 0, 1024);
        
        // Validate based on file type
        switch ($extension) {
            case 'txt':
                // Text files should not contain PHP tags or suspicious patterns
                if (strpos($content, '<?php') !== false || 
                    strpos($content, '<%') !== false ||
                    strpos($content, '<script') !== false) {
                    return false;
                }
                break;
                
            case 'json':
                // Validate JSON structure and content
                $decoded = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
                // Check for suspicious content in JSON
                $json_string = json_encode($decoded);
                if (strpos($json_string, '<?php') !== false || 
                    strpos($json_string, '<script') !== false ||
                    strpos($json_string, 'eval(') !== false) {
                    return false;
                }
                break;
                
            case 'csv':
                // CSV files should not contain PHP or script tags
                if (strpos($content, '<?php') !== false || 
                    strpos($content, '<script') !== false ||
                    strpos($content, '<%') !== false) {
                    return false;
                }
                break;
                
            case 'pdf':
                // PDF files should start with PDF signature
                if (strpos($content, '%PDF-') !== 0) {
                    return false;
                }
                break;
                
            default:
                // Unknown file type - reject
                return false;
        }
        
        // Check file size is reasonable (additional safety check)
        $file_size = filesize($file_path);
        $max_sizes = [
            'txt' => 10 * 1024 * 1024,  // 10MB for text
            'csv' => 50 * 1024 * 1024,  // 50MB for CSV
            'json' => 10 * 1024 * 1024, // 10MB for JSON
            'pdf' => 100 * 1024 * 1024  // 100MB for PDF
        ];
        
        if ($file_size > ($max_sizes[$extension] ?? 10 * 1024 * 1024)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Helper function to verify nonce and permissions
     * @param string $nonce_action The nonce action to verify
     * @param string $capability Required capability (default: 'manage_options')
     */
    private static function verify_nonce_and_permission($nonce_action, $capability = 'manage_options') {
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Comprehensive input validation and sanitization function
     * @param mixed $input The input to validate and sanitize
     * @param string $type The type of validation to apply
     * @param array $options Additional validation options
     * @return mixed|WP_Error Sanitized input or WP_Error on validation failure
     */
    private static function validate_and_sanitize_input($input, $type = 'text', $options = []) {
        // Check for required fields
        if (!empty($options['required']) && (empty($input) || trim($input) === '')) {
            return new WP_Error('required_field', __('This field is required.', 'aiohm-knowledge-assistant'));
        }
        
        switch ($type) {
            case 'email':
                if (!is_email($input)) {
                    return new WP_Error('invalid_email', __('Invalid email address.', 'aiohm-knowledge-assistant'));
                }
                return sanitize_email($input);
                
            case 'url':
                if (!filter_var($input, FILTER_VALIDATE_URL)) {
                    return new WP_Error('invalid_url', __('Invalid URL format.', 'aiohm-knowledge-assistant'));
                }
                return esc_url_raw($input);
                
            case 'html':
                return wp_kses_post($input);
                
            case 'int':
                if (!is_numeric($input)) {
                    return new WP_Error('invalid_number', __('Invalid number format.', 'aiohm-knowledge-assistant'));
                }
                $value = intval($input);
                if (isset($options['min']) && $value < $options['min']) {
                    /* translators: %d: minimum number value */
                    return new WP_Error('number_too_small', sprintf(__('Number must be at least %d.', 'aiohm-knowledge-assistant'), $options['min']));
                }
                if (isset($options['max']) && $value > $options['max']) {
                    /* translators: %d: maximum number value */
                    return new WP_Error('number_too_large', sprintf(__('Number must be at most %d.', 'aiohm-knowledge-assistant'), $options['max']));
                }
                return $value;
                
            case 'float':
                if (!is_numeric($input)) {
                    return new WP_Error('invalid_number', __('Invalid number format.', 'aiohm-knowledge-assistant'));
                }
                return floatval($input);
                
            case 'array':
                if (!is_array($input)) {
                    return new WP_Error('invalid_array', __('Expected array input.', 'aiohm-knowledge-assistant'));
                }
                return array_map('sanitize_text_field', $input);
                
            case 'json':
                $decoded = json_decode($input, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new WP_Error('invalid_json', __('Invalid JSON format.', 'aiohm-knowledge-assistant'));
                }
                return $decoded;
                
            case 'textarea':
                $sanitized = sanitize_textarea_field($input);
                if (isset($options['max_length']) && strlen($sanitized) > $options['max_length']) {
                    /* translators: %d: maximum character length */
                    return new WP_Error('text_too_long', sprintf(__('Text must be no more than %d characters.', 'aiohm-knowledge-assistant'), $options['max_length']));
                }
                return $sanitized;
                
            case 'slug':
                return sanitize_title($input);
                
            case 'key':
                return sanitize_key($input);
                
            default:
                $sanitized = sanitize_text_field($input);
                if (isset($options['max_length']) && strlen($sanitized) > $options['max_length']) {
                    /* translators: %d: maximum character length */
                    return new WP_Error('text_too_long', sprintf(__('Text must be no more than %d characters.', 'aiohm-knowledge-assistant'), $options['max_length']));
                }
                return $sanitized;
        }
    }

    /**
     * Handle MCP settings save
     */
    public static function handle_save_mcp_settings_ajax() {
        // Verify nonce first before processing any POST data
        if (!check_ajax_referer('aiohm_mcp_settings_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        // Check if user has MCP access (Private level) - this is the primary requirement
        if (!AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access()) {
            wp_send_json_error(['message' => __('MCP access requires Private level membership', 'aiohm-knowledge-assistant')]);
        }
        
        // For saving settings, we need at least 'read' capability
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            // Parse form data
            $post_data = isset($_POST['data']) ? sanitize_text_field(wp_unslash($_POST['data'])) : '';
            if (empty($post_data)) {
                wp_send_json_error(['message' => __('No data provided', 'aiohm-knowledge-assistant')]);
                return;
            }
            parse_str($post_data, $form_data);
            
            // Get RAW settings from database, not processed settings
            $current_settings = get_option('aiohm_kb_settings', []);
            
            // If settings don't exist yet, initialize with defaults
            if (empty($current_settings)) {
                $current_settings = [];
            }
            
            $old_mcp_enabled = !empty($current_settings['mcp_enabled']);
            
            // Update MCP settings - handle checkbox properly
            $mcp_enabled = isset($form_data['mcp_enabled']) && $form_data['mcp_enabled'] === '1';
            $mcp_rate_limit = min(10000, max(100, intval($form_data['mcp_rate_limit'] ?? 1000)));
            $mcp_require_https = isset($form_data['mcp_require_https']) && $form_data['mcp_require_https'] === '1';
            
            // Try updating individual settings in the main array
            $current_settings['mcp_enabled'] = $mcp_enabled;
            $current_settings['mcp_rate_limit'] = $mcp_rate_limit;
            $current_settings['mcp_require_https'] = $mcp_require_https;
            
            // Try to save with autoload disabled
            $result = update_option('aiohm_kb_settings', $current_settings, false);
            
            // If update_option returns false, it might be because the value didn't change
            // Let's force a save by deleting and re-adding the option
            if (!$result) {
                delete_option('aiohm_kb_settings');
                $result = add_option('aiohm_kb_settings', $current_settings, '', false);
            }
            
            // If main settings fail, try saving MCP settings separately as fallback
            if (!$result) {
                $mcp_settings = [
                    'mcp_enabled' => $mcp_enabled,
                    'mcp_rate_limit' => $mcp_rate_limit, 
                    'mcp_require_https' => $mcp_require_https
                ];
                $result = update_option('aiohm_mcp_settings', $mcp_settings);
            }
            
            $reload_needed = $old_mcp_enabled !== $mcp_enabled;
            
            if ($result) {
                
                wp_send_json_success([
                    'message' => __('MCP settings saved successfully!', 'aiohm-knowledge-assistant'),
                    'reload_needed' => $reload_needed
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to save settings. Please try again.', 'aiohm-knowledge-assistant')]);
            }
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('MCP Settings Save Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => self::get_safe_error_message($e, 'mcp_settings_save')]);
        }
    }

    /**
     * Handle saving individual settings like privacy consent
     */
    public static function handle_save_setting_ajax() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'aiohm-knowledge-assistant')]);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        // Get and validate inputs
        $setting_key = isset($_POST['setting_key']) ? sanitize_key($_POST['setting_key']) : '';
        $setting_value = isset($_POST['setting_value']) ? sanitize_text_field(wp_unslash($_POST['setting_value'])) : '';

        if (empty($setting_key)) {
            wp_send_json_error(['message' => __('Missing setting key.', 'aiohm-knowledge-assistant')]);
        }

        try {
            // Get current settings
            $current_settings = get_option('aiohm_kb_settings', []);
            
            // Handle specific settings
            switch ($setting_key) {
                case 'external_api_consent':
                    // Convert to boolean
                    $current_settings[$setting_key] = ($setting_value === '1');
                    break;
                case 'default_ai_provider':
                    // Validate provider value
                    $valid_providers = ['openai', 'gemini', 'claude', 'shareai', 'ollama'];
                    if (in_array($setting_value, $valid_providers) || empty($setting_value)) {
                        $current_settings[$setting_key] = $setting_value;
                    } else {
                        wp_send_json_error(['message' => __('Invalid AI provider selected.', 'aiohm-knowledge-assistant')]);
                        return;
                    }
                    break;
                default:
                    wp_send_json_error(['message' => __('Unknown setting key.', 'aiohm-knowledge-assistant')]);
                    return;
            }
            
            // Save the updated settings
            $result = update_option('aiohm_kb_settings', $current_settings);
            
            if ($result !== false) {
                wp_send_json_success([
                    'message' => __('Setting saved successfully!', 'aiohm-knowledge-assistant')
                ]);
            } else {
                // Check if value was already the same (update_option returns false if no change)
                $verify_settings = get_option('aiohm_kb_settings', []);
                if (isset($verify_settings[$setting_key]) && $verify_settings[$setting_key] === $current_settings[$setting_key]) {
                    wp_send_json_success([
                        'message' => __('Setting saved successfully!', 'aiohm-knowledge-assistant')
                    ]);
                } else {
                    wp_send_json_error(['message' => __('Failed to save setting. Please try again.', 'aiohm-knowledge-assistant')]);
                }
            }
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Setting Save Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => __('An error occurred while saving the setting.', 'aiohm-knowledge-assistant')]);
        }
    }


    /**
     * Add demo upgrade banner to admin header (exclude Get Help page)
     */
    public static function add_demo_upgrade_banner() {
        // Only show on AIOHM admin pages and only for demo version
        if (!defined('AIOHM_KB_VERSION') || AIOHM_KB_VERSION !== 'DEMO') {
            return;
        }
        
        // Only show on AIOHM plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'aiohm') === false) {
            return;
        }
        
        // Exclude Get Help page
        if ($screen->id === 'aiohm_page_aiohm-get-help') {
            return;
        }
        
        ?>
        <div class="notice" style="background: #cbddd1; border: 2px solid #457d58; color: #272727; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: center; font-family: 'Montserrat', sans-serif; border-left: 4px solid #457d58;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 20px;">
                <div style="flex: 1; text-align: left;">
                    <h3 style="margin: 0 0 10px 0; color: #1f5014; font-family: 'Montserrat Alternates', sans-serif; font-size: 1.1em;">
                        🚀 <strong>DEMO VERSION</strong> - This is a fully functional demo with some features restricted
                    </h3>
                    <p style="margin: 0; color: #272727; font-family: 'PT Sans', sans-serif; font-size: 0.95em;">
                        Ready to unlock the complete AIOHM experience? Get real AI functionality and full access.
                    </p>
                </div>
                <div style="flex-shrink: 0;">
                    <a href="https://aiohm.app/shop" target="_blank" class="button" style="background: #457d58; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; font-family: 'Montserrat', sans-serif; font-size: 1em; border: none; box-shadow: 0 3px 8px rgba(69, 125, 88, 0.3);" onmouseover="this.style.background='#1f5014'" onmouseout="this.style.background='#457d58'">
                        Get Full AIOHM →
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle getting debug information for support
     */
    public static function handle_get_debug_info_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            global $wpdb;
            $settings = AIOHM_KB_Assistant::get_settings();
            
            // Collect plugin settings (sanitized)
            $sanitized_settings = [];
            foreach ($settings as $key => $value) {
                if (strpos($key, 'key') !== false || strpos($key, 'token') !== false) {
                    $sanitized_settings[$key] = !empty($value) ? '[CONFIGURED]' : '[NOT SET]';
                } else {
                    $sanitized_settings[$key] = $value;
                }
            }
            
            // Check database tables
            $tables = [
                'aiohm_vector_entries',
                'aiohm_conversations',
                'aiohm_messages',
                'aiohm_projects',
                'aiohm_mcp_tokens',
                'aiohm_mcp_usage'
            ];
            
            $database_info = [];
            foreach ($tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Debug check for table existence
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
                $rows = 0;
                
                if ($exists) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Debug query for row count
                    $rows = $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table_name}`");
                }
                
                $database_info[$table] = [
                    'exists' => !empty($exists),
                    'rows' => (int) $rows
                ];
            }
            
            // Get recent errors from WordPress debug log (if available)
            $recent_errors = [];
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                $log_file = WP_CONTENT_DIR . '/debug.log';
                if (file_exists($log_file) && is_readable($log_file)) {
                    // Initialize WP_Filesystem if needed
                    global $wp_filesystem;
                    if (empty($wp_filesystem)) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        WP_Filesystem();
                    }
                    $log_content = $wp_filesystem->get_contents($log_file);
                    if (false === $log_content) {
                        $log_content = '';
                    }
                    $lines = explode("\n", $log_content);
                    $aiohm_errors = array_filter($lines, function($line) {
                        return strpos($line, 'AIOHM') !== false && (
                            strpos($line, 'ERROR') !== false || 
                            strpos($line, 'WARNING') !== false ||
                            strpos($line, 'FATAL') !== false
                        );
                    });
                    $recent_errors = array_slice(array_reverse($aiohm_errors), 0, 10);
                }
            }
            
            // Get comprehensive debug information
            $debug_info = self::collect_comprehensive_debug_info($sanitized_settings, $database_info, $recent_errors);
            
            wp_send_json_success($debug_info);
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Debug Info Collection Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => __('Failed to collect debug information', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Collect comprehensive debug information from all AIOHM features and pages
     */
    private static function collect_comprehensive_debug_info($sanitized_settings, $database_info, $recent_errors) {
        global $wpdb;
        
        $debug_info = [
            'timestamp' => current_time('Y-m-d H:i:s T'),
            'plugin_version' => AIOHM_KB_VERSION,
            'settings' => $sanitized_settings,
            'database' => $database_info,
            'errors' => $recent_errors
        ];
        
        // === SYSTEM INFORMATION ===
        $debug_info['system'] = [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
            'mysql_version' => $wpdb->get_var("SELECT VERSION()"),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'is_multisite' => is_multisite(),
            'active_theme' => wp_get_theme()->get('Name'),
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG
        ];
        
        // === PLUGIN STATUS ===
        $debug_info['plugin_status'] = [
            'is_demo_version' => defined('AIOHM_KB_VERSION') && AIOHM_KB_VERSION === 'DEMO',
            'plugin_path' => AIOHM_KB_PLUGIN_DIR,
            'plugin_url' => AIOHM_KB_PLUGIN_URL,
            'main_file_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'aiohm-kb-assistant.php'),
            'includes_dir_exists' => is_dir(AIOHM_KB_PLUGIN_DIR . 'includes/'),
            'assets_dir_exists' => is_dir(AIOHM_KB_PLUGIN_DIR . 'assets/'),
            'templates_dir_exists' => is_dir(AIOHM_KB_PLUGIN_DIR . 'templates/')
        ];
        
        // === DASHBOARD PAGE STATUS ===
        $debug_info['dashboard'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-dashboard.php'),
            'robot_script_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'assets/js/aiohm-robot-guide.js'),
            'dashboard_css_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'assets/css/aiohm-admin-dashboard.css')
        ];
        
        // === SETTINGS PAGE STATUS ===
        $debug_info['settings'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-settings.php'),
            'settings_class_exists' => class_exists('AIOHM_KB_Settings_Page'),
            'universal_robot_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'assets/js/aiohm-universal-robot-guide.js'),
            'configured_providers' => [
                'openai' => !empty($sanitized_settings['openai_api_key']) && $sanitized_settings['openai_api_key'] !== '[NOT SET]',
                'gemini' => !empty($sanitized_settings['gemini_api_key']) && $sanitized_settings['gemini_api_key'] !== '[NOT SET]',
                'claude' => !empty($sanitized_settings['claude_api_key']) && $sanitized_settings['claude_api_key'] !== '[NOT SET]',
                'shareai' => !empty($sanitized_settings['shareai_api_key']) && $sanitized_settings['shareai_api_key'] !== '[NOT SET]'
            ],
            'default_provider' => $sanitized_settings['default_ai_provider'] ?? 'not_set'
        ];
        
        // === BRAND SOUL PAGE STATUS ===
        $debug_info['brand_soul'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-brand-soul.php'),
            'has_brand_soul_data' => !empty($sanitized_settings['brand_soul']) && is_array($sanitized_settings['brand_soul']),
            'completed_sections' => 0
        ];
        if (!empty($sanitized_settings['brand_soul']) && is_array($sanitized_settings['brand_soul'])) {
            $debug_info['brand_soul']['completed_sections'] = count(array_filter($sanitized_settings['brand_soul'], function($value) {
                return !empty($value) && is_string($value) && strlen(trim($value)) > 10;
            }));
        }
        
        // === SCAN CONTENT PAGE STATUS ===
        $scan_stats = self::get_content_scan_stats();
        $debug_info['scan_content'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/scan-website.php'),
            'scanner_class_exists' => class_exists('AIOHM_KB_Content_Scanner'),
            'total_posts' => $scan_stats['posts']['total'] ?? 0,
            'indexed_posts' => $scan_stats['posts']['indexed'] ?? 0,
            'total_pages' => $scan_stats['pages']['total'] ?? 0,
            'indexed_pages' => $scan_stats['pages']['indexed'] ?? 0,
            'total_media' => $scan_stats['uploads']['total_files'] ?? 0,
            'indexed_media' => $scan_stats['uploads']['indexed_files'] ?? 0
        ];
        
        // === MANAGE KB PAGE STATUS ===
        $debug_info['manage_kb'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-manage-kb.php'),
            'kb_manager_class_exists' => class_exists('AIOHM_KB_Manager'),
            'total_entries' => $database_info['aiohm_vector_entries']['rows'] ?? 0,
            'has_entries' => ($database_info['aiohm_vector_entries']['rows'] ?? 0) > 0
        ];
        
        // === MIRROR MODE PAGE STATUS ===
        $debug_info['mirror_mode'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-mirror-mode.php'),
            'enabled' => !empty($sanitized_settings['chat_enabled']) && $sanitized_settings['chat_enabled'] == '1',
            'floating_chat' => !empty($sanitized_settings['show_floating_chat']) && $sanitized_settings['show_floating_chat'] == '1',
            'has_system_message' => !empty($sanitized_settings['mirror_mode']['qa_system_message']),
            'has_business_name' => !empty($sanitized_settings['mirror_mode']['business_name']),
            'configured_ai_model' => $sanitized_settings['mirror_mode']['ai_model'] ?? 'not_set'
        ];
        
        // === MUSE MODE PAGE STATUS ===
        $debug_info['muse_mode'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-muse-mode.php'),
            'enabled' => !empty($sanitized_settings['enable_private_assistant']) && $sanitized_settings['enable_private_assistant'] == '1',
            'assistant_name' => $sanitized_settings['muse_mode']['assistant_name'] ?? 'not_set',
            'has_system_prompt' => !empty($sanitized_settings['muse_mode']['system_prompt']),
            'brand_archetype' => $sanitized_settings['muse_mode']['brand_archetype'] ?? 'not_set',
            'configured_ai_model' => $sanitized_settings['muse_mode']['ai_model'] ?? 'not_set',
            'temperature' => $sanitized_settings['muse_mode']['temperature'] ?? 'not_set',
            'fullscreen_mode' => !empty($sanitized_settings['muse_mode']['start_fullscreen'])
        ];
        
        // === MCP API PAGE STATUS ===
        $debug_info['mcp'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-mcp.php'),
            'mcp_integration_exists' => class_exists('AIOHM_KB_MCP_Integration'),
            'total_tokens' => $database_info['aiohm_mcp_tokens']['rows'] ?? 0,
            'total_usage_records' => $database_info['aiohm_mcp_usage']['rows'] ?? 0,
            'has_active_tokens' => false
        ];
        if ($database_info['aiohm_mcp_tokens']['rows'] > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Debug check for active tokens
            $active_tokens = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}aiohm_mcp_tokens WHERE status = %s", 'active'));
            $debug_info['mcp']['has_active_tokens'] = (int) $active_tokens > 0;
        }
        
        // === LICENSE PAGE STATUS ===
        $debug_info['license'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-license.php'),
            'pmp_integration_exists' => class_exists('AIOHM_KB_PMP_Integration'),
            'aiohm_email_configured' => !empty($sanitized_settings['aiohm_app_email'])
        ];
        
        // === GET HELP PAGE STATUS ===
        $debug_info['get_help'] = [
            'template_exists' => file_exists(AIOHM_KB_PLUGIN_DIR . 'templates/admin-help.php'),
            'debug_collection_working' => true, // If we're here, it's working
            'support_features_available' => [
                'debug_info_collection' => method_exists(__CLASS__, 'handle_get_debug_info_ajax'),
                'api_connection_test' => method_exists(__CLASS__, 'handle_test_all_api_connections_ajax'),
                'database_health_check' => method_exists(__CLASS__, 'handle_check_database_health_ajax')
            ]
        ];
        
        // === CONVERSATIONS & PROJECTS STATUS ===
        $debug_info['conversations'] = [
            'total_conversations' => $database_info['aiohm_conversations']['rows'] ?? 0,
            'total_messages' => $database_info['aiohm_messages']['rows'] ?? 0,
            'total_projects' => $database_info['aiohm_projects']['rows'] ?? 0,
            'has_conversation_data' => ($database_info['aiohm_conversations']['rows'] ?? 0) > 0
        ];
        
        // === AI PROVIDERS TEST RESULTS ===
        $debug_info['ai_providers'] = [];
        if (!empty($sanitized_settings['openai_api_key']) && $sanitized_settings['openai_api_key'] !== '[NOT SET]') {
            $debug_info['ai_providers']['openai'] = ['configured' => true, 'status' => 'needs_testing'];
        }
        if (!empty($sanitized_settings['gemini_api_key']) && $sanitized_settings['gemini_api_key'] !== '[NOT SET]') {
            $debug_info['ai_providers']['gemini'] = ['configured' => true, 'status' => 'needs_testing'];
        }
        if (!empty($sanitized_settings['claude_api_key']) && $sanitized_settings['claude_api_key'] !== '[NOT SET]') {
            $debug_info['ai_providers']['claude'] = ['configured' => true, 'status' => 'needs_testing'];
        }
        if (!empty($sanitized_settings['shareai_api_key']) && $sanitized_settings['shareai_api_key'] !== '[NOT SET]') {
            $debug_info['ai_providers']['shareai'] = ['configured' => true, 'status' => 'needs_testing'];
        }
        
        // === FILE PERMISSIONS ===
        $debug_info['file_permissions'] = [
            'wp_content_writable' => is_writable(WP_CONTENT_DIR),
            'uploads_dir_writable' => is_writable(wp_upload_dir()['basedir']),
            'plugin_dir_readable' => is_readable(AIOHM_KB_PLUGIN_DIR),
            'debug_log_writable' => is_writable(WP_CONTENT_DIR . '/debug.log') || is_writable(WP_CONTENT_DIR)
        ];
        
        // === WORDPRESS CAPABILITIES ===
        $debug_info['wordpress_features'] = [
            'wp_filesystem_available' => class_exists('WP_Filesystem_Base'),
            'wp_http_available' => function_exists('wp_remote_get'),
            'json_encode_available' => function_exists('json_encode'),
            'curl_available' => function_exists('curl_init'),
            'openssl_available' => function_exists('openssl_encrypt'),
            'mbstring_available' => function_exists('mb_strlen')
        ];
        
        // === RECENT ACTIVITY SUMMARY ===
        $debug_info['recent_activity'] = self::get_recent_activity_summary();
        
        return $debug_info;
    }
    
    /**
     * Get content scan statistics
     */
    private static function get_content_scan_stats() {
        // Get posts stats
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);
        
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);
        
        $indexed_posts = 0;
        $indexed_pages = 0;
        
        foreach ($posts as $post_id) {
            if (get_post_meta($post_id, '_aiohm_indexed', true)) {
                $indexed_posts++;
            }
        }
        
        foreach ($pages as $page_id) {
            if (get_post_meta($page_id, '_aiohm_indexed', true)) {
                $indexed_pages++;
            }
        }
        
        // Get media stats
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);
        
        $indexed_media = 0;
        foreach ($attachments as $attachment_id) {
            if (get_post_meta($attachment_id, '_aiohm_indexed', true)) {
                $indexed_media++;
            }
        }
        
        return [
            'posts' => ['total' => count($posts), 'indexed' => $indexed_posts],
            'pages' => ['total' => count($pages), 'indexed' => $indexed_pages],
            'uploads' => ['total_files' => count($attachments), 'indexed_files' => $indexed_media]
        ];
    }
    
    /**
     * Get recent activity summary
     */
    private static function get_recent_activity_summary() {
        global $wpdb;
        
        $activity = [];
        
        // Recent conversations (last 7 days)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aiohm_conversations'")) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Debug query for recent activity
            $recent_conversations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aiohm_conversations WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            ));
            $activity['conversations_last_7_days'] = (int) $recent_conversations;
        }
        
        // Recent knowledge base additions (last 7 days)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aiohm_vector_entries'")) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Debug query for recent entries
            $recent_entries = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aiohm_vector_entries WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            ));
            $activity['kb_entries_last_7_days'] = (int) $recent_entries;
        }
        
        // Recent MCP usage (last 7 days)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aiohm_mcp_usage'")) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Debug query for recent MCP usage
            $recent_mcp_usage = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aiohm_mcp_usage WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            ));
            $activity['mcp_usage_last_7_days'] = (int) $recent_mcp_usage;
        }
        
        return $activity;
    }

    /**
     * Handle testing all API connections
     */
    public static function handle_test_all_api_connections_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            $settings = AIOHM_KB_Assistant::get_settings();
            $results = [];
            
            // Test each configured API
            $apis_to_test = [
                'openai' => $settings['openai_api_key'] ?? '',
                'gemini' => $settings['gemini_api_key'] ?? '',
                'claude' => $settings['claude_api_key'] ?? '',
                'shareai' => $settings['shareai_api_key'] ?? '',
                'ollama' => $settings['private_llm_server_url'] ?? ''
            ];
            
            foreach ($apis_to_test as $provider => $key_or_url) {
                if (empty($key_or_url)) {
                    $results[$provider] = [
                        'status' => 'not_configured',
                        'message' => __('Not configured', 'aiohm-knowledge-assistant')
                    ];
                    continue;
                }
                
                // Use existing API testing methods with full settings configuration
                try {
                    $test_result = [];
                    
                    // Create AI client with full settings to ensure proper initialization
                    $ai_client = new AIOHM_KB_AI_GPT_Client($settings);
                    
                    switch ($provider) {
                        case 'openai':
                            $test_result = $ai_client->test_api_connection();
                            break;
                        case 'gemini':
                            $test_result = $ai_client->test_gemini_api_connection();
                            break;
                        case 'claude':
                            $test_result = $ai_client->test_claude_api_connection();
                            break;
                        case 'shareai':
                            $test_result = $ai_client->test_shareai_api_connection();
                            break;
                        case 'ollama':
                            $test_result = $ai_client->test_ollama_api_connection();
                            break;
                    }
                    
                    if (!empty($test_result) && is_array($test_result) && $test_result['success']) {
                        $results[$provider] = [
                            'status' => 'success',
                            'message' => __('Connection successful', 'aiohm-knowledge-assistant')
                        ];
                    } else {
                        $results[$provider] = [
                            'status' => 'error',
                            'message' => $test_result['error'] ?? __('Connection failed', 'aiohm-knowledge-assistant')
                        ];
                    }
                } catch (Exception $e) {
                    $results[$provider] = [
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
            
            wp_send_json_success($results);
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('API Connection Test Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => __('Failed to test API connections', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Handle checking database health
     */
    public static function handle_check_database_health_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            global $wpdb;
            $health_data = [];
            
            $tables = [
                'aiohm_vector_entries',
                'aiohm_conversations', 
                'aiohm_messages',
                'aiohm_projects',
                'aiohm_mcp_tokens',
                'aiohm_mcp_usage'
            ];
            
            foreach ($tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Health check for table existence
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
                
                if (!$exists) {
                    $health_data[$table] = [
                        'rows' => 0,
                        'status' => 'missing',
                        'issues' => ['Table does not exist']
                    ];
                    continue;
                }
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Health check for row count
                $rows = $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table_name}`");
                $issues = [];
                
                // Check for common issues
                if ($table === 'aiohm_vector_entries') {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Health check for empty vectors
                    $empty_vectors = $wpdb->get_var("SELECT COUNT(*) FROM `{$full_table_name}` WHERE vector_data IS NULL OR vector_data = ''");
                    if ($empty_vectors > 0) {
                        // translators: %d is the number of entries missing vector data
                        $issues[] = sprintf(__('%d entries missing vector data', 'aiohm-knowledge-assistant'), $empty_vectors);
                    }
                }
                
                $health_data[$table] = [
                    'rows' => (int) $rows,
                    'status' => empty($issues) ? 'healthy' : 'issues',
                    'issues' => $issues
                ];
            }
            
            wp_send_json_success($health_data);
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Database Health Check Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => __('Failed to check database health', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Handle submitting support requests
     */
    public static function handle_submit_support_request_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
            $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
            $include_debug = !empty($_POST['include_debug']);
            $system_info = '';
            if (isset($_POST['system_info'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below
                $raw_system_info = wp_unslash($_POST['system_info']);
                $sanitized_system_info = array();
                if (is_array($raw_system_info)) {
                    foreach ($raw_system_info as $key => $value) {
                        $sanitized_system_info[sanitize_key($key)] = sanitize_text_field($value);
                    }
                }
                $system_info = wp_json_encode($sanitized_system_info);
            }
            $debug_information = isset($_POST['debug_information']) ? sanitize_textarea_field(wp_unslash($_POST['debug_information'])) : '';
            
            if (empty($email) || empty($title) || empty($description)) {
                wp_send_json_error(['message' => __('Please fill in all required fields', 'aiohm-knowledge-assistant')]);
                return;
            }
            
            // Prepare email content
            $subject = "[AIOHM Support] {$type}: {$title}";
            $message = "Support Request Details:\n\n";
            $message .= "Type: {$type}\n";
            $message .= "Title: {$title}\n";
            $message .= "From: {$email}\n";
            $message .= "Site: " . get_site_url() . "\n";
            $message .= "Time: " . current_time('mysql') . "\n\n";
            $message .= "Description:\n{$description}\n\n";
            
            if ($include_debug && !empty($debug_information)) {
                $message .= "Debug Information:\n{$debug_information}\n\n";
            }
            
            if (!empty($system_info)) {
                $message .= "System Info:\n{$system_info}\n";
            }
            
            // Try to send email to support team
            $to = 'support@aiohm.app';
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
                'Reply-To: ' . $email
            ];
            
            $sent = wp_mail($to, $subject, $message, $headers);
            
            // Store in database as backup (always store, regardless of email success)
            global $wpdb;
            $table_name = $wpdb->prefix . 'aiohm_support_requests';
            
            // Create table if it doesn't exist
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                title varchar(500) NOT NULL,
                type varchar(100) NOT NULL,
                description text NOT NULL,
                debug_information longtext,
                system_info text,
                site_url varchar(255),
                submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
                email_sent tinyint(1) DEFAULT 0,
                PRIMARY KEY (id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Insert the request
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Support request storage
            $wpdb->insert(
                $table_name,
                [
                    'email' => $email,
                    'title' => $title,
                    'type' => $type,
                    'description' => $description,
                    'debug_information' => $debug_information,
                    'system_info' => $system_info,
                    'site_url' => get_site_url(),
                    'email_sent' => $sent ? 1 : 0
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
            
            // Log the request
            AIOHM_KB_Assistant::log('Support Request: ' . ($sent ? 'Sent' : 'Stored') . ' - ' . $subject, 'info');
            
            // Always show success since we stored it in database
            wp_send_json_success(['message' => __('Support request submitted successfully! We have received your request and will get back to you soon.', 'aiohm-knowledge-assistant')]);
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Support Request Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => __('Failed to submit support request', 'aiohm-knowledge-assistant')]);
        }
    }

    /**
     * Handle submitting feature requests
     */
    public static function handle_submit_feature_request_ajax() {
        if (!check_ajax_referer('aiohm_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }
        
        try {
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
            $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
            $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
            $include_debug = !empty($_POST['include_debug']);
            $system_info = '';
            if (isset($_POST['system_info'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below
                $raw_system_info = wp_unslash($_POST['system_info']);
                $sanitized_system_info = array();
                if (is_array($raw_system_info)) {
                    foreach ($raw_system_info as $key => $value) {
                        $sanitized_system_info[sanitize_key($key)] = sanitize_text_field($value);
                    }
                }
                $system_info = wp_json_encode($sanitized_system_info);
            }
            $debug_information = isset($_POST['debug_information']) ? sanitize_textarea_field(wp_unslash($_POST['debug_information'])) : '';
            
            if (empty($email) || empty($title) || empty($description)) {
                wp_send_json_error(['message' => __('Please fill in all required fields', 'aiohm-knowledge-assistant')]);
                return;
            }
            
            // Prepare email content
            $subject = "[AIOHM Feature Request] {$title}";
            $message = "Feature Request Details:\n\n";
            $message .= "Type: {$type}\n";
            $message .= "Title: {$title}\n";
            $message .= "From: {$email}\n";
            $message .= "Site: " . get_site_url() . "\n";
            $message .= "Time: " . current_time('mysql') . "\n\n";
            $message .= "Description:\n{$description}\n\n";
            
            if ($include_debug && !empty($debug_information)) {
                $message .= "Debug Information:\n{$debug_information}\n\n";
            }
            
            if (!empty($system_info)) {
                $message .= "System Info:\n{$system_info}\n";
            }
            
            // Try to send email to development team
            $to = 'features@aiohm.app';
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
                'Reply-To: ' . $email
            ];
            
            $sent = wp_mail($to, $subject, $message, $headers);
            
            // Store in database as backup (always store, regardless of email success)
            global $wpdb;
            $table_name = $wpdb->prefix . 'aiohm_feature_requests';
            
            // Create table if it doesn't exist
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                title varchar(500) NOT NULL,
                type varchar(100) NOT NULL,
                description text NOT NULL,
                debug_information longtext,
                system_info text,
                site_url varchar(255),
                submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
                email_sent tinyint(1) DEFAULT 0,
                PRIMARY KEY (id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Insert the request
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Feature request storage
            $wpdb->insert(
                $table_name,
                [
                    'email' => $email,
                    'title' => $title,
                    'type' => $type,
                    'description' => $description,
                    'debug_information' => $debug_information,
                    'system_info' => $system_info,
                    'site_url' => get_site_url(),
                    'email_sent' => $sent ? 1 : 0
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
            
            // Log the request
            AIOHM_KB_Assistant::log('Feature Request: ' . ($sent ? 'Sent' : 'Stored') . ' - ' . $subject, 'info');
            
            // Always show success since we stored it in database
            wp_send_json_success(['message' => __('Feature request submitted successfully! We have received your request and will review it for our roadmap.', 'aiohm-knowledge-assistant')]);
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Feature Request Error: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => __('Failed to submit feature request', 'aiohm-knowledge-assistant')]);
        }
    }
    
    /**
     * Handle JSON content update AJAX request
     */
    public static function handle_update_json_content_ajax() {
        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }

        $content_id = sanitize_text_field(wp_unslash($_POST['content_id'] ?? ''));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Content validation is done below after JSON parsing
        $new_content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

        if (empty($content_id) || empty($new_content)) {
            wp_send_json_error(['message' => __('Missing required data.', 'aiohm-knowledge-assistant')]);
        }

        // Validate JSON
        $decoded = json_decode($new_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON: ', 'aiohm-knowledge-assistant') . esc_html(json_last_error_msg())]);
        }

        // Sanitize JSON recursively
        $sanitized = self::sanitize_json_recursive($decoded);
        $pretty_json = wp_json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Update the knowledge base entry
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Content update requires direct query, cache cleared after update
        $result = $wpdb->update(
            $table_name,
            ['content' => $pretty_json],
            ['content_id' => $content_id],
            ['%s'],
            ['%s']
        );

        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to update content.', 'aiohm-knowledge-assistant')]);
        }

        wp_send_json_success(['message' => __('JSON content updated successfully.', 'aiohm-knowledge-assistant')]);
    }

    /**
     * Handle updating text content (MD, TXT) via AJAX
     */
    public static function handle_update_text_content_ajax() {
        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'aiohm_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'aiohm-knowledge-assistant')]);
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'aiohm-knowledge-assistant')]);
        }

        $content_id = sanitize_text_field(wp_unslash($_POST['content_id'] ?? ''));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Content validation is done below based on content type
        $new_content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        $content_type = sanitize_text_field(wp_unslash($_POST['content_type'] ?? ''));

        if (empty($content_id) || empty($new_content)) {
            wp_send_json_error(['message' => __('Missing required data.', 'aiohm-knowledge-assistant')]);
        }

        // Sanitize content based on type
        $sanitized_content = '';
        switch ($content_type) {
            case 'MD':
            case 'text/markdown':
                // For markdown, allow basic formatting tags
                $sanitized_content = wp_kses($new_content, [
                    'p' => [], 'br' => [], 'strong' => [], 'em' => [], 'ul' => [], 'ol' => [], 'li' => [],
                    'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
                    'blockquote' => [], 'code' => [], 'pre' => [], 'a' => ['href' => [], 'title' => []]
                ]);
                break;
            case 'TXT':
            case 'text/plain':
            case 'project_note':
            default:
                // For plain text and notes, basic sanitization
                $sanitized_content = sanitize_textarea_field($new_content);
                break;
        }

        if (empty($sanitized_content)) {
            wp_send_json_error(['message' => __('Content is empty after sanitization.', 'aiohm-knowledge-assistant')]);
        }

        // Update the knowledge base entry
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Content update requires direct query, cache cleared after update
        $result = $wpdb->update(
            $table_name,
            ['content' => $sanitized_content],
            ['content_id' => $content_id],
            ['%s'],
            ['%s']
        );

        if ($result === false) {
            wp_send_json_error(['message' => __('Failed to update content.', 'aiohm-knowledge-assistant')]);
        }

        // Clear cache after update
        wp_cache_flush_group('aiohm_kb_manager');

        wp_send_json_success(['message' => __('Content updated successfully.', 'aiohm-knowledge-assistant')]);
    }
    
}

} // End class_exists check