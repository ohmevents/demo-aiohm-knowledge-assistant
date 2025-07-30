<?php
/**
 * Admin Brand Core Questionnaire page template - Final version with a two-column layout,
 * side navigation menu, and a "Typeform-like" user experience.
 * Includes a robust, conflict-free access control lock and corrected JavaScript syntax.
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- Start: Data Fetching and Status Checks ---
$settings = AIOHM_KB_Assistant::get_settings();
$is_tribe_member_connected = !empty($settings['aiohm_app_email']);

$user_id = get_current_user_id();
$brand_soul_answers = get_user_meta($user_id, 'aiohm_brand_soul_answers', true);
if (!is_array($brand_soul_answers)) {
    $brand_soul_answers = [];
}

$brand_soul_questions = [
    '✨ Foundation' => [
        'foundation_1' => "What's the deeper purpose behind your brand — beyond profit?",
        'foundation_2' => "What life experiences shaped this work you now do?",
        'foundation_3' => "Who were you before this calling emerged?",
        'foundation_4' => "If your brand had a soul story, how would you tell it?",
        'foundation_5' => "What's one transformation you've witnessed that reminds you why you do this?",
    ],
];

$total_questions = 0;
foreach ($brand_soul_questions as $section) {
    $total_questions += count($section);
}
// --- End: Data Fetching ---
?>

<div class="wrap aiohm-brand-soul-page">
    <h1><?php esc_html_e('Your Brand Core Questionnaire', 'aiohm-knowledge-assistant'); ?></h1>
    <p class="page-description"><?php esc_html_e('Answer these questions to define the core of your brand. Your answers will help shape your AI assistant\'s voice and knowledge.', 'aiohm-knowledge-assistant'); ?></p>


    <div id="aiohm-admin-notice" class="notice is-dismissible" tabindex="-1" role="alert" aria-live="polite"></div>

    <?php if (!$is_tribe_member_connected) : ?>
        <div class="aiohm-content-locked">
            <div class="lock-content">
                <div class="lock-icon">🔒</div>
                <h2><?php esc_html_e('Unlock Your AI Brand Core', 'aiohm-knowledge-assistant'); ?></h2>
                <p><?php esc_html_e('This questionnaire is a key feature for AIOHM Tribe members. Please connect your free Tribe account to begin defining your brand\'s soul.', 'aiohm-knowledge-assistant'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-license')); ?>" class="button button-primary"><?php esc_html_e('Connect Your Account', 'aiohm-knowledge-assistant'); ?></a>
            </div>
        </div>
    <?php else: ?>
        <div class="aiohm-page-layout">
            <div class="aiohm-side-nav">
                <nav>
                    <?php
                    $question_index_for_nav = 0;
                    foreach ($brand_soul_questions as $section_title => $questions) {
                        echo "<div class='nav-section'>";
                        echo "<h4>" . esc_html($section_title) . "</h4>";
                        echo "<ol start='" . esc_attr($question_index_for_nav + 1) . "'>";
                        foreach ($questions as $key => $question_text) {
                            echo "<li><a href='#' class='nav-question-link' data-index='" . esc_attr($question_index_for_nav) . "'>" . esc_html($question_text) . "</a></li>";
                            $question_index_for_nav++;
                        }
                        echo "</ol>";
                        echo "</div>";
                    }
                    ?>
                     <div class="nav-section-final">
                        <a href="#" class='nav-question-link' data-index="<?php echo esc_attr($total_questions); ?>" class="final-actions-link">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Save & Export', 'aiohm-knowledge-assistant'); ?>
                        </a>
                    </div>
                    
                    <!-- Tribe upgrade notification in sidebar -->
                    <div class="nav-tribe-upgrade" style="margin-top: 20px; padding: 15px; background: #cbddd1; border: 2px solid #457d58; border-radius: 10px; font-family: 'Montserrat', sans-serif;">
                        <h4 style="margin: 0 0 10px 0; color: #1f5014; font-family: 'Montserrat Alternates', sans-serif; font-size: 1em; font-weight: 600; text-align: center;">🌟 Want the Complete Brand Soul Experience?</h4>
                        <p style="margin: 0 0 12px 0; color: #272727; font-family: 'PT Sans', sans-serif; font-size: 0.85em; line-height: 1.4; text-align: center;">Register for free AIOHM Tribe to unlock all 20 questions!</p>
                        <a href="https://aiohm.app/tribe" target="_blank" style="display: block; background: #457d58; color: white; padding: 10px 15px; border-radius: 6px; text-decoration: none; font-weight: 600; font-family: 'Montserrat', sans-serif; font-size: 0.9em; text-align: center; box-shadow: 0 2px 6px rgba(69, 125, 88, 0.3);" onmouseover="this.style.background='#1f5014'" onmouseout="this.style.background='#457d58'">→ Join AIOHM Tribe (Free)</a>
                    </div>
                </nav>
            </div>

            <div class="aiohm-form-container">
                <div class="aiohm-progress-bar">
                    <div class="aiohm-progress-bar-inner"></div>
                    <div class="aiohm-progress-label"></div>
                </div>

                <form id="brand-soul-form">
                    <?php wp_nonce_field('aiohm_brand_soul_nonce', 'aiohm_brand_soul_nonce_field'); ?>

                    <div class="aiohm-questions-wrapper">
                        <?php
                        $question_index = 0;
                        foreach ($brand_soul_questions as $section_title => $questions) {
                            foreach ($questions as $key => $question_text) {
                                $is_active = ($question_index === 0) ? 'active' : '';
                                echo "<div class='aiohm-question-slide " . esc_attr($is_active) . "' data-index='" . esc_attr($question_index) . "'>";
                                echo "<p class='question-text'>" . esc_html($question_text) . "</p>";
                                echo "<textarea name='answers[" . esc_attr($key) . "]' placeholder='Type your answer here...' rows='5'>" . esc_textarea($brand_soul_answers[$key] ?? '') . "</textarea>";
                                echo "</div>";
                                $question_index++;
                            }
                        }
                        
                        echo "<div class='aiohm-question-slide' data-index='" . esc_attr($question_index) . "'>";
                        echo "<h2 class='question-section-title'>All Done!</h2>";
                        echo "<p class='question-text'>You've completed your Brand Soul questionnaire. You can now save your work, add it to your private knowledge base for your AI to use, or download a copy.</p>";
                        echo "<div class='aiohm-final-actions'></div>";
                        echo "</div>";
                        ?>
                    </div>

                    <div class="aiohm-navigation">
                        <button type="button" id="prev-btn" class="button button-secondary"><?php esc_html_e('Previous', 'aiohm-knowledge-assistant'); ?></button>
                        <button type="button" id="save-progress-btn" class="button button-secondary"><?php esc_html_e('Save Progress', 'aiohm-knowledge-assistant'); ?></button>
                        <button type="button" id="next-btn" class="button button-primary"><?php esc_html_e('Next', 'aiohm-knowledge-assistant'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>


