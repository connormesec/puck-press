<?php
/**
 * Admin Loader Class
 *
 * This class handles loading all the necessary admin classes.
 *
 * @package PuckPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Puck_Press_Admin_Loader {

	/**
	 * Load all admin classes.
	 *
	 * @return void
	 */
	public static function load() {
		// Core Admin Classes
		require_once plugin_dir_path( __FILE__ ) . 'class-puck-press-admin.php';

		// Utilities
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/teams/class-puck-press-teams-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedules-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/archive/class-puck-press-archive-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-materializer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-team-source-importer.php';

		require_once plugin_dir_path( __DIR__ ) . 'includes/class-puck-press-tts-api.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-process-acha-url.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-process-usphl-url.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/schedule/class-puck-press-schedule-process-csv-data.php';
	
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-acha-url.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-acha-stats.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-usphl-url.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-process-csv-data.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-normalizer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-roster-registry-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/roster/class-puck-press-team-roster-importer.php';

		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-schedule-template-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-roster-template-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-slider-template-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-record-template-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/record/class-puck-press-record-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-stats-template-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/stats/class-puck-press-stats-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-stat-leaders-template-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/stat-leaders/class-puck-press-stat-leaders-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-post-slider-template-manager.php';

		// Abstracts
		require_once plugin_dir_path( __FILE__ ) . 'components/abstracts/class-puck-press-admin-card-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/abstracts/class-puck-press-admin-modal-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/abstracts/class-puck-press-admin-preview-card-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/abstracts/class-puck-press-admin-groups-card-abstract.php';

		// Schedule Module
						require_once plugin_dir_path( __FILE__ ) . 'components/schedule/class-puck-press-schedule-admin-preview-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/schedule/class-puck-press-schedule-admin-slider-preview-card.php';
			require_once plugin_dir_path( __FILE__ ) . 'components/schedule/schedule-add-game-modal.php';

		// Roster Module
		require_once plugin_dir_path( __FILE__ ) . 'components/roster/class-puck-press-roster-admin-preview-card.php';

		// Divi Page Builder
		require_once plugin_dir_path( __DIR__ ) . 'includes/divi/class-puck-press-divi-page-builder.php';

		// Teams Module
		require_once plugin_dir_path( __FILE__ ) . 'components/teams/class-puck-press-teams-admin-data-sources-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/teams/class-puck-press-teams-admin-games-table-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/teams/class-puck-press-teams-admin-roster-sources-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/teams/class-puck-press-teams-admin-players-table-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/teams/class-puck-press-teams-admin-pages-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/teams/class-puck-press-teams-admin-standings-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/teams/class-puck-press-standings-admin-preview-card.php';

		// Stats Module
		require_once plugin_dir_path( __FILE__ ) . 'components/stats/class-puck-press-stats-admin-preview-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/stats/class-puck-press-stat-leaders-admin-preview-card.php';

		// Game Summary Post Module
		require_once plugin_dir_path( __FILE__ ) . 'components/game-summary-post/game-summary-display-post.php';

		// Insta Post Module
		require_once plugin_dir_path( __FILE__ ) . 'components/insta-post-importer/instagram-post-admin-display.php';

		// Post Slider Module
		require_once plugin_dir_path( __DIR__ ) . 'includes/post-slider/class-puck-press-post-slider-admin-preview-card.php';

		// League News Module
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-league-news-template-manager.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/league-news/class-puck-press-league-news-api.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/league-news/class-puck-press-league-news-admin-preview-card.php';

		// Awards Module
		require_once plugin_dir_path( __DIR__ ) . 'includes/awards/class-puck-press-awards-wpdb-utils.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/templates/class-puck-press-awards-template-manager.php';

		// update checker
		require_once plugin_dir_path( __DIR__ ) . 'includes/update.php';
	}
}
