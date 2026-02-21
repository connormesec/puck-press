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
        </select>
    </div>
    <div class="pp-dynamic-source-group-achaRosterUrl">
        <div class="pp-form-group">
            <label for="pp-source-url" class="pp-form-label">URL</label>
            <input type="url" id="pp-source-url" class="pp-form-input" placeholder="https://example.com/data.csv" required>
            <span class="pp-form-help">URL to the ACHA Roster</span>
        </div>
        <div class="pp-form-group">
            <label for="pp-stats-url" class="pp-form-label">Stats URL <span style="font-weight:normal;color:#888;">(optional)</span></label>
            <input type="url" id="pp-stats-url" class="pp-form-input" placeholder="https://www.achahockey.org/stats/player-stats/51/60?...">
            <span class="pp-form-help">ACHA stats page URL — used to import player stats</span>
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
        <label for="pp-roster-fileInput" class="pp-form-label">Upload CSV</label>
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