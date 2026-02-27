(function ($) {
    window.gameScheduleInitializers = [];

    window.withRefresh = function (ajaxOptions, { dim, restore, onSuccess }) {
        dim();
        return $.ajax(Object.assign(
            { url: ajaxurl, type: 'POST' },
            ajaxOptions,
            {
                success: (response) => {
                    if (response.success) {
                        onSuccess(response);
                    } else {
                        console.error('withRefresh: action failed', response);
                        restore();
                    }
                },
                error: () => restore()
            }
        ));
    };

}(jQuery));
function refreshGamesTable(successCallback, errorCallback) {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab === 'roster') {
        return (jQuery).ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'pp_refresh_all_roster_sources', // The action hook for refreshing all sources
            },
            success: (response) => {
                if (response.success) {
                    // Replace the table with the refreshed game table from the response
                    (jQuery)('#pp-roster-preview').html(response.data.refreshed_roster_preview_html);
                    if (response.data.refreshed_edits_table_html) {
                        (jQuery)('#pp-roster-edits-table').replaceWith(response.data.refreshed_edits_table_html);
                        if (typeof applyEditHighlights === 'function') {
                            applyEditHighlights();
                        }
                    }
                    for (let key in ppRosterTemplates.rosterTemplates) {
                        (jQuery)(`.${key}_roster_container`).hide();
                    }
                    (jQuery)(`.${ppRosterTemplates.selected_template}_roster_container`).show();

                    // Log import results to the browser console
                    const results = response.data.raw_roster_table_results || {};
                    const messages = (results.messages || []).filter(m => typeof m === 'string');
                    const errors   = results.errors || [];
                    console.group('Puck Press — Roster Refresh Log');
                    messages.forEach(m => console.log(m));
                    errors.forEach(e => console.warn(`ERROR [${e.source || ''}]: ${e.message || JSON.stringify(e)}`));
                    console.groupEnd();

                    // Call the success callback if provided
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else if (response.data && response.data.message === 'No active sources to import.') {
                    console.log('No active sources to import.');
                    // Call the success callback if provided
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else {
                    alert('Failed to refresh the roster table.');
                    console.error('Error:', response);

                    // Call the error callback if provided
                    if (typeof errorCallback === 'function') {
                        errorCallback(response);
                    }
                }
            },
            error: (xhr, status, error) => {
                alert('An error occurred while refreshing the games table.');
                console.error('Error:', error);

                // Call the error callback if provided
                if (typeof errorCallback === 'function') {
                    errorCallback(xhr, status, error);
                }
            }
        });
    } else {
        //is schedule tab
        return (jQuery).ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'pp_refresh_all_sources', // The action hook for refreshing all sources
            },
            success: (response) => {
                if (response.success) {
                    // Replace the table with the refreshed game table from the response
                    (jQuery)('#pp-games-table').html(response.data.refreshed_game_table_ui);
                    (jQuery)('#pp-game-schedule-preview').html(response.data.refreshed_game_preview_html);
                    for (let key in ppScheduleTemplates.scheduleTemplates) {
                        (jQuery)(`.${key}_schedule_container`).hide();
                    }
                    (jQuery)(`.${ppScheduleTemplates.selected_template}_schedule_container`).show();

                    (jQuery)('#pp-game-slider-preview').html(response.data.refreshed_slider_preview_html);
                    for (let key in ppSliderTemplates.sliderTemplates) {
                        (jQuery)(`.${key}_slider_container`).hide();
                    }
                    (jQuery)(`.${ppSliderTemplates.selected_template}_slider_container`).show();

                    // Dynamically initialize everything
                    gameScheduleInitializers.forEach(initFn => {
                        if (typeof initFn === 'function') {
                            initFn();
                        }
                    });

                    console.log('Games table refreshed successfully.');
                    console.log('Response:', response);

                    // Call the success callback if provided
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else if (response.data && response.data.message === 'No active sources to import.') {
                    console.log('No active sources to import.');
                    // Call the success callback if provided
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else {
                    alert('Failed to refresh the games table.');
                    console.error('Error:', response);

                    // Call the error callback if provided
                    if (typeof errorCallback === 'function') {
                        errorCallback(response);
                    }
                }
            },
            error: (xhr, status, error) => {
                alert('An error occurred while refreshing the games table.');
                console.error('Error:', error);

                // Call the error callback if provided
                if (typeof errorCallback === 'function') {
                    errorCallback(xhr, status, error);
                }
            }
        });
    }
}