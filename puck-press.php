<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://github.com/connormesec/
 * @since             1.0.0
 * @package           Puck_Press
 *
 * @wordpress-plugin
 * Plugin Name:       Puck Press
 * Plugin URI:        https://www.google.com
 * Description:       This plugin hosts a number of tools for creating the best ice hockey website
 * Version:           1.0.01
 * Author:            Connor Mesec
 * Author URI:        https://https://github.com/connormesec//
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       puck-press
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PUCK_PRESS_VERSION', '1.0.01' );
define( 'PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-puck-press-activator.php
 */
function activate_puck_press() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-puck-press-activator.php';
	Puck_Press_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-puck-press-deactivator.php
 */
function deactivate_puck_press() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-puck-press-deactivator.php';
	Puck_Press_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_puck_press' );
register_deactivation_hook( __FILE__, 'deactivate_puck_press' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-puck-press.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_puck_press() {

	$plugin = new Puck_Press();
	$plugin->run();

}
run_puck_press();
