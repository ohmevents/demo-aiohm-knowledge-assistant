/**
 * AIOHM Universal Robot Guide
 * 
 * A configurable robot guide system for all AIOHM admin pages
 */

(function($) {
    'use strict';

    // Page-specific configurations
    const PAGE_CONFIGS = {
        'aiohm_page_aiohm-brand-soul': {
            pageName: 'AI Brand Core',
            storagePrefix: 'brand_soul',
            welcomeMessage: "Welcome to your <strong>AI Brand Core</strong>!<br>This is where you define your brand's soul - the deep essence that makes your brand unique. Think of this as teaching the AI who you really are, not just what you do.",
            tasks: [
                {
                    id: 'brand_intro',
                    text: "The AI Brand Core is like a personality profile for your brand.<br>You'll answer questions about your mission, values, voice, and audience. This becomes the foundation for how your AI assistant speaks and thinks about your brand.",
                    points: 20
                },
                {
                    id: 'questionnaire_section',
                    text: "The questionnaire covers key areas:<br>• <strong>Brand Foundation:</strong> Your mission and values<br>• <strong>Brand Voice:</strong> How you communicate<br>• <strong>Target Audience:</strong> Who you serve<br>• <strong>Brand Personality:</strong> Your unique character",
                    points: 25
                },
                {
                    id: 'save_progress',
                    text: "Your answers are automatically saved as you type.<br>You can come back anytime to refine your responses. The more detailed your answers, the better your AI assistant will represent your brand.",
                    points: 20
                },
                {
                    id: 'integration',
                    text: "Once complete, your Brand Core data feeds into:<br>• <strong>Muse Mode:</strong> For authentic content creation<br>• <strong>Mirror Mode:</strong> For consistent brand voice<br>• <strong>All AI interactions:</strong> Ensuring brand alignment",
                    points: 35
                }
            ]
        },

        'aiohm_page_aiohm-scan-content': {
            pageName: 'Scan Content',
            storagePrefix: 'scan_content',
            welcomeMessage: "Welcome to <strong>Content Scanning</strong>!<br>This is your content discovery hub. Here you'll scan your website to find and select which content should feed your AI's knowledge base.",
            tasks: [
                {
                    id: 'scan_purpose',
                    text: "Content scanning finds all your posts, pages, and uploaded files.<br>The AI needs to know what content exists before it can help visitors with questions about your brand and offerings.",
                    points: 20
                },
                {
                    id: 'scan_process',
                    text: "The scanning process:<br>• <strong>Automatic Detection:</strong> Finds posts, pages, media<br>• <strong>Content Preview:</strong> Shows what will be included<br>• <strong>Selective Addition:</strong> You choose what to include<br>• <strong>Processing:</strong> Breaks content into searchable chunks",
                    points: 30
                },
                {
                    id: 'file_support',
                    text: "Supported file types include:<br>• <strong>Documents:</strong> PDF, DOC, TXT<br>• <strong>Data:</strong> CSV, JSON<br>• <strong>Web Content:</strong> Posts, pages, custom post types<br>• <strong>Media:</strong> Images with descriptions",
                    points: 25
                },
                {
                    id: 'quality_control',
                    text: "Best practices:<br>• Review content before adding to knowledge base<br>• Include only relevant, high-quality content<br>• Regular scans keep your AI's knowledge fresh<br>• Remove outdated content when needed",
                    points: 25
                }
            ]
        },

        'aiohm_page_aiohm-manage-kb': {
            pageName: 'Manage Knowledge Base',
            storagePrefix: 'manage_kb',
            welcomeMessage: "Welcome to <strong>Knowledge Base Management</strong>!<br>This is your content control center. Here you can review, edit, organize, and export all the content that powers your AI assistant.",
            tasks: [
                {
                    id: 'kb_overview',
                    text: "Your knowledge base is the brain of your AI assistant.<br>It contains all the information your AI uses to answer questions, create content, and represent your brand accurately.",
                    points: 15
                },
                {
                    id: 'content_management',
                    text: "Management features include:<br>• <strong>Content Review:</strong> See all stored content<br>• <strong>Edit Entries:</strong> Modify or improve content<br>• <strong>Delete Entries:</strong> Remove outdated information<br>• <strong>Search & Filter:</strong> Find specific content quickly",
                    points: 25
                },
                {
                    id: 'organization_tools',
                    text: "Organization capabilities:<br>• <strong>Categorization:</strong> Group related content<br>• <strong>Priority Settings:</strong> Mark important content<br>• <strong>Source Tracking:</strong> See where content came from<br>• <strong>Status Management:</strong> Track content state",
                    points: 25
                },
                {
                    id: 'export_features',
                    text: "Export your knowledge base:<br>• <strong>Full Export:</strong> Download complete knowledge base<br>• <strong>Selective Export:</strong> Choose specific content<br>• <strong>Format Options:</strong> Multiple file formats<br>• <strong>Brand Portability:</strong> Use anywhere",
                    points: 35
                }
            ]
        },

        'aiohm_page_aiohm-mirror-mode': {
            pageName: 'Mirror Mode',
            storagePrefix: 'mirror_mode',
            welcomeMessage: "Welcome to <strong>Mirror Mode</strong> settings!<br>This configures your public-facing Q&A chatbot - the AI that visitors interact with on your website to learn about your brand.",
            tasks: [
                {
                    id: 'mirror_purpose',
                    text: "Mirror Mode is your brand's public voice.<br>It uses your public content and Brand Core to answer visitor questions, provide information, and guide people through your offerings - like a knowledgeable team member.",
                    points: 20
                },
                {
                    id: 'customization_options',
                    text: "Customization features:<br>• <strong>System Prompt:</strong> Define AI personality and behavior<br>• <strong>Visual Styling:</strong> Colors, fonts, appearance<br>• <strong>Welcome Messages:</strong> First impression settings<br>• <strong>Response Tone:</strong> Formal, casual, friendly, etc.",
                    points: 30
                },
                {
                    id: 'integration_settings',
                    text: "Integration options:<br>• <strong>Website Placement:</strong> Where chatbot appears<br>• <strong>Page Restrictions:</strong> Control which pages show it<br>• <strong>User Permissions:</strong> Who can access it<br>• <strong>Rate Limiting:</strong> Prevent abuse",
                    points: 25
                },
                {
                    id: 'testing_monitoring',
                    text: "Testing and monitoring:<br>• <strong>Test Chat:</strong> Try before going live<br>• <strong>Conversation Logs:</strong> See what people ask<br>• <strong>Usage Analytics:</strong> Track performance<br>• <strong>Feedback Collection:</strong> Improve over time",
                    points: 25
                }
            ]
        },

        'aiohm_page_aiohm-muse-mode': {
            pageName: 'Muse Mode',
            storagePrefix: 'muse_mode',
            welcomeMessage: "Welcome to <strong>Muse Mode</strong> settings!<br>This configures your private brand assistant - the AI that helps authenticated users with content creation, strategy, and deep brand work.",
            tasks: [
                {
                    id: 'muse_purpose',
                    text: "Muse Mode is your creative partner.<br>It combines your Brand Core data with your knowledge base to help create content, develop strategies, and brainstorm ideas that truly sound like your brand.",
                    points: 20
                },
                {
                    id: 'brand_archetypes',
                    text: "Brand Archetype selection:<br>• <strong>12 Archetypes:</strong> Creator, Sage, Innocent, Explorer, etc.<br>• <strong>Voice Adaptation:</strong> AI speaks in your archetype's style<br>• <strong>Content Approach:</strong> Influences creative direction<br>• <strong>Audience Connection:</strong> Resonates with your tribe",
                    points: 30
                },
                {
                    id: 'creative_features',
                    text: "Creative capabilities:<br>• <strong>Content Generation:</strong> Posts, emails, captions<br>• <strong>Strategy Development:</strong> Brand planning and direction<br>• <strong>Idea Brainstorming:</strong> Campaign and project concepts<br>• <strong>Brand Consistency:</strong> Always on-brand results",
                    points: 25
                },
                {
                    id: 'privacy_access',
                    text: "Privacy and access:<br>• <strong>Member-Only:</strong> Only authenticated users can access<br>• <strong>Brand Soul Integration:</strong> Uses your private brand data<br>• <strong>Secure Environment:</strong> Protected workspace<br>• <strong>Personal Assistant:</strong> Tailored to your brand needs",
                    points: 25
                }
            ]
        },

        'aiohm_page_aiohm-mcp': {
            pageName: 'MCP API',
            storagePrefix: 'mcp_api',
            welcomeMessage: "Welcome to the <strong>MCP API</strong> settings!<br>MCP (Model Context Protocol) allows external applications to connect securely to your AIOHM knowledge base and AI capabilities.",
            tasks: [
                {
                    id: 'mcp_explanation',
                    text: "MCP is a bridge between AIOHM and other tools.<br>It allows apps like Claude Desktop, VS Code, or custom applications to access your brand's knowledge base securely through API endpoints.",
                    points: 25
                },
                {
                    id: 'token_management',
                    text: "Token management:<br>• <strong>Generate Tokens:</strong> Create secure access keys<br>• <strong>Usage Tracking:</strong> Monitor API consumption<br>• <strong>Permission Control:</strong> Limit what each token can access<br>• <strong>Revoke Access:</strong> Disable tokens when needed",
                    points: 30
                },
                {
                    id: 'integration_examples',
                    text: "Common integrations:<br>• <strong>Claude Desktop:</strong> Access brand knowledge in conversations<br>• <strong>Development Tools:</strong> Include brand context in coding<br>• <strong>Content Apps:</strong> Brand-aware content creation<br>• <strong>Custom Solutions:</strong> Build your own integrations",
                    points: 25
                },
                {
                    id: 'security_features',
                    text: "Security and monitoring:<br>• <strong>Encrypted Connections:</strong> All API calls secured<br>• <strong>Rate Limiting:</strong> Prevent overuse<br>• <strong>Access Logs:</strong> Track all API activity<br>• <strong>Private Access:</strong> Enterprise-level security",
                    points: 20
                }
            ]
        },

        'aiohm_page_aiohm-license': {
            pageName: 'License',
            storagePrefix: 'license',
            welcomeMessage: "Welcome to <strong>License Management</strong>!<br>This is where you connect your AIOHM account and manage your membership level to unlock different features and capabilities.",
            tasks: [
                {
                    id: 'membership_tiers',
                    text: "AIOHM membership tiers:<br>• <strong>Tribe (Free):</strong> AI Brand Core access<br>• <strong>Club ($1/month):</strong> Mirror & Muse modes<br>• <strong>Private ($10/month):</strong> MCP API, Ollama, maximum privacy",
                    points: 25
                },
                {
                    id: 'account_connection',
                    text: "Account connection process:<br>• <strong>AIOHM Account:</strong> Register or login at aiohm.app<br>• <strong>Email Verification:</strong> Connect your WordPress to AIOHM<br>• <strong>Membership Sync:</strong> Automatic feature unlocking<br>• <strong>Status Updates:</strong> Real-time membership changes",
                    points: 30
                },
                {
                    id: 'feature_unlocking',
                    text: "Feature activation:<br>• <strong>Automatic Detection:</strong> Features appear based on membership<br>• <strong>Instant Access:</strong> No plugin reinstallation needed<br>• <strong>Upgrade Path:</strong> Easy membership level increases<br>• <strong>Grace Period:</strong> Brief access during payment issues",
                    points: 25
                },
                {
                    id: 'support_resources',
                    text: "Support and resources:<br>• <strong>Documentation:</strong> Complete setup guides<br>• <strong>Community Access:</strong> AIOHM member community<br>• <strong>Priority Support:</strong> Based on membership level<br>• <strong>Feature Requests:</strong> Influence product development",
                    points: 20
                }
            ]
        },

        'aiohm_page_aiohm-get-help': {
            pageName: 'Get Help',
            storagePrefix: 'get_help',
            welcomeMessage: "Welcome to <strong>Get Help</strong>!<br>This is your support center with documentation, tutorials, community resources, and direct support options to help you master AIOHM.",
            tasks: [
                {
                    id: 'documentation_resources',
                    text: "Documentation sections:<br>• <strong>Quick Start Guide:</strong> Get up and running fast<br>• <strong>Feature Tutorials:</strong> Step-by-step walkthroughs<br>• <strong>Best Practices:</strong> Pro tips and strategies<br>• <strong>Troubleshooting:</strong> Common issues and solutions",
                    points: 25
                },
                {
                    id: 'community_support',
                    text: "Community resources:<br>• <strong>AIOHM Community:</strong> Connect with other users<br>• <strong>Feature Requests:</strong> Suggest improvements<br>• <strong>User Showcase:</strong> See how others use AIOHM<br>• <strong>Expert Tips:</strong> Learn from power users",
                    points: 25
                },
                {
                    id: 'direct_support',
                    text: "Direct support options:<br>• <strong>Help Tickets:</strong> Technical support system<br>• <strong>Live Chat:</strong> Real-time assistance (Club+)<br>• <strong>Screen Sharing:</strong> Direct setup help (Private)<br>• <strong>Custom Training:</strong> Personal onboarding (Enterprise)",
                    points: 25
                },
                {
                    id: 'learning_resources',
                    text: "Learning materials:<br>• <strong>Video Tutorials:</strong> Visual step-by-step guides<br>• <strong>Webinars:</strong> Live training sessions<br>• <strong>Use Case Examples:</strong> Real-world implementations<br>• <strong>Advanced Strategies:</strong> Maximize your AI potential",
                    points: 25
                }
            ]
        },

        'aiohm_page_aiohm-settings': {
            pageName: 'Settings',
            storagePrefix: 'settings',
            welcomeMessage: "Hello and welcome to Settings!<br>I'm your AIOHM guide assistant, here to walk you through all the configuration options. Each section has a specific purpose to help you get the most out of AIOHM.<br>Ready to explore?",
            tasks: [
                {
                    id: 'configuration_privacy',
                    text: "Let's start with <strong>Configuration & Privacy Settings</strong>.<br>This is where you'll set your default AI provider and manage privacy consent. Think of this as your foundation - without an AI provider configured, the other features won't work.",
                    points: 15
                },
                {
                    id: 'free_ai_services',
                    text: "Next up: <strong>Free AI Services</strong>.<br>This section shows ShareAI - a great option to test AIOHM without any upfront costs. Perfect for trying things out before committing to a paid API.",
                    points: 10
                },
                {
                    id: 'ollama_private',
                    text: "Here's the <strong>Ollama Server</strong> section.<br>This is for AIOHM Private members who want maximum privacy by running AI models on their own servers. If you need complete data control, this is your option.",
                    points: 10
                },
                {
                    id: 'premium_ai_services',
                    text: "Now we're at <strong>Premium AI Services</strong>.<br>OpenAI, Gemini, and Claude APIs are here. These are paid services but offer the most advanced AI capabilities. You'll get API keys from their respective platforms.",
                    points: 15
                },
                {
                    id: 'free_features',
                    text: "<strong>Free Features</strong> section.<br>These are tools available to everyone - no membership required. Great for basic functionality and getting started with AIOHM.",
                    points: 10
                },
                {
                    id: 'ai_usage_overview',
                    text: "The <strong>AI Usage Overview</strong> helps you monitor your API usage.<br>Keep track of costs and performance across all your AI providers. Essential for managing your AI budget effectively.",
                    points: 10
                },
                {
                    id: 'chatbot_settings',
                    text: "<strong>Q&A Chatbot Settings</strong> - your public-facing Mirror Mode.<br>This configures the chatbot that website visitors can interact with. It uses your public content to answer questions about your brand.",
                    points: 10
                },
                {
                    id: 'private_assistant',
                    text: "<strong>Private Brand Assistant</strong> - your Muse Mode for members.<br>This is where authenticated users access the deeper AI features, including your Brand Soul data for content creation and strategy.",
                    points: 10
                },
                {
                    id: 'scheduled_scan',
                    text: "<strong>Scheduled Content Scan</strong> keeps your knowledge base fresh.<br>Set this up to automatically scan and update your content regularly, so your AI always has the latest information.",
                    points: 10
                },
                {
                    id: 'data_management',
                    text: "Finally, <strong>Plugin Data Management</strong>.<br>This controls what happens to your data if you ever uninstall the plugin. Important for data retention and cleanup preferences.",
                    points: 10
                }
            ]
        }
    };

    class AIUniversalGuide {
        constructor() {
            this.robot = null;
            this.chatBubble = null;
            this.progressText = null;
            this.currentTask = 0;
            this.completedTasks = new Set();
            this.isActive = false;
            this.config = null;
            
            this.init();
        }

        init() {
            // Detect current page
            const bodyClasses = document.body.className;
            let currentPageKey = null;
            
            for (const pageKey in PAGE_CONFIGS) {
                if (bodyClasses.includes(pageKey)) {
                    currentPageKey = pageKey;
                    break;
                }
            }
            
            if (!currentPageKey || !PAGE_CONFIGS[currentPageKey]) {
                return; // Not a supported page
            }
            
            this.config = PAGE_CONFIGS[currentPageKey];
            
            // Check if robot was previously dismissed
            if (localStorage.getItem(`aiohm_${this.config.storagePrefix}_robot_dismissed`) === 'true') {
                return;
            }

            // Create robot HTML
            this.createRobotHTML();
            
            this.robot = $('#aiohm-universal-robot-guide');
            this.chatBubble = $('#universal-robot-chat-bubble');
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
                <div id="aiohm-universal-robot-guide" class="aiohm-robot-guide" style="display: none;">
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
                    
                    <div class="chat-bubble" id="universal-robot-chat-bubble">
                        <div class="chat-content">
                            <p id="universal-robot-message">Hello! Ready to explore? 🤖</p>
                        </div>
                        <div class="chat-arrow"></div>
                    </div>
                </div>
            `;
            
            $('body').append(robotHTML);
        }

        loadProgress() {
            const saved = localStorage.getItem(`aiohm_${this.config.storagePrefix}_guide_progress`);
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
            localStorage.setItem(`aiohm_${this.config.storagePrefix}_guide_progress`, JSON.stringify(data));
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
            if (this.currentTask === 0) {
                // Show welcome message first
                this.displayWelcomeMessage();
            } else if (this.currentTask <= this.config.tasks.length) {
                // Show task messages
                const taskIndex = this.currentTask - 1;
                if (taskIndex < this.config.tasks.length) {
                    const task = this.config.tasks[taskIndex];
                    if (!this.completedTasks.has(task.id)) {
                        this.displayTaskMessage(task);
                    } else {
                        this.currentTask++;
                        this.showCurrentTask();
                    }
                } else {
                    this.showCompletion();
                }
            } else {
                this.showCompletion();
            }
        }

        displayWelcomeMessage() {
            const messageElement = $('#universal-robot-message');
            
            setTimeout(() => {
                const welcomeContent = `${this.config.welcomeMessage}<br><br>
                    <button class="robot-continue-btn" data-task-id="welcome" style="
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
                    " onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">Let's Explore! ✨</button>`;
                
                messageElement.html(welcomeContent);
                this.chatBubble.addClass('show');
                this.addTypingEffect();
            }, 300);
        }

        displayTaskMessage(task) {
            const messageElement = $('#universal-robot-message');
            
            if (this.chatBubble.hasClass('show')) {
                messageElement.css('opacity', '0.3');
                
                setTimeout(() => {
                    this.updateTaskContent(task, messageElement);
                    messageElement.css('opacity', '1');
                }, 150);
            } else {
                setTimeout(() => {
                    this.updateTaskContent(task, messageElement);
                    this.chatBubble.addClass('show');
                }, 300);
            }
        }

        updateTaskContent(task, messageElement) {
            const taskContent = `${task.text}<br><br>
                <button class="robot-continue-btn" data-task-id="${task.id}" style="
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
                " onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">Continue Guide ✨</button>`;
            
            messageElement.html(taskContent);
        }

        showCompletion() {
            const messageElement = $('#universal-robot-message');
            this.chatBubble.removeClass('show');
            
            setTimeout(() => {
                const completionContent = `🎊 <strong>${this.config.pageName} Guide Complete!</strong> 🎊<br><br>
                    You now understand how to use this page effectively. Each section is designed to help you get the most out of AIOHM's capabilities.<br><br>
                    <button class="robot-continue-btn" onclick="localStorage.removeItem('aiohm_${this.config.storagePrefix}_guide_progress'); localStorage.removeItem('aiohm_${this.config.storagePrefix}_robot_dismissed'); location.reload();" style="
                        background: linear-gradient(145deg, #FFD700 0%, #FFA500 100%);
                        color: #333;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 25px;
                        font-weight: bold;
                        cursor: pointer;
                        box-shadow: 0 4px 20px rgba(255, 215, 0, 0.4);
                        transition: all 0.3s ease;
                    " onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">Start New Guide 🚀</button>`;
                
                messageElement.html(completionContent);
                this.chatBubble.addClass('show');
                
                // Add celebration effects
                this.addCelebrationEffects();
            }, 300);
        }

        addTypingEffect() {
            const eyes = this.robot.find('.robot-eye');
            eyes.css('animation', 'typing 1s ease-in-out 3, blink 4s infinite');
            
            setTimeout(() => {
                eyes.css('animation', 'blink 4s infinite');
            }, 3000);
        }

        completeTask(taskId) {
            if (taskId === 'welcome') {
                this.currentTask = 1;
                this.updateProgress();
                this.saveProgress();
                setTimeout(() => {
                    this.showCurrentTask();
                }, 300);
                return;
            }

            const task = this.config.tasks.find(t => t.id === taskId);
            if (!task || this.completedTasks.has(taskId)) return;

            this.completedTasks.add(taskId);
            this.updateProgress();
            this.saveProgress();
            
            // Show completion effect
            this.showTaskCompletionEffect();
            
            // Move to next task
            this.currentTask++;
            setTimeout(() => {
                this.showCurrentTask();
            }, 300);
        }

        showTaskCompletionEffect() {
            // Simple sparkle effect
            for (let i = 0; i < 2; i++) {
                setTimeout(() => {
                    this.addSparkleEffect();
                }, i * 100);
            }
        }

        updateProgress() {
            const totalPoints = this.config.tasks.reduce((sum, task) => sum + task.points, 0) + 10; // +10 for welcome
            const earnedPoints = Array.from(this.completedTasks)
                .map(id => this.config.tasks.find(t => t.id === id))
                .filter(Boolean)
                .reduce((sum, task) => sum + task.points, 0) + (this.currentTask > 0 ? 10 : 0); // +10 if welcome completed
            
            const percentage = Math.round((earnedPoints / totalPoints) * 100);
            this.progressText.text(percentage + '%');
        }

        hideRobot() {
            this.isActive = false;
            this.chatBubble.removeClass('show');
            this.robot.removeClass('materialized');
            
            localStorage.setItem(`aiohm_${this.config.storagePrefix}_robot_dismissed`, 'true');
            
            setTimeout(() => {
                this.robot.hide();
            }, 600);
        }

        addCelebrationEffects() {
            for (let i = 0; i < 8; i++) {
                setTimeout(() => {
                    this.addSparkleEffect();
                }, i * 200);
            }
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
        new AIUniversalGuide();
    });

})(jQuery);