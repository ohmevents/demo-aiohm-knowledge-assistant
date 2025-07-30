<?php
/**
 * Handles the display and processing of the "Manage Knowledge Base" admin page.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class AIOHM_KB_List_Table extends WP_List_Table {
    private $rag_engine;

    function __construct() {
        parent::__construct(['singular' => 'kb_entry', 'plural' => 'kb_entries', 'ajax' => false]);
        $this->rag_engine = new AIOHM_KB_RAG_Engine();
    }

    function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'title'        => __('Title', 'aiohm-knowledge-assistant'),
            'content_type' => __('Content Type', 'aiohm-knowledge-assistant'),
            'user_id'      => __('Visibility', 'aiohm-knowledge-assistant'),
            'last_updated' => __('Last Updated', 'aiohm-knowledge-assistant'),
            'scope_toggle' => __('Actions', 'aiohm-knowledge-assistant'),
        ];
    }
    
    /**
     * Defines which columns are sortable.
     *
     * @return array
     */
    protected function get_sortable_columns() {
        $sortable_columns = [
            'title'        => ['title', false],
            'content_type' => ['content_type', false],
            'user_id'      => ['user_id', false],
            'last_updated' => ['created_at', true],
        ];
        return $sortable_columns;
    }


    function column_cb($item) {
        return sprintf('<input type="checkbox" name="entry_ids[]" value="%s" />', esc_attr($item['content_id']));
    }



    function column_scope_toggle($item) {
        $action_links_html = [];
        $current_user_id = get_current_user_id();
        $is_public = isset($item['is_public']) ? intval($item['is_public']) : 0;
        $is_mine = ($item['user_id'] == $current_user_id);
        
        // Toggle Scope link - admins can toggle any content between public/private
        $new_scope = $is_public === 1 ? 'private' : 'public';
        $button_text = $is_public === 1 ? 'Make Private' : 'Make Public';
        $button_class = $is_public === 1 ? 'make-private' : 'make-public';
        $action_links_html[] = sprintf(
            '<a href="#" class="scope-toggle-btn %s" data-content-id="%s" data-new-scope="%s">%s</a>',
            $button_class,
            esc_attr($item['content_id']),
            $new_scope,
            $button_text
        );
        
        // View Button - Enhanced to handle Brand Soul content type
        $metadata = isset($item['metadata']) ? json_decode($item['metadata'], true) : null;
        $content_type = $item['content_type'] ?? '';
        
        // Enhanced View button logic for different content types
        
        // Content types that need modal view (Brand Soul, Brand Core, JSON, TXT, etc.)
        $modal_content_types = [
            'brand-soul', 'brand_soul', 'brand-core', 'brand_core', 'github', 'repository', 
            'contact', 'contact_type', 'conversation', 'JSON', 'text/plain', 'TXT',
            'text/csv', 'CSV', 'text/markdown', 'MD', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
            'application/msword', 'DOC', 'DOCX', 'manual', 'project_note'
        ];
        
        // Content types that can be edited
        $editable_content_types = ['JSON', 'TXT', 'text/plain', 'MD', 'text/markdown', 'project_note'];
        
        if (in_array($content_type, $modal_content_types)) {
            $action_links_html[] = sprintf(
                '<a href="#" class="view-content-btn" data-content-id="%s" data-content-type="%s">%s</a>',
                esc_attr($item['content_id']),
                esc_attr($content_type),
                __('View', 'aiohm-knowledge-assistant')
            );
        }
        // PDF files - always show modal view for content like other text files
        elseif ($content_type === 'application/pdf' || $content_type === 'PDF') {
            // Always use modal view to show PDF content in popup
            $action_links_html[] = sprintf(
                '<a href="#" class="view-content-btn" data-content-id="%s" data-content-type="%s">%s</a>',
                esc_attr($item['content_id']),
                esc_attr($content_type),
                __('View', 'aiohm-knowledge-assistant')
            );
        }
        // Links/URLs - always show View button
        elseif (is_array($metadata) && isset($metadata['url'])) {
            $action_links_html[] = sprintf('<a href="%s" target="_blank" class="view-link-btn">%s</a>', esc_url($metadata['url']), __('View', 'aiohm-knowledge-assistant'));
        }
        // Other content with metadata (posts, pages, attachments)
        elseif (is_array($metadata)) {
            $view_url = '';
            $view_text = __('View', 'aiohm-knowledge-assistant');

            if (isset($metadata['post_id']) && get_post_type($metadata['post_id'])) {
                $view_url = get_permalink($metadata['post_id']);
            } elseif (isset($metadata['attachment_id'])) {
                $view_url = wp_get_attachment_url($metadata['attachment_id']);
            }

            if ($view_url) {
                $action_links_html[] = sprintf('<a href="%s" target="_blank">%s</a>', esc_url($view_url), $view_text);
            }
        }
        // Fallback: if no metadata but content exists, show modal view
        elseif (!empty($item['content'])) {
            $action_links_html[] = sprintf(
                '<a href="#" class="view-content-btn" data-content-id="%s" data-content-type="%s">%s</a>',
                esc_attr($item['content_id']),
                esc_attr($content_type),
                __('View', 'aiohm-knowledge-assistant')
            );
        }

        // Delete button
        $delete_nonce = wp_create_nonce('aiohm_delete_entry_nonce');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter safe for admin URL building
        $page = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : '';
        $delete_url = sprintf('?page=%s&action=delete&content_id=%s&_wpnonce=%s', esc_attr($page), esc_attr($item['content_id']), $delete_nonce);
        $action_links_html[] = sprintf('<a href="%s" onclick="return confirm(\'%s\')" class="button-link-delete">Delete</a>', 
            $delete_url, 
            esc_js(__('Are you sure you want to delete this entry?', 'aiohm-knowledge-assistant')));
        
        return implode(' | ', $action_links_html);
    }

    function get_bulk_actions() {
        return [
            'bulk-delete' => __('Delete', 'aiohm-knowledge-assistant'),
            'make-public' => __('Make Selected Public', 'aiohm-knowledge-assistant'),
            'make-private' => __('Make Selected Private', 'aiohm-knowledge-assistant'),
        ];
    }
    
    function process_bulk_action() {
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // Bulk actions are handled by custom AJAX handlers
        // This method is kept for WordPress compatibility
    }

    function column_default($item, $column_name) {
        // $column_name will now always be a string (the column slug)
        switch ($column_name) {
            case 'content_type':
                $type = esc_html($item['content_type']);
                $display_type = $type;
                $type_class = '';

                // Enhance display for common types and assign classes
                if ($type === 'application/pdf' || $type === 'PDF') {
                    $display_type = 'PDF';
                    $type_class = 'type-pdf';
                } elseif ($type === 'JSON') {
                    $display_type = 'JSON';
                    $type_class = 'type-json';
                } elseif ($type === 'text/plain' || $type === 'TXT') {
                    $display_type = 'TXT';
                    $type_class = 'type-txt';
                } elseif ($type === 'text/csv' || $type === 'CSV') {
                    $display_type = 'CSV';
                    $type_class = 'type-csv';
                } elseif ($type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $type === 'application/msword' || $type === 'DOC' || $type === 'DOCX') {
                    $display_type = 'DOC';
                    $type_class = 'type-doc';
                } elseif ($type === 'text/markdown' || $type === 'text/x-markdown' || $type === 'MD') {
                    $display_type = 'MD';
                    $type_class = 'type-md';
                } elseif ($type === 'post') {
                    $display_type = 'Post';
                    $type_class = 'type-post';
                } elseif ($type === 'page') {
                    $display_type = 'Page';
                    $type_class = 'type-page';
                } elseif ($type === 'manual') {
                    $display_type = 'Manual';
                    $type_class = 'type-manual';
                } elseif ($type === 'brand-soul' || $type === 'brand_soul') {
                    $display_type = 'Brand Soul';
                    $type_class = 'type-brand-soul';
                } elseif ($type === 'brand-core' || $type === 'brand_core') {
                    $display_type = 'Brand Core';
                    $type_class = 'type-brand-core';
                } elseif ($type === 'github' || $type === 'repository') {
                    $display_type = 'GitHub';
                    $type_class = 'type-github';
                } elseif ($type === 'contact' || $type === 'contact_type') {
                    $display_type = 'Contact';
                    $type_class = 'type-contact';
                } elseif ($type === 'project_note') {
                    $display_type = 'Note';
                    $type_class = 'type-note';
                } elseif ($type === 'conversation') {
                    $display_type = 'CHAT';
                    $type_class = 'type-chat';
                } else {
                    if (strpos($type, '/') !== false) {
                        $main_type = explode('/', $type)[0];
                        $type_class = 'type-' . esc_attr($main_type);
                        $display_type = strtoupper($main_type);
                    } else {
                        $type_class = 'type-default';
                    }
                }
                
                return sprintf('<span class="aiohm-content-type-badge %s">%s</span>', $type_class, $display_type);

            case 'user_id':
                $is_public = isset($item['is_public']) ? intval($item['is_public']) : 0;
                $visibility = $is_public === 1 ? 'Public' : 'Private';
                $visibility_class = $is_public === 1 ? 'visibility-public' : 'visibility-private';
                return sprintf('<span class="visibility-text %s">%s</span>', esc_attr($visibility_class), $visibility);

            case 'last_updated':
                return isset($item['created_at']) ? esc_html(gmdate('Y-m-d H:i', strtotime($item['created_at']))) : 'N/A';
            case 'title': // Explicitly handle 'title' for its bold formatting
                return sprintf('<strong>%s</strong>', esc_html($item['title']));
            default:
                // For any other column not explicitly handled, just return the item's value
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
        }
    }

    /**
     * Renders the custom filters in the table nav area.
     *
     * @param string $which The part of the table nav to render (top or bottom).
     */
    function extra_tablenav($which) {
        // Render filters at the top, on the same line as bulk actions
        if ($which === 'top') {
            ?>
            <div class="alignleft actions filters-block">
                <label for="filter-content-type" class="screen-reader-text">Filter by Content Type</label>
                <select name="content_type" id="filter-content-type">
                    <option value=""><?php esc_html_e('All Types', 'aiohm-knowledge-assistant'); ?></option>
                    <?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET parameters safe for admin filter form ?>
                    <option value="post" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'post'); ?>><?php esc_html_e('Posts', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="page" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'page'); ?>><?php esc_html_e('Pages', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="application/pdf" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'application/pdf'); ?>><?php esc_html_e('PDFs', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="text/plain" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'text/plain'); ?>><?php esc_html_e('TXT', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="text/csv" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'text/csv'); ?>><?php esc_html_e('CSV', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="JSON" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'JSON'); ?>><?php esc_html_e('JSON', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="manual" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'manual'); ?>><?php esc_html_e('Manual Entries', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="brand-soul" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'brand-soul'); ?>><?php esc_html_e('Brand Soul', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="brand-core" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'brand-core'); ?>><?php esc_html_e('Brand Core', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="github" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'github'); ?>><?php esc_html_e('GitHub', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="contact" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'contact'); ?>><?php esc_html_e('Contact', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="project_note" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'project_note'); ?>><?php esc_html_e('Notes', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="conversation" <?php selected(isset($_GET['content_type']) ? sanitize_text_field(wp_unslash($_GET['content_type'])) : '', 'conversation'); ?>><?php esc_html_e('Conversations', 'aiohm-knowledge-assistant'); ?></option>
                </select>

                <label for="filter-visibility" class="screen-reader-text">Filter by Visibility</label>
                <select name="visibility" id="filter-visibility">
                    <option value=""><?php esc_html_e('All Visibility', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="public" <?php selected(isset($_GET['visibility']) ? sanitize_text_field(wp_unslash($_GET['visibility'])) : '', 'public'); ?>><?php esc_html_e('Public', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="private" <?php selected(isset($_GET['visibility']) ? sanitize_text_field(wp_unslash($_GET['visibility'])) : '', 'private'); ?>><?php esc_html_e('Private', 'aiohm-knowledge-assistant'); ?></option>
                </select>

                <label for="filter-date-range" class="screen-reader-text">Filter by Date Range</label>
                <select name="date_range" id="filter-date-range">
                    <option value=""><?php esc_html_e('All Dates', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="last_7_days" <?php selected(isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '', 'last_7_days'); ?>><?php esc_html_e('Last 7 Days', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="last_30_days" <?php selected(isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '', 'last_30_days'); ?>><?php esc_html_e('Last 30 Days', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="this_month" <?php selected(isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '', 'this_month'); ?>><?php esc_html_e('This Month', 'aiohm-knowledge-assistant'); ?></option>
                    <option value="this_year" <?php selected(isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '', 'this_year'); ?>><?php esc_html_e('This Year', 'aiohm-knowledge-assistant'); ?></option>
                </select>
                <?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

                <?php submit_button(__('Filter', 'aiohm-knowledge-assistant'), 'button', false, false, ['id' => 'post-query-submit']); ?>
            </div>
            <?php
        }
    }


    function prepare_items() {
        global $wpdb;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $where_clauses = ['1=1'];
        $query_args = [];

        // Removed search handling as per user request
        // if (isset($_GET['s']) && !empty($_GET['s'])) { ... }

        // Content type filter - admin interface only, no nonce required for GET filters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET filter parameters safe for admin list filtering
        if (isset($_GET['content_type']) && !empty($_GET['content_type'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter parameter
            $content_type = sanitize_text_field(wp_unslash($_GET['content_type']));
            $where_clauses[] = "content_type = %s";
            $query_args[] = $content_type;
        }

        // Visibility filter - admin interface only, no nonce required for GET filters  
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET filter parameters safe for admin list filtering
        if (isset($_GET['visibility']) && !empty($_GET['visibility'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter parameter
            $visibility = sanitize_text_field(wp_unslash($_GET['visibility']));
            if ($visibility === 'public') {
                $where_clauses[] = "is_public = 1";
            } elseif ($visibility === 'private') {
                $where_clauses[] = "is_public = 0";
            }
        }

        // Date range filter - admin interface only, no nonce required for GET filters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET filter parameters safe for admin list filtering
        if (isset($_GET['date_range']) && !empty($_GET['date_range'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter parameter
            $date_range = sanitize_text_field(wp_unslash($_GET['date_range']));
            $current_time = current_time('mysql');
            switch ($date_range) {
                case 'last_7_days':
                    $where_clauses[] = "created_at >= DATE_SUB(%s, INTERVAL 7 DAY)";
                    $query_args[] = $current_time;
                    break;
                case 'last_30_days':
                    $where_clauses[] = "created_at >= DATE_SUB(%s, INTERVAL 30 DAY)";
                    $query_args[] = $current_time;
                    break;
                case 'this_month':
                    $where_clauses[] = "YEAR(created_at) = YEAR(%s) AND MONTH(created_at) = MONTH(%s)";
                    $query_args[] = $current_time;
                    $query_args[] = $current_time;
                    break;
                case 'this_year':
                    $where_clauses[] = "YEAR(created_at) = YEAR(%s)";
                    $query_args[] = $current_time;
                    break;
            }
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Determine sorting
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters safe for admin list ordering
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby(wp_unslash($_GET['orderby'])) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin ordering parameter
        $order = isset($_GET['order']) ? sanitize_sql_orderby(wp_unslash($_GET['order'])) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin ordering parameter

        // Validate orderby against sortable columns to prevent SQL injection
        $sortable_columns = $this->get_sortable_columns();
        if (!array_key_exists($orderby, $sortable_columns)) {
            $orderby = 'id'; // Fallback to default if invalid orderby is provided
            $order = 'DESC';
        } else {
            $orderby = $sortable_columns[$orderby][0]; // Use the actual database column name
        }


        // Get total items count (respecting filters) with caching
        $table_name = $wpdb->prefix . 'aiohm_vector_entries';
        $total_items_query_args = $query_args;
        
        // Create cache key based on filters
        $cache_key = 'aiohm_kb_count_' . md5($where_sql . serialize($total_items_query_args));
        $total_items = wp_cache_get($cache_key, 'aiohm_kb_manager');
        
        if (false === $total_items) {
            if (!empty($where_clauses) && !empty($total_items_query_args)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe, from system
                $sql = "SELECT COUNT(DISTINCT content_id) FROM {$wpdb->prefix}aiohm_vector_entries WHERE " . $where_sql;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Count query with prepared statement and caching
                $total_items = (int) $wpdb->get_var($wpdb->prepare($sql, $total_items_query_args));
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple count query with caching
                $total_items = (int) $wpdb->get_var("SELECT COUNT(DISTINCT content_id) FROM {$wpdb->prefix}aiohm_vector_entries");
            }
            
            // Cache for 5 minutes
            wp_cache_set($cache_key, $total_items, 'aiohm_kb_manager', 300);
        }

        $this->set_pagination_args(['total_items' => $total_items, 'per_page' => $per_page]);

        $offset = ($current_page - 1) * $per_page;
        
        // Create cache key for items query
        $items_cache_key = 'aiohm_kb_items_' . md5($where_sql . serialize($query_args) . $orderby . $order . $per_page . $offset);
        $this->items = wp_cache_get($items_cache_key, 'aiohm_kb_manager');
        
        if (false === $this->items) {
            if (!empty($where_clauses) && !empty($query_args)) {
                array_push($query_args, $per_page, $offset);
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe, from system
                $sql = "SELECT id, title, content_type, user_id, content_id, created_at, metadata, content, is_public FROM {$wpdb->prefix}aiohm_vector_entries WHERE " . $where_sql . " GROUP BY content_id ORDER BY " . esc_sql($orderby) . " " . esc_sql($order) . " LIMIT %d OFFSET %d";
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Admin list query with prepared statement and caching
                $this->items = $wpdb->get_results($wpdb->prepare($sql, $query_args), ARRAY_A);
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe, from system
                $sql = "SELECT id, title, content_type, user_id, content_id, created_at, metadata, content, is_public FROM {$wpdb->prefix}aiohm_vector_entries GROUP BY content_id ORDER BY " . esc_sql($orderby) . " " . esc_sql($order) . " LIMIT %d OFFSET %d";
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Admin list query with prepared statement and caching
                $this->items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset), ARRAY_A);
            }
            
            // Cache for 2 minutes (shorter for admin interface)
            wp_cache_set($items_cache_key, $this->items, 'aiohm_kb_manager', 120);
        }
    }
}

class AIOHM_KB_Manager {
    private $rag_engine;
    private $list_table;

    public function __construct() {
        $this->rag_engine = new AIOHM_KB_RAG_Engine();
        $this->list_table = new AIOHM_KB_List_Table();
    }
    
    public function display_page() {
        $this->handle_actions();
        $list_table = $this->list_table;
        $list_table->prepare_items();
        $settings = AIOHM_KB_Assistant::get_settings();
        include_once AIOHM_KB_PLUGIN_DIR . 'templates/admin-manage-kb.php';
    }

    private function handle_actions() {
        // Handle custom bulk actions first
        if (isset($_POST['aiohm_bulk_action']) && isset($_POST['entry_ids']) && !empty($_POST['entry_ids'])) {
            $this->handle_custom_bulk_action();
        }
        
        // Process WP_List_Table bulk actions (keeping this for compatibility)
        $this->list_table->process_bulk_action();
        
        $current_action = $this->list_table->current_action();
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';

        if ('delete' === $current_action && isset($_GET['content_id']) && wp_verify_nonce($nonce, 'aiohm_delete_entry_nonce')) {
            if ($this->rag_engine->delete_entry_by_content_id(sanitize_text_field(wp_unslash($_GET['content_id'])))) {
                // Clear cache after successful delete
                $this->clear_kb_manager_cache();
                // Admin notice handled by JS in admin-manage-kb.php for single actions
            } else {
                // Notifying error in JS
            }
        }
        // Note: Bulk actions are now handled by the WP_List_Table's process_bulk_action() method
    }
    
    /**
     * Handle custom bulk actions that bypass WP_List_Table
     */
    private function handle_custom_bulk_action() {
        // Verify nonce
        if (!isset($_POST['aiohm_bulk_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiohm_bulk_nonce'])), 'aiohm_bulk_action')) {
            wp_die(esc_html__('Security check failed.', 'aiohm-knowledge-assistant'));
        }
        
        $action = isset($_POST['aiohm_bulk_action']) ? sanitize_text_field(wp_unslash($_POST['aiohm_bulk_action'])) : '';
        $entry_ids = isset($_POST['entry_ids']) ? array_map('sanitize_text_field', wp_unslash($_POST['entry_ids'])) : [];
        
        try {
            switch ($action) {
                case 'delete':
                    $deleted_count = 0;
                    foreach ($entry_ids as $content_id) {
                        if ($this->rag_engine->delete_entry_by_content_id($content_id)) {
                            $deleted_count++;
                        }
                    }
                    $this->clear_kb_manager_cache();
                    break;
                    
                case 'make_public':
                    $updated_count = 0;
                    foreach ($entry_ids as $content_id) {
                        if ($this->rag_engine->update_entry_visibility_by_content_id($content_id, 1)) {
                            $updated_count++;
                        }
                    }
                    $this->clear_kb_manager_cache();
                    break;
                    
                case 'make_private':
                    $updated_count = 0;
                    foreach ($entry_ids as $content_id) {
                        if ($this->rag_engine->update_entry_visibility_by_content_id($content_id, 0)) {
                            $updated_count++;
                        }
                    }
                    $this->clear_kb_manager_cache();
                    break;
            }
        } catch (Exception $e) {
            wp_die(esc_html__('An error occurred during bulk action: ', 'aiohm-knowledge-assistant') . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Clear all cache related to KB manager queries
     */
    private function clear_kb_manager_cache() {
        // Clear all cached queries for KB manager
        wp_cache_flush_group('aiohm_kb_manager');
    }
}