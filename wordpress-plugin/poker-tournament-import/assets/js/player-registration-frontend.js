/**
 * Player Registration Frontend JavaScript
 *
 * @package Poker_Tournament_Import
 * @since 3.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Player Registration Manager
	 *
	 * @since 3.0.0
	 */
	var TDWPPlayerReg = {
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
			$('.player-registration-form').on('submit', this.handleSubmit.bind(this));

			// Real-time validation
			$('.player-registration-form input[required]').on('blur', this.validateField.bind(this));
		},

		/**
		 * Handle form submission
		 *
		 * @since 3.0.0
		 *
		 * @param {Event} e Submit event
		 */
		handleSubmit: function (e) {
			e.preventDefault();

			var $form = $(e.currentTarget);

			// Validate form
			if (!this.validateForm($form)) {
				return false;
			}

			// Disable form
			$form.find('.submit-button').prop('disabled', true).addClass('loading');
			$form.find('.spinner').show();
			$form.closest('.tdwp-player-registration-form').addClass('loading');

			// Hide previous messages
			$('.registration-success, .registration-error').hide();

			// Get form data
			var formData = {
				action: 'tdwp_register_player',
				nonce: $form.find('input[name="player_registration_nonce"]').val(),
				player_name: $form.find('#player_name').val(),
				player_email: $form.find('#player_email').val(),
				player_phone: $form.find('#player_phone').val(),
				player_bio: $form.find('#player_bio').val(),
				player_website: $form.find('#player_website').val() // Honeypot
			};

			// Submit via AJAX
			$.ajax({
				url: tdwpPlayerReg.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function (response) {
					if (response.success) {
						this.showSuccess(response.data.message);
						$form[0].reset();
					} else {
						this.showError(response.data.message);
					}
				}.bind(this),
				error: function () {
					this.showError(tdwpPlayerReg.i18n.errorSubmitting);
				}.bind(this),
				complete: function () {
					$form.find('.submit-button').prop('disabled', false).removeClass('loading');
					$form.find('.spinner').hide();
					$form.closest('.tdwp-player-registration-form').removeClass('loading');
				}
			});

			return false;
		},

		/**
		 * Validate entire form
		 *
		 * @since 3.0.0
		 *
		 * @param {jQuery} $form Form element
		 * @return {boolean} True if valid
		 */
		validateForm: function ($form) {
			var isValid = true;

			// Validate name
			var name = $form.find('#player_name').val().trim();
			if (!name) {
				this.showFieldError('#player_name', tdwpPlayerReg.i18n.requiredName);
				isValid = false;
			} else {
				this.clearFieldError('#player_name');
			}

			// Validate email (if required)
			var $email = $form.find('#player_email');
			if ($email.prop('required')) {
				var email = $email.val().trim();
				if (!email || !this.isValidEmail(email)) {
					this.showFieldError('#player_email', tdwpPlayerReg.i18n.requiredEmail);
					isValid = false;
				} else {
					this.clearFieldError('#player_email');
				}
			}

			return isValid;
		},

		/**
		 * Validate single field
		 *
		 * @since 3.0.0
		 *
		 * @param {Event} e Blur event
		 */
		validateField: function (e) {
			var $field = $(e.currentTarget);
			var value = $field.val().trim();

			if (!value) {
				$field.addClass('error').removeClass('success');
				return;
			}

			// Special validation for email
			if ($field.attr('type') === 'email') {
				if (this.isValidEmail(value)) {
					$field.addClass('success').removeClass('error');
				} else {
					$field.addClass('error').removeClass('success');
				}
				return;
			}

			$field.addClass('success').removeClass('error');
		},

		/**
		 * Validate email format
		 *
		 * @since 3.0.0
		 *
		 * @param {string} email Email address
		 * @return {boolean} True if valid
		 */
		isValidEmail: function (email) {
			var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return regex.test(email);
		},

		/**
		 * Show field error
		 *
		 * @since 3.0.0
		 *
		 * @param {string} fieldId Field ID
		 * @param {string} message Error message
		 */
		showFieldError: function (fieldId, message) {
			var $field = $(fieldId);
			$field.addClass('error').removeClass('success');

			// Remove existing error
			$field.next('.field-error').remove();

			// Add error message
			$field.after('<span class="field-error">' + message + '</span>');
		},

		/**
		 * Clear field error
		 *
		 * @since 3.0.0
		 *
		 * @param {string} fieldId Field ID
		 */
		clearFieldError: function (fieldId) {
			var $field = $(fieldId);
			$field.removeClass('error');
			$field.next('.field-error').remove();
		},

		/**
		 * Show success message
		 *
		 * @since 3.0.0
		 *
		 * @param {string} message Success message
		 */
		showSuccess: function (message) {
			var $success = $('.registration-success');
			$success.find('p').text(message);
			$success.slideDown();

			// Scroll to message
			$('html, body').animate({
				scrollTop: $success.offset().top - 100
			}, 500);
		},

		/**
		 * Show error message
		 *
		 * @since 3.0.0
		 *
		 * @param {string} message Error message
		 */
		showError: function (message) {
			var $error = $('.registration-error');
			$error.find('p').text(message);
			$error.slideDown();

			// Scroll to message
			$('html, body').animate({
				scrollTop: $error.offset().top - 100
			}, 500);
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function () {
		if ($('.tdwp-player-registration-form').length > 0) {
			TDWPPlayerReg.init();
		}
	});

})(jQuery);
