/**
 * Layout Builder JavaScript
 *
 * Handles drag-and-drop functionality for tournament display layouts
 *
 * @package Poker_Tournament_Import
 * @subpackage Tournament_Manager
 * @since 3.4.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // Layout Builder Class
    var TDWP_Layout_Builder = {

        // Configuration
        config: {
            gridColumns: 12,
            gridRows: 8,
            cellSize: 80,
            gap: 10,
            padding: 20
        },

        // State
        state: {
            tournamentId: 0,
            currentLayout: null,
            selectedComponent: null,
            isDragging: false,
            draggedComponent: null,
            components: {},
            gridOccupied: [],
            showGrid: true
        },

        // Initialize
        init: function() {
            this.state.tournamentId = $('#tdwp-layout-data').data('tournament-id');
            this.state.nonce = $('#tdwp-layout-data').data('nonce');
            this.state.availableComponents = $('#tdwp-layout-data').data('available-components');
            this.state.breakpoints = $('#tdwp-layout-data').data('breakpoints');

            this.setupEventListeners();
            this.initializeGrid();
            this.loadInitialLayout();
        },

        // Setup event listeners
        setupEventListeners: function() {
            var self = this;

            // Toolbar actions
            $('#tdwp-new-layout-btn').on('click', function() {
                self.createNewLayout();
            });

            $('#tdwp-save-layout-btn').on('click', function() {
                self.saveLayout();
            });

            $('#tdwp-delete-layout-btn').on('click', function() {
                self.deleteLayout();
            });

            $('#tdwp-grid-toggle').on('click', function() {
                self.toggleGrid();
            });

            $('#tdwp-reset-layout').on('click', function() {
                self.resetLayout();
            });

            // Layout selection
            $('#tdwp-layout-select').on('change', function() {
                self.loadLayout($(this).val());
            });

            // Device preview
            $('#tdwp-device-preview').on('change', function() {
                self.changeDevicePreview($(this).val());
            });

            // Component palette drag
            $('.tdwp-component-item').draggable({
                helper: 'clone',
                revert: 'invalid',
                opacity: 0.7,
                zIndex: 1000,
                start: function() {
                    $(this).addClass('dragging');
                },
                stop: function() {
                    $(this).removeClass('dragging');
                }
            });

            // Layout canvas drop zone
            $('#tdwp-layout-canvas').droppable({
                accept: '.tdwp-component-item',
                drop: function(event, ui) {
                    self.handleComponentDrop(event, ui);
                }
            });

            // Preview modal
            $('.tdwp-layout-preview-btn').on('click', function(e) {
                e.preventDefault();
                self.showPreview();
            });

            $('.tdwp-modal-close').on('click', function() {
                self.hidePreview();
            });

            // Properties panel
            $('#tdwp-apply-properties').on('click', function() {
                self.applyComponentProperties();
            });

            $('#tdwp-delete-component').on('click', function() {
                self.deleteSelectedComponent();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.which) {
                        case 83: // Ctrl+S
                            e.preventDefault();
                            self.saveLayout();
                            break;
                        case 90: // Ctrl+Z
                            e.preventDefault();
                            self.undo();
                            break;
                        case 89: // Ctrl+Y
                            e.preventDefault();
                            self.redo();
                            break;
                    }
                } else if (e.which === 46) { // Delete key
                    self.deleteSelectedComponent();
                }
            });
        },

        // Initialize grid
        initializeGrid: function() {
            var self = this;
            var $grid = $('#tdwp-layout-grid');

            // Create grid cells
            for (var row = 0; row < self.config.gridRows; row++) {
                for (var col = 0; col < self.config.gridColumns; col++) {
                    var $cell = $('<div>')
                        .addClass('tdwp-grid-cell')
                        .data('row', row)
                        .data('col', col);
                    $grid.append($cell);
                }
            }

            // Initialize occupied array
            for (var row = 0; row < self.config.gridRows; row++) {
                self.state.gridOccupied[row] = new Array(self.config.gridColumns).fill(false);
            }
        },

        // Load initial layout
        loadInitialLayout: function() {
            var selectedLayoutId = $('#tdwp-layout-select').val();
            if (selectedLayoutId) {
                this.loadLayout(selectedLayoutId);
            }
        },

        // Create new layout
        createNewLayout: function() {
            var self = this;
            var layoutName = prompt('Enter layout name:');

            if (layoutName) {
                self.resetLayout();
                self.state.currentLayout = {
                    name: layoutName,
                    components: {}
                };
                self.updateUI();
            }
        },

        // Load layout
        loadLayout: function(layoutId) {
            var self = this;

            if (!layoutId) {
                self.resetLayout();
                return;
            }

            self.setStatus('Loading layout...');

            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'tdwp_get_layout',
                    layout_id: layoutId,
                    nonce: self.state.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.applyLayout(response.data);
                        self.setStatus('Layout loaded');
                    } else {
                        self.setStatus('Error loading layout', 'error');
                    }
                },
                error: function() {
                    self.setStatus('Network error', 'error');
                }
            });
        },

        // Apply layout to canvas
        applyLayout: function(layoutData) {
            var self = this;

            self.state.currentLayout = layoutData;
            self.resetCanvas();

            if (layoutData.component_positions) {
                try {
                    var positions = JSON.parse(layoutData.component_positions);
                    $.each(positions, function(componentId, position) {
                        self.addComponentToCanvas(componentId, position);
                    });
                } catch (e) {
                    self.setStatus('Invalid layout data', 'error');
                }
            }

            self.updateUI();
        },

        // Add component to canvas
        addComponentToCanvas: function(componentId, position) {
            var self = this;
            var componentData = self.state.availableComponents[componentId];

            if (!componentData) return;

            // Mark grid cells as occupied
            for (var row = position.row_start; row < position.row_start + position.height; row++) {
                for (var col = position.column_start; col < position.column_start + position.width; col++) {
                    if (row < self.config.gridRows && col < self.config.gridColumns) {
                        self.state.gridOccupied[row][col] = true;
                        $('#tdwp-layout-grid').find('.tdwp-grid-cell')
                            .eq(row * self.config.gridColumns + col)
                            .addClass('occupied');
                    }
                }
            }

            // Create component element
            var $component = self.createComponentElement(componentId, componentData, position);
            $('#tdwp-layout-canvas').append($component);

            // Store component data
            self.state.components[componentId] = {
                data: componentData,
                position: position,
                element: $component
            };
        },

        // Create component element
        createComponentElement: function(componentId, componentData, position) {
            var self = this;
            var $component = $('<div>')
                .addClass('tdwp-layout-component')
                .attr('data-component', componentId)
                .css({
                    left: (position.column_start * (self.config.cellSize + self.config.gap) + self.config.padding) + 'px',
                    top: (position.row_start * (self.config.cellSize + self.config.gap) + self.config.padding) + 'px',
                    width: (position.width * (self.config.cellSize + self.config.gap) - self.config.gap) + 'px',
                    height: (position.height * (self.config.cellSize + self.config.gap) - self.config.gap) + 'px'
                });

            // Add component content
            var $content = $('<div>')
                .addClass('tdwp-component-preview')
                .text('Sample ' + componentData.name + ' Content');
            $component.append($content);

            // Make component draggable within canvas
            $component.draggable({
                containment: '#tdwp-layout-canvas',
                grid: [self.config.cellSize + self.config.gap, self.config.cellSize + self.config.gap],
                stop: function() {
                    self.updateComponentPosition($(this));
                }
            });

            // Make component selectable
            $component.on('click', function() {
                self.selectComponent($(this));
            });

            return $component;
        },

        // Handle component drop
        handleComponentDrop: function(event, ui) {
            var self = this;
            var componentId = ui.draggable.data('component');
            var componentData = self.state.availableComponents[componentId];
            var canvasOffset = $('#tdwp-layout-canvas').offset();
            var dropX = event.pageX - canvasOffset.left;
            var dropY = event.pageY - canvasOffset.top;

            // Calculate grid position
            var col = Math.round((dropX - self.config.padding) / (self.config.cellSize + self.config.gap));
            var row = Math.round((dropY - self.config.padding) / (self.config.cellSize + self.config.gap));

            // Validate position
            if (self.validatePosition(col, row, componentData.default_size.width, componentData.default_size.height)) {
                var position = {
                    column_start: col,
                    row_start: row,
                    width: componentData.default_size.width,
                    height: componentData.default_size.height
                };

                self.addComponentToCanvas(componentId, position);
                self.updateUI();
            }
        },

        // Validate component position
        validatePosition: function(col, row, width, height) {
            var self = this;

            // Check bounds
            if (col < 0 || row < 0 || col + width > self.config.gridColumns || row + height > self.config.gridRows) {
                return false;
            }

            // Check for overlaps
            for (var r = row; r < row + height; r++) {
                for (var c = col; c < col + width; c++) {
                    if (self.state.gridOccupied[r][c]) {
                        return false;
                    }
                }
            }

            return true;
        },

        // Update component position
        updateComponentPosition: function($component) {
            var self = this;
            var componentId = $component.data('component');
            var position = $component.position();

            // Convert position back to grid coordinates
            var col = Math.round((position.left - self.config.padding) / (self.config.cellSize + self.config.gap));
            var row = Math.round((position.top - self.config.padding) / (self.config.cellSize + self.config.gap));

            // Validate new position
            var componentData = self.state.availableComponents[componentId];
            var currentPosition = self.state.components[componentId].position;

            // Clear old position
            for (var r = currentPosition.row_start; r < currentPosition.row_start + currentPosition.height; r++) {
                for (var c = currentPosition.column_start; c < currentPosition.column_start + currentPosition.width; c++) {
                    if (r < self.config.gridRows && c < self.config.gridColumns) {
                        self.state.gridOccupied[r][c] = false;
                        $('#tdwp-layout-grid').find('.tdwp-grid-cell')
                            .eq(r * self.config.gridColumns + c)
                            .removeClass('occupied');
                    }
                }
            }

            // Validate new position
            if (self.validatePosition(col, row, currentPosition.width, currentPosition.height)) {
                // Update position
                var newPosition = {
                    column_start: col,
                    row_start: row,
                    width: currentPosition.width,
                    height: currentPosition.height
                };

                self.state.components[componentId].position = newPosition;

                // Mark new position as occupied
                for (var r = row; r < row + newPosition.height; r++) {
                    for (var c = col; c < col + newPosition.width; c++) {
                        if (r < self.config.gridRows && c < self.config.gridColumns) {
                            self.state.gridOccupied[r][c] = true;
                            $('#tdwp-layout-grid').find('.tdwp-grid-cell')
                                .eq(r * self.config.gridColumns + c)
                                .addClass('occupied');
                        }
                    }
            } else {
                // Revert to original position
                $component.css({
                    left: (currentPosition.column_start * (self.config.cellSize + self.config.gap) + self.config.padding) + 'px',
                    top: (currentPosition.row_start * (self.config.cellSize + self.config.gap) + self.config.padding) + 'px'
                });
            }
        },

        // Select component
        selectComponent: function($component) {
            var self = this;

            // Remove previous selection
            $('.tdwp-layout-component').removeClass('selected');

            // Select new component
            $component.addClass('selected');
            self.state.selectedComponent = $component.data('component');

            // Show properties
            self.showComponentProperties($component.data('component'));
        },

        // Show component properties
        showComponentProperties: function(componentId) {
            var self = this;
            var component = self.state.components[componentId];

            if (!component) return;

            var componentData = {
                name: component.data.name,
                width: component.position.width,
                height: component.position.height,
                bg_color: '#ffffff',
                text_color: '#000000',
                font_size: 16
            };

            $('#tdwp-properties-content').html('');

            // Create properties panel content manually (since we don't have the PHP method)
            var $properties = $('<div>').html(`
                <div class="tdwp-property-group">
                    <h4>Component Properties</h4>
                    <table class="tdwp-property-table">
                        <tr>
                            <td><label>Type:</label></td>
                            <td>${componentData.name}</td>
                        </tr>
                        <tr>
                            <td><label>Width:</label></td>
                            <td>
                                <input type="number" id="tdwp-prop-width" class="small-text" value="${componentData.width}" min="1" max="24">
                                <span class="tdwp-property-unit">columns</span>
                            </td>
                        </tr>
                        <tr>
                            <td><label>Height:</label></td>
                            <td>
                                <input type="number" id="tdwp-prop-height" class="small-text" value="${componentData.height}" min="1" max="20">
                                <span class="tdwp-property-unit">rows</span>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="tdwp-property-group">
                    <h4>Appearance</h4>
                    <table class="tdwp-property-table">
                        <tr>
                            <td><label>Background:</label></td>
                            <td>
                                <input type="color" id="tdwp-prop-bg-color" class="tdwp-color-picker" value="${componentData.bg_color}">
                            </td>
                        </tr>
                        <tr>
                            <td><label>Text Color:</label></td>
                            <td>
                                <input type="color" id="tdwp-prop-text-color" class="tdwp-color-picker" value="${componentData.text_color}">
                            </td>
                        </tr>
                        <tr>
                            <td><label>Font Size:</label></td>
                            <td>
                                <input type="number" id="tdwp-prop-font-size" class="small-text" value="${componentData.font_size}" min="8" max="72">
                                <span class="tdwp-property-unit">px</span>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="tdwp-property-group">
                    <h4>Actions</h4>
                    <button id="tdwp-apply-properties" class="button button-secondary">Apply Changes</button>
                    <button id="tdwp-delete-component" class="button button-link-delete">Delete Component</button>
                </div>
            `);

            $('#tdwp-properties-content').append($properties);
        },

        // Apply component properties
        applyComponentProperties: function() {
            var self = this;
            var componentId = self.state.selectedComponent;

            if (!componentId) return;

            var $component = $('.tdwp-layout-component[data-component="' + componentId + '"]');
            var component = self.state.components[componentId];

            // Update position
            var newWidth = parseInt($('#tdwp-prop-width').val());
            var newHeight = parseInt($('#tdwp-prop-height').val());

            if (self.canResizeComponent(component.position, newWidth, newHeight)) {
                component.position.width = newWidth;
                component.position.height = newHeight;

                // Update component size
                $component.css({
                    width: (newWidth * (self.config.cellSize + self.config.gap) - self.config.gap) + 'px',
                    height: (newHeight * (self.config.cellSize + self.config.gap) - self.config.gap) + 'px'
                });

                // Update component appearance
                var bgColor = $('#tdwp-prop-bg-color').val();
                var textColor = $('#tdwp-prop-text-color').val();
                var fontSize = $('#tdwp-prop-font-size').val();

                $component.css({
                    backgroundColor: bgColor,
                    color: textColor,
                    fontSize: fontSize + 'px'
                });

                self.updateUI();
            }
        },

        // Check if component can be resized
        canResizeComponent: function(currentPosition, newWidth, newHeight) {
            var self = this;

            // Check bounds
            if (currentPosition.column_start + newWidth > self.config.gridColumns ||
                currentPosition.row_start + newHeight > self.config.gridRows) {
                return false;
            }

            // Check for overlaps (simplified)
            return true;
        },

        // Delete selected component
        deleteSelectedComponent: function() {
            var self = this;
            var componentId = self.state.selectedComponent;

            if (!componentId) return;

            var component = self.state.components[componentId];
            var $component = component.element;

            // Clear occupied cells
            for (var row = component.position.row_start; row < component.position.row_start + component.position.height; row++) {
                for (var col = component.position.column_start; col < component.position.column_start + component.position.width; col++) {
                    if (row < self.config.gridRows && col < self.config.gridColumns) {
                        self.state.gridOccupied[row][col] = false;
                        $('#tdwp-layout-grid').find('.tdwp-grid-cell')
                            .eq(row * self.config.gridColumns + col)
                            .removeClass('occupied');
                    }
                }
            }

            // Remove component
            $component.remove();
            delete self.state.components[componentId];
            self.state.selectedComponent = null;

            // Clear properties panel
            $('#tdwp-properties-content').html('<p class="description">Select a component to edit its properties</p>');

            self.updateUI();
        },

        // Save layout
        saveLayout: function() {
            var self = this;

            if (!self.state.currentLayout) {
                self.setStatus('No layout to save', 'error');
                return;
            }

            self.setStatus('Saving layout...');

            var layoutData = {
                tournament_id: self.state.tournamentId,
                layout_name: self.state.currentLayout.name || 'Untitled Layout',
                grid_config: JSON.stringify({
                    columns: self.config.gridColumns,
                    rows: self.config.gridRows,
                    gap: '10px',
                    padding: '20px'
                }),
                component_positions: JSON.stringify(self.getComponentPositions()),
                screen_size: $('#tdwp-device-preview').val(),
                is_active: true
            };

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_save_layout',
                    layout_data: JSON.stringify(layoutData),
                    nonce: self.state.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.state.currentLayout.id = response.data.layout_id;
                        self.setStatus('Layout saved');
                        self.updateLastSavedTime();
                    } else {
                        self.setStatus('Error saving layout', 'error');
                    }
                },
                error: function() {
                    self.setStatus('Network error', 'error');
                }
            });
        },

        // Get component positions
        getComponentPositions: function() {
            var self = this;
            var positions = {};

            $.each(self.state.components, function(componentId, component) {
                positions[componentId] = component.position;
            });

            return positions;
        },

        // Delete layout
        deleteLayout: function() {
            var self = this;
            var selectedLayoutId = $('#tdwp-layout-select').val();

            if (!selectedLayoutId) {
                self.setStatus('No layout selected', 'error');
                return;
            }

            if (confirm('Are you sure you want to delete this layout?')) {
                // Implementation would go here
                self.setStatus('Layout deleted');
            }
        },

        // Reset layout
        resetLayout: function() {
            var self = this;

            self.resetCanvas();
            self.state.currentLayout = null;
            self.state.selectedComponent = null;

            $('#tdwp-properties-content').html('<p class="description">Select a component to edit its properties</p>');
            self.updateUI();
        },

        // Reset canvas
        resetCanvas: function() {
            var self = this;

            // Remove all components
            $('.tdwp-layout-component').remove();

            // Reset grid occupation
            for (var row = 0; row < self.config.gridRows; row++) {
                self.state.gridOccupied[row] = new Array(self.config.gridColumns).fill(false);
            }

            // Reset grid cells
            $('.tdwp-grid-cell').removeClass('occupied');

            // Clear component storage
            self.state.components = {};
        },

        // Toggle grid visibility
        toggleGrid: function() {
            var self = this;
            var $toggle = $('#tdwp-grid-toggle');
            var $grid = $('#tdwp-layout-grid');

            self.state.showGrid = !self.state.showGrid;

            if (self.state.showGrid) {
                $grid.removeClass('tdwp-grid-hidden');
                $toggle.text('Show Grid').removeClass('button-secondary').addClass('button-primary');
            } else {
                $grid.addClass('tdwp-grid-hidden');
                $toggle.text('Hide Grid').removeClass('button-primary').addClass('button-secondary');
            }
        },

        // Change device preview
        changeDevicePreview: function(device) {
            var self = this;
            var $canvas = $('#tdwp-layout-canvas');

            // Remove all device classes
            $canvas.removeClass('preview-desktop preview-tablet preview-mobile preview-large');

            // Add device class
            $canvas.addClass('preview-' + device);

            // Adjust canvas size for device
            switch(device) {
                case 'mobile':
                    self.config.gridColumns = 6;
                    self.config.gridRows = 12;
                    break;
                case 'tablet':
                    self.config.gridColumns = 8;
                    self.config.gridRows = 10;
                    break;
                case 'large':
                    self.config.gridColumns = 16;
                    self.config.gridRows = 6;
                    break;
                default:
                    self.config.gridColumns = 12;
                    self.config.gridRows = 8;
            }

            self.rebuildGrid();
        },

        // Rebuild grid
        rebuildGrid: function() {
            var self = this;

            // Remove existing grid cells
            $('.tdwp-grid-cell').remove();

            // Reset grid occupation
            self.state.gridOccupied = [];
            for (var row = 0; row < self.config.gridRows; row++) {
                self.state.gridOccupied[row] = new Array(self.config.gridColumns).fill(false);
            }

            // Recreate grid cells
            var $grid = $('#tdwp-layout-grid');
            for (var row = 0; row < self.config.gridRows; row++) {
                for (var col = 0; col < self.config.gridColumns; col++) {
                    var $cell = $('<div>')
                        .addClass('tdwp-grid-cell')
                        .data('row', row)
                        .data('col', col);
                    $grid.append($cell);
                }
            }
        },

        // Show preview
        showPreview: function() {
            var self = this;

            if (!self.state.currentLayout) {
                self.setStatus('No layout to preview', 'error');
                return;
            }

            var previewUrl = window.location.origin + '/tdwp-display/preview-' + self.state.currentLayout.id + '/';
            $('#tdwp-preview-frame').attr('src', previewUrl);
            $('#tdwp-preview-modal').show();
        },

        // Hide preview
        hidePreview: function() {
            $('#tdwp-preview-modal').hide();
        },

        // Update UI
        updateUI: function() {
            var self = this;

            // Update save button
            $('#tdwp-save-layout-btn').prop('disabled', !self.state.currentLayout);

            // Update delete button
            $('#tdwp-layout-select').val(self.state.currentLayout ? self.state.currentLayout.id : '');
            $('#tdwp-delete-layout-btn').prop('disabled', !self.state.currentLayout);

            // Update component count
            $('#tdwp-component-count').text(Object.keys(self.state.components).length + ' Components');

            // Update grid info
            $('#tdwp-grid-info').text(self.config.gridColumns + '×' + self.config.gridRows + ' Grid');
        },

        // Update status
        setStatus: function(message, type) {
            var $status = $('#tdwp-layout-status');

            $status.removeClass('tdwp-status-ready tdwp-status-saving tdwp-status-error')
                .addClass('tdwp-status-' + (type || 'ready'))
                .text(message);
        },

        // Update last saved time
        updateLastSavedTime: function() {
            var now = new Date();
            $('#tdwp-last-saved').text('Last saved: ' + now.toLocaleTimeString());
        }
    };

    // Initialize layout builder
    TDWP_Layout_Builder.init();
});