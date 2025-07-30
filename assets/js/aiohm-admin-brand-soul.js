// Self-invoking function to avoid polluting the global scope
(function($) {
    // Only run the script if the main layout exists (i.e., user is connected)
    if ($('.aiohm-page-layout').length === 0) {
        return;
    }

    let currentQuestionIndex = 0;
    const slides = $('.aiohm-question-slide');
    const navLinks = $('.nav-question-link'); // Use a specific class for navigation links
    const totalQuestions = slides.length - 1;

    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const progressBarInner = document.querySelector('.aiohm-progress-bar-inner');
    const progressLabel = document.querySelector('.aiohm-progress-label');

    function updateView() {
        slides.removeClass('active');
        $(slides[currentQuestionIndex]).addClass('active').find('textarea').focus();

        navLinks.removeClass('active');
        navLinks.filter(`[data-index=${currentQuestionIndex}]`).addClass('active');

        const progressPercentage = (currentQuestionIndex / totalQuestions) * 100;
        progressBarInner.style.width = progressPercentage + '%';
        
        progressLabel.textContent = currentQuestionIndex < totalQuestions 
            ? `Question ${currentQuestionIndex + 1} of ${totalQuestions}` 
            : 'Completed!';

        prevBtn.style.display = currentQuestionIndex > 0 ? 'inline-block' : 'none';
        nextBtn.style.display = currentQuestionIndex < totalQuestions ? 'inline-block' : 'none';

        if (currentQuestionIndex === totalQuestions) {
            const finalActionsHtml = `
                <button type="button" id="save-brand-soul" class="button button-primary">${aiohm_brand_soul_ajax.save_answers_text}</button>
                <button type="button" id="add-to-kb" class="button button-secondary">${aiohm_brand_soul_ajax.add_to_kb_text}</button>
                <a href="${aiohm_brand_soul_ajax.download_pdf_url}" id="download-pdf" class="button button-secondary" target="_blank">${aiohm_brand_soul_ajax.download_pdf_text}</a>
            `;
            $('.aiohm-final-actions').html(finalActionsHtml);
        } else {
             $('.aiohm-final-actions').empty();
        }
    }

    // --- Event Listeners ---
    nextBtn.addEventListener('click', () => {
        if (currentQuestionIndex < totalQuestions) {
            currentQuestionIndex++;
            updateView();
        }
    });

    prevBtn.addEventListener('click', () => {
        if (currentQuestionIndex > 0) {
            currentQuestionIndex--;
            updateView();
        }
    });

    // Add a single delegated event listener to the navigation container
    $('.aiohm-side-nav').on('click', '.nav-question-link', function(e) {
        e.preventDefault();
        currentQuestionIndex = parseInt($(this).data('index'), 10);
        updateView();
    });

    // Delegated event handlers for final action buttons
    $('.aiohm-form-container').on('click', '#save-brand-soul', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).text(aiohm_brand_soul_ajax.saving_text);
        $.post(aiohm_brand_soul_ajax.ajax_url, {
            action: 'aiohm_save_brand_soul',
            nonce: aiohm_brand_soul_ajax.nonce,
            data: $('#brand-soul-form').serialize()
        }).done(response => {
            showAdminNotice(response.success ? aiohm_brand_soul_ajax.save_success_text : 'Error: ' + (response.data.message || aiohm_brand_soul_ajax.save_error_text), response.success ? 'success' : 'error');
        }).fail(() => showAdminNotice(aiohm_brand_soul_ajax.server_error_text, 'error')).always(() => $btn.prop('disabled', false).text(aiohm_brand_soul_ajax.save_answers_text));
    });

    // Save progress button for each question
    $('.aiohm-form-container').on('click', '#save-progress-btn', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text(aiohm_brand_soul_ajax.saving_text);
        $.post(aiohm_brand_soul_ajax.ajax_url, {
            action: 'aiohm_save_brand_soul',
            nonce: aiohm_brand_soul_ajax.nonce,
            data: $('#brand-soul-form').serialize()
        }).done(response => {
            showAdminNotice(response.success ? aiohm_brand_soul_ajax.progress_save_success_text : 'Error: ' + (response.data.message || aiohm_brand_soul_ajax.save_error_text), response.success ? 'success' : 'error');
        }).fail(() => showAdminNotice(aiohm_brand_soul_ajax.server_error_text, 'error')).always(() => $btn.prop('disabled', false).text(originalText));
    });

    $('.aiohm-form-container').on('click', '#add-to-kb', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).text(aiohm_brand_soul_ajax.adding_text);
        $.post(aiohm_brand_soul_ajax.ajax_url, {
            action: 'aiohm_add_brand_soul_to_kb',
            nonce: aiohm_brand_soul_ajax.nonce,
            data: $('#brand-soul-form').serialize()
        }).done(response => {
            showAdminNotice(response.success ? aiohm_brand_soul_ajax.add_to_kb_success_text : 'Error: ' + (response.data.message || aiohm_brand_soul_ajax.add_to_kb_error_text), response.success ? 'success' : 'error');
        }).fail(() => showAdminNotice(aiohm_brand_soul_ajax.server_error_text, 'error')).always(() => $btn.prop('disabled', false).text(aiohm_brand_soul_ajax.add_to_kb_text));
    });

    // Enhanced admin notice function with accessibility features
    function showAdminNotice(message, type = 'success', persistent = false) {
        let $noticeDiv = $('#aiohm-admin-notice');
        
        // Create notice div if it doesn't exist
        if ($noticeDiv.length === 0) {
            $('<div id="aiohm-admin-notice" class="notice is-dismissible admin-notice-hidden" tabindex="-1" role="alert" aria-live="polite"></div>').insertAfter('h1');
            $noticeDiv = $('#aiohm-admin-notice');
        }
        
        // Clear existing classes and add new type
        $noticeDiv.removeClass('notice-success notice-error notice-warning notice-info').addClass('notice-' + type);
        
        // Set message content - create p element if it doesn't exist
        let $p = $noticeDiv.find('p');
        if ($p.length === 0) {
            $p = $('<p></p>').appendTo($noticeDiv);
        }
        $p.html(message);
        
        // Show notice with fade in effect
        $noticeDiv.fadeIn(300, function() {
            // Auto-focus for accessibility after fade in completes
            $noticeDiv.focus();
            
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

    // Initial view setup
    updateView();

})(jQuery);