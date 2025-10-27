/**
 * Player Management Admin JavaScript
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Player Management Manager
	 *
	 * @since 3.0.0
	 */
	var TDWPPlayerMgmt = {
		/**
		 * Cached import data
		 */
		importData: null,

		/**
		 * Initialize
		 *
		 * @since 3.0.0
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 *
		 * @since 3.0.0
		 */
		bindEvents: function () {
			// Delete player
			$(document).on('click', '.delete-player', this.deletePlayer.bind(this));

			// Import preview
			$('#preview-import-button').on('click', this.previewImport.bind(this));

			// Import execute
			$('#import-button').on('click', this.executeImport.bind(this));

			// Search players
			$('.tdwp-list-search input[type="text"]').on('keyup', this.debounce(this.searchPlayers.bind(this), 300));
		},

		/**
		 * Delete player with confirmation
		 *
		 * @since 3.0.0
		 *
		 * @param {Event} e Click event
		 */
		deletePlayer: function (e) {
			e.preventDefault();

			if (!confirm(tdwpPlayerMgmt.i18n.confirmDelete)) {
				return;
			}

			var $button = $(e.currentTarget);
			var playerId = $button.data('player-id');

			$button.prop('disabled', true).addClass('is-busy');

			$.ajax({
				url: tdwpPlayerMgmt.ajaxUrl,
				type: 'POST',
				data: {
					action: 'tdwp_delete_player',
					nonce: tdwpPlayerMgmt.nonce,
					player_id: playerId
				},
				success: function (response) {
					if (response.success) {
						// Remove row from table
						$button.closest('tr').fadeOut(function () {
							$(this).remove();

							// Check if table is empty
							if ($('.wp-list-table tbody tr').length === 0) {
								location.reload();
							}
						});
					} else {
						alert(response.data.message || tdwpPlayerMgmt.i18n.errorDeleting);
						$button.prop('disabled', false).removeClass('is-busy');
					}
				},
				error: function () {
					alert(tdwpPlayerMgmt.i18n.errorDeleting);
					$button.prop('disabled', false).removeClass('is-busy');
				}
			});
		},

		/**
		 * Preview import from file
		 *
		 * @since 3.0.0
		 */
		previewImport: function () {
			var fileInput = $('#import_file')[0];

			if (!fileInput.files.length) {
				alert(tdwpPlayerMgmt.i18n.noFileSelected);
				return;
			}

			var formData = new FormData();
			formData.append('action', 'tdwp_import_players');
			formData.append('nonce', $('#player-import-form input[name="import_nonce"]').val());
			formData.append('import_action', 'preview');
			formData.append('import_file', fileInput.files[0]);

			var $button = $('#preview-import-button');
			$button.prop('disabled', true).addClass('is-busy');

			$('#import-preview').hide();
			$('#import-results').hide();

			$.ajax({
				url: tdwpPlayerMgmt.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						this.importData = response.data;
						this.displayImportPreview(response.data);
						$('#import-button').prop('disabled', false);
					} else {
						alert(response.data.message || tdwpPlayerMgmt.i18n.errorImporting);
					}
				}.bind(this),
				error: function () {
					alert(tdwpPlayerMgmt.i18n.errorImporting);
				},
				complete: function () {
					$button.prop('disabled', false).removeClass('is-busy');
				}
			});
		},

		/**
		 * Display import preview
		 *
		 * @since 3.0.0
		 *
		 * @param {Object} data Import data
		 */
		displayImportPreview: function (data) {
			var html = '';

			// Summary stats
			html += '<div class="import-preview-summary">';
			html += '<div class="preview-stat">';
			html += '<div class="stat-value">' + data.total + '</div>';
			html += '<div class="stat-label">Total Rows</div>';
			html += '</div>';
			html += '<div class="preview-stat valid">';
			html += '<div class="stat-value">' + data.valid + '</div>';
			html += '<div class="stat-label">Valid</div>';
			html += '</div>';
			html += '<div class="preview-stat invalid">';
			html += '<div class="stat-value">' + data.invalid + '</div>';
			html += '<div class="stat-label">Invalid</div>';
			html += '</div>';
			html += '</div>';

			// Errors if any
			if (data.errors && data.errors.length > 0) {
				html += '<div class="import-errors">';
				html += '<h4>Errors Found:</h4>';
				html += '<ul>';
				data.errors.forEach(function (error) {
					html += '<li>';
					html += '<span class="row-number">Row ' + error.row + ':</span> ';
					html += error.message;
					html += '</li>';
				});
				html += '</ul>';
				html += '</div>';
			}

			// Duplicate warnings
			var duplicates = data.players.filter(function (player) {
				return player.duplicate !== false;
			});

			if (duplicates.length > 0) {
				html += '<div class="import-warnings">';
				html += '<h4>Duplicates Found (' + duplicates.length + '):</h4>';
				html += '<p>Based on your duplicate handling setting, these will be skipped or updated.</p>';
				html += '</div>';
			}

			$('#import-preview-content').html(html);
			$('#import-preview').slideDown();
		},

		/**
		 * Execute import
		 *
		 * @since 3.0.0
		 */
		executeImport: function () {
			if (!this.importData || !this.importData.players) {
				alert('No import data. Please preview first.');
				return;
			}

			if (!confirm('Import ' + this.importData.valid + ' players?')) {
				return;
			}

			var $button = $('#import-button');
			$button.prop('disabled', true).addClass('is-busy');

			$.ajax({
				url: tdwpPlayerMgmt.ajaxUrl,
				type: 'POST',
				data: {
					action: 'tdwp_import_players',
					nonce: $('#player-import-form input[name="import_nonce"]').val(),
					import_action: 'import',
					players: JSON.stringify(this.importData.players),
					duplicate_handling: $('#duplicate_handling').val(),
					import_status: $('#import_status').val()
				},
				success: function (response) {
					if (response.success) {
						this.displayImportResults(response.data);
					} else {
						alert(response.data.message || tdwpPlayerMgmt.i18n.errorImporting);
					}
				}.bind(this),
				error: function () {
					alert(tdwpPlayerMgmt.i18n.errorImporting);
				},
				complete: function () {
					$button.prop('disabled', false).removeClass('is-busy');
				}
			});
		},

		/**
		 * Display import results
		 *
		 * @since 3.0.0
		 *
		 * @param {Object} results Import results
		 */
		displayImportResults: function (results) {
			var html = '';

			html += '<div class="import-success">';
			html += '<h4>Import Complete!</h4>';
			html += '<div class="import-stats">';
			html += '<p><strong>Created:</strong> ' + results.created + ' players</p>';
			html += '<p><strong>Updated:</strong> ' + results.updated + ' players</p>';
			html += '<p><strong>Skipped:</strong> ' + results.skipped + ' players</p>';
			html += '<p><strong>Failed:</strong> ' + results.failed + ' players</p>';
			html += '</div>';
			html += '</div>';

			if (results.errors && results.errors.length > 0) {
				html += '<div class="import-errors">';
				html += '<h4>Errors:</h4>';
				html += '<ul>';
				results.errors.forEach(function (error) {
					html += '<li>';
					html += '<span class="row-number">Row ' + error.row + ':</span> ';
					html += error.message;
					html += '</li>';
				});
				html += '</ul>';
				html += '</div>';
			}

			html += '<p><a href="' + window.location.href.split('?')[0] + '?post_type=tournament&page=tdwp-player-management&tab=list" class="button button-primary">View Players List</a></p>';

			$('#import-results-content').html(html);
			$('#import-preview').slideUp();
			$('#import-results').slideDown();

			// Clear form
			$('#player-import-form')[0].reset();
			this.importData = null;
			$('#import-button').prop('disabled', true);
		},

		/**
		 * Search players (for autocomplete)
		 *
		 * @since 3.0.0
		 */
		searchPlayers: function () {
			var term = $('.tdwp-list-search input[type="text"]').val();

			if (term.length < 2) {
				return;
			}

			// This would be used for autocomplete if implemented
			// For now, just submit the form
		},

		/**
		 * Debounce helper
		 *
		 * @since 3.0.0
		 *
		 * @param {Function} func Function to debounce
		 * @param {number} wait Wait time in ms
		 * @return {Function} Debounced function
		 */
		debounce: function (func, wait) {
			var timeout;
			return function () {
				var context = this;
				var args = arguments;
				clearTimeout(timeout);
				timeout = setTimeout(function () {
					func.apply(context, args);
				}, wait);
			};
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function () {
		if ($('.tdwp-player-management').length > 0) {
			TDWPPlayerMgmt.init();
		}
	});

})(jQuery);
