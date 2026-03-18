<?php

require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-render-utils-abstract.php';

class Puck_Press_Roster_Render_Utils extends Puck_Press_Render_Utils_Abstract {

	private int $roster_id;

	public function __construct( int $roster_id = 1 ) {
		$this->roster_id = $roster_id;
		$this->load_dependencies();

		$this->template_manager = new Puck_Press_Roster_Template_Manager( $roster_id );
		$this->wpdb_utils       = new Puck_Press_Roster_Wpdb_Utils();

		global $wpdb;
		$registry    = new Puck_Press_Roster_Registry_Wpdb_Utils();
		$team_ids    = $registry->get_roster_team_ids( $roster_id );
		if ( ! empty( $team_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $team_ids ), '%d' ) );
			$this->games  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}pp_team_players_display WHERE team_id IN ($placeholders)",
					$team_ids
				),
				ARRAY_A
			) ?? array();
		} else {
			$this->games = array();
		}

		$this->templates             = $this->template_manager->get_all_templates();
		$this->selected_template_key = $this->template_manager->get_current_template_key();
	}

	public function load_dependencies(): void {
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . '../../public/templates/class-puck-press-roster-template-manager.php';
		require_once plugin_dir_path( __FILE__ ) . '../class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-roster-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-roster-registry-wpdb-utils.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-roster-player-detail.php';
	}

	public function get_current_template_html( array $options = array() ): string {
		$options['roster_id'] = $this->roster_id;
		return $this->build_schema() . $this->get_template_html( $this->selected_template_key, $options );
	}

	public static function build_player_schema( array $player ): string {
		$position_labels = array(
			'F'  => 'Forward',
			'C'  => 'Center',
			'LW' => 'Left Wing',
			'RW' => 'Right Wing',
			'D'  => 'Defenseman',
			'LD' => 'Left Defense',
			'RD' => 'Right Defense',
			'G'  => 'Goalie',
		);

		$pos_code  = strtoupper( $player['pos'] ?? '' );
		$pos_label = $position_labels[ $pos_code ] ?? $pos_code;
		$number    = $player['number'] ?? '';

		$person = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => $player['name'] ?? '',
			'memberOf' => array(
				'@type' => 'SportsTeam',
				'name'  => get_bloginfo( 'name' ),
				'sport' => 'Ice Hockey',
			),
		);

		if ( $pos_label ) {
			$person['jobTitle'] = $pos_label;
		}
		if ( $number ) {
			$person['description'] = '#' . $number . ( $pos_label ? ' · ' . $pos_label : '' );
		}
		if ( ! empty( $player['headshot_link'] ) ) {
			$person['image'] = $player['headshot_link'];
		}
		if ( ! empty( $player['hometown'] ) ) {
			$person['homeLocation'] = array(
				'@type' => 'Place',
				'name'  => $player['hometown'],
			);
		}

		return '<script type="application/ld+json">'
			. wp_json_encode( $person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
			. '</script>';
	}

	protected function build_schema(): string {
		if ( empty( $this->games ) ) {
			return '';
		}

		$athletes = array();
		foreach ( $this->games as $player ) {
			$desc_parts = array_filter(
				array(
					! empty( $player['pos'] ) ? $player['pos'] : null,
					! empty( $player['number'] ) ? '#' . $player['number'] : null,
					! empty( $player['hometown'] ) ? $player['hometown'] : null,
				)
			);

			$athlete = array(
				'@type' => 'Person',
				'name'  => $player['name'],
			);
			if ( $desc_parts ) {
				$athlete['description'] = implode( ', ', $desc_parts );
			}
			$athletes[] = $athlete;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'SportsTeam',
			'name'     => get_bloginfo( 'name' ),
			'sport'    => 'Ice Hockey',
			'athlete'  => $athletes,
		);

		return '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
			. '</script>';
	}
}
