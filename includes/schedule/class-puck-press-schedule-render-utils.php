<?php

require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-render-utils-abstract.php';

class Puck_Press_Schedule_Render_Utils extends Puck_Press_Render_Utils_Abstract {

	public function __construct( string $archive_key = '', int $schedule_id = 1 ) {
		$this->load_dependencies();

		$this->template_manager = new Puck_Press_Schedule_Template_Manager( $schedule_id );

		$schedules_utils = new Puck_Press_Schedules_Wpdb_Utils();

		if ( $archive_key !== '' ) {
			$team_ids        = $schedules_utils->get_schedule_team_ids( $schedule_id );
			$archive_manager = new Puck_Press_Archive_Manager();
			$team_names      = array();
			if ( ! empty( $team_ids ) ) {
				require_once plugin_dir_path( __FILE__ ) . '../teams/class-puck-press-teams-wpdb-utils.php';
				$teams_utils = new Puck_Press_Teams_Wpdb_Utils();
				foreach ( $team_ids as $tid ) {
					$team = $teams_utils->get_team_by_id( (int) $tid );
					if ( $team && ! empty( $team['name'] ) ) {
						$team_names[] = $team['name'];
					}
				}
			}
			$this->games = $archive_manager->get_archive_games( $archive_key, $team_names );
		} else {
			$this->games = $schedules_utils->get_schedule_games_display( $schedule_id );
		}

		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
	}

	public function load_dependencies(): void {
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-schedule-template-manager.php';
		require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . '../archive/class-puck-press-archive-manager.php';
	}

	protected function build_schema(): string {
		// When the Puck Press SEO integration is active, the schedule schema
		// is emitted as a Yoast graph piece (Puck_Press_Schema_Sports_Event_List)
		// — a single ItemList of upcoming games with proper status, scores,
		// and team logos. Skip the legacy emission to avoid duplicate entities.
		if ( class_exists( 'Puck_Press_Seo_Yoast' ) && Puck_Press_Seo_Yoast::is_active() ) {
			return '';
		}

		if ( empty( $this->games ) ) {
			return '';
		}

		$events = array();
		foreach ( $this->games as $game ) {
			$start_date = ! empty( $game['game_timestamp'] )
				? ( new DateTime( $game['game_timestamp'] ) )->format( DateTime::ATOM )
				: $game['game_date_day'];

			$is_home   = $game['home_or_away'] === 'home';
			$home_name = $is_home ? $game['target_team_name'] : $game['opponent_team_name'];
			$away_name = $is_home ? $game['opponent_team_name'] : $game['target_team_name'];

			$event = array(
				'@type'       => 'SportsEvent',
				'name'        => $game['target_team_name'] . ' vs ' . $game['opponent_team_name'],
				'startDate'   => $start_date,
				'homeTeam'    => array(
					'@type' => 'SportsTeam',
					'name'  => $home_name,
				),
				'awayTeam'    => array(
					'@type' => 'SportsTeam',
					'name'  => $away_name,
				),
				'eventStatus' => 'https://schema.org/EventScheduled',
			);

			if ( ! empty( $game['venue'] ) ) {
				$event['location'] = array(
					'@type' => 'Place',
					'name'  => $game['venue'],
				);
			}

			$events[] = $event;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => $events,
		);

		return '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
			. '</script>';
	}
}
