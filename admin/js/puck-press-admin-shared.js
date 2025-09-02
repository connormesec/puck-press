(function ($) {
    window.gameScheduleInitializers = [];

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
            success: function (response) {
                if (response.success) {
                    // Replace the table with the refreshed game table from the response
                    console.log(response.data);
                    (jQuery)('#pp-roster-table').html(response.data.refreshed_roster_table_ui);
                    (jQuery)('#pp-roster-preview').html(response.data.refreshed_roster_preview_html);
                    for (let key in ppRosterTemplates.rosterTemplates) {
                        (jQuery)(`.${key}_container`).hide();
                    }
                    (jQuery)(`.${ppRosterTemplates.selected_template}_container`).show();



                    console.log('Roster table refreshed successfully.');
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
                    alert('Failed to refresh the roster table.');
                    console.error('Error:', response);

                    // Call the error callback if provided
                    if (typeof errorCallback === 'function') {
                        errorCallback(response);
                    }
                }
            },
            error: function (xhr, status, error) {
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
            success: function (response) {
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
            error: function (xhr, status, error) {
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