(function ($) {

    var currentEditingPlayerId = null;
    var originalPlayerValues   = {};

    // Exposed globally so puck-press-add-player.js and puck-press-admin-shared.js can call it
    window.applyEditHighlights = function () {
        $('#pp-roster-edits-table tbody tr:not(.pp-row-deleted)').each(function () {
            var $row      = $(this);
            var overrides = [];
            try { overrides = JSON.parse($row.attr('data-overrides') || '[]'); } catch (e) {}
            var modId = $row.attr('data-mod-id');

            $row.find('td[data-field]').each(function () {
                var $td    = $(this);
                var field  = $td.attr('data-field');
                if (overrides.indexOf(field) !== -1) {
                    $td.addClass('pp-cell-overridden');
                    if (!$td.find('.pp-revert-btn').length) {
                        $td.append(
                            '<button class="pp-revert-btn" title="Revert to original" ' +
                            'data-mod-id="' + modId + '" data-fields="' + field + '">&#x2715;</button>'
                        );
                    }
                } else {
                    $td.removeClass('pp-cell-overridden');
                    $td.find('.pp-revert-btn').remove();
                }
            });
        });
    };

    jQuery(document).ready(function ($) {

        //============================================================//
        //   Dim / restore helpers                                     //
        //============================================================//
        var dimEditListStyles = function () {
            $('#pp-card-roster-preview, #pp-card-roster-edits-table, .pp-modal').css({
                'opacity': '0.5',
                'pointer-events': 'none'
            });
        };
        var restoreEditListStyles = function () {
            $('#pp-card-roster-preview, #pp-card-roster-edits-table, .pp-modal').css({
                'opacity': '1',
                'pointer-events': 'auto'
            });
        };

        var afterRefresh = function () {
            restoreEditListStyles();
            applyEditHighlights();
        };

        //============================================================//
        //   Apply highlights on page load                            //
        //============================================================//
        applyEditHighlights();

        //============================================================//
        //   Edit Player Modal                                         //
        //============================================================//
        var $editPlayerModal = $('#pp-edit-player-modal');
        var $editPlayerForm  = $('#pp-edit-player-form');

        function closeEditPlayerModal() {
            $editPlayerModal.css('display', 'none');
            if ($editPlayerForm.length) { $editPlayerForm[0].reset(); }
        }

        $('#pp-edit-player-modal-close').on('click', closeEditPlayerModal);
        $('#pp-cancel-edit-player').on('click', closeEditPlayerModal);
        enableClickOutsideToClose($editPlayerModal, closeEditPlayerModal);

        function openEditModalForPlayer(playerId) {
            currentEditingPlayerId = playerId;
            originalPlayerValues   = {};
            if ($editPlayerForm.length) { $editPlayerForm[0].reset(); }
            $editPlayerModal.css('display', 'flex');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: { action: 'pp_get_player_data', player_id: playerId },
                success: function (response) {
                    if (response.success && response.data && response.data.player) {
                        prefillEditPlayerForm(response.data.player);
                    }
                }
            });
        }

        function prefillEditPlayerForm(player) {
            if (!player) return;

            var posMap    = { 'F': 'forward', 'D': 'defense', 'G': 'goalie' };
            var shootsMap = { 'R': 'right', 'L': 'left' };
            var yearMap   = {
                'FR': 'freshman', 'FRESHMAN': 'freshman',
                'SO': 'sophomore', 'SOPHOMORE': 'sophomore',
                'JR': 'junior', 'JUNIOR': 'junior',
                'SR': 'senior', 'SENIOR': 'senior',
                'GR': 'graduate', 'GRADUATE': 'graduate'
            };

            var nameVal      = player.name || '';
            var numberVal    = player.number || '';
            var posRaw       = (player.pos || '').toUpperCase();
            var posVal       = posMap[posRaw] || posRaw.toLowerCase();
            var htVal        = player.ht || '';
            var wtVal        = player.wt || '';
            var shootsRaw    = (player.shoots || '').toUpperCase();
            var shootsVal    = shootsMap[shootsRaw] || shootsRaw.toLowerCase();
            var hometownVal  = player.hometown || '';
            var lastTeamVal  = player.last_team || '';
            var yearRaw      = (player.year_in_school || '').toUpperCase();
            var yearVal      = yearMap[yearRaw] || yearRaw.toLowerCase() || '';
            var majorVal     = player.major || '';
            var headshotVal  = player.headshot_link || '';

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

            originalPlayerValues = {
                name: nameVal, number: numberVal, pos: posVal, ht: htVal,
                wt: wtVal, shoots: shootsVal, hometown: hometownVal,
                last_team: lastTeamVal, year_in_school: yearVal,
                major: majorVal, headshot_link: headshotVal
            };
        }

        // Open edit modal on edit button click
        $(document).on('click', '.pp-edit-player-btn', function () {
            var playerId = $(this).data('player-id');
            openEditModalForPlayer(playerId);
        });

        // Edit modal form submission
        $('#pp-confirm-edit-player').on('click', function () {
            if ($editPlayerForm.length && !$editPlayerForm[0].checkValidity()) {
                $editPlayerForm[0].reportValidity();
                return;
            }

            var newValues = {
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
                headshot_link:  $('#pp-edit-player-headshot-url').val()
            };

            var changedFields = {};
            Object.keys(newValues).forEach(function (key) {
                if (newValues[key] !== (originalPlayerValues[key] || '')) {
                    changedFields[key] = newValues[key];
                }
            });

            if (Object.keys(changedFields).length === 0) {
                alert('No changes detected.');
                return;
            }

            var editData = {
                edit_action: 'update',
                fields: Object.assign({ external_id: currentEditingPlayerId }, changedFields)
            };

            dimEditListStyles();
            closeEditPlayerModal();

            var formData = new FormData();
            formData.append('action', 'pp_update_player_edits');
            formData.append('edit_data', JSON.stringify(editData));

            $.ajax({
                url: ajaxurl, method: 'POST', data: formData,
                processData: false, contentType: false,
                success: function (response) {
                    if (response.success && response.data && response.data.roster_table_html) {
                        $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                        afterRefresh();
                    } else {
                        restoreEditListStyles();
                        if (!response.success) {
                            alert('Failed to save edit: ' + (response.data && response.data.message || 'Unknown error'));
                        }
                    }
                },
                error: function () {
                    restoreEditListStyles();
                    alert('Error saving edit.');
                }
            });
        });

        //============================================================//
        //   Revert field button                                       //
        //============================================================//
        $(document).on('click', '.pp-revert-btn', function (e) {
            e.stopPropagation();
            var modId  = $(this).data('mod-id');
            var fields = String($(this).data('fields')).split(',');
            dimEditListStyles();

            $.ajax({
                url: ajaxurl, type: 'POST',
                data: { action: 'pp_revert_player_field', mod_id: modId, fields: fields },
                success: function (response) {
                    if (response.success && response.data && response.data.roster_table_html) {
                        $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                        afterRefresh();
                    } else {
                        restoreEditListStyles();
                    }
                },
                error: function () { restoreEditListStyles(); }
            });
        });

        //============================================================//
        //   Restore deleted player button                             //
        //============================================================//
        $(document).on('click', '.pp-restore-player-btn', function () {
            var deleteModId = $(this).data('delete-mod-id');
            dimEditListStyles();

            $.ajax({
                url: ajaxurl, type: 'POST',
                data: { action: 'ajax_delete_player_edit', id: deleteModId },
                success: function (response) {
                    if (response.success) {
                        if (response.data && response.data.roster_table_html) {
                            $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                            afterRefresh();
                        } else {
                            refreshGamesTable(afterRefresh, restoreEditListStyles);
                        }
                    } else {
                        restoreEditListStyles();
                        alert('Failed to restore player.');
                    }
                },
                error: function () { restoreEditListStyles(); }
            });
        });

        //============================================================//
        //   Delete player button                                      //
        //============================================================//
        $(document).on('click', '.pp-delete-player-btn', function () {
            var playerId   = $(this).data('player-id');
            var sourceType = $(this).data('source-type');

            if (!confirm('Are you sure you want to delete this player?')) return;

            dimEditListStyles();

            if (sourceType === 'manual') {
                // Delete the insert mod row entirely
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'pp_delete_manual_player', player_id: playerId },
                    success: function (response) {
                        if (response.success && response.data && response.data.roster_table_html) {
                            $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                            afterRefresh();
                        } else {
                            restoreEditListStyles();
                            alert('Failed to delete player.');
                        }
                    },
                    error: function () { restoreEditListStyles(); }
                });
            } else {
                // Sourced player — add a delete mod
                var editData = {
                    edit_action: 'delete',
                    fields: { external_id: playerId }
                };
                var formData = new FormData();
                formData.append('action', 'pp_update_player_edits');
                formData.append('edit_data', JSON.stringify(editData));

                $.ajax({
                    url: ajaxurl, method: 'POST', data: formData,
                    processData: false, contentType: false,
                    success: function (response) {
                        if (response.success && response.data && response.data.roster_table_html) {
                            $('#pp-roster-edits-table').replaceWith(response.data.roster_table_html);
                            afterRefresh();
                        } else {
                            restoreEditListStyles();
                        }
                    },
                    error: function () { restoreEditListStyles(); }
                });
            }
        });

        //============================================================//
        //   Helper: click-outside-to-close                           //
        //============================================================//
        function enableClickOutsideToClose($modal, closeCallback) {
            var mouseDownOutside = false;

            function onMouseDown(e) {
                mouseDownOutside = (e.target === $modal[0]);
            }
            function onMouseUp(e) {
                if (e.target === $modal[0] && mouseDownOutside) {
                    closeCallback();
                }
            }

            $modal.on('mousedown.modalClose', onMouseDown);
            $modal.on('mouseup.modalClose', onMouseUp);

            return function () {
                $modal.off('mousedown.modalClose', onMouseDown);
                $modal.off('mouseup.modalClose', onMouseUp);
            };
        }
    });

})(jQuery);
