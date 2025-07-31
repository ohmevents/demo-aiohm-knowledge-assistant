/**
 * AIOHM Knowledge Assistant - Admin Mode Scripts
 *
 * This file handles the dynamic functionality for the Mirror Mode and Muse Mode
 * admin pages, including live previews, AJAX saving, and test chat features.
 * It relies on a localized data object `aiohm_admin_modes_data` passed from PHP.
 */

jQuery(document).ready(function($) {
    // Exit if the required configuration object is not present
    if (typeof aiohm_admin_modes_data === 'undefined') {
        return;
    }

    const config = aiohm_admin_modes_data;
    let noticeTimer;

    /**
     * Displays a dismissible admin notice at the top of the page.
     * @param {string} message - The message to display. Can contain HTML.
     * @param {string} type - The notice type ('success', 'error', 'warning').
     */
    function showAdminNotice(message, type = 'success') {
        clearTimeout(noticeTimer);
        let $notice = $('#aiohm-admin-notice');
        
        // Create the notice element if it doesn't exist or ensure it has proper structure
        if ($notice.length === 0) {
            $('<div id="aiohm-admin-notice" class="notice is-dismissible" style="display:none; margin-top: 10px;" tabindex="-1" role="alert" aria-live="polite"><p></p></div>').insertAfter('h1');
            $notice = $('#aiohm-admin-notice');
        } else if ($notice.find('p').length === 0) {
            $notice.html('<p></p>');
        }
        
        $notice.removeClass('notice-success notice-error notice-warning').addClass('notice-' + type).addClass('is-dismissible');
        $notice.find('p').html(message);
        $notice.fadeIn();

        // Focus on the notice for accessibility and scroll to top
        setTimeout(() => {
            $notice.focus();
            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 300);
        }, 100);

        // If the notice doesn't contain a button, auto-hide it.
        if (!message.includes('<button')) {
            noticeTimer = setTimeout(() => $notice.fadeOut(), 5000);
        }
    }

    /**
     * Determines if a hex color is "dark" to decide text color.
     * @param {string} hex - The hex color string (e.g., '#RRGGBB').
     * @returns {boolean} - True if the color is dark.
     */
    function isColorDark(hex) {
        if (!hex) return false;
        const color = (hex.charAt(0) === '#') ? hex.substring(1, 7) : hex;
        if (color.length !== 6) return false;
        const r = parseInt(color.substring(0, 2), 16);
        const g = parseInt(color.substring(2, 4), 16);
        const b = parseInt(color.substring(4, 6), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance < 0.5;
    }

    /**
     * Updates the live preview elements based on form input changes.
     */
    function updateLivePreview() {
        // Update colors and text for the test chat preview
        const primaryColor = $('#primary_color').val() || '#1f5014';
        const textColor = $('#text_color').val() || '#ffffff';
        // Use business_name for Mirror Mode, assistant_name for Muse Mode
        const assistantName = config.mode === 'mirror' ? 
            ($('#business_name').val() || 'Live Preview') : 
            ($('#assistant_name').val() || 'Muse');
        const botAvatarUrl = $('#ai_avatar').val();
        const defaultAvatarUrl = config.pluginUrl + 'assets/images/OHM-logo.png';

        $('#aiohm-test-chat .aiohm-chat-header').css({ 'background-color': primaryColor, 'color': textColor });
        $('#aiohm-test-chat .aiohm-message-user .aiohm-message-bubble').css('background-color', primaryColor);
        $('#aiohm-test-chat .aiohm-chat-title-preview').text(assistantName);

        // Use default avatar if no custom avatar is set
        const avatarToUse = botAvatarUrl || defaultAvatarUrl;
        $('.aiohm-avatar-preview').attr('src', avatarToUse).show();
        
        // Update footer preview for Mirror Mode
        if (config.mode === 'mirror') {
            const footerPreview = $('.aiohm-chat-footer-preview');
            const meetingUrl = $('#meeting_button_url').val().trim();
            const brandingHtml = `<div class="aiohm-chat-footer-branding"><span>Powered by <strong>AIOHM</strong></span></div>`;
            const buttonHtml = `<a href="#" class="aiohm-chat-footer-button chat-footer-button-custom" style="background-color: ${primaryColor}; color: ${textColor};" onclick="event.preventDefault();">Book a Meeting</a>`;

            footerPreview.html(meetingUrl ? buttonHtml : brandingHtml);
        }
    }

    /**
     * Test Chat Functionality
     */
    const testChat = {
        $container: $('#aiohm-test-chat'),
        $messages: $('#aiohm-test-chat .aiohm-chat-messages'),
        $input: $('#aiohm-test-chat .aiohm-chat-input'),
        $sendBtn: $('#aiohm-test-chat .aiohm-chat-send-btn'),
        isTyping: false,
        currentRequest: null,
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            this.$input.on('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.sendMessage(); }});
            this.$sendBtn.on('click', () => this.sendMessage());
            this.$input.on('input', e => this.$sendBtn.prop('disabled', $(e.target).val().trim().length === 0));
        },
        
        sendMessage: function() {
            const message = this.$input.val().trim();
            if (!message || this.isTyping) return;
            
            this.addMessage(message, 'user');
            this.$input.val('').trigger('input');
            this.showTypingIndicator();

            if (this.currentRequest) this.currentRequest.abort();

            const settingsPayload = {
                qa_system_message: $('#qa_system_message').val(),
                system_prompt: $('#system_prompt').val(),
                temperature: $('#temperature, #qa_temperature').val(),
                assistant_name: config.mode === 'mirror' ? $('#business_name').val() : $('#assistant_name').val(),
                ai_model: $('#ai_model_selector').val(),
            };
            
            this.currentRequest = $.post(config.ajax_url, {
                action: config.testChatAction,
                [config.nonceFieldId]: $('#' + config.nonceFieldId).val(),
                message: message,
                settings: settingsPayload
            }).done(response => {
                let answer;
                if (response.success && response.data && response.data.answer) {
                    let rawAnswer = response.data.answer;
                    
                    // Handle different response formats
                    if (typeof rawAnswer === 'string') {
                        // Check if it's a JSON string that needs parsing
                        try {
                            const parsed = JSON.parse(rawAnswer);
                            if (parsed.content) {
                                answer = parsed.content;
                            } else if (parsed.message) {
                                answer = parsed.message;
                            } else {
                                answer = rawAnswer;
                            }
                        } catch (e) {
                            // Not JSON, use as-is
                            answer = rawAnswer;
                        }
                    } else if (typeof rawAnswer === 'object') {
                        // If it's already an object, extract content
                        answer = rawAnswer.content || rawAnswer.message || rawAnswer.text || JSON.stringify(rawAnswer);
                    } else {
                        answer = String(rawAnswer);
                    }
                } else {
                    answer = response.data?.message || "Sorry, an error occurred.";
                }
                
                // Format the answer for better display
                answer = this.formatResponse(answer);
                this.addMessage(answer, 'bot');
            }).fail(() => {
                this.addMessage("Server error. Please try again.", 'bot', true);
            }).always(() => {
                this.hideTypingIndicator();
                this.currentRequest = null;
            });
        },
        
        formatResponse: function(text) {
            if (!text || typeof text !== 'string') {
                return text;
            }
            
            // Convert markdown-style formatting to HTML
            return text
                // Bold text: **text** or __text__ -> <strong>text</strong>
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/__(.*?)__/g, '<strong>$1</strong>')
                
                // Italic text: *text* or _text_ -> <em>text</em>
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/_(.*?)_/g, '<em>$1</em>')
                
                // Code blocks: `text` -> <code>text</code>
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                
                // Line breaks: double newlines -> paragraphs, single newlines -> breaks
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>')
                
                // Wrap in paragraph tags if not already wrapped
                .replace(/^(?!<p>)/, '<p>')
                .replace(/(?!<\/p>)$/, '</p>')
                
                // Clean up empty paragraphs
                .replace(/<p><\/p>/g, '')
                .replace(/<p><br><\/p>/g, '<br>');
        },
        
        addMessage: function(content, type, isError = false) {
            // For bot messages that are already formatted, use as-is. For user messages, sanitize.
            let processedContent;
            if (type === 'bot' && !isError) {
                // Bot responses are already formatted and safe
                processedContent = content;
            } else {
                // User input and error messages need sanitization
                processedContent = $('<div/>').text(content).html().replace(/\n/g, '<br>');
            }
            
            const errorIcon = isError ? '⚠️ ' : '';
            const botAvatarUrl = $('#ai_avatar').val();
            let avatarHtml = (type === 'bot' && botAvatarUrl) ? `<img src="${botAvatarUrl}" alt="AI Avatar" class="aiohm-avatar-preview">` : '';

            const messageHtml = `
                <div class="aiohm-message aiohm-message-${type}">
                    ${avatarHtml ? `<div class="aiohm-message-avatar">${avatarHtml}</div>` : ''}
                    <div class="aiohm-message-bubble"><div class="aiohm-message-content">${errorIcon}${processedContent}</div></div>
                </div>`;
            this.$messages.append(messageHtml).scrollTop(this.$messages[0].scrollHeight);
        },
        
        showTypingIndicator: function() {
            this.isTyping = true;
            const typingHtml = `
                <div class="aiohm-message aiohm-message-bot aiohm-typing-indicator">
                    <div class="aiohm-message-bubble"><div class="aiohm-typing-dots"><span></span><span></span><span></span></div></div>
                </div>`;
            this.$messages.append(typingHtml).scrollTop(this.$messages[0].scrollHeight);
        },
        
        hideTypingIndicator: function() {
            this.isTyping = false;
            this.$messages.find('.aiohm-typing-indicator').remove();
        }
    };
    testChat.init();

    // --- General Event Handlers ---
    $('#' + config.formId).on('input change', 'input, select, textarea', updateLivePreview);
    $('#qa_temperature, #temperature').on('input', function() { $('.temp-value').text($(this).val()); });

    // --- Page-Specific Logic ---
    if (config.mode === 'mirror') {
        // Media uploader for Mirror Mode avatar
        wp.media && $('#upload_ai_avatar_button').on('click', function(e) {
            e.preventDefault();
            const mediaUploader = wp.media({ title: 'Choose AI Avatar', button: { text: 'Choose Avatar' }, multiple: false });
            mediaUploader.on('select', () => {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#ai_avatar').val(attachment.url).trigger('input');
            });
            mediaUploader.open();
        });
        
        // Q&A Generator for Mirror Mode
        $('#generate-q-and-a').on('click', function() {
            const $btn = $(this);
            const $results = $('#q-and-a-results');
            $btn.prop('disabled', true).text('Generating...');
            $results.html('<span class="spinner is-active"></span>');
            
            $.post(config.ajax_url, { action: 'aiohm_generate_mirror_mode_qa', nonce: $('#' + config.nonceFieldId).val() })
             .done(response => {
                 if (response.success) {
                     const question = response.data.qa_pair.question;
                     const answer = response.data.qa_pair.answer;
                     $results.html(`
                         <div class="qa-sample-container">
                             <div class="qa-header">
                                 <span class="qa-icon">💬</span>
                                 <h4>Sample Q&A from Your Knowledge Base</h4>
                             </div>
                             <div class="qa-question">
                                 <div class="qa-label">
                                     <span class="qa-q-icon">❓</span>
                                     <strong>Question:</strong>
                                 </div>
                                 <div class="qa-content">${question}</div>
                             </div>
                             <div class="qa-answer">
                                 <div class="qa-label">
                                     <span class="qa-a-icon">💡</span>
                                     <strong>Answer:</strong>
                                 </div>
                                 <div class="qa-content">${answer}</div>
                             </div>
                         </div>
                     `);
                 } else {
                     $results.html(`<div class="qa-error">⚠️ ${response.data.message || 'Failed to generate Q&A sample.'}</div>`);
                 }
             })
             .fail(() => $results.html('<div class="qa-error">❌ Server error occurred.</div>'))
             .always(() => $btn.prop('disabled', false).text('Generate Sample Q&A'));
        });
        
        // Text formatting removed - was causing corruption
    }
    
    if (config.mode === 'muse') {
        // Archetype change handler for Muse Mode
        $('#brand_archetype').on('change', function() {
            const selected = $(this).val();
            const promptText = selected && config.archetypePrompts[selected] ? config.archetypePrompts[selected] : config.defaultPrompt;
            $('#' + config.promptTextareaId).val(promptText);
            
            // Trigger input event to ensure proper formatting is applied
            $('#' + config.promptTextareaId).trigger('input');
        });
        
        // Text formatting removed - was causing corruption
    }

    // --- Shared Handlers for Buttons ---
    $('#reset-prompt-btn').on('click', function(e) {
        e.preventDefault();
        showAdminNotice(
            'Are you sure you want to reset the prompt? <button id="confirm-prompt-reset" class="button button-small button-margin-left-10">Confirm</button>',
            'warning'
        );
    });

    $(document).on('click', '#confirm-prompt-reset', function() {
        const promptText = (config.mode === 'muse' && config.archetypePrompts && config.archetypePrompts[$('#brand_archetype').val()])
            ? config.archetypePrompts[$('#brand_archetype').val()]
            : config.defaultPrompt;
        $('#' + (config.promptTextareaId || 'qa_system_message')).val(promptText);
        $('#aiohm-admin-notice').fadeOut();
        showAdminNotice('Prompt has been reset to default.', 'success');
    });

    $('#' + config.saveButtonId).on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Saving...');
        
        const formData = $('#' + config.formId).serialize();
        
        
        $.post(config.ajax_url, {
            action: config.saveAction,
            form_data: formData
        }).done(response => {
            showAdminNotice(response.success ? response.data.message : 'Error: ' + (response.data.message || 'Could not save.'), response.success ? 'success' : 'error');
        }).fail((xhr, status, error) => {
            showAdminNotice('A server error occurred.', 'error');
        }).always(() => $btn.prop('disabled', false).text(originalText));
    });

    // KB Search Handler
    $('.aiohm-search-btn').on('click', function() {
        const query = $('.aiohm-search-input').val();
        const filter = $('#aiohm-test-search-filter').val() || '';
        const $results = $('.aiohm-search-results');
        if (!query) return;

        $results.html('<span class="spinner is-active"></span>');
        
        $.post(config.ajax_url, {
            action: 'aiohm_admin_search_knowledge',
            nonce: $('#' + config.nonceFieldId).val(),
            query: query,
            content_type_filter: filter
        }).done(function(response) {
            $results.empty();
            if (response.success && response.data.results.length > 0) {
                response.data.results.forEach(item => {
                    $results.append(`<div class="aiohm-search-result-item"><h4><a href="${item.url}" target="_blank">${item.title}</a></h4><p>${item.excerpt}</p><div class="result-meta">Type: ${item.content_type} | Similarity: ${item.similarity}%</div></div>`);
                });
            } else {
                 $results.html('<div class="aiohm-search-result-item"><p>No results found.</p></div>');
            }
        }).fail(() => $results.html('<div class="aiohm-search-result-item"><p>Search request failed.</p></div>'));
    });

    // --- Individual API Key Save Functionality ---
    $('.aiohm-save-api-key').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const originalText = $btn.text();
        const apiType = $btn.data('type');
        const targetInput = $btn.data('target');
        const apiValue = $('#' + targetInput).val();
        
        // Special handling for ShareAI - save both API key and model
        if (apiType === 'shareai') {
            const selectedModel = $('#shareai_model').val();
            
            if (!apiValue.trim()) {
                showAdminNotice('Please enter an API key before saving.', 'warning');
                return;
            }
            
            if (!selectedModel) {
                showAdminNotice('Please select a model before saving.', 'warning');
                return;
            }
            
            // Disable button and show loading state
            $btn.prop('disabled', true).text('Saving...');
            
            // Save API key first, then model
            let apiSaveCompleted = false;
            let modelSaveCompleted = false;
            let apiSaveSuccess = false;
            let modelSaveSuccess = false;
            let apiSaveMessage = '';
            let modelSaveMessage = '';
            
            function checkBothSavesComplete() {
                if (apiSaveCompleted && modelSaveCompleted) {
                    // Re-enable button and restore original text
                    $btn.prop('disabled', false).text(originalText);
                    
                    if (apiSaveSuccess && modelSaveSuccess) {
                        // Success state
                        $btn.addClass('success');
                        showAdminNotice('ShareAI settings saved successfully!', 'success');
                        
                        // Update the default AI provider dropdown to include ShareAI
                        updateDefaultProviderDropdown('shareai');
                        
                        // Now display the model information
                        updateModelInfo(selectedModel);
                        
                        // Reset button state after 2 seconds
                        setTimeout(function() {
                            $btn.removeClass('success');
                        }, 2000);
                    } else {
                        const errorMessages = [];
                        if (!apiSaveSuccess) errorMessages.push(apiSaveMessage);
                        if (!modelSaveSuccess) errorMessages.push(modelSaveMessage);
                        showAdminNotice('Error: ' + errorMessages.join(', '), 'error');
                    }
                }
            }
            
            // Save API key
            $.post(config.ajax_url, {
                action: 'aiohm_save_individual_api_key',
                nonce: config.nonce || $('#aiohm_nonce').val() || $('[name*="nonce"]').val(),
                api_type: 'shareai',
                setting_name: 'shareai_api_key',
                api_value: apiValue
            }).done(function(response) {
                apiSaveSuccess = response.success;
                apiSaveMessage = response.data?.message || 'Could not save API key';
            }).fail(function() {
                apiSaveSuccess = false;
                apiSaveMessage = 'Server error saving API key';
            }).always(function() {
                apiSaveCompleted = true;
                checkBothSavesComplete();
            });
            
            // Save model selection
            $.post(config.ajax_url, {
                action: 'aiohm_save_individual_api_key',
                nonce: config.nonce || $('#aiohm_nonce').val() || $('[name*="nonce"]').val(),
                api_type: 'shareai_model',
                setting_name: 'shareai_model',
                api_value: selectedModel
            }).done(function(response) {
                modelSaveSuccess = response.success;
                modelSaveMessage = response.data?.message || 'Could not save model';
            }).fail(function() {
                modelSaveSuccess = false;
                modelSaveMessage = 'Server error saving model';
            }).always(function() {
                modelSaveCompleted = true;
                checkBothSavesComplete();
            });
            
            return; // Exit early for ShareAI
        }
        
        // Special handling for Ollama - save both server URL and model
        if (apiType === 'ollama') {
            const serverUrl = $('#private_llm_server_url').val();
            const model = $('#private_llm_model').val();
            
            if (!serverUrl.trim()) {
                showAdminNotice('Please enter a server URL before saving.', 'warning');
                return;
            }
            
            if (!model.trim()) {
                showAdminNotice('Please enter a model name before saving.', 'warning');
                return;
            }
            
            // Disable button and show loading state
            $btn.prop('disabled', true).text('Saving...');
            
            // Save both server URL and model
            let urlSaveCompleted = false;
            let modelSaveCompleted = false;
            let urlSaveSuccess = false;
            let modelSaveSuccess = false;
            
            function checkBothSavesComplete() {
                if (urlSaveCompleted && modelSaveCompleted) {
                    // Re-enable button and restore original text
                    $btn.prop('disabled', false).text(originalText);
                    
                    if (urlSaveSuccess && modelSaveSuccess) {
                        // Success state
                        $btn.addClass('success');
                        showAdminNotice('Ollama settings saved successfully!', 'success');
                        
                        // Update the default AI provider dropdown to include Ollama
                        updateDefaultProviderDropdown('ollama');
                        
                        // Reset button state after 2 seconds
                        setTimeout(function() {
                            $btn.removeClass('success');
                        }, 2000);
                    } else {
                        showAdminNotice('Error: Could not save Ollama settings.', 'error');
                    }
                }
            }
            
            // Save server URL
            $.post(config.ajax_url, {
                action: 'aiohm_save_individual_api_key',
                nonce: config.nonce || $('#aiohm_nonce').val() || $('[name*="nonce"]').val(),
                api_type: 'ollama',
                setting_name: 'private_llm_server_url',
                api_value: serverUrl
            }).done(function(response) {
                urlSaveSuccess = response.success;
                urlSaveCompleted = true;
                checkBothSavesComplete();
            }).fail(function() {
                urlSaveSuccess = false;
                urlSaveCompleted = true;
                checkBothSavesComplete();
            });
            
            // Save model
            $.post(config.ajax_url, {
                action: 'aiohm_save_individual_api_key',
                nonce: config.nonce || $('#aiohm_nonce').val() || $('[name*="nonce"]').val(),
                api_type: 'ollama',
                setting_name: 'private_llm_model',
                api_value: model
            }).done(function(response) {
                modelSaveSuccess = response.success;
                modelSaveCompleted = true;
                checkBothSavesComplete();
            }).fail(function() {
                modelSaveSuccess = false;
                modelSaveCompleted = true;
                checkBothSavesComplete();
            });
            
            return; // Exit early for Ollama
        }
        
        // Standard handling for other API providers
        if (!apiValue.trim()) {
            showAdminNotice('Please enter an API key before saving.', 'warning');
            return;
        }
        
        // Disable button and show loading state
        $btn.prop('disabled', true).text('Saving...');
        
        // Build the setting name based on API type
        let settingName;
        switch(apiType) {
            case 'openai':
                settingName = 'openai_api_key';
                break;
            case 'gemini':
                settingName = 'gemini_api_key';
                break;
            case 'claude':
                settingName = 'claude_api_key';
                break;
            default:
                settingName = apiType + '_api_key';
        }
        
        // AJAX request to save individual API key
        $.post(config.ajax_url, {
            action: 'aiohm_save_individual_api_key',
            nonce: config.nonce || $('#aiohm_nonce').val() || $('[name*="nonce"]').val(),
            api_type: apiType,
            setting_name: settingName,
            api_value: apiValue
        }).done(function(response) {
            if (response.success) {
                // Success state
                $btn.addClass('success');
                showAdminNotice(`${apiType.toUpperCase()} API key saved successfully!`, 'success');
                
                // Update the default AI provider dropdown to include this provider
                updateDefaultProviderDropdown(apiType);
                
                // Reset button state after 2 seconds
                setTimeout(function() {
                    $btn.removeClass('success');
                }, 2000);
            } else {
                showAdminNotice('Error: ' + (response.data?.message || 'Could not save API key.'), 'error');
            }
        }).fail(function(xhr, status, error) {
            showAdminNotice('A server error occurred while saving the API key.', 'error');
        }).always(function() {
            // Re-enable button and restore original text
            $btn.prop('disabled', false).text(originalText);
        });
    });

    // --- ShareAI Model Information Display ---
    const modelInformation = {
        // DeepSeek R1 Models
        'deepseek-r1:7b': {
            name: 'DeepSeek R1 7B',
            badge: 'new',
            parameters: '7 Billion',
            training: 'Reinforcement Learning from Human Feedback (RLHF) trained for reasoning tasks',
            bestFor: 'Mathematical reasoning, code generation, logical problem solving',
            speed: 'Very Fast',
            release: 'January 2025',
            description: 'DeepSeek R1 7B is a cutting-edge reasoning model trained with reinforcement learning. It excels at chain-of-thought reasoning, mathematical problem solving, and step-by-step logical analysis. Released in January 2025, it represents the latest advancement in reasoning-focused AI models.'
        },
        'deepseek-r1:14b': {
            name: 'DeepSeek R1 14B',
            badge: 'new',
            parameters: '14 Billion',
            training: 'Advanced RLHF with enhanced reasoning capabilities',
            bestFor: 'Complex reasoning, advanced mathematics, scientific analysis',
            speed: 'Fast',
            release: 'January 2025',
            description: 'The 14B variant of DeepSeek R1 offers enhanced reasoning capabilities with more parameters for complex problem solving. Ideal for advanced mathematical computations, scientific reasoning, and multi-step logical analysis tasks.'
        },
        'deepseek-r1:32b': {
            name: 'DeepSeek R1 32B',
            badge: 'powerful',
            parameters: '32 Billion',
            training: 'Extensive RLHF training for superior reasoning performance',
            bestFor: 'Expert-level reasoning, research tasks, complex problem solving',
            speed: 'Moderate',
            release: 'January 2025',
            description: 'DeepSeek R1 32B provides expert-level reasoning capabilities with 32 billion parameters. Perfect for research applications, complex mathematical proofs, and sophisticated analytical tasks requiring deep logical reasoning.'
        },
        'deepseek-r1:671b': {
            name: 'DeepSeek R1 671B',
            badge: 'powerful',
            parameters: '671 Billion',
            training: 'Massive-scale RLHF training for unprecedented reasoning ability',
            bestFor: 'Cutting-edge research, PhD-level analysis, complex scientific reasoning',
            speed: 'Slower (High Quality)',
            release: 'January 2025',
            description: 'The flagship DeepSeek R1 model with 671 billion parameters. This massive model delivers unprecedented reasoning capabilities, suitable for PhD-level research, complex scientific analysis, and the most challenging logical reasoning tasks.'
        },
        
        // Llama 4 Models
        'llama4:17b-scout-16e-instruct-fp16': {
            name: 'Llama 4 17B Scout',
            badge: 'recommended',
            parameters: '17 Billion',
            training: 'Instruction-tuned for dialogue and assistant tasks with 16-bit optimization',
            bestFor: 'General conversation, Q&A, creative writing, knowledge base tasks',
            speed: 'Fast (optimized 16-bit precision)',
            release: '2024',
            description: 'The Llama 4 17B Scout model offers an excellent balance of performance and efficiency. Specifically optimized for assistant tasks with 16-bit floating point precision, providing faster inference while maintaining high quality responses. Perfect for knowledge base applications and general conversational AI.'
        },
        'llama4:90b-instruct-fp16': {
            name: 'Llama 4 90B Instruct',
            badge: 'powerful',
            parameters: '90 Billion',
            training: 'Large-scale instruction tuning for complex tasks',
            bestFor: 'Complex reasoning, detailed analysis, professional writing',
            speed: 'Moderate (16-bit optimized)',
            release: '2024',
            description: 'Llama 4 90B Instruct is a large-scale model designed for complex tasks requiring detailed reasoning and analysis. With 90 billion parameters, it excels at professional writing, detailed explanations, and sophisticated problem-solving tasks.'
        },
        
        // Qwen 2.5 Models
        'qwen2.5:0.5b': {
            name: 'Qwen 2.5 0.5B',
            badge: 'fast',
            parameters: '0.5 Billion',
            training: 'Efficient training for lightweight applications',
            bestFor: 'Simple tasks, quick responses, resource-constrained environments',
            speed: 'Extremely Fast',
            release: 'Late 2024',
            description: 'Qwen 2.5 0.5B is the most lightweight model in the series, designed for speed and efficiency. Perfect for simple conversational tasks, quick Q&A, and applications where response speed is critical and computational resources are limited.'
        },
        'qwen2.5:1.5b': {
            name: 'Qwen 2.5 1.5B',
            badge: 'fast',
            parameters: '1.5 Billion',
            training: 'Balanced training for speed and capability',
            bestFor: 'General chat, basic reasoning, quick assistance',
            speed: 'Very Fast',
            release: 'Late 2024',
            description: 'Qwen 2.5 1.5B strikes a balance between speed and capability. Ideal for general conversational AI, basic reasoning tasks, and applications requiring quick, intelligent responses without heavy computational overhead.'
        },
        'qwen2.5:3b': {
            name: 'Qwen 2.5 3B',
            badge: 'fast',
            parameters: '3 Billion',
            training: 'Enhanced training for improved reasoning and knowledge',
            bestFor: 'Conversational AI, knowledge tasks, moderate complexity reasoning',
            speed: 'Fast',
            release: 'Late 2024',
            description: 'Qwen 2.5 3B offers improved reasoning and knowledge capabilities while maintaining fast inference speeds. Excellent for conversational AI applications, knowledge-based queries, and moderate complexity reasoning tasks.'
        },
        'qwen2.5:7b': {
            name: 'Qwen 2.5 7B',
            badge: 'fast',
            parameters: '7 Billion',
            training: 'Advanced training for strong general capabilities',
            bestFor: 'General purpose AI, detailed responses, moderate reasoning',
            speed: 'Fast',
            release: 'Late 2024',
            description: 'Qwen 2.5 7B provides strong general-purpose AI capabilities with detailed response generation. Suitable for a wide range of applications including detailed Q&A, moderate reasoning tasks, and comprehensive conversational AI.'
        },
        'qwen2.5:14b': {
            name: 'Qwen 2.5 14B',
            badge: 'powerful',
            parameters: '14 Billion',
            training: 'Comprehensive training for complex task handling',
            bestFor: 'Complex reasoning, detailed analysis, professional tasks',
            speed: 'Moderate',
            release: 'Late 2024',
            description: 'Qwen 2.5 14B offers enhanced capabilities for complex reasoning and detailed analysis. With 14 billion parameters, it excels at professional tasks, complex problem-solving, and generating detailed, well-structured responses.'
        },
        'qwen2.5:32b': {
            name: 'Qwen 2.5 32B',
            badge: 'powerful',
            parameters: '32 Billion',
            training: 'Large-scale training for advanced reasoning and knowledge',
            bestFor: 'Advanced reasoning, research tasks, complex analysis',
            speed: 'Moderate',
            release: 'Late 2024',
            description: 'Qwen 2.5 32B is designed for advanced reasoning and research applications. With 32 billion parameters, it provides sophisticated analytical capabilities, complex reasoning skills, and extensive knowledge for demanding AI tasks.'
        },
        'qwen2.5:72b': {
            name: 'Qwen 2.5 72B',
            badge: 'powerful',
            parameters: '72 Billion',
            training: 'Massive-scale training for expert-level performance',
            bestFor: 'Expert-level tasks, research, complex scientific reasoning',
            speed: 'Slower (High Quality)',
            release: 'Late 2024',
            description: 'Qwen 2.5 72B represents the flagship model in the series with expert-level capabilities. Ideal for research applications, complex scientific reasoning, and tasks requiring the highest level of AI performance and knowledge depth.'
        },
        
        // Llama 3.x Models
        'llama3.2:1b': {
            name: 'Llama 3.2 1B',
            badge: 'fast',
            parameters: '1 Billion',
            training: 'Efficient training for lightweight deployment',
            bestFor: 'Basic conversations, simple tasks, mobile applications',
            speed: 'Extremely Fast',
            release: 'Mid 2024',
            description: 'Llama 3.2 1B is optimized for lightweight deployment and mobile applications. Perfect for basic conversational AI, simple question answering, and scenarios where computational efficiency is paramount.'
        },
        'llama3.2:3b': {
            name: 'Llama 3.2 3B',
            badge: 'fast',
            parameters: '3 Billion',
            training: 'Balanced training for general-purpose applications',
            bestFor: 'General chat, basic reasoning, everyday AI assistance',
            speed: 'Very Fast',
            release: 'Mid 2024',
            description: 'Llama 3.2 3B provides a good balance between capability and efficiency. Suitable for general-purpose conversational AI, basic reasoning tasks, and everyday AI assistance applications.'
        },
        'llama3.3:70b': {
            name: 'Llama 3.3 70B',
            badge: 'powerful',
            parameters: '70 Billion',
            training: 'Advanced training for high-performance applications',
            bestFor: 'Complex reasoning, detailed analysis, professional applications',
            speed: 'Moderate',
            release: 'Late 2024',
            description: 'Llama 3.3 70B offers high-performance capabilities for demanding applications. With 70 billion parameters, it excels at complex reasoning, detailed analysis, and professional-grade AI assistance tasks.'
        },
        
        // Qwen 2.5 Coder Models
        'qwen2.5-coder:1.5b': {
            name: 'Qwen 2.5 Coder 1.5B',
            badge: 'coding',
            parameters: '1.5 Billion',
            training: 'Specialized training on code repositories and programming tasks',
            bestFor: 'Code completion, simple programming tasks, code explanation',
            speed: 'Very Fast',
            release: 'Late 2024',
            description: 'Qwen 2.5 Coder 1.5B is specifically trained for programming tasks. Excellent for code completion, simple programming assistance, code explanation, and basic software development tasks with fast inference speeds.'
        },
        'qwen2.5-coder:7b': {
            name: 'Qwen 2.5 Coder 7B',
            badge: 'coding',
            parameters: '7 Billion',
            training: 'Comprehensive coding training across multiple programming languages',
            bestFor: 'Code generation, debugging, algorithm design, software development',
            speed: 'Fast',
            release: 'Late 2024',
            description: 'Qwen 2.5 Coder 7B provides comprehensive programming assistance across multiple languages. Ideal for code generation, debugging, algorithm design, and general software development tasks with strong coding capabilities.'
        },
        'qwen2.5-coder:14b': {
            name: 'Qwen 2.5 Coder 14B',
            badge: 'coding',
            parameters: '14 Billion',
            training: 'Advanced coding training for complex software development',
            bestFor: 'Complex code generation, software architecture, advanced debugging',
            speed: 'Moderate',
            release: 'Late 2024',
            description: 'Qwen 2.5 Coder 14B offers advanced programming capabilities for complex software development. Perfect for sophisticated code generation, software architecture design, advanced debugging, and complex programming challenges.'
        },
        'qwen2.5-coder:32b': {
            name: 'Qwen 2.5 Coder 32B',
            badge: 'coding',
            parameters: '32 Billion',
            training: 'Expert-level coding training for professional software development',
            bestFor: 'Expert-level programming, system design, complex software projects',
            speed: 'Moderate',
            release: 'Late 2024',
            description: 'Qwen 2.5 Coder 32B represents expert-level programming assistance. Ideal for professional software development, system design, complex software projects, and the most challenging programming tasks requiring deep technical expertise.'
        }
    };

    function updateModelInfo(modelValue) {
        const $infoContent = $('#aiohm-model-info-content');
        const modelInfo = modelInformation[modelValue];
        
        if (!modelInfo) {
            $infoContent.html('<div class="aiohm-model-details active"><p>Model information not available for this selection.</p></div>');
            return;
        }
        
        const badgeClass = modelInfo.badge || 'default';
        const badgeText = modelInfo.badge === 'recommended' ? 'Recommended' : 
                         modelInfo.badge === 'new' ? 'New' :
                         modelInfo.badge === 'fast' ? 'Fast' :
                         modelInfo.badge === 'powerful' ? 'Powerful' :
                         modelInfo.badge === 'coding' ? 'Coding' : '';
        
        const html = `
            <div class="aiohm-model-details active" data-model="${modelValue}">
                <div class="aiohm-model-header">
                    <span class="aiohm-model-name">${modelInfo.name}</span>
                    ${badgeText ? `<span class="aiohm-model-badge ${badgeClass}">${badgeText}</span>` : ''}
                </div>
                <div class="aiohm-model-specs">
                    <div class="aiohm-spec-item">
                        <strong>Parameters:</strong> ${modelInfo.parameters}
                    </div>
                    <div class="aiohm-spec-item">
                        <strong>Training:</strong> ${modelInfo.training}
                    </div>
                    <div class="aiohm-spec-item">
                        <strong>Best For:</strong> ${modelInfo.bestFor}
                    </div>
                    <div class="aiohm-spec-item">
                        <strong>Speed:</strong> ${modelInfo.speed}
                    </div>
                    ${modelInfo.release ? `<div class="aiohm-spec-item">
                        <strong>Released:</strong> ${modelInfo.release}
                    </div>` : ''}
                </div>
                <div class="aiohm-model-description">
                    <p>${modelInfo.description}</p>
                </div>
            </div>
        `;
        
        $infoContent.html(html);
    }

    // Remove automatic model info display on dropdown change
    // Model info will only show after clicking save

    // --- Initial State ---
    updateLivePreview();
    
    // Check if ShareAI model is already selected and show its info
    const selectedShareAiModel = $('#shareai_model').val();
    if (selectedShareAiModel && selectedShareAiModel !== '') {
        updateModelInfo(selectedShareAiModel);
    }
    
    /**
     * Update the default AI provider dropdown to include a newly saved provider
     * @param {string} apiType - The API type that was just saved (openai, gemini, claude, shareai)
     */
    function updateDefaultProviderDropdown(apiType) {
        const $dropdown = $('#default_ai_provider');
        if ($dropdown.length === 0) return;
        
        const providerNames = {
            'openai': 'OpenAI',
            'gemini': 'Gemini', 
            'claude': 'Claude',
            'shareai': 'ShareAI',
            'ollama': 'Ollama Server'
        };
        
        const providerName = providerNames[apiType];
        if (!providerName) return;
        
        // Check if option already exists
        if ($dropdown.find(`option[value="${apiType}"]`).length > 0) {
            return; // Option already exists
        }
        
        // Determine which optgroup to add to
        let targetGroup;
        if (apiType === 'shareai' || apiType === 'ollama') {
            targetGroup = $dropdown.find('optgroup[label*="Free"]');
            if (targetGroup.length === 0) {
                // Create Free AI Services optgroup
                $dropdown.prepend('<optgroup label="Free AI Services"></optgroup>');
                targetGroup = $dropdown.find('optgroup[label*="Free"]');
            }
        } else {
            targetGroup = $dropdown.find('optgroup[label*="Premium"]');
            if (targetGroup.length === 0) {
                // Create Premium AI Services optgroup
                $dropdown.append('<optgroup label="Premium AI Services"></optgroup>');
                targetGroup = $dropdown.find('optgroup[label*="Premium"]');
            }
        }
        
        // Add the new option
        const newOption = `<option value="${apiType}">${providerName}</option>`;
        targetGroup.append(newOption);
        
        // Show a subtle indication that new provider is available
        $dropdown.addClass('updated-dropdown');
        setTimeout(() => {
            $dropdown.removeClass('updated-dropdown');
        }, 2000);
    }
});