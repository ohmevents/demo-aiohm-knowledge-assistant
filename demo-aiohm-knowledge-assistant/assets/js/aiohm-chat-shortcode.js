/**
 * AIOHM Chat Shortcode JavaScript
 * Handles the frontend chat functionality for the [aiohm_chat] shortcode
 */

jQuery(document).ready(function($) {
    'use strict';

    // Process all chat containers on the page
    $('.aiohm-chat-container').each(function() {
        const chatContainer = $(this);
        const chatId = chatContainer.attr('id');
        const config = window.aiohm_chat_configs && window.aiohm_chat_configs[chatId];
        
        if (!config) {
            return;
        }

        initializeChatWidget(chatContainer, config);
    });

    function initializeChatWidget(chatContainer, config) {
        const chatInput = chatContainer.find('.aiohm-chat-input');
        const sendBtn = chatContainer.find('.aiohm-chat-send-btn');
        const messagesContainer = chatContainer.find('.aiohm-chat-messages');
        const statusText = chatContainer.find('.aiohm-status-text');
        const statusIndicator = chatContainer.find('.aiohm-status-indicator');

        // Auto-resize textarea
        chatInput.on('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            
            // Enable/disable send button
            const hasText = $(this).val().trim().length > 0;
            sendBtn.prop('disabled', !hasText);
        });

        // Handle Enter key (send message)
        chatInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendBtn.prop('disabled')) {
                    sendMessage();
                }
            }
        });

        // Handle send button click
        sendBtn.on('click', function() {
            if (!$(this).prop('disabled')) {
                sendMessage();
            }
        });

        function sendMessage() {
            const message = chatInput.val().trim();
            if (!message) return;

            // Clear input and disable send button
            chatInput.val('').trigger('input');
            chatInput.css('height', 'auto');

            // Add user message to chat
            addMessage('user', message);

            // Show typing indicator
            showTypingIndicator();
            setStatus('thinking', 'Thinking...');

            // Send message to server
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: config.chat_action,
                    nonce: config.nonce,
                    message: message,
                    chat_id: config.chat_id
                },
                success: function(response) {
                    hideTypingIndicator();
                    
                    if (response.success && response.data && response.data.reply) {
                        addMessage('bot', response.data.reply);
                        setStatus('ready', 'Ready');
                    } else {
                        addMessage('bot', config.strings.error);
                        setStatus('error', 'Error');
                        setTimeout(() => setStatus('ready', 'Ready'), 3000);
                    }
                },
                error: function() {
                    hideTypingIndicator();
                    addMessage('bot', config.strings.error);
                    setStatus('error', 'Error');
                    setTimeout(() => setStatus('ready', 'Ready'), 3000);
                }
            });
        }

        function addMessage(sender, content) {
            const isBot = sender === 'bot';
            const messageClass = isBot ? 'aiohm-message-bot' : 'aiohm-message-user';
            const avatarHtml = isBot && config.settings.ai_avatar ? 
                `<div class="aiohm-message-avatar"><img src="${config.settings.ai_avatar}" alt="AI Avatar" class="chat-avatar-img"></div>` :
                (isBot ? '<div class="aiohm-message-avatar"><div class="chat-avatar-default">AI</div></div>' : '');

            // For bot messages, allow basic HTML formatting but clean it
            const formattedContent = isBot ? formatBotMessage(content) : escapeHtml(content);

            const messageHtml = `
                <div class="aiohm-message ${messageClass}">
                    ${avatarHtml}
                    <div class="aiohm-message-bubble">
                        <div class="aiohm-message-content">${formattedContent}</div>
                    </div>
                </div>
            `;

            messagesContainer.append(messageHtml);
            scrollToBottom();
        }

        function showTypingIndicator() {
            const typingHtml = `
                <div class="aiohm-message aiohm-message-bot aiohm-typing-message">
                    ${config.settings.ai_avatar ? 
                        `<div class="aiohm-message-avatar"><img src="${config.settings.ai_avatar}" alt="AI Avatar" class="chat-avatar-img"></div>` :
                        '<div class="aiohm-message-avatar"><div style="width:100%; height:100%; border-radius:50%; background:#457d58; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:12px;">AI</div></div>'
                    }
                    <div class="aiohm-message-bubble">
                        <div class="aiohm-typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            `;
            messagesContainer.append(typingHtml);
            scrollToBottom();
        }

        function hideTypingIndicator() {
            messagesContainer.find('.aiohm-typing-message').remove();
        }

        function setStatus(status, text) {
            statusIndicator.attr('data-status', status);
            statusText.text(text);
        }

        function scrollToBottom() {
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatBotMessage(content) {
            // Convert common markdown-like formatting to HTML
            let formatted = content
                // Convert **bold** to <strong>
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                // Convert *italic* to <em>
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                // Convert line breaks to proper breaks
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>');
            
            // Wrap in paragraph if it doesn't already have paragraph tags
            if (!formatted.includes('<p>') && !formatted.includes('</p>')) {
                formatted = '<p>' + formatted + '</p>';
            }
            
            // Clean up any malformed paragraph tags
            formatted = formatted.replace(/<\/p><p>/g, '</p><p>');
            
            return formatted;
        }

        // Initialize send button state
        sendBtn.prop('disabled', true);
        setStatus('ready', 'Ready');
    }

});