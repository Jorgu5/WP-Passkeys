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
use WP_User;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Traits\SingletonTrait;

/**
 * Credential Helper for WP Pass Keys.
 */
class CredentialHelper implements PublicKeyCredentialSourceRepository
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
     * @throws \JsonException
     */
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        global $wpdb;

        $credentialSource = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT credential_source FROM wp_pk_credential_sources WHERE pk_credential_id = %s",
                $publicKeyCredentialId
            )
        );

        if (empty($credentialSource)) {
            return null;
        }

        return PublicKeyCredentialSource::createFromArray(
            json_decode($credentialSource, true, 512, JSON_THROW_ON_ERROR)
        );
    }


    /**
     * Finds all credential sources for a given WordPress username.
     *
     * @param PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity
     *
     * @return array The array of PublicKeyCredentialDescriptor objects.
     * @throws InvalidDataException
     * @throws \JsonException
     * @throws CredentialException
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $credentialDescriptorsStore = [];

        $username = $publicKeyCredentialUserEntity->name;

        $credentialSource = $this->getUserPublicKeySources($username);

        $publicKeySource = PublicKeyCredentialSource::createFromArray($credentialSource);

        $credentialDescriptorsStore[] = $publicKeySource->getPublicKeyCredentialDescriptor();

        return $credentialDescriptorsStore;
    }


    /**
     * Saves a credential source to the database.
     *
     * @param PublicKeyCredentialSource $publicKeyCredentialSource The credential source to save.
     *
     * @return void
     * @throws Exception If there is an error saving the credential source.
     */
    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        global $wpdb;

        $safeEncodedPkId = Utilities::safeEncode($publicKeyCredentialSource->publicKeyCredentialId);

        if ($this->findOneByCredentialId($safeEncodedPkId)) {
            return;
        }
        // Insert only the credential source into the custom table wp_pk_credential_sources
        $wpdb->insert('wp_pk_credential_sources', [
            'pk_credential_id' => $safeEncodedPkId,
            'credential_source' => json_encode($publicKeyCredentialSource, JSON_THROW_ON_ERROR)
        ], ['%s']);

        // Check if the insert was successful, throw exception otherwise
        if (!$wpdb->insert_id) {
            throw new CredentialException('Failed to save credential source.');
        }
    }

    /**
     * @throws CredentialException
     */
    public function createUserWithPkCredentialId(string $publicKeyCredentialId): void
    {

        $username = SessionHandler::instance()->get('user_login');
        $userData = [
            'user_login' => $username,
            'meta_input' => [
                'pk_credential_id' => Utilities::safeEncode($publicKeyCredentialId)
            ]
        ];

        if ($this->getExistingUserId($username)) {
            $userData['ID'] = get_user_by('login', $username)->ID;
        }

        $addUser = wp_insert_user(
            $userData
        );

        if (is_wp_error($addUser) && $addUser->get_error_code() !== 'existing_user_login') {
            throw new CredentialException($addUser->get_error_message());
        }
    }

    private function getExistingUserId(string $username): ?int
    {
        $user = get_user_by('login', $username);
        return $user->ID ?? null;
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

    /**
     * Retrieves public key credential sources for a specific user.
     *
     * @param string $username
     *
     * @return array An array of credential sources.
     * @throws \JsonException
     * @throws CredentialException
     * @throws InvalidDataException
     */
    private function getUserPublicKeySources(string $username): array
    {
        $user = get_user_by('login', $username);

        if (!$user) {
            throw new CredentialException('User not found.');
        }

        $pkCredentialId = get_user_meta($user->ID, 'pk_credential_id', true);

        if (!$pkCredentialId) {
            throw new CredentialException('No credential ID found for user.');
        }

        $credentialSource = $this->findOneByCredentialId($pkCredentialId);

        return json_decode($credentialSource, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws CredentialException
     */
    public function getUserByCredentialId(string $pkCredentialId): int
    {
        global $wpdb;

        $user = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM wp_usermeta WHERE meta_key = 'pk_credential_id' AND meta_value = %s",
                $pkCredentialId
            )
        );

        if (!$user) {
            throw new CredentialException('User not found.');
        }

        return $user;
    }
}
