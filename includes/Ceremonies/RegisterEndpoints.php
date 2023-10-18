<?php

/**
 * Registration Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since 1.0.0
 * @version 1.0.0
 */

declare(strict_types=1);

namespace WpPasskeys\Ceremonies;

use Exception;
use InvalidArgumentException;
use JsonException;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpPasskeys\Credentials\CredentialEntityInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\UtilitiesInterface;

class RegisterEndpoints implements RegisterEndpointsInterface
{
    private array $verifiedResponse;

    public function __construct(
        public readonly CredentialHelperInterface $credentialHelper,
        public readonly CredentialEntityInterface $credentialEntity,
        public readonly UtilitiesInterface $utilities,
        public readonly SessionHandlerInterface $sessionHandler,
        public readonly PublicKeyCredentialParameters $publicKeyCredentialParameters,
        public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader,
        public readonly AttestationStatementSupportManager $attestationStatementSupportManager,
    ) {
    }

    /**
     * @throws Exception
     */
    public function createPublicKeyCredentialOptions(): WP_Error|WP_REST_Response
    {
            $userLogin = $this->credentialHelper->getUserLogin();
            $publicKeyCredentialCreationOptions = $this->creationOptions($userLogin);
            $this->credentialHelper->saveSessionCredentialOptions(
                $publicKeyCredentialCreationOptions
            );
            return new WP_REST_Response($publicKeyCredentialCreationOptions, 200);
    }

    public function verifyPublicKeyCredentials(
        WP_REST_Request $request
    ): WP_Error|WP_REST_Response {
        try {
            $pkCredential = $this->getPkCredential($request);
            $this->credentialHelper->storePublicKeyCredentialSource(
                $this->credentialHelper->getPublicKeyCredentials(
                    $this->getAuthenticatorAttestationResponse(
                        $pkCredential
                    ),
                    $this->attestationStatementSupportManager,
                    ExtensionOutputCheckerHandler::create(),
                )
            );

            $this->utilities->setAuthCookie(
                $this->credentialHelper->getUserLogin(),
                null
            );

            $this->verifiedResponse = [
                'code' => 'verified',
                'message' => 'Your account has been created. You are being redirect now to dashboard...',
                'data' => [
                    'redirectUrl' => $this->utilities->getRedirectUrl(),
                    'pk_credential_id' => $pkCredential->id,
                ]];

            $response = new WP_REST_Response($this->verifiedResponse, 200);
        } catch (JsonException $e) {
            $response = new WP_Error('json', $e->getMessage(), $e->getTrace());
        } catch (CredentialException $e) {
            $response = new WP_Error('credential-error', $e->getMessage(), $e->getTrace());
        } catch (Throwable $e) {
            $response = new WP_Error('server', $e->getMessage(), $e->getTrace());
        }

        return $response;
    }

    public function getCreationOptions(): PublicKeyCredentialCreationOptions
    {
        return $this->publicKeyCredentialCreationOptions;
    }

    public function getVerifiedResponse(): array
    {
        return $this->verifiedResponse;
    }

    public function getChallenge(): string
    {
        return base64_encode(random_bytes(32));
    }

    public function getPkParameters(): array
    {
        return $this->publicKeyCredentialParameters->get();
    }

    public function getPkCredential(WP_REST_Request $request): PublicKeyCredential
    {
        $data = $request->get_body();
        return $this->publicKeyCredentialLoader->load($data);
    }

    public function getAuthenticatorAttestationResponse(
        PublicKeyCredential $pkCredential
    ): AuthenticatorAttestationResponse {
        $authenticatorAttestationResponse = $this->getPkCredentialResponse($pkCredential);
        if (! $authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) {
            throw new InvalidArgumentException("Invalid attestation response");
        }
        return $authenticatorAttestationResponse;
    }

    public function getRedirectUrl(): string
    {
        return !is_user_logged_in() ? $this->utilities->getRedirectUrl() : '';
    }

    public function getTimeout(): int
    {
        return (int)get_option('wppk_passkeys_timeout', 30000);
    }

    public function getPkCredentialResponse(PublicKeyCredential $pkCredential)
    {
        return $pkCredential->response;
    }

    /**
     * @throws Exception
     */
    public function creationOptions(string $userLogin): PublicKeyCredentialCreationOptions | WP_Error
    {
        try {
            $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
                $this->credentialEntity->createRpEntity(),
                $this->credentialEntity->createUserEntity($userLogin),
                $this->getChallenge(),
                $this->getPkParameters(),
            );

            $publicKeyCredentialCreationOptions->timeout = $this->getTimeout();
            $publicKeyCredentialCreationOptions->authenticatorSelection = AuthenticatorSelectionCriteria::create(
                AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
                true
            );

            $publicKeyCredentialCreationOptions->attestation =
                PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE;

            return $publicKeyCredentialCreationOptions;
        } catch (Exception $e) {
            return new WP_Error('server', $e->getMessage(), $e->getTrace());
        }
    }
}
