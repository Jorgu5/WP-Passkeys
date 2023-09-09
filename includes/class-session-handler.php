<?php

namespace WpPasskeys;

use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use WpPasskeys\traits\Singleton;

class Session_Handler {
	use Singleton;

	public readonly PublicKeyCredentialCreationOptions $public_key_credential_creation_options;

	/**
	 * Sets the session credential data.
	 *
	 * @param PublicKeyCredentialCreationOptions $public_key_credential_creation_options
	 * @return void
	 */
	public function save_session_credential_options(
		PublicKeyCredentialCreationOptions $public_key_credential_creation_options
	): void {
		global $wpdb;
		$wpdb->show_errors();
		$table_name = $wpdb->prefix . 'webauthn_credential_options';

		$wpdb->insert(
			$table_name,
			array(
				'user_cookie'        => USER_COOKIE,
				'timestamp'          => time(),
				'credential_options' => $public_key_credential_creation_options->jsonSerialize(),
			)
		);
	}

	/**
	 * Retrieves the session credential data.
	 *
	 * @return PublicKeyCredentialCreationOptions|null The credential data, or null if not found.
	 * @throws InvalidDataException
	 * @throws \JsonException
	 */

	public function get_session_credential_options(): ?PublicKeyCredentialCreationOptions {
		global $wpdb;
		$table_name = $wpdb->prefix . 'webauthn_credential_options';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_cookie = %s ORDER BY timestamp DESC LIMIT 1",
				USER_COOKIE
			)
		);

		if ( $result ) {
			return PublicKeyCredentialCreationOptions::createFromString(
				$result->credential_options
			);
		}

		return null;
	}

	/**
	 * Lazy cleanup of expired records in wp_options.
	 * TODO: Add it to a cron job.
	 *
	 * @return void
	 */
	public function cleanup_expired_records(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'webauthn_credential_options';

		$current_time = time();

		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE %d - timestamp > 300", $current_time ) );
	}
}
