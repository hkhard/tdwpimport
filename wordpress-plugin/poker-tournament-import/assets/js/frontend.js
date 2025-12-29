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

    // Series Tab Navigation
    $('.series-tabs-container').each(function() {
        const $container = $(this);
        const $nav = $container.find('.series-tabs-nav');
        const $content = $container.find('.series-tabs-content');
        const seriesId = $container.data('series-id');

        // Tab click handler
        $nav.on('click', '.series-tab-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const tabName = $btn.data('tab');

            // Update active states
            $nav.find('.series-tab-btn').removeClass('active');
            $btn.addClass('active');

            // Load tab content via AJAX
            $content.addClass('loading');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_series_tab_content',
                    series_id: seriesId,
                    tab: tabName,
                    nonce: pokerImportFrontend.nonce || ''
                },
                success: function(response) {
                    $content.html(response).removeClass('loading');

                    // Re-initialize functionality in loaded content
                    initializeTabContent(tabName);
                },
                error: function() {
                    $content.html('<div class="error-message">' +
                        (pokerImportFrontend.messages?.tabError || 'Error loading content. Please try again.') +
                        '</div>').removeClass('loading');
                }
            });

            // Update URL hash for direct linking
            if (history.pushState) {
                const newUrl = window.location.pathname + '#tab-' + tabName;
                history.pushState({tab: tabName}, '', newUrl);
            }
        });

        // Handle URL hash on page load
        if (window.location.hash) {
            const hash = window.location.hash.substring(1);
            if (hash.startsWith('tab-')) {
                const tabName = hash.substring(4);
                $nav.find('.series-tab-btn[data-tab="' + tabName + '"]').trigger('click');
            }
        }

        // Handle browser back/forward
        $(window).on('popstate', function(e) {
            if (e.originalEvent.state && e.originalEvent.state.tab) {
                $nav.find('.series-tab-btn[data-tab="' + e.originalEvent.state.tab + '"]').trigger('click');
            }
        });
    });

    // Initialize content within tabs
    function initializeTabContent(tabName) {
        switch (tabName) {
            case 'players':
                initializePlayerSearch();
                break;
            case 'results':
                initializeLoadMore();
                break;
            case 'statistics':
                initializeTableSorting();
                break;
            case 'overview':
                // Overview tab doesn't need special initialization
                break;
        }
    }

    // Player search functionality
    function initializePlayerSearch() {
        const $searchInput = $('#player-search');
        const $playersGrid = $('.players-grid');
        const $visibleCount = $('#visible-players');
        const $totalCount = $('#total-players');

        if ($searchInput.length && $playersGrid.length) {
            $searchInput.on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                const $playerCards = $playersGrid.find('.player-card');
                let visibleCount = 0;

                $playerCards.each(function() {
                    const $card = $(this);
                    const playerName = $card.data('player-name') || '';

                    if (searchTerm === '' || playerName.includes(searchTerm)) {
                        $card.show();
                        visibleCount++;
                    } else {
                        $card.hide();
                    }
                });

                if ($visibleCount.length) {
                    $visibleCount.text(visibleCount);
                }
            });
        }
    }

    // Load more functionality
    function initializeLoadMore() {
        $('.load-more-results').on('click', function() {
            const $btn = $(this);
            const seriesId = $btn.data('series-id');
            const offset = $btn.data('offset');
            const $tableWrapper = $('.series-results-table-wrapper');
            const limit = 20;

            $btn.prop('disabled', true).text('Loading...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tdwp_series_load_more',
                    series_id: seriesId,
                    offset: offset,
                    limit: limit,
                    nonce: pokerImportFrontend.nonce || ''
                },
                success: function(response) {
                    const $newRows = $(response);
                    const $tableBody = $('.series-results-table tbody');
                    $tableBody.append($newRows);

                    // Update button data
                    $btn.data('offset', offset + limit);

                    // Hide button if no more results
                    if ($newRows.length < limit) {
                        $btn.hide();
                    } else {
                        $btn.prop('disabled', false).text('Load More');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Load More');
                    alert('Error loading more results. Please try again.');
                }
            });
        });
    }

    // Table sorting functionality
    function initializeTableSorting() {
        $('.leaderboard-table').each(function() {
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
    }

    // Smooth scroll to tabs when clicking internal links
    $('a[href*="#tab-"]').on('click', function(e) {
        const href = $(this).attr('href');
        if (href.includes('#tab-')) {
            e.preventDefault();
            const tabName = href.split('#tab-')[1];

            $('.series-tab-btn[data-tab="' + tabName + '"]').trigger('click');

            // Smooth scroll to tab container
            $('html, body').animate({
                scrollTop: $('.series-tabs-container').offset().top - 100
            }, 500);
        }
    });

    // Tournament chronology reconstruction functions
    window.attemptChronologicalReconstruction = function(tournamentId) {
        const $resultsContainer = $('#reconstruction-results-' + tournamentId);

        $resultsContainer.html('<p><strong>Attempting chronological reconstruction...</strong></p>').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_reconstruct_chronology',
                tournament_id: tournamentId,
                nonce: pokerImportFrontend.nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    $resultsContainer.html('<div class="success-message" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin: 10px 0;">' +
                        '<strong>✅ ' + response.data.message + '</strong></div>' +
                        '<button type="button" class="button button-small" onclick="location.reload()">Refresh Page to See Results</button>');
                } else {
                    $resultsContainer.html('<div class="error-message" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin: 10px 0;">' +
                        '<strong>❌ ' + (response.data.message || 'Reconstruction failed') + '</strong></div>');
                }
            },
            error: function() {
                $resultsContainer.html('<div class="error-message" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin: 10px 0;">' +
                    '<strong>❌ Network error during reconstruction</strong></div>');
            }
        });
    };

    window.showTdtUploadInterface = function(tournamentId) {
        const $resultsContainer = $('#reconstruction-results-' + tournamentId);

        $resultsContainer.html(`
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; margin: 10px 0;">
                <h4>Upload Original .tdt File</h4>
                <p>Upload the original Tournament Director file to enable perfect chronological processing.</p>
                <form id="tdt-upload-form-${tournamentId}" enctype="multipart/form-data">
                    <input type="file" name="tdt_file" accept=".tdt" required style="margin: 10px 0;">
                    <br>
                    <button type="submit" class="button button-primary">Upload and Process</button>
                    <button type="button" class="button" onclick="$('#reconstruction-results-${tournamentId}').hide()">Cancel</button>
                </form>
            </div>
        `).show();

        // Handle form submission
        $(`#tdt-upload-form-${tournamentId}`).on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'poker_upload_tdt_for_tournament');
            formData.append('tournament_id', tournamentId);
            formData.append('nonce', pokerImportFrontend.nonce || '');

            $resultsContainer.html('<p><strong>Uploading and processing .tdt file...</strong></p>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $resultsContainer.html('<div class="success-message" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin: 10px 0;">' +
                            '<strong>✅ ' + response.data.message + '</strong></div>' +
                            '<button type="button" class="button button-small" onclick="location.reload()">Refresh Page to See Results</button>');
                    } else {
                        $resultsContainer.html('<div class="error-message" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin: 10px 0;">' +
                            '<strong>❌ ' + (response.data.message || 'Upload failed') + '</strong></div>');
                    }
                },
                error: function() {
                    $resultsContainer.html('<div class="error-message" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin: 10px 0;">' +
                        '<strong>❌ Network error during upload</strong></div>');
                }
            });
        });
    };

    // Player leaderboard table sorting
    $('.player-leaderboard-table .sortable').on('click', function() {
        const $header = $(this);
        const sortColumn = $header.data('sort');
        const $table = $header.closest('.player-leaderboard-table');
        const $rows = $table.find('.table-row');

        // Determine sort direction
        const isActive = $header.hasClass('active');
        const currentDirection = $header.hasClass('desc') ? 'desc' : 'asc';
        const newDirection = isActive && currentDirection === 'desc' ? 'asc' : 'desc';

        // Remove active/desc from all headers
        $table.find('.sortable').removeClass('active desc');

        // Add active and direction to clicked header
        $header.addClass('active');
        if (newDirection === 'desc') {
            $header.addClass('desc');
        }

        // Update sort indicators
        $table.find('.sort-indicator').text('');
        $header.find('.sort-indicator').text(newDirection === 'desc' ? '▼' : '▲');

        // Get rows as array for sorting
        const rowsArray = $rows.toArray();

        // Sort rows
        rowsArray.sort(function(a, b) {
            const $a = $(a);
            const $b = $(b);

            // Get values from data attributes
            let aValue, bValue;

            if (sortColumn === 'season_points') {
                aValue = parseFloat($a.data('season-points')) || 0;
                bValue = parseFloat($b.data('season-points')) || 0;
            } else if (sortColumn === 'points') {
                aValue = parseFloat($a.data('points')) || 0;
                bValue = parseFloat($b.data('points')) || 0;
            }

            // Apply sort direction
            if (newDirection === 'desc') {
                return bValue - aValue;
            } else {
                return aValue - bValue;
            }
        });

        // Re-append sorted rows and update rank numbers
        rowsArray.forEach(function(row, index) {
            const $row = $(row);
            $row.find('.rank-cell').text(index + 1);
            $table.append($row);
        });
    });

    // Tournament Grid Loading (Dashboard)
    let tournamentsPage = 1;
    let tournamentsLoading = false;
    let tournamentsHasMore = true;

    // Season filter change handler
    $('#tournament-season-select').on('change', function() {
        tournamentsPage = 1;
        tournamentsHasMore = true;
        loadTournaments($(this).val(), true);
    });

    // Load tournaments function
    function loadTournaments(seasonId, reset) {
        if (tournamentsLoading || (!tournamentsHasMore && !reset)) return;

        tournamentsLoading = true;
        const $container = $('#tournaments-grid-container');
        const $loadMoreContainer = $('.tournament-load-more-container');

        if (reset) {
            $container.html('<div class="loading-spinner">Loading tournaments...</div>');
            $loadMoreContainer.hide();
            tournamentsPage = 1;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_dashboard_tournaments_filtered',
                season_id: seasonId || 'all',
                page: tournamentsPage,
                nonce: pokerImportFrontend.nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    const tournaments = response.data.tournaments;

                    if (reset) {
                        $container.empty();
                    }

                    if (tournaments.length > 0) {
                        // Build tournament table HTML matching player tab style
                        let html = reset ? '<div class="tournament-leaderboard-table">' : '';

                        if (reset) {
                            html += '<div class="table-header">';
                            html += '<div class="header-rank">RANK</div>';
                            html += '<div class="header-tournament">TOURNAMENT</div>';
                            html += '<div class="header-date">DATE</div>';
                            html += '<div class="header-players">PLAYERS</div>';
                            html += '<div class="header-prize">PRIZE POOL</div>';
                            html += '<div class="header-winner">WINNER</div>';
                            html += '</div>';
                        }

                        tournaments.forEach(function(tournament) {
                            html += '<div class="table-row">';
                            html += '<div class="rank-cell">' + tournament.rank + '</div>';
                            html += '<div class="tournament-cell"><a href="' + tournament.url + '">' + tournament.title + '</a></div>';
                            html += '<div class="date-cell">' + tournament.date + '</div>';
                            html += '<div class="players-cell">' + tournament.players + '</div>';
                            html += '<div class="prize-cell">' + tournament.prize_pool + '</div>';
                            html += '<div class="winner-cell">' + (tournament.winner ? tournament.winner : '--') + '</div>';
                            html += '</div>';
                        });

                        if (reset) {
                            html += '</div>';
                        }

                        if (reset) {
                            $container.html(html);
                        } else {
                            $container.find('.tournament-leaderboard-table').append(html);
                        }

                        tournamentsHasMore = response.data.has_more;

                        if (tournamentsHasMore) {
                            $loadMoreContainer.show();
                        } else {
                            $loadMoreContainer.hide();
                        }
                    } else {
                        if (reset) {
                            $container.html('<p>No tournaments found.</p>');
                        }
                        $loadMoreContainer.hide();
                    }
                } else {
                    $container.html('<div class="error-message">Error loading tournaments.</div>');
                }
            },
            error: function() {
                $container.html('<div class="error-message">Network error loading tournaments.</div>');
            },
            complete: function() {
                tournamentsLoading = false;
            }
        });
    }

    // Load more button handler
    $('#tournament-load-more-btn').on('click', function() {
        tournamentsPage++;
        const seasonId = $('#tournament-season-select').val();
        loadTournaments(seasonId, false);
    });

    // Auto-load tournaments when tournaments tab is shown
    if ($('#tournaments-tab').length && $('#tournaments-grid-container').length) {
        // Check if tournaments tab is active on load
        if ($('#tournaments-tab').hasClass('active')) {
            loadTournaments('all', true);
        }

        // Load when tab is clicked
        $('.nav-tab[data-view="tournaments"]').on('click', function() {
            if ($('#tournaments-grid-container .loading-spinner').length) {
                loadTournaments('all', true);
            }
        });
    }

    // Frontend statistics refresh button
    $('#refresh-stats-btn').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $icon = $button.find('.icon');
        const seasonId = $('#tournament-season-select').val();

        $button.prop('disabled', true);
        $icon.addClass('spinning');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdwp_frontend_refresh_statistics',
                season_id: seasonId || 'all',
                nonce: pokerImportFrontend.nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    // Reload the page to show updated stats
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to refresh statistics'));
                }
            },
            error: function() {
                alert('Network error. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false);
                $icon.removeClass('spinning');
            }
        });
    });

    // Console log for debugging
    console.log('Poker Tournament Import Frontend JavaScript loaded');
});