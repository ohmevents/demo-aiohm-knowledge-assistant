<?php
/**
 * RAG (Retrieval-Augmented Generation) Engine.
 * This version includes performance optimizations, improved error handling, and live URL research.
 */
if (!defined('ABSPATH')) exit;

class AIOHM_KB_RAG_Engine {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiohm_vector_entries';
    }
    
    public function get_table_name() {
        return $this->table_name;
    }

    public function add_entry($content, $content_type, $title, $metadata = [], $user_id = 0, $is_public = 0) {
        try {
            global $wpdb;
            $ai_client = new AIOHM_KB_AI_GPT_Client();
            $settings = AIOHM_KB_Assistant::get_settings();
            $chunk_size = $settings['chunk_size'] ?? 1000;
            $chunk_overlap = $settings['chunk_overlap'] ?? 200;

            $chunks = $this->chunk_content($content, $chunk_size, $chunk_overlap);

            if (empty($chunks)) {
                throw new Exception('Content was empty or could not be chunked.');
            }
            
            $content_id = $this->generate_entry_id($title, $content);
            $this->delete_entry_by_content_id($content_id);

            foreach ($chunks as $chunk_index => $chunk) {
                try {
                    AIOHM_KB_Assistant::log('Generating embeddings for chunk ' . $chunk_index . ' of length ' . strlen($chunk), 'info');
                    $embedding = $ai_client->generate_embeddings($chunk);
                    AIOHM_KB_Assistant::log('Embeddings generated successfully. Array length: ' . count($embedding), 'info');
                } catch (Exception $e) {
                    AIOHM_KB_Assistant::log('Failed to generate embeddings for chunk ' . $chunk_index . ': ' . $e->getMessage(), 'error');
                    throw new Exception('Failed to generate embeddings: ' . esc_html($e->getMessage()));
                }
                
                $chunk_metadata = array_merge($metadata, ['chunk_index' => $chunk_index]);
                
                // Insert with proper error handling and cache invalidation
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Vector data insertion requires direct insert
                $result = $wpdb->insert(
                    $this->table_name,
                    [
                        'user_id' => $user_id, 
                        'content_id' => $content_id, 
                        'content_type' => $content_type, 
                        'title' => $title, 
                        'content' => $chunk, 
                        'vector_data' => json_encode($embedding), 
                        'metadata' => json_encode($chunk_metadata),
                        'is_public' => $is_public
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
                );
                
                // Clear relevant caches after insert
                if ($result) {
                    wp_cache_delete('aiohm_vector_search_' . md5($content_id), 'aiohm_kb');
                    wp_cache_delete('aiohm_vector_user_' . $user_id, 'aiohm_kb');
                }

                if ($result === false) {
                    $db_error = $wpdb->last_error;
                    AIOHM_KB_Assistant::log('Database insert failed for chunk ' . $chunk_index . '. Error: ' . $db_error, 'error');
                    throw new Exception('Failed to insert a chunk into the database. DB Error: ' . esc_html($db_error));
                }
            }
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Failed to add entry for "' . $title . '": ' . $e->getMessage(), 'error');
            return new WP_Error(
                'add_entry_failed',
                'Could not add the entry "' . esc_html($title) . '" to the knowledge base. Please check the logs for more details.',
                ['title' => $title]
            );
        }
        return true;
    }
    
    public function query($query_text, $scope = 'site', $user_id = 0) {
        try {
            $research_prefix = "Please research the following URL and provide a summary of its key points:";

            if (strpos($query_text, $research_prefix) === 0) {
                preg_match('/(https?:\/\/[^\s]+)/', $query_text, $matches);
                $url = $matches[0] ?? null;

                if (!$url) {
                    return "I couldn't find a valid URL in your request. Please try again with the full URL (e.g., https://example.com).";
                }

                $result = $this->research_and_add_url($url, $user_id);

                if (is_wp_error($result)) {
                    return "I encountered an error trying to research that URL: " . $result->get_error_message();
                }
                
                return $this->summarize_new_context($url, $user_id);
            }

            if ($scope === 'private') {
                $context_entries = $this->find_context_for_user($query_text, $user_id);
            } else {
                $context_entries = $this->find_relevant_context($query_text);
            }

            $context = "";
            foreach ($context_entries as $entry) {
                $context .= "Title: " . $entry['entry']['title'] . "\n";
                $context .= "Content: " . $entry['entry']['content'] . "\n\n";
            }

            $settings = AIOHM_KB_Assistant::get_settings();
            $ai_settings = ($scope === 'private') ? $settings['muse_mode'] : $settings['mirror_mode'];
            
            $system_message_key = ($scope === 'private') ? 'system_prompt' : 'qa_system_message';
            $system_message = $ai_settings[$system_message_key] ?? 'You are a helpful assistant.';
            $model_name = $ai_settings['ai_model'] ?? 'gpt-3.5-turbo';
            $temperature = $ai_settings['temperature'] ?? 0.7;
            
            $enriched_user_message = "Here is some context to help you answer:\n\n---\n\n{$context}\n\n---\n\nBased on that context, please answer the following question:\n\n{$query_text}";

            $ai_client = new AIOHM_KB_AI_GPT_Client();
            return $ai_client->get_chat_completion(
                $system_message,
                $enriched_user_message,
                $temperature,
                $model_name
            );

        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('AI Query Error: ' . $e->getMessage(), 'error');
            return "AI Error: " . $e->getMessage();
        }
    }

    public function research_and_add_url($url, $user_id) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'The provided URL is not valid.');
        }

        // Enhanced request with proper headers
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', 'Could not retrieve content: ' . $response->get_error_message());
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (strpos($content_type, 'text/html') === false) {
            return new WP_Error('invalid_content_type', 'The URL does not appear to be an HTML page.');
        }

        $html_content = wp_remote_retrieve_body($response);
        
        // Enhanced content extraction
        $extracted_data = $this->extract_enhanced_content($html_content, $url);
        
        if (empty(trim($extracted_data['content']))) {
            return new WP_Error('no_content', 'Could not extract any readable text from the URL.');
        }

        $metadata = [
            'source_url' => $url,
            'title' => $extracted_data['title'],
            'description' => $extracted_data['description'],
            'author' => $extracted_data['author'],
            'published_date' => $extracted_data['published_date'],
            'keywords' => $extracted_data['keywords'],
            'extraction_date' => current_time('mysql')
        ];
        
        return $this->add_entry($extracted_data['content'], 'external_url', $extracted_data['title'], $metadata, $user_id, 0);
    }
    
    private function extract_enhanced_content($html_content, $url) {
        $title = 'Web Research: ' . $url;
        $description = '';
        $author = '';
        $published_date = '';
        $keywords = '';
        
        // Create DOMDocument for better parsing
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html_content);
        $xpath = new DOMXPath($dom);
        
        // Extract title - try multiple methods
        if (preg_match('/<title>(.*?)<\/title>/i', $html_content, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // Extract meta description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html_content, $matches)) {
            $description = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // Extract Open Graph data
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html_content, $matches)) {
            $og_title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
            if (!empty($og_title) && strlen($og_title) > strlen($title)) {
                $title = $og_title;
            }
        }
        
        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html_content, $matches)) {
            $og_description = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
            if (!empty($og_description) && empty($description)) {
                $description = $og_description;
            }
        }
        
        // Extract author information
        if (preg_match('/<meta\s+name=["\']author["\']\s+content=["\'](.*?)["\']/i', $html_content, $matches)) {
            $author = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // Extract keywords
        if (preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\'](.*?)["\']/i', $html_content, $matches)) {
            $keywords = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // Extract published date
        if (preg_match('/<meta\s+property=["\']article:published_time["\']\s+content=["\'](.*?)["\']/i', $html_content, $matches)) {
            $published_date = trim($matches[1]);
        }
        
        // Enhanced content extraction - prioritize main content areas
        $content_selectors = [
            'article',
            'main',
            '[role="main"]',
            '.content',
            '.post-content',
            '.entry-content',
            '.article-content',
            '#content',
            '.main-content'
        ];
        
        $main_content = '';
        foreach ($content_selectors as $selector) {
            $elements = $xpath->query("//*[contains(@class, '" . str_replace(['[', ']', '.', '#'], '', $selector) . "')]");
            if ($elements->length > 0) {
                foreach ($elements as $element) {
                    $main_content .= $element->textContent . ' ';
                }
                break;
            }
        }
        
        // If no main content found, extract from body but exclude common noise
        if (empty(trim($main_content))) {
            $plain_text = wp_strip_all_tags($html_content);
            // Remove common noise patterns
            $plain_text = preg_replace('/\b(cookie|privacy|policy|terms|conditions|javascript|advertisement|ads)\b/i', '', $plain_text);
        } else {
            $plain_text = $main_content;
        }
        
        // Clean up whitespace
        $plain_text = preg_replace('/\s+/', ' ', trim($plain_text));
        
        // Limit content length to prevent massive entries
        if (strlen($plain_text) > 15000) {
            $plain_text = substr($plain_text, 0, 15000) . '... [Content truncated]';
        }
        
        return [
            'title' => $title,
            'content' => $plain_text,
            'description' => $description,
            'author' => $author,
            'published_date' => $published_date,
            'keywords' => $keywords
        ];
    }
    
    private function summarize_new_context($url, $user_id) {
        $summary_prompt = "You have just successfully read the content from the URL: {$url}. Now, provide a concise summary of its key points based on the context you've just learned.";
        return $this->query($summary_prompt, 'private', $user_id);
    }
    
    public function find_relevant_context($query_text, $limit = 5) {
        global $wpdb;
        
        // Create cache key based on query and limit
        $cache_key = 'aiohm_context_' . md5($query_text . $limit);
        $cached_result = wp_cache_get($cache_key, 'aiohm_kb');
        
        if (false !== $cached_result) {
            return $cached_result;
        }
        
        $ai_client = new AIOHM_KB_AI_GPT_Client();
        $query_embedding = $ai_client->generate_embeddings($query_text);

        $keywords = preg_replace('/[^a-z0-9\s]/i', '', strtolower($query_text));
        
        $table_name = $this->table_name;
        
        if (!empty(trim($keywords))) {
            $search_query = '+' . str_replace(' ', ' +', trim($keywords));
            $cache_key_search = 'aiohm_search_' . md5($search_query);
            $pre_filtered_entries = wp_cache_get($cache_key_search, 'aiohm_kb');
            
            if (false === $pre_filtered_entries) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Vector search with caching
                $pre_filtered_entries = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, title, content, content_type, metadata, vector_data FROM {$wpdb->prefix}aiohm_vector_entries WHERE user_id = 0 AND MATCH(content) AGAINST(%s IN BOOLEAN MODE)",
                    $search_query
                ), ARRAY_A);
                wp_cache_set($cache_key_search, $pre_filtered_entries, 'aiohm_kb', 300);
            }
        } else {
            $cache_key_all = 'aiohm_all_entries';
            $pre_filtered_entries = wp_cache_get($cache_key_all, 'aiohm_kb');
            
            if (false === $pre_filtered_entries) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- All entries query with caching
                $pre_filtered_entries = $wpdb->get_results(
                    "SELECT id, title, content, content_type, metadata, vector_data FROM {$wpdb->prefix}aiohm_vector_entries WHERE user_id = 0",
                    ARRAY_A
                );
                wp_cache_set($cache_key_all, $pre_filtered_entries, 'aiohm_kb', 600);
            }
        }

        if (empty($pre_filtered_entries)) {
            $cache_key_random = 'aiohm_random_entries';
            $pre_filtered_entries = wp_cache_get($cache_key_random, 'aiohm_kb');
            
            if (false === $pre_filtered_entries) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Random entries fallback with caching
                $pre_filtered_entries = $wpdb->get_results(
                    "SELECT id, title, content, content_type, metadata, vector_data FROM {$wpdb->prefix}aiohm_vector_entries WHERE user_id = 0 ORDER BY RAND() LIMIT 100",
                    ARRAY_A
                );
                wp_cache_set($cache_key_random, $pre_filtered_entries, 'aiohm_kb', 180); // 3 minute cache for random results
            }
        }

        $similarities = [];
        foreach ($pre_filtered_entries as $entry) {
            $vector = json_decode($entry['vector_data'], true);
            if (is_array($vector)) {
                $dot_product = array_sum(array_map(fn($a, $b) => $a * $b, $query_embedding, $vector));
                $mag_a = sqrt(array_sum(array_map(fn($a) => $a * $a, $query_embedding)));
                $mag_b = sqrt(array_sum(array_map(fn($b) => $b * $b, $vector)));
                if ($mag_a > 0 && $mag_b > 0) {
                    $similarities[] = ['score' => $dot_product / ($mag_a * $mag_b), 'entry' => $entry];
                }
            }
        }
        usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);
        $result = array_slice($similarities, 0, $limit);
        
        // Cache the final result
        wp_cache_set($cache_key, $result, 'aiohm_kb', 300);
        
        return $result;
    }
    
    public function get_all_entries_paginated($per_page = 20, $page_number = 1) {
        global $wpdb;
        
        $cache_key = 'aiohm_entries_page_' . $page_number . '_' . $per_page;
        $cached_result = wp_cache_get($cache_key, 'aiohm_kb');
        
        if (false !== $cached_result) {
            return $cached_result;
        }
        
        $offset = ($page_number - 1) * $per_page;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Export query with caching
        $result = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, content_type, user_id, content_id, metadata, created_at FROM {$wpdb->prefix}aiohm_vector_entries GROUP BY content_id ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ), ARRAY_A);
        
        wp_cache_set($cache_key, $result, 'aiohm_kb', 600);
        
        return $result;
    }

    public function get_total_entries_count() {
        global $wpdb;
        
        $cache_key = 'aiohm_total_entries_count';
        $cached_count = wp_cache_get($cache_key, 'aiohm_kb');
        
        if (false !== $cached_count) {
            return (int) $cached_count;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Count query with caching
        $count = (int) $wpdb->get_var("SELECT COUNT(DISTINCT content_id) FROM {$wpdb->prefix}aiohm_vector_entries");
        
        wp_cache_set($cache_key, $count, 'aiohm_kb', 3600); // Cache for 1 hour
        
        return $count;
    }

    public function delete_entry_by_content_id($content_id) {
        global $wpdb;
        $table_name = $this->table_name;
        
        // Get metadata before deletion for cache invalidation
        $cache_key_meta = 'aiohm_meta_' . md5($content_id);
        $metadata_json = wp_cache_get($cache_key_meta, 'aiohm_kb');
        
        if (false === $metadata_json) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Metadata lookup with caching
            $metadata_json = $wpdb->get_var($wpdb->prepare(
                "SELECT metadata FROM {$wpdb->prefix}aiohm_vector_entries WHERE content_id = %s LIMIT 1",
                $content_id
            ));
            wp_cache_set($cache_key_meta, $metadata_json, 'aiohm_kb', 300);
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Delete operation with cache invalidation
        $deleted = $wpdb->delete($this->table_name, ['content_id' => $content_id], ['%s']);
        
        if ($deleted > 0) {
            // Invalidate relevant caches
            $this->clear_search_caches();
            wp_cache_delete($cache_key_meta, 'aiohm_kb');
            wp_cache_delete('aiohm_total_entries_count', 'aiohm_kb');
            
            if (!empty($metadata_json)) {
                $metadata = json_decode($metadata_json, true);
                $original_item_id = null;
                if (isset($metadata['post_id'])) {
                    $original_item_id = (int) $metadata['post_id'];
                } elseif (isset($metadata['attachment_id'])) {
                    $original_item_id = (int) $metadata['attachment_id'];
                }
                if ($original_item_id) {
                    delete_post_meta($original_item_id, '_aiohm_indexed');
                    clean_post_cache($original_item_id);
                }
            }
        }
        return $deleted;
    }
    
    private function clear_search_caches() {
        // Clear all search-related caches
        wp_cache_flush_group('aiohm_kb');
    }

    private function generate_entry_id($title, $content) {
        return md5($title . $content);
    }
    
    private function chunk_content($content, $chunk_size, $chunk_overlap) {
        $chunks = []; $content = trim($content); $content_length = strlen($content);
        if ($content_length === 0) return [];
        if ($content_length <= $chunk_size) return [$content];
        $start = 0;
        while ($start < $content_length) {
            $chunks[] = substr($content, $start, $chunk_size);
            $start += ($chunk_size - $chunk_overlap);
        }
        return $chunks;
    }

    public function export_knowledge_base($user_id = 0) {
        global $wpdb;
        
        try {
            AIOHM_KB_Assistant::log('Starting export for user_id: ' . $user_id, 'info');
            
            // Check if table exists
            $table_name = $wpdb->prefix . 'aiohm_vector_entries';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check
            $table_check = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            
            if (!$table_check) {
                AIOHM_KB_Assistant::log('Table does not exist: ' . $table_name, 'error');
                throw new Exception('Vector entries table does not exist');
            }
            
            // Get data with specific columns to avoid memory issues
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe, from system
            $sql = "SELECT id, user_id, content_id, content_type, title, content, metadata, created_at FROM {$wpdb->prefix}aiohm_vector_entries WHERE user_id = %d";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Export query
            $data = $wpdb->get_results($wpdb->prepare($sql, $user_id), ARRAY_A);
            
            if ($wpdb->last_error) {
                AIOHM_KB_Assistant::log('Database error: ' . $wpdb->last_error, 'error');
                throw new Exception('Database error: ' . esc_html($wpdb->last_error));
            }
            
            AIOHM_KB_Assistant::log('Found ' . count($data) . ' entries for user_id: ' . $user_id, 'info');
            
            // Return empty array as JSON if no data found
            if (empty($data)) {
                AIOHM_KB_Assistant::log('No data found, returning empty array', 'info');
                return json_encode([], JSON_PRETTY_PRINT);
            }
            
            // Try encoding without vector_data first for public exports
            if ($user_id === 0) {
                // Remove large vector_data for public exports to avoid memory issues
                foreach ($data as &$entry) {
                    unset($entry['vector_data']);
                }
            }
            
            $json_result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                AIOHM_KB_Assistant::log('JSON error: ' . json_last_error_msg(), 'error');
                throw new Exception('JSON encoding error: ' . json_last_error_msg());
            }
            
            AIOHM_KB_Assistant::log('Export successful, JSON length: ' . strlen($json_result), 'info');
            return $json_result;
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log('Export error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    public function import_knowledge_base($json_data) {
        global $wpdb;
        $data = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new Exception('Invalid JSON data provided.');
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction required for data integrity
        $wpdb->query('START TRANSACTION');
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete with cache clearing
            $wpdb->delete($this->table_name, ['user_id' => 0], ['%d']);
            foreach ($data as $row) {
                if (isset($row['content_id'], $row['content_type'], $row['title'], $row['content'])) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk import requires direct insert
                    $wpdb->insert($this->table_name, [
                        'user_id'      => 0,
                        'content_id'   => $row['content_id'],
                        'content_type' => $row['content_type'],
                        'title'        => $row['title'],
                        'content'      => $row['content'],
                        'vector_data'  => $row['vector_data'] ?? '[]',
                        'metadata'     => $row['metadata'] ?? '[]',
                    ]);
                }
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commit with cache clearing
            $wpdb->query('COMMIT');
            
            // Clear all caches after successful import
            $this->clear_search_caches();
            
        } catch (Exception $e) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction rollback required
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        return count($data);
    }
    
    public function find_context_for_user($query_text, $user_id, $limit = 5) {
        global $wpdb;
        $ai_client = new AIOHM_KB_AI_GPT_Client();
        $query_embedding = $ai_client->generate_embeddings($query_text);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- User context search query
        $all_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, content, content_type, metadata, vector_data FROM {$wpdb->prefix}aiohm_vector_entries WHERE user_id = 0 OR user_id = %d",
            $user_id
        ), ARRAY_A);
        $similarities = [];
        foreach ($all_entries as $entry) {
            $vector = !empty($entry['vector_data']) ? json_decode($entry['vector_data'], true) : null;
            if (is_array($vector)) {
                $dot_product = array_sum(array_map(fn($a, $b) => $a * $b, $query_embedding, $vector));
                $mag_a = sqrt(array_sum(array_map(fn($a) => $a * $a, $query_embedding)));
                $mag_b = sqrt(array_sum(array_map(fn($b) => $b * $b, $vector)));
                if ($mag_a > 0 && $mag_b > 0) {
                    $similarities[] = ['score' => $dot_product / ($mag_a * $mag_b), 'entry' => $entry];
                }
            }
        }
        usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($similarities, 0, $limit);
    }

    public function update_entry_scope_by_content_id($content_id, $new_user_id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update with cache clearing
        $result = $wpdb->update($this->table_name, ['user_id' => $new_user_id], ['content_id' => $content_id], ['%d'], ['%s']);
        
        if ($result) {
            // Clear relevant caches after update
            $this->clear_search_caches();
        }
        
        return $result;
    }
    
    public function update_entry_visibility_by_content_id($content_id, $is_public) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update with cache clearing
        $result = $wpdb->update($this->table_name, ['is_public' => $is_public], ['content_id' => $content_id], ['%d'], ['%s']);
        
        if ($result) {
            // Clear relevant caches after update
            $this->clear_search_caches();
        }
        
        return $result;
    }

    public function get_random_chunk() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Random content query
        $random_entry = $wpdb->get_var("SELECT content FROM {$wpdb->prefix}aiohm_vector_entries WHERE is_public = 1 ORDER BY RAND() LIMIT 1");
        return $random_entry;
    }
}