(function ($) {
    jQuery(document).ready(function ($) {
        for (var key in ppStatLeadersData.leadersTemplates) {
            $('.' + key + '_leaders_container').hide();
        }
        $('.' + ppStatLeadersData.selected_template + '_leaders_container').show();

        // ── Color picker controller ───────────────────────────────────────────
        createColorPickerController({
            modalId:                '#pp-stat-leaders-paletteModal',
            openBtnId:              '#pp-stat-leaders-colorPaletteBtn',
            closeBtnId:             '#pp-stat-leaders-palette-modal-close',
            cancelBtnId:            '#pp-cancel-save-stat-leaders-colors',
            saveBtnId:              '#pp-stat-leaders-palette-save-colors',
            formId:                 '#pp-stat-leaders-color-palette-form',
            colorFieldsContainerId: '#pp-stat-leaders-dynamic-color-fields',
            templateSelectorId:     '#pp-stat-leaders-template-selector',
            templatesData:          ppStatLeadersData,
            templatesKey:           'leadersTemplates',
            ajaxAction:             'pp_update_stat_leaders_colors',
            containerSuffix:        '_leaders_container',
            fontFieldsContainerId:  '#pp-stat-leaders-dynamic-font-fields',
            extraData: function () {
                var teamColors = {};
                $('#pp-stat-leaders-team-color-fields input[type="color"]').each(function () {
                    var match = $(this).attr('name').match(/\[(.+)\]$/);
                    if (match) {
                        teamColors[match[1]] = $(this).val();
                    }
                });
                return {
                    team_colors: teamColors,
                    more_link:   $('#pp-stat-leaders-more-link').val().trim(),
                    show_team:   $('#pp-stat-leaders-show-team').is(':checked') ? 1 : 0,
                };
            },
        });

        // ── Render team color fields ──────────────────────────────────────────
        function renderTeamColorFields() {
            var $container = $('#pp-stat-leaders-team-color-fields').empty();
            var teamNames  = ppStatLeadersData.team_names || [];
            var teamColors = ppStatLeadersData.team_colors || {};

            if (teamNames.length === 0) {
                $container.append('<p style="font-size:0.8rem;color:#5f6368;margin:0;">No teams found in roster data.</p>');
                return;
            }

            teamNames.forEach(function (teamName) {
                var saved = teamColors[teamName] || '#8B1A2E';
                $container.append(
                    '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">' +
                    '<label style="font-size:0.875rem;flex:1;min-width:0;">' + $('<span>').text(teamName).html() + '</label>' +
                    '<input type="color" name="team_colors[' + $('<span>').text(teamName).html() + ']" value="' + saved + '" style="width:40px;height:28px;padding:2px;border:1px solid #ddd;border-radius:4px;cursor:pointer;">' +
                    '</div>'
                );
            });
        }

        // Render team color fields when modal opens
        $('#pp-stat-leaders-colorPaletteBtn').on('click', function () {
            renderTeamColorFields();
        });

        // ── Stat Leaders settings save ────────────────────────────────────────
        $('#pp-stat-leaders-save-settings').on('click', function () {
            var $btn = $(this);
            var $msg = $('#pp-stat-leaders-settings-msg');

            $btn.prop('disabled', true).text('Saving…');
            $msg.hide();

            var data = {
                action:         'pp_save_stat_leaders_settings',
                nonce:          ppStatLeadersData.settings_nonce,
                show_team:      $('input[name="pp_sl_show_team"]').is(':checked')    ? 1 : 0,
                show_goals:     $('input[name="pp_sl_show_goals"]').is(':checked')   ? 1 : 0,
                show_assists:   $('input[name="pp_sl_show_assists"]').is(':checked') ? 1 : 0,
                show_points:    $('input[name="pp_sl_show_points"]').is(':checked')  ? 1 : 0,
                show_pim:       $('input[name="pp_sl_show_pim"]').is(':checked')     ? 1 : 0,
                show_gaa:       $('input[name="pp_sl_show_gaa"]').is(':checked')     ? 1 : 0,
                show_saves:     $('input[name="pp_sl_show_saves"]').is(':checked')   ? 1 : 0,
                show_sv_pct:    $('input[name="pp_sl_show_sv_pct"]').is(':checked')  ? 1 : 0,
                show_wins:      $('input[name="pp_sl_show_wins"]').is(':checked')    ? 1 : 0,
            };

            $.ajax({
                url:  ajaxurl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        $msg.text('Saved!').css('color', 'green').show().delay(2500).fadeOut();
                        if (response.data && response.data.preview_html) {
                            $('#pp-stat-leaders-preview').html(response.data.preview_html);
                        }
                    } else {
                        $msg.text('Save failed. Please try again.').css('color', '#c0392b').show();
                    }
                },
                error: function () {
                    $msg.text('Network error. Please try again.').css('color', '#c0392b').show();
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Save Settings');
                },
            });
        });
    });
})(jQuery);
