/**
 * Admin JavaScript for Poker Tournament Import
 */

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

    if (uploadArea.length > 0) {
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

            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = $('#tdt_file')[0];
                fileInput.files = files;
                $(fileInput).trigger('change');
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

    // Console log for debugging
    console.log('Poker Tournament Import Admin JavaScript loaded');
});