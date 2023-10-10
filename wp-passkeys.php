<?php

/**
 * Plugin Name: WP Passkeys
 * Plugin URI: https://thecavers.io
 * Description: Login without username and password. The most secure way to login to your WordPress site.
 * Version: 0.9.0
 * Author: Tommy Sobolew.ski
 * Author URI: https://github.com/jorgu5
 * License: A "Slug" license name e.g. GPL2
 *
 * @package WpPasskeys
 */

use WpPasskeys\PasskeysPlugin;

defined('ABSPATH') || exit;

// Define constants.
define('WP_PASSKEYS_PLUGIN_PATH', plugin_dir_path(__FILE__));
const WP_PASSKEYS_API_NAMESPACE = 'wp-passkeys';

require_once WP_PASSKEYS_PLUGIN_PATH . 'vendor/autoload.php';

// Activation hook.
register_activation_hook(__FILE__, array( PasskeysPlugin::class, 'activate' ));

// Initialization
$plugin = PasskeysPlugin::make();
$plugin->run();