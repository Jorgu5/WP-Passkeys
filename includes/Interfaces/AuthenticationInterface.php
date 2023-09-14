<?php

namespace WpPasskeys\Interfaces;

use Exception;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

interface AuthenticationInterface
{
    /**
     * Registers the routes for the application.
     *
     * @throws Exception if an error occurs while registering the routes.
     */
    public function registerAuthRoutes(): void;

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
    public function responseAuthenticator(
        WP_REST_Request $request
    ): WP_REST_Response|WP_Error;
}
