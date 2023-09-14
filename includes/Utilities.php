<?php

/**
 * Utilities helper for WP Pass Keys.
 */

namespace WpPasskeys;

use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_User;

class Utilities
{
    /**
     * Creates a PublicKeyCredentialRpEntity object.
     *
     * @return PublicKeyCredentialRpEntity The created PublicKeyCredentialRpEntity object.
     */
    public static function getRpEntity(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(
            get_bloginfo('name'),
            self::getHostname(),
            null
        );
    }

    /**
     * Creates a WordPress user entity of type PublicKeyCredentialUserEntity.
     *
     * @param WP_User|null $user The WordPress user entity.
     *
     * @return PublicKeyCredentialUserEntity The created or retrieved WebAuthn user entity.
     */
    public static function getUserEntity(?WP_User $user): PublicKeyCredentialUserEntity
    {
        // Check if user is null and generate the required parameters accordingly
        if ($user === null) {
            $user_login        = '';
            $user_display_name = '';
            $user_id           = self::generateBinaryId();
        } else {
            $user_login        = $user->user_login;
            $user_display_name = $user->display_name;
            $user_id           = $user->ID;
        }

        return PublicKeyCredentialUserEntity::create(
            $user_login,
            $user_id,
            $user_display_name,
            null
        );
    }

    /**
     * Generate a binary ID using wp_generate_uuid4() and convert it to binary.
     *
     * @return string The binary ID.
     */
    private static function generateBinaryId(): string
    {
        $uuid = wp_generate_uuid4();
        return hex2bin(str_replace('-', '', $uuid));
    }

    /**
     * Set the plugin version.
     *
     * This function sets the version of the plugin by retrieving the plugin data
     * using the `get_plugin_data` function and defining the constant
     * `WPPASSKEYS_VERSION` with the plugin's version.
     *
     * @return void
     */
    public static function setPluginVersion(): void
    {
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data(__FILE__);
        if (! defined('WP_PASSKEYS_VERSION')) {
            define('WP_PASSKEYS_VERSION', $plugin_data['Version']);
        }
    }

    /**
     * Retrieves the hostname of the current site.
     *
     * @return string The hostname of the current site.
     */
    public static function getHostname(): string
    {
        $site_url = get_site_url();
        return parse_url($site_url, PHP_URL_HOST);
    }
}
