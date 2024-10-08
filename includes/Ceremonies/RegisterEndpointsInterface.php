<?php

namespace WpPasskeys\Ceremonies;

use Exception;
use RuntimeException;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registration Handler for WP Pass Keys.
 */
interface RegisterEndpointsInterface
{
    /**
     * Creates the public key credential creation options.
     *
     * @return WP_Error|WP_REST_Response A REST response object with the result.
     * @throws RuntimeException If an error occurs during the process.
     */
    public function createPublicKeyCredentialOptions(): WP_Error|WP_REST_Response;

    /**
     * Handles the creation response.
     *
     * @param WP_REST_Request $request the REST request object.
     *
     * @return WP_Error|WP_REST_Response a REST response object with the result.
     */
    public function verifyPublicKeyCredentials(WP_REST_Request $request): WP_Error|WP_REST_Response;

    /**
     * @throws InvalidDataException
     * @throws Throwable
     */
    public function getPublicKeyCredential(string $data): PublicKeyCredential;

    public function getRedirectUrl(): string;

    public function creationOptions(string $userLogin): PublicKeyCredentialCreationOptions|WP_Error;

    /**
     * Retrieves the validated credentials.
     *
     * @return PublicKeyCredentialSource The validated credentials.
     * @throws Throwable
     */
    public function validateAuthenticatorAttestationResponse(PublicKeyCredential $publicKeyCredential): PublicKeyCredentialSource;
}
