<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Puck_Press_Standings_Refresher {

    private Puck_Press_Standings_Wpdb_Utils $wpdb_utils;

    public function __construct() {
        $this->wpdb_utils = new Puck_Press_Standings_Wpdb_Utils();
    }

    public function refresh_all_teams(): array {
        $teams_utils = new Puck_Press_Teams_Wpdb_Utils();
        $all_teams   = $teams_utils->get_all_teams();
        $log         = array();
        $fetched     = array();

        foreach ( $all_teams as $team ) {
            $team_id   = (int) $team['id'];
            $team_name = $team['name'] ?? "Team {$team_id}";
            $source    = Puck_Press_Standings_Source_Resolver::get_regular_season_source( $team_id );

            if ( ! $source ) {
                $log[] = "Team '{$team_name}': no regular-season source found, skipped.";
                continue;
            }

            $other       = json_decode( $source['other_data'] ?? '{}', true );
            $season_id   = (string) ( $other['season_id'] ?? '' );
            $division_id = (string) ( $other['division_id'] ?? '' );
            $league_type = $source['type'] === 'usphlGameScheduleUrl' ? 'usphl' : 'acha';
            $dedup_key   = "{$league_type}:{$season_id}:{$division_id}";

            if ( isset( $fetched[ $dedup_key ] ) ) {
                $cached_result = $fetched[ $dedup_key ];
                $this->save_for_team( $team_id, $source, $league_type, $cached_result );
                $log[] = "Team '{$team_name}': cached from dedup ({$dedup_key}).";
                continue;
            }

            $result = $this->fetch_standings( $source, $league_type );
            if ( empty( $result ) ) {
                $log[] = "Team '{$team_name}': fetch returned empty standings.";
                continue;
            }

            $fetched[ $dedup_key ] = $result;
            $this->save_for_team( $team_id, $source, $league_type, $result );
            $count = count( $result['standings'] ?? array() );
            $log[] = "Team '{$team_name}': refreshed ({$count} teams in {$result['division_name']}).";
        }

        return $log;
    }

    public function refresh_team( int $wp_team_id ): array {
        $source = Puck_Press_Standings_Source_Resolver::get_regular_season_source( $wp_team_id );
        if ( ! $source ) {
            return array( 'No regular-season source found for this team.' );
        }

        $league_type = $source['type'] === 'usphlGameScheduleUrl' ? 'usphl' : 'acha';
        $result      = $this->fetch_standings( $source, $league_type );

        if ( empty( $result ) ) {
            return array( 'Fetch returned empty standings.' );
        }

        $this->save_for_team( $wp_team_id, $source, $league_type, $result );
        $count = count( $result['standings'] ?? array() );
        return array( "Refreshed: {$count} teams in {$result['division_name']}." );
    }

    private function fetch_standings( array $source, string $league_type ): array {
        $other       = json_decode( $source['other_data'] ?? '{}', true );
        $api_team_id = $source['source_url_or_path'] ?? '';
        $season_id   = (string) ( $other['season_id'] ?? '' );

        if ( $league_type === 'usphl' ) {
            require_once plugin_dir_path( __DIR__ ) . 'class-puck-press-tts-api.php';
            $fetcher = new Puck_Press_Standings_Fetch_Usphl( $api_team_id, $season_id );
        } else {
            $division_id = (string) ( $other['division_id'] ?? '' );
            $fetcher     = new Puck_Press_Standings_Fetch_Acha( $api_team_id, $season_id, $division_id );
        }

        return $fetcher->fetch();
    }

    private function save_for_team( int $team_id, array $source, string $league_type, array $result ): void {
        $api_team_id = $source['source_url_or_path'] ?? '';
        $standings   = $result['standings'] ?? array();

        foreach ( $standings as &$row ) {
            $row['is_target'] = ( (string) $row['team_id'] === (string) $api_team_id );
        }
        unset( $row );

        $this->wpdb_utils->upsert_standings(
            $team_id,
            (int) $source['id'],
            $league_type,
            $result['division_name'] ?? '',
            $standings
        );
    }
}
