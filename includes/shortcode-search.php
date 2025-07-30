<?php
/**
 * Search shortcode implementation - [aiohm_search]
 * This version includes a fully functional AJAX handler and layout fixes.
 * Refactored to move search logic into a reusable private method.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AIOHM_KB_Shortcode_Search {
    
    public static function init() {
        add_shortcode('aiohm_search', array(__CLASS__, 'render_search_shortcode'));
        add_action('wp_ajax_aiohm_search_knowledge', array(__CLASS__, 'handle_search_ajax'));
        add_action('wp_ajax_nopriv_aiohm_search_knowledge', array(__CLASS__, 'handle_search_ajax'));
    }
    
    public static function render_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => __('Search knowledge base...', 'aiohm-knowledge-assistant'),
            'show_categories' => 'true',
            'show_results_count' => 'true',
            'max_results' => '10',
            'excerpt_length' => '150',
            'show_content_type' => 'true',
            'enable_instant_search' => 'true',
            'min_chars' => '3'
        ), $atts, 'aiohm_search');
        
        static $search_counter = 0;
        $search_counter++;
        $search_id = 'aiohm-search-' . $search_counter;
        
        // Register and enqueue search script
        wp_register_script(
            'aiohm-search',
            plugin_dir_url(__FILE__) . '../assets/js/aiohm-search-shortcode.js',
            array('jquery'),
            '1.0.0',
            true
        );
        wp_enqueue_script('aiohm-search');
        
        // Register and enqueue search styles
        wp_register_style(
            'aiohm-search',
            plugin_dir_url(__FILE__) . '../assets/css/aiohm-chat.css',
            array(),
            '1.0.0'
        );
        wp_enqueue_style('aiohm-search');
        
        $output = '<div class="aiohm-search-wrapper">';

        $output .= '<div class="aiohm-search-container" id="' . esc_attr($search_id) . '">';
        
        $output .= '<div class="aiohm-search-controls">';
        $output .= '<div class="aiohm-search-form">';
        $output .= '<div class="aiohm-search-input-wrapper">';
        $output .= '<input type="text" class="aiohm-search-input" placeholder="' . esc_attr($atts['placeholder']) . '" />';
        $output .= '<button type="button" class="aiohm-search-btn">';
        $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>';
        $output .= '</button>';
        $output .= '</div>';
        $output .= '</div>';
        
        if ($atts['show_categories'] === 'true') {
            $output .= '<div class="aiohm-search-filters">';
            $output .= '<select class="aiohm-content-type-filter">';
            $output .= '<option value="">' . __('All Types', 'aiohm-knowledge-assistant') . '</option>';
            $output .= '<option value="post">' . __('Posts', 'aiohm-knowledge-assistant') . '</option>';
            $output .= '<option value="page">' . __('Pages', 'aiohm-knowledge-assistant') . '</option>';
            $output .= '<option value="application/pdf">' . __('Documents', 'aiohm-knowledge-assistant') . '</option>';
            $output .= '</select>';
            $output .= '</div>';
        }
        $output .= '</div>'; // .aiohm-search-controls
        
        $output .= '<div class="aiohm-search-status" style="display: none;">';
        $output .= '<span class="aiohm-search-loading">' . __('Searching...', 'aiohm-knowledge-assistant') . '</span>';
        $output .= '</div>';
        
        $output .= '<div class="aiohm-search-results"></div>';
        
        $output .= '</div>'; // .aiohm-search-container
        
        $search_config = array(
            'search_id' => $search_id,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiohm_search_nonce'),
            'settings' => array(
                'max_results' => intval($atts['max_results']),
                'excerpt_length' => intval($atts['excerpt_length']),
                'show_content_type' => $atts['show_content_type'] === 'true',
                'enable_instant_search' => $atts['enable_instant_search'] === 'true',
                'min_chars' => intval($atts['min_chars']),
                'show_results_count' => $atts['show_results_count'] === 'true'
            ),
            'strings' => array(
                'no_results' => __('No results found for your search.', 'aiohm-knowledge-assistant'),
                'error' => __('Search failed. Please try again.', 'aiohm-knowledge-assistant'),
                // translators: %d is the number of search results found
                'results_count' => __('Found %d result(s)', 'aiohm-knowledge-assistant'),
                'searching' => __('Searching...', 'aiohm-knowledge-assistant')
            )
        );
        
        // Localize script data instead of inline script
        wp_add_inline_script('aiohm-search', 
            'if (typeof window.aiohm_search_configs === "undefined") window.aiohm_search_configs = {};' .
            'window.aiohm_search_configs["' . esc_js($search_id) . '"] = ' . wp_json_encode($search_config) . ';'
        );

        $output .= '</div>'; // .aiohm-search-wrapper
        
        return $output;
    }

    /**
     * Handles the AJAX request for searching the knowledge base.
     */
    public static function handle_search_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aiohm_search_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $content_type_filter = isset($_POST['content_type_filter']) ? sanitize_text_field(wp_unslash($_POST['content_type_filter'])) : '';
        $max_results = isset($_POST['max_results']) ? intval($_POST['max_results']) : 10;
        $excerpt_length = isset($_POST['excerpt_length']) ? intval($_POST['excerpt_length']) : 25;
        
        if (empty($query)) {
            wp_send_json_error(['message' => __('Search query is required', 'aiohm-knowledge-assistant')]);
            wp_die();
        }
        
        try {
            $results = self::perform_search($query, $content_type_filter, $max_results, $excerpt_length);
            wp_send_json_success($results);
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Search Error: ' . $e->getMessage(), 'error');
            // translators: %s is the error message from the search operation
            wp_send_json_error(['message' => sprintf(__('Search failed: %s', 'aiohm-knowledge-assistant'), $e->getMessage())]);
        }

        wp_die();
    }

    /**
     * Performs the knowledge base search and formats the results.
     * This private method encapsulates the core search logic.
     *
     * @param string $query The search term.
     * @param string $content_type_filter The content type to filter by.
     * @param int    $max_results The maximum number of results to return.
     * @param int    $excerpt_length The length of the result excerpts.
     * @return array An array of formatted search results.
     */
    private static function perform_search($query, $content_type_filter, $max_results, $excerpt_length) {
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
        
        return [
            'results' => $formatted_results,
            'total_count' => count($formatted_results),
        ];
    }
}