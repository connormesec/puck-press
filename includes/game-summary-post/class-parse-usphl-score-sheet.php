<?php
class Parse_Usphl_Score_Sheet
{
    public function parseScoreSheet($gameId)
    {
        $url = 'https://stats.usphl.timetoscore.com/display-game-stats.php?game_id=' . intval($gameId);

        // Fetch the HTML
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Failed to fetch game page: ' . $response->get_error_message()]);
        }

        $html = wp_remote_retrieve_body($response);
        if (!$html) {
            wp_send_json_error(['message' => 'Failed to retrieve game page content']);
        }

        // Load HTML into DOMDocument
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // Check if this is an invalid game ID (completely empty body)
        $bodyContent = $xpath->query("//body/*");
        if ($bodyContent->length === 0) {
            wp_send_json_error(['message' => 'Invalid game ID - no game data found']);
        }

        $data = [];

        // --- GAME INFO ---
        $gameInfoCells = $xpath->query("//table//td[contains(text(),'Date:')]");
        if ($gameInfoCells->length) {
            $dateText = $gameInfoCells->item(0)->textContent;
            $data['date'] = trim(str_replace('Date:', '', $dateText));
        } else {
            wp_send_json_error(['message' => 'Invalid game - no date information found']);
        }

        $timeInfoCells = $xpath->query("//table//td[contains(text(),'Time:')]");
        if ($timeInfoCells->length) {
            $timeText = $timeInfoCells->item(0)->textContent;
            $data['time'] = trim(str_replace('Time:', '', $timeText));
        }

        $leagueInfoCells = $xpath->query("//table//td[contains(text(),'League:')]");
        if ($leagueInfoCells->length) {
            $leagueText = $leagueInfoCells->item(0)->textContent;
            $data['league'] = trim(str_replace('League:', '', $leagueText));
        }

        $gameInfoCells = $xpath->query("//table//td[contains(text(),'Game:')]");
        if ($gameInfoCells->length) {
            $gameText = $gameInfoCells->item(0)->textContent;
            $data['game_id'] = trim(str_replace('Game:', '', $gameText));
        }

        $locationInfoCells = $xpath->query("//table//td[contains(text(),'Location:')]");
        if ($locationInfoCells->length) {
            $locationText = $locationInfoCells->item(0)->textContent;
            $data['venue'] = trim(str_replace('Location:', '', $locationText));
        }

        // --- TEAMS & FINAL SCORE ---
        $visitorRow = $xpath->query("//tr[th[text()='Visitor']]");
        if ($visitorRow->length) {
            $cells = $xpath->query("./td", $visitorRow->item(0));
            if ($cells->length >= 2) {
                $finalScoreText = trim($cells->item(1)->textContent);

                $data['visitor'] = [
                    'name' => trim($cells->item(0)->textContent),
                    'final_score' => ($finalScoreText === '' || $finalScoreText === '&nbsp;') ? null : intval($finalScoreText)
                ];
            }
        }

        $homeRow = $xpath->query("//tr[th[text()='Home']]");
        if ($homeRow->length) {
            $cells = $xpath->query("./td", $homeRow->item(0));
            if ($cells->length >= 2) {
                $finalScoreText = trim($cells->item(1)->textContent);

                $data['home'] = [
                    'name' => trim($cells->item(0)->textContent),
                    'final_score' => ($finalScoreText === '' || $finalScoreText === '&nbsp;') ? null : intval($finalScoreText)
                ];
            }
        }

        // Determine game status
        $data['game_status'] = $this->determineGameStatus($data);

        // Only parse player/goalie stats if the game has been played
        if ($data['game_status'] === 'completed') {
            // --- PLAYER STATS ---
            // The HTML has a main table structure with left and right columns
            // Left column contains visitor stats, right column contains home stats

            // Get the main table row that contains both team stats
            $mainStatsRow = $xpath->query("//tr[@valign='top']");

            if ($mainStatsRow->length) {
                $statsColumns = $xpath->query("./td", $mainStatsRow->item(0));

                // Left column (index 0) should be visitor stats
                if ($statsColumns->length >= 1) {
                    $visitorColumn = $statsColumns->item(0);

                    // Find player stats table in visitor column
                    $playerStatsTable = $xpath->query(".//table[.//th[text()='Player Stats']]", $visitorColumn);
                    if ($playerStatsTable->length) {
                        $data['visitor']['players'] = $this->parsePlayerStats($xpath, $playerStatsTable->item(0));
                    }

                    // Find goalie stats table in visitor column (nested inside td)
                    $goalieStatsTable = $xpath->query(".//table[.//th[text()='Goalie Stats']]", $visitorColumn);
                    if ($goalieStatsTable->length) {
                        $data['visitor']['goalies'] = $this->parseGoalieStats($xpath, $goalieStatsTable->item(0));
                    }

                    // Find special teams table in visitor column
                    $specialTeamsTable = $xpath->query(".//table[.//th[text()='Special Teams']]", $visitorColumn);
                    if ($specialTeamsTable->length) {
                        $data['visitor']['special_teams'] = $this->parseSpecialTeams($xpath, $specialTeamsTable->item(0));
                    }
                }

                // Right column (index 2) should be home stats (index 1 is the officials column)
                if ($statsColumns->length >= 3) {
                    $homeColumn = $statsColumns->item(2);

                    // Find player stats table in home column
                    $playerStatsTable = $xpath->query(".//table[.//th[text()='Player Stats']]", $homeColumn);
                    if ($playerStatsTable->length) {
                        $data['home']['players'] = $this->parsePlayerStats($xpath, $playerStatsTable->item(0));
                    }

                    // Find goalie stats table in home column (nested inside td)
                    $goalieStatsTable = $xpath->query(".//table[.//th[text()='Goalie Stats']]", $homeColumn);
                    if ($goalieStatsTable->length) {
                        $data['home']['goalies'] = $this->parseGoalieStats($xpath, $goalieStatsTable->item(0));
                    }

                    // Find special teams table in home column
                    $specialTeamsTable = $xpath->query(".//table[.//th[text()='Special Teams']]", $homeColumn);
                    if ($specialTeamsTable->length) {
                        $data['home']['special_teams'] = $this->parseSpecialTeams($xpath, $specialTeamsTable->item(0));
                    }
                }
            }

            $this->add_total_shots_to_game($data);
        } else {
            // For games that haven't been played, initialize empty arrays
            $data['visitor']['players'] = [];
            $data['visitor']['goalies'] = [];
            $data['visitor']['special_teams'] = [];
            $data['visitor']['total_shots'] = 0;

            $data['home']['players'] = [];
            $data['home']['goalies'] = [];
            $data['home']['special_teams'] = [];
            $data['home']['total_shots'] = 0;
        }

        return $data;
    }

    private function determineGameStatus($data)
    {
        $visitorScore = $data['visitor']['final_score'] ?? null;
        $homeScore = $data['home']['final_score'] ?? null;

        // If both scores are null, the game is scheduled
        if ($visitorScore === null && $homeScore === null) {
            return 'scheduled';
        }

        // If both scores are numeric (including 0), the game is completed
        if (is_numeric($visitorScore) && is_numeric($homeScore)) {
            return 'completed';
        }

        // Default to unknown status
        return 'unknown';
    }

    private function add_total_shots_to_game(&$game)
    {
        // Only calculate shots if the game has been completed
        if ($game['game_status'] !== 'completed') {
            return;
        }

        // Total shots for home team = sum of shots against visiting goalies
        $home_total_shots = 0;
        if (!empty($game['visitor']['goalies'])) {
            foreach ($game['visitor']['goalies'] as $goalie) {
                $home_total_shots += intval($goalie['shots']);
            }
        }
        $game['home']['total_shots'] = $home_total_shots;

        // Total shots for visitor team = sum of shots against home goalies
        $visitor_total_shots = 0;
        if (!empty($game['home']['goalies'])) {
            foreach ($game['home']['goalies'] as $goalie) {
                $visitor_total_shots += intval($goalie['shots']);
            }
        }
        $game['visitor']['total_shots'] = $visitor_total_shots;
    }

    private function parsePlayerStats($xpath, $table)
    {
        $players = [];

        // Find the player stats header row to establish the starting point
        $playerHeaderRow = $xpath->query(".//tr[th[text()='Player Stats']]", $table);
        if ($playerHeaderRow->length == 0) {
            return $players;
        }

        // Find the column headers row (should contain "Name", "#", "GP", etc.)
        $columnHeaderRow = $xpath->query(".//tr[th[text()='Name']]", $table);
        if ($columnHeaderRow->length == 0) {
            return $players;
        }

        // Get all rows in the table
        $allRows = $xpath->query(".//tr", $table);

        $startParsing = false;
        $foundColumnHeaders = false;

        foreach ($allRows as $row) {
            // Check if this row contains "Player Stats" header
            $playerStatsHeader = $xpath->query("./th[text()='Player Stats']", $row);
            if ($playerStatsHeader->length > 0) {
                $startParsing = true;
                continue;
            }

            // Check if this row contains the column headers
            $nameHeader = $xpath->query("./th[text()='Name']", $row);
            if ($nameHeader->length > 0 && $startParsing) {
                $foundColumnHeaders = true;
                continue;
            }

            // Check if we've hit the "Goalie Stats" header - stop parsing players
            $goalieStatsHeader = $xpath->query("./th[text()='Goalie Stats']", $row);
            if ($goalieStatsHeader->length > 0) {
                break; // Stop parsing players once we hit goalie stats
            }

            // Only parse data rows after we've found the column headers
            if ($foundColumnHeaders && $startParsing) {
                $cells = $xpath->query("./td", $row);

                // Verify this row has the expected number of columns for player stats (8)
                if ($cells->length >= 8) {
                    $name = trim($cells->item(0)->textContent);
                    $number = trim($cells->item(1)->textContent);

                    // Basic validation - make sure we have a name and number
                    if (!empty($name) && is_numeric($number)) {
                        $players[] = [
                            'name' => $name,
                            'number' => intval($number),
                            'GP' => intval(trim($cells->item(2)->textContent)),
                            'goals' => intval(trim($cells->item(3)->textContent)),
                            'assists' => intval(trim($cells->item(4)->textContent)),
                            'min' => intval(trim($cells->item(5)->textContent)),
                            'points' => intval(trim($cells->item(6)->textContent)),
                            'SOG' => intval(trim($cells->item(7)->textContent)),
                        ];
                    }
                }
            }
        }

        return $players;
    }

    private function parseGoalieStats($xpath, $table)
    {
        $goalies = [];

        // Debug: Check what table we're working with
        $tableHeaders = $xpath->query(".//th", $table);
        $headerTexts = [];
        foreach ($tableHeaders as $header) {
            $headerTexts[] = trim($header->textContent);
        }

        // Only proceed if this is actually a goalie stats table
        if (!in_array('Goalie Stats', $headerTexts)) {
            return $goalies; // Return empty array if this isn't the right table
        }

        // Look for the specific goalie stats header row to find the right starting point
        $goalieHeaderRow = $xpath->query(".//tr[th[text()='Goalie Stats']]", $table);
        if ($goalieHeaderRow->length == 0) {
            return $goalies;
        }

        // Find the column headers row (should be the next row after "Goalie Stats")
        $columnHeaderRow = $xpath->query(".//tr[th[text()='Name']]", $table);
        if ($columnHeaderRow->length == 0) {
            return $goalies;
        }

        // Get all rows that come after the column header row and contain goalie data
        $dataRows = $xpath->query(".//tr[td and position() > 2]", $table);

        foreach ($dataRows as $row) {
            $cells = $xpath->query("./td", $row);

            // Verify this row has the expected number of columns for goalie stats (7)
            if ($cells->length >= 7) {
                // Additional check: verify the columns match goalie stats pattern
                // Name should be text, number should be numeric, etc.
                $name = trim($cells->item(0)->textContent);
                $number = trim($cells->item(1)->textContent);
                $shots = trim($cells->item(3)->textContent);
                $savePercent = trim($cells->item(5)->textContent);

                // Check if this looks like goalie data (save% should be a decimal)
                if (
                    !empty($name) && is_numeric($number) && is_numeric($shots) &&
                    (strpos($savePercent, '.') !== false || strpos($savePercent, '0.') === 0)
                ) {

                    $goalies[] = [
                        'name' => $name,
                        'number' => intval($number),
                        'GP' => intval(trim($cells->item(2)->textContent)),
                        'shots' => intval($shots),
                        'GA' => intval(trim($cells->item(4)->textContent)),
                        'save_percent' => floatval($savePercent),
                        'result' => trim($cells->item(6)->textContent),
                    ];
                }
            }
        }

        return $goalies;
    }

    private function parseSpecialTeams($xpath, $table)
    {
        $specialTeams = [];
        $dataRows = $xpath->query(".//tr[td]", $table); // Find rows with td elements

        foreach ($dataRows as $row) {
            $cells = $xpath->query("./td", $row);
            if ($cells->length >= 9) {
                $specialTeams = [
                    'GP' => intval(trim($cells->item(0)->textContent)),
                    'power_play' => [
                        'advantages' => intval(trim($cells->item(1)->textContent)),
                        'goals_for' => intval(trim($cells->item(2)->textContent)),
                        'percentage' => intval(trim($cells->item(3)->textContent)),
                        'short_handed_goals_against' => intval(trim($cells->item(4)->textContent)),
                    ],
                    'penalty_kill' => [
                        'times_short_handed' => intval(trim($cells->item(5)->textContent)),
                        'power_play_goals_against' => intval(trim($cells->item(6)->textContent)),
                        'percentage' => intval(trim($cells->item(7)->textContent)),
                        'short_handed_goals_for' => intval(trim($cells->item(8)->textContent)),
                    ]
                ];
                break; // Only need the first data row
            }
        }

        return $specialTeams;
    }
}
