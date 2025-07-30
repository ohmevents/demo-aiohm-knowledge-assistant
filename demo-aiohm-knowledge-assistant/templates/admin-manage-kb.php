<?php
/**
 * Template for the Manage Knowledge Base admin page.
 * This is the final version with all UI improvements and working scripts.
 */
if (!defined('ABSPATH')) exit;

// Check club membership for button access control
$has_club_access = class_exists('AIOHM_KB_PMP_Integration') && AIOHM_KB_PMP_Integration::aiohm_user_has_club_access();

// Include the header for consistent branding
include_once AIOHM_KB_PLUGIN_DIR . 'templates/partials/header.php';
?>

<div class="wrap aiohm-manage-kb-page">
    <h1 class="wp-heading-inline"><?php esc_html_e('Manage Knowledge Base', 'aiohm-knowledge-assistant'); ?></h1>
    <button type="button" id="add-content-btn" class="page-title-action aiohm-btn-primary"><?php esc_html_e('Add New Content', 'aiohm-knowledge-assistant'); ?></button>
    <a href="<?php echo esc_url(add_query_arg(['page' => 'aiohm-scan-content'], admin_url('admin.php'))); ?>" class="page-title-action aiohm-btn-secondary scan-website-link"><?php esc_html_e('Scan Website', 'aiohm-knowledge-assistant'); ?></a>
    <p class="page-description"><?php esc_html_e('View, organize, and manage all your knowledge base entries in one place.', 'aiohm-knowledge-assistant'); ?></p>

    <div id="aiohm-admin-notice" class="notice is-dismissible admin-notice-hidden" tabindex="-1" role="alert" aria-live="polite"></div>

    <hr class="wp-header-end">

    <div class="aiohm-knowledge-intro">
        <div class="knowledge-section public-section">
            <div class="section-header">
                <h3><span class="section-icon">🌍</span> <?php esc_html_e('Public Knowledge (Mirror Mode)', 'aiohm-knowledge-assistant'); ?></h3>
                <?php if ($has_club_access): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-mirror-mode')); ?>" class="button aiohm-btn-secondary section-link">
                        <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Configure Mirror Mode', 'aiohm-knowledge-assistant'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-license&tab=club')); ?>" class="button aiohm-btn-secondary section-link">
                        <span class="dashicons dashicons-lock"></span> <?php esc_html_e('Configure Mirror Mode', 'aiohm-knowledge-assistant'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <p><?php 
                // translators: %s is the word "Public" that will be bolded
                printf(esc_html__('%s entries are part of the global knowledge base. They are used by your AI assistant to answer questions from any website visitor.', 'aiohm-knowledge-assistant'), '<strong>' . esc_html__('Public', 'aiohm-knowledge-assistant') . '</strong>'); ?></p>
            <p><?php esc_html_e('This is perfect for general support, FAQs, and public information about your brand.', 'aiohm-knowledge-assistant'); ?></p>
        </div>
        <div class="knowledge-section private-section">
            <div class="section-header">
                <h3><span class="section-icon">🔒</span> <?php esc_html_e('Private Knowledge (Muse Mode)', 'aiohm-knowledge-assistant'); ?></h3>
                <?php if ($has_club_access): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-muse-mode')); ?>" class="button aiohm-btn-secondary section-link">
                        <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Configure Muse Mode', 'aiohm-knowledge-assistant'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiohm-license&tab=club')); ?>" class="button aiohm-btn-secondary section-link">
                        <span class="dashicons dashicons-lock"></span> <?php esc_html_e('Configure Muse Mode', 'aiohm-knowledge-assistant'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <p><?php 
                // translators: %s is the word "Private" that will be bolded
                printf(esc_html__('%s entries are only accessible to you when using the Brand Assistant chat (Muse Mode).', 'aiohm-knowledge-assistant'), '<strong>' . esc_html__('Private', 'aiohm-knowledge-assistant') . '</strong>'); ?></p>
            <p><?php esc_html_e('Use this for personal notes, strategic insights, or confidential brand guidelines that only you should access.', 'aiohm-knowledge-assistant'); ?></p>
        </div>
    </div>

    <!-- Custom Bulk Actions (bypassing WP_List_Table issues) -->
    <div id="aiohm-custom-bulk-actions" class="bulk-actions-container">
        <strong><?php esc_html_e('Bulk Actions:', 'aiohm-knowledge-assistant'); ?></strong>
        <select id="aiohm-bulk-action-select">
            <option value=""><?php esc_html_e('Select Action', 'aiohm-knowledge-assistant'); ?></option>
            <option value="delete"><?php esc_html_e('Delete', 'aiohm-knowledge-assistant'); ?></option>
            <option value="make_public"><?php esc_html_e('Make Public', 'aiohm-knowledge-assistant'); ?></option>
            <option value="make_private"><?php esc_html_e('Make Private', 'aiohm-knowledge-assistant'); ?></option>
        </select>
        <button type="button" id="aiohm-bulk-action-btn" class="button"><?php esc_html_e('Apply', 'aiohm-knowledge-assistant'); ?></button>
        <span id="aiohm-bulk-status" class="bulk-status"></span>
    </div>

    <form id="kb-filter-form" method="post" action="">
        <!-- Admin filter form - page parameter safe for admin interface -->
        <?php $page_param = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page parameter safe for admin interface ?>
        <input type="hidden" name="page" value="<?php echo esc_attr($page_param); ?>" />
        
        <!-- Hidden form for custom bulk actions -->
        <form id="aiohm-bulk-form" method="post" class="bulk-form-hidden">
            <input type="hidden" name="page" value="<?php echo esc_attr($page_param); ?>" />
            <input type="hidden" name="aiohm_bulk_action" id="aiohm-bulk-action-input" value="" />
            <?php wp_nonce_field('aiohm_bulk_action', 'aiohm_bulk_nonce'); ?>
            <div id="aiohm-bulk-ids"></div>
        </form>
        
        <?php
        // The display() method of WP_List_Table will render bulk actions and filters
        // via its built-in functionality and the extra_tablenav() method.
        if (isset($list_table)) {
            $list_table->display();
        } else {
            echo '<p>No knowledge base entries found.</p>';
        }
        ?>
    </form>

    <div id="aiohm-kb-actions" class="kb-actions-container">
        <form method="post" action="options.php">
            <?php settings_fields('aiohm_kb_settings'); ?>
            <div class="aiohm-settings-section">
                <h2><?php esc_html_e('Knowledge Base Actions', 'aiohm-knowledge-assistant'); ?></h2>
                <div class="actions-grid-wrapper">

                    <div class="action-box">
                        <h3><?php esc_html_e('Export Knowledge Base', 'aiohm-knowledge-assistant'); ?></h3>
                        <p class="description"><?php esc_html_e('Export your knowledge base entries as JSON files. Choose to export public entries or private entries separately.', 'aiohm-knowledge-assistant'); ?></p>
                        <div class="export-buttons">
                            <button type="button" class="button aiohm-btn-primary" id="export-public-btn"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Public', 'aiohm-knowledge-assistant'); ?></button>
                            <button type="button" class="button aiohm-btn-secondary" id="export-private-btn"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Private', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                    </div>

                    <div class="action-box">
                        <h3><?php esc_html_e('Restore Knowledge Base', 'aiohm-knowledge-assistant'); ?></h3>
                        <p class="description"><?php esc_html_e('Overwrite all existing public knowledge base entries from a previously saved JSON file.', 'aiohm-knowledge-assistant'); ?></p>
                        <div class="restore-controls">
                            <div class="file-input-group">
                                <input type="file" id="restore-kb-file" accept=".json" class="restore-file-input">
                                <label for="restore-kb-file" class="button aiohm-btn-secondary"><span class="dashicons dashicons-upload"></span> <?php esc_html_e('Choose File...', 'aiohm-knowledge-assistant'); ?></label>
                                <span id="restore-file-name" class="file-name-display"></span>
                            </div>
                            <button type="button" class="button aiohm-btn-primary button-hero" id="restore-kb-btn" disabled><?php esc_html_e('Restore KB', 'aiohm-knowledge-assistant'); ?></button>
                        </div>
                    </div>

                    <div class="action-box reset-action">
                        <h3><?php esc_html_e('Reset Knowledge Base', 'aiohm-knowledge-assistant'); ?></h3>
                        <p class="description warning-description"><strong><?php esc_html_e('Warning: This will permanently delete ALL knowledge base entries (public & private). This cannot be undone.', 'aiohm-knowledge-assistant'); ?></strong></p>
                        <button type="button" class="button aiohm-btn-danger button-hero" id="reset-kb-btn"><span class="dashicons dashicons-trash"></span> <?php esc_html_e('Reset Entire KB', 'aiohm-knowledge-assistant'); ?></button>
                    </div>
                </div>
            </div>
            <?php // The submit button for 'Save Schedule Setting' is removed as it's no longer relevant here. ?>
        </form>
    </div>
</div>


<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo esc_attr(wp_create_nonce('aiohm_admin_nonce')); ?>';

    // Custom bulk action handler
    $('#aiohm-bulk-action-btn').on('click', function() {
        const action = $('#aiohm-bulk-action-select').val();
        const checkedBoxes = $('input[name="entry_ids[]"]:checked');
        
        if (!action) {
            alert('Please select an action.');
            return;
        }
        
        if (checkedBoxes.length === 0) {
            alert('Please select at least one item.');
            return;
        }
        
        const confirmMsg = action === 'delete' ? 
            'Are you sure you want to delete the selected items?' : 
            'Are you sure you want to ' + action.replace('_', ' ') + ' the selected items?';
            
        if (!confirm(confirmMsg)) {
            return;
        }
        
        $('#aiohm-bulk-action-input').val(action);
        $('#aiohm-bulk-ids').empty();
        
        checkedBoxes.each(function() {
            $('#aiohm-bulk-ids').append('<input type="hidden" name="entry_ids[]" value="' + $(this).val() + '">');
        });
        
        $('#aiohm-bulk-status').text('Processing...');
        $('#aiohm-bulk-form').submit();
    });

    // Reset KB button
    $('#reset-kb-btn').on('click', function(){
        if (confirm('Are you absolutely sure you want to delete all knowledge base data? This cannot be undone.')) {
            const $btn = $(this);
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner is-active spinner-inline"></span> Resetting...');
            
            $.post(ajaxurl, {
                action: 'aiohm_reset_kb',
                nonce: nonce
            }).done(function(response){
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }).fail(function(){
                alert('An unexpected server error occurred.');
            }).always(function(){
                $btn.prop('disabled', false).html(originalText);
            });
        }
    });

    // Export buttons
    $('#export-public-btn').on('click', function(){
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active spinner-inline"></span> Exporting...');

        $.post(ajaxurl, {
            action: 'aiohm_export_kb',
            scope: 'public',
            nonce: nonce
        }).done(function(response){
            if (response.success) {
                const data = response.data.data;
                const filename = response.data.filename;
                const blob = new Blob([data], {type: 'application/json'});
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                alert('Public knowledge base exported successfully!');
            } else {
                alert('Error: ' + (response.data.message || 'Could not export.'));
            }
        }).fail(function(){
            alert('An unexpected server error occurred during export.');
        }).always(function(){
            $btn.prop('disabled', false).html(originalText);
        });
    });

    $('#export-private-btn').on('click', function(){
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active spinner-inline"></span> Exporting...');

        $.post(ajaxurl, {
            action: 'aiohm_export_kb',
            scope: 'private',
            nonce: nonce
        }).done(function(response){
            if (response.success) {
                const data = response.data.data;
                const filename = response.data.filename;
                const blob = new Blob([data], {type: 'application/json'});
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                alert('Private knowledge base exported successfully!');
            } else {
                alert('Error: ' + (response.data.message || 'Could not export.'));
            }
        }).fail(function(){
            alert('An unexpected server error occurred during export.');
        }).always(function(){
            $btn.prop('disabled', false).html(originalText);
        });
    });

    // Add New Content button (file upload modal)
    $('#add-content-btn').on('click', function() {
        // Create upload modal
        $('body').append(`
            <div id="file-upload-modal" class="aiohm-modal" style="display: flex;">
                <div class="aiohm-modal-backdrop"></div>
                <div class="aiohm-modal-content">
                    <div class="aiohm-modal-header">
                        <h2>Upload Files to Knowledge Base</h2>
                        <button type="button" class="aiohm-modal-close">&times;</button>
                    </div>
                    <div class="aiohm-modal-body">
                        <p>Upload documents directly to your knowledge base. Supported formats: .txt, .json, .csv, .pdf, .doc, .docx, .md</p>
                        
                        <div style="margin-bottom: 20px;">
                            <label for="kb-scope" style="font-weight: bold;">Knowledge Base Scope:</label>
                            <select id="kb-scope" style="width: 100%;">
                                <option value="public">Public (Mirror Mode - visible to all visitors)</option>
                                <option value="private">Private (Muse Mode - visible only to you)</option>
                            </select>
                        </div>
                        
                        <input type="file" id="file-input" multiple accept=".txt,.json,.csv,.pdf,.doc,.docx,.md" style="display: none;">
                        <div id="drop-zone" style="border: 2px dashed #ccc; padding: 40px; text-align: center; cursor: pointer;">
                            <p><strong>Drop files here or click to browse</strong></p>
                            <p>Maximum file size: 10MB per file</p>
                        </div>
                        
                        <div style="margin-top: 20px; text-align: right;">
                            <button type="button" class="button" onclick="$('#file-upload-modal').remove();">Cancel</button>
                            <button type="button" id="start-upload" class="button button-primary" disabled>Upload to Knowledge Base</button>
                        </div>
                    </div>
                </div>
            </div>
        `);

        // Handle file selection
        $('#drop-zone').on('click', function() {
            $('#file-input').click();
        });

        $('#file-input').on('change', function() {
            if (this.files.length > 0) {
                $('#start-upload').prop('disabled', false);
                $('#drop-zone').html('<p><strong>' + this.files.length + ' file(s) selected</strong></p>');
            }
        });

        // Handle modal close
        $('.aiohm-modal-close, .aiohm-modal-backdrop').on('click', function() {
            $('#file-upload-modal').remove();
        });
    });
});
</script>

<?php
// Include the footer for consistent branding
include_once AIOHM_KB_PLUGIN_DIR . 'templates/partials/footer.php';
?>