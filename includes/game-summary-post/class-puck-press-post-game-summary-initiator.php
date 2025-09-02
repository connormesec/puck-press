<?php

if (!defined('ABSPATH')) {
    exit;
}

class Puck_Press_Post_Game_Summary_Initiator
{
    private $game_id;
    private $source_type;

    public function __construct($game_id, $source_type)
    {
        $this->load_dependencies();
        $this->game_id = $game_id;
        $this->source_type = $source_type;
    }

    private function load_dependencies()
    {
        include_once plugin_dir_path(__FILE__) . 'class-parse-usphl-score-sheet.php';
        include_once plugin_dir_path(__FILE__) . 'class-parse-acha-score-sheet.php';
        include_once plugin_dir_path(__FILE__) . 'class-hockey-game-blog-generator.php';
    }

    public function returnGameDataInImageAPIFormat()
    {
        if ($this->source_type === 'usphlGameScheduleUrl') {
            $parser = new Parse_Usphl_Score_Sheet();
            $parsed = $parser->parseScoreSheet($this->game_id); // array from parser
            $data = $this->transformUsphlData($parsed);
            return $data;
        } elseif ($this->source_type === 'achaGameScheduleUrl') {
            $parser = new Parse_Acha_Score_Sheet();
            $parsed = $parser->parseScoreSheet($this->game_id); // array from parser
            $data = $this->transformAchaData($parsed);
            return $data;
        } else {
            throw new Exception('Unsupported source type: ' . $this->source_type);
        }
    }

    public function getGameSummaryFromBlogAPI($gameData)
    {
        $generator = new Class_Hockey_Game_Blog_Generator(get_option('pp_openai_api_key'));
        $title_and_body = $this->extract_title_and_body($generator->generateGameBlog($gameData));
        $blog_data['body'] = $title_and_body['body'];
        $blog_data['title'] = $title_and_body['title'];
        return $blog_data;
    }

    /**
     * Extracts title and body from a string where the title is in square brackets.
     *
     * @param string $input
     * @return array [ 'title' => string, 'body' => string ]
     */
    function extract_title_and_body($input)
    {
        $title = '';
        $body  = trim($input);

        // Regex: match [title] at the start
        if (preg_match('/^\[(.*?)\]\s*(.*)$/s', $input, $matches)) {
            $title = trim($matches[1]);
            $body  = trim($matches[2]);
        }

        return [
            'title' => $title,
            'body'  => $body,
        ];
    }

    /**
     * Transform parsed USPHL score sheet into API-friendly format
     */
    private function transformUsphlData(array $data): array
    {
        // Map a player object into API schema
        $mapPlayer = function ($p) {
            // Split full name into first/last
            $parts = preg_split('/\s+/', trim($p['name']));
            $lastName = array_pop($parts);
            $firstName = implode(" ", $parts);

            return [
                'info' => [
                    'jerseyNumber' => $p['number'],
                    'position'     => '', // parser didn’t give position because USPHL score sheets don’t include it
                    'firstName'    => $firstName,
                    'lastName'     => $lastName,
                ],
                'stats' => [
                    'goals'          => $p['goals'],
                    'assists'        => $p['assists'],
                    'points'         => $p['points'],
                    'penaltyMinutes' => $p['min'],
                ]
            ];
        };

        // Map a goalie object into API schema
        $mapGoalie = function ($g) {
            $parts = preg_split('/\s+/', trim($g['name']));
            $lastName = array_pop($parts);
            $firstName = implode(" ", $parts);

            return [
                'info' => [
                    'jerseyNumber' => $g['number'],
                    'firstName'    => $firstName,
                    'lastName'     => $lastName,
                ],
                'stats' => [
                    'saves' => $g['shots'] - $g['GA'], // derive saves
                ]
            ];
        };

        $logos_ids = $this->getHomeAndAwayTeamLogosAndIds($this->game_id, $this->source_type);

        $mapTeam = function ($team, $logo, $team_id) use ($mapPlayer, $mapGoalie) {
            $splitName = $this->split_usphl_team_name($team['name']);

            return [
                'info' => [
                    'id'       => $team_id,
                    'name'     => $team['name'],
                    'nickname' => $splitName['name'],
                    'logo'     => $logo,
                ],
                'stats' => [
                    'goals'           => $team['final_score'],
                    'shots'           => $team['total_shots'],
                    'powerPlayGoals'  => $team['special_teams']['power_play']['goals_for'],
                    'infractionCount' => $team['special_teams']['penalty_kill']['times_short_handed'],
                ],
                'skaters'   => array_map($mapPlayer, $team['players']),
                'goalieLog' => array_map($mapGoalie, $team['goalies']),
            ];
        };

        // Format date/time into ISO8601
        $dateTime = \DateTime::createFromFormat('n/j/y g:i A', $data['date'] . ' ' . $data['time'], new \DateTimeZone('America/Denver')); //would be nice to get timezone from DB
        $isoDate = $dateTime ? $dateTime->format(DateTimeInterface::ATOM) : null;

        $next_game = $this->getNextGameInformation($this->game_id, $this->source_type);
        return [
            'league' => 'usphl',
            'targetTeamId' => $logos_ids['target_team_id'],
            'nextGameInfo' => $next_game,
            'highLevelStats' => [
                'league'       => 'usphl',
                'homeTeam'     => $mapTeam($data['home'], $logos_ids['home_team_logo'], $logos_ids['home_team_id']),
                'visitingTeam' => $mapTeam($data['visitor'], $logos_ids['away_team_logo'], $logos_ids['away_team_id']),
                'details' => [
                    'status'        => $data['game_status'],
                    'simpleStatus'  => $data['game_status'],
                    'venue'         => $data['venue'],
                    'GameDateISO8601' => $isoDate,
                ],
            ]
        ];
    }

    private function split_usphl_team_name($team)
    {
        // Special case: capture if "Jr" or "Junior" is part of the team name
        if (preg_match('/^(.+?)\s+(Jr(?:\.|)|Junior\s+\w+.*)$/i', $team, $matches)) {
            return ['city' => $matches[1], 'name' => $matches[2]];
        }

        // Default: split on last space
        if (preg_match('/^(.+)\s+(\S+)$/', $team, $matches)) {
            return ['city' => $matches[1], 'name' => $matches[2]];
        }

        return ['city' => $team, 'name' => $team];
    }

    private function getNextGameInformation($game_id, $source_type)
    {
        global $wpdb;

        $table = $wpdb->prefix . "pp_game_schedule_for_display";

        // Step 1: get the current game's timestamp
        $current = $wpdb->get_row(
            $wpdb->prepare("
            SELECT game_timestamp
            FROM $table
            WHERE game_id = %s
              AND source_type = %s
            LIMIT 1
        ", $game_id, $source_type)
        );

        if (!$current) {
            return null; // game not found
        }

        // Step 2: find the next game after this timestamp
        $next_game = $wpdb->get_row(
            $wpdb->prepare("
            SELECT *
            FROM $table
            WHERE source_type = %s
              AND game_timestamp > %s
            ORDER BY game_timestamp ASC
            LIMIT 1
        ", $source_type, $current->game_timestamp)
        );

        if ($next_game) {
            return [
                'date_day'       => $next_game->game_date_day,
                'time'       => $next_game->game_time,
                'venue'      => $next_game->venue,
                'home_or_away' => $next_game->home_or_away,
                'opponent'  => $next_game->opponent_team_name,
                'game_id'    => $next_game->game_id
            ];
        }

        return null; // no next game
    }

    private function getHomeAndAwayTeamLogosAndIds($game_id, $source_type)
    {
        global $wpdb;

        $table = $wpdb->prefix . "pp_game_schedule_for_display";

        // Fetch exactly one row that matches game_id and source_type
        $game = $wpdb->get_row(
            $wpdb->prepare("
            SELECT target_team_logo, opponent_team_logo, home_or_away, target_team_id, opponent_team_id
            FROM $table
            WHERE game_id = %s
              AND source_type = %s
            LIMIT 1
        ", $game_id, $source_type)
        );

        if ($game) {
            // Return as associative array
            if ($game->home_or_away === 'home') {
                return [
                    'home_team_logo'   => $game->target_team_logo,
                    'away_team_logo' => $game->opponent_team_logo,
                    'home_or_away'       => $game->home_or_away,
                    'home_team_id'       => $game->target_team_id,
                    'away_team_id'       => $game->opponent_team_id,
                    'target_team_id'       => $game->target_team_id,
                ];
            } else {
                return [
                    'away_team_logo'   => $game->target_team_logo,
                    'home_team_logo' => $game->opponent_team_logo,
                    'home_or_away'       => $game->home_or_away,
                    'away_team_id'       => $game->target_team_id,
                    'home_team_id'       => $game->opponent_team_id,
                    'target_team_id'       => $game->target_team_id,
                ];
            }
        } else {
            throw new Exception('Game not found in the database');
        }
    }

    public function getImageFromImageAPI($bodyData)
    {
        $apiUrl = 'https://6bl3vhnaqh.execute-api.us-east-2.amazonaws.com/default/post-game-summary-graphic-api';
        $apiKey = get_option('pp_image_api_key');

        $response = wp_remote_post($apiUrl, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body'    => json_encode($bodyData),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Image API request failed: ' . $response->get_error_message());
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        if ($statusCode !== 200) {
            error_log("Image API returned status $statusCode: $responseBody");
            return "Image API returned status $statusCode: $responseBody"; // return error message from API
        }

        return $responseBody;
    }

    /**
     * Transform parsed ACHA score sheet into API-friendly format
     */
    private function transformAchaData($data)
    {
        // Helper function to clean team names (remove "MD2 " prefix)
        $cleanTeamName = function ($name) {
            return preg_replace('/^(MD[1-3]|WD[1-2])\s+/', '', $name);
        };

        // Helper function to transform skater data
        $transformSkaters = function ($skaters) {
            $result = [];
            foreach ($skaters as $skater) {
                // Only include players with stats or significant data
                if (
                    $skater['stats']['goals'] > 0 ||
                    $skater['stats']['assists'] > 0 ||
                    $skater['stats']['penaltyMinutes'] > 0 ||
                    !empty($skater['info']['firstName']) && !empty($skater['info']['lastName'])
                ) {

                    $result[] = [
                        'info' => [
                            'jerseyNumber' => $skater['info']['jerseyNumber'],
                            'position' => $skater['info']['position'],
                            'firstName' => $skater['info']['firstName'],
                            'lastName' => $skater['info']['lastName']
                        ],
                        'stats' => [
                            'goals' => $skater['stats']['goals'],
                            'assists' => $skater['stats']['assists'],
                            'points' => $skater['stats']['points'],
                            'penaltyMinutes' => $skater['stats']['penaltyMinutes']
                        ]
                    ];
                }
            }
            return $result;
        };

        // Helper function to transform goalie log
        $transformGoalieLog = function ($goalieLog) {
            $result = [];
            foreach ($goalieLog as $goalie) {
                $result[] = [
                    'info' => [
                        'jerseyNumber' => $goalie['info']['jerseyNumber'],
                        'firstName' => $goalie['info']['firstName'],
                        'lastName' => $goalie['info']['lastName']
                    ],
                    'stats' => [
                        'saves' => $goalie['stats']['saves']
                    ]
                ];
            }
            return $result;
        };

        $logos_ids = $this->getHomeAndAwayTeamLogosAndIds($this->game_id, $this->source_type);

        // Get next game information
        $next_game = $this->getNextGameInformation($this->game_id, $this->source_type);
        
        // Transform the data structure
        $transformed = [
            'league' => 'acha',
            'targetTeamId' => $logos_ids['target_team_id'],
            'nextGameInfo' => $next_game,
            'highLevelStats' => [
                'league' => 'acha',
                'homeTeam' => [
                    'info' => [
                        'id' => $data['homeTeam']['info']['id'],
                        'name' => $cleanTeamName($data['homeTeam']['info']['name']),
                        'nickname' => $data['homeTeam']['info']['nickname'],
                        'logo' => $data['homeTeam']['info']['logo']
                    ],
                    'stats' => [
                        'goals' => $data['homeTeam']['stats']['goals'],
                        'shots' => $data['homeTeam']['stats']['shots'],
                        'powerPlayGoals' => $data['homeTeam']['stats']['powerPlayGoals'],
                        'infractionCount' => $data['homeTeam']['stats']['infractionCount']
                    ],
                    'skaters' => $transformSkaters($data['homeTeam']['skaters']),
                    'goalieLog' => $transformGoalieLog($data['homeTeam']['goalieLog'])
                ],
                'visitingTeam' => [
                    'info' => [
                        'id' => $data['visitingTeam']['info']['id'],
                        'name' => $cleanTeamName($data['visitingTeam']['info']['name']),
                        'nickname' => $data['visitingTeam']['info']['nickname'],
                        'logo' => $data['visitingTeam']['info']['logo']
                    ],
                    'stats' => [
                        'goals' => $data['visitingTeam']['stats']['goals'],
                        'shots' => $data['visitingTeam']['stats']['shots'],
                        'powerPlayGoals' => $data['visitingTeam']['stats']['powerPlayGoals'],
                        'infractionCount' => $data['visitingTeam']['stats']['infractionCount']
                    ],
                    'skaters' => $transformSkaters($data['visitingTeam']['skaters']),
                    'goalieLog' => $transformGoalieLog($data['visitingTeam']['goalieLog'])
                ],
                'details' => [
                    'status' => $data['details']['status'],
                    'simpleStatus' => $this->determineStatus($data['details']['status']),
                    'GameDateISO8601' => $data['details']['GameDateISO8601'],
                    'venue' => $data['details']['venue'],
                ]
            ]
        ];

        return $transformed;
    }

    private function determineStatus($status)
    {
        $status = trim($status);

        // Completed: matches "Final", "Final SO", "Final OT1", etc.
        if (preg_match('/^Final(\s+.*)?$/i', $status)) {
            return 'completed';
        }

        // Scheduled: matches time formats like "7:30 PM", with optional timezone
        if (preg_match('/^\d{1,2}:\d{2}\s?(AM|PM)(\s?[A-Z]{2,4})?$/i', $status)) {
            return 'scheduled';
        }

        return 'unknown';
    }
}
