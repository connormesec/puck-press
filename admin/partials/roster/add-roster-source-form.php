
<form id="pp-add-source-form">
	<div class="pp-form-group">
		<label for="pp-source-name" class="pp-form-label">Name</label>
		<select id="pp-source-name" class="pp-form-select" required>
			<option value="">— Select —</option>
			<option value="Regular Season">Regular Season</option>
			<option value="Playoffs">Playoffs</option>
			<option value="Conference Tournament">Conference Tournament</option>
			<option value="Regionals">Regionals</option>
			<option value="Nationals">Nationals</option>
			<option value="Exhibition">Exhibition</option>
			<option value="__other__">Other...</option>
		</select>
		<input type="text" id="pp-source-name-other" class="pp-form-input" style="display:none;" placeholder="Enter custom name">
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
			<input type="url" id="pp-source-url" class="pp-form-input" placeholder="https://www.achahockey.org/stats/roster/883/46?division=58&league=1" required>
			<span class="pp-form-help">URL to the ACHA Roster page</span>
		</div>
		<div class="pp-form-group">
			<label class="pp-form-label pp-checkbox-label">
				<input type="checkbox" id="pp-include-stats" value="1" checked>
				Include Stats
			</label>
			<span class="pp-form-help">Automatically imports skater and goalie stats using the team and season from the roster URL above. No separate stats URL needed.</span>
		</div>
	</div>
	<div class="pp-dynamic-source-group-usphlRosterUrl">
		<div class="pp-form-group">
			<label for="pp-usphl-team-id" class="pp-form-label">Team ID</label>
			<input type="text" id="pp-usphl-team-id" class="pp-form-input" placeholder="e.g. 2301" required>
			<span class="pp-form-help">USPHL league-assigned team ID</span>
		</div>
		<div class="pp-form-group">
			<label for="pp-usphl-season-id" class="pp-form-label">Season ID</label>
			<input type="text" id="pp-usphl-season-id" class="pp-form-input" placeholder="e.g. 65" required>
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
