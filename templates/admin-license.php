<?php
/**
 * Admin License page template - Final version with corrected logo and dynamic content.
 */

if (!defined('ABSPATH')) exit;

// --- Start: Data Fetching and Status Checks ---
$settings = AIOHM_KB_Assistant::get_settings();
$user_email = $settings['aiohm_app_email'] ?? '';
$is_user_linked = !empty($user_email);
$has_club_access = false;
$has_private_access = false;
$membership_details = null;
$display_name = null;

if ($is_user_linked && class_exists('AIOHM_KB_PMP_Integration')) {
    $has_club_access = AIOHM_KB_PMP_Integration::aiohm_user_has_club_access();
    $has_private_access = AIOHM_KB_PMP_Integration::aiohm_user_has_private_access();
    $membership_details = AIOHM_KB_PMP_Integration::get_user_membership_details();
    $display_name = AIOHM_KB_PMP_Integration::get_user_display_name();
}
// --- End: Data Fetching and Status Checks ---
?>
<div class="wrap aiohm-license-page">
    <h1><?php esc_html_e('AIOHM Membership & Features', 'aiohm-knowledge-assistant'); ?></h1>
    <p class="description"><?php esc_html_e('Connect your account to see the features available with your membership tier.', 'aiohm-knowledge-assistant'); ?></p>

    <div class="aiohm-feature-grid">

        <div class="aiohm-feature-box <?php echo esc_attr($is_user_linked ? 'plan-active' : 'plan-inactive'); ?>">
            <div class="box-icon"><?php
                echo wp_kses_post(AIOHM_KB_Core_Init::render_image(
                    AIOHM_KB_PLUGIN_URL . 'assets/images/OHM-logo.png',
                    esc_attr__('OHM Logo', 'aiohm-knowledge-assistant'),
                    ['class' => 'ohm-logo-icon']
                ));
            ?></div>
            <h3><?php esc_html_e('AIOHM Tribe', 'aiohm-knowledge-assistant'); ?></h3>
            <?php if ($is_user_linked) : ?>
                <?php if ($user_email === 'contact@ohm.events') : ?>
                    <h4 class="plan-price" style="color: #1f5014; font-family: 'Montserrat Alternates', sans-serif;"><?php esc_html_e('🚀 DEMO LICENSE ACTIVATED', 'aiohm-knowledge-assistant'); ?></h4>
                    <div class="membership-info" style="background: #cbddd1; border: 2px solid #457d58; color: #272727; padding: 20px; border-radius: 10px; margin: 10px 0; font-family: 'PT Sans', sans-serif;">
                        <p style="margin: 5px 0;"><strong style="color: #1f5014;">Status:</strong> Full Demo Access</p>
                        <p style="margin: 5px 0;"><strong style="color: #1f5014;">Email:</strong> <?php echo esc_html($user_email); ?></p>
                        <p style="margin: 5px 0;"><strong style="color: #1f5014;">Features:</strong> All OHM × AIOHM features unlocked</p>
                    </div>
                    <div class="plan-description"><p style="font-family: 'PT Sans', sans-serif;"><?php esc_html_e('🎯 Demo mode gives you full access to all AIOHM features including Tribe, Club, and Private features. Experience the complete OHM × AIOHM integration!', 'aiohm-knowledge-assistant'); ?></p></div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-dashboard')); ?>" class="button button-primary margin-top-auto" style="background: #457d58; border-color: #457d58; font-family: 'Montserrat', sans-serif;" onmouseover="this.style.background='#1f5014'" onmouseout="this.style.background='#457d58'"><?php esc_html_e('→ Explore Dashboard', 'aiohm-knowledge-assistant'); ?></a>
                <?php else : ?>
                    <h4 class="plan-price"><?php esc_html_e('Welcome to the Tribe!', 'aiohm-knowledge-assistant'); ?></h4>
                    <div class="membership-info">
                        <p><strong>Name:</strong> <?php echo esc_html($display_name ?? 'N/A'); ?></p>
                        <p><strong>Email:</strong> <?php echo esc_html($user_email); ?></p>
                    </div>
                    <div class="plan-description"><p><?php esc_html_e('As a Tribe member, you can now use the core features of the AIOHM Assistant, including the Brand Soul questionnaire and knowledge base management.', 'aiohm-knowledge-assistant'); ?></p></div>
                    <a href="https://www.aiohm.app/members/" target="_blank" class="button button-secondary margin-top-auto"><?php esc_html_e('View Your Tribe Profile', 'aiohm-knowledge-assistant'); ?></a>
                <?php endif; ?>
            <?php else: ?>
                <h4 class="plan-price"><?php esc_html_e('Free - Where brand resonance begins.', 'aiohm-knowledge-assistant'); ?></h4>
                <div class="tribe-counter-container">
                    <div class="tribe-counter-display">
                        <div class="counter-number" id="tribe-members-count">
                            <span class="loading-dots">•••</span>
                        </div>
                        <div class="counter-label">tribe members</div>
                        <div class="counter-subtext" id="tribe-stats">Loading tribe status...</div>
                    </div>
                </div>
                <div class="plan-description"><p><?php esc_html_e('Access your personal Brand Soul Map through our guided questionnaire and shape your AI with the truths that matter most to you.', 'aiohm-knowledge-assistant'); ?></p></div>
                <a href="https://www.aiohm.app/register" target="_blank" class="button button-primary margin-top-auto">→ <?php esc_html_e('Join AIOHM Tribe', 'aiohm-knowledge-assistant'); ?></a>
            <?php endif; ?>
        </div>

        <div class="aiohm-feature-box">
             <?php if ($is_user_linked) : ?>
                <div class="box-icon">🔗</div>
                <h3><?php echo esc_html($display_name ?? 'Account Connected'); ?></h3>
                <p><?php 
                    // translators: %s is the user's email address
                    printf(esc_html__('Your site is linked via the email: %s', 'aiohm-knowledge-assistant'), '<strong>' . esc_html($user_email) . '</strong>'); ?></p>
                <form method="post" action="options.php" class="aiohm-disconnect-form">
                    <?php settings_fields('aiohm_kb_settings'); ?>
                    <input type="hidden" name="aiohm_kb_settings[aiohm_app_email]" value="">
                    <?php 
                    foreach ($settings as $key => $value) { 
                        if ($key !== 'aiohm_app_email') { 
                            echo '<input type="hidden" name="aiohm_kb_settings[' . esc_attr($key) . ']" value="' . esc_attr(is_array($value) ? json_encode($value) : $value) . '">'; 
                        } 
                    } 
                    ?>
                    <button type="submit" class="button button-primary button-disconnect"><?php esc_html_e('Disconnect Account', 'aiohm-knowledge-assistant'); ?></button>
                </form>
             <?php else : ?>
                <div class="box-icon">🔌</div>
                <h3><?php esc_html_e('Connect Your Account', 'aiohm-knowledge-assistant'); ?></h3>
                <p><?php esc_html_e('Enter your AIOHM Email below to verify your membership and connect your account.', 'aiohm-knowledge-assistant'); ?></p>
                <div class="aiohm-connect-form-wrapper">
                    <div id="aiohm-verification-step-1">
                        <input type="email" id="aiohm-verification-email" placeholder="Enter Your AIOHM Email" required>
                        <button type="button" id="aiohm-send-code-btn" class="button button-secondary"><?php esc_html_e('Send Verification Code', 'aiohm-knowledge-assistant'); ?></button>
                    </div>
                    <div id="aiohm-verification-step-2">
                        <p class="aiohm-verification-message">We've sent a verification code to your email. Please enter it below:</p>
                        <input type="text" id="aiohm-verification-code" placeholder="Enter 6-digit code" maxlength="6" required>
                        <div class="aiohm-verification-actions">
                            <button type="button" id="aiohm-verify-code-btn" class="button button-primary"><?php esc_html_e('Verify & Connect', 'aiohm-knowledge-assistant'); ?></button>
                            <button type="button" id="aiohm-resend-code-btn" class="button button-link"><?php esc_html_e('Resend Code', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                    </div>
                    <div id="aiohm-verification-status" class="aiohm-status-message"></div>
                </div>
             <?php endif; ?>
        </div>

        <div class="aiohm-feature-box <?php echo esc_attr($has_club_access ? 'plan-active' : 'plan-inactive'); ?>">
            <div class="box-icon"><?php
                echo wp_kses_post(AIOHM_KB_Core_Init::render_image(
                    AIOHM_KB_PLUGIN_URL . 'assets/images/OHM-logo.png',
                    esc_attr__('AIOHM Logo', 'aiohm-knowledge-assistant'),
                    ['class' => 'ohm-logo-icon']
                ));
            ?></div>
            <h3><?php esc_html_e('AIOHM Club', 'aiohm-knowledge-assistant'); ?></h3>
            <?php if ($has_club_access && $membership_details) : ?>
                <h4 class="plan-price"><?php esc_html_e('You have unlocked Club features!', 'aiohm-knowledge-assistant'); ?></h4>
                <div class="membership-info">
                    <p><strong>Level:</strong> <?php echo esc_html($membership_details['level_name']); ?></p>
                    <p><strong>Started:</strong> <?php echo esc_html($membership_details['start_date']); ?></p>
                    <p><strong>Expires:</strong> <?php echo esc_html($membership_details['end_date']); ?></p>
                </div>
                <a href="https://www.aiohm.app/club" target="_blank" class="button button-secondary margin-top-auto"><?php esc_html_e('Manage Membership', 'aiohm-knowledge-assistant'); ?></a>
            <?php else: ?>
                <h4 class="plan-price"><?php esc_html_e('1€/month or 10€/year for first 1000 members.', 'aiohm-knowledge-assistant'); ?></h4>
                <div class="club-countdown-container">
                    <div class="club-countdown-display">
                        <div class="countdown-number" id="remaining-spots">
                            <span class="loading-dots">•••</span>
                        </div>
                        <div class="countdown-label">spots remaining</div>
                        <div class="countdown-progress">
                            <div class="progress-bar" id="progress-bar"></div>
                        </div>
                        <div class="countdown-subtext" id="club-stats">Loading club status...</div>
                    </div>
                </div>
                <div class="plan-description"><p>Club members gain exclusive access to Mirror Mode for Q&A chat-bot and Muse Mode for brand idea-rich, emotionally attuned content.</p></div>
                <a href="https://www.aiohm.app/club/" target="_blank" class="button button-primary margin-top-auto">→ <?php esc_html_e('Join AIOHM Club', 'aiohm-knowledge-assistant'); ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($has_private_access && $membership_details) : ?>
    <!-- Private Members Wide Box -->
    <div class="aiohm-private-wide-box support-card">
        <div class="private-box-header">
            <div class="private-box-icon"><?php
                echo wp_kses_post(AIOHM_KB_Core_Init::render_image(
                    AIOHM_KB_PLUGIN_URL . 'assets/images/OHM-logo.png',
                    esc_attr__('AIOHM Private Logo', 'aiohm-knowledge-assistant'),
                    ['class' => 'ohm-logo-private']
                ));
            ?></div>
            <div class="private-box-title">
                <h3><?php esc_html_e('AIOHM Private Members - Connected', 'aiohm-knowledge-assistant'); ?></h3>
                <p class="private-welcome"><?php 
                // translators: %s is the user's display name
                printf(esc_html__('Welcome %s, you have exclusive access to premium private infrastructure.', 'aiohm-knowledge-assistant'), esc_html($display_name)); ?></p>
            </div>
        </div>
        
        <div class="private-features-grid">
            <div class="private-feature-item">
                <div class="feature-icon">🛠️</div>
                <h4><?php esc_html_e('Ollama Integration', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('Full access to self-hosted Ollama models for complete privacy and control over your AI processing.', 'aiohm-knowledge-assistant'); ?></p>
            </div>
            
            <div class="private-feature-item">
                <div class="feature-icon">🖥️</div>
                <h4><?php esc_html_e('Private Server Access', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('Connect to dedicated private AI servers and custom LLM endpoints with enterprise-grade security.', 'aiohm-knowledge-assistant'); ?></p>
            </div>
            
            <div class="private-feature-item">
                <div class="feature-icon">⚡</div>
                <h4><?php esc_html_e('Advanced Model Selection', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('Access to premium AI models and exclusive private infrastructure not available to public users.', 'aiohm-knowledge-assistant'); ?></p>
            </div>
            
            <div class="private-feature-item">
                <div class="feature-icon">🔐</div>
                <h4><?php esc_html_e('Data Sovereignty', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('Complete control over your AI processing with private server deployment and zero data sharing.', 'aiohm-knowledge-assistant'); ?></p>
            </div>
            
            <div class="private-feature-item">
                <div class="feature-icon">👨‍💼</div>
                <h4><?php esc_html_e('White-Glove Support', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('Direct access to AIOHM team for personalized setup, optimization, and priority technical support.', 'aiohm-knowledge-assistant'); ?></p>
            </div>
            
            <div class="private-feature-item">
                <div class="feature-icon">📊</div>
                <h4><?php esc_html_e('Usage Analytics', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('Detailed analytics and monitoring of your private AI infrastructure performance and usage patterns.', 'aiohm-knowledge-assistant'); ?></p>
            </div>
            
            <div class="private-feature-item">
                <div class="feature-icon">🚀</div>
                <h4><?php esc_html_e('Early Access to New Features', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('Be the first to test and access cutting-edge AI features before they\'re released to other membership tiers.', 'aiohm-knowledge-assistant'); ?></p>
            </div>
            
            <div class="private-feature-item">
                <div class="feature-icon">✨</div>
                <h4><?php esc_html_e('More Soon', 'aiohm-knowledge-assistant'); ?></h4>
                <p><?php esc_html_e('We\'re constantly developing new exclusive features and capabilities for our private members. Stay tuned for exciting updates!', 'aiohm-knowledge-assistant'); ?></p>
            </div>
        </div>
        
        <div class="private-box-footer">
            <div class="membership-status">
                <p><strong><?php esc_html_e('Membership Level:', 'aiohm-knowledge-assistant'); ?></strong> <?php echo esc_html($membership_details['level_name']); ?></p>
                <p><strong><?php esc_html_e('Valid Until:', 'aiohm-knowledge-assistant'); ?></strong> <?php echo esc_html($membership_details['end_date']); ?></p>
            </div>
            <div class="private-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-settings')); ?>" class="button button-primary button-large"><?php esc_html_e('Configure Private Infrastructure', 'aiohm-knowledge-assistant'); ?></a>
                <a href="https://www.aiohm.app/contact" target="_blank" class="button button-secondary button-large"><?php esc_html_e('Contact Private Support', 'aiohm-knowledge-assistant'); ?></a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

