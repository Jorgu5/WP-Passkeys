<?php

/**
 * Credential Helper for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since   1.0.0
 * @version 1.0.0
 */

namespace WpPasskeys\Credentials;

use JsonException;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_Error;
use WpPasskeys\Exceptions\InvalidCredentialsException;
use WpPasskeys\Exceptions\InsertUserException;
use WpPasskeys\Exceptions\InvalidUserDataException;
use WpPasskeys\Utilities;

class CredentialHelper implements CredentialHelperInterface, PublicKeyCredentialSourceRepository
{
    public readonly PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;

    public function __construct(
        private readonly SessionHandlerInterface $sessionHandler,
    ) {
        add_action('delete_user', [$this, 'removeUserCredentials']);
    }

    /**
     * @throws InvalidCredentialsException
     * @throws InvalidUserDataException
     * @throws InvalidDataException
     * @throws JsonException
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $username                     = $publicKeyCredentialUserEntity->name;
        $credentialSource             = $this->getUserPublicKeySources($username);
        $credentialDescriptorsStore[] = $credentialSource?->getPublicKeyCredentialDescriptor();

        return $credentialDescriptorsStore;
    }

    /**
     * Retrieves public key credential sources for a specific user.
     *
     * @param string $username
     *
     * @return PublicKeyCredentialSource|null An array of credential sources.
     * @throws InvalidCredentialsException
     * @throws InvalidDataException
     * @throws InvalidUserDataException
     * @throws JsonException
     */
    public function getUserPublicKeySources(string $username): ?PublicKeyCredentialSource
    {
        $user = get_user_by('login', $username);

        if (! $user) {
            throw new InvalidUserDataException('User not found.');
        }

        $pkCredentialId = get_user_meta($user->ID, 'pk_credential_id', true);

        if (! $pkCredentialId) {
            throw new InvalidCredentialsException('No credentials assigned to this user.');
        }

        return $this->findOneByCredentialId($pkCredentialId);
    }

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
        $data = json_decode($credentialSource, true, 512, JSON_THROW_ON_ERROR);

        return PublicKeyCredentialSource::createFromArray($data);
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        global $wpdb;

        $safeEncodedPkId = Utilities::safeEncode($publicKeyCredentialSource->publicKeyCredentialId);

        if ($this->findOneByCredentialId($safeEncodedPkId)) {
            return;
        }
        // Insert only the credential source into the custom table wp_pk_credential_sources
        $wpdb->insert('wp_pk_credential_sources', [
            'pk_credential_id'  => $safeEncodedPkId,
            'credential_source' => json_encode($publicKeyCredentialSource, JSON_THROW_ON_ERROR),
        ], ['%s']);

        // Check if the insert was successful, throw exception otherwise
        if (! $wpdb->insert_id) {
            throw new InvalidCredentialsException('Failed to save credential source.');
        }
    }

    /**
     * Removes user credentials from the database when a user is deleted.
     *
     * @param int $userId The ID of the user being deleted.
     *
     * @return bool|WP_Error True on success, false on failure.
     */
    public function removeUserCredentials(int $userId): bool|WP_Error
    {
        global $wpdb;

        $pkCredentialId = get_user_meta($userId, 'pk_credential_id', true);

        if (! $pkCredentialId) {
            return new WP_Error(
                404,
                'No credentials found for this user.',
                ['status' => 'no_credentials']
            );
        }

        if ($wpdb->delete('wp_pk_credential_sources', ['pk_credential_id' => $pkCredentialId], ['%s']) === false) {
            return new WP_Error(
                500,
                'Failed to delete credentials for this user.',
                ['status' => 'delete_failed']
            );
        }

        return delete_user_meta($userId, 'pk_credential_id');
    }

    /**
     * @throws JsonException
     */
    public function saveSessionCredentialOptions(
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions
    ): void {
        $this->sessionHandler->start();
        $this->sessionHandler->set(
            'webauthn_credential_options',
            json_encode($publicKeyCredentialCreationOptions, JSON_THROW_ON_ERROR)
        );
    }

    public function getUserByCredentialId(string $pkCredentialId): int|WP_Error
    {
        global $wpdb;

        $user = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM wp_usermeta WHERE meta_key = 'pk_credential_id' AND meta_value = %s",
                $pkCredentialId
            )
        );

        if (! $user) {
            throw new InvalidCredentialsException(
                'User with this credential ID does not exist in the database.',
                204
            );
        }
        if (! is_numeric($user)) {
            throw new InvalidCredentialsException('Unexpected data type for user');
        }

        return (int)$user;
    }

    public function getPublicKeyCredentials(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        AttestationStatementSupportManager $supportManager,
        ExtensionOutputCheckerHandler $checkerHandler
    ): PublicKeyCredentialSource {
        $authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
            $supportManager,
            null,
            null,
            $checkerHandler,
            null
        );

        $publicKeyCredentialCreationOptions = $this->getSessionCredentialOptions();

        if ($publicKeyCredentialCreationOptions === null) {
            throw new InvalidCredentialsException('Credential options not found in session.');
        }

        return $authenticatorAttestationResponseValidator->check(
            $authenticatorAttestationResponse,
            $publicKeyCredentialCreationOptions,
            Utilities::getHostname(),
            ['localhost']
        );
    }

    /**
     * @throws JsonException
     */
    public function getSessionCredentialOptions(): ?PublicKeyCredentialCreationOptions
    {
        if ($this->sessionHandler->has('webauthn_credential_options')) {
            return PublicKeyCredentialCreationOptions::createFromString(
                $this->sessionHandler->get('webauthn_credential_options')
            );
        }

        return null;
    }

    /**
     * @throws InvalidUserDataException
     */
    public function updateOrCreateUser(string $publicKeyCredentialId): int|WP_Error
    {
        if (is_user_logged_in()) {
            return $this->updateExistingUserWithPkCredentialId(
                get_current_user_id(),
                $publicKeyCredentialId
            );
        }

        $userId = $this->addAccountWithPkCredentialId(
            $this->getUserData(),
            $publicKeyCredentialId
        );

        if (is_wp_error($userId) && $userId->get_error_code() === 'existing_user_login') {
            return new WP_Error(
                401,
                'User already exists. If you want to update your passkeys, log in first and go to user settings.',
                ['status' => 'user_not_authorized']
            );
        }

        return $userId;
    }

    public function updateExistingUserWithPkCredentialId(int $userId, string $publicKeyCredentialId): WP_Error|int
    {
        $userData                     = [
            'user_login' => get_user_by('id', $userId)->user_login,
            'ID'         => $userId,
        ];
        $encodedPublicKeyCredentialId = Utilities::safeEncode($publicKeyCredentialId);
        $userData['meta_input']       = ['pk_credential_id' => $encodedPublicKeyCredentialId];

        return wp_insert_user($userData);
    }

    public function addAccountWithPkCredentialId(array $userData, string $publicKeyCredentialId): int|WP_Error
    {
        $encodedPublicKeyCredentialId = Utilities::safeEncode($publicKeyCredentialId);
        $userData['meta_input']       = ['pk_credential_id' => $encodedPublicKeyCredentialId];

        return wp_insert_user($userData);
    }

    /**
     * @throws InvalidUserDataException
     */
    private function getUserData(): array
    {
        if ($this->sessionHandler->has('user_data')) {
            $userData = $this->sessionHandler->get('user_data');
        }

        if (! is_array($userData) && ! isset($userData['user_login'])) {
            throw new InvalidUserDataException('User data not found');
        }

        return $userData;
    }

    public function getExistingUserId($username): WP_Error|int
    {
        if (! wp_verify_nonce($_POST['wp_passkeys_nonce'], 'wp_passkeys_nonce')) {
            return new WP_Error(
                403,
                'Adding credentials failed, nonce is not correct, please refresh the page and try again.',
                ['status' => 'nonce_not_correct']
            );
        }

        return get_user_by('login', $username)->ID;
    }

    /**
     * @throws InvalidUserDataException
     */
    public function getSessionUserLogin(): string
    {
        try {
            $userData = $this->getUserData();
        } catch (InvalidUserDataException $e) {
            throw new InvalidUserDataException('User login cannot be created due to invalid user data.');
        }

        return $userData['user_login'];
    }
}
