<?php
/**
 * Plugin Name: WP Passkeys
 * Plugin URI: https://thecavers.io
 * Description: Login without username and password. The most secure way to login to your WordPress site.
 * Version: 0.1.0
 * Author: Tommy Sobolew.ski
 * Author URI: https://sobolew.ski/
 * License: A "Slug" license name e.g. GPL2
 *
 * @package WpPasskeys
 */

use WpPasskeys\Passkeys_Plugin;

defined( 'ABSPATH' ) || exit;

// Define constants.
define( 'WP_PASSKEYS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
const WP_PASSKEYS_API_NAMESPACE = 'wp-passkeys';

require_once WP_PASSKEYS_PLUGIN_PATH . 'vendor/autoload.php';

// Activation hook.
register_activation_hook( __FILE__, array( Passkeys_Plugin::class, 'activate' ) );
register_activation_hook( __FILE__, array( Passkeys_Plugin::class, 'create_tables' ) );

// Initialization.
Passkeys_Plugin::instance()->run();
