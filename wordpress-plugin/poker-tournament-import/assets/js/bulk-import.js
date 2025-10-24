/**
 * Bulk Import JavaScript
 *
 * Handles file selection, upload, sequential processing, and progress tracking.
 *
 * @package Poker_Tournament_Import
 * @since 2.9.0
 */

(function($) {
    'use strict';

    // State management
    const PokerBulkImport = {
        batchUuid: null,
        files: [],
        selectedFiles: null,
        isProcessing: false,
        isCancelled: false,
        currentFileIndex: 0,
        processedCount: 0,
        successCount: 0,
        errorCount: 0,
        skippedCount: 0,

        /**
         * Initialize bulk import functionality
         */
        init: function() {
            this.bindEvents();
            this.checkResumeState();
        },

        /**
         * Bind UI event handlers
         */
        bindEvents: function() {
            // File selection
            $('#poker-select-files').on('click', () => {
                $('#poker-tdt-files').trigger('click');
            });

            $('#poker-tdt-files').on('change', (e) => {
                this.handleFileSelection(e.target.files);
            });

            // Start upload
            $('#poker-start-upload').on('click', () => {
                this.startImport();
            });

            // Cancel upload
            $('#poker-cancel-upload').on('click', () => {
                this.cancelImport();
            });

            // Retry failed
            $('#poker-retry-failed').on('click', () => {
                this.retryFailed();
            });

            // Clear batch and start new
            $('#poker-clear-batch').on('click', () => {
                this.clearBatch();
            });

            // Import another
            $('#poker-import-another').on('click', () => {
                this.resetUI();
            });

            // Option toggles
            $('#poker-update-existing').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#poker-skip-duplicates').prop('checked', true);
                    $('#poker-import-as-new').prop('checked', false);
                }
            });

            $('#poker-import-as-new').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#poker-skip-duplicates').prop('checked', false);
                    $('#poker-update-existing').prop('checked', false);
                }
            });
        },

        /**
         * Check for resumable batch in localStorage
         */
        checkResumeState: function() {
            const storedUuid = localStorage.getItem('poker_batch_uuid');
            if (storedUuid) {
                // Check if batch exists and is incomplete
                this.fetchBatchStatus(storedUuid).then(response => {
                    if (response.status === 'processing' || response.status === 'pending') {
                        if (confirm('Resume previous import batch?')) {
                            this.resumeBatch(storedUuid, response);
                        } else {
                            localStorage.removeItem('poker_batch_uuid');
                        }
                    } else {
                        localStorage.removeItem('poker_batch_uuid');
                    }
                }).catch(() => {
                    localStorage.removeItem('poker_batch_uuid');
                });
            }
        },

        /**
         * Handle file selection
         */
        handleFileSelection: function(files) {
            if (!files || files.length === 0) {
                return;
            }

            // Validate files
            const validation = this.validateFiles(files);
            if (!validation.valid) {
                alert(validation.error);
                return;
            }

            this.selectedFiles = files;
            this.updateFileSelectionUI(files.length);
            $('#poker-start-upload').prop('disabled', false);
        },

        /**
         * Validate selected files
         */
        validateFiles: function(files) {
            const maxFiles = parseInt(pokerBulkImportSettings.maxFileUploads);
            const maxSize = parseInt(pokerBulkImportSettings.maxUploadSize);
            const postMaxSize = parseInt(pokerBulkImportSettings.postMaxSize);

            // Check file count
            if (files.length > maxFiles) {
                return {
                    valid: false,
                    error: `You can only upload ${maxFiles} files at once. Server limit: max_file_uploads.`
                };
            }

            // Check individual file sizes and total size
            let totalSize = 0;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];

                // Check extension
                if (!file.name.toLowerCase().endsWith('.tdt')) {
                    return {
                        valid: false,
                        error: `Invalid file type: ${file.name}. Only .tdt files are allowed.`
                    };
                }

                // Check individual file size
                if (file.size > maxSize) {
                    return {
                        valid: false,
                        error: `File ${file.name} exceeds maximum size of ${this.formatBytes(maxSize)}.`
                    };
                }

                totalSize += file.size;
            }

            // Check total size
            if (totalSize > postMaxSize) {
                return {
                    valid: false,
                    error: `Total file size (${this.formatBytes(totalSize)}) exceeds server limit of ${this.formatBytes(postMaxSize)}.`
                };
            }

            return { valid: true };
        },

        /**
         * Update file selection UI
         */
        updateFileSelectionUI: function(count) {
            $('#poker-files-selected').text(`${count} file(s) selected`);
        },

        /**
         * Start import process
         */
        startImport: async function() {
            if (!this.selectedFiles || this.selectedFiles.length === 0) {
                alert('Please select files first.');
                return;
            }

            this.isProcessing = true;
            this.isCancelled = false;

            // Disable controls
            $('#poker-select-files').prop('disabled', true);
            $('#poker-start-upload').prop('disabled', true);
            $('input[type=checkbox]').prop('disabled', true);

            // Show progress section
            $('#poker-progress-section').slideDown();
            $('#poker-cancel-upload').show();

            // Update progress label
            $('#poker-progress-label').text('Uploading files...');

            try {
                // Step 1: Initialize batch (upload files)
                const batchResponse = await this.initializeBatch();

                if (!batchResponse.success) {
                    throw new Error(batchResponse.error || 'Failed to initialize batch');
                }

                this.batchUuid = batchResponse.batch_uuid;
                this.files = batchResponse.files;
                $('#poker-batch-uuid').val(this.batchUuid);
                localStorage.setItem('poker_batch_uuid', this.batchUuid);

                // Display files in table
                this.renderFileTable();

                // Step 2: Process files sequentially
                await this.processFilesSequentially();

                // Step 3: Show results
                this.showResults();

            } catch (error) {
                console.error('Import error:', error);
                alert('Import failed: ' + error.message);
                this.resetUI();
            }
        },

        /**
         * Initialize batch via REST API
         */
        initializeBatch: function() {
            return new Promise((resolve, reject) => {
                const formData = new FormData();

                // Add files
                for (let i = 0; i < this.selectedFiles.length; i++) {
                    formData.append('files[]', this.selectedFiles[i]);
                }

                // Add options
                const options = {
                    skip_duplicates: $('#poker-skip-duplicates').is(':checked'),
                    update_existing: $('#poker-update-existing').is(':checked'),
                    import_as_new: $('#poker-import-as-new').is(':checked'),
                };
                formData.append('options', JSON.stringify(options));

                // AJAX request
                $.ajax({
                    url: pokerBulkImportSettings.restUrl + '/import/batch',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', pokerBulkImportSettings.nonce);
                    },
                    success: resolve,
                    error: function(xhr) {
                        reject(new Error(xhr.responseJSON?.error || 'Upload failed'));
                    }
                });
            });
        },

        /**
         * Process files sequentially (one at a time)
         */
        processFilesSequentially: async function() {
            $('#poker-progress-label').text('Processing files...');
            $('#poker-cancel-upload').show();

            this.currentFileIndex = 0;
            this.processedCount = 0;
            this.successCount = 0;
            this.errorCount = 0;
            this.skippedCount = 0;

            for (let i = 0; i < this.files.length; i++) {
                if (this.isCancelled) {
                    break;
                }

                this.currentFileIndex = i;
                const file = this.files[i];

                // Update file row to processing state
                this.updateFileRow(file.id, 'processing', 'Processing...');

                try {
                    const result = await this.processFile(file.id);

                    if (result.success) {
                        if (result.skipped) {
                            this.skippedCount++;
                            this.updateFileRow(file.id, 'skipped', result.message, result);
                        } else {
                            this.successCount++;
                            this.updateFileRow(file.id, 'completed', result.message, result);
                        }
                    } else {
                        this.errorCount++;
                        this.updateFileRow(file.id, 'failed', result.error, result);
                    }

                    this.processedCount++;
                    this.updateProgressBar();

                } catch (error) {
                    console.error('Processing error:', error);
                    this.errorCount++;
                    this.processedCount++;
                    this.updateFileRow(file.id, 'failed', error.message);
                    this.updateProgressBar();
                }

                // Small delay between files
                await this.delay(100);
            }

            $('#poker-cancel-upload').hide();
        },

        /**
         * Process a single file
         */
        processFile: function(fileId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: pokerBulkImportSettings.restUrl + '/import/batch/' + this.batchUuid + '/process?file_id=' + fileId,
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', pokerBulkImportSettings.nonce);
                    },
                    success: resolve,
                    error: function(xhr) {
                        reject(new Error(xhr.responseJSON?.error || 'Processing failed'));
                    }
                });
            });
        },

        /**
         * Fetch batch status
         */
        fetchBatchStatus: function(uuid) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: pokerBulkImportSettings.restUrl + '/import/batch/' + uuid,
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', pokerBulkImportSettings.nonce);
                    },
                    success: resolve,
                    error: reject
                });
            });
        },

        /**
         * Resume a batch
         */
        resumeBatch: function(uuid, batchData) {
            this.batchUuid = uuid;
            this.files = batchData.files;
            $('#poker-batch-uuid').val(uuid);

            // Show progress section
            $('#poker-progress-section').show();

            // Render file table
            this.renderFileTable();

            // Update progress
            this.processedCount = batchData.processed_count;
            this.successCount = batchData.success_count;
            this.errorCount = batchData.error_count;
            this.updateProgressBar();

            // If not all files processed, continue
            if (this.processedCount < this.files.length) {
                this.continueProcessing();
            } else {
                this.showResults();
            }
        },

        /**
         * Continue processing remaining files
         */
        continueProcessing: async function() {
            this.isProcessing = true;

            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];

                if (file.status === 'pending') {
                    this.updateFileRow(file.id, 'processing', 'Processing...');

                    try {
                        const result = await this.processFile(file.id);

                        if (result.success) {
                            if (result.skipped) {
                                this.skippedCount++;
                                this.updateFileRow(file.id, 'skipped', result.message, result);
                            } else {
                                this.successCount++;
                                this.updateFileRow(file.id, 'completed', result.message, result);
                            }
                        } else {
                            this.errorCount++;
                            this.updateFileRow(file.id, 'failed', result.error, result);
                        }

                        this.processedCount++;
                        this.updateProgressBar();

                    } catch (error) {
                        this.errorCount++;
                        this.processedCount++;
                        this.updateFileRow(file.id, 'failed', error.message);
                        this.updateProgressBar();
                    }

                    await this.delay(100);
                }
            }

            this.showResults();
        },

        /**
         * Render file table
         */
        renderFileTable: function() {
            const tbody = $('#poker-files-tbody');
            tbody.empty();

            this.files.forEach((file, index) => {
                const row = $('<tr></tr>')
                    .attr('data-file-id', file.id)
                    .addClass(file.status || 'pending');

                row.append($('<td></td>').text(index + 1));
                row.append($('<td></td>').text(file.filename));
                row.append($('<td></td>').text(this.formatBytes(file.filesize)));
                row.append($('<td></td>').addClass('file-status').html(this.getStatusBadge(file.status || 'pending')));
                row.append($('<td></td>').addClass('file-details').text(file.error_message || '-'));
                row.append($('<td></td>').addClass('file-time').text('-'));

                tbody.append(row);
            });
        },

        /**
         * Update file row
         */
        updateFileRow: function(fileId, status, message, details) {
            const row = $(`tr[data-file-id="${fileId}"]`);

            // Update status
            row.removeClass('pending processing completed failed skipped').addClass(status);
            row.find('.file-status').html(this.getStatusBadge(status));

            // Update details
            if (message) {
                row.find('.file-details').text(message);
            }

            // Update time
            if (details && details.processing_time_ms) {
                row.find('.file-time').text(this.formatDuration(details.processing_time_ms));
            }
        },

        /**
         * Update progress bar
         */
        updateProgressBar: function() {
            const total = this.files.length;
            const percent = Math.round((this.processedCount / total) * 100);

            $('#poker-progress-fill').css('width', percent + '%');
            $('#poker-progress-percent').text(percent + '%');

            const statsText = `${this.processedCount} of ${total} processed | ${this.successCount} succeeded | ${this.skippedCount} skipped | ${this.errorCount} failed`;
            $('#poker-progress-stats').text(statsText);

            if (this.errorCount > 0) {
                $('#poker-retry-failed').show();
            }
        },

        /**
         * Cancel import
         */
        cancelImport: async function() {
            if (!confirm('Cancel import? Files already processed will remain imported.')) {
                return;
            }

            this.isCancelled = true;
            $('#poker-cancel-upload').prop('disabled', true).text('Cancelling...');

            // Call cancel API
            try {
                await $.ajax({
                    url: pokerBulkImportSettings.restUrl + '/import/batch/' + this.batchUuid,
                    method: 'DELETE',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', pokerBulkImportSettings.nonce);
                    }
                });
            } catch (error) {
                console.error('Cancel error:', error);
            }

            $('#poker-progress-label').text('Import cancelled');
            $('#poker-cancel-upload').hide();
            $('#poker-clear-batch').show();
        },

        /**
         * Retry failed files
         */
        retryFailed: async function() {
            const failedFiles = this.files.filter(f => f.status === 'failed');

            if (failedFiles.length === 0) {
                return;
            }

            $('#poker-retry-failed').prop('disabled', true).text('Retrying...');

            for (const file of failedFiles) {
                this.updateFileRow(file.id, 'processing', 'Retrying...');

                try {
                    const result = await this.processFile(file.id);

                    if (result.success) {
                        this.successCount++;
                        this.errorCount--;
                        this.updateFileRow(file.id, 'completed', result.message, result);
                    } else {
                        this.updateFileRow(file.id, 'failed', result.error, result);
                    }

                    this.updateProgressBar();

                } catch (error) {
                    this.updateFileRow(file.id, 'failed', error.message);
                }

                await this.delay(100);
            }

            $('#poker-retry-failed').prop('disabled', false).text('Retry Failed Files');

            if (this.errorCount === 0) {
                $('#poker-retry-failed').hide();
                this.showResults();
            }
        },

        /**
         * Clear batch and reset UI
         */
        clearBatch: function() {
            localStorage.removeItem('poker_batch_uuid');
            this.resetUI();
        },

        /**
         * Show results
         */
        showResults: function() {
            this.isProcessing = false;
            localStorage.removeItem('poker_batch_uuid');

            $('#poker-progress-section').slideUp();
            $('#poker-results-section').slideDown();

            const summary = `
                <div class="poker-summary-item">
                    <strong>Total Files:</strong> ${this.files.length}
                </div>
                <div class="poker-summary-item" style="border-color: #00a32a;">
                    <strong>Successfully Imported:</strong> ${this.successCount}
                </div>
                <div class="poker-summary-item" style="border-color: #dba617;">
                    <strong>Skipped (Duplicates):</strong> ${this.skippedCount}
                </div>
                <div class="poker-summary-item" style="border-color: #d63638;">
                    <strong>Failed:</strong> ${this.errorCount}
                </div>
            `;

            $('#poker-results-summary').html(summary);
        },

        /**
         * Reset UI to initial state
         */
        resetUI: function() {
            this.batchUuid = null;
            this.files = [];
            this.selectedFiles = null;
            this.isProcessing = false;
            this.isCancelled = false;
            this.currentFileIndex = 0;
            this.processedCount = 0;
            this.successCount = 0;
            this.errorCount = 0;
            this.skippedCount = 0;

            $('#poker-tdt-files').val('');
            $('#poker-files-selected').text('');
            $('#poker-start-upload').prop('disabled', true);
            $('#poker-select-files').prop('disabled', false);
            $('input[type=checkbox]').prop('disabled', false);
            $('#poker-cancel-upload').hide();
            $('#poker-retry-failed').hide();
            $('#poker-clear-batch').hide();
            $('#poker-progress-section').hide();
            $('#poker-results-section').hide();

            $('#poker-progress-fill').css('width', '0%');
            $('#poker-progress-percent').text('0%');
            $('#poker-progress-stats').text('');
            $('#poker-files-tbody').empty();
        },

        /**
         * Get status badge HTML
         */
        getStatusBadge: function(status) {
            const labels = {
                pending: 'Pending',
                processing: '<span class="poker-spinner"></span> Processing',
                completed: 'Completed',
                failed: 'Failed',
                skipped: 'Skipped'
            };

            return `<span class="poker-status-badge poker-status-${status}">${labels[status] || status}</span>`;
        },

        /**
         * Format bytes to human readable
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        /**
         * Format duration in milliseconds
         */
        formatDuration: function(ms) {
            if (ms < 1000) {
                return ms + 'ms';
            }
            return (ms / 1000).toFixed(2) + 's';
        },

        /**
         * Delay helper
         */
        delay: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PokerBulkImport.init();
    });

})(jQuery);
