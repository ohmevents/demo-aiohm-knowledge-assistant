/**
 * AIOHM Search Shortcode JavaScript
 * Handles the frontend search functionality for the [aiohm_search] shortcode
 */

jQuery(document).ready(function($) {
    'use strict';

    // Process all search containers on the page
    if (typeof window.aiohm_search_configs !== 'undefined') {
        Object.keys(window.aiohm_search_configs).forEach(function(searchId) {
            const config = window.aiohm_search_configs[searchId];
            const $container = $('#' + config.search_id);
            const $input = $container.find('.aiohm-search-input');
            const $btn = $container.find('.aiohm-search-btn');
            const $results = $container.find('.aiohm-search-results');
            const $loading = $container.find('.aiohm-search-loading');
            
            let searchTimeout;
            
            // Search function
            function performSearch(query) {
                if (!query || query.length < config.settings.min_chars) {
                    $results.empty();
                    return;
                }
                
                $loading.show();
                $results.empty();
                
                $.ajax({
                    url: config.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'aiohm_search_knowledge',
                        query: query,
                        nonce: config.nonce,
                        max_results: config.settings.max_results,
                        excerpt_length: config.settings.excerpt_length,
                        content_type_filter: $container.find('.aiohm-content-type-filter').val() || ''
                    },
                    success: function(response) {
                        $loading.hide();
                        
                        if (response.success && response.data) {
                            displaySearchResults(response.data, $results, config);
                        } else {
                            $results.html('<div class="aiohm-search-error">' + (response.data?.message || config.strings.error) + '</div>');
                        }
                    },
                    error: function() {
                        $loading.hide();
                        $results.html('<div class="aiohm-search-error">' + config.strings.error + '</div>');
                    }
                });
            }
            
            // Display search results
            function displaySearchResults(data, $results, config) {
                if (!data.results || data.results.length === 0) {
                    $results.html('<div class="aiohm-search-no-results">' + config.strings.no_results + '</div>');
                    return;
                }
                
                let html = '';
                
                if (config.settings.show_results_count) {
                    html += '<div class="aiohm-search-count">' + config.strings.results_count.replace('%d', data.results.length) + '</div>';
                }
                
                html += '<div class="aiohm-search-results-list">';
                
                data.results.forEach(function(result) {
                    html += '<div class="aiohm-search-result-item">';
                    html += '<h4 class="aiohm-search-result-title">' + escapeHtml(result.title) + '</h4>';
                    
                    if (config.settings.show_content_type && result.content_type) {
                        html += '<span class="aiohm-search-result-type">' + escapeHtml(result.content_type) + '</span>';
                    }
                    
                    if (result.excerpt) {
                        html += '<p class="aiohm-search-result-excerpt">' + escapeHtml(result.excerpt) + '</p>';
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
                $results.html(html);
            }
            
            // Helper function to escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Event handlers
            $btn.on('click', function(e) {
                e.preventDefault();
                performSearch($input.val().trim());
            });
            
            $input.on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    performSearch($input.val().trim());
                }
            });
            
            // Instant search
            if (config.settings.enable_instant_search) {
                $input.on('input', function() {
                    clearTimeout(searchTimeout);
                    const query = $(this).val().trim();
                    
                    if (query.length >= config.settings.min_chars) {
                        searchTimeout = setTimeout(function() {
                            performSearch(query);
                        }, 300); // 300ms delay
                    } else {
                        $results.empty();
                    }
                });
            }
        });
    }
});