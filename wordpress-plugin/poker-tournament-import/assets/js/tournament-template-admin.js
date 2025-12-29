/**
 * Tournament Template Admin JavaScript
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Tournament Template Manager
	 *
	 * @since 3.0.0
	 */
	var TDWPTemplateManager = {
		/**
		 * Initialize
		 *
		 * @since 3.0.0
		 */
		init: function () {
			this.bindEvents();
			this.validateForm();
		},

		/**
		 * Bind event handlers
		 *
		 * @since 3.0.0
		 */
		bindEvents: function () {
			// Delete confirmation
			$('.submitdelete').on('click', function (e) {
				if (!confirm(tdwpTemplates.i18n.confirmDelete)) {
					e.preventDefault();
					return false;
				}
			});

			// Form validation on submit
			$('.tdwp-template-form').on('submit', this.handleSubmit.bind(this));

			// Real-time validation for numeric fields
			$('input[type="number"]').on('input', this.validateNumericInput);
		},

		/**
		 * Validate form inputs
		 *
		 * @since 3.0.0
		 */
		validateForm: function () {
			var $form = $('.tdwp-template-form');

			if ($form.length === 0) {
				return;
			}

			// Add required field indicators
			$form.find('input[required], textarea[required], select[required]').each(function () {
				var $field = $(this);
				var $label = $('label[for="' + $field.attr('id') + '"]');

				if ($label.find('.required').length === 0) {
					$label.append(' <span class="required">*</span>');
				}
			});
		},

		/**
		 * Handle form submit
		 *
		 * @since 3.0.0
		 * @param {Event} e Submit event
		 */
		handleSubmit: function (e) {
			var $form = $(e.target);
			var $submitButton = $form.find('input[type="submit"]');
			var errors = [];

			// Validate template name
			var templateName = $form.find('input[name="name"]').val().trim();
			if (templateName === '') {
				errors.push(tdwpTemplates.i18n.error || 'Template name is required');
			}

			// Validate starting chips
			var startingChips = parseInt($form.find('input[name="starting_chips"]').val(), 10);
			if (isNaN(startingChips) || startingChips <= 0) {
				errors.push('Starting chips must be greater than zero');
			}

			// Validate rake percentage
			var rakePercentage = parseFloat($form.find('input[name="rake_percentage"]').val());
			if (isNaN(rakePercentage) || rakePercentage < 0 || rakePercentage > 100) {
				errors.push('Rake percentage must be between 0 and 100');
			}

			// Show errors if any
			if (errors.length > 0) {
				e.preventDefault();
				alert(errors.join('\n'));
				return false;
			}

			// Add loading state
			$submitButton.prop('disabled', true).addClass('tdwp-loading');

			// Form will submit normally (no AJAX needed for this implementation)
			return true;
		},

		/**
		 * Validate numeric input in real-time
		 *
		 * @since 3.0.0
		 */
		validateNumericInput: function () {
			var $input = $(this);
			var value = parseFloat($input.val());
			var min = parseFloat($input.attr('min'));
			var max = parseFloat($input.attr('max'));

			// Check min value
			if (!isNaN(min) && value < min) {
				$input.val(min);
			}

			// Check max value
			if (!isNaN(max) && value > max) {
				$input.val(max);
			}

			// Remove invalid class if value is now valid
			if (!isNaN(value)) {
				$input.removeClass('tdwp-error');
			}
		},

		/**
		 * Show success message
		 *
		 * @since 3.0.0
		 * @param {string} message Success message
		 */
		showSuccess: function (message) {
			this.showNotice(message, 'success');
		},

		/**
		 * Show error message
		 *
		 * @since 3.0.0
		 * @param {string} message Error message
		 */
		showError: function (message) {
			this.showNotice(message, 'error');
		},

		/**
		 * Show admin notice
		 *
		 * @since 3.0.0
		 * @param {string} message Notice message
		 * @param {string} type Notice type (success|error|warning|info)
		 */
		showNotice: function (message, type) {
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.wrap h1').after($notice);

			// Auto-dismiss after 5 seconds
			setTimeout(function () {
				$notice.fadeOut(function () {
					$(this).remove();
				});
			}, 5000);
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function () {
		TDWPTemplateManager.init();
	});

})(jQuery);
