/**
 * Formula Manager Admin JavaScript
 *
 * @package Poker Tournament Import
 */

/**
 * Generate formula key from display name (slugify)
 * Global function for formula key generation
 */
function slugify(text) {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')  // Special chars to underscore
        .replace(/^_+|_+$/g, '')        // Trim leading/trailing underscores
        .substring(0, 50);              // Max length
}

jQuery(document).ready(function($) {
    /**
     * Add a new dependency input field
     */
    function addDependencyField() {
        var container = $("#dependencies-container");
        var index = container.find(".dependency-item").length;
        var html = '<div class="dependency-item">' +
            '<input type="text" name="dependencies[]" placeholder="Enter dependency formula">' +
            '<button type="button" class="button remove-dependency">Remove</button>' +
            '</div>';
        container.append(html);
    }

    /**
     * Add dependency button handler
     */
    $("#add-dependency").click(function(e) {
        e.preventDefault();
        addDependencyField();
    });

    /**
     * Remove dependency button handler
     */
    $(document).on("click", ".remove-dependency", function() {
        $(this).closest(".dependency-item").remove();
    });

    /**
     * Edit formula toggle handler
     */
    $(".edit-formula").click(function(e) {
        e.preventDefault();
        var $card = $(this).closest(".formula-card");
        var key = $card.data("key");
        var $form = $("#edit-form-" + key);

        if ($form.is(":visible")) {
            $form.slideUp();
        } else {
            $(".formula-edit-form").slideUp();
            $form.slideDown();
        }
    });

    /**
     * Cancel edit button handler
     */
    $(".cancel-edit").click(function(e) {
        e.preventDefault();
        $(this).closest(".formula-edit-form").slideUp();
    });

    /**
     * Delete formula confirmation
     */
    $(".delete-formula").click(function(e) {
        if (!confirm("Are you sure you want to delete this formula?")) {
            e.preventDefault();
        }
    });

    /**
     * Open formula editor modal
     *
     * @param {string} key Formula key for edit mode, null for create mode
     */
    function openFormulaModal(key) {
        var $modal = $('#formula-editor-modal');
        var $form = $('#formula-editor-form');

        if (key && key !== 'new') {
            // Edit mode: populate from data attributes
            var $button = $('button[data-key="' + key + '"]');

            // Extract data from button data attributes
            var displayName = $button.data('display-name') || '';
            var description = $button.data('description') || '';
            var category = $button.data('category') || 'points';
            var dependencies = $button.data('dependencies') || [];
            var formula = $button.data('formula') || '';

            // Populate form fields
            $('#formula-name').val(key);
            $('#formula-display-name').val(displayName);
            $('#formula-description').val(description);
            $('#formula-category').val(category);
            $('#formula-expression').val(formula);

            // Populate dependencies textarea
            if (dependencies && dependencies.length > 0) {
                $('#formula-dependencies').val(dependencies.join('\n'));
            } else {
                $('#formula-dependencies').val('');
            }

            // Set modal title
            $('#modal-title').text('Edit Formula');

        } else {
            // Create mode: clear all fields
            $form[0].reset();
            $('#formula-name').val('');
            $('#modal-title').text('Add New Formula');
        }

        // Show modal
        $modal.show();
    }

    /**
     * Close formula editor modal
     */
    function closeFormulaModal() {
        $('#formula-editor-modal').hide();
        $('#formula-editor-form')[0].reset();
    }

    /**
     * Open variable reference modal
     */
    function openVariableReferenceModal() {
        // Show first tab by default
        $('.tab-content').hide();
        $('#var-tab-tournament').show();

        // Set first tab as active
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[data-target="var-tab-tournament"]').addClass('nav-tab-active');

        // Show modal
        $('#variable-reference-modal').show();
    }

    /**
     * Close variable reference modal
     */
    function closeVariableReferenceModal() {
        $('#variable-reference-modal').hide();
    }

    /**
     * Tab navigation handler for variable reference modal
     */
    $('.nav-tab-wrapper').on('click', '.nav-tab', function(e) {
        e.preventDefault();

        var target = $(this).data('target');

        // Hide all tab content
        $('.tab-content').hide();

        // Show target tab
        $('#' + target).show();

        // Update active class
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
    });

    /**
     * Save Formula button handler
     */
    $('#save-formula-btn').click(function(e) {
        e.preventDefault();

        // Validate required fields
        var displayName = $('#formula-display-name').val().trim();
        var category = $('#formula-category').val();
        var expression = $('#formula-expression').val().trim();

        if (!displayName) {
            alert('Display name is required');
            return;
        }

        if (!category) {
            alert('Category is required');
            return;
        }

        if (!expression) {
            alert('Expression is required');
            return;
        }

        // Collect form data
        var dependencies = $('#formula-dependencies').val().trim()
            ? $('#formula-dependencies').val().split('\n').map(s => s.trim()).filter(s => s)
            : [];

        // Auto-generate key for new formulas from display name
        var existingKey = $('#formula-name').val();
        var isNewFormula = !existingKey || existingKey === '';
        var formulaKey = isNewFormula ? slugify(displayName) : existingKey;

        // Validate that key was generated successfully
        if (!formulaKey || formulaKey === '') {
            alert('Display name must contain at least one letter or number');
            $('#save-formula-btn').prop('disabled', false);
            return;
        }

        var formData = {
            key: formulaKey,
            display_name: displayName,
            description: $('#formula-description').val(),
            category: category,
            dependencies: dependencies,
            expression: expression
        };

        // Disable button during save
        $('#save-formula-btn').prop('disabled', true);

        $.ajax({
            url: pokerFormulaManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tdwp_save_formula',
                nonce: pokerFormulaManager.nonce,
                key: formData.key,
                display_name: formData.display_name,
                description: formData.description,
                category: formData.category,
                dependencies: formData.dependencies,
                expression: formData.expression
            },
            success: function(response) {
                if (response.success) {
                    closeFormulaModal();
                    location.reload();
                } else {
                    alert(response.data || 'Save failed');
                    $('#save-formula-btn').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to save formula: ' + error);
                $('#save-formula-btn').prop('disabled', false);
            }
        });
    });

    /**
     * Test Formula button handler (placeholder)
     */
    $('#test-formula-btn').click(function(e) {
        e.preventDefault();

        var expression = $('#formula-expression').val().trim();

        if (!expression) {
            alert('Expression is required');
            return;
        }

        // Validate via AJAX (to be implemented)
        alert('Formula validation not yet implemented');
    });

    /**
     * Delete formula function
     *
     * @param {string} key Formula key to delete
     */
    function deleteFormula(key) {
        if (!confirm('Are you sure you want to delete this formula?')) {
            return;
        }

        $.ajax({
            url: pokerFormulaManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tdwp_delete_formula',
                nonce: pokerFormulaManager.nonce,
                key: key
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Delete failed');
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to delete formula: ' + error);
            }
        });
    }

    // Make functions globally available for onclick attributes
    window.openFormulaModal = openFormulaModal;
    window.closeFormulaModal = closeFormulaModal;
    window.openVariableReferenceModal = openVariableReferenceModal;
    window.closeVariableReferenceModal = closeVariableReferenceModal;
    window.deleteFormula = deleteFormula;
});
