/**
 * AIOHM Futuristic Robot Guide
 * 
 * Creates an interactive robot guide that materializes in the center of the page
 * and moves to the bottom-right corner with guided dialog
 */

(function($) {
    'use strict';

    class AIRobotGuide {
        constructor() {
            this.robot = null;
            this.chatBubble = null;
            this.currentStep = 0;
            this.isActive = false;
            this.messages = [
                {
                    text: "Hello! Welcome to AIOHM demo experience. 🤖",
                    duration: 3000
                },
                {
                    text: "On this page you can explore all the amazing features of AIOHM Knowledge Assistant.",
                    duration: 4000
                },
                {
                    text: "You can connect AI providers, configure Mirror Mode, enable Muse Mode, and much more!",
                    duration: 4500
                },
                {
                    text: "OK, now let's move to the Settings page. Click on this link from me and see you on the Settings page! ⚙️",
                    duration: 5000,
                    hasLink: true,
                    linkText: "Go to Settings",
                    linkUrl: ajaxurl.replace('/admin-ajax.php', '/admin.php?page=aiohm-settings')
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
            
            if (this.robot.length === 0) {
                return;
            }

            // Start the experience after a short delay
            setTimeout(() => {
                this.startExperience();
            }, 2000);

            // Bind click events
            this.bindEvents();
        }

        startExperience() {
            if (this.isActive) return;
            
            this.isActive = true;
            
            // Position robot in center initially
            this.robot.addClass('center').show();
            
            // Materialize effect
            setTimeout(() => {
                this.robot.addClass('materialized');
                
                // Start showing messages after materialization
                setTimeout(() => {
                    this.showNextMessage();
                }, 800);
                
                // Move to corner after first message
                setTimeout(() => {
                    this.moveToCorner();
                }, 4000);
                
            }, 100);
        }

        moveToCorner() {
            this.robot.removeClass('center').addClass('positioned');
        }

        showNextMessage() {
            if (this.currentStep >= this.messages.length) {
                // End of conversation - hide after a delay
                setTimeout(() => {
                    this.hideRobot();
                }, 8000);
                return;
            }

            const message = this.messages[this.currentStep];
            const messageElement = $('#robot-message');
            
            // Hide bubble first
            this.chatBubble.removeClass('show');
            
            setTimeout(() => {
                // Update message content
                let messageContent = message.text;
                
                if (message.hasLink) {
                    messageContent += ` <br><br><a href="${message.linkUrl}" class="robot-guide-link" style="
                        display: inline-block;
                        background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 8px 16px;
                        border-radius: 20px;
                        text-decoration: none;
                        font-weight: 600;
                        margin-top: 5px;
                        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                        transition: all 0.3s ease;
                    " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(102, 126, 234, 0.3)';">${message.linkText} →</a>`;
                }
                
                messageElement.html(messageContent);
                
                // Show bubble with animation
                this.chatBubble.addClass('show');
                
                // Add typing effect to robot eyes
                this.addTypingEffect();
                
                // Schedule next message
                setTimeout(() => {
                    this.currentStep++;
                    this.showNextMessage();
                }, message.duration);
                
            }, 300);
        }

        addTypingEffect() {
            const eyes = this.robot.find('.robot-eye');
            eyes.css('animation', 'typing 1s ease-in-out 3, blink 4s infinite');
            
            setTimeout(() => {
                eyes.css('animation', 'blink 4s infinite');
            }, 3000);
        }

        hideRobot() {
            this.chatBubble.removeClass('show');
            
            setTimeout(() => {
                this.robot.removeClass('materialized');
                
                setTimeout(() => {
                    this.robot.hide();
                    this.isActive = false;
                }, 600);
            }, 500);
        }

        bindEvents() {
            // Click on robot to restart conversation
            this.robot.find('.robot-container').on('click', (e) => {
                e.preventDefault();
                
                if (this.isActive && this.currentStep < this.messages.length) {
                    // Skip to next message
                    this.currentStep++;
                    this.showNextMessage();
                } else if (!this.isActive) {
                    // Restart experience
                    this.currentStep = 0;
                    this.startExperience();
                }
            });

            // Hover effects
            this.robot.find('.robot-container').on('mouseenter', () => {
                if (this.isActive) {
                    // Add sparkle effect
                    this.addSparkleEffect();
                }
            });

            // Close button functionality (if user wants to dismiss)
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isActive) {
                    this.hideRobot();
                }
            });
        }

        addSparkleEffect() {
            const container = this.robot.find('.robot-container');
            const sparkle = $('<div class="robot-sparkle" style="position: absolute; width: 4px; height: 4px; background: #00ffff; border-radius: 50%; box-shadow: 0 0 10px #00ffff; opacity: 0; animation: sparkle 1s ease-out forwards;"></div>');
            
            // Random position around robot
            const x = Math.random() * 100 - 20;
            const y = Math.random() * 100 - 20;
            
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

    // Add sparkle animation to CSS
    $('<style>').text(`
        @keyframes sparkle {
            0% { opacity: 0; transform: scale(0) rotate(0deg); }
            50% { opacity: 1; transform: scale(1) rotate(180deg); }
            100% { opacity: 0; transform: scale(0) rotate(360deg); }
        }
        
        .robot-guide-link:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4) !important;
        }
    `).appendTo('head');

    // Initialize when document is ready
    $(document).ready(() => {
        new AIRobotGuide();
    });

})(jQuery);