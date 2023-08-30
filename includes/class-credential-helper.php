<?php
/**
 * Credential Helper for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since 1.0.0
 * @version 1.0.0
 */

namespace WpPasskeys;

use JsonException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\TrustPath;
use Symfony\Component\Uid\Uuid;


/**
 * Credential Helper for WP Pass Keys.
 */
class Credential_Helper implements PublicKeyCredentialSourceRepository {

	/**
	 * A method to load a PublicKeyCredentialSource object given a credential ID.
	 *
	 * @param string $public_key_credential_id The public key credential ID.
	 * @return PublicKeyCredentialSource|null The PublicKeyCredentialSource object or null if not found.
	 * @throws InvalidDataException When data is invalid.
	 */
	public function findOneByCredentialId( string $public_key_credential_id ): ?PublicKeyCredentialSource {
		global $wpdb;

		$table_name = $wpdb->prefix . 'public_key_credential_sources';
		$cache_key  = 'public_key_credential_' . $public_key_credential_id;

		$cached_row = wp_cache_get( $cache_key );

		if ( $cached_row ) {
			return PublicKeyCredentialSource::createFromArray( (array) $cached_row );
		}
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table_name} WHERE public_key_credential_id = %s",
				$public_key_credential_id
			)
		);

		if ( $row ) {
			wp_cache_set( $cache_key, (array) $row );

			return PublicKeyCredentialSource::createFromArray( (array) $row );
		}

		return null;
	}

	/**
	 * Retrieves all the publicKeyCredentialSources associated with a given publicKeyCredentialUserEntity.
	 *
	 * @param PublicKeyCredentialUserEntity $public_key_credential_user_entity The user entity.
	 * @return array An array of publicKeyCredentialSources associated with the given publicKeyCredentialUserEntity.
	 * @throws InvalidDataException When data is invalid.
	 */
	public function findAllForUserEntity( PublicKeyCredentialUserEntity $public_key_credential_user_entity ): array {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'public_key_credential_sources';
		$user_handle = $public_key_credential_user_entity->getId();

		$cache_key = 'public_key_credential_user_' . $user_handle;

		$cached_results = wp_cache_get( $cache_key );

		if ( $cached_results ) {
			return $cached_results;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE userHandle = %s", $user_handle )
		);

		$credential_sources = array();
		foreach ( $results as $row ) {
			$credential_sources[] = PublicKeyCredentialSource::createFromArray( (array) $row );
		}

		wp_cache_set( $cache_key, $credential_sources );

		return $credential_sources;
	}

	/**
	 * A method to store a PublicKeyCredentialSource object.
	 *
	 * @param PublicKeyCredentialSource $public_key_credential_source The public key credential source.
	 * @return void Nothing.
	 */
	public function saveCredentialSource( PublicKeyCredentialSource $public_key_credential_source ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'public_key_credential_sources';

		$data = array(
			'publicKeyCredentialId' => $public_key_credential_source->getPublicKeyCredentialId(),
			'type'                  => $public_key_credential_source->getType(),
			'transports'            => wp_json_encode( $public_key_credential_source->getTransports(), JSON_THROW_ON_ERROR ),
			'attestationType'       => $public_key_credential_source->getAttestationType(),
			'trustPath'             => wp_json_encode(
				$public_key_credential_source->getTrustPath()->jsonSerialize(),
				JSON_THROW_ON_ERROR
			),
			'aaguid'                => $public_key_credential_source->getAaguid()->__toString(),
			'credentialPublicKey'   => $public_key_credential_source->getCredentialPublicKey(),
			'userHandle'            => $public_key_credential_source->getUserHandle(),
			'counter'               => $public_key_credential_source->getCounter(),
			'otherUI'               => wp_json_encode( $public_key_credential_source->getOtherUI(), JSON_THROW_ON_ERROR ),
		);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table_name, $data );
	}
}
