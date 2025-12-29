/**
 * Enhanced Layout Builder JavaScript
 * Handles grid-based layout functionality with component sizing
 *
 * @version 3.4.0
 * @package PokerTournamentImport
 */

jQuery(document).ready(function($) {
    'use strict';

    // Debug: Log when script loads
    console.log('TDWP Layout Builder: Enhanced script loaded');

    // Check if jQuery UI draggable is available
    if (typeof $.fn.draggable === 'undefined') {
        console.error('TDWP Layout Builder: jQuery UI draggable not available');
        return;
    }

    if (typeof $.fn.droppable === 'undefined') {
        console.error('TDWP Layout Builder: jQuery UI droppable not available');
        return;
    }

    console.log('TDWP Layout Builder: jQuery UI dependencies verified');

    // Grid State Management
    const TDWP_GridBuilder = {
        // Grid configuration
        config: {
            gridColumns: 12,
            gridRows: 8,
            cellSize: 60,
            gap: 1,
            cellSizeUnit: 'px'
        },

        // Grid state
        state: {
            cells: [], // 2D array tracking cell states
            selectedCells: [],
            occupiedCells: {},
            components: [],
            isSelecting: false,
            selectionStart: null,
            gridVisible: true
        },

        // Initialize the grid builder
        init: function() {
            this.initializeGrid();
            this.setupEventListeners();
            this.setupDragAndDrop();
            console.log('TDWP Grid Builder: Initialized with', this.config.gridColumns, 'x', this.config.gridRows, 'grid');
        },

        // Initialize grid cells
        initializeGrid: function() {
            const $gridOverlay = $('#tdwp-grid-overlay');
            $gridOverlay.empty();

            // Initialize state arrays
            this.state.cells = [];
            this.state.selectedCells = [];

            // Ensure grid overlay has proper dimensions
            const $canvasArea = $('#tdwp-canvas');
            const canvasWidth = $canvasArea.width() || 960; // Fallback width
            const canvasHeight = $canvasArea.height() || 600; // Fallback height

            $gridOverlay.css({
                width: canvasWidth + 'px',
                height: canvasHeight + 'px'
            });

            // Calculate cell dimensions
            this.config.cellSize = Math.floor((canvasWidth - (this.config.gridColumns * this.config.gap)) / this.config.gridColumns);

            // Create grid cells
            for (let row = 0; row < this.config.gridRows; row++) {
                this.state.cells[row] = [];
                for (let col = 0; col < this.config.gridColumns; col++) {
                    const cellId = `cell-${row}-${col}`;
                    const $cell = $('<div>')
                        .attr('id', cellId)
                        .addClass('tdwp-grid-cell')
                        .data('row', row)
                        .data('col', col)
                        .css({
                            left: (col * (this.config.cellSize + this.config.gap)) + 'px',
                            top: (row * (this.config.cellSize + this.config.gap)) + 'px',
                            width: this.config.cellSize + 'px',
                            height: this.config.cellSize + 'px'
                        });

                    $gridOverlay.append($cell);
                    this.state.cells[row][col] = { id: cellId, state: 'empty', componentId: null };
                }
            }

            console.log('TDWP Grid Builder: Created', (this.config.gridRows * this.config.gridColumns), 'grid cells');

            // Update grid info display
            this.updateGridInfoDisplay();
        },

        // Setup event listeners
        setupEventListeners: function() {
            const self = this;

            // Grid cell selection
            $(document).on('mousedown', '.tdwp-grid-cell', function(e) {
                e.preventDefault();
                self.startSelection($(this));
            });

            $(document).on('mousemove', function(e) {
                if (self.state.isSelecting) {
                    self.updateSelection(e);
                }
            });

            $(document).on('mouseup', function(e) {
                if (self.state.isSelecting) {
                    self.endSelection();
                }
            });

            // Grid controls
            $('#tdwp-update-grid').on('click', function() {
                self.updateGridDimensions();
            });

            $('#tdwp-toggle-grid').on('click', function() {
                self.toggleGrid();
            });

            $('#tdwp-clear-selection').on('click', function() {
                self.clearSelection();
            });

            // Component property controls
            $('#tdwp-apply-properties').on('click', function() {
                self.applyComponentProperties();
            });

            $('#tdwp-delete-component').on('click', function() {
                self.deleteSelectedComponent();
            });

            // Size preset buttons
            $('.tdwp-preset-size').on('click', function() {
                const width = parseInt($(this).data('width'));
                const height = parseInt($(this).data('height'));
                $('#tdwp-prop-width').val(width);
                $('#tdwp-prop-height').val(height);
            });
        },

        // Start cell selection
        startSelection: function($cell) {
            this.state.isSelecting = true;
            this.state.selectionStart = {
                row: $cell.data('row'),
                col: $cell.data('col')
            };
            this.clearSelection();
            $cell.addClass('selected');
            this.state.selectedCells = [{row: $cell.data('row'), col: $cell.data('col')}];
        },

        // Update selection during drag
        updateSelection: function(e) {
            const $gridOverlay = $('#tdwp-grid-overlay');
            const offset = $gridOverlay.offset();
            const col = Math.floor((e.pageX - offset.left) / (this.config.cellSize + this.config.gap));
            const row = Math.floor((e.pageY - offset.top) / (this.config.cellSize + this.config.gap));

            if (col >= 0 && col < this.config.gridColumns && row >= 0 && row < this.config.gridRows) {
                this.clearSelection();
                this.selectArea(this.state.selectionStart.row, this.state.selectionStart.col, row, col);
            }
        },

        // End selection
        endSelection: function() {
            this.state.isSelecting = false;
            this.state.selectionStart = null;
            console.log('TDWP Grid Builder: Selected', this.state.selectedCells.length, 'cells');
        },

        // Select rectangular area
        selectArea: function(startRow, startCol, endRow, endCol) {
            const minRow = Math.min(startRow, endRow);
            const maxRow = Math.max(startRow, endRow);
            const minCol = Math.min(startCol, endCol);
            const maxCol = Math.max(startCol, endCol);

            this.state.selectedCells = [];

            for (let row = minRow; row <= maxRow; row++) {
                for (let col = minCol; col <= maxCol; col++) {
                    if (this.state.cells[row] && this.state.cells[row][col]) {
                        const $cell = $(`#cell-${row}-${col}`);
                        $cell.addClass('selected');
                        this.state.selectedCells.push({row, col});
                    }
                }
            }
        },

        // Clear selection
        clearSelection: function() {
            $('.tdwp-grid-cell.selected').removeClass('selected');
            this.state.selectedCells = [];
        },

        // Toggle grid visibility
        toggleGrid: function() {
            const $toggle = $('#tdwp-toggle-grid');
            const $gridOverlay = $('#tdwp-grid-overlay');

            this.state.gridVisible = !this.state.gridVisible;

            if (this.state.gridVisible) {
                $gridOverlay.removeClass('hidden');
                $toggle.text('Hide Grid');
            } else {
                $gridOverlay.addClass('hidden');
                $toggle.text('Show Grid');
            }
        },

        // Update grid dimensions
        updateGridDimensions: function() {
            const newCols = parseInt($('#tdwp-grid-columns').val());
            const newRows = parseInt($('#tdwp-grid-rows').val());

            if (newCols >= 4 && newCols <= 20 && newRows >= 4 && newRows <= 20) {
                this.config.gridColumns = newCols;
                this.config.gridRows = newRows;
                this.initializeGrid();
                this.showMessage('Grid updated to ' + newCols + '×' + newRows, 'success');
            } else {
                this.showMessage('Invalid grid dimensions', 'error');
            }
        },

        // Setup drag and drop with grid integration
        setupDragAndDrop: function() {
            const self = this;

            // Component dragging
            $('.tdwp-component').draggable({
                helper: 'clone',
                appendTo: '#tdwp-canvas',
                zIndex: 1000,
                start: function() {
                    console.log('TDWP Layout Builder: Started dragging component');
                },
                stop: function() {
                    console.log('TDWP Layout Builder: Stopped dragging component');
                }
            });

            // Enhanced droppable with grid snapping
            $('#tdwp-canvas').droppable({
                accept: '.tdwp-component',
                drop: function(event, ui) {
                    const component = ui.draggable.data('component');
                    const componentText = ui.draggable.text();

                    // Calculate drop position relative to grid
                    const canvasOffset = $(this).offset();
                    const dropX = ui.offset.left - canvasOffset.left;
                    const dropY = ui.offset.top - canvasOffset.top;

                    // Convert to grid coordinates
                    const gridX = Math.floor(dropX / (self.config.cellSize + self.config.gap));
                    const gridY = Math.floor(dropY / (self.config.cellSize + self.config.gap));

                    console.log('TDWP Layout Builder: Component dropped at grid position', gridX, gridY);

                    // Check if drop position is valid
                    if (gridX >= 0 && gridX < self.config.gridColumns && gridY >= 0 && gridY < self.config.gridRows) {
                        // Default component size is 2x2 grid cells
                        const width = 2;
                        const height = 2;

                        // Check if the area is available
                        if (self.validateComponentPlacement('temp-' + Date.now(), gridX, gridY, width, height)) {
                            // Select the grid area for this component
                            self.clearSelection();
                            self.selectArea(gridY, gridX, gridY + height - 1, gridX + width - 1);

                            // Add component to selected area
                            self.addComponentToGrid(component, componentText);
                        } else {
                            self.showMessage('Cannot place component here - area is occupied', 'warning');
                        }
                    } else {
                        self.showMessage('Drop position is outside the grid', 'warning');
                    }
                }
            });
        },

        // Add component to grid
        addComponentToGrid: function(componentType, componentText) {
            if (this.state.selectedCells.length === 0) return;

            // Check if any selected cells are occupied
            const occupiedCells = this.state.selectedCells.filter(cell =>
                this.isCellOccupied(cell.row, cell.col)
            );

            if (occupiedCells.length > 0) {
                this.showMessage('Some cells are already occupied', 'error');
                return;
            }

            // Calculate component dimensions
            const bounds = this.getSelectionBounds();
            const componentId = 'component-' + Date.now();

            // Create component element
            const $component = this.createComponent(componentId, componentType, componentText, bounds);

            // Add to component container
            $('#tdwp-component-container').append($component);

            // Update grid state
            this.markCellsAsOccupied(componentId);

            // Store component data
            this.state.components.push({
                id: componentId,
                type: componentType,
                text: componentText,
                position: { x: bounds.minCol, y: bounds.minRow },
                size: { width: bounds.maxCol - bounds.minCol + 1, height: bounds.maxRow - bounds.minRow + 1 }
            });

            // Update component count display
            this.updateGridInfoDisplay();

            // Make component draggable within canvas
            $component.draggable({
                containment: '#tdwp-canvas',
                stop: function() {
                    // Update component position after manual drag
                    self.updateComponentPosition($(this));
                }
            });

            // Make component selectable
            $component.on('click', function() {
                self.selectComponent($(this));
            });

            this.clearSelection();
            this.showMessage('Component added successfully', 'success');
        },

        // Create component element
        createComponent: function(id, type, text, bounds) {
            const left = (bounds.minCol * (this.config.cellSize + this.config.gap)) + 'px';
            const top = (bounds.minRow * (this.config.cellSize + this.config.gap)) + 'px';
            const width = ((bounds.maxCol - bounds.minCol + 1) * (this.config.cellSize + this.config.gap)) - this.config.gap + 'px';
            const height = ((bounds.maxRow - bounds.minRow + 1) * (this.config.cellSize + this.config.gap)) - this.config.gap + 'px';

            return $('<div>')
                .addClass('tdwp-layout-component')
                .attr('id', id)
                .attr('data-component-type', type)
                .css({
                    left: left,
                    top: top,
                    width: width,
                    height: height
                })
                .text(text);
        },

        // Get selection bounds
        getSelectionBounds: function() {
            if (this.state.selectedCells.length === 0) return null;

            const rows = this.state.selectedCells.map(cell => cell.row);
            const cols = this.state.selectedCells.map(cell => cell.col);

            return {
                minRow: Math.min(...rows),
                maxRow: Math.max(...rows),
                minCol: Math.min(...cols),
                maxCol: Math.max(...cols)
            };
        },

        // Mark cells as occupied
        markCellsAsOccupied: function(componentId) {
            this.state.selectedCells.forEach(cell => {
                this.state.cells[cell.row][cell.col].state = 'occupied';
                this.state.cells[cell.row][cell.col].componentId = componentId;
                $(`#cell-${cell.row}-${cell.col}`).addClass('occupied');
            });
        },

        // Check if cell is occupied
        isCellOccupied: function(row, col) {
            return this.state.cells[row] && this.state.cells[row][col] &&
                   this.state.cells[row][col].state === 'occupied';
        },

        // Select component
        selectComponent: function($component) {
            // Remove previous selection
            $('.tdwp-layout-component').removeClass('selected');

            // Select new component
            $component.addClass('selected');

            // Show component properties
            this.showComponentProperties($component);
        },

        // Show component properties
        showComponentProperties: function($component) {
            const componentId = $component.attr('id');
            const component = this.state.components.find(c => c.id === componentId);

            if (!component) return;

            // Show properties panel
            $('#tdwp-component-properties').show();
            $('#tdwp-property-panel .description').hide();

            // Set property values
            $('#tdwp-prop-x').val(component.position.x);
            $('#tdwp-prop-y').val(component.position.y);
            $('#tdwp-prop-width').val(component.size.width);
            $('#tdwp-prop-height').val(component.size.height);
        },

        // Apply component properties
        applyComponentProperties: function() {
            const $selectedComponent = $('.tdwp-layout-component.selected');
            if ($selectedComponent.length === 0) return;

            const componentId = $selectedComponent.attr('id');
            const component = this.state.components.find(c => c.id === componentId);

            if (!component) return;

            // Get new values
            const newX = parseInt($('#tdwp-prop-x').val());
            const newY = parseInt($('#tdwp-prop-y').val());
            const newWidth = parseInt($('#tdwp-prop-width').val());
            const newHeight = parseInt($('#tdwp-prop-height').val());

            // Validate position and size
            if (this.validateComponentPlacement(componentId, newX, newY, newWidth, newHeight)) {
                // Clear old occupied cells
                this.clearComponentOccupiedCells(componentId);

                // Update component data
                component.position = { x: newX, y: newY };
                component.size = { width: newWidth, height: newHeight };

                // Update visual position and size
                const left = (newX * (this.config.cellSize + this.config.gap)) + 'px';
                const top = (newY * (this.config.cellSize + this.config.gap)) + 'px';
                const width = (newWidth * (this.config.cellSize + this.config.gap)) - this.config.gap + 'px';
                const height = (newHeight * (this.config.cellSize + this.config.gap)) - this.config.gap + 'px';

                $selectedComponent.css({
                    left: left,
                    top: top,
                    width: width,
                    height: height
                });

                // Mark new cells as occupied
                this.markComponentCellsAsOccupied(componentId);

                this.showMessage('Component properties updated', 'success');
            } else {
                this.showMessage('Invalid placement - cells occupied or out of bounds', 'error');
            }
        },

        // Delete selected component
        deleteSelectedComponent: function() {
            const $selectedComponent = $('.tdwp-layout-component.selected');
            if ($selectedComponent.length === 0) return;

            const componentId = $selectedComponent.attr('id');

            // Clear occupied cells
            this.clearComponentOccupiedCells(componentId);

            // Remove from components array
            this.state.components = this.state.components.filter(c => c.id !== componentId);

            // Remove element
            $selectedComponent.remove();

            // Hide properties panel
            $('#tdwp-component-properties').hide();
            $('#tdwp-property-panel .description').show();

            this.showMessage('Component deleted', 'success');
        },

        // Clear component occupied cells
        clearComponentOccupiedCells: function(componentId) {
            this.state.components.forEach(component => {
                if (component.id === componentId) {
                    for (let row = component.position.y; row < component.position.y + component.size.height; row++) {
                        for (let col = component.position.x; col < component.position.x + component.size.width; col++) {
                            if (this.state.cells[row] && this.state.cells[row][col]) {
                                this.state.cells[row][col].state = 'empty';
                                this.state.cells[row][col].componentId = null;
                                $(`#cell-${row}-${col}`).removeClass('occupied');
                            }
                        }
                    }
                }
            });
        },

        // Mark component cells as occupied
        markComponentCellsAsOccupied: function(componentId) {
            const component = this.state.components.find(c => c.id === componentId);
            if (!component) return;

            for (let row = component.position.y; row < component.position.y + component.size.height; row++) {
                for (let col = component.position.x; col < component.position.x + component.size.width; col++) {
                    if (this.state.cells[row] && this.state.cells[row][col]) {
                        this.state.cells[row][col].state = 'occupied';
                        this.state.cells[row][col].componentId = componentId;
                        $(`#cell-${row}-${col}`).addClass('occupied');
                    }
                }
            }
        },

        // Validate component placement
        validateComponentPlacement: function(componentId, x, y, width, height) {
            // Check bounds
            if (x < 0 || y < 0 || x + width > this.config.gridColumns || y + height > this.config.gridRows) {
                return false;
            }

            // Check for overlaps with other components
            for (let row = y; row < y + height; row++) {
                for (let col = x; col < x + width; col++) {
                    if (this.isCellOccupied(row, col) && this.state.cells[row][col].componentId !== componentId) {
                        return false;
                    }
                }
            }

            return true;
        },

        // Update component position (for manual dragging)
        updateComponentPosition: function($component) {
            const componentId = $component.attr('id');
            const component = this.state.components.find(c => c.id === componentId);

            if (!component) return;

            const position = $component.position();
            const $gridOverlay = $('#tdwp-grid-overlay');
            const offset = $gridOverlay.offset();

            // Calculate new grid position
            const newX = Math.round((position.left - offset.left) / (this.config.cellSize + this.config.gap));
            const newY = Math.round((position.top - offset.top) / (this.config.cellSize + this.config.gap));

            // Validate new position
            if (this.validateComponentPlacement(componentId, newX, newY, component.size.width, component.size.height)) {
                // Clear old cells
                this.clearComponentOccupiedCells(componentId);

                // Update component data
                component.position = { x: newX, y: newY };

                // Mark new cells as occupied
                this.markComponentCellsAsOccupied(componentId);
            } else {
                // Revert to original position
                const left = (component.position.x * (this.config.cellSize + this.config.gap)) + 'px';
                const top = (component.position.y * (this.config.cellSize + this.config.gap)) + 'px';
                $component.css({ left: left, top: top });
            }
        },

        // Update grid info display
        updateGridInfoDisplay: function() {
            const $gridInfo = $('#tdwp-grid-info');
            const $componentCount = $('#tdwp-component-count');

            if ($gridInfo.length) {
                $gridInfo.text(this.config.gridColumns + '×' + this.config.gridRows + ' Grid');
            }

            if ($componentCount.length) {
                $componentCount.text(this.state.components.length + ' Components');
            }
        },

        // Show status message
        showMessage: function(message, type) {
            const $message = $('<div>')
                .addClass('tdwp-status-message ' + type)
                .text(message)
                .appendTo('body');

            setTimeout(function() {
                $message.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize the grid builder
    TDWP_GridBuilder.init();

    // Legacy clear layout functionality
    $('#tdwp-clear-layout').on('click', function() {
        console.log('TDWP Layout Builder: Clearing layout');
        $('.tdwp-layout-component').remove();
        TDWP_GridBuilder.state.components = [];
        $('.tdwp-grid-cell').removeClass('occupied');

        // Reset all cells to empty
        for (let row = 0; row < TDWP_GridBuilder.config.gridRows; row++) {
            for (let col = 0; col < TDWP_GridBuilder.config.gridColumns; col++) {
                if (TDWP_GridBuilder.state.cells[row] && TDWP_GridBuilder.state.cells[row][col]) {
                    TDWP_GridBuilder.state.cells[row][col].state = 'empty';
                    TDWP_GridBuilder.state.cells[row][col].componentId = null;
                }
            }
        }

        $('#tdwp-component-properties').hide();
        $('#tdwp-property-panel .description').show();

        TDWP_GridBuilder.showMessage('Layout cleared', 'success');
    });

    console.log('TDWP Layout Builder: Enhanced initialization complete');
});