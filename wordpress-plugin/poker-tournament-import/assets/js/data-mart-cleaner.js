/**
 * Data Mart Cleaner Admin JavaScript
 *
 * @package Poker Tournament Import
 */

jQuery(document).ready(function($) {
    var nonce = tdwpCleanerData.nonce;

    /**
     * Initialize AJAX functionality
     */
    $('.poker-ajax-button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var action = $button.data('action');
        var confirmMessage = $button.data('confirm');

        if (confirmMessage && !confirm(confirmMessage)) {
            return;
        }

        performAjaxAction($button, action);
    });

    /**
     * Perform AJAX action
     */
    function performAjaxAction($button, action) {
        var $container = $button.closest('.option-group');
        var originalText = $button.html();

        // Show loading state
        $button.addClass('poker-ajax-disabled');
        $button.html('<span class="poker-spinner"></span>' + tdwpCleanerData.i18n.processing);

        // Remove previous messages
        $('.poker-ajax-message').remove();

        var ajaxData = {
            action: 'poker_clean_data_mart',
            cleaning_action: action,
            nonce: nonce
        };

        // Special handling for migration
        if (action === 'migrate_tournaments') {
            ajaxData.action = 'poker_migrate_tournaments';
        }

        $.ajax({
            url: tdwpCleanerData.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                // Restore button
                $button.removeClass('poker-ajax-disabled');
                $button.html(originalText);

                // Show result message
                var messageClass = response.success ? 'poker-ajax-success' : 'poker-ajax-error';
                var messageHtml = '<div class="poker-ajax-message ' + messageClass + '">' +
                               response.message + '</div>';
                $container.prepend(messageHtml);

                // Update data if successful
                if (response.success && response.data) {
                    updateDataDisplays(response.data);
                }

                // Auto-hide success messages
                if (response.success) {
                    setTimeout(function() {
                        $('.poker-ajax-message').fadeOut();
                    }, 5000);
                }
            },
            error: function(xhr, status, error) {
                // Restore button
                $button.removeClass('poker-ajax-disabled');
                $button.html(originalText);

                // Show error message
                var errorMessage = tdwpCleanerData.i18n.ajaxFailed;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                var messageHtml = '<div class="poker-ajax-message poker-ajax-error">' +
                               errorMessage + '</div>';
                $container.prepend(messageHtml);
            }
        });
    }

    /**
     * Update data displays after AJAX operation
     */
    function updateDataDisplays(data) {
        // Update stats table
        if (data.stats) {
            updateStatsTable(data.stats);
        }

        // Update migration status
        if (data.migration_status) {
            updateMigrationStatus(data.migration_status);
        }
    }

    /**
     * Update statistics table
     */
    function updateStatsTable(stats) {
        $('tbody tr').each(function() {
            var $row = $(this);
            var tableCode = $row.find('td:first-child code').text();

            if (stats[tableCode]) {
                var info = stats[tableCode];
                $row.find('td:nth-child(3)').text(info.count.toLocaleString());

                var $statusCell = $row.find('td:nth-child(4) span');
                $statusCell.removeClass('status-active status-inactive');

                if (info.count > 0) {
                    $statusCell.addClass('status-active').text(tdwpCleanerData.i18n.hasData);
                } else {
                    $statusCell.addClass('status-inactive').text(tdwpCleanerData.i18n.empty);
                }
            }
        });
    }

    /**
     * Update migration status
     */
    function updateMigrationStatus(migrationStatus) {
        // Update counts
        $('.migration-status-migrated').text(migrationStatus.migrated_count.toLocaleString());
        $('.migration-status-needs-reimport').text(migrationStatus.needs_reimport_count.toLocaleString());
        $('.migration-status-total').text(migrationStatus.total_tournaments.toLocaleString());

        // Update migration button state
        var $migrateButton = $('button[data-action="migrate_tournaments"]');
        if (migrationStatus.needs_reimport_count > 0) {
            $migrateButton.prop('disabled', false);
        } else {
            $migrateButton.prop('disabled', true);
        }

        // Update migration notice
        if (migrationStatus.needs_reimport_count > 0) {
            $('.migration-notice').show();
        } else {
            $('.migration-notice').hide();
        }
    }
});
