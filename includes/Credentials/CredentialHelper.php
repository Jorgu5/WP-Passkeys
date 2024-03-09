<?php

/**
 * Credential Helper for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since   1.0.0
 * @version 1.0.0
 */

namespace WpPasskeys\Credentials;

use DateTime;
use InvalidArgumentException;
use JsonException;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_Error;
use wpdb;
use WpPasskeys\Exceptions\InvalidCredentialsException;
use WpPasskeys\Exceptions\InvalidUserDataException;
use WpPasskeys\Utilities;

class CredentialHelper implements CredentialHelperInterface
{
    public readonly PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;
    private wpdb $wpdb;

    public function __construct(
        private readonly SessionHandlerInterface $sessionHandler,
        private readonly Utilities $utilities
    ) {
        global $wpdb;
        $this->wpdb = $wpdb;
        add_action('delete_user', [$this, 'removeUserCredentials']);
    }

    /**
     * @param string $username
     *
     * @return array
     * @throws InvalidDataException
     * @throws InvalidUserDataException
     * @throws JsonException
     */
    public function findAllForUserEntity(string $username): array
    {
        $credentialSources = $this->getUserPublicKeySources($username);

        $credentialDescriptorsStore = [];
        foreach ($credentialSources as $credentialSource) {
            $descriptor = $credentialSource->getPublicKeyCredentialDescriptor();
            if ($descriptor !== null) {
                $credentialDescriptorsStore[] = $descriptor;
            }
        }

        return $credentialDescriptorsStore;
    }


    /**
     * Retrieves public key credential sources for a specific user.
     *
     * @param string $username
     *
     * @return array An array of PublicKeyCredentialSource objects.
     * @throws InvalidDataException
     * @throws InvalidUserDataException
     * @throws JsonException
     */
    public function getUserPublicKeySources(string $username): array
    {
        $user = get_user_by('login', $username);

        if (! $user) {
            throw new InvalidUserDataException('User not found.');
        }

        $pkCredentialIds = get_user_meta($user->ID, 'pk_credential_id', true);

        if (empty($pkCredentialIds)) {
            return [];
        }

        $publicKeyCredentialSources = [];
        foreach ($pkCredentialIds as $pkCredentialId) {
            $credentialSource = $this->findOneByCredentialId($pkCredentialId);
            if ($credentialSource !== null) {
                $publicKeyCredentialSources[] = $credentialSource;
            }
        }

        return $publicKeyCredentialSources;
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credentialSource = $this->wpdb->get_var(
            $this->wpdb->prepare(
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
        $safeEncodedPkId = $this->utilities->safeEncode($publicKeyCredentialSource->publicKeyCredentialId);

        if ($this->findOneByCredentialId($safeEncodedPkId)) {
            return;
        }

        $createdAt = date('Y-m-d H:i:s');
        $createdOs = $this->utilities->getDeviceOS();

        // Insert only the credential source into the custom table wp_pk_credential_sources
        $this->wpdb->insert('wp_pk_credential_sources', [
            'pk_credential_id' => $safeEncodedPkId,
            'credential_source' => json_encode($publicKeyCredentialSource, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
            'created_os' => $createdOs,
            'last_used_at' => $createdAt,
            'last_used_os' => $createdOs,
        ], ['%s']);

        // Check if the insert was successful, throw exception otherwise
        if (! $this->wpdb->insert_id) {
            throw new InvalidCredentialsException('Failed to save credential source.');
        }
    }

    /**
     * @throws InvalidDataException
     * @throws InvalidCredentialsException
     * @throws JsonException
     */
    public function updateCredentialSourceData(string $credentialId): void
    {
        $safeEncodedPkId = $this->utilities->safeEncode($credentialId);

        if ($this->findOneByCredentialId($safeEncodedPkId)) {
            return;
        }

        $updateResult = $this->wpdb->update(
            'wp_pk_credential_sources',
            [
                'last_used_at' => time(),
                'last_used_os' => $this->utilities->getDeviceOS(),
            ],
            ['pk_credential_id' => $safeEncodedPkId]
        );

        if ($updateResult === false) {
            throw new InvalidCredentialsException('Failed to update credential usage.');
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
        $pkCredentialIds = get_user_meta($userId, 'pk_credential_id', true);

        if (empty($pkCredentialIds)) {
            return new WP_Error(
                404,
                'No credentials found for this user.',
                ['status' => 'no_credentials']
            );
        }

        // Ensure $pkCredentialIds is always treated as an array
        $pkCredentialIds = is_array($pkCredentialIds) ? $pkCredentialIds : [$pkCredentialIds];
        $errors          = [];

        foreach ($pkCredentialIds as $credentialId) {
            // Attempt to delete each credential ID from the custom table
            if (
                $this->wpdb->delete('wp_pk_credential_sources', ['pk_credential_id' => $credentialId], ['%s']) === false
            ) {
                $errors[] = $credentialId; // Track credential IDs that failed to be deleted
            }
        }

        // Regardless of custom table deletion success, remove the meta key to clean up
        if (! delete_user_meta($userId, 'pk_credential_id')) {
            return new WP_Error(
                500,
                'Failed to delete user meta for credentials.',
                ['status' => 'meta_delete_failed']
            );
        }

        // If there were errors deleting any credentials from the custom table, report back
        if (! empty($errors)) {
            return new WP_Error(
                500,
                'Failed to delete some or all credentials from the custom table for this user.',
                ['status' => 'delete_failed', 'failed_ids' => $errors]
            );
        }

        return true;
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
        $users = get_users([
            'meta_key' => 'pk_credential_id',
            'fields'   => 'ids',
        ]);

        foreach ($users as $userId) {
            $credentialIds = get_user_meta($userId, 'pk_credential_id', true);
            if (in_array($pkCredentialId, (array)$credentialIds, true)) {
                return (int)$userId;
            }
        }

        return new WP_Error(
            204,
            'No user found with this credential ID.',
            ['status' => 'no_user_found']
        );
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
            $this->utilities->getHostname(),
            $this->utilities->isLocalhost() ? ["localhost"] : []
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
        $existingIds = get_user_meta($userId, 'pk_credential_id', true);
        if (! is_array($existingIds)) {
            $existingIds = [];
        }

        $existingIds[] = $publicKeyCredentialId;

        $result = update_user_meta($userId, 'pk_credential_id', $existingIds);

        if (false === $result) {
            return new WP_Error(
                'update_error',
                __('Failed to update user with new pk_credential_id.', 'wp-passkeys')
            );
        }

        return $userId;
    }

    public function addAccountWithPkCredentialId(array $userData, string $publicKeyCredentialId): int|WP_Error
    {
        $userData['meta_input'] = ['pk_credential_id' => $publicKeyCredentialId];

        return wp_insert_user($userData);
    }

    /**
     * @throws InvalidUserDataException
     */
    private function getUserData(): array
    {
        $userData = '';
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

    public function getDataByCredentialId(string $credentialId, string $columnName): ?string
    {
        $allowedColumns = [
            'created_at',
            'created_os',
            'last_used_at',
            'last_used_os',
        ];
        if (! in_array($columnName, $allowedColumns, true)) {
            throw new InvalidArgumentException("Invalid column name: {$columnName}");
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT {$columnName} FROM wp_pk_credential_sources WHERE pk_credential_id = %s",
                $credentialId
            )
        );

        if ($row !== null && property_exists($row, $columnName) && $row->$columnName !== null && $columnName) {
            if (in_array($columnName, ['created_at', 'last_used_at'], true)) {
                $date = DateTime::createFromFormat('Y-m-d H:i:s', $row->$columnName);
                if ($date) {
                    return $date->format('F jS, Y, \a\t H:i:s');
                }
            }

            return $row->$columnName;
        }

        return null;
    }
}
