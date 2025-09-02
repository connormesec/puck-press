<?php


?>

<form id="pp-add-source-form">
    <div class="pp-form-group">
        <label for="pp-source-name" class="pp-form-label">Name</label>
        <input type="text" id="pp-source-name" class="pp-form-input" placeholder="e.g. Regular Season 2023-2024" required>
    </div>

    <div class="pp-form-group">
        <label for="pp-source-type" class="pp-form-label">Type</label>
        <select id="pp-source-type" class="pp-form-select" required>
            <option value="achaRosterUrl">ACHA Website Roster Url</option>
            <option value="usphlRosterUrl">USPHL Roster Url</option>
            <option value="csv">CSV</option>
            <option value="customPlayer">Custom Player</option>
        </select>
    </div>
    <div class="pp-dynamic-source-group-achaRosterUrl">
        <div class="pp-form-group">
            <label for="pp-source-url" class="pp-form-label">URL</label>
            <input type="url" id="pp-source-url" class="pp-form-input" placeholder="https://example.com/data.csv" required>
            <span class="pp-form-help">URL to the ACHA Roster</span>
        </div>
    </div>
    <div class="pp-dynamic-source-group-usphlRosterUrl">
        <div class="pp-form-group">
            <label for="pp-usphl-source-url" class="pp-form-label">URL</label>
            <input type="url" id="pp-usphl-source-url" class="pp-form-input" placeholder="link to S3 bucket json" required>
            <span class="pp-form-help">URL to the USPHL Roster</span>
        </div>
    </div>
    <!-- CSV Upload Section -->
    <div class="pp-form-group pp-dynamic-source-group-csv">
        <label for="pp-source-csv" class="pp-form-label">Upload CSV</label>
        <div class="upload-container">
            <div class="file-input-container">
                <input type="file" id="pp-roster-fileInput" class="pp-form-input" accept=".csv">
            </div>
        </div>
        <div class="pp-roster-sources-csv-upload-info-box">
            <div class="pp-roster-sources-csv-upload-title">
                <div class="pp-roster-sources-csv-upload-icon">i</div>
                CSV Format Requirements
            </div>

            <div class="pp-roster-sources-csv-upload-description">
                Your CSV must include the following headers EXACTLY (case-sensitive):
            </div>

            <div class="pp-roster-sources-csv-upload-code-box">headshot_link, number, name, pos, ht, wt, shoots, hometown, last_team, year_in_school, major</div>

            <div class="pp-roster-sources-csv-upload-example-label">Example:</div>
            <button id="copyRosterCsvToClipboard">Copy for Excel</button>

            <div class="pp-roster-sources-csv-upload-code-box">headshot_link,number,name,pos,ht,wt,shoots,hometown,last_team,year_in_school,major<br>https://www.pathwaysvermont.org/wp-content/uploads/2017/03/avatar-placeholder-e1490629554738.png,1.00,Nikolai Wallery,Goalie,6'1,180,L,"Helena, MT","Butte Cobras, NA3HL",Sophomore,-<br>https://www.pathwaysvermont.org/wp-content/uploads/2017/03/avatar-placeholder-e1490629554738.png,3.00,Tyler Fisher,Defense,6'4,200,R,"Park City, UT","Seahawks HC, EHL",Freshman,-</div>
        </div>
    </div>
    <!-- Custom Game Section -->
    <div class="pp-dynamic-source-group-customPlayer" id="pp-customPlayer-section">
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-player-name" class="pp-form-label">Player Name</label>
                <input type="text" id="pp-player-name" class="pp-form-input">
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-player-number" class="pp-form-label">Number</label>
                <input type="text" id="pp-player-number" class="pp-form-input">
            </div>
            <div class="pp-form-group">
                <label for="pp-player-position" class="pp-form-label">Position</label>
                <select id="pp-player-position" class="pp-form-select">
                    <option selected="selected" value="forward">Forward</option>
                    <option value="defense">Defense</option>
                    <option value="goalie">Goalie</option>
                </select>
            </div>
            <div class="pp-form-group">
                <label for="pp-player-height" class="pp-form-label">Height</label>
                <input type="text" id="pp-player-height" class="pp-form-input">
            </div>
            <div class="pp-form-group">
                <label for="pp-player-weight" class="pp-form-label">Weight</label>
                <input type="text" id="pp-player-weight" class="pp-form-input">
            </div>
            <div class="pp-form-group">
                <label for="pp-player-shoots" class="pp-form-label">Shoots</label>
                <select id="pp-player-shoots" class="pp-form-select">
                    <option selected="selected" value="right">Right</option>
                    <option value="left">Left</option>
                </select>
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-player-hometown" class="pp-form-label">Hometown</label>
                <input type="text" id="pp-player-hometown" class="pp-form-input">
            </div>

            <div class="pp-form-group">
                <label for="pp-player-last-team" class="pp-form-label">Last Team</label>
                <input type="text" id="pp-player-last-team" class="pp-form-input">
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
            <label for="pp-player-year" class="pp-form-label">Year</label>
                <select id="pp-player-year" class="pp-form-select">
                    <option selected="selected" value="freshman">Freshman</option>
                    <option value="sophomore">Sophomore</option>
                    <option value="junior">Junior</option>
                    <option value="senior">Senior</option>
                </select>
            </div>

            <div class="pp-form-group">
                <label for="pp-player-major" class="pp-form-label">Major</label>
                <input type="text" id="pp-player-major" class="pp-form-input">
            </div>
        </div>
        <div class="pp-form-row">
            <div class="pp-form-group">
                <label for="pp-player-headshot-url" class="pp-form-label">Headshot URL</label>
                <input type="text" id="pp-player-headshot-url" class="pp-form-input">
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