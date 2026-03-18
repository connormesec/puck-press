(function ($) {
    jQuery(document).ready(function ($) {
        // Show only the selected stats template in the preview on load.
        for (const key in ppStatsTemplates.statsTemplates) {
            $(`.${key}_stats_container`).hide();
        }
        $(`.${ppStatsTemplates.selected_template}_stats_container`).show();

        // ── Color picker ──────────────────────────────────────────────────────
        createColorPickerController({
            modalId:                '#pp-stats-paletteModal',
            openBtnId:              '#pp-stats-colorPaletteBtn',
            closeBtnId:             '#pp-stats-palette-modal-close',
            cancelBtnId:            '#pp-cancel-save-stats-colors',
            saveBtnId:              '#pp-stats-palette-save-colors',
            formId:                 '#pp-stats-color-palette-form',
            colorFieldsContainerId: '#pp-stats-dynamic-color-fields',
            templateSelectorId:     '#pp-stats-template-selector',
            templatesData:          ppStatsTemplates,
            templatesKey:           'statsTemplates',
            ajaxAction:             'puck_press_update_stats_colors',
            containerSuffix:        '_stats_container',
            fontFieldsContainerId:  '#pp-stats-dynamic-font-fields',
        });

        // ── Column settings save ──────────────────────────────────────────────
        $('#pp-stats-save-columns').on('click', function () {
            const $btn = $(this);
            const $msg = $('#pp-stats-columns-msg');

            $btn.prop('disabled', true).text('Saving…');
            $msg.hide();

            const data = {
                action:                 'pp_save_stats_column_settings',
                nonce:                  ppStatsTemplates.columns_nonce,
                current_season_label:   $('#pp-stats-current-season-label').val().trim(),
                show_team:           $('input[name="show_team"]').is(':checked') ? 1 : 0,
                show_pim:            $('input[name="show_pim"]').is(':checked') ? 1 : 0,
                show_ppg:            $('input[name="show_ppg"]').is(':checked') ? 1 : 0,
                show_shg:            $('input[name="show_shg"]').is(':checked') ? 1 : 0,
                show_gwg:            $('input[name="show_gwg"]').is(':checked') ? 1 : 0,
                show_pts_per_game:   $('input[name="show_pts_per_game"]').is(':checked') ? 1 : 0,
                show_sh_pct:         $('input[name="show_sh_pct"]').is(':checked') ? 1 : 0,
                show_goalie_otl:     $('input[name="show_goalie_otl"]').is(':checked') ? 1 : 0,
                show_goalie_gaa:     $('input[name="show_goalie_gaa"]').is(':checked') ? 1 : 0,
                show_goalie_svpct:   $('input[name="show_goalie_svpct"]').is(':checked') ? 1 : 0,
                show_goalie_sa:      $('input[name="show_goalie_sa"]').is(':checked') ? 1 : 0,
                show_goalie_saves:   $('input[name="show_goalie_saves"]').is(':checked') ? 1 : 0,
            };

            $.ajax({
                url:  ajaxurl,
                type: 'POST',
                data: data,
                success(response) {
                    if (response.success) {
                        $msg
                            .text('Saved!')
                            .css('color', 'green')
                            .show()
                            .delay(2500)
                            .fadeOut();
                        if (response.data.preview_html) {
                            $('#pp-stats-preview').html(response.data.preview_html);
                            // Re-initialize stats containers in the replaced HTML so
                            // season select, sorting, and filtering continue to work.
                            if (typeof window.ppStatsInitContainer === 'function') {
                                $('#pp-stats-preview .standard_stats_container').each(function () {
                                    window.ppStatsInitContainer(this);
                                });
                            }
                        }
                    } else {
                        $msg
                            .text('Save failed. Please try again.')
                            .css('color', '#c0392b')
                            .show();
                    }
                },
                error() {
                    $msg
                        .text('Network error. Please try again.')
                        .css('color', '#c0392b')
                        .show();
                },
                complete() {
                    $btn.prop('disabled', false).text('Save Column Settings');
                },
            });
        });
    });
})(jQuery);
