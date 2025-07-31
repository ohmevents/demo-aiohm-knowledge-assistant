<?php
/**
 * Admin Dashboard template.
 * Final version with a redesigned Welcome tab that matches the Tribe tab's box style,
 * a new header, dynamic content for the Tribe tab, and removal of redundant text.
 */
if (!defined('ABSPATH')) exit;

// --- Data Fetching and Status Checks ---
$default_tab = 'welcome';
$current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : $default_tab; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switching doesn't require nonce verification

// Check if user is connected by seeing if their AIOHM email is saved
$settings = AIOHM_KB_Assistant::get_settings();
$is_tribe_member_connected = !empty($settings['aiohm_app_email']);

// Force demo access for contact@ohm.events
if ($settings['aiohm_app_email'] === 'contact@ohm.events') {
    $is_tribe_member_connected = true;
}

// Check Club access using the PMPro helper function
$has_club_access = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_club_access();

// Check Private access (membership ID 12)
$has_private_access = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_private_access();
?>

<div class="wrap aiohm-dashboard">

    <div class="aiohm-header" style="text-align: left;">
        <h1 style="text-align: left;"><?php esc_html_e('AIOHM Assistant Dashboard', 'aiohm-knowledge-assistant'); ?></h1>
        <p class="aiohm-tagline" style="margin-left: auto; margin-right: auto;"><?php esc_html_e("Welcome! Let's turn your content into an expert AI assistant.", 'aiohm-knowledge-assistant'); ?></p>
    </div>


    <nav class="nav-tab-wrapper">
        <a href="?page=aiohm-dashboard&tab=welcome" class="nav-tab <?php echo esc_attr($current_tab == 'welcome' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('Welcome', 'aiohm-knowledge-assistant'); ?></a>
        <a href="?page=aiohm-dashboard&tab=tribe" class="nav-tab <?php echo esc_attr($current_tab == 'tribe' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('AIOHM Tribe', 'aiohm-knowledge-assistant'); ?></a>
        <a href="?page=aiohm-dashboard&tab=club" class="nav-tab <?php echo esc_attr($current_tab == 'club' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('AIOHM Club', 'aiohm-knowledge-assistant'); ?></a>
        <a href="?page=aiohm-dashboard&tab=private" class="nav-tab <?php echo esc_attr($current_tab == 'private' ? 'nav-tab-active' : ''); ?>"><?php esc_html_e('AIOHM Private', 'aiohm-knowledge-assistant'); ?></a>
    </nav>

    <div class="aiohm-tab-content">

        <?php if ($current_tab === 'welcome'): ?>
            <section class="aiohm-sales-page aiohm-welcome-tab">
                <div class="container">
                    <h2 class="headline"><?php esc_html_e('4 Steps to Turn Your Site Into a Living Knowledge Base', 'aiohm-knowledge-assistant'); ?></h2>
                    <div class="benefits-grid">
                        <div class="benefit">
                            <h3><?php esc_html_e('1. Root Your Presence', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Connect your preferred AI provider. This is where your structure meets spirit. Add your API key from OpenAI, Claude, or Gemini to activate the intelligence behind your knowledge base.', 'aiohm-knowledge-assistant'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-settings')); ?>" class="button button-primary"><?php esc_html_e('Open Settings', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                        <div class="benefit">
                            <h3><?php esc_html_e('2. Feed the Flame', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Choose which content carries your essence. Curate pages, posts, and files that truly represent your mission. Not just information—transmission.', 'aiohm-knowledge-assistant'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-scan-content')); ?>" class="button button-primary"><?php esc_html_e('Scan Content', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                        <div class="benefit">
                            <h3><?php esc_html_e('3. Clear the Channel', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Refine your knowledge base for resonance. Review, edit, and release what no longer aligns. Shape your AI\'s voice like a sacred text.', 'aiohm-knowledge-assistant'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-manage-kb')); ?>" class="button button-primary"><?php esc_html_e('Manage Knowledge', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                        <div class="benefit">
                            <h3><?php esc_html_e('4. Set Your Wisdom Free', 'aiohm-knowledge-assistant'); ?></h3>
                            <p><?php esc_html_e('Download your curated knowledge base and use it anywhere. Your brand\'s soul - structured and portable for any platform that honors your voice.', 'aiohm-knowledge-assistant'); ?></p>
                             <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-manage-kb')); ?>" class="button button-primary"><?php esc_html_e('Export Your KB', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($current_tab === 'tribe'): ?>
            <?php if ($is_tribe_member_connected): ?>
                <section class="aiohm-sales-page aiohm-tribe-connected">
                  <div class="container">
                    <h1 class="headline"><?php esc_html_e('Welcome to the Tribe', 'aiohm-knowledge-assistant'); ?></h1>
                    <p class="intro"><?php 
                        // translators: %s is the user's email address
                        printf(esc_html__('Your account is connected via %s.', 'aiohm-knowledge-assistant'), '<strong>' . esc_html($settings['aiohm_app_email']) . '</strong>'); ?></p>
                    <div class="benefits-grid">
                      <div class="benefit">
                        <h3><?php esc_html_e('Your Next Step: The AI Brand Core', 'aiohm-knowledge-assistant'); ?></h3>
                        <p><?php esc_html_e('You now have access to the AI Brand Core questionnaire. This is where you define the heart of your brand, so your AI can learn to speak with your authentic voice. It\'s the most crucial step in creating an assistant that truly represents you.', 'aiohm-knowledge-assistant'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-brand-soul')); ?>" class="button button-primary"><?php esc_html_e('Go to my AI Brand Core', 'aiohm-knowledge-assistant'); ?></a>
                      </div>
                       <div class="benefit">
                        <h3><?php esc_html_e('Manage Your Profile', 'aiohm-knowledge-assistant'); ?></h3>
                        <p><?php esc_html_e('You can manage your AIOHM Tribe account, view your Brand Soul map, and explore other member resources directly on the AIOHM app website.', 'aiohm-knowledge-assistant'); ?></p>
                        <a href="https://www.aiohm.app/members/" target="_blank" class="button button-secondary"><?php esc_html_e('View My AIOHM Account', 'aiohm-knowledge-assistant'); ?></a>
                      </div>
                    </div>
                  </div>
                </section>
            <?php else: ?>
                <section class="aiohm-sales-page aiohm-tribe-sales">
                  <div class="container">
                    <div class="aiohm-settings-locked-overlay is-active">
                        <div class="lock-content">
                            <div class="lock-icon">🔒</div>
                            <h2><?php esc_html_e('Unlock Tribe Features', 'aiohm-knowledge-assistant'); ?></h2>
                            <p><?php esc_html_e('To access the AI Brand Core questionnaire, please connect your free AIOHM Tribe account.', 'aiohm-knowledge-assistant'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-license')); ?>" class="button button-primary"><?php esc_html_e('Connect Your Account', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>
                    <h1 class="headline" style="filter: blur(4px);"><?php esc_html_e('Join the AIOHM Tribe', 'aiohm-knowledge-assistant'); ?></h1>
                    <p class="intro" style="filter: blur(4px);"><?php esc_html_e('A sacred starting point for soulful entrepreneurs and creators. The Tribe is your free invitation to explore the deeper layers of brand resonance and personal AI alignment.', 'aiohm-knowledge-assistant'); ?></p>
                    <div class="benefits-grid" style="filter: blur(4px);">
                      <div class="benefit">
                          <h3><?php esc_html_e('Access the AI Brand Core', 'aiohm-knowledge-assistant'); ?></h3>
                          <p><?php esc_html_e('Join for free to unlock the AI Brand Core questionnaire. This is the foundation for teaching the AI your unique voice, mission, and brand essence.', 'aiohm-knowledge-assistant'); ?></p>
                      </div>
                      <div class="benefit">
                          <h3><?php esc_html_e('Knowledge Base Management', 'aiohm-knowledge-assistant'); ?></h3>
                          <p><?php esc_html_e('Upload, organize, and edit what your AI assistant learns. Teach it your content, your story, your sacred material.', 'aiohm-knowledge-assistant'); ?></p>
                      </div>
                    </div>
                  </div>
                </section>
            <?php endif; ?>

        <?php elseif ($current_tab === 'club'): ?>
            <section class="aiohm-sales-page aiohm-club-sales">
              <div class="container">
                <h1 class="headline">AIOHM Club</h1>
                <p class="intro">Designed for creators ready to bring depth and ease into their message. AIOHM Club gives you access to tools that think like you—so your voice leads the way.</p>
                <?php if (!$has_club_access) : // Lock content if no club access ?>
                    <div class="aiohm-settings-locked-overlay is-active">
                        <div class="lock-content">
                            <div class="lock-icon">🔒</div>
                            <h2><?php esc_html_e('Unlock Club Features', 'aiohm-knowledge-assistant'); ?></h2>
                            <p><?php esc_html_e('💫 Special Founder\'s Offer: Be one of the first 1,000 Club Founders and enjoy full access for just €1/month (or €10/year)! After this early phase, membership will be €10/month — so this is your moment to lock it in.', 'aiohm-knowledge-assistant'); ?></p>
                            <p><strong><?php esc_html_e('Your voice. Your AI. Your Club.', 'aiohm-knowledge-assistant'); ?></strong></p>
                            <a href="https://www.aiohm.app/club" target="_blank" class="button button-primary"><?php esc_html_e('Join AIOHM Club', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="benefits-grid <?php echo esc_attr(!$has_club_access ? 'is-locked' : ''); ?>">
                  <div class="benefit"><h3>✨ Mirror Mode (Q&A Chatbot)</h3><p>A sacred space to reflect on your brand. Ask questions. Hear your truth echoed back through the Mirror—powered by your Brand Soul and knowledge base.</p></div>
                  <div class="benefit"><h3>🎨 Muse Mode (Brand Assistant)</h3><p>Create content that feels like you wrote it on your best day. Muse Mode understands your tone, your offers, your audience—and helps shape captions, emails, and ideas.</p></div>
                </div>
              </div>
            </section>

        <?php elseif ($current_tab === 'private'): ?>
            <section class="aiohm-sales-page aiohm-private-sales">
              <div class="container">
                <h1 class="headline">AIOHM Private</h1>
                <p class="intro">A private channel for your most sacred work. Built for creators, guides, and visionaries who need more than general AI tools—they need intimacy, integrity, and invisible support.</p>
                <?php if (!$has_private_access) : ?>
                    <div class="aiohm-settings-locked-overlay is-active">
                        <div class="lock-content">
                            <div class="lock-icon">🔒</div>
                            <h2><?php esc_html_e('Unlock Private Features', 'aiohm-knowledge-assistant'); ?></h2>
                            <p><?php esc_html_e('Private features are available with an AIOHM Private membership (ID 12).', 'aiohm-knowledge-assistant'); ?></p>
                            <div class="unlock-features-list">
                                <h4><?php esc_html_e('Features you\'ll unlock:', 'aiohm-knowledge-assistant'); ?></h4>
                                <ul>
                                    <li>🔌 <?php esc_html_e('MCP Feature - Model Context Protocol API access', 'aiohm-knowledge-assistant'); ?></li>
                                    <li>🖥️ <?php esc_html_e('Unlock AIOHM Private Features', 'aiohm-knowledge-assistant'); ?></li>
                                    <li>🔐 <?php esc_html_e('Full Privacy & Confidentiality', 'aiohm-knowledge-assistant'); ?></li>
                                    <li>🧠 <?php esc_html_e('Personalized LLM Connection', 'aiohm-knowledge-assistant'); ?></li>
                                </ul>
                            </div>
                            <a href="https://www.aiohm.app/private" target="_blank" class="button button-primary"><?php esc_html_e('Explore Private', 'aiohm-knowledge-assistant'); ?></a>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="benefits-grid <?php echo esc_attr(!$has_private_access ? 'is-locked' : ''); ?>">
                  <div class="benefit">
                    <h3>🔐 Full Privacy & Confidentiality</h3>
                    <p>Your content never leaves your WordPress site. All AI responses are generated within your protected space.</p>
                  </div>
                  <div class="benefit">
                    <h3>🧠 Personalized LLM Connection</h3>
                    <p>Connect to a private model endpoint so your AI assistant learns only from your truth, not the internet.</p>
                  </div>
                  <div class="benefit">
                    <h3>🔌 MCP API Access</h3>
                    <p>Enable Model Context Protocol endpoints for external integrations. Connect your knowledge base to compatible tools and applications securely.</p>
                    <?php if ($has_private_access): ?>
                      <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-mcp')); ?>" class="button button-primary"><?php esc_html_e('Configure MCP API', 'aiohm-knowledge-assistant'); ?></a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </section>
        <?php endif; ?>
    </div>
    
    <!-- AIOHM Robot Guide -->
    <div id="aiohm-robot-guide" class="aiohm-robot-guide" style="display: none;">
        <div class="robot-container">
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
        
        <div class="chat-bubble" id="robot-chat-bubble">
            <div class="chat-content">
                <p id="robot-message">Hello! Welcome to AIOHM demo experience. 🤖</p>
            </div>
            <div class="chat-arrow"></div>
        </div>
    </div>
</div>

