/**
 * AIOHM Demo Upgrade Modal System
 * 
 * Handles upgrade prompts and demo limitations across the plugin
 */

(function($) {
    'use strict';

    // Global upgrade modal system
    window.AIOHM_Demo = {
        
        /**
         * Show upgrade modal for premium features
         */
        showUpgradeModal: function(featureName, tier = 'club') {
            const tiers = {
                'tribe': {
                    name: 'AIOHM Tribe',
                    price: 'Free',
                    url: 'https://aiohm.app/register',
                    color: '#28a745'
                },
                'club': {
                    name: 'AIOHM Club',
                    price: '€1/month',
                    url: 'https://aiohm.app/club',
                    color: '#007cba'
                },
                'private': {
                    name: 'AIOHM Private',
                    price: 'Custom pricing',
                    url: 'https://aiohm.app/private',
                    color: '#6c5ce7'
                }
            };

            const tierInfo = tiers[tier] || tiers['club'];
            
            // Remove existing modal if any
            $('#aiohm-upgrade-modal').remove();
            
            // Create modal HTML
            const modalHtml = `
                <div id="aiohm-upgrade-modal" class="aiohm-modal-overlay">
                    <div class="aiohm-modal-content">
                        <div class="aiohm-modal-header">
                            <h3>🚀 Upgrade Required</h3>
                            <button class="aiohm-modal-close" aria-label="Close">&times;</button>
                        </div>
                        <div class="aiohm-modal-body">
                            <div class="upgrade-feature-info">
                                <p><strong>"${featureName}"</strong> is available in:</p>
                                <div class="upgrade-tier" style="border-color: ${tierInfo.color}">
                                    <div class="tier-badge" style="background-color: ${tierInfo.color}">
                                        ${tierInfo.name}
                                    </div>
                                    <div class="tier-price">${tierInfo.price}</div>
                                </div>
                            </div>
                            <div class="demo-notice">
                                <p>🎭 <strong>This is a demo version</strong></p>
                                <p>You're experiencing AIOHM's interface and features, but AI responses are simulated.</p>
                            </div>
                        </div>
                        <div class="aiohm-modal-footer">
                            <a href="${tierInfo.url}" target="_blank" class="button button-primary upgrade-btn" style="background-color: ${tierInfo.color}">
                                Upgrade to ${tierInfo.name} →
                            </a>
                            <button class="button button-secondary close-demo-modal">
                                Continue Demo
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Show modal with animation
            setTimeout(() => {
                $('#aiohm-upgrade-modal').addClass('show');
            }, 10);
            
            // Bind close events
            this.bindModalEvents();
        },
        
        /**
         * Bind modal close events
         */
        bindModalEvents: function() {
            $(document).on('click', '.aiohm-modal-close, .close-demo-modal, .aiohm-modal-overlay', function(e) {
                if (e.target === this) {
                    $('#aiohm-upgrade-modal').removeClass('show');
                    setTimeout(() => {
                        $('#aiohm-upgrade-modal').remove();
                    }, 300);
                }
            });
            
            // Prevent modal content clicks from closing modal
            $(document).on('click', '.aiohm-modal-content', function(e) {
                e.stopPropagation();
            });
            
            // ESC key closes modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $('#aiohm-upgrade-modal').length) {
                    $('#aiohm-upgrade-modal').removeClass('show');
                    setTimeout(() => {
                        $('#aiohm-upgrade-modal').remove();
                    }, 300);
                }
            });
        },
        
        /**
         * Show demo banner on admin pages
         */
        showDemoBanner: function() {
            // Banner disabled - return early
            return;
        },
        
        /**
         * Add demo watermarks to chat interfaces
         */
        addChatWatermark: function(container) {
            if ($(container).find('.aiohm-demo-watermark').length > 0) return;
            
            const watermarkHtml = `
                <div class="aiohm-demo-watermark">
                    <span>🎭 DEMO MODE</span>
                </div>
            `;
            
            $(container).prepend(watermarkHtml);
        },
        
        /**
         * Initialize demo features
         */
        init: function() {
            $(document).ready(() => {
                // Show demo banner on admin pages
                if ($('body').hasClass('wp-admin')) {
                    this.showDemoBanner();
                }
                
                // Add watermarks to chat containers
                $('.aiohm-chat-container, .aiohm-private-chat-container').each(function() {
                    AIOHM_Demo.addChatWatermark(this);
                });
                
                // Intercept premium feature access
                this.interceptPremiumFeatures();
            });
        },
        
        /**
         * Intercept clicks on premium features
         */
        interceptPremiumFeatures: function() {
            // Mirror Mode access
            $(document).on('click', '[data-premium-feature="mirror-mode"]', function(e) {
                e.preventDefault();
                AIOHM_Demo.showUpgradeModal('Mirror Mode AI Responses', 'club');
            });
            
            // Muse Mode access
            $(document).on('click', '[data-premium-feature="muse-mode"]', function(e) {
                e.preventDefault();
                AIOHM_Demo.showUpgradeModal('Muse Mode Private Assistant', 'club');
            });
            
            // MCP features
            $(document).on('click', '[data-premium-feature="mcp"]', function(e) {
                e.preventDefault();
                AIOHM_Demo.showUpgradeModal('MCP API Access', 'private');
            });
            
            // Advanced settings
            $(document).on('click', '[data-premium-feature="advanced-settings"]', function(e) {
                e.preventDefault();
                AIOHM_Demo.showUpgradeModal('Advanced Configuration', 'club');
            });
            
            // Private infrastructure
            $(document).on('click', '[data-premium-feature="private-infrastructure"]', function(e) {
                e.preventDefault();
                AIOHM_Demo.showUpgradeModal('Private AI Infrastructure', 'private');
            });
        }
    };
    
    // Initialize when document is ready
    AIOHM_Demo.init();
    
})(jQuery);