/**
 * AIOHM Wise Mage Robot Guide
 * 
 * Creates a persistent OHM-branded robot guide that leads users through
 * a gamified journey across all dashboard tabs with points (0-100%)
 */

(function($) {
    'use strict';

    class AIWiseMageGuide {
        constructor() {
            this.robot = null;
            this.chatBubble = null;
            this.progressBar = null;
            this.progressText = null;
            this.currentTask = 0;
            this.completedTasks = new Set();
            this.isActive = false;
            
            // User Journey Tasks with points
            this.tasks = [
                {
                    id: 'welcome',
                    type: 'welcome',
                    text: "Greetings, fellow seeker of knowledge! 🧙‍♂️ I am your OHM Wise Guide. Together we'll explore every corner of AIOHM's power!",
                    points: 5,
                    action: 'click_to_continue',
                    instruction: "Click 'Continue Journey' to begin your adventure!"
                },
                {
                    id: 'dashboard_overview',
                    type: 'welcome_tab',
                    text: "Here in the Welcome tab, you can see your journey begins. This dashboard shows all AIOHM features at a glance! Notice the 4 steps to transform your site into a living knowledge base.",
                    points: 10,
                    tabSelector: '.nav-tab[href*="tab=welcome"]',
                    action: 'click_to_continue',
                    instruction: "Click 'Continue Journey' when you've reviewed the 4 steps!"
                },
                {
                    id: 'tribe_tab_guide',
                    type: 'tribe_tab_guide',
                    text: "Now let's explore the Tribe tab! Click on the 'AIOHM Tribe' tab above to discover community features and Brand Core access.",
                    points: 15,
                    tabSelector: '.nav-tab[href*="tab=tribe"]',
                    action: 'click_tab',
                    instruction: "Click the 'AIOHM Tribe' tab to continue!"
                },
                {
                    id: 'tribe_tab_explored',
                    type: 'tribe_tab_explored',
                    text: "Excellent! You've discovered the Tribe features. Here you can access the AI Brand Core questionnaire and connect with the community.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Continue Journey' to explore the next area!"
                },
                {
                    id: 'club_tab_guide',
                    type: 'club_tab_guide',
                    text: "Time to explore the Club! Click on the 'AIOHM Club' tab to see Mirror Mode and Muse Mode features.",
                    points: 15,
                    tabSelector: '.nav-tab[href*="tab=club"]',
                    action: 'click_tab',
                    instruction: "Click the 'AIOHM Club' tab to continue!"
                },
                {
                    id: 'club_tab_explored',
                    type: 'club_tab_explored',
                    text: "Perfect! The Club offers Mirror Mode for reflection and Muse Mode for content creation. Both powered by your Brand Soul!",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Continue Journey' to explore the final tab!"
                },
                {
                    id: 'private_tab_guide',
                    type: 'private_tab_guide',
                    text: "Finally, let's explore Private! Click on the 'AIOHM Private' tab to discover enterprise-level privacy features.",
                    points: 15,
                    tabSelector: '.nav-tab[href*="tab=private"]',
                    action: 'click_tab',
                    instruction: "Click the 'AIOHM Private' tab to continue!"
                },
                {
                    id: 'private_tab_explored',
                    type: 'private_tab_explored',
                    text: "Amazing! Private offers maximum data control, personalized LLM connections, and MCP API access for enterprise needs.",
                    points: 10,
                    action: 'click_to_continue',
                    instruction: "Click 'Continue Journey' to complete your dashboard tour!"
                },
                {
                    id: 'settings_journey',
                    type: 'navigate',
                    text: "Perfect! You've mastered the dashboard! Now let's venture to Settings where you can configure your AI providers and unlock powerful features!",
                    points: 10,
                    hasLink: true,
                    linkText: "Journey to Settings ⚙️",
                    linkUrl: ajaxurl.replace('/admin-ajax.php', '/admin.php?page=aiohm-settings'),
                    action: 'navigate',
                    instruction: "Click the 'Journey to Settings' button to continue!"
                }
            ];
            
            this.init();
        }

        init() {
            // Only run on dashboard page
            if (!$('body').hasClass('toplevel_page_aiohm-dashboard')) {
                return;
            }

            this.robot = $('#aiohm-robot-guide');
            this.chatBubble = $('#robot-chat-bubble');
            this.progressBar = this.robot.find('.robot-progress-bar');
            this.progressText = this.robot.find('.robot-progress-text');
            
            if (this.robot.length === 0) {
                return;
            }

            // Load saved progress from localStorage
            this.loadProgress();

            // Start the experience after a short delay
            setTimeout(() => {
                this.startExperience();
            }, 2000);

            // Bind events
            this.bindEvents();
        }

        loadProgress() {
            const saved = localStorage.getItem('aiohm_guide_progress');
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
            localStorage.setItem('aiohm_guide_progress', JSON.stringify(data));
        }

        startExperience() {
            if (this.isActive) return;
            
            this.isActive = true;
            
            // Position robot in center initially for first time, corner for returning users
            if (this.completedTasks.size === 0) {
                this.robot.addClass('center').show();
            } else {
                this.robot.addClass('positioned').show();
            }
            
            // Materialize effect
            setTimeout(() => {
                this.robot.addClass('materialized');
                
                // Start showing current task
                setTimeout(() => {
                    this.showCurrentTask();
                }, 800);
                
                // Move to corner if starting in center
                if (this.completedTasks.size === 0) {
                    setTimeout(() => {
                        this.moveToCorner();
                    }, 4000);
                }
                
            }, 100);
        }

        moveToCorner() {
            this.robot.removeClass('center').addClass('positioned');
        }

        showCurrentTask() {
            if (this.currentTask >= this.tasks.length) {
                this.showCompletion();
                return;
            }

            const task = this.tasks[this.currentTask];
            
            // Skip completed tasks
            if (this.completedTasks.has(task.id)) {
                this.currentTask++;
                this.showCurrentTask();
                return;
            }

            this.displayTaskMessage(task);
            this.highlightTargetElement(task);
        }

        displayTaskMessage(task) {
            const messageElement = $('#robot-message');
            
            // Hide bubble first
            this.chatBubble.removeClass('show');
            
            setTimeout(() => {
                let messageContent = task.text;
                
                // Add instruction if provided
                if (task.instruction) {
                    messageContent += `<br><br><small style="color: var(--ohm-muted-accent, #7d9b76); font-style: italic;">💡 ${task.instruction}</small>`;
                }
                
                if (task.hasLink) {
                    messageContent += ` <br><br><a href="${task.linkUrl}" class="robot-guide-link" style="
                        display: inline-block;
                        background: linear-gradient(145deg, var(--ohm-primary, #457d58) 0%, var(--ohm-dark-accent, #1f5014) 100%);
                        color: white;
                        padding: 8px 16px;
                        border-radius: 20px;
                        text-decoration: none;
                        font-weight: 600;
                        margin-top: 5px;
                        box-shadow: 0 4px 15px rgba(69, 125, 88, 0.4);
                        transition: all 0.3s ease;
                    " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(69, 125, 88, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(69, 125, 88, 0.4)';">${task.linkText} →</a>`;
                }
                
                // Add click to continue button for click_to_continue tasks
                if (task.action === 'click_to_continue') {
                    messageContent += ` <br><br><button class="robot-continue-btn" data-task-id="${task.id}" style="
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
                    " onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">Continue Journey ✨</button>`;
                }
                
                // Add waiting message for click_tab tasks
                if (task.action === 'click_tab') {
                    messageContent += `<br><br><div style="
                        padding: 8px 12px;
                        background: rgba(69, 125, 88, 0.1);
                        border-left: 3px solid var(--ohm-primary, #457d58);
                        border-radius: 4px;
                        font-size: 13px;
                        margin-top: 10px;
                    ">⏳ Waiting for you to click the tab above...</div>`;
                }
                
                messageElement.html(messageContent);
                this.chatBubble.addClass('show');
                this.addTypingEffect();
                
            }, 300);
        }

        highlightTargetElement(task) {
            // Remove existing indicators
            $('.tab-bounce-indicator').remove();
            
            if (task.tabSelector) {
                const $tab = $(task.tabSelector);
                if ($tab.length > 0) {
                    // Add bounce indicator to tab
                    $tab.css('position', 'relative');
                    const indicator = $('<div class="tab-bounce-indicator"></div>');
                    $tab.append(indicator);
                }
            }
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
            
            // Move to next task
            this.currentTask++;
            setTimeout(() => {
                this.showCurrentTask();
            }, 2000);
        }

        showTaskCompletionEffect(task) {
            // Add multiple sparkle effects
            for (let i = 0; i < 5; i++) {
                setTimeout(() => {
                    this.addSparkleEffect();
                }, i * 100);
            }
            
            // Celebration effect on robot light
            this.robot.find('.robot-light').css('animation', 'celebration 0.8s ease-out, heartbeat 2s infinite 0.8s');
            
            // Brief glow effect
            this.robot.addClass('task-completed');
            setTimeout(() => {
                this.robot.removeClass('task-completed');
            }, 800);
        }

        updateProgress() {
            const totalPoints = this.tasks.reduce((sum, task) => sum + task.points, 0);
            const earnedPoints = Array.from(this.completedTasks)
                .map(id => this.tasks.find(t => t.id === id))
                .filter(Boolean)
                .reduce((sum, task) => sum + task.points, 0);
            
            const percentage = Math.round((earnedPoints / totalPoints) * 100);
            
            this.progressBar.css('width', percentage + '%');
            this.progressText.text(percentage + '%');
            
            if (percentage === 100) {
                this.showCompletion();
            }
        }

        showCompletion() {
            const messageElement = $('#robot-message');
            this.chatBubble.removeClass('show');
            
            setTimeout(() => {
                messageElement.html(`
                    🎊 <strong>Congratulations, Master Explorer!</strong> 🎊<br><br>
                    You've completed the AIOHM journey with 100% mastery!<br>
                    You are now ready to harness the full power of AI knowledge assistance!<br><br>
                    <button class="robot-continue-btn" onclick="localStorage.removeItem('aiohm_guide_progress'); location.reload();" style="
                        background: linear-gradient(145deg, #FFD700 0%, #FFA500 100%);
                        color: #333;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 25px;
                        font-weight: bold;
                        cursor: pointer;
                        box-shadow: 0 4px 20px rgba(255, 215, 0, 0.4);
                        transition: all 0.3s ease;
                    " onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)';">Start New Journey 🚀</button>
                `);
                this.chatBubble.addClass('show');
                
                // Add celebration effects
                this.addCelebrationEffects();
                
            }, 300);
        }

        addCelebrationEffects() {
            for (let i = 0; i < 10; i++) {
                setTimeout(() => {
                    this.addSparkleEffect();
                }, i * 200);
            }
        }

        bindEvents() {
            // Click on robot to show current task
            this.robot.find('.robot-container').on('click', (e) => {
                e.preventDefault();
                if (this.isActive) {
                    this.showCurrentTask();
                }
            });

            // Continue button clicks
            $(document).on('click', '.robot-continue-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const taskId = $btn.data('task-id');
                
                // Use the task ID from the button if available, otherwise use current task
                if (taskId) {
                    this.completeTask(taskId);
                } else {
                    const currentTask = this.tasks[this.currentTask];
                    if (currentTask && currentTask.action === 'click_to_continue') {
                        this.completeTask(currentTask.id);
                    }
                }
            });

            // Tab click monitoring
            $(document).on('click', '.nav-tab', (e) => {
                const $tab = $(e.currentTarget);
                const href = $tab.attr('href');
                
                // Check if this tab click completes a task
                const currentTask = this.tasks[this.currentTask];
                if (currentTask && currentTask.action === 'click_tab' && currentTask.tabSelector) {
                    if ($tab.is(currentTask.tabSelector)) {
                        setTimeout(() => {
                            this.completeTask(currentTask.id);
                        }, 500);
                    }
                }
                
                // Also check for specific tab types
                if (currentTask && currentTask.action === 'click_tab') {
                    if (href.includes('tab=tribe') && currentTask.type === 'tribe_tab_guide') {
                        setTimeout(() => {
                            this.completeTask(currentTask.id);
                        }, 500);
                    } else if (href.includes('tab=club') && currentTask.type === 'club_tab_guide') {
                        setTimeout(() => {
                            this.completeTask(currentTask.id);
                        }, 500);
                    } else if (href.includes('tab=private') && currentTask.type === 'private_tab_guide') {
                        setTimeout(() => {
                            this.completeTask(currentTask.id);
                        }, 500);
                    }
                }
            });

            // Hover effects for sparkles
            this.robot.find('.robot-container').on('mouseenter', () => {
                if (this.isActive) {
                    this.addSparkleEffect();
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
            
            // Random position around robot
            const x = Math.random() * 120 - 20;
            const y = Math.random() * 120 - 20;
            
            sparkle.css({
                top: y + '%',
                left: x + '%'
            });
            
            container.append(sparkle);
            
            // Remove sparkle after animation
            setTimeout(() => {
                sparkle.remove();
            }, 1000);
        }
    }

    // Add sparkle animation and other styles to CSS
    $('<style>').text(`
        @keyframes sparkle {
            0% { opacity: 0; transform: scale(0) rotate(0deg); }
            50% { opacity: 1; transform: scale(1) rotate(180deg); }
            100% { opacity: 0; transform: scale(0) rotate(360deg); }
        }
        
        .robot-guide-link:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(69, 125, 88, 0.5) !important;
        }
        
        .robot-continue-btn:hover {
            transform: translateY(-2px) !important;
        }
        
        /* Celebration confetti effect */
        .celebration-confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: var(--ohm-primary, #457d58);
            animation: confetti 3s linear infinite;
            z-index: 10001;
        }
        
        @keyframes confetti {
            0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        
        @keyframes celebration {
            0% { 
                background: radial-gradient(circle, #00ffff 0%, #0088cc 100%); 
                transform: scale(1); 
                box-shadow: 0 0 15px #00ffff, inset 0 0 8px rgba(255, 255, 255, 0.6);
            }
            25% { 
                background: radial-gradient(circle, #ffd700 0%, #ffb700 100%); 
                transform: scale(1.3); 
                box-shadow: 0 0 25px #ffd700, inset 0 0 12px rgba(255, 255, 255, 0.8);
            }
            50% { 
                background: radial-gradient(circle, #00ff88 0%, #00cc66 100%); 
                transform: scale(1.1); 
                box-shadow: 0 0 20px #00ff88, inset 0 0 10px rgba(255, 255, 255, 0.7);
            }
            75% { 
                background: radial-gradient(circle, #ff6b6b 0%, #ff4757 100%); 
                transform: scale(1.2); 
                box-shadow: 0 0 22px #ff6b6b, inset 0 0 11px rgba(255, 255, 255, 0.75);
            }
            100% { 
                background: radial-gradient(circle, #00ffff 0%, #0088cc 100%); 
                transform: scale(1); 
                box-shadow: 0 0 15px #00ffff, inset 0 0 8px rgba(255, 255, 255, 0.6);
            }
        }
        
        .aiohm-robot-guide.task-completed .robot-container {
            animation: celebrate 0.6s ease-out;
        }
        
        @keyframes celebrate {
            0% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.1) rotate(-5deg); }
            50% { transform: scale(1.15) rotate(5deg); }
            75% { transform: scale(1.05) rotate(-2deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
    `).appendTo('head');

    // Initialize when document is ready
    $(document).ready(() => {
        new AIWiseMageGuide();
    });

})(jQuery);