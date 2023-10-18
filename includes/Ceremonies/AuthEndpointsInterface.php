<?php

namespace WpPasskeys\Ceremonies;

use Exception;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Exceptions\RandomException;

interface AuthEndpointsInterface
{
    /**
     * @param WP_REST_Request $request *
     *
     * @throws Exception
     */
    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response;

    /**
     *
     * @param WP_REST_Request<array<array-key, mixed>> $request
     *
     * @return WP_REST_Response|WP_Error The REST response or error.
     */
    public function verifyPublicKeyCredentials(WP_REST_Request $request): WP_REST_Response|WP_Error;

    /**
     * @return array<array-key, mixed>
     *
     */
    public function getVerifiedResponse(): array;

    public function getAuthenticatorAssertionResponse(PublicKeyCredential $pkCredential): AuthenticatorAssertionResponse;

    public function getPkCredentialResponse(PublicKeyCredential $pkCredential);

    /**
     * @param WP_REST_Request<array<array-key, mixed>> $request
     *
     * @throws Throwable
     */
    public function validateAuthenticatorAssertionResponse(
        AuthenticatorAssertionResponse $authenticatorAssertionResponse,
        WP_REST_Request $request
    ): void;

    public function createAuthenticatorAssertionResponse(): AuthenticatorAssertionResponseValidator;

    /**
     * @throws InvalidDataException
     * @throws Throwable
     */
    public function getPkCredential(WP_REST_Request $request): PublicKeyCredential;

    /**
     * @throws CredentialException
     */
    public function loginUserWithCookie(WP_REST_Request $request): void;

    public function getRawId(WP_REST_Request $request): string;

    /**
     * @throws RandomException
     */
    public function requestOptions(): PublicKeyCredentialRequestOptions | WP_Error;

    /**
     * @throws Exception
     */
    public function getChallenge(): string;
}
