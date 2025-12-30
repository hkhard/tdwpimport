/**
 * Admin JavaScript for Poker Tournament Import
 * VERSION: 2.6.1
 */

// Debug wrapper - only logs when debug mode is enabled
const POKER_DEBUG = (typeof pokerImport !== 'undefined' && pokerImport.debugMode) || false;
const debugLog = function(...args) {
    if (POKER_DEBUG) {
        console.log('[Poker Debug]', ...args);
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
        console.log('=== TDWP ADMIN FILE CHANGE ===');
        const file = this.files[0];
        console.log('File selected:', file ? file.name : 'none');
        const uploadArea = $('.upload-area');
        console.log('Button element count:', $('.tdwp-import-submit').length);
        console.log('Button visible before:', $('.tdwp-import-submit').is(':visible'));

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

            // Show submit button when valid file selected
            $('.tdwp-import-submit').removeClass('tdwp-hidden');
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
