/**
 * Formula Manager Admin JavaScript
 *
 * @package Poker Tournament Import
 */

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
});
