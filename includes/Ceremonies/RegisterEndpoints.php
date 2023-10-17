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
use JsonException;
use RuntimeException;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Exception\InvalidDataException;
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
    private AttestationStatementSupportManager $attestationStatementSupportManager;
    private PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;

    public function __construct(
        public readonly CredentialHelperInterface $credentialHelper,
        public readonly CredentialEntityInterface $credentialEntity,
        public readonly UtilitiesInterface $utilities,
        public readonly SessionHandlerInterface $sessionHandler,
        public readonly PublicKeyCredentialParameters $publicKeyCredentialParameters,
        public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader
    ) {
    }

    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $userLogin = $this->credentialHelper->getUserLogin();
            $this->publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
                $this->credentialEntity->createRpEntity(),
                $this->credentialEntity->createUserEntity($userLogin),
                $this->getChallenge(),
                $this->getPkParameters(),
            );

            $this->publicKeyCredentialCreationOptions->timeout = (int)get_option('wppk_passkeys_timeout', '30000');
            $this->publicKeyCredentialCreationOptions->authenticatorSelection = AuthenticatorSelectionCriteria::create(
                AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
                true
            );

            $this->publicKeyCredentialCreationOptions->attestation =
                PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE;

            $this->credentialHelper->saveSessionCredentialOptions(
                $this->publicKeyCredentialCreationOptions
            );

            return new WP_REST_Response($this->publicKeyCredentialCreationOptions, 200);
        } catch (Exception $e) {
            throw new ($e->getMessage());
        }
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
                    'redirectUrl' => $this->getRedirectUrl(),
                    'pk_credential_id' => $pkCredential->id,
                ]];

            $response = new WP_REST_Response($this->verifiedResponse, 200);
        } catch (JsonException $e) {
            $response = new WP_Error('json', $e->getMessage());
        } catch (CredentialException $e) {
            $response = new WP_Error('credential-error', $e->getMessage());
        } catch (Throwable $e) {
            $response = new WP_Error('server', $e->getMessage());
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
        $authenticatorAttestationResponse = $pkCredential->response;
        if (! $authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) {
            wp_redirect(wp_login_url());
        }
        return $authenticatorAttestationResponse;
    }

    public function getAttestationStatementSupportManager(): AttestationStatementSupportManager
    {
        $this->attestationStatementSupportManager = AttestationStatementSupportManager::create();
        $this->attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());

        return $this->attestationStatementSupportManager;
    }

    public function getRedirectUrl(): string
    {
        return !is_user_logged_in() ? $this->utilities->getRedirectUrl() : '';
    }
}
