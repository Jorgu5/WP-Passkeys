<?php
/**
 * Plugin Name: WP Passkeys
 * Plugin URI: https://thecavers.io
 * Description: Login without username and password. The most secure way to login to your WordPress site.
 * Version: 1.0
 * Author: Tommy Sobolew.ski
 * Author URI: https://sobolew.ski/
 * License: A "Slug" license name e.g. GPL2
 *
 * @package WpPasskeys
 */

defined( 'ABSPATH' ) || exit;

// Define constants

define( 'WPPASSKEYS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

const WPPASSKEYS_API_NAMESPACE = 'wp-passkeys';


// Include necessary files.
require_once WPPASSKEYS_PLUGIN_PATH . 'includes/class-plugin.php';

// Activation hook.
register_activation_hook( __FILE__, array( 'WpPasskeys', 'activate' ) );

// Initialization.
WpPasskeys::instance();
