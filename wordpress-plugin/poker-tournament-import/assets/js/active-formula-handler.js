/**
 * Active Formula Handler
 *
 * Handles client-side active formula selection and UI updates.
 * Manages AJAX requests for setting/getting the active formula.
 *
 * @package Poker Tournament Import
 * @since 3.5.0-beta42
 */

jQuery(document).ready(function($) {
    /**
     * Show admin notice
     *
     * @param {string} message Notice message
     * @param {string} type Notice type (success/error)
     */
    function showNotice(message, type) {
        type = type || 'success';
        var noticeClass = type === 'success' ? 'success' : 'error';

        var $notice = $('<div class="notice poker-formula-notice ' + noticeClass + ' is-dismissible">' +
            '<p>' + message + '</p>' +
            '</div>');

        // Remove existing notices
        $('.poker-formula-notice').remove();

        // Prepend to wrap
        $('.wrap').prepend($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Make dismissible
        $notice.on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').remove();
        });
    }

    /**
     * Set active formula via AJAX
     *
     * @param {string} formulaKey The formula key to set as active
     * @param {string} category Formula category ('tournament' or 'season')
     * @param {jQuery} $checkbox The checkbox element
     */
    function setActiveFormula(formulaKey, category, $checkbox) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_set_active_formula',
                security: pokerActiveFormulaManager.activeFormulaNonce,
                formula_key: formulaKey,
                category: category
            },
            beforeSend: function() {
                // Show loading state
                $checkbox.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    updateActiveFormulaUI(formulaKey, category);

                    // Show success message
                    showNotice(response.data.message || 'Active formula updated successfully', 'success');
                } else {
                    // Revert checkbox
                    $checkbox.prop('checked', false);
                    showNotice(response.data.message || 'Error setting active formula', 'error');
                }
            },
            error: function(xhr, status, error) {
                // Revert checkbox
                $checkbox.prop('checked', false);
                showNotice('AJAX error: ' + error, 'error');
            },
            complete: function() {
                $checkbox.prop('disabled', false);
            }
        });
    }

    /**
     * Get active formulas via AJAX
     */
    function getActiveFormulas() {
        // Get active tournament formula
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_get_active_formula',
                security: pokerActiveFormulaManager.activeFormulaNonce,
                category: 'tournament'
            },
            success: function(response) {
                if (response.success && response.data.active_formula) {
                    // Update checkboxes to match server state
                    $('.formula-active-checkbox[data-category="tournament"]').each(function() {
                        var $checkbox = $(this);
                        var formulaKey = $checkbox.data('formula-key');

                        if (formulaKey === response.data.active_formula) {
                            $checkbox.prop('checked', true);
                            // Add active class to card
                            $checkbox.closest('.formula-card').addClass('is-active-tournament');
                        } else {
                            $checkbox.prop('checked', false);
                            $checkbox.closest('.formula-card').removeClass('is-active-tournament');
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading active tournament formula:', error);
            }
        });

        // Get active season formula
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_get_active_formula',
                security: pokerActiveFormulaManager.activeFormulaNonce,
                category: 'season'
            },
            success: function(response) {
                if (response.success && response.data.active_formula) {
                    // Update checkboxes to match server state
                    $('.formula-active-checkbox[data-category="season"]').each(function() {
                        var $checkbox = $(this);
                        var formulaKey = $checkbox.data('formula-key');

                        if (formulaKey === response.data.active_formula) {
                            $checkbox.prop('checked', true);
                            // Add active class to card
                            $checkbox.closest('.formula-card').addClass('is-active-season');
                        } else {
                            $checkbox.prop('checked', false);
                            $checkbox.closest('.formula-card').removeClass('is-active-season');
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading active season formula:', error);
            }
        });
    }

    /**
     * Update UI to reflect active formula
     *
     * @param {string} formulaKey The formula key that is now active
     * @param {string} category Formula category ('tournament' or 'season')
     */
    function updateActiveFormulaUI(formulaKey, category) {
        var activeClass = category === 'season' ? 'is-active-season' : 'is-active-tournament';

        // Remove active class from all cards in this category
        $('.formula-active-checkbox[data-category="' + category + '"]').each(function() {
            var $checkbox = $(this);
            var $card = $checkbox.closest('.formula-card');

            if ($checkbox.data('formula-key') === formulaKey) {
                // Add active class to this card
                $card.addClass(activeClass);
            } else {
                // Remove active class from other cards
                $card.removeClass(activeClass);
            }
        });
    }

    /**
     * Handle checkbox change event
     */
    $(document).on('change', '.formula-active-checkbox', function(e) {
        var $checkbox = $(this);
        var category = $checkbox.data('category');
        var formulaKey = $checkbox.data('formula-key');
        var isChecked = $checkbox.prop('checked');

        if (isChecked) {
            // Uncheck all other checkboxes in the same category (mutual exclusion)
            $('.formula-active-checkbox[data-category="' + category + '"]').not($checkbox).prop('checked', false);

            // Set this formula as active
            setActiveFormula(formulaKey, category, $checkbox);
        } else {
            // Don't allow unchecking the active formula without selecting another
            // Re-check the checkbox
            $checkbox.prop('checked', true);
            showNotice('Please select a different formula instead', 'error');
        }
    });

    /**
     * Initialize: Load active formulas on page ready
     */
    if (typeof pokerActiveFormulaManager !== 'undefined' && pokerActiveFormulaManager.activeFormulaNonce) {
        getActiveFormulas();
    }
});
