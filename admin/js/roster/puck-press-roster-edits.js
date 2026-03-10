(function ($) {

    let currentEditingPlayerId = null;
    let originalPlayerValues   = {};

    // Exposed globally so puck-press-add-player.js and puck-press-admin-shared.js can call it
    window.applyEditHighlights = () => {
        $('#pp-roster-edits-table tbody tr:not(.pp-row-deleted)').each(function () {
            const $row = $(this);
            let overrides = [];
            try { overrides = JSON.parse($row.attr('data-overrides') || '[]'); } catch (e) {}
            const modId = $row.attr('data-mod-id');

            $row.find('td[data-field]').each(function () {
                const $td    = $(this);
                const field  = $td.attr('data-field');
                if (overrides.indexOf(field) !== -1) {
                    $td.addClass('pp-cell-overridden');
                    if (!$td.find('.pp-revert-btn').length) {
                        $td.append(
                            `<button class="pp-revert-btn" title="Revert to original" data-mod-id="${modId}" data-fields="${field}">&#x2715;</button>`
                        );
                    }
                } else {
                    $td.removeClass('pp-cell-overridden');
                    $td.find('.pp-revert-btn').remove();
                }
            });
        });
    };

    jQuery(() => {

        //============================================================//
        //   Dim / restore helpers                                     //
        //============================================================//
        const dimEditListStyles = () => {
            $('#pp-card-roster-preview, #pp-card-roster-edits-table, .pp-modal').css({
                'opacity': '0.5',
                'pointer-events': 'none'
            });
        };
        const restoreEditListStyles = () => {
            $('#pp-card-roster-preview, #pp-card-roster-edits-table, .pp-modal').css({
                'opacity': '1',
                'pointer-events': 'auto'
            });
        };

        const afterRefresh = () => {
            restoreEditListStyles();
            applyEditHighlights();
        };

        const doWithRosterUpdate = (ajaxOptions) =>
            withRefresh(ajaxOptions, {
                dim: dimEditListStyles,
                restore: restoreEditListStyles,
                onSuccess: (response) => {
                    if (response.data && response.data.roster_table_html) {
                        $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                        afterRefresh();
                    } else {
                        restoreEditListStyles();
                    }
                }
            });

        //============================================================//
        //   Apply highlights on page load                            //
        //============================================================//
        applyEditHighlights();

        // Allow external scripts (e.g. bulk edit) to trigger afterRefresh after replacing the table
        $(document).on('pp:roster-table-replaced', afterRefresh);

        //============================================================//
        //   Edit Player Modal                                         //
        //============================================================//
        const $editPlayerModal = $('#pp-edit-player-modal');
        const $editPlayerForm  = $('#pp-edit-player-form');

        const closeEditPlayerModal = () => {
            $editPlayerModal.css('display', 'none');
            if ($editPlayerForm.length) { $editPlayerForm[0].reset(); }
        };

        $('#pp-edit-player-modal-close').on('click', closeEditPlayerModal);
        $('#pp-cancel-edit-player').on('click', closeEditPlayerModal);
        enableClickOutsideToClose($editPlayerModal, closeEditPlayerModal);

        const $editPlayerLoading = $('#pp-edit-player-loading');
        const showEditLoading    = () => $editPlayerLoading.addClass('is-loading');
        const hideEditLoading    = () => $editPlayerLoading.removeClass('is-loading');

        const openEditModalForPlayer = (playerId) => {
            currentEditingPlayerId = playerId;
            originalPlayerValues   = {};
            if ($editPlayerForm.length) { $editPlayerForm[0].reset(); }
            $editPlayerModal.css('display', 'flex');
            showEditLoading();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: { action: 'pp_get_player_data', player_id: playerId },
                success: (response) => {
                    if (response.success && response.data && response.data.player) {
                        prefillEditPlayerForm(response.data.player);
                    }
                    hideEditLoading();
                },
                error: () => hideEditLoading()
            });
        };

        const prefillEditPlayerForm = (player) => {
            if (!player) return;

            const posMap    = { 'F': 'forward', 'D': 'defense', 'G': 'goalie' };
            const shootsMap = { 'R': 'right', 'L': 'left' };
            const yearMap   = {
                'FR': 'freshman', 'FRESHMAN': 'freshman',
                'SO': 'sophomore', 'SOPHOMORE': 'sophomore',
                'JR': 'junior', 'JUNIOR': 'junior',
                'SR': 'senior', 'SENIOR': 'senior',
                'GR': 'graduate', 'GRADUATE': 'graduate'
            };

            const nameVal      = player.name || '';
            const numberVal    = player.number || '';
            const posRaw       = (player.pos || '').toUpperCase();
            const posVal       = posMap[posRaw] || player.pos || '';
            const htVal        = player.ht || '';
            const wtVal        = player.wt || '';
            const shootsRaw    = (player.shoots || '').toUpperCase();
            const shootsVal    = shootsMap[shootsRaw] || shootsRaw.toLowerCase();
            const hometownVal  = player.hometown || '';
            const lastTeamVal  = player.last_team || '';
            const yearRaw      = (player.year_in_school || '').toUpperCase();
            const yearVal      = yearMap[yearRaw] || yearRaw.toLowerCase() || '';
            const majorVal     = player.major || '';
            const headshotVal  = player.headshot_link || '';
            const heroImageVal = player.hero_image_url || '';

            $('#pp-edit-player-name').val(nameVal);
            $('#pp-edit-player-number').val(numberVal);
            $('#pp-edit-player-position').val(posVal);
            $('#pp-edit-player-height').val(htVal);
            $('#pp-edit-player-weight').val(wtVal);
            $('#pp-edit-player-shoots').val(shootsVal);
            $('#pp-edit-player-hometown').val(hometownVal);
            $('#pp-edit-player-last-team').val(lastTeamVal);
            $('#pp-edit-player-year').val(yearVal);
            $('#pp-edit-player-major').val(majorVal);
            $('#pp-edit-player-headshot-url').val(headshotVal);
            $('#pp-edit-player-hero-image-url').val(heroImageVal);

            originalPlayerValues = {
                name: nameVal, number: numberVal, pos: posVal, ht: htVal,
                wt: wtVal, shoots: shootsVal, hometown: hometownVal,
                last_team: lastTeamVal, year_in_school: yearVal,
                major: majorVal, headshot_link: headshotVal,
                hero_image_url: heroImageVal
            };
        };

        // Open edit modal on edit button click
        $(document).on('click', '.pp-edit-player-btn', function () {
            const playerId = $(this).data('player-id');
            openEditModalForPlayer(playerId);
        });

        // Edit modal form submission
        $('#pp-confirm-edit-player').on('click', () => {
            if ($editPlayerForm.length && !$editPlayerForm[0].checkValidity()) {
                $editPlayerForm[0].reportValidity();
                return;
            }

            const newValues = {
                name:           $('#pp-edit-player-name').val(),
                number:         $('#pp-edit-player-number').val(),
                pos:            $('#pp-edit-player-position').val(),
                ht:             $('#pp-edit-player-height').val(),
                wt:             $('#pp-edit-player-weight').val(),
                shoots:         $('#pp-edit-player-shoots').val(),
                hometown:       $('#pp-edit-player-hometown').val(),
                last_team:      $('#pp-edit-player-last-team').val(),
                year_in_school: $('#pp-edit-player-year').val(),
                major:          $('#pp-edit-player-major').val(),
                headshot_link:  $('#pp-edit-player-headshot-url').val(),
                hero_image_url: $('#pp-edit-player-hero-image-url').val()
            };

            const changedFields = {};
            Object.keys(newValues).forEach((key) => {
                if (newValues[key] !== (originalPlayerValues[key] || '')) {
                    changedFields[key] = newValues[key];
                }
            });

            if (Object.keys(changedFields).length === 0) {
                alert('No changes detected.');
                return;
            }

            const editData = {
                edit_action: 'update',
                fields: Object.assign({ external_id: currentEditingPlayerId }, changedFields)
            };

            closeEditPlayerModal();

            const formData = new FormData();
            formData.append('action', 'pp_update_player_edits');
            formData.append('edit_data', JSON.stringify(editData));

            doWithRosterUpdate({ data: formData, processData: false, contentType: false });
        });

        //============================================================//
        //   Revert field button                                       //
        //============================================================//
        $(document).on('click', '.pp-revert-btn', function (e) {
            e.stopPropagation();
            const modId  = $(this).data('mod-id');
            const fields = String($(this).data('fields')).split(',');
            doWithRosterUpdate({ data: { action: 'pp_revert_player_field', mod_id: modId, fields: fields } });
        });

        //============================================================//
        //   Restore deleted player button                             //
        //============================================================//
        $(document).on('click', '.pp-restore-player-btn', function () {
            const deleteModId = $(this).data('delete-mod-id');
            withRefresh(
                { data: { action: 'ajax_delete_player_edit', id: deleteModId } },
                {
                    dim: dimEditListStyles,
                    restore: restoreEditListStyles,
                    onSuccess: (response) => {
                        if (response.data && response.data.roster_table_html) {
                            $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                            afterRefresh();
                        } else {
                            refreshGamesTable(afterRefresh, restoreEditListStyles);
                        }
                    }
                }
            );
        });

        //============================================================//
        //   Delete player button                                      //
        //============================================================//
        $(document).on('click', '.pp-delete-player-btn', function () {
            const playerId   = $(this).data('player-id');
            const sourceType = $(this).data('source-type');

            if (!confirm('Are you sure you want to delete this player?')) return;

            if (sourceType === 'manual') {
                // Delete the insert mod row entirely
                doWithRosterUpdate({ data: { action: 'pp_delete_manual_player', player_id: playerId } });
            } else {
                // Sourced player — add a delete mod
                const editData = {
                    edit_action: 'delete',
                    fields: { external_id: playerId }
                };
                const formData = new FormData();
                formData.append('action', 'pp_update_player_edits');
                formData.append('edit_data', JSON.stringify(editData));
                doWithRosterUpdate({ data: formData, processData: false, contentType: false });
            }
        });

        //============================================================//
        //   WP Media picker for hero image                           //
        //============================================================//
        $(document).on('click', '.pp-hero-image-browse-btn', function (e) {
            e.preventDefault();
            const targetSelector = $(this).data('target');
            const frame = wp.media({
                title: 'Select Hero Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                $(targetSelector).val(attachment.url).trigger('input');
            });
            frame.open();
        });

        //============================================================//
        //   Helper: click-outside-to-close                           //
        //============================================================//
        //============================================================//
        //   Reset All Roster Edits                                   //
        //============================================================//
        $('#pp-reset-all-roster-edits').on('click', () => {
            if (!confirm('Reset all roster edits? This will remove every override, deletion, and manual player. This cannot be undone.')) {
                return;
            }
            doWithRosterUpdate({ data: { action: 'pp_reset_all_roster_edits' } });
        });

        function enableClickOutsideToClose($modal, closeCallback) {
            let mouseDownOutside = false;

            const onMouseDown = (e) => {
                mouseDownOutside = (e.target === $modal[0]);
            };
            const onMouseUp = (e) => {
                if (e.target === $modal[0] && mouseDownOutside) {
                    closeCallback();
                }
            };

            $modal.on('mousedown.modalClose', onMouseDown);
            $modal.on('mouseup.modalClose', onMouseUp);

            return () => {
                $modal.off('mousedown.modalClose', onMouseDown);
                $modal.off('mouseup.modalClose', onMouseUp);
            };
        }
    });

})(jQuery);
