<?php
/**
 * Chat box display components and utilities
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AIOHM_KB_Chat_Box {
    
    /**
     * Render message bubble
     */
    public static function render_message($content, $type = 'user', $timestamp = null, $metadata = array()) {
        if (!$timestamp) {
            $timestamp = current_time('H:i');
        }
        
        // Sanitize type to prevent XSS
        $allowed_types = array('user', 'ai', 'system', 'error');
        $safe_type = in_array($type, $allowed_types) ? $type : 'user';
        
        $classes = array('aiohm-message', 'aiohm-message-' . $safe_type);
        
        if (!empty($metadata['error'])) {
            $classes[] = 'aiohm-message-error';
        }
        
        $output = '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        
        // Message avatar
        $output .= '<div class="aiohm-message-avatar">';
        if ($type === 'user') {
            $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>';
            $output .= '<circle cx="12" cy="7" r="4"></circle>';
            $output .= '</svg>';
        } else {
            $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<circle cx="12" cy="12" r="3"></circle>';
            $output .= '<path d="M12 1v6m0 6v6"></path>';
            $output .= '<path d="m9 9 3 3 3-3"></path>';
            $output .= '</svg>';
        }
        $output .= '</div>';
        
        // Message content
        $output .= '<div class="aiohm-message-bubble">';
        $output .= '<div class="aiohm-message-content">';
        
        if (!empty($metadata['error'])) {
            $output .= '<div class="aiohm-error-icon">⚠️</div>';
        }
        
        $output .= wp_kses_post($content);
        $output .= '</div>';
        
        // Message time and metadata
        $output .= '<div class="aiohm-message-meta">';
        $output .= '<span class="aiohm-message-time">' . esc_html($timestamp) . '</span>';
        
        if ($type === 'bot' && !empty($metadata['sources'])) {
            $output .= '<div class="aiohm-message-sources">';
            $output .= '<button type="button" class="aiohm-sources-toggle">';
            $output .= __('Sources', 'aiohm-knowledge-assistant') . ' (' . count($metadata['sources']) . ')';
            $output .= '</button>';
            $output .= '<div class="aiohm-sources-list" style="display: none;">';
            
            foreach ($metadata['sources'] as $source) {
                $output .= '<div class="aiohm-source-item">';
                $output .= '<div class="aiohm-source-title">' . esc_html($source['title']) . '</div>';
                $output .= '<div class="aiohm-source-type">' . esc_html(ucfirst($source['content_type'])) . '</div>';
                if (!empty($source['metadata']['url'])) {
                    $output .= '<a href="' . esc_url($source['metadata']['url']) . '" target="_blank" class="aiohm-source-link">';
                    $output .= __('View Source', 'aiohm-knowledge-assistant');
                    $output .= '</a>';
                }
                $output .= '</div>';
            }
            
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render typing indicator
     */
    public static function render_typing_indicator() {
        $output = '<div class="aiohm-message aiohm-message-bot aiohm-typing-indicator">';
        $output .= '<div class="aiohm-message-avatar">';
        $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<circle cx="12" cy="12" r="3"></circle>';
        $output .= '<path d="M12 1v6m0 6v6"></path>';
        $output .= '<path d="m9 9 3 3 3-3"></path>';
        $output .= '</svg>';
        $output .= '</div>';
        $output .= '<div class="aiohm-message-bubble">';
        $output .= '<div class="aiohm-typing-dots">';
        $output .= '<span></span><span></span><span></span>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render quick reply buttons
     */
    public static function render_quick_replies($replies) {
        if (empty($replies)) {
            return '';
        }
        
        $output = '<div class="aiohm-quick-replies">';
        
        foreach ($replies as $reply) {
            $output .= '<button type="button" class="aiohm-quick-reply-btn" data-reply="' . esc_attr($reply) . '">';
            $output .= esc_html($reply);
            $output .= '</button>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render suggested questions
     */
    public static function render_suggested_questions() {
        $suggestions = array(
            __('What services do you offer?', 'aiohm-knowledge-assistant'),
            __('How can I contact support?', 'aiohm-knowledge-assistant'),
            __('What are your business hours?', 'aiohm-knowledge-assistant'),
            __('Do you have a FAQ section?', 'aiohm-knowledge-assistant')
        );
        
        // Try to get suggestions from Q&A dataset
        $qa_dataset = get_option('aiohm_qa_dataset', array());
        if (!empty($qa_dataset)) {
            $custom_suggestions = array_slice(wp_list_pluck($qa_dataset, 'question'), 0, 4);
            if (!empty($custom_suggestions)) {
                $suggestions = $custom_suggestions;
            }
        }
        
        $output = '<div class="aiohm-suggested-questions">';
        $output .= '<div class="aiohm-suggestions-title">' . __('Suggested questions:', 'aiohm-knowledge-assistant') . '</div>';
        
        foreach ($suggestions as $suggestion) {
            $output .= '<button type="button" class="aiohm-suggestion-btn" data-question="' . esc_attr($suggestion) . '">';
            $output .= esc_html($suggestion);
            $output .= '</button>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render chat header with status
     */
    public static function render_chat_header($title, $subtitle = '', $show_status = true) {
        $output = '<div class="aiohm-chat-header">';
        
        // Title and subtitle
        $output .= '<div class="aiohm-header-content">';
        $output .= '<div class="aiohm-chat-title">' . esc_html($title) . '</div>';
        
        if (!empty($subtitle)) {
            $output .= '<div class="aiohm-chat-subtitle">' . esc_html($subtitle) . '</div>';
        }
        $output .= '</div>';
        
        // Status indicator
        if ($show_status) {
            $output .= '<div class="aiohm-chat-status">';
            $output .= '<span class="aiohm-status-indicator" data-status="ready"></span>';
            $output .= '<span class="aiohm-status-text">' . __('Ready', 'aiohm-knowledge-assistant') . '</span>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render empty chat state
     */
    public static function render_empty_state($welcome_message = '') {
        if (empty($welcome_message)) {
            $welcome_message = __('Hello! How can I help you today?', 'aiohm-knowledge-assistant');
        }
        
        $output = '<div class="aiohm-empty-chat-state">';
        $output .= '<div class="aiohm-welcome-avatar">';
        $output .= '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
        $output .= '<circle cx="12" cy="12" r="3"></circle>';
        $output .= '<path d="M12 1v6m0 6v6"></path>';
        $output .= '<path d="m9 9 3 3 3-3"></path>';
        $output .= '</svg>';
        $output .= '</div>';
        $output .= '<div class="aiohm-welcome-message">' . esc_html($welcome_message) . '</div>';
        $output .= self::render_suggested_questions();
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Format message content with markdown-like formatting
     */
    public static function format_message_content($content) {
        // Basic markdown-like formatting
        $content = wp_kses_post($content);
        
        // Bold text
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        
        // Italic text
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        
        // Line breaks
        $content = nl2br($content);
        
        // Links (simple detection)
        $content = preg_replace(
            '/https?:\/\/[^\s<>"]+/',
            '<a href="$0" target="_blank" rel="noopener">$0</a>',
            $content
        );
        
        return $content;
    }
    
    /**
     * Render chat input with features
     */
    public static function render_chat_input($placeholder = '', $features = array()) {
        if (empty($placeholder)) {
            $placeholder = __('Type your message...', 'aiohm-knowledge-assistant');
        }
        
        $features = wp_parse_args($features, array(
            'show_emoji' => false,
            'show_attachment' => false,
            'show_voice' => false,
            'auto_resize' => true,
            'max_rows' => 4
        ));
        
        $output = '<div class="aiohm-chat-input-container">';
        
        // Input wrapper
        $output .= '<div class="aiohm-chat-input-wrapper">';
        
        // Additional features buttons (left side)
        if ($features['show_emoji'] || $features['show_attachment'] || $features['show_voice']) {
            $output .= '<div class="aiohm-input-features-left">';
            
            if ($features['show_emoji']) {
                $output .= '<button type="button" class="aiohm-feature-btn aiohm-emoji-btn" title="' . __('Add emoji', 'aiohm-knowledge-assistant') . '">';
                $output .= '😊';
                $output .= '</button>';
            }
            
            if ($features['show_attachment']) {
                $output .= '<button type="button" class="aiohm-feature-btn aiohm-attachment-btn" title="' . __('Attach file', 'aiohm-knowledge-assistant') . '">';
                $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $output .= '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.64 16.2a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>';
                $output .= '</svg>';
                $output .= '</button>';
            }
            
            $output .= '</div>';
        }
        
        // Text input
        $input_classes = array('aiohm-chat-input');
        if ($features['auto_resize']) {
            $input_classes[] = 'aiohm-auto-resize';
        }
        
        $output .= '<textarea class="' . implode(' ', $input_classes) . '" ';
        $output .= 'placeholder="' . esc_attr($placeholder) . '" ';
        $output .= 'rows="1" ';
        if ($features['max_rows']) {
            $output .= 'data-max-rows="' . intval($features['max_rows']) . '" ';
        }
        $output .= '></textarea>';
        
        // Send button and voice input
        $output .= '<div class="aiohm-input-features-right">';
        
        if ($features['show_voice']) {
            $output .= '<button type="button" class="aiohm-feature-btn aiohm-voice-btn" title="' . __('Voice input', 'aiohm-knowledge-assistant') . '">';
            $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $output .= '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>';
            $output .= '<path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>';
            $output .= '<line x1="12" y1="19" x2="12" y2="23"></line>';
            $output .= '<line x1="8" y1="23" x2="16" y2="23"></line>';
            $output .= '</svg>';
            $output .= '</button>';
        }
        
        $output .= '<button type="button" class="aiohm-chat-send-btn" disabled>';
        $output .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $output .= '<line x1="22" y1="2" x2="11" y2="13"></line>';
        $output .= '<polygon points="22,2 15,22 11,13 2,9"></polygon>';
        $output .= '</svg>';
        $output .= '</button>';
        
        $output .= '</div>';
        $output .= '</div>';
        
        // Character counter (if enabled)
        if (!empty($features['show_counter'])) {
            $output .= '<div class="aiohm-character-counter">';
            $output .= '<span class="aiohm-char-count">0</span>';
            if (!empty($features['max_chars'])) {
                $output .= '<span class="aiohm-char-limit">/' . intval($features['max_chars']) . '</span>';
            }
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
}
