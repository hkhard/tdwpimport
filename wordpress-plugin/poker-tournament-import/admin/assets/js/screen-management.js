/**
 * TD3 Screen Management JavaScript
 *
 * Handles interactions for the display screen management interface
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.4.0
 */

(function($) {
    'use strict';

    // Main screen management object
    window.TDWP_ScreenManagement = {

        // Configuration
        config: {
            ajaxUrl: tdwp_screen_mgmt.ajax_url,
            nonce: tdwp_screen_mgmt.nonce,
        },

        // State
        state: {
            currentScreen: null,
            refreshing: false,
            screens: [],
            healthStatus: null,
        },

        /**
         * Initialize screen management
         */
        init: function() {
            this.setupTabs();
            this.setupDialogs();
            this.setupEventHandlers();
            this.loadInitialData();

            console.log('TDWP Screen Management: Initialized');
        },

        /**
         * Setup jQuery UI tabs
         */
        setupTabs: function() {
            $('#screen-management-tabs').tabs({
                activate: function(event, ui) {
                    // Load data when switching tabs
                    if (ui.newPanel.attr('id') === 'system-health') {
                        TDWP_ScreenManagement.refreshHealthStatus();
                    }
                }
            });
        },

        /**
         * Setup dialog modals
         */
        setupDialogs: function() {
            try {
                // Check if jQuery UI dialog is available
                if (typeof $.fn.dialog === 'undefined') {
                    console.error('TDWP Screen Management: jQuery UI dialog not available');
                    this.showMessage('Dialog functionality not available. jQuery UI may not be properly loaded.', 'error');
                    return;
                }

                // Check if dialog element exists
                if ($('#screen-dialog').length === 0) {
                    console.error('TDWP Screen Management: Dialog element not found');
                    return;
                }

                $('#screen-dialog').dialog({
                    autoOpen: false,
                    modal: true,
                    width: 600,
                    height: 'auto',
                    resizable: false,
                    dialogClass: 'tdwp-screen-dialog',
                    buttons: [
                        {
                            text: 'Cancel',
                            class: 'button',
                            click: function() {
                                $(this).dialog('close');
                            }
                        },
                        {
                            text: 'Save Screen',
                            class: 'button button-primary',
                            click: function() {
                                TDWP_ScreenManagement.saveScreen();
                            }
                        }
                    ],
                    open: function() {
                        // Load dependent data when dialog opens
                        $('body').addClass('tdwp-dialog-open');
                        TDWP_ScreenManagement.loadTournaments();
                        TDWP_ScreenManagement.loadLayouts();
                    },
                    close: function() {
                        $('body').removeClass('tdwp-dialog-open');
                        TDWP_ScreenManagement.resetForm();
                    },
                    create: function() {
                        console.log('TDWP Screen Management: Dialog created successfully');
                    },
                    error: function(event, ui) {
                        console.error('TDWP Screen Management: Dialog error:', event, ui);
                        TDWP_ScreenManagement.showMessage('Failed to create dialog. Please refresh the page.', 'error');
                    }
                });

                console.log('TDWP Screen Management: Dialog initialized successfully');
            } catch (error) {
                console.error('TDWP Screen Management: Dialog initialization failed:', error);
                this.showMessage('Dialog system failed to initialize. Some features may not work properly.', 'error');
            }
        },

        /**
         * Setup event handlers
         */
        setupEventHandlers: function() {
            // Toolbar buttons
            $('#add-new-screen').on('click', function(e) {
                e.preventDefault();
                console.log('TDWP Screen Management: Add Screen button clicked');
                TDWP_ScreenManagement.openAddScreenDialog();
            });

            $('#add-first-screen').on('click', function(e) {
                e.preventDefault();
                console.log('TDWP Screen Management: Add First Screen button clicked');
                TDWP_ScreenManagement.openAddScreenDialog();
            });

            $('#refresh-screens').on('click', function(e) {
                e.preventDefault();
                TDWP_ScreenManagement.refreshScreenStatus();
            });

            // Screen card actions
            $(document).on('click', '.edit-screen', function() {
                var screenId = $(this).closest('.screen-card').data('screen-id');
                TDWP_ScreenManagement.editScreen(screenId);
            });

            $(document).on('click', '.delete-screen', function() {
                var screenId = $(this).closest('.screen-card').data('screen-id');
                if (confirm(tdwp_screen_mgmt.confirm_delete)) {
                    TDWP_ScreenManagement.deleteScreen(screenId);
                }
            });

            $(document).on('click', '.toggle-screen', function() {
                var $button = $(this);
                var screenId = $button.closest('.screen-card').data('screen-id');
                var isActive = $button.data('active') === '1';
                TDWP_ScreenManagement.toggleScreen(screenId, !isActive);
            });

            $(document).on('click', '.preview-screen', function() {
                var previewUrl = $(this).data('preview-url');
                window.open(previewUrl, '_blank');
            });

            // Template actions
            $(document).on('click', '.assign-template', function() {
                var templateId = $(this).closest('.template-card').data('template-id');
                TDWP_ScreenManagement.assignTemplateToScreen(templateId);
            });

            // Form changes
            $('#screen-type').on('change', function() {
                TDWP_ScreenManagement.updateFormFields();
            });

            $('#tournament-id').on('change', function() {
                TDWP_ScreenManagement.updateLayoutOptions();
            });

            // Auto-detect tournament button
            $('#auto-detect-tournament').on('click', function() {
                TDWP_ScreenManagement.autoDetectRunningTournament();
            });

            // Auto-assign tournament buttons
            $(document).on('click', '.auto-assign-tournament', function() {
                var screenId = $(this).closest('.screen-card').data('screen-id');
                TDWP_ScreenManagement.autoAssignScreen(screenId);
            });

            // Auto-refresh
            setInterval(function() {
                if (!TDWP_ScreenManagement.state.refreshing) {
                    TDWP_ScreenManagement.refreshScreenStatus();
                }
            }, 30000); // Refresh every 30 seconds
        },

        /**
         * Load initial data
         */
        loadInitialData: function() {
            this.refreshScreenStatus();
            this.refreshHealthStatus();
        },

        /**
         * Open add screen dialog
         */
        openAddScreenDialog: function() {
            try {
                console.log('TDWP Screen Management: Opening add screen dialog');

                // Reset form state
                this.state.currentScreen = null;

                // Check if dialog exists and is initialized
                var $dialog = $('#screen-dialog');
                if ($dialog.length === 0) {
                    this.showMessage('Dialog element not found. Please refresh the page.', 'error');
                    return;
                }

                if (!$dialog.hasClass('ui-dialog-content')) {
                    this.showMessage('Dialog not properly initialized. Please refresh the page.', 'error');
                    return;
                }

                // Set title and open dialog
                $dialog.dialog('option', 'title', 'Add New Screen');
                $dialog.dialog('open');

                console.log('TDWP Screen Management: Dialog opened successfully');

            } catch (error) {
                console.error('TDWP Screen Management: Failed to open dialog:', error);
                this.showMessage('Failed to open dialog. Please refresh the page and try again.', 'error');
            }
        },

        /**
         * Edit screen
         */
        editScreen: function(screenId) {
            var screen = this.findScreenById(screenId);
            if (!screen) return;

            this.state.currentScreen = screen;
            $('#screen-dialog').dialog('option', 'title', 'Edit Screen');

            // Populate form
            $('#screen-name').val(screen.screen_name);
            $('#screen-location').val(screen.screen_location || '');
            $('#screen-type').val(screen.screen_type);
            $('#tournament-id').val(screen.tournament_id || '');
            $('#layout-id').val(screen.layout_id || '');
            $('#refresh-rate').val(screen.refresh_rate || 30);
            $('#screen-description').val(screen.screen_description || '');
            $('#screen-active').prop('checked', screen.is_active);
            $('#screen-id').val(screen.screen_id);

            // Update dependent fields
            this.updateFormFields();
            this.updateLayoutOptions();

            $('#screen-dialog').dialog('open');
        },

        /**
         * Generate URL-friendly endpoint from screen name
         */
        generateEndpointUrl: function(screenName) {
            if (!screenName) {
                return 'screen-' + Date.now();
            }

            // Convert to lowercase, replace spaces with hyphens, remove special characters
            var endpoint = screenName.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');

            // Ensure it's not empty
            if (!endpoint) {
                endpoint = 'screen-' + Date.now();
            }

            return endpoint;
        },

        /**
         * Save screen
         */
        saveScreen: function() {
            var $form = $('#screen-form');
            var screenId = $('#screen-id').val();

            console.log('TDWP Screen Management: Starting save process...');

            // Validate required fields
            if (!$('#screen-name').val().trim()) {
                this.showMessage('Screen name is required', 'error');
                return;
            }

            var screenName = $('#screen-name').val();
            var endpointUrl = this.generateEndpointUrl(screenName);

            var formData = {
                action: 'tdwp_screen_management',
                sub_action: screenId ? 'edit_screen' : 'add_screen',
                nonce: this.config.nonce,
                screen_id: screenId,
                screen_name: screenName,
                screen_location: $('#screen-location').val(),
                screen_type: $('#screen-type').val(),
                tournament_id: $('#tournament-id').val(),
                layout_id: $('#layout-id').val(),
                refresh_rate: $('#refresh-rate').val(),
                screen_description: $('#screen-description').val(),
                endpoint_url: endpointUrl,
                is_active: $('#screen-active').is(':checked') ? 1 : 0
            };

            console.log('TDWP Screen Management: Form data being sent:', formData);
            console.log('TDWP Screen Management: Generated endpoint URL:', endpointUrl);

            this.showLoading();

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    console.log('TDWP Screen Management: AJAX response:', response);

                    if (response.success) {
                        TDWP_ScreenManagement.showMessage(response.data.message, 'success');
                        $('#screen-dialog').dialog('close');
                        TDWP_ScreenManagement.refreshScreenStatus();
                    } else {
                        console.error('TDWP Screen Management: Server returned error:', response.data);
                        var errorMessage = response.data.message || 'Unknown error occurred';

                        // Enhanced error handling with field highlighting
                        if (response.data.field) {
                            // Highlight the problematic field
                            var fieldElement = $('#' + response.data.field.replace('_', '-'));
                            if (fieldElement.length) {
                                fieldElement.addClass('error');
                                setTimeout(function() {
                                    fieldElement.removeClass('error');
                                }, 3000);
                            }
                            errorMessage += ' (Field: ' + response.data.field + ')';
                        }

                        if (response.data.details) {
                            errorMessage += '\n\nDetails: ' + response.data.details;
                        }

                        // Show error in a more user-friendly way
                        TDWP_ScreenManagement.showMessage(errorMessage, 'error');

                        // Log to console for debugging
                        if (window.TDWP_DEBUG) {
                            console.group('TDWP Screen Management Error Details');
                            console.log('Error Message:', response.data.message);
                            console.log('Field:', response.data.field);
                            console.log('Details:', response.data.details);
                            console.log('Full Response:', response.data);
                            console.groupEnd();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TDWP Screen Management: AJAX error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });

                    var errorMessage = 'Server error occurred';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (xhr.responseText) {
                        try {
                            var errorData = JSON.parse(xhr.responseText);
                            if (errorData.data && errorData.data.message) {
                                errorMessage = errorData.data.message;
                            }
                        } catch (e) {
                            errorMessage = 'Server error: ' + xhr.status + ' ' + error;
                        }
                    }

                    TDWP_ScreenManagement.showMessage(errorMessage, 'error');
                },
                complete: function() {
                    TDWP_ScreenManagement.hideLoading();
                }
            });
        },

        /**
         * Delete screen
         */
        deleteScreen: function(screenId) {
            var formData = {
                action: 'tdwp_screen_management',
                sub_action: 'delete_screen',
                nonce: this.config.nonce,
                screen_id: screenId
            };

            this.showLoading();

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        TDWP_ScreenManagement.showMessage(response.data.message, 'success');
                        TDWP_ScreenManagement.refreshScreenStatus();
                    } else {
                        TDWP_ScreenManagement.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    TDWP_ScreenManagement.showMessage('Server error occurred', 'error');
                },
                complete: function() {
                    TDWP_ScreenManagement.hideLoading();
                }
            });
        },

        /**
         * Toggle screen status
         */
        toggleScreen: function(screenId, isActive) {
            var formData = {
                action: 'tdwp_screen_management',
                sub_action: 'toggle_screen',
                nonce: this.config.nonce,
                screen_id: screenId,
                is_active: isActive ? 1 : 0
            };

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        TDWP_ScreenManagement.showMessage(response.data.message, 'success');
                        TDWP_ScreenManagement.refreshScreenStatus();
                    } else {
                        TDWP_ScreenManagement.showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    TDWP_ScreenManagement.showMessage('Server error occurred', 'error');
                }
            });
        },

        /**
         * Refresh screen status
         */
        refreshScreenStatus: function() {
            if (this.state.refreshing) return;

            this.state.refreshing = true;

            var formData = {
                action: 'tdwp_screen_management',
                sub_action: 'refresh_status',
                nonce: this.config.nonce
            };

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        TDWP_ScreenManagement.state.screens = response.data.screens;
                        TDWP_ScreenManagement.state.healthStatus = response.data.health_status;
                        TDWP_ScreenManagement.updateScreensList();
                        TDWP_ScreenManagement.updateScreenStats();
                    }
                },
                error: function() {
                    console.error('Failed to refresh screen status');
                },
                complete: function() {
                    TDWP_ScreenManagement.state.refreshing = false;
                }
            });
        },

        /**
         * Refresh health status
         */
        refreshHealthStatus: function() {
            this.refreshScreenStatus(); // Health status comes with screen refresh
        },

        /**
         * Update screens list
         */
        updateScreensList: function() {
            var $container = $('#active-screens .screens-grid');
            if ($container.length === 0) return;

            $container.empty();

            if (this.state.screens.length === 0) {
                $container.html('<div class="no-screens"><p>No display screens configured.</p><button type="button" class="button button-primary" id="add-first-screen">Add Your First Screen</button></div>');
                return;
            }

            this.state.screens.forEach(function(screen) {
                var $card = TDWP_ScreenManagement.createScreenCard(screen);
                $container.append($card);
            });
        },

        /**
         * Create screen card element
         */
        createScreenCard: function(screen) {
            var statusClass = screen.is_online ? 'online' : 'offline';
            var statusText = screen.is_online ? 'Online' : 'Offline';
            var lastSeen = screen.last_ping ? TDWP_ScreenManagement.timeAgo(screen.last_ping) : 'Never';
            var previewUrl = window.location.origin + '/tdwp-display/' + screen.endpoint_url + '/';

            // Tournament status badge
            var tournamentStatusBadge = '';
            if (screen.tournament_name && screen.tournament_status) {
                tournamentStatusBadge = '<span class="tournament-status-badge ' + screen.tournament_status + '">' + screen.tournament_status + '</span>';
            }

            // Auto-assign button (only for screens without tournaments)
            var autoAssignButton = '';
            if (!screen.tournament_id) {
                autoAssignButton = '<button type="button" class="button button-secondary auto-assign-tournament">' +
                    '<span class="dashicons dashicons-clock"></span>Auto-Assign Tournament' +
                '</button>';
            }

            var $card = $('<div class="screen-card" data-screen-id="' + screen.screen_id + '">' +
                '<div class="screen-header">' +
                    '<h3 class="screen-name">' + screen.screen_name + '</h3>' +
                    '<span class="screen-status ' + statusClass + '">' + statusText + '</span>' +
                '</div>' +
                '<div class="screen-info">' +
                    '<div class="info-row"><span class="label">Type:</span><span class="value">' + screen.screen_type + '</span></div>' +
                    (screen.screen_location ? '<div class="info-row"><span class="label">Location:</span><span class="value">' + screen.screen_location + '</span></div>' : '') +
                    (screen.tournament_name ? '<div class="info-row"><span class="label">Tournament:</span><span class="value">' + screen.tournament_name + tournamentStatusBadge + '</span></div>' : '') +
                    '<div class="info-row"><span class="label">Last Seen:</span><span class="value">' + lastSeen + '</span></div>' +
                    (screen.layout_name ? '<div class="info-row"><span class="label">Layout:</span><span class="value">' + screen.layout_name + '</span></div>' : '') +
                '</div>' +
                '<div class="screen-actions">' +
                    '<button type="button" class="button preview-screen" data-preview-url="' + previewUrl + '">' +
                        '<span class="dashicons dashicons-visibility"></span>Preview' +
                    '</button>' +
                    autoAssignButton +
                    '<button type="button" class="button edit-screen">' +
                        '<span class="dashicons dashicons-edit"></span>Edit' +
                    '</button>' +
                    '<button type="button" class="button toggle-screen" data-active="' + screen.is_active + '">' +
                        '<span class="dashicons ' + (screen.is_active ? 'dashicons-pause' : 'dashicons-play') + '"></span>' +
                        (screen.is_active ? 'Pause' : 'Resume') +
                    '</button>' +
                    '<button type="button" class="button delete-screen">' +
                        '<span class="dashicons dashicons-trash"></span>Delete' +
                    '</button>' +
                '</div>' +
                (screen.screen_description ? '<div class="screen-description"><p>' + screen.screen_description + '</p></div>' : '') +
            '</div>');

            return $card;
        },

        /**
         * Update screen statistics
         */
        updateScreenStats: function() {
            var onlineCount = 0;
            var offlineCount = 0;

            this.state.screens.forEach(function(screen) {
                if (screen.is_online) {
                    onlineCount++;
                } else {
                    offlineCount++;
                }
            });

            $('.online-count').text(onlineCount);
            $('.offline-count').text(offlineCount);
            $('.total-count').text(this.state.screens.length);
        },

        /**
         * Load tournaments
         */
        loadTournaments: function() {
            var formData = {
                action: 'tdwp_screen_management',
                sub_action: 'get_tournament_options',
                nonce: this.config.nonce
            };

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        var $select = $('#tournament-id');
                        $select.find('option:not(:first)').remove();

                        response.data.forEach(function(tournament) {
                            var statusBadge = tournament.status ? ' <span class="tournament-status-indicator ' + tournament.status + '">' + tournament.status_text + '</span>' : '';
                            $select.append('<option value="' + tournament.id + '">' + tournament.title + statusBadge + '</option>');
                        });
                    }
                }
            });
        },

        /**
         * Load layouts
         */
        loadLayouts: function() {
            var formData = {
                action: 'tdwp_screen_management',
                sub_action: 'get_layouts',
                nonce: this.config.nonce
            };

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        var $select = $('#layout-id');
                        $select.find('option:not(:first)').remove();

                        response.data.forEach(function(layout) {
                            $select.append('<option value="' + layout.id + '">' + layout.name + ' (' + layout.type + ')</option>');
                        });
                    }
                }
            });
        },

        /**
         * Update form fields based on screen type
         */
        updateFormFields: function() {
            var screenType = $('#screen-type').val();
            var $tournamentField = $('#tournament-id').closest('.form-group');
            var $layoutField = $('#layout-id').closest('.form-group');

            if (screenType === 'tournament') {
                $tournamentField.show();
                $layoutField.show();
            } else if (screenType === 'leaderboard' || screenType === 'clock') {
                $tournamentField.show();
                $layoutField.hide();
            } else {
                $tournamentField.hide();
                $layoutField.hide();
            }
        },

        /**
         * Update layout options based on tournament
         */
        updateLayoutOptions: function() {
            // This could be enhanced to filter layouts by tournament type
            // For now, we'll just show all available layouts
        },

        /**
         * Assign template to screen
         */
        assignTemplateToScreen: function(templateId) {
            // Find a screen to assign this template to
            if (this.state.screens.length === 0) {
                this.showMessage('Please create a screen first', 'warning');
                return;
            }

            // For simplicity, we'll open the add screen dialog with the template selected
            this.openAddScreenDialog();
            $('#layout-id').val(templateId);
        },

        /**
         * Find screen by ID
         */
        findScreenById: function(screenId) {
            return this.state.screens.find(function(screen) {
                return screen.screen_id == screenId;
            });
        },

        /**
         * Reset form
         */
        resetForm: function() {
            $('#screen-form')[0].reset();
            $('#screen-id').val('');
            this.updateFormFields();
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('body').addClass('loading');
            if (!$('.tdwp-screen-management .spinner').length) {
                $('.tdwp-screen-management').append('<div class="spinner"></div>');
            }
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('body').removeClass('loading');
            $('.tdwp-screen-management .spinner').remove();
        },

        /**
         * Show message
         */
        showMessage: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + '"><p>' + message + '</p></div>');
            $('.tdwp-screen-management h1').after($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        /**
         * Auto-detect running tournament
         */
        autoDetectRunningTournament: function() {
            var $button = $('#auto-detect-tournament');
            var $status = $('#tournament-status');
            var $select = $('#tournament-id');

            $button.prop('disabled', true);
            $status.html('<span class="detecting">' + tdwp_screen_mgmt.auto_detect_text + '</span>');

            var formData = {
                action: 'tdwp_screen_management',
                sub_action: 'get_tournament_options',
                nonce: this.config.nonce
            };

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Clear current options
                        $select.find('option:not(:first)').remove();

                        var runningTournament = null;
                        response.data.forEach(function(tournament) {
                            var statusBadge = tournament.status ? ' <span class="tournament-status-indicator ' + tournament.status + '">' + tournament.status_text + '</span>' : '';
                            $select.append('<option value="' + tournament.id + '">' + tournament.title + statusBadge + '</option>');

                            // Find running tournament
                            if (tournament.status === 'running') {
                                runningTournament = tournament;
                            }
                        });

                        if (runningTournament) {
                            $select.val(runningTournament.id);
                            $status.html('<span class="success">Found running tournament: ' + runningTournament.title + '</span>');
                        } else {
                            $status.html('<span class="warning">' + tdwp_screen_mgmt.no_running_tournaments + '</span>');
                        }
                    } else {
                        $status.html('<span class="error">Failed to load tournaments</span>');
                    }
                },
                error: function() {
                    $status.html('<span class="error">Server error occurred</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    setTimeout(function() {
                        $status.fadeOut(function() {
                            $status.empty();
                        });
                    }, 3000);
                }
            });
        },

        /**
         * Auto-assign screen to running tournament
         */
        autoAssignScreen: function(screenId) {
            var formData = {
                action: 'tdwp_screen_management',
                sub_action: 'auto_assign_screen',
                nonce: this.config.nonce,
                screen_id: screenId
            };

            this.showLoading();

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        TDWP_ScreenManagement.showMessage(response.data.message, 'success');
                        TDWP_ScreenManagement.refreshScreenStatus();
                    } else {
                        TDWP_ScreenManagement.showMessage(response.data.message, 'warning');
                    }
                },
                error: function() {
                    TDWP_ScreenManagement.showMessage('Server error occurred', 'error');
                },
                complete: function() {
                    TDWP_ScreenManagement.hideLoading();
                }
            });
        },

        /**
         * Calculate time ago
         */
        timeAgo: function(dateString) {
            var date = new Date(dateString);
            var now = new Date();
            var diff = Math.floor((now - date) / 1000); // difference in seconds

            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return Math.floor(diff / 86400) + ' days ago';
        },

        /**
         * Debug functionality - get system information
         */
        debugSystem: function() {
            console.log('TDWP Screen Management: Requesting debug information...');

            $.ajax({
                url: this.config.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'tdwp_screen_management',
                    sub_action: 'debug_info',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.group('TDWP Screen Management - Debug Information');
                        console.log('Debug Info:', response.data.debug_info);

                        // Check critical systems
                        var debug = response.data.debug_info;
                        console.log('=== System Status ===');
                        console.log('PHP Version:', debug.php_version);
                        console.log('WordPress Version:', debug.wp_version);
                        console.log('Plugin Version:', debug.plugin_version);

                        console.log('=== Dependencies Status ===');
                        for (var dep in debug.dependencies) {
                            console.log(dep + ':', debug.dependencies[dep] ? '✓ Available' : '✗ Missing');
                        }

                        console.log('=== Database Status ===');
                        console.log('Database Prefix:', debug.database.prefix);
                        console.log('Screens Table:', debug.database.tables.screens);
                        console.log('Last Error:', debug.database.last_error || 'None');

                        console.log('=== Display Manager Status ===');
                        console.log('Status:', debug.display_manager.status);
                        if (debug.display_manager.error) {
                            console.error('Error:', debug.display_manager.error);
                        }

                        console.log('=== Endpoint Generation Test ===');
                        console.log('Input:', debug.endpoint_generation_test.input);
                        console.log('Output:', debug.endpoint_generation_test.output);
                        console.log('Success:', debug.endpoint_generation_test.success ? '✓' : '✗');

                        console.groupEnd();

                        // Show user-friendly summary
                        var summary = 'Debug information logged to console. Key findings:\n';
                        summary += '- Dependencies: ' + (Object.values(debug.dependencies).every(v => v) ? 'All OK' : 'Some missing') + '\n';
                        summary += '- Database: ' + (debug.database.tables.screens === 'exists' ? 'OK' : 'Table missing') + '\n';
                        summary += '- Display Manager: ' + (debug.display_manager.status === 'available' ? 'OK' : 'Issue detected') + '\n';
                        summary += '- Endpoint Generation: ' + (debug.endpoint_generation_test.success ? 'Working' : 'Failed') + '\n';

                        alert(summary);
                    } else {
                        console.error('Failed to get debug info:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Debug request failed:', { status: status, error: error });
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        TDWP_ScreenManagement.init();

        // Add global debug function for console access
        window.TDWP_DEBUG = window.TDWP_DEBUG || false;
        window.TDWP_DebugScreenManagement = function() {
            TDWP_ScreenManagement.debugSystem();
        };

        // Log initialization
        console.log('TDWP Screen Management: Initialized');
        console.log('TDWP Screen Management: Debug available via TDWP_DebugScreenManagement()');
    });

})(jQuery);