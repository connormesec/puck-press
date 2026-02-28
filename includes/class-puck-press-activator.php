<?php

/**
 * Fired during plugin activation
 *
 * @link       https://https://github.com/connormesec/
 * @since      1.0.0
 *
 * @package    Puck_Press
 * @subpackage Puck_Press/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Puck_Press
 * @subpackage Puck_Press/includes
 * @author     Connor Mesec <connormesec@gmail.com>
 */
class Puck_Press_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path(__FILE__) . 'class-puck-press-cron.php';
    	$cron = new Puck_Press_Cron();
    	$cron->schedule_cron();

		require_once plugin_dir_path(__FILE__) . 'class-puck-press-rewrite-manager.php';
		Puck_Press_Rewrite_Manager::add_rules();

		// Register CPTs before flushing so their rewrite rules are included.
		register_post_type('pp_insta_post', array(
			'public'  => true,
			'rewrite' => array('slug' => 'instagram', 'with_front' => false),
		));
		register_post_type('pp_game_summary', array(
			'public'  => true,
			'rewrite' => array('slug' => 'game-recap', 'with_front' => false),
		));

		flush_rewrite_rules();
	}

}
