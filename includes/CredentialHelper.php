<?php

/**
 * Credential Helper for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since   1.0.0
 * @version 1.0.0
 */

namespace WpPasskeys;

use Exception;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\TrustPath;
use Symfony\Component\Uid\Uuid;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Traits\SingletonTrait;

/**
 * Credential Helper for WP Pass Keys.
 */
class CredentialHelper
{
    use SingletonTrait;

    public readonly PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;

    /**
     * Finds a PublicKeyCredentialSource by the given credential ID.
     *
     * @param string $publicKeyCredentialId The credential ID to search for.
     *
     * @return PublicKeyCredentialSource|null The found PublicKeyCredentialSource, or null if not found.
     * @throws InvalidDataException
     */
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $userCredentialId = get_user_meta(get_current_user_id(), $publicKeyCredentialId, true);

        if ($userCredentialId) {
            return PublicKeyCredentialSource::createFromArray(
                unserialize(
                    $userCredentialId,
                    array( 'allowed_classes' => __CLASS__ )
                )
            );
        }

        return null;
    }


    /**
     * Finds all credential sources for a user entity.
     *
     * @return array The array of credential sources.
     * @throws InvalidDataException
     */
    public function findAllForUserEntity(): array
    {
        $userId             = SessionHandler::instance()->get('user_id');
        $userCredentialsSource        = get_user_meta($userId, 'pk_credential_source', false);
        $credentialSources = array();

        $credentialSources[] = PublicKeyCredentialSource::createFromArray(
            $userCredentialsSource[0]
        );

        return $credentialSources;
    }


    /**
     * Saves a credential source to the database.
     *
     * @param  PublicKeyCredentialSource $publicKeyCredentialSource The credential source to save.
     * @throws Exception If there is an error saving the credential source.
     * @return void
     */
    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $credentials      = $publicKeyCredentialSource->jsonSerialize();

        $addUser = wp_insert_user(
            array(
                'user_login' => SessionHandler::instance()->get('user_login'),
                'meta_input' => [
                    'pk_credential_source' => $credentials
                ]
            )
        );

        if (is_wp_error($addUser)) {
            throw new CredentialException($addUser->get_error_message());
        }
    }

    public function saveSessionCredentialOptions(
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions
    ): void {
        SessionHandler::instance()->start();
        SessionHandler::instance()->set(
            'webauthn_credential_options',
            $publicKeyCredentialCreationOptions->jsonSerialize()
        );
    }

    /**
     * Retrieves the session credential data.
     *
     * @return PublicKeyCredentialCreationOptions|null The credential data, or null if not found.
     * @throws InvalidDataException
     */

    public function getSessionCredentialOptions(): ?PublicKeyCredentialCreationOptions
    {
        $session = SessionHandler::instance();
        $session->start();
        if ($session->has('webauthn_credential_options')) {
            $options = $session->get('webauthn_credential_options');
            return PublicKeyCredentialCreationOptions::createFromArray($options);
        }
        return null;
    }
}
