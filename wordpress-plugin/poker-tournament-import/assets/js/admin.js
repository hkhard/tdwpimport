/**
 * Admin JavaScript for Poker Tournament Import
 * VERSION: 2.6.1
 */

// Debug wrapper - only logs when debug mode is enabled
const POKER_DEBUG = (typeof pokerImport !== 'undefined' && pokerImport.debugMode) || false;
const debugLog = function(...args) {
    if (POKER_DEBUG) {
        debugLog('[Poker Debug]', ...args);
    }
};

// Version verification log - will appear first in console if debug enabled
debugLog('========================================');
debugLog('ADMIN.JS VERSION 2.6.1 LOADED');
debugLog('Expected pokerImport structure: {dashboardNonce, refreshNonce, ajaxUrl, adminUrl, messages}');
debugLog('Actual pokerImport:', typeof pokerImport !== 'undefined' ? pokerImport : 'UNDEFINED');
debugLog('========================================');

jQuery(document).ready(function($) {
    'use strict';

    // File upload validation
    $('#tdt_file').on('change', function() {
        const file = this.files[0];
        const uploadArea = $('.upload-area');

        if (file) {
            // Check file extension
            const fileName = file.name.toLowerCase();
            if (!fileName.endsWith('.tdt')) {
                alert('Please select a Tournament Director (.tdt) file.');
                $(this).val('');
                return;
            }

            // Check file size (max 5MB)
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if (file.size > maxSize) {
                alert('File size must be less than 5MB.');
                $(this).val('');
                return;
            }

            // Update upload area to show selected file
            uploadArea.addClass('file-selected');
            uploadArea.find('p').html(`Selected file: <strong>${escape(fileName)}</strong><br>Size: ${formatFileSize(file.size)}`);
        }
    });

    // Form submission with loading state
    $('#poker-import-form').on('submit', function(e) {
        const submitButton = $(this).find('input[type="submit"]');
        const originalText = submitButton.val();

        // Show loading state
        submitButton.val('Importing...').prop('disabled', true);
        $('.upload-area').css('opacity', '0.6');

        // Re-enable after 30 seconds in case of timeout
        setTimeout(function() {
            submitButton.val(originalText).prop('disabled', false);
            $('.upload-area').css('opacity', '1');
        }, 30000);
    });

    // Format file size helper
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Escape HTML helper
    function escape(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Auto-close success notices after 5 seconds
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut(500);
    }, 5000);

    // Confirmation for overwrite action
    $('form').on('submit', function(e) {
        if ($(this).find('[name="confirm_overwrite"]').length > 0) {
            if (!confirm('Are you sure you want to overwrite this tournament? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Toggle publish immediately based on user role
    $('#publish_immediately').on('change', function() {
        const checked = $(this).is(':checked');
        const createPlayers = $('#create_players');

        if (checked && !createPlayers.is(':checked')) {
            if (confirm('Publish immediately will create the tournament as public. Do you also want to create the players as public?')) {
                createPlayers.prop('checked', true);
            }
        }
    });

    // Add drag and drop functionality
    const uploadArea = $('.upload-area');
    console.log('[TDWP Debug] Upload area found:', uploadArea.length);

    if (uploadArea.length > 0) {
        console.log('[TDWP Debug] Initializing drag and drop functionality');
        uploadArea.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });

        uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });

        uploadArea.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');

            try {
                const files = e.originalEvent.dataTransfer.files;
                console.log('[TDWP Debug] Files dropped:', files.length);

                if (files.length > 0) {
                    const fileInput = $('#tdt_file')[0];

                    // Check if file input exists
                    if (!fileInput) {
                        console.error('[TDWP Debug] File input #tdt_file not found');
                        alert('Error: File input not found. Please refresh the page and try again.');
                        return;
                    }

                    console.log('[TDWP Debug] File input found, attempting to set files');

                    // Use DataTransfer API to properly assign files (cross-browser compatible)
                    try {
                        if (fileInput.files && typeof fileInput.files.length !== 'undefined') {
                            // Create a new DataTransfer object
                            const dataTransfer = new DataTransfer();

                            // Add each file to the DataTransfer object
                            for (let i = 0; i < files.length; i++) {
                                dataTransfer.items.add(files[i]);
                            }

                            // Assign the files to the input
                            fileInput.files = dataTransfer.files;
                            console.log('[TDWP Debug] Files assigned successfully using DataTransfer API');
                        } else {
                            console.warn('[TDWP Debug] Files property not supported, triggering manual file selection');
                            // Fallback: inform user to use file selection dialog
                            alert('Please select the file using the file selection dialog instead of drag and drop.');
                            return;
                        }
                    } catch (dataTransferError) {
                        console.error('[TDWP Debug] DataTransfer API error:', dataTransferError);
                        // Fallback: trigger click on file input
                        alert('Drag and drop is not fully supported in your browser. Please use the file selection dialog.');
                        fileInput.click();
                        return;
                    }

                    // Trigger change event
                    $(fileInput).trigger('change');
                    console.log('[TDWP Debug] Change event triggered');
                }
            } catch (error) {
                console.error('[TDWP Debug] Error in drop handler:', error);
                alert('An error occurred while processing the dropped files. Please try selecting the file manually.');
            }
        });
    }

    // Add drag-over class styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .upload-area.drag-over {
                background: #e1f5fe !important;
                border-color: #0073aa !important;
            }
            .upload-area.file-selected {
                background: #f0f8ff !important;
                border-color: #72aee6 !important;
            }
        `)
        .appendTo('head');

    // Initialize tooltips if available
    if (typeof jQuery.fn.tooltipster === 'function') {
        $('.tooltip').tooltipster({
            theme: 'tooltipster-borderless',
            animation: 'fade',
            delay: 200,
            maxWidth: 300
        });
    }

    // ========================================
    // **DASHBOARD TABBED INTERFACE**
    // ========================================

    // Initialize dashboard functionality
    if ($('.poker-dashboard-container').length > 0) {
        debugLog('[Dashboard] Initializing dashboard');
        initDashboardTabs();
        debugLog('[Dashboard] Initialization complete');
    } else {
        debugLog('[WARN]','[Dashboard] Dashboard container element not found');
    }

    function initDashboardTabs() {
        // Cache for tab content
        const tabCache = new Map();
        let loadingQueue = new Set();
        let retryCount = new Map();
        const MAX_RETRIES = 3;

        // Initialize tab click handlers
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const tabName = $btn.data('view');

            // Switch to tab
            switchToTab(tabName);
        });

        // Initialize refresh button
        $('#poker-refresh-dashboard-stats').on('click', function() {
            const $btn = $(this);
            refreshDashboardStats($btn);
        });

        // Initialize stats box links
        initStatsBoxLinks();

        // Handle URL hash for direct tab linking
        handleUrlHash();

        // Handle browser back/forward buttons
        $(window).on('popstate', function(e) {
            if (e.originalEvent.state && e.originalEvent.state.tab) {
                switchToTab(e.originalEvent.state.tab, false);
            }
        });

        // Keyboard navigation support
        $(document).on('keydown', function(e) {
            if (e.altKey && e.key >= '1' && e.key <= '5') {
                e.preventDefault();
                const tabIndex = parseInt(e.key) - 1;
                const $tabs = $('.nav-tab');
                if ($tabs[tabIndex]) {
                    const tabName = $($tabs[tabIndex]).data('view');
                    switchToTab(tabName);
                }
            }
        });

        // Load initial overview tab
        switchToTab('overview', false);

        /**
         * Switch to a specific tab
         */
        function switchToTab(tabName, updateHistory = true) {
        const $btn = $(`.nav-tab[data-view="${tabName}"]`);
        const $panel = $(`#${tabName}-tab`);

        if (!$btn.length || !$panel.length) {
            debugLog('[ERROR]',`Tab "${tabName}" not found`);
            return;
        }

        // Update active states with smooth transition
        $('.nav-tab').removeClass('active');
        $btn.addClass('active');

        $('.dashboard-tab').removeClass('active');
        $panel.addClass('active');

        // Update URL hash and history
        if (updateHistory) {
            const newUrl = window.location.pathname + '#tab-' + tabName;
            if (history.pushState) {
                history.pushState({tab: tabName}, '', newUrl);
            } else {
                window.location.hash = 'tab-' + tabName;
            }
        }

        // Load content if not already loaded
        if (!$panel.hasClass('loaded') && $panel.find('.loading-spinner').length > 0) {
            loadTabContent(tabName);
        }

        // Add visual feedback
        $panel.addClass('tab-activated');
        setTimeout(() => {
            $panel.removeClass('tab-activated');
        }, 300);
    }

    /**
     * Load tab content via AJAX with advanced features
     */
    function loadTabContent(tabName, retryAttempt = 0) {
        debugLog('[Dashboard] Loading tab:', tabName, 'Retry attempt:', retryAttempt);
        const $panel = $(`#${tabName}-tab`);

        if (!$panel.length) {
            debugLog('[ERROR]',`[Dashboard] Panel for tab "${tabName}" not found`);
            return;
        }

        // Check cache first
        if (tabCache.has(tabName)) {
            debugLog('[Dashboard] Using cached content for:', tabName);
            const cachedContent = tabCache.get(tabName);
            $panel.html(cachedContent).addClass('loaded');
            return;
        }

        // Prevent duplicate loading
        if (loadingQueue.has(tabName)) {
            debugLog('[WARN]','[Dashboard] Tab already loading:', tabName);
            return;
        }

        loadingQueue.add(tabName);
        $panel.addClass('loading');

        // Add loading animation
        const $loading = $panel.find('.loading-spinner');
        $loading.html('<i class="icon-spinner"></i> ' +
                     getLoadingMessage(tabName));

        // Determine the correct AJAX action based on tab name
        let ajaxAction = 'poker_dashboard_load_content'; // fallback
        let requestData = {
            nonce: pokerImport.dashboardNonce || ''
        };

        switch (tabName) {
            case 'overview':
                ajaxAction = 'poker_load_overview_stats';
                requestData.time_range = '30';
                requestData.series_id = '0';
                break;
            case 'tournaments':
                ajaxAction = 'poker_load_tournaments_data';
                requestData.page = '1';
                requestData.per_page = '25';
                requestData.search = '';
                requestData.status = '';
                break;
            case 'players':
                ajaxAction = 'poker_load_players_data';
                requestData.page = '1';
                requestData.per_page = '50';
                requestData.search = '';
                requestData.sort = 'total_winnings';
                requestData.order = 'DESC';
                break;
            case 'series':
                ajaxAction = 'poker_load_series_data';
                break;
            case 'seasons':
                ajaxAction = 'poker_load_seasons_data';
                break;
            case 'analytics':
                ajaxAction = 'poker_load_analytics_data';
                requestData.analytics_type = 'overview';
                break;
        }

        debugLog('[Dashboard] AJAX action:', ajaxAction);
        debugLog('[Dashboard] Request data:', requestData);

        // AJAX request with timeout and retry logic
        const ajaxRequest = $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 15000, // 15 second timeout
            data: {
                action: ajaxAction,
                ...requestData
            },
            success: function(response) {
                debugLog('[Dashboard] AJAX response received for:', tabName, response);
                if (response.success) {
                    let content = '';

                    // Handle different response formats based on tab type
                    switch (tabName) {
                        case 'overview':
                            content = renderOverviewTab(response.data);
                            break;
                        case 'tournaments':
                            content = renderTournamentsTab(response.data);
                            break;
                        case 'players':
                            content = renderPlayersTab(response.data);
                            break;
                        case 'series':
                            content = renderSeriesTab(response.data);
                            break;
                        case 'seasons':
                            content = renderSeasonsTab(response.data);
                            break;
                        case 'analytics':
                            content = renderAnalyticsTab(response.data);
                            break;
                        default:
                            // Fallback for old content-based responses
                            content = response.data.content || '<div>No content available</div>';
                    }

                    // Cache the content
                    tabCache.set(tabName, content);

                    // Update panel
                    $panel.html(content);
                    $panel.addClass('loaded');

                    // Reset retry count
                    retryCount.delete(tabName);

                    // Show success notification
                    showNotification(`${tabName.charAt(0).toUpperCase() + tabName.slice(1)} loaded successfully`, 'success');
                    debugLog('[Dashboard] Tab loaded successfully:', tabName);

                    // Reinitialize any functionality in loaded content
                    initializeTabContent(tabName);
                } else {
                    debugLog('[ERROR]','[Dashboard] AJAX error response:', response.data);
                    handleAjaxError(tabName, response.data.message || 'Server error', retryAttempt);
                }
            },
            error: function(xhr, status, error) {
                // Enhanced error logging for debugging
                debugLog('[ERROR]','[Dashboard] ========== AJAX FAILURE DETAILS ==========');
                debugLog('[ERROR]','[Dashboard] Tab Name:', tabName);
                debugLog('[ERROR]','[Dashboard] Status:', status);
                debugLog('[ERROR]','[Dashboard] Error:', error);
                debugLog('[ERROR]','[Dashboard] XHR Status Code:', xhr.status);
                debugLog('[ERROR]','[Dashboard] XHR Status Text:', xhr.statusText);
                debugLog('[ERROR]','[Dashboard] XHR Response Text:', xhr.responseText);
                debugLog('[ERROR]','[Dashboard] XHR Response JSON:', xhr.responseJSON);
                debugLog('[ERROR]','[Dashboard] AJAX Action:', ajaxAction);
                debugLog('[ERROR]','[Dashboard] Request Data:', requestData);
                debugLog('[ERROR]','[Dashboard] AJAX URL:', ajaxurl);
                debugLog('[ERROR]','[Dashboard] pokerImport object:', pokerImport);
                debugLog('[ERROR]','[Dashboard] ==========================================');

                let errorMessage = 'Network error';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Permission denied. Please refresh the page.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error. Please try again later.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Connection error - server not responding or CORS issue';
                } else if (xhr.status === 400) {
                    errorMessage = 'Bad request - check console for details';
                }

                // Display detailed error in tab panel for debugging
                const $panel = $(`#${tabName}-tab`);
                $panel.html(`
                    <div class="notice notice-error">
                        <h3>Debug Information</h3>
                        <p><strong>Error:</strong> ${errorMessage}</p>
                        <p><strong>Status Code:</strong> ${xhr.status}</p>
                        <p><strong>Status Text:</strong> ${xhr.statusText}</p>
                        <p><strong>AJAX Action:</strong> ${ajaxAction}</p>
                        <p><strong>Response:</strong></p>
                        <pre style="background:#f5f5f5;padding:10px;overflow:auto;max-height:200px;">${xhr.responseText || 'No response'}</pre>
                        <button class="button button-primary poker-retry-tab" data-tab="${tabName}">Retry</button>
                    </div>
                `);

                $('.poker-retry-tab').on('click', function() {
                    const retryTab = $(this).data('tab');
                    tabCache.delete(retryTab);
                    retryCount.delete(retryTab);
                    $(`#${retryTab}-tab`).removeClass('loaded');
                    loadTabContent(retryTab);
                });

                handleAjaxError(tabName, errorMessage, retryAttempt);
            },
            complete: function() {
                loadingQueue.delete(tabName);
                $panel.removeClass('loading');
            }
        });

        // Add retry capability
        ajaxRequest.fail(function() {
            if (retryAttempt < MAX_RETRIES) {
                setTimeout(() => {
                    debugLog(`Retrying tab "${tabName}" (attempt ${retryAttempt + 1})`);
                    loadTabContent(tabName, retryAttempt + 1);
                }, 1000 * (retryAttempt + 1)); // Exponential backoff
            }
        });
    }

    /**
     * Handle AJAX errors with retry logic
     */
    function handleAjaxError(tabName, message, retryAttempt) {
        const $panel = $(`#${tabName}-tab`);
        const currentRetries = retryCount.get(tabName) || 0;

        if (currentRetries < MAX_RETRIES) {
            retryCount.set(tabName, currentRetries + 1);

            $panel.html(`
                <div class="notice notice-warning">
                    <p><strong>Loading failed:</strong> ${message}</p>
                    <p>Retrying... (${currentRetries + 1}/${MAX_RETRIES})</p>
                    <button class="button button-small poker-retry-tab" data-tab="${tabName}">
                        Retry Now
                    </button>
                </div>
            `);

            // Bind retry button
            $('.poker-retry-tab').on('click', function() {
                const retryTab = $(this).data('tab');
                loadTabContent(retryTab);
            });
        } else {
            $panel.html(`
                <div class="notice notice-error">
                    <p><strong>Failed to load tab after ${MAX_RETRIES} attempts:</strong> ${message}</p>
                    <button class="button button-small poker-retry-tab" data-tab="${tabName}">
                        Try Again
                    </button>
                    <button class="button button-small poker-clear-cache" data-tab="${tabName}">
                        Clear Cache & Reload
                    </button>
                </div>
            `);

            // Bind retry and clear cache buttons
            $('.poker-retry-tab').on('click', function() {
                const retryTab = $(this).data('tab');
                retryCount.delete(retryTab);
                loadTabContent(retryTab);
            });

            $('.poker-clear-cache').on('click', function() {
                const clearTab = $(this).data('tab');
                tabCache.delete(clearTab);
                retryCount.delete(clearTab);
                loadTabContent(clearTab);
            });
        }
    }

    /**
     * Refresh dashboard statistics
     */
    function refreshDashboardStats($btn) {
        if ($btn.hasClass('loading')) {
            return;
        }

        $btn.addClass('loading');

        // Show progress notification
        showNotification('Refreshing statistics...', 'info');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 30000, // 30 second timeout for stats refresh
            data: {
                action: 'tdwp_refresh_statistics',
                nonce: pokerImport.refreshNonce || ''
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Statistics refreshed successfully!', 'success');

                    // Clear cache and reload current tab
                    const activeTab = $('.nav-tab.active').data('view');
                    tabCache.delete(activeTab);
                    $(`#${activeTab}-tab`).removeClass('loaded');
                    loadTabContent(activeTab);

                    // Update stats bar after a short delay
                    setTimeout(() => {
                        updateStatsBar(response.data.stats);
                    }, 500);
                } else {
                    showNotification(response.data.message || 'Failed to refresh statistics', 'error');
                }
            },
            error: function() {
                showNotification('Network error refreshing statistics', 'error');
            },
            complete: function() {
                $btn.removeClass('loading');
            }
        });
    }

    /**
     * Update the stats bar with new data
     */
    function updateStatsBar(stats) {
        if (!stats) return;

        const updates = [
            { selector: '.poker-dashboard-stats-bar .stat-item:nth-child(1) .stat-number', value: stats.total_tournaments || 0, format: 'number' },
            { selector: '.poker-dashboard-stats-bar .stat-item:nth-child(2) .stat-number', value: stats.total_players || 0, format: 'number' },
            { selector: '.poker-dashboard-stats-bar .stat-item:nth-child(3) .stat-number', value: stats.active_series || 0, format: 'number' },
            { selector: '.poker-dashboard-stats-bar .stat-item:nth-child(4) .stat-number', value: stats.total_prize_pool || 0, format: 'currency' },
            { selector: '.poker-dashboard-stats-bar .stat-item:nth-child(5) .stat-number', value: stats.recent_tournaments_30d || 0, format: 'number' }
        ];

        updates.forEach(update => {
            const $element = $(update.selector);
            if ($element.length) {
                let formattedValue = '';

                switch (update.format) {
                    case 'currency':
                        formattedValue = '$' + number_format(update.value, 0);
                        break;
                    case 'number':
                        formattedValue = number_format(update.value, 0);
                        break;
                    default:
                        formattedValue = update.value;
                }

                $element.addClass('stat-updated');
                $element.text(formattedValue);

                // Remove highlight after animation
                setTimeout(() => {
                    $element.removeClass('stat-updated');
                }, 2000);
            }
        });

        // Update last updated time
        const $footer = $('.poker-dashboard-footer .description');
        if ($footer.length && stats.last_updated) {
            const formattedDate = new Date(stats.last_updated).toLocaleString();
            $footer.html('Last updated: <strong>' + formattedDate + '</strong>');
        }
    }

    /**
     * Handle URL hash for direct tab linking
     */
    function handleUrlHash() {
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            if (hash.startsWith('tab-')) {
                const tabName = hash.substring(4);
                switchToTab(tabName, false);
            }
        }
    }

    /**
     * Initialize content within loaded tabs
     */
    function initializeTabContent(tabName) {
        switch (tabName) {
            case 'players':
                initPlayerSearch();
                initPlayerDrillthrough();
                break;
            case 'tournaments':
                initTournamentFilters();
                break;
            case 'analytics':
                initAnalyticsCharts();
                break;
            case 'series':
                initSeriesManagement();
                break;
            case 'seasons':
                initSeasonsManagement();
                break;
        }

        // Re-initialize any tooltips
        initTooltips();
    }

    /**
     * Initialize player search functionality
     */
    function initPlayerSearch() {
        const $searchInput = $('#player-search');
        if ($searchInput.length) {
            // Debounce search input
            let searchTimeout;
            $searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val();

                searchTimeout = setTimeout(() => {
                    filterPlayerList(searchTerm);
                }, 300);
            });
        }
    }

    /**
     * Filter player list based on search term
     */
    function filterPlayerList(searchTerm) {
        const $playerItems = $('.player-list-item');
        let visibleCount = 0;

        $playerItems.each(function() {
            const $item = $(this);
            const playerName = $item.data('player-name') || '';
            const isVisible = searchTerm === '' || playerName.toLowerCase().includes(searchTerm.toLowerCase());

            $item.toggle(isVisible);
            if (isVisible) visibleCount++;
        });

        // Update visible count if exists
        const $countElement = $('#visible-players');
        if ($countElement.length) {
            $countElement.text(visibleCount);
        }
    }

    /**
     * Initialize player drill-through functionality
     */
    function initPlayerDrillthrough() {
        $('.player-drillthrough').on('click', function(e) {
            e.preventDefault();
            const playerId = $(this).data('player-id');
            showPlayerDetails(playerId);
        });
    }

    /**
     * Show player details modal
     */
    function showPlayerDetails(playerId) {
        if (!playerId) return;

        // Create modal if it doesn't exist
        if (!$('#player-details-modal').length) {
            $('body').append(`
                <div id="player-details-modal" class="poker-modal" style="display: none;">
                    <div class="poker-modal-content">
                        <div class="poker-modal-header">
                            <h3>Player Details</h3>
                            <button class="poker-modal-close">&times;</button>
                        </div>
                        <div class="poker-modal-body">
                            <div class="loading">Loading player details...</div>
                        </div>
                    </div>
                </div>
            `);
        }

        const $modal = $('#player-details-modal');
        $modal.show();

        // Load player details via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_get_player_details',
                player_id: playerId,
                nonce: pokerImport.dashboardNonce || ''
            },
            success: function(response) {
                if (response.success) {
                    renderPlayerDetails(response.data);
                } else {
                    $modal.find('.poker-modal-body').html(
                        '<div class="notice notice-error">Failed to load player details</div>'
                    );
                }
            },
            error: function() {
                $modal.find('.poker-modal-body').html(
                    '<div class="notice notice-error">Network error loading player details</div>'
                );
            }
        });

        // Handle modal close
        $('.poker-modal-close, .poker-modal').on('click', function(e) {
            if (e.target === this) {
                $modal.hide();
            }
        });
    }

    /**
     * Render player details in modal
     */
    function renderPlayerDetails(playerData) {
        const $modalBody = $('#player-details-modal .poker-modal-body');

        const html = `
            <div class="player-details-grid">
                <div class="player-info">
                    <h4>${playerData.player_name}</h4>
                    <div class="player-stats">
                        <div class="stat-row">
                            <span class="stat-label">Tournaments Played:</span>
                            <span class="stat-value">${playerData.tournaments_played}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Total Winnings:</span>
                            <span class="stat-value">$${number_format(playerData.total_winnings, 0)}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Best Finish:</span>
                            <span class="stat-value">${playerData.best_finish}${getOrdinalSuffix(playerData.best_finish)}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Average Finish:</span>
                            <span class="stat-value">${number_format(playerData.avg_finish, 1)}</span>
                        </div>
                    </div>
                </div>
                <div class="recent-tournaments">
                    <h5>Recent Tournaments</h5>
                    ${renderRecentTournaments(playerData.recent_tournaments)}
                </div>
            </div>
        `;

        $modalBody.html(html);
    }

    /**
     * Render recent tournaments for player details
     */
    function renderRecentTournaments(tournaments) {
        if (!tournaments || tournaments.length === 0) {
            return '<p>No recent tournaments</p>';
        }

        let html = '<div class="recent-tournaments-list">';
        tournaments.forEach(tournament => {
            const finishClass = tournament.finish_position <= 3 ? 'finish-top' : '';
            html += `
                <div class="tournament-row ${finishClass}">
                    <div class="tournament-name">${tournament.tournament_name}</div>
                    <div class="tournament-results">
                        <span class="finish-position">${tournament.finish_position}${getOrdinalSuffix(tournament.finish_position)}</span>
                        <span class="winnings">$${number_format(tournament.winnings, 0)}</span>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        return html;
    }

    /**
     * Initialize tournament filters
     */
    function initTournamentFilters() {
        $('#tournament-filter').on('change', function() {
            const filterValue = $(this).val();
            filterTournaments(filterValue);
        });
    }

    /**
     * Filter tournaments based on selected criteria
     */
    function filterTournaments(filterValue) {
        // Implementation depends on tournament filtering requirements
        debugLog('Filtering tournaments:', filterValue);
    }

    /**
     * Initialize analytics charts (placeholder)
     */
    function initAnalyticsCharts() {
        // Placeholder for chart initialization
        debugLog('Initializing analytics charts');
    }

    /**
     * Initialize series management
     */
    function initSeriesManagement() {
        debugLog('Initializing series management');

        // Add click handlers for series drillthrough
        $('.series-drillthrough').on('click', function() {
            const viewLink = $(this).data('view-link');
            if (viewLink) {
                window.location.href = viewLink;
            }
        });
    }

    /**
     * Initialize seasons management
     */
    function initSeasonsManagement() {
        debugLog('Initializing seasons management');

        // Add click handlers for season drillthrough
        $('.season-drillthrough').on('click', function() {
            const viewLink = $(this).data('view-link');
            if (viewLink) {
                window.location.href = viewLink;
            }
        });
    }

    /**
     * Initialize stats box links
     */
    function initStatsBoxLinks() {
        debugLog('[Dashboard] Initializing stat box links');

        // Handle leaderboard link click
        const $leaderboardLink = $('#poker-view-leaderboard');
        if ($leaderboardLink.length) {
            $leaderboardLink.on('click', function(e) {
                e.preventDefault();
                debugLog('[Dashboard] Leaderboard link clicked');
                showLeaderboardModal();
            });
            debugLog('[Dashboard] Leaderboard link initialized');
        } else {
            debugLog('[WARN]','[Dashboard] Leaderboard link not found');
        }
    }

    /**
     * Show leaderboard modal
     */
    function showLeaderboardModal() {
        debugLog('[Dashboard] Opening leaderboard modal');

        // Create modal if it doesn't exist
        if (!$('#leaderboard-modal').length) {
            debugLog('[Dashboard] Creating leaderboard modal');
            $('body').append(`
                <div id="leaderboard-modal" class="poker-modal" style="display: none;">
                    <div class="poker-modal-content">
                        <div class="poker-modal-header">
                            <h3>Complete Leaderboard</h3>
                            <button class="poker-modal-close">&times;</button>
                        </div>
                        <div class="poker-modal-body">
                            <div class="loading">
                                <span class="spinner is-active"></span>
                                Loading leaderboard...
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }

        const $modal = $('#leaderboard-modal');
        $modal.show().addClass('show');

        // Load leaderboard data via AJAX
        debugLog('[Dashboard] Loading leaderboard data via AJAX');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_get_leaderboard_data',
                nonce: pokerImport.dashboardNonce || ''
            },
            success: function(response) {
                debugLog('[Dashboard] Leaderboard data received:', response);
                if (response.success) {
                    renderLeaderboardContent(response.data);
                } else {
                    debugLog('[ERROR]','[Dashboard] Leaderboard error:', response.data);
                    $modal.find('.poker-modal-body').html(
                        '<div class="notice notice-error">Failed to load leaderboard</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                debugLog('[ERROR]','[Dashboard] Leaderboard AJAX error:', {xhr, status, error});
                $modal.find('.poker-modal-body').html(
                    '<div class="notice notice-error">Network error loading leaderboard</div>'
                );
            }
        });

        // Handle modal close
        $('.poker-modal-close, #leaderboard-modal').on('click', function(e) {
            if (e.target === this) {
                $modal.hide().removeClass('show');
            }
        });
    }

    /**
     * Render leaderboard content in modal
     */
    function renderLeaderboardContent(data) {
        const $modalBody = $('#leaderboard-modal .poker-modal-body');

        if (!data.leaderboard || data.leaderboard.length === 0) {
            $modalBody.html('<p class="no-data">No leaderboard data available</p>');
            return;
        }

        let html = `
            <div class="leaderboard-table-wrapper">
                <table class="widefat leaderboard-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Tournaments</th>
                            <th>Total Winnings</th>
                            <th>Best Finish</th>
                            <th>Average Finish</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.leaderboard.forEach((player, index) => {
            const finishClass = player.best_finish <= 3 ? 'finish-top' : '';
            html += `
                <tr class="${finishClass}">
                    <td><strong>${index + 1}</strong></td>
                    <td>${player.name}</td>
                    <td>${player.tournaments_played}</td>
                    <td>$${number_format(player.total_winnings, 0)}</td>
                    <td>${player.best_finish}${getOrdinalSuffix(player.best_finish)}</td>
                    <td>${number_format(player.avg_finish, 1)}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        $modalBody.html(html);
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('.has-tooltip').each(function() {
            const $element = $(this);
            const tooltipText = $element.attr('title') || $element.data('tooltip');

            if (tooltipText) {
                $element.on('mouseenter', function(e) {
                    const $tooltip = $('<div class="poker-tooltip">' + tooltipText + '</div>');
                    $('body').append($tooltip);

                    $tooltip.css({
                        position: 'absolute',
                        top: e.pageY - 30,
                        left: e.pageX + 10,
                        zIndex: 10000
                    }).fadeIn(200);
                }).on('mouseleave', function() {
                    $('.poker-tooltip').fadeOut(200, function() {
                        $(this).remove();
                    });
                });
            }
        });
    }

    /**
     * Show notification to user
     */
    function showNotification(message, type = 'info') {
        // Create notification element if it doesn't exist
        if (!$('#poker-notifications').length) {
            $('body').append('<div id="poker-notifications"></div>');
        }

        const $notification = $(`
            <div class="poker-notification poker-notification-${type}">
                ${message}
            </div>
        `);

        $('#poker-notifications').append($notification);

        // Animate in
        setTimeout(() => {
            $notification.addClass('show');
        }, 100);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            $notification.removeClass('show');
            setTimeout(() => {
                $notification.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Get loading message for tab
     */
    function getLoadingMessage(tabName) {
        const messages = {
            overview: 'Loading overview...',
            tournaments: 'Loading tournaments...',
            players: 'Loading players...',
            series: 'Loading series...',
            analytics: 'Loading analytics...'
        };
        return messages[tabName] || 'Loading...';
    }

    /**
     * Helper function to get ordinal suffix
     */
    function getOrdinalSuffix(number) {
        const k = number % 100;
        // Check 11-13 exception FIRST (they always end in 'th')
        if (k >= 11 && k <= 13) return 'th';
        // Then check last digit for 1st, 2nd, 3rd
        const j = number % 10;
        if (j === 1) return 'st';
        if (j === 2) return 'nd';
        if (j === 3) return 'rd';
        return 'th';
    }

    /**
     * Helper function for number formatting
     */
    function number_format(number, decimals = 0) {
        return parseFloat(number).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Add dashboard-specific CSS
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            /* Tab activation animation */
            .tab-activated {
                animation: tabActivate 0.3s ease-out;
            }

            @keyframes tabActivate {
                0% { opacity: 0.7; transform: translateY(10px); }
                100% { opacity: 1; transform: translateY(0); }
            }

            /* Stat update animation */
            .stat-updated {
                animation: statUpdate 0.5s ease-out;
            }

            @keyframes statUpdate {
                0% { transform: scale(1); color: #0073aa; }
                50% { transform: scale(1.1); color: #00a0d2; }
                100% { transform: scale(1); color: #0073aa; }
            }

            /* Modal styles */
            .poker-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .poker-modal-content {
                background: #fff;
                border-radius: 8px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            }

            .poker-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #eee;
            }

            .poker-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
            }

            .poker-modal-body {
                padding: 20px;
            }

            /* Player details */
            .player-details-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }

            .stat-row {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .stat-label {
                font-weight: 500;
                color: #666;
            }

            .stat-value {
                font-weight: 600;
                color: #333;
            }

            .finish-top {
                background: #f0f8ff;
                border-left: 3px solid #0073aa;
            }

            .tournament-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .finish-position {
                font-weight: 600;
                color: #0073aa;
            }

            .winnings {
                color: #28a745;
                font-weight: 500;
            }

            /* Notifications */
            #poker-notifications {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 100001;
            }

            .poker-notification {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 12px 16px;
                margin-bottom: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                transform: translateX(100%);
                transition: all 0.3s ease;
                max-width: 300px;
            }

            .poker-notification.show {
                transform: translateX(0);
            }

            .poker-notification-success {
                border-left: 4px solid #28a745;
                color: #155724;
            }

            .poker-notification-error {
                border-left: 4px solid #dc3545;
                color: #721c24;
            }

            .poker-notification-info {
                border-left: 4px solid #17a2b8;
                color: #0c5460;
            }

            .poker-tooltip {
                background: #333;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 100002;
                max-width: 200px;
            }

            /* Loading enhancements */
            .poker-dashboard-loading {
                position: relative;
            }

            .poker-dashboard-loading::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.8);
                z-index: 10;
            }

            /* Responsive modal */
            @media (max-width: 768px) {
                .player-details-grid {
                    grid-template-columns: 1fr;
                }

                .poker-modal-content {
                    width: 95%;
                    margin: 10px;
                }

                #poker-notifications {
                    left: 10px;
                    right: 10px;
                }

                .poker-notification {
                    max-width: none;
                }
            }
        `)
        .appendTo('head');

    // ========================================
    // **TAB RENDERING FUNCTIONS**
    // ========================================

    /**
     * Render Overview tab content
     */
    function renderOverviewTab(data) {
        const overview = data.overview || {};
        const trends = data.trends || {};
        const topPerformers = data.top_performers || [];

        let html = `
            <div class="overview-dashboard">
                <div class="overview-stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">${number_format(overview.total_tournaments || 0)}</div>
                        <div class="stat-label">Total Tournaments</div>
                        <div class="stat-trend ${getTrendClass(trends.tournaments)}">${trends.tournaments || '0%'}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">${number_format(overview.total_players || 0)}</div>
                        <div class="stat-label">Total Players</div>
                        <div class="stat-trend ${getTrendClass(trends.players)}">${trends.players || '0%'}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">$${number_format(overview.total_prizepool || 0)}</div>
                        <div class="stat-label">Total Prize Pool</div>
                        <div class="stat-trend ${getTrendClass(trends.prizepool)}">${trends.prizepool || '0%'}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">${number_format(overview.average_players || 0, 1)}</div>
                        <div class="stat-label">Average Players</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">$${number_format(overview.average_prizepool || 0)}</div>
                        <div class="stat-label">Average Prize Pool</div>
                    </div>
                </div>
        `;

        if (topPerformers.length > 0) {
            html += `
                <div class="top-performers-section">
                    <h4>Top Performers</h4>
                    <div class="performers-list">
            `;

            topPerformers.forEach((player, index) => {
                html += `
                    <div class="performer-item">
                        <span class="rank">${index + 1}</span>
                        <span class="player-name">${player.name}</span>
                        <span class="winnings">$${number_format(player.winnings)}</span>
                        <span class="tournaments">${player.tournaments} tournaments</span>
                        <span class="best-finish">Best: ${player.best_finish}${getOrdinalSuffix(player.best_finish)}</span>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        html += `</div>`;
        return html;
    }

    /**
     * Render Tournaments tab content
     */
    function renderTournamentsTab(data) {
        const tournaments = data.tournaments || [];
        const pagination = data.pagination || {};

        let html = '<div class="tournament-leaderboard-scroll-wrapper">';
        html += '<div class="tournament-leaderboard-table">';

        // Gradient header matching player tab
        html += '<div class="table-header">';
        html += '<div class="header-rank">RANK</div>';
        html += '<div class="header-tournament">TOURNAMENT</div>';
        html += '<div class="header-date">DATE</div>';
        html += '<div class="header-players">PLAYERS</div>';
        html += '<div class="header-prize">PRIZE POOL</div>';
        html += '<div class="header-status">STATUS</div>';
        html += '</div>';

        if (tournaments.length === 0) {
            html += '<div class="table-row"><div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">No tournaments found.</div></div>';
        } else {
            // Data rows
            tournaments.forEach((tournament, idx) => {
                const statusClass = tournament.status === 'publish' ? 'status-published' : 'status-draft';
                html += '<div class="table-row">';
                html += `<div class="rank-cell">${idx + 1}</div>`;
                html += `<div class="tournament-cell"><a href="${tournament.view_link || tournament.edit_link}">${tournament.title}</a></div>`;
                html += `<div class="date-cell">${formatDate(tournament.date)}</div>`;
                html += `<div class="players-cell">${tournament.players_count}</div>`;
                html += `<div class="prize-cell">$${number_format(tournament.prize_pool)}</div>`;
                html += `<div class="status-cell ${statusClass}">${tournament.status === 'publish' ? 'Published' : 'Draft'}</div>`;
                html += '</div>';
            });

            // Add pagination if needed
            if (pagination.total_pages > 1) {
                html += '<div class="table-row" style="grid-column: 1 / -1; padding: 20px; border-top: 2px solid #e5e7eb;">';
                html += renderPagination(pagination);
                html += '</div>';
            }
        }

        html += '</div></div>';
        return html;
    }

    /**
     * Render Players tab content
     */
    function renderPlayersTab(data) {
        const players = data.players || [];
        const pagination = data.pagination || {};

        let html = `
            <div class="players-dashboard">
                <div class="players-controls">
                    <input type="text" id="player-search" placeholder="Search players..." class="regular-text">
                    <select id="player-sort" class="regular-text">
                        <option value="total_winnings">Total Winnings</option>
                        <option value="tournaments_played">Tournaments Played</option>
                        <option value="average_finish">Average Finish</option>
                        <option value="roi">ROI</option>
                    </select>
                    <button id="player-refresh" class="button">Refresh</button>
                </div>

                <div class="players-list">
        `;

        if (players.length === 0) {
            html += `<p class="no-data">No players found.</p>`;
        } else {
            html += `<div class="player-items">`;
            players.forEach(player => {
                html += `
                    <div class="player-item player-drillthrough" data-player-id="${player.id}" data-player-name="${player.name}">
                        <div class="player-info">
                            <h4>${player.name}</h4>
                            <div class="player-stats">
                                <span class="stat">Tournaments: ${player.tournaments_played}</span>
                                <span class="stat">Winnings: $${number_format(player.total_winnings)}</span>
                                <span class="stat">Avg Finish: ${number_format(player.average_finish, 1)}</span>
                                <span class="stat">Best: ${player.best_finish}${getOrdinalSuffix(player.best_finish)}</span>
                                <span class="stat ${getRoiClass(player.roi)}">ROI: ${number_format(player.roi, 1)}%</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += `</div>`;

            // Add pagination
            if (pagination.total_pages > 1) {
                html += renderPagination(pagination);
            }
        }

        html += `</div></div>`;
        return html;
    }

    /**
     * Render Series tab content
     */
    function renderSeriesTab(data) {
        const series = data.series || [];

        let html = '<div class="series-leaderboard-scroll-wrapper">';
        html += '<div class="series-leaderboard-table">';

        // Gradient header matching player tab style
        html += '<div class="table-header series-header">';
        html += '<div class="header-rank">RANK</div>';
        html += '<div class="header-series">SERIES</div>';
        html += '<div class="header-tournaments">TOURNAMENTS</div>';
        html += '<div class="header-players">TOTAL PLAYERS</div>';
        html += '<div class="header-prize">PRIZE POOL</div>';
        html += '<div class="header-avg">AVG PLAYERS</div>';
        html += '</div>';

        if (series.length === 0) {
            html += '<div class="table-row"><div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">No series found.</div></div>';
        } else {
            series.forEach((item, idx) => {
                html += '<div class="table-row series-drillthrough" data-view-link="' + item.view_link + '" style="cursor: pointer;">';
                html += '<div class="rank-cell">' + (idx + 1) + '</div>';
                html += '<div class="series-cell">' + item.title + '</div>';
                html += '<div class="tournaments-cell">' + item.tournament_count + '</div>';
                html += '<div class="players-cell">' + number_format(item.total_players) + '</div>';
                html += '<div class="prize-cell">$' + number_format(item.total_prizepool) + '</div>';
                html += '<div class="avg-cell">' + number_format(item.average_players, 1) + '</div>';
                html += '</div>';
            });
        }

        html += '</div></div>';
        return html;
    }

    /**
     * Render Seasons tab content
     */
    function renderSeasonsTab(data) {
        const seasons = data.seasons || [];

        let html = '<div class="seasons-leaderboard-scroll-wrapper">';
        html += '<div class="seasons-leaderboard-table">';

        // Gradient header matching player tab style
        html += '<div class="table-header seasons-header">';
        html += '<div class="header-rank">RANK</div>';
        html += '<div class="header-season">SEASON</div>';
        html += '<div class="header-tournaments">TOURNAMENTS</div>';
        html += '<div class="header-players">TOTAL PLAYERS</div>';
        html += '<div class="header-prize">PRIZE POOL</div>';
        html += '<div class="header-avg">AVG PLAYERS</div>';
        html += '</div>';

        if (seasons.length === 0) {
            html += '<div class="table-row"><div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">No seasons found.</div></div>';
        } else {
            seasons.forEach((item, idx) => {
                html += '<div class="table-row season-drillthrough" data-view-link="' + item.view_link + '" style="cursor: pointer;">';
                html += '<div class="rank-cell">' + (idx + 1) + '</div>';
                html += '<div class="season-cell">' + item.title + '</div>';
                html += '<div class="tournaments-cell">' + item.tournament_count + '</div>';
                html += '<div class="players-cell">' + number_format(item.total_players) + '</div>';
                html += '<div class="prize-cell">$' + number_format(item.total_prizepool) + '</div>';
                html += '<div class="avg-cell">' + number_format(item.average_players, 1) + '</div>';
                html += '</div>';
            });
        }

        html += '</div></div>';
        return html;
    }

    /**
     * Render Analytics tab content
     */
    function renderAnalyticsTab(data) {
        let html = `
            <div class="analytics-dashboard">
                <div class="analytics-controls">
                    <select id="analytics-type" class="regular-text">
                        <option value="overview">Overview</option>
                        <option value="prize_distribution">Prize Distribution</option>
                        <option value="player_trends">Player Trends</option>
                        <option value="tournament_growth">Tournament Growth</option>
                    </select>
                    <button id="analytics-refresh" class="button">Refresh</button>
                </div>

                <div class="analytics-content">
                    <div class="analytics-grid">
        `;

        // Prize Distribution Chart
        if (data.prize_distribution && data.prize_distribution.length > 0) {
            html += `
                <div class="analytics-card">
                    <h4>Prize Pool Distribution</h4>
                    <div class="chart-container">
            `;

            data.prize_distribution.forEach(item => {
                const percentage = 100; // Would need total for actual percentage
                html += `
                    <div class="chart-bar">
                        <div class="chart-label">${item.prize_range}</div>
                        <div class="chart-bar-container">
                            <div class="chart-bar-fill" style="width: ${percentage}%"></div>
                        </div>
                        <div class="chart-value">${item.tournament_count} tournaments</div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        // Player Trends
        if (data.player_trends) {
            html += `
                <div class="analytics-card">
                    <h4>Player Trends</h4>
                    <div class="trend-stats">
                        <div class="trend-item">
                            <span class="trend-label">New Players (30d):</span>
                            <span class="trend-value">${data.player_trends.new_players || 0}</span>
                        </div>
                        <div class="trend-item">
                            <span class="trend-label">Returning Players (30d):</span>
                            <span class="trend-value">${data.player_trends.returning_players || 0}</span>
                        </div>
                    </div>
                </div>
            `;
        }

        // Tournament Growth
        if (data.tournament_growth && data.tournament_growth.length > 0) {
            html += `
                <div class="analytics-card">
                    <h4>Tournament Growth</h4>
                    <div class="growth-chart">
            `;

            data.tournament_growth.slice(0, 6).forEach(item => {
                html += `
                    <div class="growth-item">
                        <span class="growth-month">${item.month}</span>
                        <span class="growth-count">${item.tournament_count}</span>
                        <span class="growth-avg">${number_format(item.avg_players, 1)} avg players</span>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        html += `
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    // ========================================
    // **HELPER FUNCTIONS FOR TAB RENDERING**
    // ========================================

    /**
     * Get trend CSS class
     */
    function getTrendClass(trend) {
        if (!trend) return '';
        const value = parseFloat(trend.replace('%', ''));
        if (value > 0) return 'trend-up';
        if (value < 0) return 'trend-down';
        return 'trend-neutral';
    }

    /**
     * Get ROI CSS class
     */
    function getRoiClass(roi) {
        if (roi > 0) return 'roi-positive';
        if (roi < 0) return 'roi-negative';
        return 'roi-neutral';
    }

    /**
     * Format date for display
     */
    function formatDate(dateString) {
        if (!dateString) return 'Unknown';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    /**
     * Render pagination controls
     */
    function renderPagination(pagination) {
        const currentPage = pagination.current_page || 1;
        const totalPages = pagination.total_pages || 1;

        if (totalPages <= 1) return '';

        let html = `
            <div class="pagination-controls">
                <div class="pagination-info">
                    Showing page ${currentPage} of ${totalPages} (${pagination.total_items} total items)
                </div>
                <div class="pagination-links">
        `;

        // Previous button
        if (currentPage > 1) {
            html += `<button class="button page-btn" data-page="${currentPage - 1}">← Previous</button>`;
        }

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            const className = i === currentPage ? 'button button-primary' : 'button';
            html += `<button class="${className} page-btn" data-page="${i}">${i}</button>`;
        }

        // Next button
        if (currentPage < totalPages) {
            html += `<button class="button page-btn" data-page="${currentPage + 1}">Next →</button>`;
        }

        html += `
                </div>
            </div>
        `;

        return html;
    }
    } // END initDashboardTabs

    // ========================================
    // **CURRENCY SYMBOL SPACE PRESERVATION**
    // ========================================

    // Preserve leading/trailing spaces in currency symbol input
    const currencyInput = $('input[name="poker_currency_symbol"]');
    if (currencyInput.length > 0) {
        debugLog('[Currency] Currency symbol input found, initializing space preservation');

        // Store original value with spaces
        let preservedValue = currencyInput.val();

        // On focus, show visual indicator for spaces
        currencyInput.on('focus', function() {
            const value = $(this).val();
            // Count leading/trailing spaces for visual feedback
            const leadingSpaces = value.match(/^(\s+)/);
            const trailingSpaces = value.match(/(\s+)$/);

            if (leadingSpaces || trailingSpaces) {
                let hint = 'Spaces preserved: ';
                if (leadingSpaces) hint += leadingSpaces[0].length + ' leading';
                if (leadingSpaces && trailingSpaces) hint += ', ';
                if (trailingSpaces) hint += trailingSpaces[0].length + ' trailing';

                debugLog('[Currency] ' + hint);
            }
        });

        // Store value before form submission
        currencyInput.closest('form').on('submit', function(e) {
            // Preserve the exact value with spaces
            preservedValue = currencyInput.val();
            debugLog('[Currency] Preserving value with spaces:', JSON.stringify(preservedValue));

            // Create a hidden input to carry the exact value
            const hiddenInput = $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'poker_currency_symbol_preserved')
                .val(preservedValue);

            $(this).append(hiddenInput);
        });

        // Restore value after page load (in case browser strips it)
        const storedValue = currencyInput.val();
        if (storedValue !== preservedValue && preservedValue !== '') {
            debugLog('[Currency] Restoring preserved value');
            currencyInput.val(preservedValue);
        }
    }

    // Console log for debugging
    debugLog('Poker Tournament Import Admin JavaScript loaded');
});