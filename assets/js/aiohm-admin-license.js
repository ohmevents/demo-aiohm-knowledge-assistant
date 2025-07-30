jQuery(document).ready(function($) {
    const $step1 = $('#aiohm-verification-step-1');
    const $step2 = $('#aiohm-verification-step-2');
    const $status = $('#aiohm-verification-status');
    const $emailInput = $('#aiohm-verification-email');
    const $codeInput = $('#aiohm-verification-code');
    
    let currentEmail = '';

    function showStatus(message, type) {
        $status.removeClass('success error loading').addClass(type).text(message).show();
    }

    function hideStatus() {
        $status.hide();
    }

    // Send verification code
    $('#aiohm-send-code-btn').on('click', function() {
        const email = $emailInput.val().trim();
        
        if (!email || !email.includes('@')) {
            showStatus('Please enter a valid email address.', 'error');
            return;
        }

        currentEmail = email;
        showStatus('Sending verification code...', 'loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiohm_send_verification_code',
                email: email,
                nonce: aiohm_license_ajax.nonce
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    $step1.hide();
                    $step2.show();
                    showStatus(data.message, 'success');
                    $codeInput.focus();
                } else {
                    showStatus(data.error || 'Failed to send verification code.', 'error');
                }
            },
            error: function() {
                showStatus('Network error. Please try again.', 'error');
            }
        });
    });

    // Verify code
    $('#aiohm-verify-code-btn').on('click', function() {
        const code = $codeInput.val().trim();
        
        if (!code) {
            showStatus('Please enter the verification code.', 'error');
            return;
        }

        showStatus('Verifying code...', 'loading');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aiohm_verify_email_code',
                email: currentEmail,
                code: code,
                nonce: aiohm_license_ajax.nonce
            },
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    showStatus(data.message, 'success');
                    // Reload page after successful verification
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showStatus(data.error || 'Verification failed.', 'error');
                }
            },
            error: function() {
                showStatus('Network error. Please try again.', 'error');
            }
        });
    });

    // Resend code
    $('#aiohm-resend-code-btn').on('click', function() {
        $('#aiohm-send-code-btn').click();
    });

    // Allow Enter key to trigger actions
    $emailInput.on('keypress', function(e) {
        if (e.which === 13) {
            $('#aiohm-send-code-btn').click();
        }
    });

    $codeInput.on('keypress', function(e) {
        if (e.which === 13) {
            $('#aiohm-verify-code-btn').click();
        }
    });

    // Club countdown functionality
    function loadClubCountdown() {
        $.ajax({
            url: 'https://www.aiohm.app/wp-json/aiohm/v1/get-club-count',
            type: 'GET',
            success: function(data) {
                if (data.success) {
                    updateCountdownDisplay(data);
                } else {
                    showCountdownError();
                }
            },
            error: function() {
                showCountdownError();
            }
        });
    }

    function updateCountdownDisplay(data) {
        const $remainingSpots = $('#remaining-spots');
        const $progressBar = $('#progress-bar');
        const $clubStats = $('#club-stats');
        
        // Update remaining spots number
        $remainingSpots.html(data.remaining_spots.toLocaleString());
        
        // Apply color based on urgency
        if (data.remaining_spots <= 50) {
            $remainingSpots.addClass('countdown-urgent');
        } else if (data.remaining_spots <= 200) {
            $remainingSpots.addClass('countdown-warning');
        }
        
        // Update progress bar
        $progressBar.css('width', data.percentage_filled + '%');
        
        // Update stats text
        const statsText = data.total_members.toLocaleString() + ' of ' + data.max_spots.toLocaleString() + ' spots taken (' + data.percentage_filled + '%)';
        $clubStats.text(statsText);
        
        // Show urgency message if needed
        if (data.remaining_spots <= 50) {
            $clubStats.html(statsText + '<br><strong class="club-stats-warning">⚡ Almost full! Limited spots remaining</strong>');
        } else if (data.remaining_spots <= 200) {
            $clubStats.html(statsText + '<br><strong class="club-stats-alert">🔥 Filling up fast!</strong>');
        }
    }

    function showCountdownError() {
        $('#remaining-spots').html('???');
        $('#club-stats').text('Unable to load current availability');
        $('#progress-bar').css('width', '0%');
    }

    // Load countdown on page load
    loadClubCountdown();
    
    // Refresh countdown every 30 seconds
    setInterval(loadClubCountdown, 30000);

    // Tribe counter functionality
    function loadTribeCounter() {
        $.ajax({
            url: 'https://www.aiohm.app/wp-json/aiohm/v1/get-tribe-count',
            type: 'GET',
            success: function(data) {
                if (data.success) {
                    updateTribeCounterDisplay(data);
                } else {
                    showTribeCounterError();
                }
            },
            error: function() {
                showTribeCounterError();
            }
        });
    }

    function updateTribeCounterDisplay(data) {
        const $tribeCount = $('#tribe-members-count');
        const $tribeStats = $('#tribe-stats');
        
        // Update tribe members count
        $tribeCount.html(data.total_members.toLocaleString());
        
        // Update stats text
        const statsText = 'Growing community of conscious creators';
        $tribeStats.text(statsText);
    }

    function showTribeCounterError() {
        $('#tribe-members-count').html('???');
        $('#tribe-stats').text('Unable to load community stats');
    }

    // Load tribe counter on page load
    loadTribeCounter();
    
    // Refresh tribe counter every 60 seconds
    setInterval(loadTribeCounter, 60000);
});