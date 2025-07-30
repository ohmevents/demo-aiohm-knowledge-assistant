<?php
/**
 * Media Library (Uploads) Crawler.
 * This version fixes the logic for stats calculation to correctly identify all supported files.
 */
if (!defined('ABSPATH')) exit;

class AIOHM_KB_Uploads_Crawler {

    private $rag_engine;
    private $readable_extensions = ['json', 'txt', 'csv', 'pdf', 'doc', 'docx', 'md'];
    
    /**
     * Maximum file size in bytes (10MB)
     */
    private $max_file_size = 10485760;
    
    /**
     * Allowed MIME types for security
     */
    private $allowed_mime_types = [
        'json' => 'application/json',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'md' => 'text/markdown'
    ];

    public function __construct() {
        $this->rag_engine = new AIOHM_KB_RAG_Engine();
    }

    public function get_stats() {
        AIOHM_KB_Assistant::log('Starting get_stats in Uploads_Crawler.');
        $stats = ['total_files' => 0, 'indexed_files' => 0, 'pending_files' => 0, 'by_type' => []];
        $all_files_with_status = $this->find_all_supported_attachments(); // Use the new method

        $stats['total_files'] = count($all_files_with_status);
        AIOHM_KB_Assistant::log('Total supported files found: ' . $stats['total_files']);

        foreach ($all_files_with_status as $file_info) {
            $ext = strtolower(pathinfo($file_info['path'], PATHINFO_EXTENSION));
            if (!isset($stats['by_type'][$ext])) {
                $stats['by_type'][$ext] = ['count' => 0, 'indexed' => 0, 'pending' => 0, 'size' => 0];
            }

            $stats['by_type'][$ext]['count']++;
            $stats['by_type'][$ext]['size'] += filesize($file_info['path']);

            if ($file_info['status'] === 'Knowledge Base') {
                $stats['indexed_files']++;
                $stats['by_type'][$ext]['indexed']++;
            } else {
                $stats['pending_files']++;
                $stats['by_type'][$ext]['pending']++;
            }
        }
        AIOHM_KB_Assistant::log('Finished get_stats.');
        return $stats;
    }

    public function find_all_supported_attachments() {
        AIOHM_KB_Assistant::log('Starting find_all_supported_attachments in Uploads_Crawler.');
        $all_items = [];
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_status'    => 'inherit',
            'cache_results'  => false, // Prevent caching of the post query itself
            'no_found_rows'  => true,  // Optimization
            'update_post_meta_cache' => false, // Prevent populating meta cache during this query
            'update_post_term_cache' => false  // Prevent populating term cache during this query
        ]);
        AIOHM_KB_Assistant::log('Found ' . count($attachments) . ' attachments in WordPress. Iterating through them.');

        foreach ($attachments as $attachment) {
            // IMPORTANT: Clear the object cache for the specific post/attachment before getting its meta.
            // This is a strong measure against persistent caching issues.
            clean_post_cache($attachment->ID);

            $file_path = get_attached_file($attachment->ID);
            if ($file_path && file_exists($file_path)) {
                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                AIOHM_KB_Assistant::log("Processing attachment ID: {$attachment->ID}, Title: {$attachment->post_title}, Path: {$file_path}, Ext: {$ext}");
                if (in_array($ext, $this->readable_extensions)) {
                    $is_indexed = get_post_meta($attachment->ID, '_aiohm_indexed', true);
                    $item_status = $is_indexed ? 'Knowledge Base' : 'Ready to Add';
                    AIOHM_KB_Assistant::log("Attachment ID {$attachment->ID} ({$attachment->post_title}) is readable ({$ext}). Indexed Status: " . ($is_indexed ? 'True' : 'False') . ". Final Item Status: {$item_status}");
                    $all_items[] = [
                        'id'     => $attachment->ID,
                        'title'  => $attachment->post_title ?: basename($file_path),
                        'link'   => wp_get_attachment_url($attachment->ID),
                        'type'   => wp_check_filetype($file_path)['type'], // Use wp_check_filetype for MIME type
                        'status' => $item_status,
                        'path'   => $file_path // Added path for stats calculation
                    ];
                } else {
                    AIOHM_KB_Assistant::log("Attachment ID {$attachment->ID} ({$attachment->post_title}) is NOT a readable extension ({$ext}). Skipping.");
                }
            } else {
                AIOHM_KB_Assistant::log("Attachment ID {$attachment->ID} ({$attachment->post_title}) has no file path or file does not exist: {$file_path}");
            }
        }
        AIOHM_KB_Assistant::log('Finished find_all_supported_attachments. Returning ' . count($all_items) . ' supported items.');
        return $all_items;
    }

    public function find_pending_attachments() {
        AIOHM_KB_Assistant::log('Starting find_pending_attachments in Uploads_Crawler.');
        $all_supported = $this->find_all_supported_attachments();
        $pending = array_filter($all_supported, function($item) {
            return $item['status'] === 'Ready to Add';
        });
        AIOHM_KB_Assistant::log('Finished find_pending_attachments. Returning ' . count($pending) . ' pending items.');
        return $pending;
    }

    public function add_attachments_to_kb(array $attachment_ids) {
        AIOHM_KB_Assistant::log('Starting add_attachments_to_kb in Uploads_Crawler for IDs: ' . implode(', ', $attachment_ids));
        if (empty($attachment_ids)) return [];
        $processed = [];
        foreach ($attachment_ids as $attachment_id) {
            $file_path = get_attached_file($attachment_id);
            $file_title = get_the_title($attachment_id) ?: "Attachment ID {$attachment_id}";
            try {
                if (!$file_path) {
                    throw new Exception("WordPress could not locate the file path for attachment ID {$attachment_id}. The file may have been moved or deleted.");
                }
                if (!file_exists($file_path)) {
                    throw new Exception("File does not exist at path: {$file_path}. The file may have been moved or deleted outside of WordPress.");
                }
                $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                AIOHM_KB_Assistant::log("Attempting to process file ID {$attachment_id} (Title: {$file_title}, Extension: {$file_extension}, Path: {$file_path}) for KB.");
                $file_data = $this->process_file($file_path, $attachment_id);
                if ($file_data && !empty(trim($file_data['content']))) {
                    $result = $this->rag_engine->add_entry($file_data['content'], $file_data['type'], $file_data['title'], $file_data['metadata'], get_current_user_id(), 0);
                    
                    // Check if the knowledge base addition was successful
                    if (is_wp_error($result)) {
                        throw new Exception(sprintf(
                            /* translators: %s: error message */
                            __('Failed to add to knowledge base: %s', 'aiohm-knowledge-assistant'),
                            esc_html($result->get_error_message())
                        ));
                    }
                    
                    // Only update meta if KB addition was successful
                    update_post_meta($attachment_id, '_aiohm_indexed', time());
                    clean_post_cache($attachment_id); // Clear cache for this specific post. This should also clear its post_meta cache.
                    wp_cache_delete($attachment_id, 'post_meta'); // Extra cache clearing for post meta
                    wp_cache_delete($attachment_id, 'posts'); // Clear post cache
                    
                    // Clear any object cache that might be interfering
                    if (function_exists('wp_cache_flush_group')) {
                        wp_cache_flush_group('posts');
                        wp_cache_flush_group('post_meta');
                    }
                    AIOHM_KB_Assistant::log("Successfully processed and indexed attachment ID {$attachment_id}.");
                    $processed[] = ['id' => $attachment_id, 'title' => $file_title, 'status' => 'success'];
                } else {
                     throw new Exception('File type not readable or content is empty after processing.');
                }
            } catch (Exception $e) {
                AIOHM_KB_Assistant::log('Upload scan error processing file ID ' . $attachment_id . ': ' . $e->getMessage(), 'error');
                $processed[] = ['id' => $attachment_id, 'title' => $file_title, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }
        
        // Final cache clearing after processing all items
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        AIOHM_KB_Assistant::log('Finished add_attachments_to_kb.');
        return $processed;
    }

    private function get_supported_attachments($get_all_for_stats = false) {
        // This method's logic is largely superseded by find_all_supported_attachments()
        // It should ideally be refactored or removed if no longer directly used.
        // For now, it will simply return all supported files without status.
        $attachments = get_posts(['post_type' => 'attachment', 'posts_per_page' => -1, 'post_status' => 'inherit']);
        $file_infos = [];

        foreach ($attachments as $attachment) {
            $path = get_attached_file($attachment->ID);
            if ($path && file_exists($path)) {
                 $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                 if (in_array($ext, $this->readable_extensions)) {
                     $file_infos[] = ['path' => $path, 'id' => $attachment->ID];
                 }
            }
        }
        return $file_infos;
    }

    private function process_file($file_path, $attachment_id) {
        AIOHM_KB_Assistant::log("Starting process_file for path: {$file_path}, ID: {$attachment_id}");
        if (!file_exists($file_path) || !is_readable($file_path)) {
            AIOHM_KB_Assistant::log("File not found or not readable: {$file_path}", 'error');
            return null;
        }

        $attachment_post = get_post($attachment_id);
        if (!$attachment_post) {
            AIOHM_KB_Assistant::log("Attachment post not found for ID: {$attachment_id}", 'error');
            return null;
        }

        $file_info = pathinfo($file_path);
        $extension = strtolower($file_info['extension'] ?? '');
        
        // Extract content based on file type
        $sanitized_content = $this->extract_file_content($file_path, $extension);
        if (empty($sanitized_content)) {
            AIOHM_KB_Assistant::log("Failed to extract content from file: {$file_path}", 'error');
            return null;
        }
        
        // Prepare file data
        $file_data = [
            'content' => $sanitized_content,
            'type' => strtoupper($extension), // Use actual file extension as type
            'title' => $attachment_post->post_title ?: $file_info['filename'],
            'metadata' => [
                'source_type' => 'upload_file',
                'attachment_id' => $attachment_id,
                'file_path' => $file_path,
                'file_extension' => $extension,
                'file_size' => filesize($file_path),
                'mime_type' => $attachment_post->post_mime_type,
                'upload_date' => $attachment_post->post_date,
            ]
        ];

        AIOHM_KB_Assistant::log("Successfully processed file: {$file_path}");
        return $file_data;
    }
    
    /**
     * Extract content from files based on their type
     * @param string $file_path Full path to the file
     * @param string $extension File extension
     * @return string Extracted and sanitized content
     */
    private function extract_file_content($file_path, $extension) {
        switch ($extension) {
            case 'pdf':
                return $this->extract_pdf_content($file_path);
                
            case 'doc':
            case 'docx':
                return $this->extract_doc_content($file_path, $extension);
                
            case 'txt':
            case 'md':
            case 'json':
            case 'csv':
                // Read text-based files directly
                $content = file_get_contents($file_path);
                if ($content === false) {
                    return '';
                }
                return $this->sanitize_file_content($content, $extension);
                
            default:
                AIOHM_KB_Assistant::log("Unsupported file extension: {$extension}", 'warning');
                return '';
        }
    }
    
    /**
     * Extract content from PDF files
     * @param string $file_path Path to PDF file
     * @return string Extracted text content
     */
    private function extract_pdf_content($file_path) {
        try {
            // Method 1: Try to use WordPress's built-in functions if available
            if (function_exists('wp_read_pdf_content')) {
                $content = wp_read_pdf_content($file_path);
                if (!empty($content)) {
                    return sanitize_textarea_field($content);
                }
            }
            
            // Method 2: Simple text extraction attempt for PDF files
            // This is a basic fallback - many PDFs won't work with this method
            $content = file_get_contents($file_path);
            if ($content === false) {
                return '';
            }
            
            // Try to extract readable text from PDF (very basic approach)
            // This will only work for simple text-based PDFs
            if (preg_match_all('/\((.*?)\)/s', $content, $matches)) {
                $text = implode(' ', $matches[1]);
                $text = preg_replace('/[^\x20-\x7E]/', ' ', $text); // Remove non-printable chars
                $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
                $text = trim($text);
                
                if (strlen($text) > 50) { // Only return if we got substantial text
                    return sanitize_textarea_field($text);
                }
            }
            
            // Fallback: Create descriptive content
            $filename = basename($file_path);
            return "PDF Document: {$filename}. This PDF file has been uploaded and is available for reference. For best results, please extract text content manually and upload as a text file.";
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log("PDF extraction error for {$file_path}: " . $e->getMessage(), 'error');
            $filename = basename($file_path);
            return "PDF Document: {$filename} (content extraction failed)";
        }
    }
    
    /**
     * Extract content from DOC/DOCX files
     * @param string $file_path Path to document file
     * @param string $extension File extension (doc or docx)
     * @return string Extracted text content
     */
    private function extract_doc_content($file_path, $extension) {
        try {
            if ($extension === 'docx') {
                // Try to extract from DOCX using ZipArchive
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($file_path) === TRUE) {
                        $content = $zip->getFromName('word/document.xml');
                        if ($content !== false) {
                            // Strip XML tags to get plain text
                            $content = wp_strip_all_tags($content);
                            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
                            $content = preg_replace('/\s+/', ' ', $content);
                            $content = trim($content);
                            $zip->close();
                            
                            if (strlen($content) > 20) {
                                return sanitize_textarea_field($content);
                            }
                        }
                        $zip->close();
                    }
                }
            }
            
            // Fallback for DOC files or failed DOCX extraction
            $filename = basename($file_path);
            return "Word Document: {$filename}. This document has been uploaded and is available for reference. For best results, please save as text (.txt) and re-upload.";
            
        } catch (Exception $e) {
            AIOHM_KB_Assistant::log("DOC extraction error for {$file_path}: " . $e->getMessage(), 'error');
            $filename = basename($file_path);
            return "Document: {$filename} (content extraction failed)";
        }
    }
    
    /**
     * Sanitize file content based on file type
     * @param string $content Raw file content
     * @param string $extension File extension
     * @return string Sanitized content
     */
    private function sanitize_file_content($content, $extension) {
        // Remove null bytes and control characters
        $content = str_replace("\0", '', $content);
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        switch ($extension) {
            case 'json':
                // For JSON, decode and re-encode to ensure structure is safe
                $decoded = json_decode($content, true);
                if ($decoded !== null) {
                    // Recursively sanitize JSON values
                    $decoded = $this->sanitize_json_recursive($decoded);
                    return wp_json_encode($decoded);
                }
                return '';
                
            case 'txt':
            case 'md':
                // For text and markdown, strip dangerous HTML but allow basic formatting
                return wp_kses($content, [
                    'p' => [],
                    'br' => [],
                    'strong' => [],
                    'em' => [],
                    'ul' => [],
                    'ol' => [],
                    'li' => [],
                    'h1' => [],
                    'h2' => [],
                    'h3' => [],
                    'h4' => [],
                    'h5' => [],
                    'h6' => [],
                    'blockquote' => [],
                    'code' => [],
                    'pre' => []
                ]);
                
            case 'csv':
                // For CSV, ensure no script injection in cells
                $lines = explode("\n", $content);
                $sanitized_lines = [];
                foreach ($lines as $line) {
                    // Remove potential formula injection
                    $line = preg_replace('/^[=+\-@]/', "'", $line);
                    $sanitized_lines[] = sanitize_text_field($line);
                }
                return implode("\n", $sanitized_lines);
                
            default:
                // For other types, basic sanitization
                return sanitize_textarea_field($content);
        }
    }
    
    /**
     * Recursively sanitize JSON data
     * @param mixed $data JSON data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_json_recursive($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize_json_recursive'], $data);
        } elseif (is_string($data)) {
            return sanitize_text_field($data);
        }
        return $data;
    }
}