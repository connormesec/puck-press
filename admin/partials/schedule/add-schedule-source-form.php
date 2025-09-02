<?php
$team_data = $this->get_team_data_array();
$season_options = $this->get_season_options_array();
?>

<form id="pp-add-source-form">
    <div class="pp-form-group">
        <label for="pp-source-name" class="pp-form-label">Name</label>
        <input type="text" id="pp-source-name" class="pp-form-input" placeholder="e.g. Regular Season 2023-2024" required>
    </div>

    <div class="pp-form-group">
        <label for="pp-source-type" class="pp-form-label">Type</label>
        <select id="pp-source-type" class="pp-form-select" required>
            <option value="achaGameScheduleUrl">ACHA Website Schedule Url</option>
            <option value="usphlGameScheduleUrl">USPHL Website Schedule Url</option>
            <option value="csv">CSV</option>
            <option value="customGame">Custom Game</option>
        </select>
    </div>
    <!-- ACHA Website Schedule URL -->
    <div class="pp-dynamic-source-group-achaGameScheduleUrl">
        <div class="pp-form-group">
            <label for="pp-source-season-year" class="pp-form-label">Season Year</label>
            <select id="pp-source-season-year" class="pp-form-select" required>
                <option value="<?php echo esc_html($season_options[2]) ?>"><?php echo esc_html($season_options[2]) ?></option>
                <option value="<?php echo esc_html($season_options[1]) ?>" selected><?php echo esc_html($season_options[1]) ?></option>
                <option value="<?php echo esc_html($season_options[0]) ?>"><?php echo esc_html($season_options[0]) ?></option>
            </select>
        </div>

        <div class="pp-form-group">
            <label for="pp-source-url" class="pp-form-label">URL</label>
            <input type="url" id="pp-source-url" class="pp-form-input" placeholder="https://example.com/data.csv" required>
            <span class="pp-form-help">URL to the ACHA schedule</span>
        </div>
    </div>
    <!-- USPHL Schedule URL -->
    <div class="pp-dynamic-source-group-usphlGameScheduleUrl">
        <div class="pp-form-group">
            <label for="pp-source-url-usphl" class="pp-form-label">URL</label>
            <input type="url" id="pp-source-url-usphl" class="pp-form-input" placeholder="https://example.com/data.csv" required>
            <span class="pp-form-help">URL to the USPHL schedule</span>
        </div>
    </div>
    <!-- CSV Upload Section -->
    <div class="pp-form-group pp-dynamic-source-group-csv">
        <label for="pp-source-csv" class="pp-form-label">Upload CSV</label>
        <div class="upload-container">
            <div class="file-input-container">
                <input type="file" id="pp-schedule-fileInput" class="pp-form-input" accept=".csv">
            </div>
        </div>
        <div class="pp-schedule-sources-csv-upload-info-box">
            <div class="pp-schedule-sources-csv-upload-title">
                <div class="pp-schedule-sources-csv-upload-icon">i</div>
                CSV Format Requirements
            </div>

            <div class="pp-schedule-sources-csv-upload-description">
                Your CSV must include the following headers EXACTLY (case-sensitive):
            </div>

            <div class="pp-schedule-sources-csv-upload-code-box">target_team_name, target_team_nickname, target_team_logo, target_score, opponent_team_name, opponent_team_nickname, opponent_team_logo, opponent_score, game_time, game_timestamp, game_status, home_or_away, venue</div>

            <div class="pp-schedule-sources-csv-upload-example-label">Example:</div>
            <button id="copyCsvToClipboard">Copy for Excel</button>

            <div class="pp-schedule-sources-csv-upload-code-box">target_team_name, target_team_nickname, target_team_logo, target_score, opponent_team_name, opponent_team_nickname, opponent_team_logo, opponent_score, game_time, game_timestamp, game_status, home_or_away, venue<br>Montana State University, Bobcats, https://assets.leaguestat.com/acha/logos/51.png, , Boise State University, Broncos, https://assets.leaguestat.com/acha/logos/217.png, , 7:30 pm MST, "February 28, 2025 7:30 PM", , away, Idaho Ice World<br>Montana Tech, Orediggers, https://assets.leaguestat.com/acha/logos/883.png, 1, Williston State College, Tetons, https://assets.leaguestat.com/acha/logos/60.png,4, "Thu, Oct 10", "October 10, 2024 12:00 am", Final, home, Butte Community Ice Center</div>
        </div>
    </div>
    <!-- Custom Game Section -->
    <div class="pp-dynamic-source-group-customGame" id="pp-custom-game-section">
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-game-date" class="pp-form-label">Date</label>
                <input type="date" id="pp-game-date" class="pp-form-input" required>
            </div>

            <div class="pp-form-group">
                <label for="pp-game-time" class="pp-form-label">Time</label>
                <input type="time" id="pp-game-time" class="pp-form-input">
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-game-target" class="pp-form-label">Target Team</label>
                <?php echo $this->render_team_option_picker($team_data, 'target'); ?>
            </div>
            <div class="pp-form-group">
                <label for="pp-game-target-score" class="pp-form-label">Target Score</label>
                <input type="text" id="pp-game-target-score" class="pp-form-input" style="width: 25%;">
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-game-opponent" class="pp-form-label">Opponent</label>
                <?php echo $this->render_team_option_picker($team_data, 'opponent'); ?>
            </div>
            <div class="pp-form-group">
                <label for="pp-game-opponent-score" class="pp-form-label">Opponent Score</label>
                <input type="text" id="pp-game-opponent-score" class="pp-form-input" style="width: 25%;">
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-home-or-away" class="pp-form-label">Location</label>
                <select id="pp-home-or-away" class="pp-form-select" required>
                    <option selected="selected" value="Home">Home</option>
                    <option value="Away">Away</option>
                </select>
            </div>
            <div class="pp-form-group">
                <label for="pp-game-status" class="pp-form-label">Status</label>
                <select id="pp-game-status" class="pp-form-select" required>
                    <option selected="selected" value="none">None</option>
                    <option value="final">Final</option>
                    <option value="final-ot">Final OT</option>
                    <option value="final-so">Final SO</option>
                </select>
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-game-venue" class="pp-form-label">Venue</label>
                <input type="text" id="pp-game-venue" class="pp-form-input">
            </div>
        </div>
    </div>

    <div class="pp-form-group">
        <div class="pp-status-toggle">
            <label class="pp-data-source-toggle-switch">
                <input type="checkbox" id="pp-new-source-active" checked>
                <span class="pp-slider"></span>
            </label>
            <span>Active</span>
        </div>
    </div>
</form>