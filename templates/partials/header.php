<?php
/**
 * AIOHM Plugin Admin Header - Redesigned with internal plugin page navigation.
 */
if (!defined('ABSPATH')) exit;

// Get the current page to set the 'active' class on the menu item
$current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter safe for admin navigation
?>
<div class="aiohm-admin-header">
    <div class="aiohm-admin-header__logo">
        <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-dashboard')); ?>">
            <?php
            echo wp_kses_post(AIOHM_KB_Core_Init::render_image(
                AIOHM_KB_PLUGIN_URL . 'assets/images/AIOHM-logo.png',
                esc_attr__('AIOHM Logo', 'aiohm-knowledge-assistant')
            ));
            ?>
        </a>
    </div>
    <div class="aiohm-admin-header__nav">
        <nav class="aiohm-nav">
          <ul class="aiohm-menu">
            <li class="<?php echo esc_attr(($current_page === 'aiohm-dashboard') ? 'active' : ''); ?>">
              <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-dashboard')); ?>">Dashboard</a>
            </li>
            <li class="<?php echo esc_attr(($current_page === 'aiohm-brand-soul') ? 'active' : ''); ?>">
              <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-brand-soul')); ?>">AI Brand Core</a>
            </li>
             <li class="has-submenu">
                <a href="#" class="<?php echo esc_attr(in_array($current_page, ['aiohm-scan-content', 'aiohm-manage-kb']) ? 'active' : ''); ?>">Knowledge Base</a>
                <ul class="submenu">
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-scan-content')); ?>">Scan Content</a></li>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-manage-kb')); ?>">Manage KB</a></li>
                </ul>
            </li>
<?php
            // Determine which settings pages to show based on membership
            $has_mcp_access = AIOHM_KB_PMP_Integration::aiohm_user_has_mcp_access();
            $settings_pages = ['aiohm-settings', 'aiohm-license'];
            if ($has_mcp_access) {
                $settings_pages[] = 'aiohm-mcp';
            }
            ?>
            <li class="has-submenu">
              <a href="#" class="<?php echo esc_attr(in_array($current_page, $settings_pages) ? 'active' : ''); ?>">Settings</a>
              <ul class="submenu">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-settings')); ?>">API Settings</a></li>
                <?php if ($has_mcp_access): ?>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-mcp')); ?>">MCP API</a></li>
                <?php endif; ?>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-license')); ?>">License</a></li>
              </ul>
            </li>
            <li>
              <a href="https://www.aiohm.app/contact/" target="_blank">Contact</a>
            </li>
          </ul>
        </nav>
    </div>
</div>
<div class="aiohm-admin-wrap">

