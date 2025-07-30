<?php
/**
 * Plugin Name: AIOHM Knowledge Assistant
 * Plugin URI:  https://aiohm.app/
 * Description: AIOHM turns WordPress into an AI hub with Muse (Private) & Mirror (Public) Modes, brand voice alignment, and MCP for connected AI workflows.
 * Version:     1.2.9
 * Author:      OHM Events Agency
 * Author URI:  https://ohm.events
 * Text Domain: aiohm-knowledge-assistant
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('AIOHM_KB_VERSION', '1.2.9');
define('AIOHM_KB_PLUGIN_FILE', __FILE__);
define('AIOHM_KB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIOHM_KB_INCLUDES_DIR', AIOHM_KB_PLUGIN_DIR . 'includes/');
define('AIOHM_KB_PLUGIN_URL', plugin_dir_url(__FILE__));


define('AIOHM_KB_SCHEDULED_SCAN_HOOK', 'aiohm_scheduled_scan');

class AIOHM_KB_Assistant {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        register_activation_hook(AIOHM_KB_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(AIOHM_KB_PLUGIN_FILE, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'load_dependencies'));
        add_action('init', array($this, 'init_plugin'));
        add_filter('plugin_action_links_' . plugin_basename(AIOHM_KB_PLUGIN_FILE), array($this, 'add_settings_link'));
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
        add_action(AIOHM_KB_SCHEDULED_SCAN_HOOK, array($this, 'run_scheduled_scan'));
        add_action('update_option_aiohm_kb_settings', array($this, 'handle_scan_schedule_change'), 10, 2);
    }
    
    public function load_dependencies() {
        $files = [
            'rag-engine.php',
            'ai-gpt-client.php',
            'user-functions.php',
            'crawler-site.php', 
            'crawler-uploads.php',
            'aiohm-kb-manager.php',
            'api-client-app.php',
            'chat-box.php',
            'pmpro-integration.php',
            'pdf-library-loader.php',
            'class-enhanced-pdf.php',
            
            // Core Initializer (which depends on the files above)
            'core-init.php',

            // Admin Pages and Shortcodes (which depend on core-init and other classes)
            'settings-page.php', 
            'shortcode-chat.php', 
            'shortcode-search.php', 
            'shortcode-private-assistant.php', 
            'frontend-widget.php',
            'mcp-handler.php',
        ];
        
        foreach ($files as $file) {
            if (file_exists(AIOHM_KB_INCLUDES_DIR . $file)) { 
                require_once AIOHM_KB_INCLUDES_DIR . $file; 
            }
        }
    }
    
    public function init_plugin() {
        AIOHM_KB_Core_Init::init();
        AIOHM_KB_Settings_Page::init();
        AIOHM_KB_Shortcode_Chat::init();
        AIOHM_KB_Shortcode_Search::init();
        AIOHM_KB_Shortcode_Private_Assistant::init();
        AIOHM_KB_Frontend_Widget::init();
        AIOHM_KB_PMP_Integration::init();
        AIOHM_KB_MCP_Handler::init();
    }
    
    public function activate() {
        // Check WordPress version requirement for %i placeholder support
        global $wp_version;
        if (version_compare($wp_version, '6.2', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('AIOHM Knowledge Assistant requires WordPress 6.2 or higher for secure database operations with %i placeholder support.', 'aiohm-knowledge-assistant'),
                esc_html__('Plugin Activation Error', 'aiohm-knowledge-assistant'),
                array('back_link' => true)
            );
        }
        
        require_once AIOHM_KB_INCLUDES_DIR . 'rag-engine.php';
        $this->create_tables();
        $this->maybe_update_content_type_column(); // Force migration on activation  
        $this->create_project_tables(); // Moved this before conversations
        $this->create_conversation_tables();
        $this->create_mcp_tables(); // Create MCP token tables
        $this->maybe_migrate_soul_signature(); // Migrate soul signature formatting
        $this->set_default_options();
        flush_rewrite_rules();
        $settings = self::get_settings();
        if ($settings['scan_schedule'] !== 'none') {
            $this->schedule_scan_event($settings['scan_schedule']);
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook(AIOHM_KB_SCHEDULED_SCAN_HOOK);
        flush_rewrite_rules();
    }
    
    /**
     * Encrypt API key for secure storage
     * @param string $api_key The API key to encrypt
     * @return string The encrypted API key
     */
    public static function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // Use WordPress auth constants for encryption key
        $key = wp_hash(SECURE_AUTH_KEY . LOGGED_IN_KEY);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $key, 0, $iv);
        
        if ($encrypted === false) {
            return '';
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt API key for use
     * @param string $encrypted_key The encrypted API key
     * @return string The decrypted API key
     */
    public static function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }
        
        $data = base64_decode($encrypted_key);
        if ($data === false || strlen($data) < 16) {
            return '';
        }
        
        $key = wp_hash(SECURE_AUTH_KEY . LOGGED_IN_KEY);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        if ($decrypted === false) {
            return '';
        }
        
        // Check if this is double-encrypted (if it's still base64 and needs another decryption)
        if (strlen($decrypted) > 50 && preg_match('/^[A-Za-z0-9+\/]+=*$/', $decrypted)) {
            // Looks like base64, try to decrypt again
            $second_decrypted = self::decrypt_api_key($decrypted);
            if (!empty($second_decrypted) && strlen($second_decrypted) < 100) {
                return $second_decrypted;
            }
        }
        
        return $decrypted;
    }
    
    public static function get_settings() {
        $default_settings = [
            'aiohm_app_email' => '',
            'openai_api_key'   => '',
            'gemini_api_key' => '',
            'claude_api_key' => '',
            'shareai_api_key' => '',
            'shareai_model' => 'llama4:17b-scout-16e-instruct-fp16',
            'private_llm_server_url' => 'https://ollama.servbay.host/',
            'private_llm_model' => 'llama2',
            'default_ai_provider' => '',
            'external_api_consent' => true,
            'chat_enabled'     => true,
            'show_floating_chat' => false,
            'scan_schedule'    => 'none',
            'chunk_size'       => 1000,
            'chunk_overlap'    => 200,
            'mirror_mode' => [
                'qa_system_message' => "You are the official AI Knowledge Assistant for \"%site_name%\".\n\nYour mission is to embody our brand voice and assist website visitors with helpful, accurate information. Today is %day_of_week%, %current_date%.\n\nResponse Guidelines:\n\n1. Answer based on the provided context below - this is your primary information source\n\n2. Maintain our brand personality:\n   • Be helpful and thoughtful, not robotic\n   • Stay professional but warm\n   • Be concise and clear\n\n3. When you don't have enough information:\n   Say: \"I don't have enough information to answer that accurately. Please contact us directly for personalized help.\"\n\n4. Use basic HTML formatting only (no Markdown)\n\nContext:\n{context}",
                'qa_temperature' => '0.8',
                'business_name' => get_bloginfo('name'),
            ],
            'muse_mode' => [
                'system_prompt' => "You are Muse, your private brand assistant.\n\nYour work focuses on helping build your brand using the context provided. This includes public information and your private Brand Soul answers.\n\nWriting Style Rules:\n• Use clear, simple language\n• Write short, direct sentences\n• Address the user as \"you\" and \"your\"\n• Focus on practical, actionable advice\n• Use bullet points for lists\n• Support ideas with data and examples\n• Avoid complex metaphors and clichés\n• Use active voice only\n• Be spartan and informative\n\nContent Approach:\n1. Read all Brand Soul context first\n2. Match the brand archetype and voice\n3. Give specific, actionable recommendations\n4. Reference your Brand Soul answers when relevant\n5. Ask clarifying questions when needed\n\nContext:\n{context}",
                'temperature' => '0.7',
                'assistant_name' => 'Muse',
                'start_fullscreen' => true,
            ],
            'mcp_enabled' => false,
            'mcp_rate_limit' => 1000,
            'mcp_require_https' => true
        ];
        $saved_settings = get_option('aiohm_kb_settings', []);
        $settings = wp_parse_args($saved_settings, $default_settings);
        
        // Check for MCP settings in separate option as fallback
        // Only use separate option if mcp_enabled is not found in main saved settings
        if (!isset($saved_settings['mcp_enabled'])) {
            $mcp_settings = get_option('aiohm_mcp_settings', []);
            if (!empty($mcp_settings)) {
                $settings['mcp_enabled'] = $mcp_settings['mcp_enabled'] ?? false;
                $settings['mcp_rate_limit'] = $mcp_settings['mcp_rate_limit'] ?? 1000;
                $settings['mcp_require_https'] = $mcp_settings['mcp_require_https'] ?? true;
            }
        }
        
        // Fix: Don't override saved values with defaults
        if (isset($saved_settings['mirror_mode'])) {
            $settings['mirror_mode'] = wp_parse_args($saved_settings['mirror_mode'], $default_settings['mirror_mode']);
        } else {
            $settings['mirror_mode'] = $default_settings['mirror_mode'];
        }
        
        if (isset($saved_settings['muse_mode'])) {
            $settings['muse_mode'] = wp_parse_args($saved_settings['muse_mode'], $default_settings['muse_mode']);
        } else {
            $settings['muse_mode'] = $default_settings['muse_mode'];
        }
        
        
        // Decrypt API keys for use (except ShareAI which is stored in plain text)
        $encrypted_api_keys = ['openai_api_key', 'gemini_api_key', 'claude_api_key'];
        foreach ($encrypted_api_keys as $key) {
            if (!empty($settings[$key])) {
                $decrypted = self::decrypt_api_key($settings[$key]);
                if (!empty($decrypted)) {
                    $settings[$key] = $decrypted;
                } else {
                    // If decryption fails, clear the key to prevent sending encrypted data to API
                    $settings[$key] = '';
                }
            }
        }
        
        // ShareAI API key is stored as plain text - no decryption needed
        if (!empty($settings['shareai_api_key'])) {
            // Clean up any corrupted ShareAI keys from previous encryption attempts
            if (strlen($settings['shareai_api_key']) > 100 || preg_match('/[+\/=]/', $settings['shareai_api_key'])) {
                $settings['shareai_api_key'] = '';
                // Also clear from database
                $current_settings = get_option('aiohm_kb_settings', []);
                $current_settings['shareai_api_key'] = '';
                update_option('aiohm_kb_settings', $current_settings);
            }
        }
        
        return $settings;
    }

    private function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        
        // Check if we need to update the content_type column size
        $this->maybe_update_content_type_column();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            content_id varchar(255) NOT NULL,
            content_type varchar(100) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            vector_data longtext,
            metadata longtext,
            is_public tinyint(1) DEFAULT 0 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY content_id (content_id),
            KEY is_public (is_public),
            FULLTEXT KEY content (content)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Update content_type column size if needed for existing installations
     */
    private function maybe_update_content_type_column() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        
        // Check if table exists first
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if (!$table_exists) {
            self::log('Table ' . $table_name . ' does not exist yet, skipping migration', 'info');
            return;
        }
        
        // Check current column definition
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, from system
        $sql = "SHOW COLUMNS FROM `{$table_name}` LIKE %s";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Schema check for migration  
        $column_info = $wpdb->get_row($wpdb->prepare($sql, 'content_type'));
        
        if ($column_info) {
            self::log('Current content_type column definition: ' . $column_info->Type, 'info');
            
            if (strpos($column_info->Type, 'varchar(50)') !== false) {
                // Column exists but is still varchar(50), update it
                self::log('Updating content_type column from varchar(50) to varchar(100)', 'info');
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, from system
                $alter_sql = "ALTER TABLE `{$table_name}` MODIFY COLUMN `content_type` varchar(100) NOT NULL";
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared -- Schema migration during activation
                $result = $wpdb->query($alter_sql);
                
                if ($result !== false) {
                    self::log('Successfully updated content_type column to varchar(100)', 'info');
                } else {
                    self::log('Failed to update content_type column: ' . $wpdb->last_error, 'error');
                }
            } else {
                self::log('content_type column is already the correct size: ' . $column_info->Type, 'info');
            }
        } else {
            self::log('content_type column not found in table ' . $table_name, 'error');
        }
    }
    
    /**
     * Migrate soul signature formatting from old poorly formatted version to new clean format
     */
    private function maybe_migrate_soul_signature() {
        $migration_key = 'aiohm_soul_signature_migration_v2';
        $migration_done = get_option($migration_key, false);
        
        if ($migration_done) {
            return; // Migration already completed
        }
        
        $settings = self::get_settings();
        $needs_update = false;
        
        // Check Mirror Mode soul signature
        if (!empty($settings['mirror_mode']['qa_system_message'])) {
            $current_message = $settings['mirror_mode']['qa_system_message'];
            
            // Check if this is the old poorly formatted version (any of the messy formats)
            if (strpos($current_message, 'Core Instructions:') !== false || 
                strpos($current_message, '**Core Instructions:**') !== false ||
                strpos($current_message, '1. Primary Directive: Your primary goal') !== false) {
                
                // This is the old format, replace with new format
                $new_format = "You are the official AI Knowledge Assistant for \"%site_name%\".\n\nYour mission is to embody our brand voice and assist website visitors with helpful, accurate information. Today is %day_of_week%, %current_date%.\n\nResponse Guidelines:\n\n1. Answer based on the provided context below - this is your primary information source\n\n2. Maintain our brand personality:\n   • Be helpful and thoughtful, not robotic\n   • Stay professional but warm\n   • Be concise and clear\n\n3. When you don't have enough information:\n   Say: \"I don't have enough information to answer that accurately. Please contact us directly for personalized help.\"\n\n4. Use basic HTML formatting only (no Markdown)\n\nContext:\n{context}";
                
                $settings['mirror_mode']['qa_system_message'] = $new_format;
                $needs_update = true;
                
                self::log('Migrating Mirror Mode soul signature to new format', 'info');
            }
        }
        
        // Save updated settings if needed
        if ($needs_update) {
            update_option('aiohm_kb_settings', $settings);
            self::log('Soul signature migration completed successfully', 'info');
        }
        
        // Mark migration as completed
        update_option($migration_key, true);
    }
    
    
    private function create_conversation_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table for conversations
        $table_conversations = $wpdb->prefix . 'aiohm_conversations';
        // **FIX: Added project_id directly to the table creation statement.**
        $sql_conversations = "CREATE TABLE $table_conversations (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            user_id BIGINT(20) NOT NULL,
            title VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY project_id (project_id)
        ) $charset_collate;";
        dbDelta($sql_conversations);

        // Table for individual messages
        $table_messages = $wpdb->prefix . 'aiohm_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT(20) NOT NULL,
            sender ENUM('user', 'ai', 'system') NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        dbDelta($sql_messages);
    }
    
    private function create_project_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
        // Table for projects
        $table_name_projects = $wpdb->prefix . 'aiohm_projects';
        $sql_projects = "CREATE TABLE $table_name_projects (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            project_name varchar(255) NOT NULL,
            notes LONGTEXT,
            creation_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_projects);
    
        // Check if project_id column exists in conversations table
        // Note: Schema changes during plugin activation are acceptable by WordPress standards
        $table_name_conversations = $wpdb->prefix . 'aiohm_conversations';
        $cache_key = 'aiohm_conversations_schema_v1';
        $schema_version = get_option($cache_key, '');
        
        if ($schema_version !== '1.2.0') {
            // Use dbDelta for safer schema updates during activation
            $sql_update = "CREATE TABLE $table_name_conversations (
                id BIGINT(20) NOT NULL AUTO_INCREMENT,
                project_id mediumint(9) NOT NULL DEFAULT 0,
                user_id BIGINT(20) NOT NULL,
                title VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY project_id (project_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_update);
            
            // Mark schema as updated
            update_option($cache_key, '1.2.0');
        }
    }
    
    /**
     * Create MCP token management tables
     */
    private function create_mcp_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table for MCP API tokens
        $table_name_tokens = $wpdb->prefix . 'aiohm_mcp_tokens';
        $sql_tokens = "CREATE TABLE $table_name_tokens (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token_name varchar(255) NOT NULL,
            token_hash varchar(64) NOT NULL,
            token_type enum('public', 'private') DEFAULT 'private' NOT NULL,
            permissions longtext NOT NULL,
            expires_at datetime DEFAULT NULL,
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_used_at datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1 NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY created_by (created_by),
            KEY is_active (is_active),
            KEY expires_at (expires_at),
            KEY token_type (token_type)
        ) $charset_collate;";
        dbDelta($sql_tokens);

        // Table for MCP API usage logging
        $table_name_usage = $wpdb->prefix . 'aiohm_mcp_usage';
        $sql_usage = "CREATE TABLE $table_name_usage (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token_id bigint(20) NOT NULL,
            endpoint varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            request_data longtext,
            response_status int(3) NOT NULL,
            response_time int(11) DEFAULT 0,
            user_agent text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY token_id (token_id),
            KEY endpoint (endpoint),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_usage);
        
        AIOHM_KB_Assistant::log('MCP tables created successfully', 'info');
    }


    private function set_default_options() {
        if (get_option('aiohm_kb_settings') === false) {
            // Only set basic defaults, don't call get_settings() which includes defaults
            $basic_defaults = [
                'aiohm_app_email' => '',
                'openai_api_key' => '',
                'gemini_api_key' => '',
                'claude_api_key' => '',
                'shareai_api_key' => '',
                'shareai_model' => 'llama4:17b-scout-16e-instruct-fp16',
                'private_llm_server_url' => 'https://ollama.servbay.host/',
                'private_llm_model' => 'llama2',
                'default_ai_provider' => '',
                'chat_enabled' => true,
                'show_floating_chat' => false,
                'scan_schedule' => 'none',
                'chunk_size' => 1000,
                'chunk_overlap' => 200
            ];
            add_option('aiohm_kb_settings', $basic_defaults);
        }
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=aiohm-settings') . '">' . __('Settings', 'aiohm-knowledge-assistant') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
            if (function_exists('error_log')) {
                // Sanitize message to prevent sensitive data exposure
                $sanitized_message = self::sanitize_log_message($message);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for development only
                error_log('[AIOHM_KB_Assistant] ' . strtoupper($level) . ': ' . $sanitized_message);
            }
        }
    }

    /**
     * Validate content ID format to prevent SQL injection
     * @param string $content_id Content ID to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_content_id($content_id) {
        if (empty($content_id)) {
            return new WP_Error('empty_content_id', __('Content ID cannot be empty.', 'aiohm-knowledge-assistant'));
        }
        
        if (strlen($content_id) > 255) {
            return new WP_Error('content_id_too_long', __('Content ID is too long.', 'aiohm-knowledge-assistant'));
        }
        
        // Allow alphanumeric, hyphens, underscores, and dots only
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $content_id)) {
            return new WP_Error('invalid_content_id', __('Content ID contains invalid characters.', 'aiohm-knowledge-assistant'));
        }
        
        return true;
    }

    /**
     * Validate and sanitize API key
     * @param string $api_key API key to validate
     * @param string $provider Provider type (openai, claude, gemini, shareai)
     * @return string|WP_Error Sanitized API key or WP_Error if invalid
     */
    public static function validate_and_sanitize_api_key($api_key, $provider = '') {
        if (empty($api_key)) {
            return new WP_Error('empty_api_key', __('API key cannot be empty.', 'aiohm-knowledge-assistant'));
        }
        
        // Remove dangerous characters but preserve API key characters
        $sanitized = preg_replace('/[<>"\'\x00-\x1f\x7f-\xff]/', '', trim($api_key));
        
        switch ($provider) {
            case 'openai':
                if (!preg_match('/^sk-[a-zA-Z0-9]{20,}$/', $sanitized)) {
                    return new WP_Error('invalid_openai_key', __('Invalid OpenAI API key format.', 'aiohm-knowledge-assistant'));
                }
                break;
            case 'claude':
                if (!preg_match('/^sk-ant-[a-zA-Z0-9_-]{90,}$/', $sanitized)) {
                    return new WP_Error('invalid_claude_key', __('Invalid Claude API key format.', 'aiohm-knowledge-assistant'));
                }
                break;
            case 'gemini':
                if (!preg_match('/^AIza[a-zA-Z0-9_-]{35}$/', $sanitized)) {
                    return new WP_Error('invalid_gemini_key', __('Invalid Gemini API key format.', 'aiohm-knowledge-assistant'));
                }
                break;
            case 'shareai':
                // ShareAI keys have simpler format - just alphanumeric and hyphens
                $sanitized = preg_replace('/[^a-zA-Z0-9\-]/', '', $sanitized);
                if (strlen($sanitized) < 10) {
                    return new WP_Error('invalid_shareai_key', __('ShareAI API key too short.', 'aiohm-knowledge-assistant'));
                }
                break;
        }
        
        return $sanitized;
    }

    /**
     * Enhanced rate limiting for sensitive endpoints
     * @param string $endpoint Endpoint identifier
     * @param int $user_id User ID
     * @param int $limit Rate limit (default 50 per hour)
     * @return bool True if within limit, false if exceeded
     */
    public static function check_enhanced_rate_limit($endpoint, $user_id = 0, $limit = 50) {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }
        
        $user_ip = self::get_client_ip();
        
        // Check user-based rate limit
        $user_key = "aiohm_rate_limit_{$endpoint}_user_{$user_id}";
        $user_count = get_transient($user_key);
        
        // Check IP-based rate limit
        $ip_key = "aiohm_rate_limit_{$endpoint}_ip_" . md5($user_ip);
        $ip_count = get_transient($ip_key);
        
        // Initialize counters if they don't exist
        if ($user_count === false) {
            set_transient($user_key, 1, HOUR_IN_SECONDS);
            $user_count = 1;
        }
        
        if ($ip_count === false) {
            set_transient($ip_key, 1, HOUR_IN_SECONDS);
            $ip_count = 1;
        }
        
        // Check if either limit is exceeded
        if ($user_count >= $limit || $ip_count >= $limit) {
            self::log("Rate limit exceeded for endpoint {$endpoint}. User: {$user_count}, IP: {$ip_count}", 'warning');
            return false;
        }
        
        // Increment both counters
        set_transient($user_key, $user_count + 1, HOUR_IN_SECONDS);
        set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);
        
        return true;
    }

    /**
     * Get client IP address securely
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1'; // Fallback
    }

    /**
     * Sanitize log messages to prevent API key exposure
     * @param string $message Log message to sanitize
     * @return string Sanitized message
     */
    private static function sanitize_log_message($message) {
        if (!is_string($message)) {
            return wp_json_encode($message);
        }
        
        // Hide OpenAI API keys (sk-xxx format)
        $message = preg_replace('/sk-[a-zA-Z0-9]{20,}/', 'OPENAI_API_KEY_HIDDEN', $message);
        $message = preg_replace('/pk-[a-zA-Z0-9]{20,}/', 'OPENAI_KEY_HIDDEN', $message);
        
        // Hide Claude API keys (sk-ant-xxx format)
        $message = preg_replace('/sk-ant-[a-zA-Z0-9_-]{90,}/', 'CLAUDE_API_KEY_HIDDEN', $message);
        
        // Hide Gemini API keys (various formats)
        $message = preg_replace('/AIza[a-zA-Z0-9_-]{35}/', 'GEMINI_API_KEY_HIDDEN', $message);
        
        // Hide ShareAI keys and other long alphanumeric strings that might be keys
        $message = preg_replace('/\b[a-zA-Z0-9]{32,}\b/', 'POTENTIAL_API_KEY_HIDDEN', $message);
        
        // Hide API key JSON patterns
        $message = preg_replace('/"(api[_-]?key|apikey|key)"\s*:\s*"[^"]{10,}"/', '"$1":"API_KEY_HIDDEN"', $message);
        
        // Hide Authorization headers
        $message = preg_replace('/Authorization:\s*Bearer\s+[^\s]{10,}/', 'Authorization: Bearer API_KEY_HIDDEN', $message);
        
        // Hide passwords, tokens, and secrets
        $message = preg_replace('/(password|token|secret)["\']?\s*[:=]\s*["\'][^"\']{3,}["\']/', '$1":"SENSITIVE_DATA_HIDDEN"', $message);
        
        // Hide any base64 encoded data that might contain keys
        $message = preg_replace('/[A-Za-z0-9+\/]{50,}={0,2}/', 'BASE64_DATA_HIDDEN', $message);
        
        // Limit message length to prevent log flooding
        if (strlen($message) > 2000) {
            $message = substr($message, 0, 1997) . '...';
        }
        
        return $message;
    }

    public function add_custom_cron_intervals($schedules) {
        $schedules['weekly'] = array('interval' => WEEK_IN_SECONDS, 'display'  => __('Once Weekly', 'aiohm-knowledge-assistant'));
        $schedules['monthly'] = array('interval' => MONTH_IN_SECONDS, 'display'  => __('Once Monthly', 'aiohm-knowledge-assistant'));
        return $schedules;
    }
    
    /**
     * Handle scan schedule changes when settings are updated
     * @param array $old_value Previous settings
     * @param array $new_value New settings  
     */
    public function handle_scan_schedule_change($old_value, $new_value) {
        // Clear any existing scheduled scan
        wp_clear_scheduled_hook(AIOHM_KB_SCHEDULED_SCAN_HOOK);
        
        // If new schedule is not 'none', schedule the event
        if (isset($new_value['scan_schedule']) && $new_value['scan_schedule'] !== 'none') {
            $this->schedule_scan_event($new_value['scan_schedule']);
        }
    }
    
    /**
     * Schedule scan event based on frequency
     * @param string $frequency Frequency (daily, weekly, monthly)
     */
    private function schedule_scan_event($frequency) {
        if (!wp_next_scheduled(AIOHM_KB_SCHEDULED_SCAN_HOOK)) {
            wp_schedule_event(time(), $frequency, AIOHM_KB_SCHEDULED_SCAN_HOOK);
        }
    }
    
    /**
     * Run scheduled scan
     */
    public function run_scheduled_scan() {
        // Implement scheduled scan logic here if needed
        self::log('Running scheduled content scan', 'info');
    }

}

AIOHM_KB_Assistant::get_instance();