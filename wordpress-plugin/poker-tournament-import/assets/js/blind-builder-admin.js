/**
 * Blind Builder Admin JavaScript
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Blind Builder Manager
	 *
	 * @since 3.0.0
	 */
	var TDWPBlindBuilder = {
		/**
		 * Initialize
		 *
		 * @since 3.0.0
		 */
		init: function () {
			this.bindEvents();
			this.initSortable();
			this.updatePreview();
		},

		/**
		 * Bind event handlers
		 *
		 * @since 3.0.0
		 */
		bindEvents: function () {
			// Delete confirmation
			$('.submitdelete').on('click', function (e) {
				if (!confirm(tdwpBlindBuilder.i18n.confirmDelete)) {
					e.preventDefault();
					return false;
				}
			});

			// Add blind level
			$('#add-blind-level').on('click', this.addBlindLevel.bind(this));

			// Add break level
			$('#add-break-level').on('click', this.addBreakLevel.bind(this));

			// Delete level
			$(document).on('click', '.delete-level', this.deleteLevel.bind(this));

			// Save levels
			$('#save-levels').on('click', this.saveLevels.bind(this));

			// Update preview on input change
			$(document).on('input', '.level-row input', this.updatePreview.bind(this));
		},

		/**
		 * Initialize sortable functionality
		 *
		 * @since 3.0.0
		 */
		initSortable: function () {
			var self = this;

			$('#levels-sortable').sortable({
				handle: '.column-drag',
				placeholder: 'ui-sortable-placeholder',
				cursor: 'move',
				axis: 'y',
				opacity: 0.8,
				update: function () {
					self.renumberLevels();
					self.updatePreview();
				}
			});
		},

		/**
		 * Add blind level
		 *
		 * @since 3.0.0
		 */
		addBlindLevel: function () {
			var template = $('#blind-level-template').html();
			var $row = $(template);

			// Remove "no levels" message if present
			$('.no-levels').remove();

			// Append to table
			$('#levels-sortable').append($row);

			// Renumber and update preview
			this.renumberLevels();
			this.updatePreview();

			// Focus first input
			$row.find('.small-blind').focus();
		},

		/**
		 * Add break level
		 *
		 * @since 3.0.0
		 */
		addBreakLevel: function () {
			var template = $('#break-level-template').html();
			var $row = $(template);

			// Remove "no levels" message if present
			$('.no-levels').remove();

			// Append to table
			$('#levels-sortable').append($row);

			// Renumber and update preview
			this.renumberLevels();
			this.updatePreview();

			// Focus break length input
			$row.find('.break-length').focus();
		},

		/**
		 * Delete level
		 *
		 * @since 3.0.0
		 */
		deleteLevel: function (e) {
			e.preventDefault();

			if (!confirm(tdwpBlindBuilder.i18n.confirmDeleteLevel)) {
				return;
			}

			$(e.currentTarget).closest('.level-row').remove();

			// Show "no levels" message if no levels remain
			if ($('#levels-sortable .level-row').length === 0) {
				$('#levels-sortable').html('<tr class="no-levels"><td colspan="7">' +
					'No levels yet. Click "Add Blind Level" to get started.</td></tr>');
			}

			this.renumberLevels();
			this.updatePreview();
		},

		/**
		 * Renumber levels
		 *
		 * @since 3.0.0
		 */
		renumberLevels: function () {
			$('#levels-sortable .level-row').each(function (index) {
				$(this).find('.level-number').text(index + 1);
			});
		},

		/**
		 * Collect levels data
		 *
		 * @since 3.0.0
		 *
		 * @return {Array} Array of level objects
		 */
		collectLevels: function () {
			var levels = [];

			$('#levels-sortable .level-row').each(function () {
				var $row = $(this);
				var type = $row.data('level-type');

				var level = {
					level_order: $row.find('.level-number').text(),
					is_break: type === 'break' ? 1 : 0,
					duration_minutes: 15 // Default duration
				};

				if (type === 'break') {
					level.break_duration_minutes = parseInt($row.find('.break-length').val(), 10) || 15;
					level.small_blind = 0;
					level.big_blind = 0;
					level.ante = 0;
				} else {
					level.small_blind = parseInt($row.find('.small-blind').val(), 10) || 0;
					level.big_blind = parseInt($row.find('.big-blind').val(), 10) || 0;
					level.ante = parseInt($row.find('.ante').val(), 10) || 0;
					level.break_duration_minutes = 0;
				}

				levels.push(level);
			});

			return levels;
		},

		/**
		 * Save levels via AJAX
		 *
		 * @since 3.0.0
		 */
		saveLevels: function () {
			var scheduleId = $('.tdwp-level-builder').data('schedule-id');
			var levels = this.collectLevels();

			if (levels.length === 0) {
				alert('Please add at least one blind level.');
				return;
			}

			// Validate levels
			var validation = this.validateLevels(levels);
			if (!validation.valid) {
				alert(validation.message);
				return;
			}

			var $button = $('#save-levels');
			$button.addClass('is-busy').prop('disabled', true);

			$.ajax({
				url: tdwpBlindBuilder.ajaxUrl,
				type: 'POST',
				data: {
					action: 'tdwp_save_blind_levels',
					nonce: tdwpBlindBuilder.nonce,
					schedule_id: scheduleId,
					levels: JSON.stringify(levels)
				},
				success: function (response) {
					if (response.success) {
						alert(tdwpBlindBuilder.i18n.levelsSaved);
					} else {
						alert(response.data.message || tdwpBlindBuilder.i18n.errorSaving);
					}
				},
				error: function () {
					alert(tdwpBlindBuilder.i18n.errorSaving);
				},
				complete: function () {
					$button.removeClass('is-busy').prop('disabled', false);
				}
			});
		},

		/**
		 * Validate levels data
		 *
		 * @since 3.0.0
		 *
		 * @param {Array} levels Levels array
		 * @return {Object} Validation result
		 */
		validateLevels: function (levels) {
			for (var i = 0; i < levels.length; i++) {
				var level = levels[i];

				if (level.is_break === 0) {
					// Validate blind levels
					if (level.big_blind <= 0) {
						return {
							valid: false,
							message: 'Level ' + (i + 1) + ': Big blind must be greater than zero.'
						};
					}

					if (level.small_blind <= 0) {
						return {
							valid: false,
							message: 'Level ' + (i + 1) + ': Small blind must be greater than zero.'
						};
					}

					if (level.small_blind >= level.big_blind) {
						return {
							valid: false,
							message: 'Level ' + (i + 1) + ': Small blind must be less than big blind.'
						};
					}

					if (level.ante < 0) {
						return {
							valid: false,
							message: 'Level ' + (i + 1) + ': Ante cannot be negative.'
						};
					}
				} else {
					// Validate break levels
					if (level.break_duration_minutes <= 0) {
						return {
							valid: false,
							message: 'Level ' + (i + 1) + ': Break duration must be greater than zero.'
						};
					}

					if (level.break_duration_minutes > 60) {
						return {
							valid: false,
							message: 'Level ' + (i + 1) + ': Break duration cannot exceed 60 minutes.'
						};
					}
				}
			}

			return { valid: true };
		},

		/**
		 * Update schedule preview
		 *
		 * @since 3.0.0
		 */
		updatePreview: function () {
			var $preview = $('#schedule-preview');
			var levels = this.collectLevels();

			if (levels.length === 0) {
				$preview.html('<div class="preview-empty">No levels to preview. Add blind levels to see the structure.</div>');
				return;
			}

			var html = '';

			for (var i = 0; i < levels.length; i++) {
				var level = levels[i];

				if (level.is_break === 1) {
					html += '<div class="preview-level preview-break">';
					html += '<div class="preview-level-number">' + (i + 1) + '</div>';
					html += '<div class="preview-level-blinds">BREAK - ' + level.break_duration_minutes + ' minutes</div>';
					html += '<div class="preview-level-ante">&nbsp;</div>';
					html += '</div>';
				} else {
					var anteText = level.ante > 0 ? 'Ante: ' + this.formatNumber(level.ante) : 'No ante';

					html += '<div class="preview-level">';
					html += '<div class="preview-level-number">' + (i + 1) + '</div>';
					html += '<div class="preview-level-blinds">';
					html += '<strong>' + this.formatNumber(level.small_blind) + '</strong> / ';
					html += '<strong>' + this.formatNumber(level.big_blind) + '</strong>';
					html += '</div>';
					html += '<div class="preview-level-ante">' + anteText + '</div>';
					html += '</div>';
				}
			}

			$preview.html(html);
		},

		/**
		 * Format number with thousand separators
		 *
		 * @since 3.0.0
		 *
		 * @param {number} num Number to format
		 * @return {string} Formatted number
		 */
		formatNumber: function (num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function () {
		if ($('.tdwp-level-builder').length > 0) {
			TDWPBlindBuilder.init();
		}
	});

})(jQuery);
