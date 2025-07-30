<?php
/**
 * AIOHM MCP (Model Context Protocol) Handler
 * 
 * Implements MCP server capabilities for sharing knowledge base
 * via standardized API endpoints with authentication and permissions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIOHM_KB_MCP_Handler {
    
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
        add_action('rest_api_init', array($this, 'register_mcp_endpoints'));
        add_action('wp_ajax_aiohm_generate_mcp_token', array($this, 'ajax_generate_mcp_token'));
        add_action('wp_ajax_aiohm_revoke_mcp_token', array($this, 'ajax_revoke_mcp_token'));
        add_action('wp_ajax_aiohm_remove_mcp_token', array($this, 'ajax_remove_mcp_token'));
        add_action('wp_ajax_aiohm_list_mcp_tokens', array($this, 'ajax_list_mcp_tokens'));
        add_action('wp_ajax_aiohm_view_mcp_token', array($this, 'ajax_view_mcp_token'));
    }
    
    /**
     * Secure database wrapper functions with table name validation
     * 
     * Note: This plugin requires WordPress 6.2+ for %i placeholder support
     * in $wpdb->prepare() for safe table name handling.
     */
    
    /**
     * Validate and sanitize table name against whitelist
     */
    private function validate_table_name($table_name) {
        global $wpdb;
        $allowed_tables = [
            $wpdb->prefix . 'aiohm_vector_entries',
            $wpdb->prefix . 'aiohm_mcp_tokens',
            $wpdb->prefix . 'aiohm_conversations',
            $wpdb->prefix . 'aiohm_messages',
            $wpdb->prefix . 'aiohm_projects'
        ];
        
        if (!in_array($table_name, $allowed_tables, true)) {
            return new WP_Error('invalid_table', __('Invalid table name for MCP operations.', 'aiohm-knowledge-assistant'));
        }
        
        return true;
    }
    
    /**
     * Get knowledge base entry by ID using prepared statement with table validation
     */
    private function get_kb_entry_by_id($entry_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        return $this->wp_cache_remember("aiohm_kb_entry_{$entry_id}", function() use ($wpdb, $table_name, $entry_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
            return $wpdb->get_row($wpdb->prepare(
                'SELECT id, user_id, content_id, content_type, title, content, metadata, created_at FROM %i WHERE id = %d',
                $table_name,
                $entry_id
            ), ARRAY_A);
        }, 'aiohm_mcp', HOUR_IN_SECONDS);
    }
    
    /**
     * Get paginated knowledge base entries using prepared statements with table validation
     */
    private function get_kb_entries_paginated($project_id = null, $page = 1, $per_page = 20) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        $offset = ($page - 1) * $per_page;
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        $cache_key = "aiohm_kb_entries_{$project_id}_{$page}_{$per_page}";
        
        return $this->wp_cache_remember($cache_key, function() use ($wpdb, $table_name, $project_id, $per_page, $offset) {
            if ($project_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
                $entries = $wpdb->get_results($wpdb->prepare(
                    'SELECT id, content_id, content_type, title, created_at, SUBSTRING(content, 1, 200) as content_preview FROM %i WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $table_name, $project_id, $per_page, $offset
                ), ARRAY_A);
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
                $total_count = $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE user_id = %d',
                    $table_name, $project_id
                ));
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
                $entries = $wpdb->get_results($wpdb->prepare(
                    'SELECT id, content_id, content_type, title, created_at, SUBSTRING(content, 1, 200) as content_preview FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $table_name, $per_page, $offset
                ), ARRAY_A);
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
                $total_count = $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(*) FROM %i',
                    $table_name
                ));
            }
            
            return array(
                'entries' => $entries,
                'total_count' => $total_count
            );
        }, 'aiohm_mcp', 15 * MINUTE_IN_SECONDS);
    }
    
    /**
     * Update knowledge base entry using prepared statement with table validation
     */
    private function update_kb_entry($entry_id, $update_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Clear cache
        wp_cache_delete("aiohm_kb_entry_{$entry_id}", 'aiohm_mcp');
        wp_cache_flush_group('aiohm_mcp');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- MCP knowledge base updates require direct database access for vector operations
        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $entry_id),
            null, // Let WordPress determine format
            array('%d')
        );
    }
    
    /**
     * Delete knowledge base entry using prepared statement with table validation
     */
    private function delete_kb_entry($entry_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Clear cache
        wp_cache_delete("aiohm_kb_entry_{$entry_id}", 'aiohm_mcp');
        wp_cache_flush_group('aiohm_mcp');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- MCP knowledge base deletion requires direct database access for vector operations
        return $wpdb->delete(
            $table_name,
            array('id' => $entry_id),
            array('%d')
        );
    }
    
    /**
     * Get MCP token by hash using prepared statement with table validation
     */
    private function get_token_by_hash($token_hash) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_mcp_tokens';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        return $this->wp_cache_remember("aiohm_mcp_token_{$token_hash}", function() use ($wpdb, $table_name, $token_hash) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom token tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
            return $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM %i WHERE token_hash = %s AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())',
                $table_name, $token_hash
            ), ARRAY_A);
        }, 'aiohm_mcp_tokens', 5 * MINUTE_IN_SECONDS);
    }
    
    /**
     * Get all MCP tokens using prepared statement with table validation
     */
    private function get_all_mcp_tokens() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_mcp_tokens';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        return $this->wp_cache_remember('aiohm_all_mcp_tokens', function() use ($wpdb, $table_name) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom token tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
            return $wpdb->get_results($wpdb->prepare(
                'SELECT id, token_name, permissions, expires_at, created_at, is_active, last_used_at FROM %i ORDER BY created_at DESC',
                $table_name
            ), ARRAY_A);
        }, 'aiohm_mcp_tokens', 5 * MINUTE_IN_SECONDS);
    }
    
    /**
     * Get MCP token details by ID using prepared statement with table validation
     */
    private function get_mcp_token_by_id($token_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_mcp_tokens';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        return $this->wp_cache_remember("aiohm_mcp_token_details_{$token_id}", function() use ($wpdb, $table_name, $token_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP custom token tables require direct queries, comprehensive caching implemented via wp_cache_remember wrapper
            return $wpdb->get_row($wpdb->prepare(
                'SELECT id, token_name, token_hash, permissions, expires_at, created_at, is_active, last_used_at, created_by FROM %i WHERE id = %d',
                $table_name, $token_id
            ), ARRAY_A);
        }, 'aiohm_mcp_tokens', 5 * MINUTE_IN_SECONDS);
    }
    
    /**
     * Create MCP token using prepared statement with table validation
     */
    private function create_mcp_token($token_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_mcp_tokens';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Clear cache
        wp_cache_flush_group('aiohm_mcp_tokens');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- MCP token creation requires direct database access for security operations
        return $wpdb->insert($table_name, $token_data);
    }
    
    /**
     * Update MCP token using prepared statement with table validation
     */
    private function update_mcp_token($token_id, $update_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_mcp_tokens';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Clear cache
        wp_cache_delete("aiohm_mcp_token_details_{$token_id}", 'aiohm_mcp_tokens');
        wp_cache_flush_group('aiohm_mcp_tokens');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- MCP token updates require direct database access for security operations
        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $token_id),
            null, // Let WordPress determine format
            array('%d')
        );
    }
    
    /**
     * Delete MCP token using prepared statement with table validation
     */
    private function delete_mcp_token($token_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_mcp_tokens';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Clear cache
        wp_cache_delete("aiohm_mcp_token_details_{$token_id}", 'aiohm_mcp_tokens');
        wp_cache_flush_group('aiohm_mcp_tokens');
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- MCP token deletion requires direct database access for security operations
        return $wpdb->delete(
            $table_name,
            array('id' => $token_id),
            array('%d')
        );
    }
    
    /**
     * WordPress cache helper function
     */
    private function wp_cache_remember($key, $callback, $group = '', $expiration = 0) {
        $cached_value = wp_cache_get($key, $group);
        if ($cached_value !== false) {
            return $cached_value;
        }
        
        $value = $callback();
        wp_cache_set($key, $value, $group, $expiration);
        return $value;
    }
    
    /**
     * Register MCP REST API endpoints
     */
    public function register_mcp_endpoints() {
        $settings = AIOHM_KB_Assistant::get_settings();
        
        // Only register endpoints if MCP is enabled
        if (empty($settings['mcp_enabled'])) {
            return;
        }
        
        // MCP Manifest endpoint
        register_rest_route('aiohm/mcp/v1', '/manifest', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_mcp_manifest'),
            'permission_callback' => array($this, 'validate_mcp_access'),
        ));
        
        // MCP Call endpoint
        register_rest_route('aiohm/mcp/v1', '/call', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mcp_call'),
            'permission_callback' => array($this, 'validate_mcp_access'),
            'args' => array(
                'action' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => array($this, 'validate_mcp_action'),
                ),
                'parameters' => array(
                    'required' => false,
                    'type' => 'object',
                    'default' => array(),
                ),
            ),
        ));
        
        // MCP Token validation endpoint
        register_rest_route('aiohm/mcp/v1', '/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_mcp_token'),
            'permission_callback' => array($this, 'validate_mcp_access'),
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
    }
    
    /**
     * Get MCP manifest describing available capabilities
     */
    public function get_mcp_manifest($request) {
        $token_data = $this->get_token_from_request($request);
        if (!$token_data) {
            return new WP_Error('invalid_token', __('Invalid MCP token', 'aiohm-knowledge-assistant'), array('status' => 401));
        }
        
        $permissions = json_decode($token_data['permissions'], true) ?: array();
        $capabilities = array();
        
        // Query Knowledge Base capability
        if (in_array('read_kb', $permissions) || in_array('read_write_kb', $permissions)) {
            $capabilities['queryKB'] = array(
                'description' => __('Query the knowledge base with semantic search', 'aiohm-knowledge-assistant'),
                'parameters' => array(
                    'query' => array(
                        'type' => 'string',
                        'description' => __('Search query text', 'aiohm-knowledge-assistant'),
                        'required' => true,
                    ),
                    'limit' => array(
                        'type' => 'integer',
                        'description' => __('Maximum number of results', 'aiohm-knowledge-assistant'),
                        'default' => 10,
                        'minimum' => 1,
                        'maximum' => 50,
                    ),
                    'project_id' => array(
                        'type' => 'integer',
                        'description' => __('Filter by specific project ID', 'aiohm-knowledge-assistant'),
                        'required' => false,
                    ),
                    'content_type' => array(
                        'type' => 'string',
                        'description' => __('Filter by content type', 'aiohm-knowledge-assistant'),
                        'enum' => array('page', 'post', 'upload', 'brand_soul', 'custom'),
                        'required' => false,
                    ),
                ),
            );
            
            $capabilities['getKBEntry'] = array(
                'description' => __('Get specific knowledge base entry by ID', 'aiohm-knowledge-assistant'),
                'parameters' => array(
                    'entry_id' => array(
                        'type' => 'integer',
                        'description' => __('Knowledge base entry ID', 'aiohm-knowledge-assistant'),
                        'required' => true,
                    ),
                ),
            );
            
            $capabilities['listKBEntries'] = array(
                'description' => __('List knowledge base entries with pagination', 'aiohm-knowledge-assistant'),
                'parameters' => array(
                    'page' => array(
                        'type' => 'integer',
                        'description' => __('Page number', 'aiohm-knowledge-assistant'),
                        'default' => 1,
                        'minimum' => 1,
                    ),
                    'per_page' => array(
                        'type' => 'integer',
                        'description' => __('Entries per page', 'aiohm-knowledge-assistant'),
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100,
                    ),
                    'project_id' => array(
                        'type' => 'integer',
                        'description' => __('Filter by project ID', 'aiohm-knowledge-assistant'),
                        'required' => false,
                    ),
                ),
            );
        }
        
        // Write capabilities
        if (in_array('read_write_kb', $permissions)) {
            $capabilities['addKBEntry'] = array(
                'description' => __('Add new entry to knowledge base', 'aiohm-knowledge-assistant'),
                'parameters' => array(
                    'title' => array(
                        'type' => 'string',
                        'description' => __('Entry title', 'aiohm-knowledge-assistant'),
                        'required' => true,
                        'minLength' => 1,
                        'maxLength' => 255,
                    ),
                    'content' => array(
                        'type' => 'string',
                        'description' => __('Entry content', 'aiohm-knowledge-assistant'),
                        'required' => true,
                        'minLength' => 1,
                    ),
                    'content_type' => array(
                        'type' => 'string',
                        'description' => __('Content type', 'aiohm-knowledge-assistant'),
                        'default' => 'mcp_external',
                        'enum' => array('mcp_external', 'custom'),
                    ),
                    'project_id' => array(
                        'type' => 'integer',
                        'description' => __('Project ID to associate with', 'aiohm-knowledge-assistant'),
                        'required' => false,
                    ),
                    'metadata' => array(
                        'type' => 'object',
                        'description' => __('Additional metadata for the entry', 'aiohm-knowledge-assistant'),
                        'required' => false,
                    ),
                ),
            );
            
            $capabilities['updateKBEntry'] = array(
                'description' => __('Update existing knowledge base entry', 'aiohm-knowledge-assistant'),
                'parameters' => array(
                    'entry_id' => array(
                        'type' => 'integer',
                        'description' => __('Entry ID to update', 'aiohm-knowledge-assistant'),
                        'required' => true,
                    ),
                    'title' => array(
                        'type' => 'string',
                        'description' => __('Updated title', 'aiohm-knowledge-assistant'),
                        'required' => false,
                        'maxLength' => 255,
                    ),
                    'content' => array(
                        'type' => 'string',
                        'description' => __('Updated content', 'aiohm-knowledge-assistant'),
                        'required' => false,
                    ),
                    'metadata' => array(
                        'type' => 'object',
                        'description' => __('Updated metadata', 'aiohm-knowledge-assistant'),
                        'required' => false,
                    ),
                ),
            );
            
            $capabilities['deleteKBEntry'] = array(
                'description' => __('Delete knowledge base entry', 'aiohm-knowledge-assistant'),
                'parameters' => array(
                    'entry_id' => array(
                        'type' => 'integer',
                        'description' => __('Entry ID to delete', 'aiohm-knowledge-assistant'),
                        'required' => true,
                    ),
                ),
            );
        }
        
        $manifest = array(
            'version' => '1.0',
            'protocol' => 'mcp',
            'server' => array(
                'name' => 'AIOHM Knowledge Assistant',
                'version' => AIOHM_KB_VERSION,
                'description' => __('WordPress knowledge base with AI-powered search and content management', 'aiohm-knowledge-assistant'),
                'url' => home_url(),
            ),
            'capabilities' => $capabilities,
            'authentication' => array(
                'type' => 'bearer_token',
                'description' => __('Use Bearer token in Authorization header', 'aiohm-knowledge-assistant'),
            ),
            'rate_limits' => array(
                'requests_per_hour' => 1000,
                'burst_limit' => 10,
            ),
        );
        
        AIOHM_KB_Assistant::log('MCP manifest requested for token: ' . substr($token_data['token_name'], 0, 20), 'info');
        
        return rest_ensure_response($manifest);
    }
    
    /**
     * Handle MCP API calls
     */
    public function handle_mcp_call($request) {
        $token_data = $this->get_token_from_request($request);
        if (!$token_data) {
            return new WP_Error('invalid_token', __('Invalid MCP token', 'aiohm-knowledge-assistant'), array('status' => 401));
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit($token_data['id'])) {
            return new WP_Error('rate_limit_exceeded', __('Rate limit exceeded', 'aiohm-knowledge-assistant'), array('status' => 429));
        }
        
        $action = $request->get_param('action');
        $parameters = $request->get_param('parameters') ?: array();
        $permissions = json_decode($token_data['permissions'], true) ?: array();
        
        AIOHM_KB_Assistant::log("MCP call: {$action} with params: " . wp_json_encode($parameters), 'info');
        
        try {
            switch ($action) {
                case 'queryKB':
                    return $this->handle_query_kb($parameters, $permissions, $token_data);
                    
                case 'getKBEntry':
                    return $this->handle_get_kb_entry($parameters, $permissions, $token_data);
                    
                case 'listKBEntries':
                    return $this->handle_list_kb_entries($parameters, $permissions, $token_data);
                    
                case 'addKBEntry':
                    return $this->handle_add_kb_entry($parameters, $permissions, $token_data);
                    
                case 'updateKBEntry':
                    return $this->handle_update_kb_entry($parameters, $permissions, $token_data);
                    
                case 'deleteKBEntry':
                    return $this->handle_delete_kb_entry($parameters, $permissions, $token_data);
                    
                default:
                    return new WP_Error('invalid_action', __('Invalid MCP action', 'aiohm-knowledge-assistant'), array('status' => 400));
            }
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('MCP call error: ' . $e->getMessage(), 'error');
            return new WP_Error('mcp_error', __('Internal server error', 'aiohm-knowledge-assistant'), array('status' => 500));
        }
    }
    
    /**
     * Query knowledge base
     */
    private function handle_query_kb($parameters, $permissions, $token_data = null) {
        if (!in_array('read_kb', $permissions) && !in_array('read_write_kb', $permissions)) {
            return new WP_Error('insufficient_permissions', __('Read permission required', 'aiohm-knowledge-assistant'), array('status' => 403));
        }
        
        $query = sanitize_text_field($parameters['query'] ?? '');
        $limit = min(50, max(1, intval($parameters['limit'] ?? 10)));
        $project_id = !empty($parameters['project_id']) ? intval($parameters['project_id']) : null;
        $content_type = !empty($parameters['content_type']) ? sanitize_text_field($parameters['content_type']) : null;
        
        if (empty($query)) {
            return new WP_Error('missing_query', __('Query parameter is required', 'aiohm-knowledge-assistant'), array('status' => 400));
        }
        
        $rag_engine = new AIOHM_KB_RAG_Engine();
        $search_results = $rag_engine->find_relevant_context($query, $limit);
        
        // Filter results based on token type
        $token_type = isset($token_data['token_type']) ? $token_data['token_type'] : 'private';
        
        // Convert the similarity results to the expected format
        $results = array();
        foreach ($search_results as $search_result) {
            $entry = $search_result['entry'];
            
            // Filter content based on token type
            if ($token_type === 'public') {
                // Public tokens: Only allow content that user has marked as public
                $is_public = isset($entry['is_public']) ? intval($entry['is_public']) : 0;
                if ($is_public !== 1) {
                    continue; // Skip content not marked as public by user
                }
            }
            // Private tokens: Allow all content (no filtering)
            
            $results[] = array(
                'id' => $entry['id'],
                'title' => $entry['title'],
                'content' => $entry['content'],
                'content_type' => $entry['content_type'],
                'similarity' => $search_result['score'],
                'created_at' => isset($entry['created_at']) ? $entry['created_at'] : '',
                'metadata' => $entry['metadata'],
                'token_type' => $token_type
            );
        }
        
        $formatted_results = array();
        foreach ($results as $result) {
            $formatted_results[] = array(
                'id' => intval($result['id']),
                'title' => $result['title'],
                'content' => $result['content'],
                'content_type' => $result['content_type'],
                'similarity_score' => floatval($result['similarity'] ?? 0),
                'created_at' => $result['created_at'],
                'metadata' => !empty($result['metadata']) ? json_decode($result['metadata'], true) : null,
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'results' => $formatted_results,
                'query' => $query,
                'total_results' => count($formatted_results),
                'limit' => $limit,
            ),
        ));
    }
    
    /**
     * Get specific KB entry
     */
    private function handle_get_kb_entry($parameters, $permissions, $token_data = null) {
        if (!in_array('read_kb', $permissions) && !in_array('read_write_kb', $permissions)) {
            return new WP_Error('insufficient_permissions', __('Read permission required', 'aiohm-knowledge-assistant'), array('status' => 403));
        }
        
        $entry_id = intval($parameters['entry_id'] ?? 0);
        if ($entry_id <= 0) {
            return new WP_Error('invalid_entry_id', __('Valid entry ID required', 'aiohm-knowledge-assistant'), array('status' => 400));
        }
        
        $entry = $this->get_kb_entry_by_id($entry_id);
        
        if (!$entry) {
            return new WP_Error('entry_not_found', __('Knowledge base entry not found', 'aiohm-knowledge-assistant'), array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'id' => intval($entry['id']),
                'title' => $entry['title'],
                'content' => $entry['content'],
                'content_type' => $entry['content_type'],
                'created_at' => $entry['created_at'],
                'metadata' => !empty($entry['metadata']) ? json_decode($entry['metadata'], true) : null,
            ),
        ));
    }
    
    /**
     * List KB entries with pagination
     */
    private function handle_list_kb_entries($parameters, $permissions, $token_data = null) {
        if (!in_array('read_kb', $permissions) && !in_array('read_write_kb', $permissions)) {
            return new WP_Error('insufficient_permissions', __('Read permission required', 'aiohm-knowledge-assistant'), array('status' => 403));
        }
        
        $page = max(1, intval($parameters['page'] ?? 1));
        $per_page = min(100, max(1, intval($parameters['per_page'] ?? 20)));
        $project_id = !empty($parameters['project_id']) ? intval($parameters['project_id']) : null;
        
        $result = $this->get_kb_entries_paginated($project_id, $page, $per_page);
        $entries = $result['entries'];
        $total_count = $result['total_count'];
        
        $formatted_entries = array();
        foreach ($entries as $entry) {
            $formatted_entries[] = array(
                'id' => intval($entry['id']),
                'title' => $entry['title'],
                'content_preview' => $entry['content_preview'] . (strlen($entry['content_preview']) >= 200 ? '...' : ''),
                'content_type' => $entry['content_type'],
                'created_at' => $entry['created_at'],
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'entries' => $formatted_entries,
                'pagination' => array(
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_entries' => intval($total_count),
                    'total_pages' => ceil($total_count / $per_page),
                ),
            ),
        ));
    }
    
    /**
     * Add new KB entry
     */
    private function handle_add_kb_entry($parameters, $permissions, $token_data = null) {
        if (!in_array('read_write_kb', $permissions)) {
            return new WP_Error('insufficient_permissions', __('Write permission required', 'aiohm-knowledge-assistant'), array('status' => 403));
        }
        
        $title = sanitize_text_field($parameters['title'] ?? '');
        $content = wp_kses_post($parameters['content'] ?? '');
        $content_type = sanitize_text_field($parameters['content_type'] ?? 'mcp_external');
        $project_id = !empty($parameters['project_id']) ? intval($parameters['project_id']) : 0;
        $metadata = $parameters['metadata'] ?? array();
        
        if (empty($title) || empty($content)) {
            return new WP_Error('missing_required_fields', __('Title and content are required', 'aiohm-knowledge-assistant'), array('status' => 400));
        }
        
        $rag_engine = new AIOHM_KB_RAG_Engine();
        $content_id = 'mcp_' . uniqid();
        
        $entry_id = $rag_engine->add_content_to_knowledge_base(
            $content_id,
            $content_type,
            $title,
            $content,
            $project_id,
            wp_json_encode($metadata)
        );
        
        if ($entry_id) {
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'entry_id' => $entry_id,
                    'title' => $title,
                    'content_id' => $content_id,
                    'message' => __('Knowledge base entry created successfully', 'aiohm-knowledge-assistant'),
                ),
            ));
        } else {
            return new WP_Error('creation_failed', __('Failed to create knowledge base entry', 'aiohm-knowledge-assistant'), array('status' => 500));
        }
    }
    
    /**
     * Update existing KB entry
     */
    private function handle_update_kb_entry($parameters, $permissions, $token_data = null) {
        if (!in_array('read_write_kb', $permissions)) {
            return new WP_Error('insufficient_permissions', __('Write permission required', 'aiohm-knowledge-assistant'), array('status' => 403));
        }
        
        $entry_id = intval($parameters['entry_id'] ?? 0);
        if ($entry_id <= 0) {
            return new WP_Error('invalid_entry_id', __('Valid entry ID required', 'aiohm-knowledge-assistant'), array('status' => 400));
        }
        
        // Check if entry exists using secure wrapper
        $existing_entry = $this->get_kb_entry_by_id($entry_id);
        if (!$existing_entry) {
            return new WP_Error('entry_not_found', __('Knowledge base entry not found', 'aiohm-knowledge-assistant'), array('status' => 404));
        }
        
        $update_data = array();
        
        if (!empty($parameters['title'])) {
            $update_data['title'] = sanitize_text_field($parameters['title']);
        }
        
        if (!empty($parameters['content'])) {
            $update_data['content'] = wp_kses_post($parameters['content']);
        }
        
        if (isset($parameters['metadata'])) {
            $update_data['metadata'] = wp_json_encode($parameters['metadata']);
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_updates', __('No valid update fields provided', 'aiohm-knowledge-assistant'), array('status' => 400));
        }
        
        $result = $this->update_kb_entry($entry_id, $update_data);
        
        if ($result !== false) {
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'entry_id' => $entry_id,
                    'updated_fields' => array_keys($update_data),
                    'message' => __('Knowledge base entry updated successfully', 'aiohm-knowledge-assistant'),
                ),
            ));
        } else {
            return new WP_Error('update_failed', __('Failed to update knowledge base entry', 'aiohm-knowledge-assistant'), array('status' => 500));
        }
    }
    
    /**
     * Delete KB entry
     */
    private function handle_delete_kb_entry($parameters, $permissions, $token_data = null) {
        if (!in_array('read_write_kb', $permissions)) {
            return new WP_Error('insufficient_permissions', __('Write permission required', 'aiohm-knowledge-assistant'), array('status' => 403));
        }
        
        $entry_id = intval($parameters['entry_id'] ?? 0);
        if ($entry_id <= 0) {
            return new WP_Error('invalid_entry_id', __('Valid entry ID required', 'aiohm-knowledge-assistant'), array('status' => 400));
        }
        
        $result = $this->delete_kb_entry($entry_id);
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'entry_id' => $entry_id,
                    'message' => __('Knowledge base entry deleted successfully', 'aiohm-knowledge-assistant'),
                ),
            ));
        } else {
            return new WP_Error('delete_failed', __('Failed to delete knowledge base entry or entry not found', 'aiohm-knowledge-assistant'), array('status' => 404));
        }
    }
    
    /**
     * Validate MCP token and permissions
     */
    public function validate_mcp_token($request) {
        $token = sanitize_text_field($request->get_param('token'));
        $token_data = $this->get_token_data($token);
        
        if (!$token_data) {
            return rest_ensure_response(array(
                'valid' => false,
                'message' => __('Invalid token', 'aiohm-knowledge-assistant'),
            ));
        }
        
        return rest_ensure_response(array(
            'valid' => true,
            'token_name' => $token_data['token_name'],
            'permissions' => json_decode($token_data['permissions'], true),
            'expires_at' => $token_data['expires_at'],
        ));
    }
    
    /**
     * Validate MCP access for API endpoints
     */
    public function validate_mcp_access($request) {
        $settings = AIOHM_KB_Assistant::get_settings();
        
        // Check if MCP is enabled
        if (empty($settings['mcp_enabled'])) {
            return new WP_Error('mcp_disabled', __('MCP API is disabled', 'aiohm-knowledge-assistant'), array('status' => 503));
        }
        
        // Validate token
        $token_data = $this->get_token_from_request($request);
        if (!$token_data) {
            return false;
        }
        
        // Check rate limiting
        $rate_limit_check = $this->check_mcp_rate_limit($token_data['id'], $request);
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }
        
        // Check access based on token type
        $token_type = isset($token_data['token_type']) ? $token_data['token_type'] : 'private';
        
        if ($token_type === 'private') {
            // Private tokens: Check if the token creator still has MCP access (Private level)
            $token_creator_id = $token_data['created_by'];
            $original_user_id = get_current_user_id();
            
            // Switch to token creator to check their membership
            wp_set_current_user($token_creator_id);
            $has_mcp_access = AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access();
            
            // Restore original user
            wp_set_current_user($original_user_id);
            
            if (!$has_mcp_access) {
                return new WP_Error('membership_expired', __('Token creator no longer has MCP access', 'aiohm-knowledge-assistant'), array('status' => 403));
            }
        }
        // Public tokens: No membership verification required, but limited to public content
        
        return true;
    }
    
    /**
     * Validate MCP action parameter
     */
    public function validate_mcp_action($action) {
        $valid_actions = array('queryKB', 'getKBEntry', 'listKBEntries', 'addKBEntry', 'updateKBEntry', 'deleteKBEntry');
        return in_array($action, $valid_actions);
    }
    
    /**
     * Extract and validate token from request
     */
    private function get_token_from_request($request) {
        $auth_header = $request->get_header('Authorization');
        
        if (empty($auth_header)) {
            return false;
        }
        
        if (!preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        return $this->get_token_data($token);
    }
    
    /**
     * Get token data from database using secure wrapper
     */
    private function get_token_data($token) {
        $token_hash = hash('sha256', $token);
        return $this->get_token_by_hash($token_hash);
    }
    
    /**
     * Check rate limiting for MCP calls
     */
    private function check_rate_limit($token_id) {
        $transient_key = "aiohm_mcp_rate_limit_{$token_id}";
        $current_count = get_transient($transient_key) ?: 0;
        
        // Rate limit: 1000 requests per hour
        $rate_limit = 1000;
        
        if ($current_count >= $rate_limit) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * Generate new MCP token (AJAX handler)
     */
    public function ajax_generate_mcp_token() {
        // Enhanced rate limiting for MCP token generation
        if (!AIOHM_KB_Assistant::check_enhanced_rate_limit('mcp_token_gen', get_current_user_id(), 10)) {
            wp_send_json_error(['message' => __('Token generation rate limit exceeded.', 'aiohm-knowledge-assistant')]);
        }

        check_ajax_referer('aiohm_mcp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'aiohm-knowledge-assistant'));
        }
        
        // Check if user has MCP access (Private level)
        if (!AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access()) {
            wp_send_json_error(array('message' => __('MCP access requires Private level membership', 'aiohm-knowledge-assistant')));
        }
        
        $token_name = isset($_POST['token_name']) ? sanitize_text_field(wp_unslash($_POST['token_name'])) : '';
        $token_type = isset($_POST['token_type']) ? sanitize_text_field(wp_unslash($_POST['token_type'])) : 'private';
        $permissions = isset($_POST['permissions']) ? array_map('sanitize_text_field', wp_unslash($_POST['permissions'])) : array();
        $expires_days = !empty($_POST['expires_days']) ? intval($_POST['expires_days']) : null;
        
        if (empty($token_name)) {
            wp_send_json_error(array('message' => __('Token name is required', 'aiohm-knowledge-assistant')));
        }
        
        // Validate token type
        if (!in_array($token_type, array('public', 'private'), true)) {
            $token_type = 'private';
        }
        
        $token = $this->generate_mcp_token($token_name, $token_type, $permissions, $expires_days);
        
        if ($token) {
            wp_send_json_success(array(
                'token' => $token['token'],
                'token_id' => $token['id'],
                'message' => __('MCP token generated successfully', 'aiohm-knowledge-assistant'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate token', 'aiohm-knowledge-assistant')));
        }
    }
    
    /**
     * Generate MCP token using secure wrapper
     */
    private function generate_mcp_token($token_name, $token_type = 'private', $permissions = array(), $expires_days = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiohm_mcp_tokens';
        
        // Validate table name against whitelist
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            AIOHM_KB_Assistant::log("Invalid table name for MCP token generation: " . $table_name, 'error');
            return false;
        }
        
        // Check if table exists using WordPress method
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- MCP table existence check required for token generation
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            AIOHM_KB_Assistant::log("MCP tokens table does not exist: " . $table_name, 'error');
            return false;
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        
        $expires_at = null;
        if ($expires_days) {
            $expires_at = gmdate('Y-m-d H:i:s', time() + ($expires_days * DAY_IN_SECONDS));
        }
        
        $token_data = array(
            'token_name' => $token_name,
            'token_hash' => $token_hash,
            'token_type' => $token_type,
            'permissions' => wp_json_encode($permissions),
            'expires_at' => $expires_at,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'is_active' => 1,
        );
        
        $result = $this->create_mcp_token($token_data);
        
        if ($result) {
            AIOHM_KB_Assistant::log("MCP token created: {$token_name}", 'info');
            return array(
                'id' => $wpdb->insert_id,
                'token' => $token,
            );
        } else {
            AIOHM_KB_Assistant::log("MCP token creation failed. Database error: " . $wpdb->last_error, 'error');
            AIOHM_KB_Assistant::log("MCP token table name: " . $table_name, 'error');
        }
        
        return false;
    }
    
    /**
     * Revoke MCP token (AJAX handler)
     */
    public function ajax_revoke_mcp_token() {
        try {
            check_ajax_referer('aiohm_mcp_nonce', 'nonce');
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Nonce verification failed: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'aiohm-knowledge-assistant')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'aiohm-knowledge-assistant')));
        }
        
        // Check if user has MCP access (Private level)
        if (!AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access()) {
            wp_send_json_error(array('message' => __('MCP access requires Private level membership', 'aiohm-knowledge-assistant')));
        }
        
        $token_id = intval($_POST['token_id'] ?? 0);
        
        if (empty($token_id)) {
            wp_send_json_error(array('message' => __('Token ID is required', 'aiohm-knowledge-assistant')));
        }
        
        AIOHM_KB_Assistant::log("Attempting to revoke token with ID: {$token_id}", 'info');
        
        if ($this->revoke_mcp_token($token_id)) {
            wp_send_json_success(array('message' => __('Token revoked successfully', 'aiohm-knowledge-assistant')));
        } else {
            wp_send_json_error(array('message' => __('Failed to revoke token', 'aiohm-knowledge-assistant')));
        }
    }
    
    /**
     * Remove MCP token (AJAX handler) - permanently delete the token
     */
    public function ajax_remove_mcp_token() {
        try {
            check_ajax_referer('aiohm_mcp_nonce', 'nonce');
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Nonce verification failed: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'aiohm-knowledge-assistant')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'aiohm-knowledge-assistant')));
        }
        
        // Check if user has MCP access (Private level)
        if (!AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access()) {
            wp_send_json_error(array('message' => __('MCP access requires Private level membership', 'aiohm-knowledge-assistant')));
        }
        
        $token_id = intval($_POST['token_id'] ?? 0);
        
        if (empty($token_id)) {
            wp_send_json_error(array('message' => __('Token ID is required', 'aiohm-knowledge-assistant')));
        }
        
        AIOHM_KB_Assistant::log("Attempting to remove token with ID: {$token_id}", 'info');
        
        if ($this->remove_mcp_token($token_id)) {
            wp_send_json_success(array('message' => __('Token removed successfully', 'aiohm-knowledge-assistant')));
        } else {
            wp_send_json_error(array('message' => __('Failed to remove token', 'aiohm-knowledge-assistant')));
        }
    }
    
    /**
     * Revoke MCP token using secure wrapper
     */
    private function revoke_mcp_token($token_id) {
        // First check if token exists and is active using secure wrapper
        $existing_token = $this->get_mcp_token_by_id($token_id);
        
        if (!$existing_token) {
            AIOHM_KB_Assistant::log("Revoke failed: Token {$token_id} not found", 'error');
            return false;
        }
        
        // Handle both string and integer values for is_active
        $is_active = (int) $existing_token['is_active'];
        if (!$is_active) {
            AIOHM_KB_Assistant::log("Revoke failed: Token {$token_id} already inactive (is_active: {$existing_token['is_active']})", 'info');
            return false;
        }
        
        $result = $this->update_mcp_token($token_id, array('is_active' => 0));
        
        if ($result !== false) {
            AIOHM_KB_Assistant::log("MCP token revoked successfully: ID {$token_id}, rows affected: {$result}", 'info');
            return true;
        } else {
            AIOHM_KB_Assistant::log("MCP token revoke failed: ID {$token_id}", 'error');
            return false;
        }
    }
    
    /**
     * Remove MCP token - permanently delete from database using secure wrapper
     */
    private function remove_mcp_token($token_id) {
        // First check if token exists using secure wrapper
        $existing_token = $this->get_mcp_token_by_id($token_id);
        
        if (!$existing_token) {
            AIOHM_KB_Assistant::log("Remove failed: Token {$token_id} not found", 'error');
            return false;
        }
        
        // Log what we're about to delete
        AIOHM_KB_Assistant::log("Removing token: ID {$token_id}, Name: {$existing_token['token_name']}, Active: {$existing_token['is_active']}", 'info');
        
        // Delete the token completely from database using secure wrapper
        $result = $this->delete_mcp_token($token_id);
        
        if ($result !== false && $result > 0) {
            AIOHM_KB_Assistant::log("MCP token removed successfully: ID {$token_id}, rows deleted: {$result}", 'info');
            return true;
        } else {
            AIOHM_KB_Assistant::log("MCP token remove failed: ID {$token_id}", 'error');
            return false;
        }
    }

    /**
     * Check MCP rate limiting for token
     * @param int $token_id Token ID
     * @param WP_REST_Request $request Request object for IP address
     * @return true|WP_Error True if within limits, WP_Error if exceeded
     */
    private function check_mcp_rate_limit($token_id, $request) {
        $settings = AIOHM_KB_Assistant::get_settings();
        $rate_limit = $settings['mcp_rate_limit'] ?? 1000; // Default 1000 requests per hour
        
        // Get client IP for additional protection
        $client_ip = $this->get_client_ip($request);
        
        // Token-based rate limit
        $token_key = "mcp_rate_limit_token_{$token_id}";
        $token_count = get_transient($token_key);
        
        // IP-based rate limit (prevent bypass by creating multiple tokens)
        $ip_key = "mcp_rate_limit_ip_" . md5($client_ip);
        $ip_count = get_transient($ip_key);
        
        // Initialize counters if they don't exist
        if ($token_count === false) {
            set_transient($token_key, 1, HOUR_IN_SECONDS);
            $token_count = 1;
        }
        
        if ($ip_count === false) {
            set_transient($ip_key, 1, HOUR_IN_SECONDS);
            $ip_count = 1;
        }
        
        // Check if either limit is exceeded
        if ($token_count >= $rate_limit || $ip_count >= $rate_limit) {
            AIOHM_KB_Assistant::log("MCP rate limit exceeded - Token: {$token_id}, IP: {$client_ip}, Token Count: {$token_count}, IP Count: {$ip_count}", 'warning');
            
            return new WP_Error(
                'rate_limit_exceeded', 
                __('MCP API rate limit exceeded. Please try again later.', 'aiohm-knowledge-assistant'),
                array('status' => 429)
            );
        }
        
        // Increment both counters
        set_transient($token_key, $token_count + 1, HOUR_IN_SECONDS);
        set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);
        
        // Log usage for monitoring
        $this->log_mcp_usage($token_id, $request);
        
        return true;
    }

    /**
     * Get client IP address securely
     * @param WP_REST_Request $request Request object
     * @return string Client IP address
     */
    private function get_client_ip($request) {
        // Try to get IP from request headers (for proxies/load balancers)
        $headers = $request->get_headers();
        
        // Check common proxy headers
        if (!empty($headers['x_forwarded_for'][0])) {
            $ip = sanitize_text_field($headers['x_forwarded_for'][0]);
        } elseif (!empty($headers['x_real_ip'][0])) {
            $ip = sanitize_text_field($headers['x_real_ip'][0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } else {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        }
        
        // Validate IP address
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0';
        }
        
        return $ip;
    }

    /**
     * Log MCP API usage for monitoring and analytics
     * @param int $token_id Token ID
     * @param WP_REST_Request $request Request object
     */
    private function log_mcp_usage($token_id, $request) {
        global $wpdb;
        
        $endpoint = $request->get_route();
        $method = $request->get_method();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $ip_address = $this->get_client_ip($request);
        
        // Don't log too much request data for privacy
        $request_data = json_encode([
            'method' => $method,
            'endpoint' => $endpoint,
            'params_count' => count($request->get_params())
        ]);
        
        $table_name = $wpdb->prefix . 'aiohm_mcp_usage';
        $validation = $this->validate_table_name($table_name);
        if (is_wp_error($validation)) {
            return;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- MCP usage logging requires direct insert for performance
        $wpdb->insert(
            $table_name,
            [
                'token_id' => $token_id,
                'endpoint' => $endpoint,
                'action' => $method,
                'request_data' => $request_data,
                'response_status' => 200, // Will be updated if error occurs
                'user_agent' => $user_agent,
                'ip_address' => $ip_address
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );
    }
    
    /**
     * List MCP tokens (AJAX handler)
     */
    public function ajax_list_mcp_tokens() {
        check_ajax_referer('aiohm_mcp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'aiohm-knowledge-assistant'));
        }
        
        // Check if user has MCP access (Private level)
        if (!AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access()) {
            wp_send_json_error(array('message' => __('MCP access requires Private level membership', 'aiohm-knowledge-assistant')));
        }
        
        $tokens = $this->list_mcp_tokens();
        wp_send_json_success(array('tokens' => $tokens));
    }
    
    /**
     * List MCP tokens using secure wrapper
     */
    private function list_mcp_tokens() {
        $tokens = $this->get_all_mcp_tokens();
        
        foreach ($tokens as &$token) {
            $token['permissions'] = json_decode($token['permissions'], true);
        }
        
        return $tokens;
    }
    
    /**
     * View MCP token (AJAX handler)
     */
    public function ajax_view_mcp_token() {
        check_ajax_referer('aiohm_mcp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'aiohm-knowledge-assistant'));
        }
        
        // Check if user has MCP access (Private level)
        if (!AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access()) {
            wp_send_json_error(array('message' => __('MCP access requires Private level membership', 'aiohm-knowledge-assistant')));
        }
        
        $token_id = intval($_POST['token_id'] ?? 0);
        
        if (empty($token_id)) {
            wp_send_json_error(array('message' => __('Token ID is required', 'aiohm-knowledge-assistant')));
        }
        
        $token_details = $this->get_mcp_token_details($token_id);
        
        if ($token_details) {
            wp_send_json_success(array(
                'token_details' => $token_details,
                'message' => __('Token details retrieved successfully', 'aiohm-knowledge-assistant'),
            ));
        } else {
            wp_send_json_error(array('message' => __('Token not found', 'aiohm-knowledge-assistant')));
        }
    }
    
    /**
     * Get MCP token details using secure wrapper (without the actual token value for security)
     */
    private function get_mcp_token_details($token_id) {
        $token = $this->get_mcp_token_by_id($token_id);
        
        if ($token) {
            $token['permissions'] = json_decode($token['permissions'], true);
            
            // Get creator info
            $creator = get_user_by('ID', $token['created_by']);
            $token['created_by_name'] = $creator ? $creator->display_name : __('Unknown', 'aiohm-knowledge-assistant');
            
            // Add token preview (first 8 characters + dots for security)
            if (!empty($token['token_hash'])) {
                $token['token_preview'] = substr($token['token_hash'], 0, 8) . '••••••••••••••••';
            } else {
                $token['token_preview'] = '••••••••••••••••';
            }
            
            // Remove the full token_hash from response for security
            unset($token['token_hash']);
        }
        
        return $token;
    }
    
    public static function init() {
        return self::get_instance();
    }
}