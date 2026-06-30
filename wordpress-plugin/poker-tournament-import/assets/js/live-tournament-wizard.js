/**
 * Live Tournament Wizard JavaScript
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

(function ($) {
  'use strict';

  let selectedMethod = '';

  $(document).ready(function () {
    initWizard();
  });

  /**
   * Initialize wizard
   */
  function initWizard() {
    // Method selection
    $('.tdwp-select-method').on('click', function () {
      const $card = $(this).closest('.tdwp-method-card');
      selectedMethod = $card.data('method');

      showConfigureStep();
    });

    // Back button
    $('#btn-back').on('click', function () {
      showMethodStep();
    });

    // Form submission
    $('#tdwp-tournament-form').on('submit', function (e) {
      e.preventDefault();
      createTournament();
    });

    // Template/tournament selection changes
    $('#template-select').on('change', function () {
      const templateId = $(this).val();
      if (templateId) {
        loadTemplateData(templateId);
      }
    });

    $('#source-tournament').on('change', function () {
      const tournamentId = $(this).val();
      if (tournamentId) {
        loadTournamentData(tournamentId);
      }
    });

    // Financial policy conditional fields
    $('#allow-reentry').on('change', function () {
      if ($(this).is(':checked')) {
        $('.reentry-options').show();
      } else {
        $('.reentry-options').hide();
      }
    });

    $('#bounty-type').on('change', function () {
      const bountyType = $(this).val();

      if (bountyType !== 'none') {
        $('.bounty-options').show();

        if (bountyType === 'pko') {
          $('.bounty-pko-only').show();
        } else {
          $('.bounty-pko-only').hide();
        }
      } else {
        $('.bounty-options').hide();
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
      success: function (response) {
        if (response.success) {
          showSuccessStep(response.data);
        } else {
          showNotice('error', response.data.message || 'Failed to create tournament');
        }
      },
      error: function () {
        showNotice('error', 'An error occurred. Please try again.');
      },
      complete: function () {
        $step.removeClass('loading');
        $('#btn-create').prop('disabled', false);
      },
    });
  }

  /**
   * Load template data and apply all fields to the wizard form (tdwp-l2l).
   */
  function loadTemplateData(templateId) {
    $.ajax({
      url: tdwpWizard.ajaxUrl,
      type: 'POST',
      data: {
        action: 'tdwp_get_template_data',
        nonce: tdwpWizard.nonce,
        template_id: templateId,
      },
      success: function (response) {
        if (!response.success || !response.data.template) {
          return;
        }
        applyTemplateToForm(response.data.template);
      },
    });
  }

  /**
   * Populate all wizard form fields from a template payload.
   *
   * Only overwrites a field when the template carries a meaningful value (> 0
   * for numerics, non-empty for strings).  Tournament name is pre-filled only
   * when the user hasn't typed one yet.
   *
   * @param {Object} tpl Template payload returned by ajax_get_template_data.
   */
  function applyTemplateToForm(tpl) {
    // Basic info.
    if (tpl.name && !$('#tournament-name').val()) {
      $('#tournament-name').val(tpl.name);
    }

    // Financial — visible fields.
    if (tpl.buy_in) {
      $('#buy-in').val(tpl.buy_in);
    }
    if (tpl.starting_chips) {
      $('#starting-chips').val(tpl.starting_chips);
    }
    if (tpl.rebuy_cost) {
      $('#rebuy-cost').val(tpl.rebuy_cost);
    }
    if (tpl.rebuy_chips) {
      $('#rebuy-chips').val(tpl.rebuy_chips);
    }
    if (tpl.addon_cost) {
      $('#addon-cost').val(tpl.addon_cost);
    }
    if (tpl.addon_chips) {
      $('#addon-chips').val(tpl.addon_chips);
    }
    if (tpl.rake_percentage) {
      $('#rake-percentage').val(tpl.rake_percentage);
    }

    // Rebuy timing.
    if (tpl.rebuy_until_level) {
      $('#rebuy-until-level').val(tpl.rebuy_until_level);
    }
    if (tpl.rebuy_chip_threshold) {
      $('#rebuy-chip-threshold').val(tpl.rebuy_chip_threshold);
    }
    if (tpl.rebuy_limit_per_player) {
      $('#rebuy-limit-per-player').val(tpl.rebuy_limit_per_player);
    }

    // Add-on timing.
    if (tpl.addon_at_level) {
      $('#addon-at-level').val(tpl.addon_at_level);
    }
    if (tpl.addon_until_level) {
      $('#addon-until-level').val(tpl.addon_until_level);
    }

    // Fee-split / rake-mode — carried as hidden inputs so create handler saves them.
    $('#tpl-entry-fee').val(tpl.entry_fee || 0);
    $('#tpl-prize-pool-contribution').val(tpl.prize_pool_contribution || 0);
    $('#tpl-rake-mode').val(tpl.rake_mode || 'percentage');
    $('#tpl-rake-flat-amount').val(tpl.rake_flat_amount || 0);

    // Relation notice — inform the user that a blind schedule / prize structure is linked.
    var notices = [];
    if (tpl.blind_schedule_name) {
      notices.push('Blind schedule: ' + tpl.blind_schedule_name);
    } else if (tpl.blind_schedule_id) {
      notices.push('Blind schedule #' + tpl.blind_schedule_id + ' will be applied.');
    }
    if (tpl.prize_structure_name) {
      notices.push('Prize structure: ' + tpl.prize_structure_name);
    } else if (tpl.prize_structure_id) {
      notices.push('Prize structure #' + tpl.prize_structure_id + ' will be applied.');
    }

    $('.tdwp-template-relations-notice').remove();
    if (notices.length) {
      var $notice = $('<p class="tdwp-template-relations-notice description"></p>').text(
        notices.join(' · ')
      );
      $('#template-section').append($notice);
    }
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
        tournament_id: tournamentId,
      },
      success: function (response) {
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
      },
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
    $('html, body').animate(
      {
        scrollTop: $notice.offset().top - 100,
      },
      300
    );

    // Auto-remove after 5 seconds for success messages
    if (type === 'success') {
      setTimeout(function () {
        $notice.fadeOut(300, function () {
          $(this).remove();
        });
      }, 5000);
    }
  }
})(jQuery);
