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
    if (tab === 'teams') {
        const teamId = parseInt((jQuery)('#pp-active-team-id').val(), 10) || 0;
        const requestData = { action: 'pp_refresh_team', team_id: teamId };
        console.log('[refreshGamesTable] → pp_refresh_team request:', requestData);
        return (jQuery).ajax({
            url: ajaxurl,
            type: 'POST',
            data: requestData,
            success: (response) => {
                console.log('[refreshGamesTable] ← pp_refresh_team response:', response);
                if (response.success) {
                    const $existing = (jQuery)('#pp-games-table');
                    console.log('[refreshGamesTable] #pp-games-table found:', $existing.length, '| replacing with HTML length:', (response.data.refreshed_game_table_ui || '').length);
                    $existing.replaceWith(response.data.refreshed_game_table_ui);
                    const results = response.data.results || {};
                    const messages = (results.messages || []).filter(m => typeof m === 'string');
                    const errors = results.errors || [];
                    console.group('Team Refresh Log');
                    messages.forEach(m => console.log(m));
                    errors.forEach(e => console.warn('ERROR [' + (e.source || '') + ']: ' + (e.message || JSON.stringify(e))));
                    console.groupEnd();
                    if (typeof successCallback === 'function') successCallback(response);
                } else {
                    console.error('[refreshGamesTable] Refresh failed:', response.data && response.data.message);
                    if (typeof errorCallback === 'function') errorCallback(response);
                }
            },
            error: (xhr, status, error) => {
                console.error('[refreshGamesTable] AJAX error:', status, error, xhr.responseText);
                if (typeof errorCallback === 'function') errorCallback(xhr, status, error);
            },
        });
    }
    if (tab === 'roster') {
        const rosterId = (window.ppRosterAdmin && window.ppRosterAdmin.activeRosterId) ? parseInt(window.ppRosterAdmin.activeRosterId, 10) : 1;
        return (jQuery).ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'pp_refresh_all_roster_groups', // The action hook for refreshing all sources
                roster_id: rosterId,
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
                    if (response.data.refreshed_sources_html) {
                        (jQuery)('#pp-data-sources-table').html(response.data.refreshed_sources_html);
                    }
                    for (let key in ppRosterTemplates.rosterTemplates) {
                        (jQuery)(`.${key}_roster_container`).hide();
                    }
                    (jQuery)(`.${ppRosterTemplates.selected_template}_roster_container`).show();

                    // Log import results to the browser console
                    const groupsResults = response.data.groups_results || [];
                    console.group('Puck Press — Roster Refresh Log (All Groups)');
                    groupsResults.forEach(g => {
                        console.group(`Group: ${g.name} (id=${g.roster_id})`);
                        if (g.error) {
                            console.error(`Group failed: ${g.error}`);
                        } else {
                            const messages = (g.raw.messages || []).filter(m => typeof m === 'string');
                            const errors   = g.raw.errors || [];
                            messages.forEach(m => console.log(m));
                            errors.forEach(e => console.warn(`ERROR [${e.source || ''}]: ${e.message || JSON.stringify(e)}`));
                        }
                        console.groupEnd();
                    });
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
        const scheduleId = parseInt((jQuery)('#pp-active-new-schedule-id').val(), 10) || 0;
        return (jQuery).ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pp_refresh_schedule_sources',
                scope: 'schedule',
                schedule_id: scheduleId,
            },
            success: (response) => {
                if (response.success) {
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

                    gameScheduleInitializers.forEach(initFn => {
                        if (typeof initFn === 'function') {
                            initFn();
                        }
                    });

                    const results = response.data.results || [];
                    console.group('Puck Press — Schedule Refresh Log');
                    results.forEach(r => {
                        console.group(`Team id=${r.team_id}`);
                        (r.messages || []).forEach(m => console.log(m));
                        (r.errors || []).forEach(e => console.warn(`ERROR [${e.source || ''}]: ${e.message || JSON.stringify(e)}`));
                        console.groupEnd();
                    });
                    console.groupEnd();

                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else if (response.data && response.data.message === 'No active sources to import.') {
                    console.log('No active sources to import.');
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else {
                    alert('Failed to refresh the games table.');
                    console.error('Error:', response);

                    if (typeof errorCallback === 'function') {
                        errorCallback(response);
                    }
                }
            },
            error: (xhr, status, error) => {
                alert('An error occurred while refreshing the games table.');
                console.error('Error:', error);

                if (typeof errorCallback === 'function') {
                    errorCallback(xhr, status, error);
                }
            }
        });
    }
}