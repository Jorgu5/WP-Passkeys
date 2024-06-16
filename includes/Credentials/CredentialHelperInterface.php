<?php

namespace WpPasskeys\Credentials;

use Exception;
use JsonException;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_Error;
use WpPasskeys\Exceptions\InvalidCredentialsException;

/**
 * Credential Helper for WP Pass Keys.
 */
interface CredentialHelperInterface
{
    /**
     * Finds a PublicKeyCredentialSource by the given credential ID.
     *
     * @param string $publicKeyCredentialId The credential ID to search for.
     *
     * @return PublicKeyCredentialSource|WP_Error|null The found PublicKeyCredentialSource, or null if not found.
     * @throws InvalidDataException
     * @throws JsonException
     */
    public function findOneByCredentialId(string $publicKeyCredentialId): PublicKeyCredentialSource|WP_Error|null;

    /**
     * Finds all credential sources for a given WordPress username.
     *
     * @param string $userLogin
     *
     * @return array The array of PublicKeyCredentialDescriptor objects.
     * @throws InvalidDataException
     * @throws JsonException
     * @throws InvalidCredentialsException
     */
    public function findAllForUserEntity(string $userLogin): array;

    /**
     * Saves a credential source to the database.
     *
     * @param PublicKeyCredentialSource $publicKeyCredentialSource The credential source to save.
     * @param string $pkCredentialId
     * @param string|null $email
     *
     * @return void
     * @throws Exception If there is an error saving the credential source.
     */
    public function saveCredentialSource(
        PublicKeyCredentialSource $publicKeyCredentialSource,
        string $pkCredentialId,
        ?string $email
    ): void;

    public function saveSessionCredentialOptions(
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions
    ): void;

    /**
     * Retrieves the session credential data.
     *
     * @return PublicKeyCredentialCreationOptions|null The credential data, or null if not found.
     * @throws InvalidDataException
     */
    public function getSessionCredentialOptions(): ?PublicKeyCredentialCreationOptions;

    /**
     * @throws InvalidCredentialsException
     */
    public function getUserByCredentialId(string $pkCredentialId): int|WP_Error;

    /**
     * @param array $userData
     * @param string $publicKeyCredentialId
     *
     * @return int|WP_Error
     */
    public function addAccountWithPkCredentialId(array $userData, string $publicKeyCredentialId,): int|WP_Error;

    /**
     * @param int $userId
     * @param string $publicKeyCredentialId
     *
     * @return int|WP_Error
     */
    public function updateExistingUserWithPkCredentialId(int $userId, string $publicKeyCredentialId): int|WP_Error;

    public function findCredentialIdByEmail(string $email): string;
}
