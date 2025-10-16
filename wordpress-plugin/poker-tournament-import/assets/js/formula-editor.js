/**
 * Formula Editor with Autocomplete
 * Provides intelligent formula editing for Tournament Director formulas
 * VERSION: 2.4.0
 */

jQuery(document).ready(function($) {
    'use strict';

    console.log('Formula Editor v2.4.0 loaded');

    // Tournament Director Variables (145+ variables)
    const TD_VARIABLES = {
        // Tournament Information Variables
        'addOnsAllowed': 'bool - True if add-ons are currently allowed',
        'addOnsLastRound': 'int - The last (final) round that add-ons are allowed',
        'ante': 'decimal - The amount of the ante in the current round',
        'bigBlind': 'decimal - The amount of the big blind',
        'bountyTotal': 'decimal - Total bounty amount in the tournament',
        'breakNum': 'int - The current break number',
        'buyins': 'int - Number of players that have bought-in (alias: n)',
        'n': 'int - Total number of players (alias for buyins)',
        'chipCount': 'decimal - Total chips in play',
        'clockPaused': 'bool - True if tournament clock is paused',
        'defaultBuyinChips': 'decimal - Default starting chip count',
        'defaultBuyinFee': 'decimal - Default buy-in fee amount',
        'fixedRake': 'decimal - Fixed rake amount',
        'guaranteedPot': 'decimal - Guaranteed prize pool amount',
        'inTheMoneyRank': 'int - Last position that gets paid',
        'level': 'int - Current blind level number',
        'playersLeft': 'int - Number of players remaining',
        'pot': 'decimal - Total prize pool amount',
        'rebuys': 'int - Total number of rebuys',
        'state': 'int - Tournament state (0=pending, 1=running, 2=paused, 3=ended)',
        'tablesLeft': 'int - Number of tables still in play',
        'time': 'int - Time in milliseconds since tournament start',
        'totalAddOns': 'int - Total number of add-ons purchased',
        'totalBuyinsAmount': 'decimal - Total money collected from buy-ins',
        'totalRebuysAmount': 'decimal - Total money collected from rebuys',
        'totalAddOnsAmount': 'decimal - Total money collected from add-ons',

        // Player Information Variables
        'rank': 'int - The rank of a player (alias: r)',
        'r': 'int - Player rank (alias for rank)',
        'numberOfHits': 'int - Number of hits a player has made (alias: hits)',
        'hits': 'int - Number of knockouts by player',
        'chipStack': 'decimal - Player\'s current chip stack',
        'finalTable': 'bool - True if player reached final table',
        'inTheMoney': 'bool - True if player finished in the money',
        'roundOut': 'int - Round number when player was eliminated',
        'take': 'decimal - Amount player won from tournament',
        'totalWinnings': 'decimal - Player\'s total winnings (alias: winnings)',
        'winnings': 'decimal - Player\'s total winnings',

        // Legacy/Calculated Variables
        'monies': 'decimal - Total tournament money (calculated)',
        'avgBC': 'decimal - Average buy-in cost (calculated)',
        'T33': 'int - One-third of total players (calculated)',
        'T80': 'int - 80% of total players (calculated)',
        'points': 'decimal - Calculated tournament points',
        'temp': 'decimal - Temporary variable for calculations'
    };

    // Tournament Director Functions (43+ functions)
    const TD_FUNCTIONS = {
        // Mathematical Functions
        'abs': 'abs(x) - Returns the absolute value of x',
        'sqrt': 'sqrt(x) - Returns the square root of x',
        'pow': 'pow(base, exponent) - Returns base raised to exponent',
        'power': 'power(base, exponent) - Same as pow()',
        'log': 'log(x[, base]) - Natural logarithm or logarithm to specified base',
        'ln': 'ln(x) - Natural logarithm (base e)',
        'log10': 'log10(x) - Base-10 logarithm',
        'exp': 'exp(x) - Returns e raised to the power of x',
        'sin': 'sin(x) - Sine of x (in radians)',
        'cos': 'cos(x) - Cosine of x (in radians)',
        'tan': 'tan(x) - Tangent of x (in radians)',
        'asin': 'asin(x) - Arc sine of x',
        'acos': 'acos(x) - Arc cosine of x',
        'atan': 'atan(x) - Arc tangent of x',
        'triangle': 'triangle(n) - Triangle number: n*(n+1)/2',
        'random': 'random([min, max]) - Random number between min and max',

        // Rounding Functions
        'round': 'round(x[, decimals]) - Round to nearest integer or decimal places',
        'ceil': 'ceil(x) - Round up to next integer',
        'floor': 'floor(x) - Round down to previous integer',
        'roundUpToNearest': 'roundUpToNearest(value, multiple) - Round up to nearest multiple',
        'roundToNearest': 'roundToNearest(value, multiple) - Round to nearest multiple',
        'roundDownToNearest': 'roundDownToNearest(value, multiple) - Round down to nearest multiple',

        // Conditional Functions
        'if': 'if(condition, trueValue, falseValue) - Conditional expression',
        'switch': 'switch(value, compareVal1, result1, ..., default) - Multiple condition matching',
        'lswitch': 'lswitch(index, val1, val2, ..., default) - Linear switch by index (1-based)',

        // Logical Functions
        'and': 'and(condition1, condition2, ...) - Logical AND',
        'or': 'or(condition1, condition2, ...) - Logical OR',
        'not': 'not(condition) - Logical NOT',

        // List/Array Functions
        'sum': 'sum(val1, val2, ...) - Sum of all arguments',
        'product': 'product(val1, val2, ...) - Product of all arguments',
        'average': 'average(val1, val2, ...) - Average of all arguments',
        'count': 'count(val1, val2, ...) - Count of arguments',
        'max': 'max(val1, val2, ...) - Maximum value',
        'min': 'min(val1, val2, ...) - Minimum value',
        'top': 'top(n, val1, val2, ...) - Return top n values',
        'bottom': 'bottom(n, val1, val2, ...) - Return bottom n values',
        'oneof': 'oneof(searchValue, val1, val2, ...) - Check if searchValue is in list',
        'index': 'index(n, val1, val2, ...) - Get nth value from list (1-based)',

        // Assignment Function
        'assign': 'assign("varName", value) - Assign value to variable',

        // Profile Functions
        'buyinProfileFee': 'buyinProfileFee(profileName) - Get fee for buy-in profile',
        'buyinProfileRake': 'buyinProfileRake(profileName) - Get rake for buy-in profile',
        'buyinProfileChips': 'buyinProfileChips(profileName) - Get chips for buy-in profile',
        'buyinProfilePoints': 'buyinProfilePoints(profileName) - Get points for buy-in profile',

        // Special Functions
        'totalForRake': 'totalForRake() - Calculate total rake amount'
    };

    // Operators
    const TD_OPERATORS = [
        '+', '-', '*', '/', '%', '^',
        '==', '!=', '<', '>', '<=', '>=',
        '&&', '||', '!',
        '(', ')', ',', ';', '?', ':'
    ];

    /**
     * Initialize formula editor on textarea
     */
    function initFormulaEditor($textarea) {
        const editorId = $textarea.attr('id');
        const $wrapper = $('<div class="formula-editor-wrapper"></div>');
        const $container = $('<div class="formula-editor-container"></div>');
        const $toolbar = createToolbar();
        const $autocomplete = createAutocompleteBox();
        const $syntaxHighlight = $('<div class="formula-syntax-highlight"></div>');
        const $validation = $('<div class="formula-validation"></div>');

        // Wrap textarea
        $textarea.wrap($wrapper);
        $textarea.before($toolbar);
        $textarea.before($syntaxHighlight);
        $textarea.after($autocomplete);
        $textarea.after($validation);
        $textarea.addClass('formula-input');

        // Store references
        $textarea.data('autocomplete', $autocomplete);
        $textarea.data('validation', $validation);
        $textarea.data('syntaxHighlight', $syntaxHighlight);

        // Bind events
        bindFormulaEditorEvents($textarea);

        // Initial syntax highlighting
        updateSyntaxHighlight($textarea);

        // Initial validation
        validateFormula($textarea);

        console.log('Formula editor initialized for:', editorId);
    }

    /**
     * Create toolbar with common functions and variables
     */
    function createToolbar() {
        const $toolbar = $('<div class="formula-toolbar"></div>');

        // Add quick insert buttons
        const commonFunctions = [
            { name: 'if', template: 'if(condition, trueValue, falseValue)' },
            { name: 'round', template: 'round(value)' },
            { name: 'sqrt', template: 'sqrt(value)' },
            { name: 'pow', template: 'pow(base, exponent)' },
            { name: 'assign', template: 'assign("varName", value)' },
            { name: 'sum', template: 'sum(val1, val2)' },
            { name: 'average', template: 'average(val1, val2)' }
        ];

        $toolbar.append('<div class="toolbar-section"><span class="toolbar-label">Quick Insert:</span></div>');

        const $quickInsert = $('<div class="toolbar-buttons"></div>');
        commonFunctions.forEach(func => {
            const $btn = $('<button type="button" class="button button-small formula-insert-btn"></button>');
            $btn.text(func.name);
            $btn.data('template', func.template);
            $quickInsert.append($btn);
        });

        $toolbar.append($quickInsert);

        // Add variables dropdown
        const $varsSection = $('<div class="toolbar-section"></div>');
        const $varsDropdown = $('<select class="formula-vars-dropdown"></select>');
        $varsDropdown.append('<option value="">Insert Variable...</option>');

        Object.keys(TD_VARIABLES).forEach(varName => {
            const $option = $('<option></option>');
            $option.val(varName);
            $option.text(varName + ' - ' + TD_VARIABLES[varName].split(' - ')[1]);
            $varsDropdown.append($option);
        });

        $varsSection.append($varsDropdown);
        $toolbar.append($varsSection);

        // Add functions dropdown
        const $funcsSection = $('<div class="toolbar-section"></div>');
        const $funcsDropdown = $('<select class="formula-funcs-dropdown"></select>');
        $funcsDropdown.append('<option value="">Insert Function...</option>');

        Object.keys(TD_FUNCTIONS).forEach(funcName => {
            const $option = $('<option></option>');
            $option.val(funcName);
            $option.text(TD_FUNCTIONS[funcName]);
            $funcsDropdown.append($option);
        });

        $funcsSection.append($funcsDropdown);
        $toolbar.append($funcsSection);

        // Add help button
        const $helpBtn = $('<button type="button" class="button button-small formula-help-btn">📘 Help</button>');
        $toolbar.append($helpBtn);

        return $toolbar;
    }

    /**
     * Create autocomplete dropdown box
     */
    function createAutocompleteBox() {
        const $box = $('<div class="formula-autocomplete"></div>');
        $box.hide();
        return $box;
    }

    /**
     * Bind all formula editor events
     */
    function bindFormulaEditorEvents($textarea) {
        const $autocomplete = $textarea.data('autocomplete');
        let currentSuggestions = [];
        let selectedIndex = -1;

        // Input event for autocomplete and syntax highlighting
        $textarea.on('input', function() {
            const cursorPos = this.selectionStart;
            const text = $(this).val();
            const textBeforeCursor = text.substring(0, cursorPos);

            // Update syntax highlighting
            updateSyntaxHighlight($textarea);

            // Show autocomplete
            const match = textBeforeCursor.match(/([a-zA-Z_][a-zA-Z0-9_]*)$/);
            if (match) {
                const partial = match[1];
                currentSuggestions = getSuggestions(partial);

                if (currentSuggestions.length > 0) {
                    showAutocomplete($textarea, currentSuggestions);
                    selectedIndex = 0;
                } else {
                    hideAutocomplete($textarea);
                }
            } else {
                hideAutocomplete($textarea);
            }

            // Validate formula (debounced)
            clearTimeout($(this).data('validateTimeout'));
            const timeout = setTimeout(() => validateFormula($textarea), 500);
            $(this).data('validateTimeout', timeout);
        });

        // Keydown event for autocomplete navigation
        $textarea.on('keydown', function(e) {
            if ($autocomplete.is(':visible')) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, currentSuggestions.length - 1);
                    highlightSuggestion($autocomplete, selectedIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, 0);
                    highlightSuggestion($autocomplete, selectedIndex);
                } else if (e.key === 'Enter' || e.key === 'Tab') {
                    if (selectedIndex >= 0 && selectedIndex < currentSuggestions.length) {
                        e.preventDefault();
                        insertSuggestion($textarea, currentSuggestions[selectedIndex]);
                        hideAutocomplete($textarea);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    hideAutocomplete($textarea);
                }
            }
        });

        // Click event for autocomplete items
        $autocomplete.on('click', '.autocomplete-item', function() {
            const suggestion = $(this).data('suggestion');
            insertSuggestion($textarea, suggestion);
            hideAutocomplete($textarea);
            $textarea.focus();
        });

        // Toolbar button events
        $textarea.closest('.formula-editor-wrapper').find('.formula-insert-btn').on('click', function() {
            const template = $(this).data('template');
            insertAtCursor($textarea, template);
            $textarea.focus();
        });

        // Variables dropdown
        $textarea.closest('.formula-editor-wrapper').find('.formula-vars-dropdown').on('change', function() {
            const varName = $(this).val();
            if (varName) {
                insertAtCursor($textarea, varName);
                $(this).val('');
                $textarea.focus();
            }
        });

        // Functions dropdown
        $textarea.closest('.formula-editor-wrapper').find('.formula-funcs-dropdown').on('change', function() {
            const funcName = $(this).val();
            if (funcName && TD_FUNCTIONS[funcName]) {
                // Extract template from description
                const desc = TD_FUNCTIONS[funcName];
                const templateMatch = desc.match(/^([^-]+)/);
                if (templateMatch) {
                    insertAtCursor($textarea, templateMatch[1].trim());
                } else {
                    insertAtCursor($textarea, funcName + '()');
                }
                $(this).val('');
                $textarea.focus();
            }
        });

        // Help button
        $textarea.closest('.formula-editor-wrapper').find('.formula-help-btn').on('click', function() {
            showFormulaHelp();
        });

        // Hide autocomplete when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.formula-editor-wrapper').length) {
                hideAutocomplete($textarea);
            }
        });
    }

    /**
     * Get autocomplete suggestions
     */
    function getSuggestions(partial) {
        const suggestions = [];
        const partialLower = partial.toLowerCase();

        // Check variables
        Object.keys(TD_VARIABLES).forEach(varName => {
            if (varName.toLowerCase().startsWith(partialLower)) {
                suggestions.push({
                    type: 'variable',
                    name: varName,
                    description: TD_VARIABLES[varName]
                });
            }
        });

        // Check functions
        Object.keys(TD_FUNCTIONS).forEach(funcName => {
            if (funcName.toLowerCase().startsWith(partialLower)) {
                suggestions.push({
                    type: 'function',
                    name: funcName,
                    description: TD_FUNCTIONS[funcName]
                });
            }
        });

        // Sort by relevance (exact match first, then alphabetically)
        suggestions.sort((a, b) => {
            const aExact = a.name.toLowerCase() === partialLower ? 0 : 1;
            const bExact = b.name.toLowerCase() === partialLower ? 0 : 1;
            if (aExact !== bExact) return aExact - bExact;
            return a.name.localeCompare(b.name);
        });

        return suggestions.slice(0, 10); // Limit to 10 suggestions
    }

    /**
     * Show autocomplete dropdown
     */
    function showAutocomplete($textarea, suggestions) {
        const $autocomplete = $textarea.data('autocomplete');
        $autocomplete.empty();

        suggestions.forEach((suggestion, index) => {
            const $item = $('<div class="autocomplete-item"></div>');
            $item.data('suggestion', suggestion);

            const icon = suggestion.type === 'function' ? '𝑓' : '𝑥';
            const typeClass = suggestion.type === 'function' ? 'autocomplete-function' : 'autocomplete-variable';

            $item.html(`
                <span class="autocomplete-icon ${typeClass}">${icon}</span>
                <span class="autocomplete-name">${suggestion.name}</span>
                <span class="autocomplete-desc">${suggestion.description}</span>
            `);

            if (index === 0) {
                $item.addClass('selected');
            }

            $autocomplete.append($item);
        });

        // Position autocomplete box
        const offset = $textarea.offset();
        const caretCoords = getCaretCoordinates($textarea[0], $textarea[0].selectionStart);

        $autocomplete.css({
            top: offset.top + caretCoords.top + 20,
            left: offset.left + caretCoords.left
        });

        $autocomplete.show();
    }

    /**
     * Hide autocomplete dropdown
     */
    function hideAutocomplete($textarea) {
        const $autocomplete = $textarea.data('autocomplete');
        $autocomplete.hide();
    }

    /**
     * Highlight selected suggestion
     */
    function highlightSuggestion($autocomplete, index) {
        $autocomplete.find('.autocomplete-item').removeClass('selected');
        $autocomplete.find('.autocomplete-item').eq(index).addClass('selected');

        // Scroll into view if needed
        const $selected = $autocomplete.find('.autocomplete-item.selected');
        if ($selected.length) {
            const itemTop = $selected.position().top;
            const scrollTop = $autocomplete.scrollTop();
            const containerHeight = $autocomplete.height();

            if (itemTop < 0) {
                $autocomplete.scrollTop(scrollTop + itemTop);
            } else if (itemTop + $selected.outerHeight() > containerHeight) {
                $autocomplete.scrollTop(scrollTop + itemTop + $selected.outerHeight() - containerHeight);
            }
        }
    }

    /**
     * Insert suggestion at cursor
     */
    function insertSuggestion($textarea, suggestion) {
        const textarea = $textarea[0];
        const cursorPos = textarea.selectionStart;
        const text = $textarea.val();
        const textBeforeCursor = text.substring(0, cursorPos);

        // Find the partial word to replace
        const match = textBeforeCursor.match(/([a-zA-Z_][a-zA-Z0-9_]*)$/);
        if (match) {
            const partial = match[1];
            const startPos = cursorPos - partial.length;
            const textAfterCursor = text.substring(cursorPos);

            let insertion = suggestion.name;
            if (suggestion.type === 'function') {
                insertion += '()';
            }

            const newText = text.substring(0, startPos) + insertion + textAfterCursor;
            $textarea.val(newText);

            // Position cursor
            let newCursorPos = startPos + insertion.length;
            if (suggestion.type === 'function') {
                newCursorPos -= 1; // Inside parentheses
            }

            textarea.setSelectionRange(newCursorPos, newCursorPos);

            // Trigger input event for syntax highlighting
            $textarea.trigger('input');
        }
    }

    /**
     * Insert text at cursor position
     */
    function insertAtCursor($textarea, text) {
        const textarea = $textarea[0];
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const currentValue = $textarea.val();

        const newValue = currentValue.substring(0, start) + text + currentValue.substring(end);
        $textarea.val(newValue);

        // Position cursor (after inserted text or inside parentheses)
        let newCursorPos = start + text.length;
        if (text.includes('()')) {
            newCursorPos = start + text.indexOf('()') + 1;
        } else if (text.includes('(') && text.includes(')')) {
            newCursorPos = start + text.indexOf('(') + 1;
        }

        textarea.setSelectionRange(newCursorPos, newCursorPos);

        // Trigger input event
        $textarea.trigger('input');
    }

    /**
     * Update syntax highlighting
     */
    function updateSyntaxHighlight($textarea) {
        const $highlight = $textarea.data('syntaxHighlight');
        const text = $textarea.val();

        // Simple syntax highlighting
        let highlighted = text;

        // Highlight functions
        Object.keys(TD_FUNCTIONS).forEach(funcName => {
            const regex = new RegExp('\\b(' + funcName + ')\\b', 'g');
            highlighted = highlighted.replace(regex, '<span class="syntax-function">$1</span>');
        });

        // Highlight variables
        Object.keys(TD_VARIABLES).forEach(varName => {
            const regex = new RegExp('\\b(' + varName + ')\\b', 'g');
            highlighted = highlighted.replace(regex, '<span class="syntax-variable">$1</span>');
        });

        // Highlight numbers
        highlighted = highlighted.replace(/\b(\d+\.?\d*)\b/g, '<span class="syntax-number">$1</span>');

        // Highlight strings
        highlighted = highlighted.replace(/"([^"]*)"/g, '<span class="syntax-string">"$1"</span>');

        // Highlight operators
        TD_OPERATORS.forEach(op => {
            const escaped = op.replace(/([+*?^${}()|[\]\\])/g, '\\$1');
            const regex = new RegExp('(' + escaped + ')', 'g');
            highlighted = highlighted.replace(regex, '<span class="syntax-operator">$1</span>');
        });

        $highlight.html(highlighted);

        // Sync scroll
        $highlight.scrollTop($textarea.scrollTop());
        $highlight.scrollLeft($textarea.scrollLeft());
    }

    /**
     * Validate formula
     */
    function validateFormula($textarea) {
        const $validation = $textarea.data('validation');
        const formula = $textarea.val();

        if (!formula.trim()) {
            $validation.html('');
            $validation.removeClass('validation-error validation-warning validation-success');
            return;
        }

        // Basic validation
        const errors = [];
        const warnings = [];

        // Check parentheses balance
        const openParens = (formula.match(/\(/g) || []).length;
        const closeParens = (formula.match(/\)/g) || []).length;
        if (openParens !== closeParens) {
            errors.push('Unbalanced parentheses');
        }

        // Check for unknown variables/functions
        const tokens = formula.match(/[a-zA-Z_][a-zA-Z0-9_]*/g) || [];
        tokens.forEach(token => {
            if (!TD_VARIABLES[token] && !TD_FUNCTIONS[token]) {
                warnings.push(`Unknown identifier: ${token}`);
            }
        });

        // Display validation results
        if (errors.length > 0) {
            $validation.html('<strong>Errors:</strong> ' + errors.join(', '));
            $validation.removeClass('validation-warning validation-success').addClass('validation-error');
        } else if (warnings.length > 0) {
            $validation.html('<strong>Warnings:</strong> ' + warnings.join(', '));
            $validation.removeClass('validation-error validation-success').addClass('validation-warning');
        } else {
            $validation.html('✓ Formula syntax is valid');
            $validation.removeClass('validation-error validation-warning').addClass('validation-success');
        }
    }

    /**
     * Get caret coordinates relative to textarea
     */
    function getCaretCoordinates(element, position) {
        // Simple approximation - in production would use a library like textarea-caret
        const rows = element.value.substr(0, position).split('\n');
        const lineNumber = rows.length - 1;
        const lineText = rows[lineNumber];

        return {
            top: lineNumber * 20, // Approximate line height
            left: lineText.length * 8 // Approximate character width
        };
    }

    /**
     * Show formula help modal
     */
    function showFormulaHelp() {
        // Create help modal if it doesn't exist
        if (!$('#formula-help-modal').length) {
            const $modal = $('<div id="formula-help-modal" class="formula-modal"></div>');
            const $content = $('<div class="formula-modal-content"></div>');
            const $header = $('<div class="formula-modal-header"><h3>Formula Help</h3><button class="formula-modal-close">&times;</button></div>');
            const $body = $('<div class="formula-modal-body"></div>');

            // Build help content
            let helpHtml = '<h4>Variables</h4><div class="help-section">';
            Object.keys(TD_VARIABLES).forEach(varName => {
                helpHtml += `<div class="help-item"><code>${varName}</code> - ${TD_VARIABLES[varName]}</div>`;
            });
            helpHtml += '</div>';

            helpHtml += '<h4>Functions</h4><div class="help-section">';
            Object.keys(TD_FUNCTIONS).forEach(funcName => {
                helpHtml += `<div class="help-item"><code>${TD_FUNCTIONS[funcName]}</code></div>`;
            });
            helpHtml += '</div>';

            $body.html(helpHtml);
            $content.append($header).append($body);
            $modal.append($content);
            $('body').append($modal);

            // Close button
            $('.formula-modal-close, #formula-help-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#formula-help-modal').hide();
                }
            });
        }

        $('#formula-help-modal').show();
    }

    // Initialize formula editors on page load
    $('.formula-editor-textarea').each(function() {
        initFormulaEditor($(this));
    });

    // Export for external use
    window.PokerFormulaEditor = {
        init: initFormulaEditor,
        TD_VARIABLES: TD_VARIABLES,
        TD_FUNCTIONS: TD_FUNCTIONS
    };
});
