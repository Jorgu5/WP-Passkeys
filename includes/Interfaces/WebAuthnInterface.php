<?php

namespace WpPasskeys\Interfaces;

use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

interface WebAuthnInterface
{
    /**
     * Create public key credential options.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response;

    /**
     * Authenticator assertion response.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response object or an error object.
     */
    public function verifyPublicKeyCredentials(
        WP_REST_Request $request
    ): WP_REST_Response|WP_Error;

    /**
     * array @return array<array-key, mixed>
     */
    public function getVerifiedResponse(): array;

}
