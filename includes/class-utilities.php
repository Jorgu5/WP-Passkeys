<?php
/**
 * Utilities helper for WP Pass Keys.
 */

namespace WpPasskeys;

use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_User;

class Utilities {
	/**
	 * Creates a PublicKeyCredentialRpEntity object.
	 *
	 * @return PublicKeyCredentialRpEntity The created PublicKeyCredentialRpEntity object.
	 */
	public static function get_rp_entity(): PublicKeyCredentialRpEntity {
		return PublicKeyCredentialRpEntity::create(
			get_bloginfo( 'name' ),
			get_site_url(),
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
    public static function get_user_entity(?WP_User $user): PublicKeyCredentialUserEntity
    {
        // Check if user is null and generate the required parameters accordingly
        if ($user === null) {
            $user_login = $_POST['user_login'];
            $user_display_name = $_POST['display_name'];
            $user_id = self::generate_binary_id();
        } else {
            $user_login = $user->user_login;
            $user_display_name = $user->display_name;
            $user_id = $user->ID;
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
    private static function generate_binary_id(): string
    {
        $uuid = wp_generate_uuid4();
        return hex2bin(str_replace('-', '', $uuid));
    }
}
