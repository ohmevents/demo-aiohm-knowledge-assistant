/**
 * AIOHM Private Assistant Frontend Script
 * v1.5.0 - Adds auto-saving notes and deletion of projects/conversations.
 */
jQuery(document).ready(function($) {
    'use strict';

    // ====================================================================
    // 1. STATE & DOM
    // ====================================================================
    let currentProjectId = null;
    let currentConversationId = null;
    let originalPlaceholder = 'Type your message...';
    let noteSaveTimer = null;

    const appContainer = $('#aiohm-app-container');
    const chatInput = $('#chat-input');
    const sendBtn = $('#send-btn');
    const conversationPanel = $('#conversation-panel');
    const projectList = $('.aiohm-pa-project-list');
    const conversationList = $('.aiohm-pa-conversation-list');
    const projectTitle = $('#project-title');
    const loadingIndicator = $('#aiohm-chat-loading');
    const notificationBar = $('#aiohm-pa-notification');
    const welcomeInstructions = $('#welcome-instructions');
    const assistantName = aiohm_private_chat_params.assistantName || 'Assistant';

    const notesInput = $('#aiohm-pa-notes-textarea');
    const saveNoteBtn = $('#aiohm-pa-save-note-btn');
    const newProjectBtn = $('#new-project-btn');
    const newChatBtn = $('#new-chat-btn');
    const addToKbBtn = $('#add-to-kb-btn');
    const sidebarToggleBtn = $('#sidebar-toggle');
    const notesToggleBtn = $('#toggle-notes-btn');
    const closeNotesBtn = $('#close-notes-btn');
    const fullscreenBtn = $('#fullscreen-toggle-btn');


    // ====================================================================
    // 2. HELPER & UI FUNCTIONS
    // ====================================================================

    let noticeTimer;

    function showNotification(message, type = 'success') {
        const notificationMessage = notificationBar.find('p');
        notificationMessage.text(message);
        notificationBar.removeClass('success error').addClass(type);
        notificationBar.fadeIn().delay(4000).fadeOut();
    }

    function showAdminNotice(message, type = 'success', persistent = false) {
        clearTimeout(noticeTimer);
        let $noticeDiv = $('#aiohm-admin-notice');
        
        // Clear existing classes and add new type
        $noticeDiv.removeClass('notice-success notice-error notice-warning notice-info').addClass('notice-' + type);
        
        // Set message content
        $noticeDiv.find('p').html(message);
        
        // Show notice with fade in effect
        $noticeDiv.fadeIn(300, function() {
            // Auto-focus for accessibility after fade in completes
            $noticeDiv.focus();
            
            // Announce to screen readers
            if (type === 'error') {
                $noticeDiv.attr('aria-live', 'assertive');
            } else {
                $noticeDiv.attr('aria-live', 'polite');
            }
        });
        
        // Handle dismiss button
        $noticeDiv.off('click.notice-dismiss').on('click.notice-dismiss', '.aiohm-notice-dismiss', function() {
            $noticeDiv.fadeOut(300);
        });
        
        // Auto-hide after timeout (unless persistent)
        if (!persistent) {
            noticeTimer = setTimeout(() => {
                if ($noticeDiv.is(':visible')) {
                    $noticeDiv.fadeOut(300);
                }
            }, 7000);
        }
    }

    function appendMessage(sender, text) {
        const messageClass = sender.toLowerCase() === 'user' ? 'user' : 'assistant';
        const senderName = sender.toLowerCase() === 'user' ? 'You' : assistantName;
        
        // Enhanced formatting for AI responses
        let formattedText = text;
        if (messageClass === 'assistant') {
            // Check if the text contains a markdown table
            const hasTable = /\|.*\|/.test(text) && /\|[-\s:]+\|/.test(text);
            
            if (hasTable) {
                formattedText = formatMarkdownTable(text);
            } else {
                // Clean and simple formatting for non-table content
                formattedText = text
                    // Preserve line breaks
                    .replace(/\n/g, '<br>')
                    // Bold text: **text** -> <strong>text</strong>
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    // Handle numbered lists (1., 2., 3., etc.)
                    .replace(/^(\d+)\.\s(.+)$/gm, '<div class="numbered-item"><span class="number">$1.</span> $2</div>')
                    // Handle bullet points
                    .replace(/^•\s(.+)$/gm, '<div class="bullet-item">• $1</div>')
                    .replace(/^-\s(.+)$/gm, '<div class="bullet-item">• $1</div>');
            }
        }
        
        const copyButton = messageClass === 'assistant' ? 
            '<button class="copy-message-btn" title="Copy message"><span class="dashicons dashicons-admin-page"></span></button>' : '';
        
        const messageHTML = `
            <div class="message ${messageClass}" data-message-id="${Date.now()}">
                <div class="message-content">
                    <div class="message-header">
                        <strong>${senderName}:</strong>
                        ${copyButton}
                    </div>
                    <div class="message-text">${formattedText}</div>
                </div>
            </div>`;
        
        conversationPanel.append(messageHTML);
        
        // If it's an AI response, scroll to the beginning of the response for better UX
        if (messageClass === 'assistant') {
            const newMessage = conversationPanel.find('.message').last();
            setTimeout(() => {
                newMessage[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        } else {
            // For user messages, scroll to bottom as usual
            conversationPanel.scrollTop(conversationPanel[0].scrollHeight);
        }
    }
    
    function formatMarkdownTable(text) {
        // Split text into lines
        const lines = text.split('\n');
        let result = '';
        let inTable = false;
        let tableLines = [];
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            
            // Check if this line is part of a table
            if (line.includes('|') && line.split('|').length > 2) {
                if (!inTable) {
                    // Starting a new table
                    inTable = true;
                    tableLines = [];
                }
                tableLines.push(line);
                
                // Check if next line is not a table line (or end of text)
                const nextLine = i + 1 < lines.length ? lines[i + 1].trim() : '';
                if (!nextLine.includes('|') || nextLine.split('|').length <= 2) {
                    // End of table, process it
                    result += processTableLines(tableLines);
                    inTable = false;
                    tableLines = [];
                }
            } else {
                // Not a table line
                if (inTable) {
                    // Process accumulated table and end table mode
                    result += processTableLines(tableLines);
                    inTable = false;
                    tableLines = [];
                }
                
                // Process non-table content
                if (line) {
                    result += '<p>' + line
                        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\*(.*?)\*/g, '<em>$1</em>') + '</p>';
                } else {
                    result += '<br>';
                }
            }
        }
        
        // Handle case where table is at the end
        if (inTable && tableLines.length > 0) {
            result += processTableLines(tableLines);
        }
        
        return result;
    }
    
    function processTableLines(tableLines) {
        if (tableLines.length < 2) return '';
        
        let html = '<div class="table-container"><table class="markdown-table">';
        let headerProcessed = false;
        
        for (let i = 0; i < tableLines.length; i++) {
            const line = tableLines[i];
            const cells = line.split('|').map(cell => cell.trim()).filter(cell => cell !== '');
            
            // Skip separator lines (like |-----|-----|)
            if (line.includes('---') || line.includes(':--') || line.includes('--:')) {
                continue;
            }
            
            if (!headerProcessed) {
                // First non-separator line is header
                html += '<thead><tr>';
                cells.forEach(cell => {
                    html += '<th>' + cell.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') + '</th>';
                });
                html += '</tr></thead><tbody>';
                headerProcessed = true;
            } else {
                // Data rows
                html += '<tr>';
                cells.forEach(cell => {
                    html += '<td>' + cell
                        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\*(.*?)\*/g, '<em>$1</em>') + '</td>';
                });
                html += '</tr>';
            }
        }
        
        html += '</tbody></table></div>';
        return html;
    }

    function updateChatUIState() {
        const isProjectSelected = !!currentProjectId;
        const isConversationActive = !!currentConversationId;
        chatInput.prop('disabled', !isProjectSelected);
        sendBtn.prop('disabled', !isProjectSelected);
        chatInput.attr('placeholder', isProjectSelected ? originalPlaceholder : 'Select a project to begin...');
        addToKbBtn.prop('disabled', !isConversationActive);
        notesInput.prop('disabled', !isProjectSelected);
        saveNoteBtn.prop('disabled', !isProjectSelected);

        if (!isProjectSelected) {
            projectTitle.text('Select a Project');
            notesInput.val('');
        }
    }

    function setFullscreen(force = null) {
        const shouldBeFullscreen = force !== null ? force : !appContainer.hasClass('fullscreen-mode');
        appContainer.toggleClass('fullscreen-mode', shouldBeFullscreen);
        $('body').toggleClass('aiohm-fullscreen-body-no-scroll', shouldBeFullscreen);
        $('html').toggleClass('aiohm-fullscreen-body-no-scroll', shouldBeFullscreen);
        const icon = fullscreenBtn.find('.dashicons');
        if (shouldBeFullscreen) {
            fullscreenBtn.attr('title', 'Exit Fullscreen');
            icon.removeClass('dashicons-fullscreen-alt').addClass('dashicons-fullscreen-exit-alt');
        } else {
            fullscreenBtn.attr('title', 'Toggle Fullscreen');
            icon.removeClass('dashicons-fullscreen-exit-alt').addClass('dashicons-fullscreen-alt');
        }
    }


    // ====================================================================
    // 3. CORE & AJAX FUNCTIONALITY
    // ====================================================================

    /**
     * MY MISTAKE WAS HERE. This function is now fixed.
     * It correctly uses the 'action' and 'nonce' parameters passed to it,
     * so that all AJAX calls work, not just the chat.
     */
    function performAjaxRequest(action, data, showLoading = true) {
        if (showLoading) {
            loadingIndicator.show();
        }
        return $.ajax({
            url: aiohm_private_chat_params.ajax_url,
            type: 'POST',
            data: {
                action: action, // Use the action passed into the function
                nonce: aiohm_private_chat_params.nonce, // The key must be 'nonce'
                ...data
            }
        }).always(function() {
            if (showLoading) {
                loadingIndicator.hide();
            }
        });
    }

    function loadHistory() {
        // This function will now work correctly because performAjaxRequest is fixed.
        return performAjaxRequest('aiohm_load_history', {}).done(function(response) {
            if (response.success) {
                projectList.empty();
                if (response.data.projects && response.data.projects.length > 0) {
                    response.data.projects.forEach(proj => {
                        const projectHTML = `
                            <div class="aiohm-pa-list-item-wrapper">
                                <a href="#" class="aiohm-pa-list-item" data-id="${proj.id}">${proj.name}</a>
                                <span class="delete-icon delete-project" data-id="${proj.id}" title="Delete Project">&times;</span>
                            </div>`;
                        projectList.append(projectHTML);
                    });
                } else {
                    projectList.append('<p class="aiohm-no-items">No projects yet.</p>');
                }

                conversationList.empty();
                if (response.data.conversations && response.data.conversations.length > 0) {
                    response.data.conversations.forEach(convo => {
                        const conversationHTML = `
                            <div class="aiohm-pa-list-item-wrapper">
                                <a href="#" class="aiohm-pa-list-item" data-id="${convo.id}">${convo.title}</a>
                                <span class="delete-icon delete-conversation" data-id="${convo.id}" title="Delete Conversation">&times;</span>
                            </div>`;
                        conversationList.append(conversationHTML);
                    });
                } else {
                    conversationList.append('<p class="aiohm-no-items">No conversations yet.</p>');
                }
            }
        });
    }

    function sendMessage() {
        const message = chatInput.val().trim();
        if (!message) return;
        if (!currentProjectId) {
            showAdminNotice('Please select a project first before sending a message.', 'warning');
            return;
        }
        welcomeInstructions.hide();
        appendMessage('user', message);
        chatInput.val('');

        // The action is 'aiohm_private_chat' to match our fix in core-init.php
        performAjaxRequest('aiohm_private_chat', {
            message: message,
            project_id: currentProjectId,
            conversation_id: currentConversationId
        }).done(function(response) {
            if (response.success) {
                // The PHP backend sends 'reply', so we use that here.
                appendMessage(assistantName, response.data.reply);
                if (response.data.conversation_id) {
                    // This correctly saves the conversation ID for the next message.
                    currentConversationId = response.data.conversation_id;
                    // We also refresh the history to show the new chat entry
                    loadHistory();
                }
            } else {
                appendMessage(assistantName, 'Error: ' + (response.data.message || 'Could not get a response.'));
            }
        }).always(updateChatUIState);
    }

    // ====================================================================
    // 4. NEW FEATURE FUNCTIONS (NOTES & DELETION)
    // ====================================================================

    function saveNotes(projectId) {
        const noteContent = notesInput.val();
        performAjaxRequest('aiohm_save_project_notes', {
            project_id: projectId,
            note_content: noteContent
        }, false).done(function(response) {
             if(response.success) {
                // Notes saved successfully
             }
        });
    }

    function loadNotes(projectId) {
        performAjaxRequest('aiohm_load_project_notes', { project_id: projectId }).done(function(response) {
            if (response.success) {
                notesInput.val(response.data.note_content || '');
            } else {
                notesInput.val('');
            }
        });
    }
    
    // ====================================================================
    // 5. EVENT LISTENERS & NEW PROJECT VIEW
    // ====================================================================

    function displayProjectCreationView() {
        chatInput.prop('disabled', true);
        sendBtn.prop('disabled', true);

        const formHTML = `
            <div id="create-project-view" class="aiohm-create-project-container">
                <div class="aiohm-create-project-card">
                    <div class="aiohm-create-project-icon">
                        <span class="dashicons dashicons-plus-alt2"></span>
                    </div>
                    <h3 class="aiohm-create-project-title">Create a New Project</h3>
                    <p class="aiohm-create-project-description">Enter a name below to organize your chats and conversations.</p>
                    
                    <div class="aiohm-create-project-form">
                        <div class="aiohm-input-group">
                            <label for="new-project-input" class="aiohm-input-label">Project Name</label>
                            <input type="text" id="new-project-input" placeholder="My Awesome Project" class="aiohm-create-project-input">
                        </div>
                        
                        <div class="aiohm-create-project-actions">
                            <button id="create-project-submit" class="aiohm-create-project-btn primary">
                                <span class="dashicons dashicons-yes-alt"></span>
                                Create Project
                            </button>
                            <button id="cancel-project-create" class="aiohm-create-project-btn secondary">
                                <span class="dashicons dashicons-dismiss"></span>
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        conversationPanel.html(formHTML);
        
        // Use setTimeout to ensure DOM is ready
        setTimeout(function() {
            // Focus the input and add Enter key handler
            const $input = $('#new-project-input');
            $input.focus();
            
            // Handle Enter key submission
            $input.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#create-project-submit').trigger('click');
                }
            });
            
            // Add direct event handlers for the form buttons (single handler only)
            $('#create-project-submit').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $inputField = $('#new-project-input');
                const projectName = $inputField.val().trim();
                
                if (!projectName) {
                    showAdminNotice('Project name cannot be empty.', 'error');
                    return;
                }
                
                // Update button with loading state
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.html('<span class="dashicons dashicons-update"></span> Creating...').prop('disabled', true);
                
                performAjaxRequest('aiohm_create_project', { name: projectName }).done(response => {
                    if (response.success && response.data.new_project_id) {
                        showAdminNotice(`Project "${projectName}" created successfully!`, 'success');
                        restoreChatView();
                        loadHistory().done(function() {
                            const newProjectLink = projectList.find(`.aiohm-pa-list-item[data-id="${response.data.new_project_id}"]`);
                            if (newProjectLink.length) {
                                newProjectLink.trigger('click');
                            }
                        });
                    } else {
                        showAdminNotice('Error: ' + (response.data.message || 'Could not create project.'), 'error');
                        $btn.html(originalHtml).prop('disabled', false);
                    }
                }).fail(function() {
                    showAdminNotice('Network error occurred while creating project.', 'error');
                    $btn.html(originalHtml).prop('disabled', false);
                });
            });
            
            $('#cancel-project-create').off('click').on('click', function() {
                restoreChatView();
            });
        }, 50);
    }

    function restoreChatView() {
        conversationPanel.html('');
        conversationPanel.append(welcomeInstructions);
        welcomeInstructions.show();
        updateChatUIState();
    }
    
    // --- Event Listeners ---
    $('#private-chat-form').on('submit', e => { e.preventDefault(); sendMessage(); });
    
    // Handle Enter key to send message (Shift+Enter for new line)
    chatInput.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    newProjectBtn.on('click', displayProjectCreationView);
    
    // Remove the delegated event handler since we're using direct binding now

    projectList.on('click', '.aiohm-pa-list-item', function(e) {
        e.preventDefault();
        if (currentProjectId) {
            saveNotes(currentProjectId);
        }
        restoreChatView();
        $('.aiohm-pa-list-item').removeClass('active');
        $(this).addClass('active');
        currentProjectId = $(this).data('id');
        currentConversationId = null; 
        projectTitle.text($(this).text());
        conversationPanel.html(`<div class="message system"><p>New chat started in project: <strong>${$(this).text()}</strong></p></div>`);
        welcomeInstructions.hide();
        loadNotes(currentProjectId);
        updateChatUIState();
    });

    conversationList.on('click', '.aiohm-pa-list-item', function(e) {
        e.preventDefault();
        
        // Auto-save current project notes before switching conversations
        if (currentProjectId) {
            saveNotes(currentProjectId);
        }
        
        restoreChatView();
        $('.aiohm-pa-list-item').removeClass('active');
        $(this).addClass('active');
        currentConversationId = $(this).data('id');
        performAjaxRequest('aiohm_load_conversation', { conversation_id: currentConversationId }).done(response => {
            if (response.success && response.data.messages) {
                conversationPanel.empty();
                welcomeInstructions.hide();
                response.data.messages.forEach(msg => appendMessage(msg.sender, msg.message_content));
                currentProjectId = response.data.project_id;
                projectTitle.text(response.data.project_name || 'Conversation');
                projectList.find('.aiohm-pa-list-item').removeClass('active');
                projectList.find(`.aiohm-pa-list-item[data-id="${currentProjectId}"]`).addClass('active');
                loadNotes(currentProjectId);
            }
        }).always(updateChatUIState);
    });

    newChatBtn.on('click', function() {
        if (!currentProjectId) {
            showAdminNotice('Please select a project first.', 'error');
            return;
        }
        
        // Prepare for new chat without creating a conversation in database yet
        // The conversation will be created when the user sends their first message
        restoreChatView();
        currentConversationId = null; // Reset conversation ID
        conversationList.find('.aiohm-pa-list-item').removeClass('active');
        conversationPanel.html(`<div class="message system"><p>New chat prepared. Send your first message to begin the conversation.</p></div>`);
        welcomeInstructions.hide();
        updateChatUIState();
        
        // Focus on the input to encourage user to start typing
        chatInput.focus();
    });

    notesInput.on('keyup', function() {
        clearTimeout(noteSaveTimer);
        if (currentProjectId) {
            noteSaveTimer = setTimeout(() => saveNotes(currentProjectId), 1500);
        }
    });

    projectList.on('click', '.delete-project', function(e) {
        e.stopPropagation();
        const projectId = $(this).data('id');
        const projectName = $(this).closest('.aiohm-pa-list-item-wrapper').find('.aiohm-pa-list-item').text();
        
        showAdminNotice(
            `Are you sure you want to delete "${projectName}" and all its conversations? This cannot be undone. ` +
            `<button class="aiohm-confirm-btn" data-action="delete-project" data-project-id="${projectId}">Delete Project</button>` +
            `<button class="aiohm-cancel-btn">Cancel</button>`,
            'warning',
            true
        );
    });

    // Handle project deletion confirmation
    $(document).on('click', '.aiohm-confirm-btn[data-action="delete-project"]', function() {
        const projectId = $(this).data('project-id');
        $('#aiohm-admin-notice').fadeOut();
        
        performAjaxRequest('aiohm_delete_project', { project_id: projectId }).done(function(response) {
            if (response.success) {
                showAdminNotice('Project deleted successfully.', 'success');
                if(currentProjectId === projectId) {
                    currentProjectId = null;
                    currentConversationId = null;
                    restoreChatView();
                    updateChatUIState();
                }
                loadHistory();
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Could not delete project.'), 'error');
            }
        });
    });

    // Handle cancellation
    $(document).on('click', '.aiohm-cancel-btn', function() {
        $('#aiohm-admin-notice').fadeOut();
    });

    conversationList.on('click', '.delete-conversation', function(e) {
        e.stopPropagation();
        const conversationId = $(this).data('id');
        const conversationName = $(this).closest('.aiohm-pa-list-item-wrapper').find('.aiohm-pa-list-item').text();
        
        showAdminNotice(
            `Are you sure you want to delete "${conversationName}"? This cannot be undone. ` +
            `<button class="aiohm-confirm-btn" data-action="delete-conversation" data-conversation-id="${conversationId}">Delete Conversation</button>` +
            `<button class="aiohm-cancel-btn">Cancel</button>`,
            'warning',
            true
        );
    });

    // Handle conversation deletion confirmation
    $(document).on('click', '.aiohm-confirm-btn[data-action="delete-conversation"]', function() {
        const conversationId = $(this).data('conversation-id');
        $('#aiohm-admin-notice').fadeOut();
        
        performAjaxRequest('aiohm_delete_conversation', { conversation_id: conversationId }).done(function(response) {
            if (response.success) {
                showAdminNotice('Conversation deleted successfully.', 'success');
                 if(currentConversationId === conversationId) {
                    currentConversationId = null;
                    restoreChatView();
                    updateChatUIState();
                }
                loadHistory();
            } else {
                showAdminNotice('Error: ' + (response.data.message || 'Could not delete conversation.'), 'error');
            }
        });
    });

    sidebarToggleBtn.on('click', () => appContainer.toggleClass('sidebar-open'));
    notesToggleBtn.on('click', () => appContainer.toggleClass('notes-open'));
    closeNotesBtn.on('click', () => appContainer.removeClass('notes-open'));
    fullscreenBtn.on('click', () => setFullscreen());
    notificationBar.on('click', '.close-btn', () => notificationBar.fadeOut());

    // Copy message button handler with hover preview
    conversationPanel.on('click', '.copy-message-btn', function() {
        const messageText = $(this).closest('.message-content').find('.message-text').text();
        
        // Create a temporary textarea to copy the text
        const tempTextarea = $('<textarea>');
        tempTextarea.val(messageText);
        $('body').append(tempTextarea);
        tempTextarea.select();
        document.execCommand('copy');
        tempTextarea.remove();
        
        // Show feedback
        const $btn = $(this);
        const originalIcon = $btn.find('.dashicons');
        originalIcon.removeClass('dashicons-admin-page').addClass('dashicons-yes');
        
        setTimeout(() => {
            originalIcon.removeClass('dashicons-yes').addClass('dashicons-admin-page');
        }, 1500);
        
        showAdminNotice('Message copied to clipboard!', 'success');
    });

    // Copy button hover preview
    conversationPanel.on('mouseenter', '.copy-message-btn', function() {
        const $messageContent = $(this).closest('.message-content');
        const messageText = $messageContent.find('.message-text').text();
        
        // Add preview border and show what will be copied
        $messageContent.addClass('copy-preview-active');
        
        // Create or update preview tooltip
        const $tooltip = $('<div class="copy-preview-tooltip">').text('Copy: ' + (messageText.length > 100 ? messageText.substring(0, 100) + '...' : messageText));
        $('body').append($tooltip);
        
        // Position tooltip near button
        const btnOffset = $(this).offset();
        $tooltip.css({
            'position': 'absolute',
            'top': btnOffset.top - 40,
            'left': btnOffset.left - 100,
            'z-index': 10000
        });
        
        // Store tooltip reference
        $(this).data('tooltip', $tooltip);
    });

    conversationPanel.on('mouseleave', '.copy-message-btn', function() {
        const $messageContent = $(this).closest('.message-content');
        $messageContent.removeClass('copy-preview-active');
        
        // Remove tooltip
        const $tooltip = $(this).data('tooltip');
        if ($tooltip) {
            $tooltip.remove();
            $(this).removeData('tooltip');
        }
    });

    // File upload button handler
    $('#upload-file-btn').on('click', function() {
        if (!currentProjectId) {
            showAdminNotice('Please select a project first.', 'error');
            return;
        }
        $('#file-upload-input').click();
    });

    // File input change handler
    $('#file-upload-input').on('change', function() {
        const files = this.files;
        if (files.length > 0) {
            uploadFiles(files);
        }
    });

    // Research online button handler
    $('#research-online-prompt-btn').on('click', function() {
        if (!currentProjectId) {
            showAdminNotice('Please select a project first.', 'error');
            return;
        }
        displayResearchModal();
    });

    // Download PDF button handler
    $('#download-pdf-btn').on('click', function() {
        if (!currentConversationId) {
            showAdminNotice('Please start a conversation before downloading PDF.', 'error');
            return;
        }
        
        const $btn = $(this);
        const originalTitle = $btn.attr('title');
        $btn.prop('disabled', true).attr('title', 'Generating PDF...');
        
        // Create a form and submit it to trigger PDF download
        const form = $('<form>', {
            method: 'POST',
            action: aiohm_private_chat_params.ajax_url,
            target: '_blank'
        });
        
        form.append($('<input>', {type: 'hidden', name: 'action', value: 'aiohm_download_conversation_pdf'}));
        form.append($('<input>', {type: 'hidden', name: 'nonce', value: aiohm_private_chat_params.nonce}));
        form.append($('<input>', {type: 'hidden', name: 'conversation_id', value: currentConversationId}));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        setTimeout(() => {
            $btn.prop('disabled', false).attr('title', originalTitle);
        }, 3000);
    });

    // Add to KB button handler with proper admin notice confirmation
    $('#add-to-kb-btn').on('click', function() {
        if (!currentConversationId) {
            showAdminNotice('Please start a conversation before adding to knowledge base.', 'error');
            return;
        }
        
        // Show proper admin notice confirmation dialog
        showAdminNotice(
            'Are you sure you want to add this conversation to the Knowledge Base as a private item? ' +
            'This will save the entire conversation history for future reference. ' +
            '<button class="aiohm-confirm-btn" data-action="add-chat-kb" data-conversation-id="' + currentConversationId + '">Confirm</button> ' +
            '<button class="aiohm-cancel-btn">Cancel</button>',
            'warning',
            true
        );
    });

    // Handle add chat to KB confirmation
    $(document).on('click', '.aiohm-confirm-btn[data-action="add-chat-kb"]', function() {
        const conversationId = $(this).data('conversation-id');
        const $btn = $('#add-to-kb-btn');
        const originalTitle = $btn.attr('title');
        
        $('#aiohm-admin-notice').fadeOut();
        $btn.prop('disabled', true).attr('title', 'Adding to KB...');
        
        performAjaxRequest('aiohm_add_conversation_to_kb', {
            conversation_id: conversationId
        }).done(function(response) {
            if (response.success) {
                showAdminNotice('Conversation added to knowledge base successfully!', 'success');
            } else {
                showAdminNotice('Error adding to KB: ' + (response.data.message || 'Unknown error'), 'error');
            }
        }).fail(function() {
            showAdminNotice('Error adding conversation to knowledge base.', 'error');
        }).always(function() {
            $btn.prop('disabled', false).attr('title', originalTitle);
        });
    });

    // Speech-to-text microphone button handler
    $('#activate-audio-btn').on('click', function() {
        if (!currentProjectId) {
            showAdminNotice('Please select a project first.', 'error');
            return;
        }
        
        // Check if browser supports speech recognition
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            showAdminNotice('Speech recognition is not supported in your browser.', 'error');
            return;
        }
        
        const $btn = $(this);
        const $icon = $btn.find('.dashicons');
        
        // Initialize speech recognition
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';
        
        // Start recording
        $btn.prop('disabled', true).attr('title', 'Listening...');
        $icon.removeClass('dashicons-microphone').addClass('dashicons-controls-pause');
        $btn.css('background-color', '#dc3545'); // Red color when recording
        
        showAdminNotice('Listening... Speak now!', 'success');
        
        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            if (transcript.trim()) {
                // Insert the transcribed text into the chat input
                const currentText = chatInput.val();
                const newText = currentText ? currentText + ' ' + transcript : transcript;
                chatInput.val(newText);
                chatInput.focus();
                showAdminNotice('Speech captured successfully!', 'success');
            } else {
                showAdminNotice('No speech detected. Please try again.', 'error');
            }
        };
        
        recognition.onerror = function(event) {
            let errorMessage = 'Speech recognition error: ';
            switch(event.error) {
                case 'no-speech':
                    errorMessage += 'No speech detected.';
                    break;
                case 'audio-capture':
                    errorMessage += 'No microphone found.';
                    break;
                case 'not-allowed':
                    errorMessage += 'Microphone access denied.';
                    break;
                default:
                    errorMessage += event.error;
            }
            showAdminNotice(errorMessage, 'error');
        };
        
        recognition.onend = function() {
            // Reset button state
            $btn.prop('disabled', false).attr('title', 'Activate voice-to-text');
            $icon.removeClass('dashicons-controls-pause').addClass('dashicons-microphone');
            $btn.css('background-color', ''); // Reset color
        };
        
        try {
            recognition.start();
        } catch (error) {
            showAdminNotice('Could not start speech recognition: ' + error.message, 'error');
            recognition.onend(); // Reset button state
        }
    });

    // ====================================================================
    // 5. FILE UPLOAD & RESEARCH FUNCTIONS
    // ====================================================================
    
    function displayResearchModal() {
        chatInput.prop('disabled', true);
        sendBtn.prop('disabled', true);

        const researchPersonas = {
            'journalist': {
                name: 'Journalist',
                icon: 'dashicons-edit',
                prompt: 'Please research the following URL as an investigative journalist and provide comprehensive coverage including: key facts, credible sources, potential biases, timeline of events, stakeholder perspectives, and verification of claims. Focus on accuracy, objectivity, and uncovering the complete story.'
            },
            'seo_guru': {
                name: 'SEO Specialist',
                icon: 'dashicons-chart-line',
                prompt: 'Please research the following URL from an SEO perspective and analyze: keyword optimization, content structure, meta descriptions, backlink opportunities, technical SEO issues, competitor analysis, search intent alignment, and recommendations for improving search rankings.'
            },
            'web_designer': {
                name: 'Web Designer',
                icon: 'dashicons-art',
                prompt: 'Please research the following URL from a web design perspective and evaluate: visual hierarchy, user experience, responsive design, loading speed, accessibility features, navigation structure, color schemes, typography choices, and overall design effectiveness.'
            },
            'marketer': {
                name: 'Digital Marketer',
                icon: 'dashicons-megaphone',
                prompt: 'Please research the following URL from a marketing perspective and analyze: target audience, value proposition, conversion opportunities, content marketing strategy, social media integration, call-to-action effectiveness, brand positioning, and competitive advantages.'
            },
            'developer': {
                name: 'Web Developer',
                icon: 'dashicons-editor-code',
                prompt: 'Please research the following URL from a technical development perspective and assess: code quality, performance optimization, security measures, framework usage, API integrations, database structure implications, scalability considerations, and technical best practices.'
            },
            'business_analyst': {
                name: 'Business Analyst',
                icon: 'dashicons-analytics',
                prompt: 'Please research the following URL from a business analysis perspective and examine: business model, revenue streams, market positioning, competitive landscape, growth opportunities, operational efficiency, customer segments, and strategic implications.'
            }
        };

        const modalHTML = `
            <div id="research-modal-view" class="aiohm-create-project-container">
                <div class="aiohm-create-project-card">
                    <div class="aiohm-create-project-icon">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <h3 class="aiohm-create-project-title">Research Website Online</h3>
                    <p class="aiohm-create-project-description">Choose your research perspective and enter the website URL to analyze.</p>
                    
                    <div class="aiohm-create-project-form">
                        <div class="aiohm-input-group">
                            <label for="research-persona-select" class="aiohm-input-label">Research Perspective</label>
                            <select id="research-persona-select" class="aiohm-create-project-input">
                                ${Object.entries(researchPersonas).map(([key, persona]) => 
                                    `<option value="${key}"><span class="dashicons ${persona.icon}"></span> ${persona.name}</option>`
                                ).join('')}
                            </select>
                        </div>
                        
                        <div class="aiohm-input-group">
                            <label for="research-url-input" class="aiohm-input-label">Website URL</label>
                            <input type="url" id="research-url-input" placeholder="https://example.com" class="aiohm-create-project-input">
                        </div>
                        
                        <div class="aiohm-create-project-actions">
                            <button id="start-research-submit" class="aiohm-create-project-btn primary">
                                <span class="dashicons dashicons-search"></span>
                                Start Research
                            </button>
                            <button id="cancel-research" class="aiohm-create-project-btn secondary">
                                <span class="dashicons dashicons-dismiss"></span>
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        conversationPanel.html(modalHTML);
        
        // Store personas for later use
        window.researchPersonas = researchPersonas;
        
        // Use setTimeout to ensure DOM is ready
        setTimeout(function() {
            // Focus the URL input
            const $urlInput = $('#research-url-input');
            $urlInput.focus();
            
            // Handle Enter key submission
            $urlInput.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#start-research-submit').trigger('click');
                }
            });
            
            // Handle form submission
            $('#start-research-submit').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const selectedPersona = $('#research-persona-select').val();
                const websiteUrl = $('#research-url-input').val().trim();
                
                if (!websiteUrl) {
                    showAdminNotice('Please enter a website URL.', 'error');
                    return;
                }
                
                if (!websiteUrl.match(/^https?:\/\/.+/)) {
                    showAdminNotice('Please enter a valid URL starting with http:// or https://', 'error');
                    return;
                }
                
                // Update button with loading state
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.html('<span class="dashicons dashicons-update"></span> Researching...').prop('disabled', true);
                
                startWebsiteResearch(selectedPersona, websiteUrl, $btn, originalHtml);
            });
            
            $('#cancel-research').off('click').on('click', function() {
                restoreChatView();
            });
        }, 50);
    }
    
    function startWebsiteResearch(persona, url, $btn, originalBtnHtml) {
        const personaData = window.researchPersonas[persona];
        const researchPrompt = `${personaData.prompt}\n\nWebsite URL: ${url}`;
        
        // Restore chat view first
        restoreChatView();
        
        // Add user message showing the research request
        appendMessage('user', `Research this website as a ${personaData.name}: ${url}`);
        
        // Perform the actual research
        performAjaxRequest('aiohm_research_online', {
            url: url,
            project_id: currentProjectId,
            conversation_id: currentConversationId,
            research_prompt: researchPrompt
        }).done(function(response) {
            if (response.success && response.data.reply) {
                appendMessage(assistantName, response.data.reply);
                if (response.data.conversation_id) {
                    currentConversationId = response.data.conversation_id;
                    loadHistory();
                }
            } else {
                appendMessage(assistantName, 'Error: ' + (response.data.message || 'Could not research the website.'));
            }
        }).fail(function() {
            appendMessage(assistantName, 'Error: Network failure while researching the website.');
        }).always(function() {
            updateChatUIState();
        });
    }
    
    function uploadFiles(files) {
        if (!currentProjectId) {
            showAdminNotice('Please select a project first.', 'error');
            return;
        }

        // Create FormData object
        const formData = new FormData();
        
        // Add files to FormData
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        
        // Add other required data
        formData.append('action', 'aiohm_upload_project_files');
        formData.append('project_id', currentProjectId);
        formData.append('nonce', aiohm_private_chat_params.nonce);

        // Show upload progress
        showAdminNotice(`Uploading ${files.length} file(s)...`, 'success');

        // Perform AJAX upload
        $.ajax({
            url: aiohm_private_chat_params.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    let message = response.data.message;
                    if (response.data.errors && response.data.errors.length > 0) {
                        message += '. Some files had errors: ' + response.data.errors.join(', ');
                    }
                    showAdminNotice(message, 'success');
                    
                    // Display uploaded files in chat
                    if (response.data.files && response.data.files.length > 0) {
                        displayUploadedFiles(response.data.files);
                    }
                } else {
                    let errorMessage = response.data.message || 'Upload failed';
                    if (response.data.errors && response.data.errors.length > 0) {
                        errorMessage += ': ' + response.data.errors.join(', ');
                    }
                    showAdminNotice(errorMessage, 'error');
                }
                
                // Clear the file input
                $('#file-upload-input').val('');
            },
            error: function(xhr, status, error) {
                showAdminNotice('Upload failed: ' + error, 'error');
                $('#file-upload-input').val('');
            }
        });
    }

    function displayUploadedFiles(files) {
        // Create a message showing the uploaded files
        let fileList = files.map(file => {
            const fileSize = (file.size / 1024).toFixed(1) + ' KB';
            const fileIcon = getFileIcon(file.type);
            return `${fileIcon} ${file.original_name} (${fileSize})`;
        }).join('<br>');

        const fileMessage = `
            <div class="message system">
                <div class="message-content">
                    <strong>📁 Files uploaded to project:</strong><br>
                    ${fileList}
                </div>
            </div>
        `;

        conversationPanel.append(fileMessage);
        conversationPanel.scrollTop(conversationPanel[0].scrollHeight);
    }

    function getFileIcon(fileType) {
        const iconMap = {
            'txt': '📄',
            'pdf': '📋',
            'doc': '📝',
            'docx': '📝',
            'jpg': '🖼️',
            'jpeg': '🖼️',
            'png': '🖼️',
            'gif': '🖼️',
            'mp3': '🎵',
            'wav': '🎵',
            'm4a': '🎵',
            'ogg': '🎵'
        };
        return iconMap[fileType] || '📎';
    }


    // Add Note to KB button handler
    $('#add-note-to-kb-btn, #add-note-to-kb-btn').on('click', handleAddNoteToKB);
    $(document).on('click', '#add-note-to-kb-btn', handleAddNoteToKB);

    function handleAddNoteToKB() {
        const notesInput = $('#aiohm-pa-notes-textarea');
        const noteContent = notesInput.val().trim();
        
        if (!noteContent) {
            showAdminNotice('Note cannot be empty!', 'error');
            return;
        }
        if (!currentProjectId) {
            showAdminNotice('Please select a project before adding a note to the Knowledge Base.', 'error');
            return;
        }

        const previewText = noteContent.substring(0, 100) + (noteContent.length > 100 ? '...' : '');
        showAdminNotice(
            'Are you sure you want to add this note to the Knowledge Base as a private item? ' +
            previewText + ' ' +
            '<button class="aiohm-confirm-btn" data-action="add-note-kb" data-project-id="' + currentProjectId + '" data-note-content="' + noteContent.replace(/"/g, '&quot;') + '">Confirm</button> ' +
            '<button class="aiohm-cancel-btn">Cancel</button>',
            'warning',
            true
        );
    }
    $(document).on('click', '.aiohm-confirm-btn[data-action="add-note-kb"]', function() {
        const projectId = $(this).data('project-id');
        const noteContent = $(this).data('note-content');
        const notesInput = $('#aiohm-pa-notes-textarea');
        
        $('#aiohm-admin-notice').fadeOut();
        
        // Proceed with AJAX call to save note to KB
        performAjaxRequest('aiohm_add_note_to_kb', {
            project_id: projectId,
            note_content: noteContent
        }).done(function(response) {
            if (response.success) {
                showAdminNotice('Note added to Knowledge Base successfully!', 'success');
                notesInput.val(''); // Clear the notes input
            } else {
                showAdminNotice('Error adding note to KB: ' + (response.data.message || 'Unknown error.'), 'error');
            }
        }).fail(function() {
            showAdminNotice('Error adding note to KB. Please try again.', 'error');
        });
    });

    // ====================================================================
    // 6. INITIALIZATION
    // ====================================================================
    function initialize() {
        appContainer.addClass('sidebar-open');
        loadHistory();
        updateChatUIState();
        if (aiohm_private_chat_params.startFullscreen) {
            setFullscreen(true);
        }
    }

    initialize();
});