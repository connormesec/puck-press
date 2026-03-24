<?php
// File: includes/class-puck-press-cron.php

class Puck_Press_Cron {

	const HOOK                      = 'puck_press_cron_hook';
	const OPTION_ENABLED            = 'puck_press_cron_enabled';
	const OPTION_LAST_RUN           = 'puck_press_cron_last_run';
	const OPTION_LAST_RUN_TIMESTAMP = 'puck_press_cron_last_run_timestamp';
	const OPTION_SCHEDULE           = 'puck_press_cron_schedule';
	const OPTION_FAILURE_COUNTS     = 'puck_press_cron_failure_counts';
	const OPTION_LAST_EMAIL_SENT    = 'puck_press_cron_last_email_sent';

	// Send an alert email after this many consecutive failures per subsystem.
	const FAILURE_EMAIL_THRESHOLD = 2;

	private $cron_messages = array();

	public function __construct() {
		// Register the cron action only.
		// maybe_schedule_cron is registered by the loader in class-puck-press.php.
		add_action( self::HOOK, array( $this, 'run_cron_task' ) );
	}

	public function maybe_schedule_cron() {
		$enabled = get_option( self::OPTION_ENABLED, true );

		if ( ! $enabled ) {
			if ( wp_next_scheduled( self::HOOK ) ) {
				$this->unschedule_cron();
				error_log( 'Puck Press Cron: Unscheduled cron because it\'s disabled' );
			}
			return;
		}

		$current_schedule = get_option( self::OPTION_SCHEDULE, 'twicedaily' );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			error_log( 'Puck Press Cron: Scheduling new cron event with schedule: ' . $current_schedule );
			$this->schedule_cron( $current_schedule );
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Puck Press Cron: Event already scheduled for ' . wp_date( 'Y-m-d H:i:s', wp_next_scheduled( self::HOOK ) ) );
			}
		}
	}

	public function schedule_cron( $schedule = null ) {
		if ( $schedule === null ) {
			$schedule = get_option( self::OPTION_SCHEDULE, 'twicedaily' );
		}

		wp_clear_scheduled_hook( self::HOOK );

		$available_schedules = wp_get_schedules();
		if ( ! isset( $available_schedules[ $schedule ] ) ) {
			error_log( 'Puck Press Cron: Invalid schedule "' . $schedule . '", falling back to twicedaily' );
			$schedule = 'twicedaily';
		}

		$scheduled = wp_schedule_event( time(), $schedule, self::HOOK );

		if ( $scheduled === false ) {
			error_log( 'Puck Press Cron: Failed to schedule cron event with schedule: ' . $schedule );

			if ( $schedule !== 'twicedaily' ) {
				$fallback_scheduled = wp_schedule_event( time(), 'twicedaily', self::HOOK );
				if ( $fallback_scheduled === false ) {
					error_log( 'Puck Press Cron: Fallback scheduling also failed' );
					return false;
				} else {
					error_log( 'Puck Press Cron: Fallback to twicedaily schedule successful' );
					update_option( self::OPTION_SCHEDULE, 'twicedaily' );
					return true;
				}
			} else {
				return false;
			}
		} else {
			error_log( 'Puck Press Cron: Successfully scheduled with ' . $schedule . ' interval' );
			update_option( self::OPTION_SCHEDULE, $schedule );
			$next_run = wp_next_scheduled( self::HOOK );
			error_log( 'Puck Press Cron: Next run scheduled for ' . wp_date( 'Y-m-d H:i:s', $next_run ) );
			return true;
		}
	}

	public function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			error_log( 'Puck Press Cron: Unscheduled event at timestamp ' . $timestamp );
		}

		wp_clear_scheduled_hook( self::HOOK );
	}

	public function run_cron_task() {
		// Prevent the process from being killed mid-run by PHP's execution timeout.
		@set_time_limit( 300 ); // phpcs:ignore

		$this->log_message( 'Puck Press Cron: Starting cron task execution' );
		$this->log_message( 'Puck Press Cron: PHP max_execution_time = ' . ini_get( 'max_execution_time' ) . 's' );

		$enabled = get_option( self::OPTION_ENABLED, true );
		if ( ! $enabled ) {
			$this->log_message( 'Puck Press Cron: Task is disabled via option' );
			return;
		}

		$this->load_dependencies();

		$start_time     = microtime( true );
		$failure_counts = get_option( self::OPTION_FAILURE_COUNTS, array() );

		$schedule_ok        = false;
		$no_active_schedule = false;
		$roster_ok          = false;
		$no_active_roster   = false;

		try {
			$teams_utils          = new Puck_Press_Teams_Wpdb_Utils();
			$all_teams            = $teams_utils->get_all_teams();
			$all_sched_no_active  = false;
			$all_roster_no_active = false;
			$any_team_processed   = false;

			foreach ( $all_teams as $team ) {
				$team_id            = (int) $team['id'];
				$team_name          = $team['name'] ?? "Team {$team_id}";
				$any_team_processed = true;

				// Schedule refresh
				$t_importer = new Puck_Press_Team_Source_Importer( $team_id );
				$results    = $t_importer->rebuild_team_and_cascade();

				$team_sched_no_active = in_array( 'No active sources to import.', $results['messages'] ?? array(), true );
				$team_sched_ok        = ( $results['success_count'] ?? 0 ) > 0 || $team_sched_no_active;

				if ( $team_sched_no_active ) {
					$all_sched_no_active = true;
				}

				if ( $team_sched_ok ) {
					$this->log_message( "Puck Press Cron: Team '{$team_name}' schedule refreshed and materialized." );
					$schedule_ok = true;
				} else {
					$this->log_error( "Puck Press Cron: Team '{$team_name}' schedule import returned no data." );
					foreach ( $results['errors'] ?? array() as $err ) {
						$source = is_array( $err ) ? ( $err['source'] ?? 'unknown' ) : 'unknown';
						$msg    = is_array( $err ) ? ( $err['message'] ?? print_r( $err, true ) ) : (string) $err;
						$this->log_error( "Puck Press Cron: Team '{$team_name}' schedule source '{$source}' error — {$msg}" );
					}
				}

				// Roster refresh
				$r_importer    = new Puck_Press_Team_Roster_Importer( $team_id );
				$roster_result = $r_importer->rebuild_team_and_cascade();
				$roster_import = $roster_result['import'] ?? array();

				$team_roster_no_active = ! empty(
					array_filter(
						$roster_import['messages'] ?? array(),
						fn( $m ) => strpos( $m, 'No active sources' ) !== false
					)
				);
				$team_roster_ok = ( $roster_import['success_count'] ?? 0 ) > 0 || $team_roster_no_active;

				if ( $team_roster_no_active ) {
					$all_roster_no_active = true;
				}

				if ( $team_roster_ok ) {
					$this->log_message( "Puck Press Cron: Team '{$team_name}' roster refreshed and materialized." );
					$roster_ok = true;
				} else {
					$this->log_error( "Puck Press Cron: Team '{$team_name}' roster import returned no data." );
					foreach ( $roster_import['errors'] ?? array() as $err ) {
						$source = is_array( $err ) ? ( $err['source'] ?? 'unknown' ) : 'unknown';
						$msg    = is_array( $err ) ? ( $err['message'] ?? print_r( $err, true ) ) : (string) $err;
						$this->log_error( "Puck Press Cron: Team '{$team_name}' roster source '{$source}' error — {$msg}" );
					}
				}
			}

			// If the loop never ran, an exception fired before the first team — treat as failure.
			if ( ! $any_team_processed ) {
				$no_active_schedule = false;
				$no_active_roster   = false;
			} else {
				$no_active_schedule = $all_sched_no_active && ! $schedule_ok;
				$no_active_roster   = $all_roster_no_active && ! $roster_ok;
			}

			$execution_time = round( microtime( true ) - $start_time, 2 );
			$this->log_message( 'Puck Press Cron: Schedule/roster refresh completed in ' . $execution_time . 's' );
		} catch ( Exception $e ) {
			$execution_time = round( microtime( true ) - $start_time, 2 );
			$this->log_error( 'Puck Press Cron: Error during schedule/roster refresh after ' . $execution_time . 's — ' . $e->getMessage() );
			$this->log_error( 'Puck Press Cron: Stack trace — ' . $e->getTraceAsString() );
		}

		if ( $schedule_ok || $no_active_schedule ) {
			$failure_counts['schedule'] = 0;
		} else {
			$failure_counts['schedule'] = ( $failure_counts['schedule'] ?? 0 ) + 1;
			$this->log_error(
				sprintf(
					'Puck Press Cron: Schedule has failed %d consecutive time(s).',
					$failure_counts['schedule']
				)
			);
		}

		if ( $roster_ok || $no_active_roster ) {
			$failure_counts['roster'] = 0;
		} else {
			$failure_counts['roster'] = ( $failure_counts['roster'] ?? 0 ) + 1;
			$this->log_error(
				sprintf(
					'Puck Press Cron: Roster has failed %d consecutive time(s).',
					$failure_counts['roster']
				)
			);
		}

		// Create game posts
		try {
			include_once plugin_dir_path( __FILE__ ) . 'game-summary-post/class-puck-press-game-post-creator.php';
			$game_post_creator = new Puck_Press_Game_Post_Creator();
			$messages          = $game_post_creator->run_daily();
			$post_errors       = 0;

			foreach ( $messages as $msg ) {
				$this->log_message( 'Puck Press Cron: Game Post Creator — ' . $msg );
				if ( stripos( $msg, 'failed' ) !== false || stripos( $msg, 'error' ) !== false ) {
					++$post_errors;
				}
			}

			if ( $post_errors === 0 ) {
				$failure_counts['game_posts'] = 0;
			} else {
				$failure_counts['game_posts'] = ( $failure_counts['game_posts'] ?? 0 ) + 1;
				$this->log_error(
					sprintf( 'Puck Press Cron: Game post creator had %d soft error(s).', $post_errors )
				);
			}
		} catch ( Exception $e ) {
			$failure_counts['game_posts'] = ( $failure_counts['game_posts'] ?? 0 ) + 1;
			$this->log_error( 'Puck Press Cron: Error during game post creation — ' . $e->getMessage() );
			$this->log_error( 'Puck Press Cron: Stack trace — ' . $e->getTraceAsString() );
		}

		// Import from Instagram
		try {
			include_once plugin_dir_path( __FILE__ ) . 'instagram-post-importer/class-puck-press-instagram-post-importer.php';
			$instagram_importer = new Puck_Press_Instagram_Post_Importer();
			$messages           = $instagram_importer->run_daily();
			foreach ( $messages as $msg ) {
				$this->log_message( 'Puck Press Cron: Instagram Importer — ' . $msg );
			}
			$failure_counts['instagram'] = 0;
		} catch ( Exception $e ) {
			$failure_counts['instagram'] = ( $failure_counts['instagram'] ?? 0 ) + 1;
			$this->log_error( 'Puck Press Cron: Error during Instagram import — ' . $e->getMessage() );
			$this->log_error( 'Puck Press Cron: Stack trace — ' . $e->getTraceAsString() );
		}

		// Strip any orphaned team_* keys left over from a previous version of the importer.
		$failure_counts = array_intersect_key(
			$failure_counts,
			array_flip( array( 'schedule', 'roster', 'game_posts', 'instagram' ) )
		);

		update_option( self::OPTION_FAILURE_COUNTS, $failure_counts );
		$this->maybe_send_failure_email( $failure_counts );

		$current_time = current_time( 'mysql' );
		update_option( self::OPTION_LAST_RUN, $current_time );
		update_option( self::OPTION_LAST_RUN_TIMESTAMP, time() );
		update_option( 'puck_press_cron_last_log', $this->cron_messages );

		$this->log_message( 'Puck Press Cron: Updated last run time to ' . $current_time );
	}

	private function load_dependencies() {
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-materializer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-registry-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-normalizer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-acha-url.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-acha-stats.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-usphl-url.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-csv-data.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-team-roster-importer.php';
	}

	/**
	 * Send an admin email when one or more subsystems have exceeded the consecutive
	 * failure threshold. Throttled to at most one email per 24 hours.
	 *
	 * @param array $failure_counts Current failure counts keyed by subsystem name.
	 */
	private function maybe_send_failure_email( array $failure_counts ) {
		$failing = array_filter(
			$failure_counts,
			fn( $count ) => $count >= self::FAILURE_EMAIL_THRESHOLD
		);

		if ( empty( $failing ) ) {
			return;
		}

		$last_sent = (int) get_option( self::OPTION_LAST_EMAIL_SENT, 0 );
		if ( time() - $last_sent < DAY_IN_SECONDS ) {
			return;
		}

		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );
		$admin_url   = admin_url( 'admin.php?page=puck-press-settings' );

		$subject = sprintf( '[%s] Puck Press cron failures detected', $site_name );

		$lines   = array();
		$lines[] = 'The Puck Press cron job has encountered repeated failures on the following subsystems:';
		$lines[] = '';

		foreach ( $failing as $subsystem => $count ) {
			$lines[] = sprintf( '  • %s — %d consecutive failure(s)', ucfirst( str_replace( '_', ' ', $subsystem ) ), $count );
		}

		$lines[] = '';
		$lines[] = 'Recent log entries:';
		$lines[] = '-------------------';

		$recent = array_slice( $this->cron_messages, -20 );
		foreach ( $recent as $entry ) {
			$lines[] = $entry;
		}

		$lines[] = '';
		$lines[] = 'Review the cron settings and logs here:';
		$lines[] = $admin_url;
		$lines[] = '';
		$lines[] = '-- Puck Press';

		$body = implode( "\n", $lines );

		$sent = wp_mail( $admin_email, $subject, $body );

		if ( $sent ) {
			update_option( self::OPTION_LAST_EMAIL_SENT, time() );
			$this->log_message( 'Puck Press Cron: Failure alert email sent to ' . $admin_email );
		} else {
			$this->log_message( 'Puck Press Cron: Failed to send failure alert email to ' . $admin_email );
		}
	}

	private function log_message( $message ) {
		$timestamp             = current_time( 'mysql' );
		$this->cron_messages[] = "[$timestamp] $message";
	}

	private function log_error( $message ) {
		$timestamp             = current_time( 'mysql' );
		$entry                 = "[$timestamp] [ERROR] $message";
		$this->cron_messages[] = $entry;
		error_log( $entry );
	}

	public static function run_manually() {
		error_log( 'Puck Press Cron: Manual execution triggered' );
		do_action( self::HOOK );
	}

	public function get_status() {
		$next_run           = wp_next_scheduled( self::HOOK );
		$enabled            = get_option( self::OPTION_ENABLED, true );
		$last_run           = get_option( self::OPTION_LAST_RUN, 'Never' );
		$last_run_timestamp = get_option( self::OPTION_LAST_RUN_TIMESTAMP, 0 );
		$schedule           = get_option( self::OPTION_SCHEDULE, 'twicedaily' );

		$failure_counts  = get_option( self::OPTION_FAILURE_COUNTS, array() );
		$last_email_sent = (int) get_option( self::OPTION_LAST_EMAIL_SENT, 0 );

		return array(
			'enabled'               => $enabled,
			'schedule'              => $schedule,
			'next_run'              => $next_run,
			'next_run_formatted'    => $next_run ? wp_date( 'Y-m-d H:i:s', $next_run ) : 'Not scheduled',
			'last_run'              => $last_run,
			'last_run_timestamp'    => $last_run_timestamp,
			'last_run_human'        => $last_run_timestamp ? human_time_diff( $last_run_timestamp ) . ' ago' : 'Never',
			'is_scheduled'          => (bool) $next_run,
			'hook_name'             => self::HOOK,
			'failure_counts'        => $failure_counts,
			'last_email_sent'       => $last_email_sent,
			'last_email_sent_human' => $last_email_sent ? human_time_diff( $last_email_sent ) . ' ago' : 'Never',
		);
	}

	public function debug_cron_status() {
		$next_run  = wp_next_scheduled( self::HOOK );
		$schedules = wp_get_schedules();
		$status    = $this->get_status();

		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$cron_status   = $cron_disabled ? 'disabled via DISABLE_WP_CRON' : 'enabled';

		$debug_info = array(
			'hook'                     => self::HOOK,
			'enabled'                  => $status['enabled'],
			'selected_schedule'        => $status['schedule'],
			'next_run'                 => $next_run,
			'next_run_formatted'       => $status['next_run_formatted'],
			'last_run'                 => $status['last_run'],
			'last_run_timestamp'       => $status['last_run_timestamp'],
			'schedules_available'      => array_keys( $schedules ),
			'selected_schedule_exists' => isset( $schedules[ $status['schedule'] ] ) ? 'Yes' : 'No',
			'wp_cron_status'           => $cron_status,
			'disable_wp_cron_defined'  => defined( 'DISABLE_WP_CRON' ) ? 'Yes' : 'No',
			'current_time'             => wp_date( 'Y-m-d H:i:s' ),
			'current_timestamp'        => time(),
		);

		if ( isset( $schedules[ $status['schedule'] ] ) ) {
			$schedule_info                                  = $schedules[ $status['schedule'] ];
			$debug_info['selected_schedule_interval']       = $schedule_info['interval'];
			$debug_info['selected_schedule_display']        = $schedule_info['display'];
			$debug_info['selected_schedule_interval_hours'] = round( $schedule_info['interval'] / HOUR_IN_SECONDS, 2 );
		}

		$debug_info['all_schedules'] = array();
		foreach ( $schedules as $key => $schedule ) {
			$debug_info['all_schedules'][ $key ] = array(
				'display'        => $schedule['display'],
				'interval'       => $schedule['interval'],
				'interval_hours' => round( $schedule['interval'] / HOUR_IN_SECONDS, 2 ),
			);
		}

		error_log( 'Puck Press Cron Debug: ' . print_r( $debug_info, true ) );

		return $debug_info;
	}

	public function health_check() {
		$issues = array();
		$status = $this->get_status();

		if ( $status['enabled'] && ! $status['is_scheduled'] ) {
			$issues[] = 'Cron is enabled but not scheduled';
		}

		if ( ! $status['enabled'] && $status['is_scheduled'] ) {
			$issues[] = 'Cron is disabled but still scheduled';
		}

		$schedules = wp_get_schedules();
		if ( ! isset( $schedules[ $status['schedule'] ] ) ) {
			$issues[] = 'Selected schedule "' . $status['schedule'] . '" does not exist';
		}

		return array(
			'healthy' => empty( $issues ),
			'issues'  => $issues,
			'status'  => $status,
		);
	}

	public function get_available_schedules() {
		return wp_get_schedules();
	}
}
