<?php

/**
 * Hockey Game Blog Generator
 * 
 * Expected input data structure:
 * {
 *   "highLevelStats": {
 *     "homeTeam": {
 *       "info": {
 *         "id": "123",
 *         "name": "University Name",
 *         "nickname": "TeamName"
 *       },
 *       "stats": {
 *         "goals": 3,
 *         "shots": 25,
 *         "powerPlayGoals": 1,
 *         "infractionCount": 4
 *       },
 *       "goalieLog": [
 *         {
 *           "info": {
 *             "firstName": "John",
 *             "lastName": "Doe"
 *           },
 *           "stats": {
 *             "shotsAgainst": 22,
 *             "saves": 19
 *           }
 *         }
 *       ],
 *       "skaters": [
 *         {
 *           "info": {
 *             "jerseyNumber": "15",
 *             "position": "F",
 *             "firstName": "Player",
 *             "lastName": "Name"
 *           },
 *           "stats": {
 *             "points": 2,
 *             "goals": 1,
 *             "assists": 1,
 *             "penaltyMinutes": 0
 *           }
 *         }
 *       ]
 *     },
 *     "visitingTeam": {
 *       // Same structure as homeTeam
 *     },
 *     "details": {
 *       "status": "Final",
 *       "date": "2024-01-15",
 *       "venue": "Arena Name"
 *     }
 *   },
 *   "targetTeamId": "123",
 *   "nextGameInfo": {
 *     "visitingTeamCity": "Away Team",
 *     "homeTeamCity": "Home Team",
 *     "dateWithDay": "Sat, Jan 20",
 *     "gameStatus": "7:00 PM"
 *   }
 * }
 */

class Class_Hockey_Game_Blog_Generator
{
    private $openaiApiKey;

    public function __construct($openaiApiKey)
    {
        $this->openaiApiKey = $openaiApiKey;
    }

    /**
     * Generate a blog post for a hockey game
     * 
     * @param array $gameData The game data structure (see comment above)
     * @return string The generated blog post content
     * @throws Exception If there's an error generating the blog post
     */
    public function generateGameBlog($gameData)
    {
        if (!isset($gameData['highLevelStats'])) {
            throw new Exception('Invalid game data structure');
        }

        if ($gameData['highLevelStats']['details']['simpleStatus'] !== 'completed') {
            throw new Exception('Game is not completed, cannot generate blog post');
        }

        $targetTeamData = $this->assignTargetTeam($gameData);
        $prompt = $this->createPrompt($targetTeamData, $gameData);

        return $this->getTextFromOpenAI($prompt);
    }

    /**
     * Get text from OpenAI API
     */
    private function getTextFromOpenAI($prompt)
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a sports writer for a hockey blog. All output must follow the exact format:
[TITLE]
Post body text here.

Do not add extra words in the brackets. Do not use parentheses. Do not add "Title:".
Keep the title short, engaging, and in brackets exactly like [Title Here].'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.50,
            'max_tokens' => 700
        ];

        $headers = [
            'Authorization: Bearer ' . $this->openaiApiKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('OpenAI API request failed with status: ' . $httpCode);
        }

        $responseData = json_decode($response, true);

        if (!isset($responseData['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from OpenAI API');
        }

        return $responseData['choices'][0]['message']['content'];
    }

    /**
     * Assign target team based on team ID
     */
    private function assignTargetTeam($gameData)
    {
        $homeTeam = $gameData['highLevelStats']['homeTeam'];
        $visitingTeam = $gameData['highLevelStats']['visitingTeam'];
        $targetTeamId = $gameData['targetTeamId'];

        if ($homeTeam['info']['id'] == $targetTeamId) {
            return [
                'targetTeam' => $homeTeam,
                'otherTeam' => $visitingTeam,
                'isHome' => true
            ];
        } else if ($visitingTeam['info']['id'] == $targetTeamId) {
            return [
                'targetTeam' => $visitingTeam,
                'otherTeam' => $homeTeam,
                'isHome' => false
            ];
        } else {
            throw new Exception('Target team ID not found in game data');
        }
    }

    /**
     * Create the prompt for OpenAI
     */
    private function createPrompt($targetTeamData, $gameData)
    {
        $homeTeam = $gameData['highLevelStats']['homeTeam'];
        $visitingTeam = $gameData['highLevelStats']['visitingTeam'];
        $gameDetails = $gameData['highLevelStats']['details'];

        $homePlayer = $this->determineHighlightedPlayer($homeTeam['skaters']);
        $visitingPlayer = $this->determineHighlightedPlayer($visitingTeam['skaters']);

        $nextGameMessage = $this->getNextGameMessage($gameData);

        $awayPP = $visitingTeam['stats']['powerPlayGoals'] . '/' . $homeTeam['stats']['infractionCount'];
        $homePP = $homeTeam['stats']['powerPlayGoals'] . '/' . $visitingTeam['stats']['infractionCount'];

        $prompt = "Write a blog post for the {$targetTeamData['targetTeam']['info']['name']} {$targetTeamData['targetTeam']['info']['nickname']} hockey team about the game\n";
        $prompt .= "{$visitingTeam['info']['name']} {$visitingTeam['info']['nickname']} {$visitingTeam['stats']['goals']} at {$homeTeam['info']['name']} {$homeTeam['info']['nickname']} {$homeTeam['stats']['goals']} - Status: {$gameDetails['status']}\n";
        $prompt .= "{$gameDetails['GameDateISO8601']} - {$gameDetails['venue']}\n";
        $prompt .= "Score was {$visitingTeam['info']['nickname']} {$visitingTeam['stats']['goals']} - {$homeTeam['info']['nickname']} {$homeTeam['stats']['goals']}\n";
        $prompt .= "{$visitingTeam['info']['nickname']} had {$visitingTeam['stats']['shots']} shots\n";
        $prompt .= "{$homeTeam['info']['nickname']} had {$homeTeam['stats']['shots']} shots\n";
        $prompt .= "{$visitingTeam['info']['nickname']} powerplay was {$awayPP}\n";
        $prompt .= "{$homeTeam['info']['nickname']} powerplay was {$homePP}\n";
        $prompt .= "{$visitingTeam['info']['nickname']} Goalie-{$visitingTeam['goalieLog'][0]['info']['firstName']} {$visitingTeam['goalieLog'][0]['info']['lastName']} (shots-{$homeTeam['stats']['shots']} saves-{$visitingTeam['goalieLog'][0]['stats']['saves']}), ";
        $prompt .= "{$homeTeam['info']['nickname']} {$homeTeam['goalieLog'][0]['info']['firstName']} {$homeTeam['goalieLog'][0]['info']['lastName']} (shots-{$visitingTeam['stats']['shots']} saves-{$homeTeam['goalieLog'][0]['stats']['saves']})\n";
        $prompt .= "{$visitingTeam['info']['nickname']} #{$visitingPlayer['number']} {$visitingPlayer['firstName']} {$visitingPlayer['lastName']} {$visitingPlayer['message']}\n";
        $prompt .= "{$homeTeam['info']['nickname']} #{$homePlayer['number']} {$homePlayer['firstName']} {$homePlayer['lastName']} {$homePlayer['message']}\n";
        $prompt .= $nextGameMessage;

        // Clean up any MD1, MD2, MD3, WD1, WD2 references
        return preg_replace('/MD2 |MD1 |MD3 |WD1 | WD2/', '', $prompt);
    }

    /**
     * Get next game message
     */
    private function getNextGameMessage($gameData)
    {
        if (!isset($gameData['nextGameInfo'])) {
            return '';
        }

        $nextGame = $gameData['nextGameInfo'];

        if (empty($nextGame)) {
            return '';
        }

        return "Next game: {$nextGame['home_or_away']} vs {$nextGame['opponent']} on {$nextGame['date_day']} {$nextGame['time']} at {$nextGame['venue']}.";
    }

    /**
     * Determine highlighted player from skaters array
     */
    private function determineHighlightedPlayer($skaters)
    {
        if (empty($skaters)) {
            return [
                'number' => '0',
                'firstName' => 'Unknown',
                'lastName' => 'Player',
                'message' => '0 POINTS'
            ];
        }

        // Find players with most points
        $maxPoints = max(array_column(array_column($skaters, 'stats'), 'points'));
        $mostPtsPlayers = array_filter($skaters, function ($player) use ($maxPoints) {
            return $player['stats']['points'] == $maxPoints;
        });

        if (count($mostPtsPlayers) > 1) {
            // Multiple players tied for most points
            $maxGoals = max(array_column(array_column($skaters, 'stats'), 'goals'));
            $mostGoalsPlayers = array_filter($skaters, function ($player) use ($maxGoals) {
                return $player['stats']['goals'] == $maxGoals;
            });

            $maxAssists = max(array_column(array_column($skaters, 'stats'), 'assists'));
            $mostAssistsPlayers = array_filter($skaters, function ($player) use ($maxAssists) {
                return $player['stats']['assists'] == $maxAssists;
            });

            $mostGoalsPlayer = $mostGoalsPlayers[array_rand($mostGoalsPlayers)];
            $mostAssistsPlayer = $mostAssistsPlayers[array_rand($mostAssistsPlayers)];

            if ($mostAssistsPlayer['stats']['assists'] > $mostGoalsPlayer['stats']['goals']) {
                return [
                    'number' => $mostAssistsPlayer['info']['jerseyNumber'],
                    'firstName' => $mostAssistsPlayer['info']['firstName'],
                    'lastName' => $mostAssistsPlayer['info']['lastName'],
                    'message' => $mostAssistsPlayer['stats']['assists'] . ' ASSISTS'
                ];
            } else if ($mostGoalsPlayer['stats']['goals'] == 1) {
                return [
                    'number' => $mostGoalsPlayer['info']['jerseyNumber'],
                    'firstName' => $mostGoalsPlayer['info']['firstName'],
                    'lastName' => $mostGoalsPlayer['info']['lastName'],
                    'message' => $mostGoalsPlayer['stats']['goals'] . ' GOAL'
                ];
            } else if ($mostGoalsPlayer['stats']['goals'] > 1) {
                return [
                    'number' => $mostGoalsPlayer['info']['jerseyNumber'],
                    'firstName' => $mostGoalsPlayer['info']['firstName'],
                    'lastName' => $mostGoalsPlayer['info']['lastName'],
                    'message' => $mostGoalsPlayer['stats']['goals'] . ' GOALS'
                ];
            } else {
                // Fall back to penalty minutes
                $maxPenalties = max(array_column(array_column($skaters, 'stats'), 'penaltyMinutes'));
                $mostPenaltiesPlayers = array_filter($skaters, function ($player) use ($maxPenalties) {
                    return $player['stats']['penaltyMinutes'] == $maxPenalties;
                });
                $mostPenaltiesPlayer = $mostPenaltiesPlayers[array_rand($mostPenaltiesPlayers)];

                return [
                    'number' => $mostPenaltiesPlayer['info']['jerseyNumber'],
                    'firstName' => $mostPenaltiesPlayer['info']['firstName'],
                    'lastName' => $mostPenaltiesPlayer['info']['lastName'],
                    'message' => $mostPenaltiesPlayer['stats']['penaltyMinutes'] . ' PENALTY MINUTES'
                ];
            }
        } else {
            // Single player with most points
            $mostPtsPlayer = reset($mostPtsPlayers);

            if ($mostPtsPlayer['stats']['goals'] >= $mostPtsPlayer['stats']['assists']) {
                $goalText = $mostPtsPlayer['stats']['goals'] == 1 ? 'GOAL' : 'GOALS';
                return [
                    'number' => $mostPtsPlayer['info']['jerseyNumber'],
                    'firstName' => $mostPtsPlayer['info']['firstName'],
                    'lastName' => $mostPtsPlayer['info']['lastName'],
                    'message' => $mostPtsPlayer['stats']['goals'] . ' ' . $goalText
                ];
            } else {
                return [
                    'number' => $mostPtsPlayer['info']['jerseyNumber'],
                    'firstName' => $mostPtsPlayer['info']['firstName'],
                    'lastName' => $mostPtsPlayer['info']['lastName'],
                    'message' => $mostPtsPlayer['stats']['assists'] . ' ASSISTS'
                ];
            }
        }
    }
}

// Example usage:
/*
$generator = new HockeyGameBlogGenerator('your-openai-api-key');

$gameData = [
    'highLevelStats' => [
        'homeTeam' => [...],
        'visitingTeam' => [...],
        'details' => [...]
    ],
    'targetTeamId' => '123',
    'nextGameInfo' => [...]
];

try {
    $blogPost = $generator->generateGameBlog($gameData);
    echo $blogPost;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/
