<?php
$team_data = $this->get_team_data_array();
?>
<form id="pp-add-game-form">
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
                <option selected="selected" value="home">Home</option>
                <option value="away">Away</option>
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
</form>
