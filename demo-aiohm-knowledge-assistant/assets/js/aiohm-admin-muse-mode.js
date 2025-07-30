jQuery(document).ready(function($) {
    // Enhanced admin notice function with accessibility features
    function showAdminNotice(message, type = 'success', persistent = false) {
        let $noticeDiv = $('#aiohm-admin-notice');
        
        // Create notice div if it doesn't exist
        if ($noticeDiv.length === 0) {
            $('<div id="aiohm-admin-notice" class="notice is-dismissible admin-notice-hidden" tabindex="-1" role="alert" aria-live="polite"></div>').insertAfter('h1');
            $noticeDiv = $('#aiohm-admin-notice');
        }
        
        // Clear existing classes and add new type
        $noticeDiv.removeClass('notice-success notice-error notice-warning').addClass('notice-' + type);
        
        // Set message content - create p element if it doesn't exist
        let $p = $noticeDiv.find('p');
        if ($p.length === 0) {
            $p = $('<p></p>').appendTo($noticeDiv);
        }
        $p.html(message);
        
        // Show notice with fade in effect
        $noticeDiv.fadeIn(300, function() {
            // Auto-focus for accessibility and scroll to notice
            $noticeDiv.focus();
            $('html, body').animate({
                scrollTop: $noticeDiv.offset().top - 100
            }, 300);
            
            // Announce to screen readers
            if (type === 'error') {
                $noticeDiv.attr('aria-live', 'assertive');
            } else {
                $noticeDiv.attr('aria-live', 'polite');
            }
        });
        
        // Handle dismiss button
        $noticeDiv.off('click.notice-dismiss').on('click.notice-dismiss', '.notice-dismiss', function() {
            $noticeDiv.fadeOut(300);
            // Return focus to the previously focused element or main content
            $('h1').focus();
        });
        
        // Auto-hide after timeout (unless persistent)
        if (!persistent) {
            setTimeout(() => {
                if ($noticeDiv.is(':visible')) {
                    $noticeDiv.fadeOut(300, function() {
                        // Return focus to main content when auto-hiding
                        $('h1').focus();
                    });
                }
            }, 7000); // Increased to 7 seconds for better UX
        }
    }

    // If there are any AJAX calls or form submissions in this page,
    // they should use showAdminNotice instead of alerts
});