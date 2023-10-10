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
use WpPasskeys\Exceptions\CredentialException;

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
     * @return PublicKeyCredentialSource|null The found PublicKeyCredentialSource, or null if not found.
     * @throws InvalidDataException
     * @throws \JsonException
     */
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource;

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
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array;

    /**
     * Saves a credential source to the database.
     *
     * @param PublicKeyCredentialSource $publicKeyCredentialSource The credential source to save.
     *
     * @return void
     * @throws Exception If there is an error saving the credential source.
     */
    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void;

    /**
     * @throws CredentialException
     */
    public function createUserWithPkCredentialId(string $publicKeyCredentialId): void;

    public function saveSessionCredentialOptions(PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions): void;

    /**
     * Retrieves the session credential data.
     *
     * @return PublicKeyCredentialCreationOptions|null The credential data, or null if not found.
     * @throws InvalidDataException
     */
    public function getSessionCredentialOptions(): ?PublicKeyCredentialCreationOptions;

    /**
     * @throws CredentialException
     */
    public static function getUserByCredentialId(string $pkCredentialId): int;

    /**
     * Retrieves the validated credentials.
     *
     * @return PublicKeyCredentialSource The validated credentials.
     * @throws Throwable
     */
    public function getPublicKeyCredentials(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        AttestationStatementSupportManager $supportManager,
        ExtensionOutputCheckerHandler $checkerHandler
    ): PublicKeyCredentialSource;

    /**
     * @throws CredentialException
     * @throws Exception
     */
    public function storePublicKeyCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void;
}
