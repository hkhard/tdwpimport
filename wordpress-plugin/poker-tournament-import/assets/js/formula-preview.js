/**
 * Formula Preview Admin JavaScript
 *
 * @package Poker Tournament Import
 */

jQuery(document).ready(function($) {
    var formulaData = pokerFormulaEditor.formulas;

    /**
     * Handle formula mode changes
     */
    $("input[name='formula_mode']").change(function() {
        if ($(this).val() === 'override') {
            $("#formula-selector").slideDown();
            updateFormulaPreview();
        } else {
            $("#formula-selector").slideUp();
            $("#formula-preview-box").slideUp();
        }
    });

    /**
     * Handle formula selection changes
     */
    $("#override_formula").change(function() {
        updateFormulaPreview();
    });

    /**
     * Update formula preview display
     */
    function updateFormulaPreview() {
        var selectedKey = $("#override_formula").val();
        var formula = formulaData[selectedKey];

        if (formula) {
            $("#formula-description").text(formula.description || "No description available");

            var codeDisplay = formula.formula;
            if (formula.dependencies && formula.dependencies.length > 0) {
                codeDisplay = "// Dependencies:\n" +
                           formula.dependencies.join(";\n") + ";\n\n" +
                           "// Main formula:\n" +
                           formula.formula;
            }

            $("#formula-code").text(codeDisplay);
            $("#formula-preview-box").slideDown();
        }
    }
});
