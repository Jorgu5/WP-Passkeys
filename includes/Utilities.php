<?php

/**
 * Utilities helper for WP Pass Keys.
 */

namespace WpPasskeys;

use Exception;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_User;

class Utilities implements UtilitiesInterface
{

    public function setAuthCookie(string $username = null, int $userId = null): void
    {
        $user = null;

        if ($userId) {
            $user = get_user_by('id', $userId);
            if ($user) {
                $this->setUserAndCookie($user);
            }
        }

        if ($username) {
            $user = get_user_by('login', $username);
            if ($user) {
                $this->setUserAndCookie($user);
            }
        }

        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }
    }

    public function getRedirectUrl(): string
    {
        $redirectUrl = get_option('wppk_passkeys_redirect');
        if (empty($redirectUrl)) {
            $redirectUrl = get_admin_url();
        }
        return $redirectUrl;
    }

    public function setUserAndCookie(WP_User|null $user): void
    {
        if (!$user) {
            return;
        }
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true);
    }

    public static function safeEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function getHostname(): string
    {
        $site_url = get_site_url();
        return parse_url($site_url, PHP_URL_HOST);
    }
}
