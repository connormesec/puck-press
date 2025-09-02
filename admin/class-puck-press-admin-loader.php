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
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-puck-press-wpdb-utils-base-abstract.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/schedule/class-puck-press-schedule-wpdb-utils.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/roster/class-puck-press-roster-wpdb-utils.php';
        
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/schedule/class-puck-press-schedule-process-acha-url.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/schedule/class-puck-press-schedule-process-usphl-url.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/schedule/class-puck-press-schedule-process-csv-data.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/schedule/class-puck-press-schedule-source-importer.php';
		
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/roster/class-puck-press-roster-process-acha-url.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/roster/class-puck-press-roster-process-usphl-url.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/roster/class-puck-press-roster-process-csv-data.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/roster/class-puck-press-roster-source-importer.php';
		
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/class-puck-press-template-manager-abstract.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/class-puck-press-schedule-template-manager.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/class-puck-press-roster-template-manager.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/templates/class-puck-press-slider-template-manager.php';

		// Abstracts
		require_once plugin_dir_path( __FILE__ ) . 'components/abstracts/class-puck-press-admin-card-abstract.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/abstracts/class-puck-press-admin-modal-abstract.php';

		// Schedule Module
		require_once plugin_dir_path( __FILE__ ) . 'components/schedule/class-puck-press-schedule-admin-data-sources-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/schedule/class-puck-press-schedule-admin-edits-table-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/schedule/class-puck-press-schedule-admin-games-table-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/schedule/class-puck-press-schedule-admin-preview-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/schedule/class-puck-press-schedule-admin-slider-preview-card.php';

		// Roster Module
		require_once plugin_dir_path( __FILE__ ) . 'components/roster/class-puck-press-roster-admin-data-sources-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/roster/class-puck-press-raw-roster-table-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/roster/class-puck-press-roster-admin-edits-table-card.php';
		require_once plugin_dir_path( __FILE__ ) . 'components/roster/class-puck-press-roster-admin-preview-card.php';

		// Game Summary Post Module
		require_once plugin_dir_path( __FILE__ ) . 'components/game-summary-post/game-summary-display-post.php';

		// Insta Post Module
		require_once plugin_dir_path( __FILE__ ) . 'components/insta-post-importer/instagram-post-admin-display.php';

		//update checker
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/update.php';
	}
}