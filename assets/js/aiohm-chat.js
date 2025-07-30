jQuery(document).ready(function($) {
    'use strict';

    // --- State Management ---
    let currentProjectId = null;
    let currentConversationId = null;

    // --- DOM Elements ---
    const appContainer = $('#aiohm-app-container');
    const chatInput = $('#chat-input');
    const sendBtn = $('#send-btn');
    const conversationPanel = $('#conversation-panel');
    const projectList = $('.aiohm-pa-project-list');
    const conversationList = $('.aiohm-pa-conversation-list');
    const projectTitle = $('#project-title');
    const loadingIndicator = $('#aiohm-chat-loading');
    const notesInput = $('#aiohm-pa-notes-textarea');
    const notificationBar = $('#aiohm-pa-notification');
    const notificationMessage = $('#aiohm-pa-notification p');


    // --- Helper Functions ---
    /**
     * Appends a message to the chat panel.
     * @param {string} sender - 'user' or the assistant's name.
     * @param {string} text - The message content.
     */
    function appendMessage(sender, text) {
        const messageClass = sender.toLowerCase() === 'user' ? 'user' : 'assistant';
        const senderName = sender.toLowerCase() === 'user' ? 'You' : sender;
        const messageHTML = `
            <div class="message ${messageClass}">
                <p><strong>${senderName}:</strong> ${text}</p>
            </div>`;
        conversationPanel.append(messageHTML);
        conversationPanel.scrollTop(conversationPanel[0].scrollHeight); // Auto-scroll
    }

    /**
     * Handles AJAX requests.
     * @param {string} action - The WordPress AJAX action hook.
     * @param {object} data - The data to send.
     * @returns {Promise}
     */
    function performAjaxRequest(action, data) {
        return $.ajax({
            url: aiohm_private_chat_params.ajax_url,
            type: 'POST',
            data: {
                action: action,
                nonce: aiohm_private_chat_params.nonce,
                ...data
            },
            beforeSend: function() {
                loadingIndicator.show();
                notificationBar.fadeOut(); // Hide any existing notifications
            },
            complete: function() {
                loadingIndicator.hide();
            }
        });
    }

    /**
     * Displays a notification message.
     * @param {string} message - The message to display.
     * @param {string} type - 'success' or 'error'.
     */
    function showNotification(message, type = 'success') {
        notificationMessage.text(message);
        notificationBar.removeClass('success error').addClass(type);
        notificationBar.fadeIn().delay(3000).fadeOut(); // Show for 3 seconds
    }


    // --- Core Functionality ---

    /**
     * Loads projects for the user. Conversations are loaded when a project is selected.
     */
    function loadProjects() {
        performAjaxRequest('aiohm_get_projects', {}).done(function(response) {
            if (response.success) {
                projectList.empty();
                if (response.data && response.data.length > 0) {
                    response.data.forEach(proj => {
                        projectList.append(`<a href="#" class="aiohm-pa-list-item" data-id="${proj.id}">${proj.project_name}</a>`);
                    });
                } else {
                    projectList.append('<p class="aiohm-no-items">No projects yet. Create one!</p>');
                }
                // Optional: Automatically select the first project if none is selected
                if (!currentProjectId && response.data && response.data.length > 0) {
                    // This will trigger the click handler to load conversations for the first project
                    projectList.find('.aiohm-pa-list-item').first().trigger('click');
                }
            } else {
                showNotification('Failed to load projects: ' + (response.data.message || 'Unknown error.'), 'error');
            }
        }).fail(function() {
            showNotification('Error loading projects. Please try again.', 'error');
        });
    }

    /**
     * Loads conversations for a given project ID.
     * @param {number} projectId - The ID of the project whose conversations to load.
     */
    function loadConversations(projectId) {
        if (!projectId) {
            conversationList.empty().append('<p class="aiohm-no-items">Select a project to see conversations.</p>');
            return;
        }

        performAjaxRequest('aiohm_get_conversations', { project_id: projectId }).done(function(response) {
            if (response.success) {
                conversationList.empty();
                if (response.data && response.data.length > 0) {
                    response.data.forEach(convo => {
                        conversationList.append(`<a href="#" class="aiohm-pa-list-item" data-id="${convo.id}">${convo.title}</a>`);
                    });
                } else {
                    conversationList.append('<p class="aiohm-no-items">No conversations yet for this project.</p>');
                }
            } else {
                showNotification('Failed to load conversations: ' + (response.data.message || 'Unknown error.'), 'error');
            }
        }).fail(function() {
            showNotification('Error loading conversations. Please try again.', 'error');
        });
    }


    /**
     * Handles sending a chat message.
     */
    function sendMessage() {
        const message = chatInput.val().trim();
        if (!message) {
            showNotification('Message cannot be empty.', 'error');
            return;
        }
        if (!currentProjectId) {
            showNotification('Please select a project before sending a message.', 'error');
            return;
        }

        appendMessage('user', message);
        chatInput.val('');

        performAjaxRequest('aiohm_private_assistant_chat', {
            message: message,
            project_id: currentProjectId,
            conversation_id: currentConversationId
        }).done(function(response) {
            if (response.success) {
                appendMessage('Assistant', response.data.reply);
                // If this was the first message of a new conversation
                if (response.data.conversation_id && !currentConversationId) {
                    currentConversationId = response.data.conversation_id;
                    loadConversations(currentProjectId); // Refresh conversation list for current project
                }
            } else {
                appendMessage('System', 'Error: Could not get a response. ' + (response.data.answer || response.data.message || ''));
                showNotification('Error getting AI response: ' + (response.data.answer || response.data.message || 'Unknown error.'), 'error');
            }
        }).fail(function() {
            appendMessage('System', 'Error: AJAX request failed.');
            showNotification('Error sending message. Please check your connection.', 'error');
        });
    }

    // --- Event Listeners ---

    // Send message on form submit
    $('#private-chat-form').on('submit', function(e) {
        e.preventDefault();
        sendMessage();
    });

    // Create New Project
    $('#new-project-btn').on('click', function() {
        const projectName = prompt('Enter a name for your new project:', 'New Project');
        if (projectName && projectName.trim() !== '') {
            performAjaxRequest('aiohm_create_project', { project_name: projectName.trim() }).done(function(response) {
                if (response.success) {
                    showNotification('Project "' + response.data.project_name + '" created successfully!');
                    loadProjects(); // Reload projects to show the new one
                    currentProjectId = response.data.id; // Set new project as current
                    projectTitle.text(response.data.project_name); // Update title
                    conversationPanel.html('<div class="message system"><p>New chat started in project: ' + response.data.project_name + '</p></div>');
                    chatInput.prop('disabled', false);
                    sendBtn.prop('disabled', false);
                    currentConversationId = null; // Start fresh for new project
                    loadConversations(currentProjectId); // Load conversations for the newly created project
                } else {
                    showNotification('Error creating project: ' + (response.data.message || 'Unknown error.'), 'error');
                }
            }).fail(function() {
                showNotification('Error creating project. Please try again.', 'error');
            });
        }
    });

    // Select a Project
    projectList.on('click', '.aiohm-pa-list-item', function(e) {
        e.preventDefault();
        $('.aiohm-pa-list-item').removeClass('active'); // Remove active from all
        $(this).addClass('active'); // Add active to clicked item

        currentProjectId = $(this).data('id');
        currentConversationId = null; // Start a new conversation for this project
        projectTitle.text($(this).text());
        conversationPanel.html('<div class="message system"><p>New chat started in project: ' + $(this).text() + '</p></div>');
        chatInput.prop('disabled', false);
        sendBtn.prop('disabled', false);
        
        loadConversations(currentProjectId); // Load conversations for the selected project
    });

    // Select a Conversation
    conversationList.on('click', '.aiohm-pa-list-item', function(e) {
        e.preventDefault();
        $('.aiohm-pa-list-item').removeClass('active'); // Remove active from all
        $(this).addClass('active'); // Add active to clicked item

        currentConversationId = $(this).data('id');
        projectTitle.text($(this).text()); // Set title to conversation title
        chatInput.prop('disabled', false);
        sendBtn.prop('disabled', false);

        // Fetch conversation history
        performAjaxRequest('aiohm_get_conversation_history', { conversation_id: currentConversationId }).done(function(response) {
            if (response.success && response.data) { // response.data directly contains messages array
                conversationPanel.empty(); // Clear current messages
                response.data.forEach(msg => {
                    appendMessage(msg.sender, msg.content); // Use msg.content as per core-init.php
                });
                // Find and set the current project ID from the conversation data (if needed, otherwise rely on project selection)
                // This might need a modification in handle_get_conversation_history_ajax to return project_id
            } else {
                showNotification('Error loading conversation: ' + (response.data.message || 'Unknown error.'), 'error');
            }
        }).fail(function() {
            showNotification('Error loading conversation. Please try again.', 'error');
        });
    });


    // --- Research Modal Logic (Existing) ---
    const researchModal = $('#research-prompt-modal'); // Ensure this ID is correct, typically it's specific for a modal.
    const researchPromptList = $('#research-prompt-list');
    const customResearchPrompt = $('#custom-research-prompt');

    $('#research-online-prompt-btn').on('click', function() {
        if(currentProjectId) {
             researchModal.show();
        } else {
            showNotification("Please select a project first to research online.", 'error');
        }
    });

    $('.aiohm-modal-close').on('click', function() {
        researchModal.hide();
    });

    researchPromptList.on('click', 'li', function() {
        const promptTemplate = $(this).data('prompt');
        chatInput.val(promptTemplate);
        researchModal.hide();
    });

    $('#start-research-btn').on('click', function() {
        const customPrompt = customResearchPrompt.val().trim();
        if (customPrompt) {
            chatInput.val(customPrompt);
            researchModal.hide();
        }
    });

    // --- Notes Sidebar Toggle Logic ---
    $('#toggle-notes-btn').on('click', function() {
        appContainer.toggleClass('notes-open');
    });

    $('#close-notes-btn').on('click', function() {
        appContainer.removeClass('notes-open');
    });

    // --- Fullscreen Toggle Logic ---
    $('#fullscreen-toggle-btn').on('click', function() {
        appContainer.toggleClass('fullscreen-mode');
        // Add a class to body to prevent scrolling when fullscreen
        $('body').toggleClass('aiohm-fullscreen-body-no-scroll'); 
    });



    // Close notification bar when close button is clicked
    notificationBar.on('click', '.close-btn', function() {
        notificationBar.fadeOut();
    });


    // --- Initial Load ---
    loadProjects(); // Start by loading projects
});