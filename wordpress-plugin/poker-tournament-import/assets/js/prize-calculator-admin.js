/**
 * Prize Calculator Admin JavaScript
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Prize Calculator Manager
	 *
	 * @since 3.0.0
	 */
	var TDWPPrizeCalc = {
		/**
		 * Current place counter
		 */
		placeCounter: 1,

		/**
		 * Initialize
		 *
		 * @since 3.0.0
		 */
		init: function () {
			this.bindEvents();
			this.initPlaceCounter();
			this.updatePercentageSum();
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
				if (!confirm(tdwpPrizeCalc.i18n.confirmDelete)) {
					e.preventDefault();
					return false;
				}
			});

			// Add place
			$('#add-place-button').on('click', this.addPlace.bind(this));

			// Delete place
			$(document).on('click', '.delete-place', this.deletePlace.bind(this));

			// Percentage input change
			$(document).on('input', '.percentage-input', this.handlePercentageChange.bind(this));

			// Preset buttons
			$('[data-preset]').on('click', this.loadPreset.bind(this));

			// Calculator tool
			$('#calculate-pool-button').on('click', this.calculatePrizePool.bind(this));
			$('#structure-select').on('change', this.updatePayoutDistribution.bind(this));

			// Chop calculator
			$('#add-chop-player').on('click', this.addChopPlayer.bind(this));
			$(document).on('click', '.remove-player', this.removeChopPlayer.bind(this));
			$('#calculate-chop-button').on('click', this.calculateChop.bind(this));
		},

		/**
		 * Initialize place counter from existing places
		 *
		 * @since 3.0.0
		 */
		initPlaceCounter: function () {
			var maxPlace = 0;

			$('.place-row').each(function () {
				var place = parseInt($(this).find('.place-input').val(), 10);
				if (place > maxPlace) {
					maxPlace = place;
				}
			});

			this.placeCounter = maxPlace + 1;
		},

		/**
		 * Add new place row
		 *
		 * @since 3.0.0
		 */
		addPlace: function () {
			var template = $('#place-row-template').html();
			var placeNum = this.placeCounter;

			var $row = $(template.replace(/__PLACE__/g, placeNum).replace(/__PERCENTAGE__/g, '0'));

			$('#places-container').append($row);
			this.placeCounter++;

			this.renumberPlaces();
			this.updatePercentageSum();
			this.updatePreview();

			// Focus percentage input
			$row.find('.percentage-input').focus();
		},

		/**
		 * Delete place row
		 *
		 * @since 3.0.0
		 */
		deletePlace: function (e) {
			e.preventDefault();

			var $row = $(e.currentTarget).closest('.place-row');

			// Prevent deleting last place
			if ($('.place-row').length <= 1) {
				alert('Cannot delete the last place.');
				return;
			}

			$row.remove();

			this.renumberPlaces();
			this.updatePercentageSum();
			this.updatePreview();
		},

		/**
		 * Renumber all places sequentially
		 *
		 * @since 3.0.0
		 */
		renumberPlaces: function () {
			$('.place-row').each(function (index) {
				var placeNum = index + 1;
				$(this).find('.place-input').val(placeNum);
				$(this).find('input[name^="places"]').each(function () {
					var name = $(this).attr('name');
					var newName = name.replace(/\[\d+\]/, '[' + placeNum + ']');
					$(this).attr('name', newName);
				});
			});
		},

		/**
		 * Handle percentage input change
		 *
		 * @since 3.0.0
		 */
		handlePercentageChange: function () {
			this.updatePercentageSum();
			this.updatePreview();
		},

		/**
		 * Update percentage sum indicator
		 *
		 * @since 3.0.0
		 */
		updatePercentageSum: function () {
			var total = 0;

			$('.percentage-input').each(function () {
				var value = parseFloat($(this).val()) || 0;
				total += value;

				// Validate individual input
				if (value < 0 || value > 100) {
					$(this).addClass('invalid');
				} else {
					$(this).removeClass('invalid');
				}
			});

			$('#percentage-sum').text(total.toFixed(1));

			var $indicator = $('.percentage-sum-indicator');
			var $status = $('#sum-status');

			// Check if total is 100 (allow 0.01 rounding error)
			if (Math.abs(total - 100) <= 0.01) {
				$indicator.removeClass('invalid').addClass('valid');
				$status.removeClass('invalid').addClass('valid');
			} else {
				$indicator.removeClass('valid').addClass('invalid');
				$status.removeClass('valid').addClass('invalid');
			}
		},

		/**
		 * Update payout preview
		 *
		 * @since 3.0.0
		 */
		updatePreview: function () {
			var $container = $('#payout-preview-content');
			var examplePool = 10000;
			var html = '';

			$('.place-row').each(function () {
				var place = $(this).find('.place-input').val();
				var percentage = parseFloat($(this).find('.percentage-input').val()) || 0;
				var amount = (examplePool * (percentage / 100)).toFixed(2);

				html += '<div class="preview-payout">';
				html += '<div class="place">' + place + '</div>';
				html += '<div class="amount">' + tdwpPrizeCalc.currency + amount + '</div>';
				html += '<div class="percentage">' + percentage.toFixed(1) + '%</div>';
				html += '</div>';
			});

			$container.html(html || '<p class="tdwp-text-muted">' + 'No places configured.' + '</p>');
		},

		/**
		 * Load preset structure
		 *
		 * @since 3.0.0
		 */
		loadPreset: function (e) {
			e.preventDefault();

			var presetKey = $(e.currentTarget).data('preset');
			var preset = tdwpPrizeCalc.commonStructures[presetKey];

			if (!preset || !preset.places) {
				return;
			}

			// Clear existing places
			$('#places-container').empty();
			this.placeCounter = 1;

			// Add preset places
			var self = this;
			preset.places.forEach(function (placeData) {
				var template = $('#place-row-template').html();
				var $row = $(template.replace(/__PLACE__/g, placeData.place).replace(/__PERCENTAGE__/g, placeData.percentage));
				$('#places-container').append($row);
				self.placeCounter++;
			});

			this.updatePercentageSum();
			this.updatePreview();
		},

		/**
		 * Calculate prize pool via AJAX
		 *
		 * @since 3.0.0
		 */
		calculatePrizePool: function () {
			var $button = $('#calculate-pool-button');
			$button.addClass('is-busy').prop('disabled', true);

			var data = {
				action: 'tdwp_calculate_prize_pool',
				nonce: tdwpPrizeCalc.nonce,
				buy_in: parseFloat($('#calc-buyin').val()) || 0,
				entries: parseInt($('#calc-entries').val(), 10) || 0,
				rebuys: parseInt($('#calc-rebuys').val(), 10) || 0,
				rebuy_cost: parseFloat($('#calc-rebuy-cost').val()) || 0,
				addons: parseInt($('#calc-addons').val(), 10) || 0,
				addon_cost: parseFloat($('#calc-addon-cost').val()) || 0,
				rake_percentage: parseFloat($('#calc-rake').val()) || 0
			};

			$.ajax({
				url: tdwpPrizeCalc.ajaxUrl,
				type: 'POST',
				data: data,
				success: function (response) {
					if (response.success) {
						this.displayPoolBreakdown(response.data);
						$('#calculator-results').show();
					} else {
						alert(response.data.message || tdwpPrizeCalc.i18n.errorCalculating);
					}
				}.bind(this),
				error: function () {
					alert(tdwpPrizeCalc.i18n.errorCalculating);
				},
				complete: function () {
					$button.removeClass('is-busy').prop('disabled', false);
				}
			});
		},

		/**
		 * Display pool breakdown
		 *
		 * @since 3.0.0
		 *
		 * @param {Object} pool Pool calculation data
		 */
		displayPoolBreakdown: function (pool) {
			var html = '';
			var currency = tdwpPrizeCalc.currency;

			html += '<div class="pool-breakdown-item">';
			html += '<span class="label">Entry Pool (' + pool.entries + ' × ' + currency + pool.buy_in + '):</span>';
			html += '<span class="value">' + currency + pool.entry_pool.toFixed(2) + '</span>';
			html += '</div>';

			if (pool.rebuys > 0) {
				html += '<div class="pool-breakdown-item">';
				html += '<span class="label">Rebuy Pool (' + pool.rebuys + ' × ' + currency + pool.rebuy_cost + '):</span>';
				html += '<span class="value">' + currency + pool.rebuy_pool.toFixed(2) + '</span>';
				html += '</div>';
			}

			if (pool.addons > 0) {
				html += '<div class="pool-breakdown-item">';
				html += '<span class="label">Add-on Pool (' + pool.addons + ' × ' + currency + pool.addon_cost + '):</span>';
				html += '<span class="value">' + currency + pool.addon_pool.toFixed(2) + '</span>';
				html += '</div>';
			}

			html += '<div class="pool-breakdown-item">';
			html += '<span class="label">Gross Pool:</span>';
			html += '<span class="value">' + currency + pool.gross_pool.toFixed(2) + '</span>';
			html += '</div>';

			if (pool.rake_percentage > 0) {
				html += '<div class="pool-breakdown-item">';
				html += '<span class="label">Rake (' + pool.rake_percentage + '%):</span>';
				html += '<span class="value">-' + currency + pool.rake_amount.toFixed(2) + '</span>';
				html += '</div>';
			}

			html += '<div class="pool-breakdown-item total">';
			html += '<span class="label">Net Prize Pool:</span>';
			html += '<span class="value">' + currency + pool.net_pool.toFixed(2) + '</span>';
			html += '</div>';

			$('#pool-breakdown').html(html);

			// Store net pool for payout calculation
			this.currentNetPool = pool.net_pool;

			// Auto-update payout if structure selected
			if ($('#structure-select').val()) {
				this.updatePayoutDistribution();
			}
		},

		/**
		 * Update payout distribution table
		 *
		 * @since 3.0.0
		 */
		updatePayoutDistribution: function () {
			var structureId = $('#structure-select').val();

			if (!structureId || !this.currentNetPool) {
				$('#payout-distribution').html('');
				return;
			}

			// Get structure data (would need to fetch via AJAX in real implementation)
			// For now, calculate based on common structures
			var html = '<p>Select a structure and calculate pool to see payouts.</p>';
			$('#payout-distribution').html(html);
		},

		/**
		 * Add chop player row
		 *
		 * @since 3.0.0
		 */
		addChopPlayer: function () {
			var playerNum = $('.chop-player-row').length + 1;

			var html = '<div class="chop-player-row">';
			html += '<input type="text" class="player-name" placeholder="Player Name" value="Player ' + playerNum + '">';
			html += '<input type="number" class="player-chips" placeholder="Chips" min="0" value="10000">';
			html += '<button type="button" class="button button-small remove-player">Remove</button>';
			html += '</div>';

			$('#chop-players-container').append(html);
		},

		/**
		 * Remove chop player row
		 *
		 * @since 3.0.0
		 */
		removeChopPlayer: function (e) {
			e.preventDefault();

			if ($('.chop-player-row').length <= 1) {
				alert('Cannot remove the last player.');
				return;
			}

			$(e.currentTarget).closest('.chop-player-row').remove();
		},

		/**
		 * Calculate chop via AJAX
		 *
		 * @since 3.0.0
		 */
		calculateChop: function () {
			var $button = $('#calculate-chop-button');
			$button.addClass('is-busy').prop('disabled', true);

			var players = [];
			$('.chop-player-row').each(function () {
				players.push({
					name: $(this).find('.player-name').val(),
					chips: parseInt($(this).find('.player-chips').val(), 10) || 0
				});
			});

			var data = {
				action: 'tdwp_calculate_chop',
				nonce: tdwpPrizeCalc.nonce,
				chop_type: $('#chop-type').val(),
				remaining_pool: parseFloat($('#remaining-pool').val()) || 0,
				players: JSON.stringify(players)
			};

			$.ajax({
				url: tdwpPrizeCalc.ajaxUrl,
				type: 'POST',
				data: data,
				success: function (response) {
					if (response.success) {
						this.displayChopResults(response.data.chop);
						$('#chop-results').show();
					} else {
						alert(response.data.message || tdwpPrizeCalc.i18n.errorCalculating);
					}
				}.bind(this),
				error: function () {
					alert(tdwpPrizeCalc.i18n.errorCalculating);
				},
				complete: function () {
					$button.removeClass('is-busy').prop('disabled', false);
				}
			});
		},

		/**
		 * Display chop results
		 *
		 * @since 3.0.0
		 *
		 * @param {Object} chop Chop calculation data
		 */
		displayChopResults: function (chop) {
			var html = '';
			var currency = tdwpPrizeCalc.currency;
			var chipTotals = {};

			// Get chip counts for display
			$('.chop-player-row').each(function () {
				var name = $(this).find('.player-name').val();
				var chips = parseInt($(this).find('.player-chips').val(), 10) || 0;
				chipTotals[name] = chips;
			});

			// Sort by chop amount descending
			var sortedPlayers = Object.keys(chop).sort(function (a, b) {
				return chop[b] - chop[a];
			});

			sortedPlayers.forEach(function (player, index) {
				var amount = chop[player];
				var chips = chipTotals[player] || 0;

				html += '<div class="chop-distribution-item' + (index === 0 ? ' highlight' : '') + '">';
				html += '<div class="player-info">';
				html += '<div class="player-name">' + player + '</div>';
				html += '<div class="player-chips">' + chips.toLocaleString() + ' chips</div>';
				html += '</div>';
				html += '<div class="chop-amount">' + currency + amount.toFixed(2) + '</div>';
				html += '</div>';
			});

			$('#chop-distribution').html(html);
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function () {
		if ($('.tdwp-prize-calculator').length > 0) {
			TDWPPrizeCalc.init();
		}
	});

})(jQuery);
