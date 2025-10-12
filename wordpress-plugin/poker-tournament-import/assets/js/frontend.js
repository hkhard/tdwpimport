/**
 * Frontend JavaScript for Poker Tournament Import
 */

jQuery(document).ready(function($) {
    'use strict';

    // Table sorting functionality
    $('.tournament-results-table, .player-stats-table').each(function() {
        const $table = $(this);
        const $headers = $table.find('thead th');

        $headers.on('click', function() {
            const $header = $(this);
            const columnIndex = $header.index();
            const sortAscending = !$header.hasClass('sort-asc');

            // Remove existing sort classes
            $headers.removeClass('sort-asc sort-desc');
            $header.addClass(sortAscending ? 'sort-asc' : 'sort-desc');

            // Sort the table
            const $tbody = $table.find('tbody');
            const $rows = $tbody.find('tr').toArray();

            $rows.sort(function(a, b) {
                const $a = $(a);
                const $b = $(b);
                const aValue = $a.find('td').eq(columnIndex).text().trim();
                const bValue = $b.find('td').eq(columnIndex).text().trim();

                // Check if values are numeric
                const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return sortAscending ? aNum - bNum : bNum - aNum;
                }

                // String comparison
                return sortAscending ?
                    aValue.localeCompare(bValue) :
                    bValue.localeCompare(aValue);
            });

            // Re-append sorted rows
            $tbody.empty().append($rows);
        });

        // Add cursor pointer to headers
        $headers.css('cursor', 'pointer');
        $headers.attr('title', 'Click to sort');
    });

    // Expandable tournament details
    $('.tournament-expand-toggle').on('click', function(e) {
        e.preventDefault();
        const $toggle = $(this);
        const $target = $($toggle.attr('href') || $toggle.data('target'));

        if ($target.length) {
            $target.slideToggle(300);
            $toggle.toggleClass('expanded');
            const newText = $toggle.hasClass('expanded') ?
                $toggle.data('expanded-text') || 'Show Less' :
                $toggle.data('collapsed-text') || 'Show More';
            $toggle.text(newText);
        }
    });

    // Player profile tabs
    $('.player-tabs').each(function() {
        const $tabsContainer = $(this);
        const $tabs = $tabsContainer.find('.player-tab');
        const $panels = $tabsContainer.find('.player-tab-panel');

        $tabs.on('click', function(e) {
            e.preventDefault();
            const $tab = $(this);
            const targetId = $tab.attr('href');

            // Remove active states
            $tabs.removeClass('active');
            $panels.removeClass('active');

            // Add active states
            $tab.addClass('active');
            $(targetId).addClass('active');
        });
    });

    // Print tournament results
    $('.print-tournament').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    // Export tournament data
    $('.export-tournament').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const tournamentId = $button.data('tournament-id');
        const format = $button.data('format') || 'csv';

        if (tournamentId) {
            // Create export URL
            const exportUrl = '?poker_export=' + format + '&tournament_id=' + tournamentId;
            window.open(exportUrl, '_blank');
        }
    });

    // Lazy load images in tournament descriptions
    $('.tournament-description img, .series-description img').each(function() {
        const $img = $(this);
        $img.on('error', function() {
            $(this).replaceWith('<p class="image-error">Image not available</p>');
        });
    });

    // Mobile table improvements
    if (window.innerWidth <= 768) {
        $('.tournament-results-table, .player-stats-table').each(function() {
            const $table = $(this);

            // Add horizontal scroll indicator
            if ($table.width() > $table.parent().width()) {
                $table.parent().addClass('has-horizontal-scroll');

                // Add scroll hint
                $table.parent().before(
                    '<div class="scroll-hint">← Swipe or scroll to see more →</div>'
                );
            }
        });
    }

    // Search/filter functionality for player lists
    $('#player-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.player-list-item').each(function() {
            const $item = $(this);
            const playerName = $item.find('.player-name').text().toLowerCase();
            const isVisible = playerName.includes(searchTerm);
            $item.toggle(isVisible);
        });
    });

    // Date range filter for tournaments
    $('#tournament-date-filter').on('change', function() {
        const filter = $(this).val();
        const $tournaments = $('.tournament-list-item');

        if (filter === 'all') {
            $tournaments.show();
            return;
        }

        const now = new Date();
        let cutoffDate = new Date();

        switch (filter) {
            case '30days':
                cutoffDate.setDate(now.getDate() - 30);
                break;
            case '90days':
                cutoffDate.setDate(now.getDate() - 90);
                break;
            case '1year':
                cutoffDate.setFullYear(now.getFullYear() - 1);
                break;
        }

        $tournaments.each(function() {
            const $item = $(this);
            const itemDate = new Date($item.data('date'));
            const isVisible = itemDate >= cutoffDate;
            $item.toggle(isVisible);
        });
    });

    // Initialize tooltips
    $('.has-tooltip').each(function() {
        const $element = $(this);
        const tooltipText = $element.attr('title') || $element.data('tooltip');

        if (tooltipText) {
            $element.on('mouseenter', function(e) {
                const $tooltip = $('<div class="poker-tooltip">' + tooltipText + '</div>');
                $('body').append($tooltip);

                $tooltip.css({
                    position: 'absolute',
                    top: e.pageY - 30,
                    left: e.pageX + 10
                }).fadeIn(200);
            }).on('mouseleave', function() {
                $('.poker-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        }
    });

    // Add CSS for dynamic elements
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .sort-asc::after { content: ' ↑'; color: #4CAF50; }
            .sort-desc::after { content: ' ↓'; color: #e74c3c; }
            .player-tabs .player-tab { display: inline-block; padding: 10px 20px; background: #f0f0f0; border: none; cursor: pointer; margin-right: 5px; border-radius: 5px 5px 0 0; }
            .player-tabs .player-tab.active { background: #4CAF50; color: white; }
            .player-tab-panel { display: none; padding: 20px; border: 1px solid #ddd; border-radius: 0 5px 5px 5px; }
            .player-tab-panel.active { display: block; }
            .tournament-expand-toggle { background: #4CAF50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
            .tournament-expand-toggle:hover { background: #45a049; }
            .print-tournament, .export-tournament { background: #3498db; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-left: 8px; }
            .print-tournament:hover, .export-tournament:hover { background: #2980b9; }
            .has-horizontal-scroll { position: relative; }
            .scroll-hint { background: #e3f2fd; padding: 8px; text-align: center; font-size: 14px; color: #1976d2; margin-bottom: 10px; border-radius: 4px; }
            .image-error { color: #999; font-style: italic; }
            .poker-tooltip { background: #333; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; z-index: 1000; }
            .player-list-item { transition: background-color 0.2s; }
            .player-list-item:hover { background: #f5f5f5; }
        `)
        .appendTo('head');

    // Console log for debugging
    console.log('Poker Tournament Import Frontend JavaScript loaded');
});