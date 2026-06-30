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
      this.initFinancialEstimator();
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
     * Initialize the live financial estimator (tdwp-vf9).
     *
     * Mirrors TDWP_Prize_Calculator::calculate_financial_summary() in PHP so the
     * form can show estimated total pot / rake / net prize pool as fields change.
     *
     * @since 3.6.0
     */
    initFinancialEstimator: function () {
      var $summary = $('#tdwp-financial-summary');

      if ($summary.length === 0) {
        return;
      }

      var self = this;

      var selectors = [
        'input[name="entry_fee"]',
        'input[name="prize_pool_contribution"]',
        'input[name="rebuy_cost"]',
        'input[name="addon_cost"]',
        'input[name="rake_percentage"]',
        'input[name="rake_flat_amount"]',
        'input[name="rake_mode"]',
        '#tdwp-est-entries',
        '#tdwp-est-rebuys',
        '#tdwp-est-addons',
      ].join(',');

      $(document).on('input change', selectors, function () {
        self.updateFinancialSummary();
      });

      $(document).on('change', '.tdwp-rake-mode', function () {
        self.toggleRakeRows();
      });

      this.toggleRakeRows();
      this.updateFinancialSummary();
    },

    /**
     * Show/hide rake rows depending on the selected rake mode.
     *
     * @since 3.6.0
     */
    toggleRakeRows: function () {
      var mode = $('input[name="rake_mode"]:checked').val() || 'percentage';

      if ('flat' === mode) {
        $('#tdwp-rake-percentage-row').hide();
        $('#tdwp-rake-flat-row').show();
      } else {
        $('#tdwp-rake-percentage-row').show();
        $('#tdwp-rake-flat-row').hide();
      }
    },

    /**
     * Read a numeric field value, defaulting to 0.
     *
     * @since 3.6.0
     * @param {string} selector jQuery selector
     * @returns {number} Parsed value or 0
     */
    numVal: function (selector) {
      var value = parseFloat($(selector).val());
      return isNaN(value) ? 0 : value;
    },

    /**
     * Recompute and render the estimated financial summary.
     *
     * @since 3.6.0
     */
    updateFinancialSummary: function () {
      var entryFee = this.numVal('input[name="entry_fee"]');
      var contribution = this.numVal('input[name="prize_pool_contribution"]');
      var rebuyCost = this.numVal('input[name="rebuy_cost"]');
      var addonCost = this.numVal('input[name="addon_cost"]');
      var rakePercentage = this.numVal('input[name="rake_percentage"]');
      var rakeFlatAmount = this.numVal('input[name="rake_flat_amount"]');
      var rakeMode = $('input[name="rake_mode"]:checked').val() || 'percentage';

      var entries = Math.max(0, Math.floor(this.numVal('#tdwp-est-entries')));
      var rebuys = Math.max(0, Math.floor(this.numVal('#tdwp-est-rebuys')));
      var addons = Math.max(0, Math.floor(this.numVal('#tdwp-est-addons')));

      var buyIn = entryFee + contribution;
      var entryPool = contribution * entries;
      var rebuyPool = rebuyCost * rebuys;
      var addonPool = addonCost * addons;
      var grossPool = entryPool + rebuyPool + addonPool;

      var rakeAmount;
      if ('flat' === rakeMode) {
        rakeAmount = rakeFlatAmount;
      } else {
        rakeAmount = grossPool * (Math.max(0, Math.min(100, rakePercentage)) / 100);
      }

      var netPool = Math.max(0, grossPool - rakeAmount);

      var currency =
        typeof tdwpTemplates !== 'undefined' && tdwpTemplates.currency
          ? tdwpTemplates.currency
          : '$';

      function fmt(amount) {
        return currency + amount.toFixed(2);
      }

      $('#tdwp-total-buyin-display').text(fmt(buyIn));

      $('#tdwp-sum-buyin').text(fmt(buyIn));
      $('#tdwp-sum-gross').text(fmt(grossPool));
      $('#tdwp-sum-rake').text(fmt(rakeAmount));
      $('#tdwp-sum-net').text(fmt(netPool));
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
      var $notice = $(
        '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>'
      );
      $('.wrap h1').after($notice);

      // Auto-dismiss after 5 seconds
      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);
    },
  };

  /**
   * Initialize when DOM is ready
   */
  $(document).ready(function () {
    TDWPTemplateManager.init();
  });
})(jQuery);
