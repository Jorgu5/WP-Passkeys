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
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\TrustPath;
use Symfony\Component\Uid\Uuid;

/**
 * Credential Helper for WP Pass Keys.
 */
class CredentialHelper implements PublicKeyCredentialSourceRepository
{
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
     * @param PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity The user entity.
     *
     * @return array The array of credential sources.
     * @throws InvalidDataException
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userId             = $publicKeyCredentialUserEntity->getId();
        $credentials        = get_user_meta($userId);
        $credential_sources = array();

        foreach ($credentials as $credential) {
            $credential_sources[] = PublicKeyCredentialSource::createFromArray(
                unserialize(
                    $credential[0],
                    array( 'allowed_classes' => __CLASS__ )
                )
            );
        }

        return $credential_sources;
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
        $publicKeyCredentialId = $publicKeyCredentialSource->getPublicKeyCredentialId();
        $metaValue               = serialize($publicKeyCredentialSource->jsonSerialize());

        update_user_meta(get_current_user_id(), $publicKeyCredentialId, $metaValue);
    }
}
