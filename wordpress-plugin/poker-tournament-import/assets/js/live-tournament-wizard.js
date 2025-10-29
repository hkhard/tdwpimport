/**
 * Live Tournament Wizard JavaScript
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

(function($) {
	'use strict';

	let selectedMethod = '';

	$(document).ready(function() {
		initWizard();
	});

	/**
	 * Initialize wizard
	 */
	function initWizard() {
		// Method selection
		$('.tdwp-select-method').on('click', function() {
			const $card = $(this).closest('.tdwp-method-card');
			selectedMethod = $card.data('method');

			showConfigureStep();
		});

		// Back button
		$('#btn-back').on('click', function() {
			showMethodStep();
		});

		// Form submission
		$('#tdwp-tournament-form').on('submit', function(e) {
			e.preventDefault();
			createTournament();
		});

		// Template/tournament selection changes
		$('#template-select').on('change', function() {
			const templateId = $(this).val();
			if (templateId) {
				loadTemplateData(templateId);
			}
		});

		$('#source-tournament').on('change', function() {
			const tournamentId = $(this).val();
			if (tournamentId) {
				loadTournamentData(tournamentId);
			}
		});
	}

	/**
	 * Show method selection step
	 */
	function showMethodStep() {
		$('.tdwp-wizard-step').hide();
		$('#step-method').show();
		resetForm();
	}

	/**
	 * Show configure step
	 */
	function showConfigureStep() {
		$('.tdwp-wizard-step').hide();
		$('#step-configure').show();

		// Set creation method
		$('#creation-method').val(selectedMethod);

		// Show/hide relevant sections
		$('#template-section, #copy-section').hide();

		if (selectedMethod === 'template') {
			$('#template-section').show();
		} else if (selectedMethod === 'copy') {
			$('#copy-section').show();
		}
	}

	/**
	 * Show success step
	 */
	function showSuccessStep(data) {
		$('.tdwp-wizard-step').hide();
		$('#step-success').show();

		$('#success-message').text(data.message || 'Tournament created successfully!');
		$('#btn-manage').attr('href', data.manage_url);
	}

	/**
	 * Reset form
	 */
	function resetForm() {
		$('#tdwp-tournament-form')[0].reset();
		selectedMethod = '';
		$('#creation-method').val('');
	}

	/**
	 * Create tournament via AJAX
	 */
	function createTournament() {
		const $form = $('#tdwp-tournament-form');
		const $step = $('#step-configure');

		// Validate
		const tournamentName = $('#tournament-name').val().trim();
		if (!tournamentName) {
			showNotice('error', 'Tournament name is required');
			return;
		}

		// Show loading
		$step.addClass('loading');
		$('#btn-create').prop('disabled', true);

		// Prepare form data
		const formData = new FormData($form[0]);
		formData.append('action', 'tdwp_create_live_tournament');
		formData.append('nonce', tdwpWizard.nonce);

		// Submit
		$.ajax({
			url: tdwpWizard.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					showSuccessStep(response.data);
				} else {
					showNotice('error', response.data.message || 'Failed to create tournament');
				}
			},
			error: function() {
				showNotice('error', 'An error occurred. Please try again.');
			},
			complete: function() {
				$step.removeClass('loading');
				$('#btn-create').prop('disabled', false);
			}
		});
	}

	/**
	 * Load template data
	 */
	function loadTemplateData(templateId) {
		$.ajax({
			url: tdwpWizard.ajaxUrl,
			type: 'POST',
			data: {
				action: 'tdwp_get_template_data',
				nonce: tdwpWizard.nonce,
				template_id: templateId
			},
			success: function(response) {
				if (response.success && response.data.template) {
					const template = response.data.template;

					// Pre-fill form fields
					if (template.buy_in) {
						$('#buy-in').val(template.buy_in);
					}
					if (template.starting_chips) {
						$('#starting-chips').val(template.starting_chips);
					}
					if (template.name && !$('#tournament-name').val()) {
						$('#tournament-name').val(template.name);
					}
				}
			}
		});
	}

	/**
	 * Load tournament data
	 */
	function loadTournamentData(tournamentId) {
		$.ajax({
			url: tdwpWizard.ajaxUrl,
			type: 'POST',
			data: {
				action: 'tdwp_get_tournament_data',
				nonce: tdwpWizard.nonce,
				tournament_id: tournamentId
			},
			success: function(response) {
				if (response.success) {
					const tournament = response.data.tournament;
					const meta = response.data.meta;

					// Pre-fill form fields
					if (meta.buy_in) {
						$('#buy-in').val(meta.buy_in);
					}
					if (meta.starting_chips) {
						$('#starting-chips').val(meta.starting_chips);
					}
					if (tournament.post_title && !$('#tournament-name').val()) {
						$('#tournament-name').val(tournament.post_title + ' (Copy)');
					}
				}
			}
		});
	}

	/**
	 * Show notice message
	 */
	function showNotice(type, message) {
		const $notice = $('<div class="tdwp-wizard-notice ' + type + '">' + message + '</div>');

		// Remove existing notices
		$('.tdwp-wizard-notice').remove();

		// Add notice
		$('#tdwp-tournament-form').before($notice);

		// Scroll to notice
		$('html, body').animate({
			scrollTop: $notice.offset().top - 100
		}, 300);

		// Auto-remove after 5 seconds for success messages
		if (type === 'success') {
			setTimeout(function() {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		}
	}

})(jQuery);
