<?php

namespace WpPasskeys;

use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use WpPasskeys\traits\SingletonTrait;

class SessionHandler
{
    use SingletonTrait;

    public readonly PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;
    public function saveSessionCredentialOptions(
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions
    ): void {
        session_start();
        $_SESSION['webauthn_credential_options'] = $publicKeyCredentialCreationOptions->jsonSerialize();
    }

    /**
     * Retrieves the session credential data.
     *
     * @return PublicKeyCredentialCreationOptions|null The credential data, or null if not found.
     * @throws InvalidDataException
     */

    public function getSessionCredentialOptions(): ?PublicKeyCredentialCreationOptions
    {
        session_start();
        if (isset($_SESSION['webauthn_credential_options'])) {
            $options = $_SESSION['webauthn_credential_options'];
            return PublicKeyCredentialCreationOptions::createFromArray($options);
        }
        return null;
    }

    /**
     * Lazy cleanup of expired records in wp_options.
     *
     * @return void
     */
    public function cleanupExpiredRecords(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'webauthn_credential_options';

        $current_time = time();

        $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE %d - timestamp > 300", $current_time));
    }
}
