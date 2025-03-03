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

register_activation_hook(__FILE__, [PasskeysPlugin::class, 'activate']);

const WP_PASSKEYS_API_NAMESPACE = 'wp-passkeys';

require_once WP_PASSKEYS_PLUGIN_PATH . 'vendor/autoload.php';

// Initialize session as early as possible
function wp_passkeys_init_session()
{
    // Only for REST API requests to our endpoints
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/wp-json/' . WP_PASSKEYS_API_NAMESPACE) !== false) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                // Use session cookie parameters that work with WordPress
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => COOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                @session_start();
                error_log('[WP Passkeys] Early session initialization for REST API request');
            }
        }
    }
}
add_action('plugins_loaded', 'wp_passkeys_init_session', 1);

try {
    $plugin = PasskeysPlugin::getInstance();
    $plugin->run();
} catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
    echo $e->getMessage();
}

// Add a function to handle SSL redirect
function wp_passkeys_ensure_ssl()
{
    // Skip SSL redirect for CLI and AJAX requests
    if (defined('WP_CLI') || wp_doing_ajax()) {
        return;
    }

    // Skip SSL redirect for local development environments
    $local_domains = ['localhost', '.local', '.test', '127.0.0.1', '.dev'];
    $current_host = $_SERVER['HTTP_HOST'] ?? '';

    $is_local = false;
    foreach ($local_domains as $domain) {
        if (strpos($current_host, $domain) !== false) {
            $is_local = true;
            break;
        }
    }

    // Only redirect to HTTPS if not a local environment and not already using HTTPS
    if (!$is_local && !isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && !is_ssl()) {
        // Make sure we have the necessary functions
        if (!function_exists('wp_redirect')) {
            require_once ABSPATH . WPINC . '/pluggable.php';
        }

        // Build the redirect URL
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        // Add a notice about the redirect
        if (!headers_sent()) {
            wp_redirect($redirect, 301);
            exit;
        } else {
            // If headers are already sent, add JavaScript redirect
            echo '<script>window.location.href = "' . esc_url($redirect) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($redirect) . '"></noscript>';
            echo '<p>Please use a secure connection. <a href="' . esc_url($redirect) . '">Click here to continue</a>.</p>';
            exit;
        }
    }
}

// Hook the SSL check into WordPress init to ensure wp_redirect is available
add_action('init', 'wp_passkeys_ensure_ssl', 5); // Priority 5 to run early
