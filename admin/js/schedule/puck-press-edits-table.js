(function ($) {
  jQuery(document).ready(function ($) {
    const dimEditListStyles = () =>
      $(
        "#pp-card-game-schedule-preview, #pp-card-schedule-game-list , #pp-card-game-schedule-edits, .pp-modal, .pp-card",
      ).css({
        opacity: "0.5",
        "pointer-events": "none",
      });
    const restoreEditListStyles = () => {
      $(
        "#pp-card-game-schedule-preview, #pp-card-schedule-game-list , #pp-card-game-schedule-edits, .pp-modal, .pp-card",
      ).css({
        opacity: "1",
        "pointer-events": "auto",
      });
    };

    // Restore styles helper for use with refreshGamesTable callbacks
    function afterRefresh() {
      restoreEditListStyles();
      countGameRows();
      applyEditHighlights();
    }

    //############################################################//
    //                                                            //
    //               Edit Highlight Functionality                 //
    //                                                            //
    //############################################################//

    /**
     * Read data-overrides on each <tr> and add highlight + revert button
     * to any <td data-field="..."> whose field name appears in the override list.
     */
    function applyEditHighlights() {
      $("#pp-games-table tbody tr:not(.pp-row-deleted)").each(function () {
        var $row = $(this);
        var overrides = [];
        try {
          overrides = JSON.parse($row.attr("data-overrides") || "[]");
        } catch (e) {
          overrides = [];
        }

        var modId = $row.attr("data-mod-id");

        $row.find("td[data-field]").each(function () {
          var $td = $(this);
          var fields = $td.attr("data-field").split(" ");
          var isOverridden = fields.some(function (f) {
            return overrides.indexOf(f) !== -1;
          });

          if (isOverridden) {
            $td.addClass("pp-cell-overridden");
            if (!$td.find(".pp-revert-btn").length) {
              $td.append(
                '<button class="pp-revert-btn" title="Revert to original" ' +
                  'data-mod-id="' +
                  modId +
                  '" ' +
                  'data-fields="' +
                  fields.join(",") +
                  '">&#x2715;</button>',
              );
            }
          } else {
            $td.removeClass("pp-cell-overridden");
            $td.find(".pp-revert-btn").remove();
          }
        });
      });
    }

    // Apply on initial page load
    applyEditHighlights();

    // Revert a single cell's field(s) back to raw value
    $(document).on("click", ".pp-revert-btn", function (e) {
      e.stopPropagation();

      var modId = $(this).data("mod-id");
      var fields = String($(this).data("fields")).split(",");

      dimEditListStyles();

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "pp_revert_game_field",
          mod_id: modId,
          fields: fields,
        },
        success: function (response) {
          if (response.success) {
            refreshGamesTable(afterRefresh, afterRefresh);
          } else {
            console.error("Revert failed:", response.data);
            restoreEditListStyles();
          }
        },
        error: function () {
          alert("Error reverting field.");
          restoreEditListStyles();
        },
      });
    });

    //############################################################//
    //                                                            //
    //               Restore Deleted Game Button                  //
    //                                                            //
    //############################################################//

    $(document).on("click", ".pp-restore-game-button", function () {
      var deleteModId = $(this).data("delete-mod-id");

      dimEditListStyles();

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "ajax_delete_game_edit",
          id: deleteModId,
        },
        success: function (response) {
          if (response.success) {
            refreshGamesTable(afterRefresh, afterRefresh);
          } else {
            console.error("Error restoring game:", response.data);
            restoreEditListStyles();
          }
        },
        error: function () {
          alert("There was an error restoring the game.");
          restoreEditListStyles();
        },
      });
    });

    //############################################################//
    //                                                            //
    //               Edit Game Modal functionality                //
    //                                                            //
    //############################################################//

    const $editGameModal = $("#pp-edit-game-modal");
    const $closeEditGameModalBtn = $("#pp-edit-game-modal-close");
    const $cancelEditGameBtn = $("#pp-cancel-edit-game");
    const $confirmBtn_editGameModal = $("#pp-confirm-edit-game");
    const $editGameForm = $("#pp-edit-game-form");
    let currentEditingGameId = null;
    // Snapshot of values set by prefillEditForm; used to detect which fields actually changed.
    let originalFormValues = null;

    // Convert a formatted time string like "7:30 PM" to "HH:MM" for <input type="time">
    function formatTimeForDisplay(timeStr) {
      if (!timeStr) return "";
      var m = String(timeStr).match(/^(\d{1,2}):(\d{2})$/);
      if (!m) return timeStr;
      var h = parseInt(m[1], 10);
      var min = m[2];
      var period = h >= 12 ? "pm" : "am";
      if (h === 0) h = 12;
      else if (h > 12) h -= 12;
      return h + ":" + min + " " + period;
    }

    function formatTimeForInput(timeStr) {
      if (!timeStr) return "";
      // Strip trailing timezone codes (e.g. "EST", "CT") that aren't AM/PM
      var cleaned = String(timeStr)
        .replace(/\s+(?!AM|PM)[A-Z]{2,4}$/i, "")
        .trim();
      const m = cleaned.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
      if (!m) return "";
      let h = parseInt(m[1], 10);
      const min = m[2];
      const period = (m[4] || "").toUpperCase();
      if (period === "PM" && h !== 12) h += 12;
      if (period === "AM" && h === 12) h = 0;
      return String(h).padStart(2, "0") + ":" + min;
    }

    // Map a status string from DB (stored uppercase: 'FINAL', 'FINAL OT', 'FINAL SO') to a select option value
    function mapStatusToInput(status) {
      if (!status) return "";
      var lower = String(status).toLowerCase().trim().replace(/\s+/g, "-");
      var valid = ["final", "final-ot", "final-so"];
      return valid.indexOf(lower) !== -1 ? lower : "";
    }

    /**
     * Pre-fill all edit form fields from a pp_game_schedule_for_display row.
     * This is the current effective state of the game (raw + all edits applied).
     */
    function prefillEditForm(game) {
      if (!game) return;

      // game_timestamp is a MySQL DATETIME string: "2024-01-15 19:30:00"
      // Extract just the date part for the date input.
      var datePart = "";
      if (game.game_timestamp) {
        datePart = String(game.game_timestamp).split(" ")[0]; // "YYYY-MM-DD"
        $("#pp-edit-game-date").val(datePart);
      }

      // game_time is stored as "7:30 PM" — convert to HH:MM for <input type="time">
      var timeVal = formatTimeForInput(game.game_time || "");
      $("#pp-edit-game-time").val(timeVal);

      // home_or_away: "home" / "away" — direct match with select options
      var homeOrAwayVal = game.home_or_away || "";
      $("#pp-edit-home-or-away").val(homeOrAwayVal);

      // game_status: "Final" / "Final OT" / null — map to select option values
      var statusVal = mapStatusToInput(game.game_status);
      $("#pp-edit-game-status").val(statusVal);

      // Scores
      var ts = game.target_score;
      var targetScoreVal = ts !== null && ts !== undefined ? String(ts) : "";
      $("#pp-edit-target-score").val(targetScoreVal);
      var os = game.opponent_score;
      var opponentScoreVal = os !== null && os !== undefined ? String(os) : "";
      $("#pp-edit-opponent-score").val(opponentScoreVal);

      // Venue
      var venueVal = game.venue || "";
      $("#pp-edit-venue").val(venueVal);

      // Promo fields
      var promoHeaderVal = game.promo_header || "";
      var promoTextVal = game.promo_text || "";
      var promoImgUrlVal = game.promo_img_url || "";
      var promoTicketLinkVal = game.promo_ticket_link || "";
      $("#pp-promo-header").val(promoHeaderVal);
      $("#pp-promo-text").val(promoTextVal);
      $("#pp-promo-img-url").val(promoImgUrlVal);
      $("#pp-promo-ticket-link").val(promoTicketLinkVal);

      // Snapshot so the submit handler can detect which fields actually changed.
      originalFormValues = {
        game_date: datePart,
        game_time: timeVal,
        home_or_away: homeOrAwayVal,
        game_status: statusVal,
        target_score: targetScoreVal,
        opponent_score: opponentScoreVal,
        venue: venueVal,
        promo_header: promoHeaderVal,
        promo_text: promoTextVal,
        promo_img_url: promoImgUrlVal,
        promo_ticket_link: promoTicketLinkVal,
      };
    }

    /**
     * Open the edit modal for a game and asynchronously pre-fill it with current values.
     * The modal is shown FIRST — nothing gates this operation.
     */
    function openEditModalForGame(gameId) {
      currentEditingGameId = gameId;

      // Show the modal immediately — this must always run regardless of what follows.
      $editGameModal.css("display", "flex");
      $(".pp-modal-subtitle").text("Loading...");

      // Reset form after the modal is visible, with null safety.
      if ($editGameForm.length) {
        $editGameForm[0].reset();
      }

      // Pre-fill asynchronously from the server's authoritative for_display row.
      $.ajax({
        url: ajaxurl,
        method: "POST",
        data: {
          action: "pp_get_game_data",
          game_id: gameId,
        },
        success: function (response) {
          if (response.success && response.data && response.data.game) {
            prefillEditForm(response.data.game);
          }
          $(".pp-modal-subtitle").text("Game: " + gameId);
        },
        error: function () {
          $(".pp-modal-subtitle").text("Game: " + gameId);
        },
      });
    }

    // Open modal from the Games Table
    $(document).on("click", "#pp-edit-game-button", function () {
      openEditModalForGame($(this).data("game-id"));
    });

    // Close modal
    function closeEditGameModal() {
      $editGameModal.css("display", "none");
      if ($editGameForm.length) {
        $editGameForm[0].reset();
      }
    }

    $closeEditGameModalBtn.on("click", closeEditGameModal);
    $cancelEditGameBtn.on("click", closeEditGameModal);

    $editGameModal.on("click", function (e) {
      if (e.target === this) {
        closeEditGameModal();
      }
    });

    // Form submission
    $confirmBtn_editGameModal.on("click", function () {
      if ($editGameForm.length && !$editGameForm[0].checkValidity()) {
        $editGameForm[0].reportValidity();
        return;
      }

      dimEditListStyles();

      // Only include fields that differ from the pre-filled originals.
      // Pre-filled-but-unchanged values must not be stored as overrides —
      // that's what caused untouched cells to get highlighted.
      var orig = originalFormValues || {};
      var fields = { external_id: currentEditingGameId };

      var gameDate = $("#pp-edit-game-date").val();
      if (gameDate && gameDate !== (orig.game_date || ""))
        fields.game_date = gameDate;

      var gameTime = $("#pp-edit-game-time").val();
      if (gameTime && gameTime !== (orig.game_time || ""))
        fields.game_time = formatTimeForDisplay(gameTime);

      var homeOrAway = $("#pp-edit-home-or-away").val();
      if (homeOrAway && homeOrAway !== (orig.home_or_away || ""))
        fields.home_or_away = homeOrAway;

      var gameStatus = $("#pp-edit-game-status").val();
      if (gameStatus && gameStatus !== (orig.game_status || ""))
        fields.game_status = gameStatus;

      var targetScore = $("#pp-edit-target-score").val();
      if (targetScore !== "" && targetScore !== (orig.target_score || ""))
        fields.target_score = targetScore;

      var opponentScore = $("#pp-edit-opponent-score").val();
      if (opponentScore !== "" && opponentScore !== (orig.opponent_score || ""))
        fields.opponent_score = opponentScore;

      var venue = $("#pp-edit-venue").val();
      if (venue && venue !== (orig.venue || "")) fields.venue = venue;

      var promoHeader = $("#pp-promo-header").val();
      if (promoHeader && promoHeader !== (orig.promo_header || ""))
        fields.promo_header = promoHeader;

      var promoText = $("#pp-promo-text").val();
      if (promoText && promoText !== (orig.promo_text || ""))
        fields.promo_text = promoText;

      var promoImgUrl = $("#pp-promo-img-url").val();
      if (promoImgUrl && promoImgUrl !== (orig.promo_img_url || ""))
        fields.promo_img_url = promoImgUrl;

      var promoTicketLink = $("#pp-promo-ticket-link").val();
      if (promoTicketLink && promoTicketLink !== (orig.promo_ticket_link || ""))
        fields.promo_ticket_link = promoTicketLink;

      var edit_data = {
        edit_action: "update",
        fields: fields,
      };

      var formData = new FormData();
      formData.append("action", "pp_update_game_promos");
      formData.append("edit_data", JSON.stringify(edit_data));

      closeEditGameModal();

      $.ajax({
        url: ajaxurl,
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            refreshGamesTable(afterRefresh, afterRefresh);
          } else {
            console.error("Error:", response);
            alert("Failed to save edit.");
            restoreEditListStyles();
          }
        },
        error: function (err) {
          console.error("Error:", err);
          alert("Failed to save edit.");
          restoreEditListStyles();
        },
      });
    });

    //############################################################//
    //                                                            //
    //               Reset All Edits Button                      //
    //                                                            //
    //############################################################//

    $("#pp-reset-all-edits").on("click", function () {
      if (
        !confirm(
          "Reset all edits? This will remove every override, deletion, and manual game. This cannot be undone.",
        )
      ) {
        return;
      }

      dimEditListStyles();

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: { action: "pp_reset_all_game_edits" },
        success: function (response) {
          if (response.success) {
            refreshGamesTable(afterRefresh, afterRefresh);
          } else {
            console.error("Error resetting edits:", response.data);
            alert("There was an error resetting edits.");
            restoreEditListStyles();
          }
        },
        error: function () {
          alert("Error resetting edits.");
          restoreEditListStyles();
        },
      });
    });

    //############################################################//
    //                                                            //
    //               Delete Game Button Functionality             //
    //                                                            //
    //############################################################//

    $(document).on("click", "#pp-delete-game-button", function () {
      dimEditListStyles();

      var $row = $(this).closest("tr");
      var gameId = $(this).data("game-id");
      var sourceType = $row.data("source-type");

      if (sourceType === "manual") {
        $.ajax({
          url: ajaxurl,
          type: "POST",
          data: {
            action: "pp_delete_manual_game",
            game_id: gameId,
          },
          success: function (response) {
            if (response.success) {
              $("#pp-games-table").replaceWith(response.data.games_table_html);
              refreshGamesTable(afterRefresh, afterRefresh);
            } else {
              console.error("Error deleting manual game:", response.data);
              alert("There was an error deleting the game.");
              restoreEditListStyles();
            }
          },
          error: function () {
            alert(
              "There was an error with the AJAX request to delete the game.",
            );
            restoreEditListStyles();
          },
        });
      } else {
        var edit_data = {
          edit_action: "delete",
          fields: { external_id: gameId },
        };
        var formData = new FormData();
        formData.append("action", "pp_update_game_promos");
        formData.append("edit_data", JSON.stringify(edit_data));

        $.ajax({
          url: ajaxurl,
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          success: function (response) {
            if (response.success) {
              refreshGamesTable(afterRefresh, afterRefresh);
            } else {
              console.error("Error deleting edit:", response.data);
              alert("There was an error deleting the edit.");
              restoreEditListStyles();
            }
          },
          error: function () {
            alert(
              "There was an error with the AJAX request to delete the edit.",
            );
            restoreEditListStyles();
          },
        });
      }
    });
  });
})(jQuery);
