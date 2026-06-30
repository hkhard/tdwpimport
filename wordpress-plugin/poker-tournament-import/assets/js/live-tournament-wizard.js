/**
 * Live Tournament Wizard JavaScript
 *
 * @package Poker_Tournament_Import
 * @since 3.1.0
 */

(function ($) {
  'use strict';

  let selectedMethod = '';

  /** Auto-save state */
  const autosave = {
    timer: null,
    changeTimer: null,
    dirty: false,
    saving: false,
    INTERVAL_MS: 30000,
    CHANGE_DEBOUNCE_MS: 2000,
  };

  $(document).ready(function () {
    initWizard();
    initDraftRestore();
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

    // Mark form dirty on any field change (for auto-save trigger)
    $('#tdwp-tournament-form').on('input change', function () {
      markDirty();
    });
  }

  // ---------------------------------------------------------------------------
  // Draft restore / crash recovery
  // ---------------------------------------------------------------------------

  /**
   * On page load: if a server-side draft exists, show the restore banner.
   */
  function initDraftRestore() {
    if (!tdwpWizard.hasDraft) {
      return;
    }

    const meta = tdwpWizard.draftMeta;
    let summary = '';
    if (meta) {
      const parts = [];
      if (meta.name) {
        parts.push('"' + meta.name + '"');
      }
      if (meta.savedAt) {
        parts.push('saved ' + meta.savedAt);
      }
      if (meta.method) {
        parts.push('(' + meta.method + ')');
      }
      if (parts.length) {
        summary = ' — ' + parts.join(', ');
      }
    }
    $('#tdwp-draft-meta-summary').text(summary);
    $('#tdwp-draft-restore-banner').show();

    $('#btn-restore-draft').on('click', function () {
      restoreDraft();
    });

    $('#btn-discard-draft').on('click', function () {
      discardDraft();
    });
  }

  /**
   * Fetch draft from server and restore form fields.
   */
  function restoreDraft() {
    $.post(
      tdwpWizard.ajaxUrl,
      {
        action: 'tdwp_wizard_get_draft',
        draft_nonce: tdwpWizard.draftNonce,
      },
      function (response) {
        if (!response.success || !response.data.draft) {
          return;
        }

        const draft = response.data.draft;

        // Determine method and show configure step.
        selectedMethod = draft.creation_method || 'blank';
        showConfigureStep();

        // Restore field values.
        applyDraftToForm(draft);

        // Hide restore banner.
        $('#tdwp-draft-restore-banner').hide();

        showNotice('success', 'Draft restored.');
      }
    );
  }

  /**
   * Apply a draft data object to all form fields.
   *
   * @param {Object} draft Saved draft from user meta.
   */
  function applyDraftToForm(draft) {
    const setVal = function (id, val) {
      if (val !== undefined && val !== null && val !== '') {
        $('#' + id).val(val);
      }
    };
    const setCheck = function (id, val) {
      $('#' + id).prop('checked', 1 === parseInt(val, 10));
    };

    setVal('tournament-name', draft.tournament_name);
    setVal('buy-in', draft.buy_in);
    setVal('starting-chips', draft.starting_chips);
    setVal('rebuy-cost', draft.rebuy_cost);
    setVal('rebuy-chips', draft.rebuy_chips);
    setVal('addon-cost', draft.addon_cost);
    setVal('addon-chips', draft.addon_chips);
    setVal('rake-percentage', draft.rake_percentage);
    setCheck('is-practice', draft.is_practice);
    setCheck('allow-reentry', draft.allow_reentry);
    setVal('reentry-cost', draft.reentry_cost);
    setVal('reentry-chips', draft.reentry_chips);
    setVal('reentry-limit', draft.reentry_limit);
    setVal('reentry-until-level', draft.reentry_until_level);
    setVal('rebuy-until-level', draft.rebuy_until_level);
    setVal('rebuy-chip-threshold', draft.rebuy_chip_threshold);
    setVal('rebuy-limit-per-player', draft.rebuy_limit_per_player);
    setVal('addon-at-level', draft.addon_at_level);
    setVal('addon-until-level', draft.addon_until_level);
    setVal('bounty-type', draft.bounty_type);
    setVal('bounty-amount', draft.bounty_amount);
    setVal('bounty-percentage', draft.bounty_percentage);
    setVal('late-reg-until-level', draft.late_reg_until_level);
    setVal('template-select', draft.template_id);
    setVal('source-tournament', draft.source_tournament_id);

    // Hidden fee-split fields.
    $('#tpl-entry-fee').val(draft.entry_fee || 0);
    $('#tpl-prize-pool-contribution').val(draft.prize_pool_contribution || 0);
    $('#tpl-rake-mode').val(draft.rake_mode || 'percentage');
    $('#tpl-rake-flat-amount').val(draft.rake_flat_amount || 0);

    // Trigger conditional UI updates.
    if (draft.allow_reentry) {
      $('.reentry-options').show();
    }
    if (draft.bounty_type && draft.bounty_type !== 'none') {
      $('.bounty-options').show();
      if ('pko' === draft.bounty_type) {
        $('.bounty-pko-only').show();
      }
    }
  }

  /**
   * Discard draft on server and hide the restore banner.
   */
  function discardDraft() {
    $.post(
      tdwpWizard.ajaxUrl,
      {
        action: 'tdwp_wizard_discard_draft',
        draft_nonce: tdwpWizard.draftNonce,
      },
      function () {
        $('#tdwp-draft-restore-banner').hide();
      }
    );
  }

  // ---------------------------------------------------------------------------
  // Auto-save
  // ---------------------------------------------------------------------------

  /**
   * Mark the form as having unsaved changes and schedule debounced + interval saves.
   */
  function markDirty() {
    autosave.dirty = true;

    // Show the indicator only when on the configure step.
    if ($('#step-configure').is(':visible')) {
      showIndicator('idle');
    }

    // Debounced save on significant change.
    clearTimeout(autosave.changeTimer);
    autosave.changeTimer = setTimeout(function () {
      if (autosave.dirty) {
        doAutosave();
      }
    }, autosave.CHANGE_DEBOUNCE_MS);
  }

  /**
   * Start the 30-second auto-save interval. Called when the configure step becomes visible.
   */
  function startAutosaveInterval() {
    stopAutosaveInterval();
    autosave.timer = setInterval(function () {
      if (autosave.dirty) {
        doAutosave();
      }
    }, autosave.INTERVAL_MS);
  }

  /**
   * Stop the auto-save interval.
   */
  function stopAutosaveInterval() {
    if (autosave.timer) {
      clearInterval(autosave.timer);
      autosave.timer = null;
    }
  }

  /**
   * Execute one auto-save POST to the server.
   */
  function doAutosave() {
    if (autosave.saving) {
      return;
    }

    const $form = $('#tdwp-tournament-form');
    if (!$form.length) {
      return;
    }

    autosave.saving = true;
    autosave.dirty = false;
    showIndicator('saving');

    const formData = new FormData($form[0]);
    formData.append('action', 'tdwp_wizard_autosave_draft');
    formData.append('draft_nonce', tdwpWizard.draftNonce);

    $.ajax({
      url: tdwpWizard.ajaxUrl,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          showIndicator('saved', response.data.saved_at);
        } else {
          showIndicator('error');
        }
      },
      error: function () {
        showIndicator('error');
      },
      complete: function () {
        autosave.saving = false;
      },
    });
  }

  /**
   * Update the auto-save status indicator.
   *
   * @param {string} state  One of: 'idle', 'saving', 'saved', 'error'.
   * @param {string} detail Optional detail string (e.g. timestamp).
   */
  function showIndicator(state, detail) {
    const $indicator = $('#tdwp-autosave-indicator');
    const $status = $('#tdwp-autosave-status');

    if ('idle' === state) {
      $indicator.show();
      $status.text('Unsaved changes…').removeClass('saving saved error').addClass('idle');
      return;
    }
    if ('saving' === state) {
      $indicator.show();
      $status.text('Saving…').removeClass('idle saved error').addClass('saving');
      return;
    }
    if ('saved' === state) {
      // Format HH:MM from "YYYY-MM-DD HH:MM:SS".
      let timeStr = '';
      if (detail) {
        const parts = detail.split(' ');
        if (parts[1]) {
          timeStr = parts[1].substring(0, 5);
        }
      }
      $indicator.show();
      $status
        .text('Saved ' + timeStr)
        .removeClass('idle saving error')
        .addClass('saved');
      return;
    }
    if ('error' === state) {
      $indicator.show();
      $status.text('Save failed').removeClass('idle saving saved').addClass('error');
    }
  }

  // ---------------------------------------------------------------------------
  // Step navigation
  // ---------------------------------------------------------------------------

  /**
   * Show method selection step
   */
  function showMethodStep() {
    stopAutosaveInterval();
    $('.tdwp-wizard-step').hide();
    $('#step-method').show();
    $('#tdwp-autosave-indicator').hide();
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

    // Start the 30s auto-save interval when the form becomes visible.
    startAutosaveInterval();
  }

  /**
   * Show success step
   */
  function showSuccessStep(data) {
    stopAutosaveInterval();
    $('.tdwp-wizard-step').hide();
    $('#step-success').show();
    $('#tdwp-autosave-indicator').hide();

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
    autosave.dirty = false;
  }

  // ---------------------------------------------------------------------------
  // Tournament creation
  // ---------------------------------------------------------------------------

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
          // Draft is discarded server-side on create; clear local state.
          autosave.dirty = false;
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

  // ---------------------------------------------------------------------------
  // Template / tournament data loaders
  // ---------------------------------------------------------------------------

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

  // ---------------------------------------------------------------------------
  // Utility
  // ---------------------------------------------------------------------------

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
