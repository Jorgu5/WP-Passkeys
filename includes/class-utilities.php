<?php
/**
 * Utilities helper for WP Pass Keys.
 */

namespace WpPasskeys;

use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class Utilities {
	/**
	 * Creates a PublicKeyCredentialRpEntity object.
	 *
	 * @return PublicKeyCredentialRpEntity The created PublicKeyCredentialRpEntity object.
	 */
	public static function create_rp_entity(): PublicKeyCredentialRpEntity {
		return PublicKeyCredentialRpEntity::create( 'foo', 'bar', null );
	}

	/**
	 * Creates a user entity of type PublicKeyCredentialUserEntity.
	 *
	 * @return PublicKeyCredentialUserEntity The created user entity.
	 */
	public static function create_user_entity(): PublicKeyCredentialUserEntity {
		return PublicKeyCredentialUserEntity::create(
			'foo',
			'bar',
			'foo',
			null
		);
	}
}
