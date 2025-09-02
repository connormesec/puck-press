<?php

if (!defined('ABSPATH')) {
    exit;
}

class Parse_Acha_Score_Sheet
{
    public function parseScoreSheet($game_id)
    {
        $url = "https://lscluster.hockeytech.com/feed/index.php?feed=statviewfeed&view=gameSummary&game_id=$game_id&key=e6867b36742a0c9d&site_id=2&client_code=acha&lang=en";

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return [];
        }

        $jsonp = wp_remote_retrieve_body($response);
        // strip everything before the first "(" and after the last ")"
        $start = strpos($jsonp, '(') + 1;
        $end   = strrpos($jsonp, ')');
        $json  = substr($jsonp, $start, $end - $start);

        // decode JSON
        $validJson = json_decode($json, true);

        return $validJson;
    }
}
