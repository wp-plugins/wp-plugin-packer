<?php

/**
 *
 * @link              https://github.com/AZdv/wp-plugin-packer
 * @since             1.0.0
 * @package           Wp_Plugin_Packer
 *
 * @wordpress-plugin
 * Plugin Name:       WP Plugin Packer
 * Plugin URI:        https://github.com/AZdv/wp-plugin-packer
 * Description:       Lets you create plugin packs (=groups) to export and import to various WordPress websites
 * Version:           1.0.0
 * Author:            Asaf Zamir
 * Author URI:        http://www.kidsil.net
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-plugin-packer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function activate_wp_plugin_packer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-plugin-packer-activator.php';
	Wp_Plugin_Packer_Activator::activate();
}

function deactivate_wp_plugin_packer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-plugin-packer-deactivator.php';
	Wp_Plugin_Packer_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_plugin_packer' );
register_deactivation_hook( __FILE__, 'deactivate_wp_plugin_packer' );

/**
 * The core plugin class that is used to define internationalization,
 * dashboard-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-plugin-packer-helper.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-plugin-packer.php';

function run_wp_plugin_packer() {

	$plugin = new Wp_Plugin_Packer();
	$plugin->run();

}
run_wp_plugin_packer();
