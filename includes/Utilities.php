<?php

/**
 * Utilities helper for WP Pass Keys.
 */

namespace WpPasskeys;

use Exception;
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
    public static function createRpEntity(): PublicKeyCredentialRpEntity
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
     * @param string $userLogin
     *
     * @return PublicKeyCredentialUserEntity The created or retrieved WebAuthn user entity.
     */
    public static function createUserEntity(string $userLogin): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            $userLogin,
            self::generateBinaryId(),
            $userLogin,
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
        $binaryUuId = hex2bin(str_replace('-', '', $uuid));
        return base64_encode($binaryUuId);
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

    /**
     * Generate login from display name
     *
     * @param $displayName
     *
     * @return string
     * @throws Exception
     */

    /* public static function generateLoginFromDisplayName($displayName): string
    {
        if (empty($displayName)) {
            return '';
        }

        $generatedLogin = strtolower(str_replace(' ', '-', $displayName));

        $usernameExists = username_exists($generatedLogin);

        if ($usernameExists) {
            $suffix = random_int(10, 99);
            $generatedLogin = "{$generatedLogin}{$suffix}";
        }

        SessionHandler::instance()->set('user_login', $generatedLogin);

        return $generatedLogin;
    }*/
}
