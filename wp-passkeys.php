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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use WpPasskeys\PasskeysPlugin;

defined('ABSPATH') || exit;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Define constants.
define('WP_PASSKEYS_PLUGIN_PATH', plugin_dir_path(__FILE__));
const WP_PASSKEYS_API_NAMESPACE = 'wp-passkeys';

require_once WP_PASSKEYS_PLUGIN_PATH . 'vendor/autoload.php';

try {
    PasskeysPlugin::make();
} catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
    echo $e->getMessage();
}
