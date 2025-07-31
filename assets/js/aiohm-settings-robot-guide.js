/**
 * AIOHM Settings Robot Guide
 * 
 * Provides section-by-section guidance for the settings page
 */

(function($) {
    'use strict';

    class AISettingsGuide {
        constructor() {
            this.robot = null;
            this.chatBubble = null;
            this.progressText = null;
            this.currentTask = 0;
            this.completedTasks = new Set();
            this.isActive = false;
            
            // Settings Page Journey Tasks
            this.tasks = [
                {
                    id: 'settings_welcome',
                    type: 'welcome',
                    text: "Hello and welcome to Settings!<br>I'm your AIOHM guide assistant, here to walk you through all the configuration options. Each section has a specific purpose to help you get the most out of AIOHM.<br>Ready to explore?",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Continue Guide' to begin!"
                },
                {
                    id: 'configuration_privacy',
                    type: 'section',
                    text: "Let's start with <strong>Configuration & Privacy Settings</strong>.<br>This is where you'll set your default AI provider and manage privacy consent. Think of this as your foundation - without an AI provider configured, the other features won't work.",
                    points: 15,
                    action: 'click_to_continue',
                    sectionSelector: '.aiohm-card h2',
                    instruction: "Click 'Next Section' when you've reviewed this area!"
                },
                {
                    id: 'free_ai_services',
                    type: 'section',
                    text: "Next up: <strong>Free AI Services</strong>.<br>This section shows ShareAI - a great option to test AIOHM without any upfront costs. Perfect for trying things out before committing to a paid API.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'ollama_private',
                    type: 'section',
                    text: "Here's the <strong>Ollama Server</strong> section.<br>This is for AIOHM Private members who want maximum privacy by running AI models on their own servers. If you need complete data control, this is your option.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'premium_ai_services',
                    type: 'section',
                    text: "Now we're at <strong>Premium AI Services</strong>.<br>OpenAI, Gemini, and Claude APIs are here. These are paid services but offer the most advanced AI capabilities. You'll get API keys from their respective platforms.",
                    points: 15,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'free_features',
                    type: 'section',
                    text: "<strong>Free Features</strong> section.<br>These are tools available to everyone - no membership required. Great for basic functionality and getting started with AIOHM.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'ai_usage_overview',
                    type: 'section',
                    text: "The <strong>AI Usage Overview</strong> helps you monitor your API usage.<br>Keep track of costs and performance across all your AI providers. Essential for managing your AI budget effectively.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'chatbot_settings',
                    type: 'section',
                    text: "<strong>Q&A Chatbot Settings</strong> - your public-facing Mirror Mode.<br>This configures the chatbot that website visitors can interact with. It uses your public content to answer questions about your brand.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'private_assistant',
                    type: 'section',
                    text: "<strong>Private Brand Assistant</strong> - your Muse Mode for members.<br>This is where authenticated users access the deeper AI features, including your Brand Soul data for content creation and strategy.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'scheduled_scan',
                    type: 'section',
                    text: "<strong>Scheduled Content Scan</strong> keeps your knowledge base fresh.<br>Set this up to automatically scan and update your content regularly, so your AI always has the latest information.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Next Section' to continue!"
                },
                {
                    id: 'data_management',
                    type: 'section',
                    text: "Finally, <strong>Plugin Data Management</strong>.<br>This controls what happens to your data if you ever uninstall the plugin. Important for data retention and cleanup preferences.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Finish Guide' to complete!"
                },
                {
                    id: 'settings_complete',
                    type: 'completion',
                    text: "🎊 <strong>Settings Guide Complete!</strong> 🎊<br><br>You now understand all the settings sections and what each one does. Start with configuring an AI provider in the first section, then explore the features that match your needs!<br><br>Need to see this again?",
                    points: 10,
                    action: 'completion'
                }
            ];
            
            this.init();
        }

        init() {
            // Only run on settings page
            if (!$('body').hasClass('aiohm_page_aiohm-settings')) {
                return;
            }

            // Check if robot was previously dismissed
            if (localStorage.getItem('aiohm_settings_robot_dismissed') === 'true') {
                return;
            }

            // Create robot HTML if it doesn't exist
            this.createRobotHTML();
            
            this.robot = $('#aiohm-settings-robot-guide');
            this.chatBubble = $('#settings-robot-chat-bubble');
            this.progressText = this.robot.find('.robot-progress-text');
            
            if (this.robot.length === 0) {
                return;
            }

            // Load saved progress
            this.loadProgress();

            // Start the experience
            setTimeout(() => {
                this.startExperience();
            }, 2000);

            // Bind events
            this.bindEvents();
        }

        createRobotHTML() {
            const robotHTML = `
                <div id="aiohm-settings-robot-guide" class="aiohm-robot-guide" style="display: none;">
                    <div class="robot-container">
                        <div class="robot-progress-text">0%</div>
                        <div class="robot-body">
                            <div class="robot-head">
                                <div class="robot-eye left-eye"></div>
                                <div class="robot-eye right-eye"></div>
                                <div class="robot-antenna"></div>
                            </div>
                            <div class="robot-chest">
                                <div class="robot-light"></div>
                            </div>
                        </div>
                        <div class="robot-glow"></div>
                    </div>
                    
                    <div class="chat-bubble" id="settings-robot-chat-bubble">
                        <div class="chat-content">
                            <p id="settings-robot-message">Hello! Ready to explore the settings? 🤖</p>
                        </div>
                        <div class="chat-arrow"></div>
                    </div>
                </div>
            `;
            
            $('body').append(robotHTML);
        }

        loadProgress() {
            const saved = localStorage.getItem('aiohm_settings_guide_progress');
            if (saved) {
                const data = JSON.parse(saved);
                this.completedTasks = new Set(data.completed || []);
                this.currentTask = data.currentTask || 0;
                this.updateProgress();
            }
        }

        saveProgress() {
            const data = {
                completed: Array.from(this.completedTasks),
                currentTask: this.currentTask
            };
            localStorage.setItem('aiohm_settings_guide_progress', JSON.stringify(data));
        }

        startExperience() {
            if (this.isActive) return;
            
            this.isActive = true;
            this.robot.addClass('positioned').show();
            
            setTimeout(() => {
                this.robot.addClass('materialized');
                setTimeout(() => {
                    this.showCurrentTask();
                }, 800);
            }, 100);
        }

        showCurrentTask() {
            if (this.currentTask >= this.tasks.length) {
                return;
            }

            const task = this.tasks[this.currentTask];
            
            if (this.completedTasks.has(task.id)) {
                this.currentTask++;
                this.showCurrentTask();
                return;
            }

            this.displayTaskMessage(task);
        }

        displayTaskMessage(task) {
            const messageElement = $('#settings-robot-message');
            
            if (this.chatBubble.hasClass('show')) {
                messageElement.css('opacity', '0.3');
                
                setTimeout(() => {
                    this.updateMessageContent(task, messageElement);
                    messageElement.css('opacity', '1');
                }, 150);
            } else {
                setTimeout(() => {
                    this.updateMessageContent(task, messageElement);
                    this.chatBubble.addClass('show');
                    this.addTypingEffect();
                }, 300);
            }
        }

        updateMessageContent(task, messageElement) {
            let messageContent = task.text;
            
            // Add continue button
            if (task.action === 'click_to_continue') {
                const buttonText = task.type === 'section' ? 'Next Section ✨' : 
                                 task.type === 'completion' ? 'Start New Guide 🚀' : 'Continue Guide ✨';
                
                messageContent += `<br><br><button class="robot-continue-btn" data-task-id="${task.id}" style="
                    background: linear-gradient(145deg, var(--ohm-light-accent, #cbddd1) 0%, var(--ohm-primary, #457d58) 100%);
                    color: var(--ohm-dark, #272727);
                    border: none;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-weight: 600;
                    cursor: pointer;
                    box-shadow: 0 4px 15px rgba(203, 221, 209, 0.4);
                    transition: all 0.3s ease;
                    margin-top: 8px;
                " onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">${buttonText}</button>`;
            }
            
            messageElement.html(messageContent);
        }

        addTypingEffect() {
            const eyes = this.robot.find('.robot-eye');
            eyes.css('animation', 'typing 1s ease-in-out 3, blink 4s infinite');
            
            setTimeout(() => {
                eyes.css('animation', 'blink 4s infinite');
            }, 3000);
        }

        completeTask(taskId) {
            const task = this.tasks.find(t => t.id === taskId);
            if (!task || this.completedTasks.has(taskId)) return;

            this.completedTasks.add(taskId);
            this.updateProgress();
            this.saveProgress();
            
            // Show completion effect
            this.showTaskCompletionEffect(task);
            
            // Handle completion task
            if (task.type === 'completion') {
                // Reset and restart
                localStorage.removeItem('aiohm_settings_guide_progress');
                localStorage.removeItem('aiohm_settings_robot_dismissed');
                setTimeout(() => {
                    location.reload();
                }, 1000);
                return;
            }
            
            // Move to next task
            this.currentTask++;
            setTimeout(() => {
                this.showCurrentTask();
            }, 300);
        }

        showTaskCompletionEffect(task) {
            // Simple sparkle effect
            for (let i = 0; i < 2; i++) {
                setTimeout(() => {
                    this.addSparkleEffect();
                }, i * 100);
            }
            
            // Pulse the robot light
            const robotLight = this.robot.find('.robot-light');
            robotLight.css('transform', 'translate(-50%, -50%) scale(1.3)');
            setTimeout(() => {
                robotLight.css('transform', 'translate(-50%, -50%) scale(1)');
            }, 200);
        }

        updateProgress() {
            const totalPoints = this.tasks.reduce((sum, task) => sum + task.points, 0);
            const earnedPoints = Array.from(this.completedTasks)
                .map(id => this.tasks.find(t => t.id === id))
                .filter(Boolean)
                .reduce((sum, task) => sum + task.points, 0);
            
            const percentage = Math.round((earnedPoints / totalPoints) * 100);
            this.progressText.text(percentage + '%');
        }

        hideRobot() {
            this.isActive = false;
            this.chatBubble.removeClass('show');
            this.robot.removeClass('materialized');
            
            localStorage.setItem('aiohm_settings_robot_dismissed', 'true');
            
            setTimeout(() => {
                this.robot.hide();
            }, 600);
        }

        bindEvents() {
            // Click on robot to close it
            this.robot.find('.robot-container').on('click', (e) => {
                e.preventDefault();
                this.hideRobot();
            });

            // Continue button clicks
            $(document).on('click', '.robot-continue-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const taskId = $btn.data('task-id');
                
                if (taskId) {
                    this.completeTask(taskId);
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isActive) {
                    this.chatBubble.removeClass('show');
                } else if (e.key === 'Enter' && this.isActive) {
                    $('.robot-continue-btn').click();
                }
            });
        }

        addSparkleEffect() {
            const container = this.robot.find('.robot-container');
            const sparkle = $('<div class="robot-sparkle" style="position: absolute; width: 4px; height: 4px; background: var(--ohm-light-accent, #cbddd1); border-radius: 50%; box-shadow: 0 0 10px var(--ohm-primary, #457d58); opacity: 0; animation: sparkle 1s ease-out forwards;"></div>');
            
            const x = Math.random() * 120 - 20;
            const y = Math.random() * 120 - 20;
            
            sparkle.css({
                top: y + '%',
                left: x + '%'
            });
            
            container.append(sparkle);
            
            setTimeout(() => {
                sparkle.remove();
            }, 1000);
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new AISettingsGuide();
    });

})(jQuery);