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
use WpOrg\Requests\Exception\InvalidArgument;
use WpPasskeys\Admin\UserPasskeysCardRender;
use WpPasskeys\Credentials\CredentialEntityInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\UsernameHandler;
use WpPasskeys\Exceptions\InsertUserException;
use WpPasskeys\Exceptions\InvalidCredentialsException;
use WpPasskeys\Utilities;

class RegisterEndpoints implements RegisterEndpointsInterface
{
    private array $response;

    public function __construct(
        public readonly CredentialHelperInterface $credentialHelper,
        public readonly CredentialEntityInterface $credentialEntity,
        public readonly Utilities $utilities,
        public readonly UsernameHandler $usernameHandler,
        public readonly PublicKeyCredentialParameters $publicKeyCredentialParameters,
        public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader,
        public readonly AttestationStatementSupportManager $attestationStatementSupportManager,
        public readonly UserPasskeysCardRender $userPasskeysCardRender,
    ) {
    }

    /**
     * @throws Exception
     */
    public function createPublicKeyCredentialOptions(): WP_Error|WP_REST_Response
    {
        $userData = $this->usernameHandler->getOrCreateUserData();

        try {
            $publicKeyCredentialCreationOptions = $this->creationOptions(
                $userData['user_login']
            );
            $this->credentialHelper->saveSessionCredentialOptions(
                $publicKeyCredentialCreationOptions
            );
        } catch (JsonException $e) {
            $this->utilities->handleException($e, 500);
        }

        return new WP_REST_Response($publicKeyCredentialCreationOptions, 200);
    }

    /**
     * @throws Exception
     */
    public function creationOptions(string $userLogin): PublicKeyCredentialCreationOptions
    {
        $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
            $this->credentialEntity->createRpEntity(),
            $this->credentialEntity->createUserEntity($userLogin),
            $this->getChallenge(),
            $this->getPkParameters(),
        );

        $publicKeyCredentialCreationOptions->timeout                = $this->getTimeout();
        $publicKeyCredentialCreationOptions->authenticatorSelection = AuthenticatorSelectionCriteria::create(
            AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            true
        );

        $publicKeyCredentialCreationOptions->attestation =
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE;

        return $publicKeyCredentialCreationOptions;
    }

    public function getChallenge(): string
    {
        return base64_encode(random_bytes(32));
    }

    public function getPkParameters(): array
    {
        return $this->publicKeyCredentialParameters->get();
    }

    public function getTimeout(): int
    {
        return (int)get_option('wppk_passkeys_timeout', 30000);
    }

    public function verifyPublicKeyCredentials(
        WP_REST_Request $request
    ): WP_REST_Response {
        try {
            $pkCredential     = $this->getPkCredential($request);
            $pkKeyCredentials = $this->credentialHelper->getPublicKeyCredentials(
                $this->getAuthenticatorAttestationResponse(
                    $pkCredential
                ),
                $this->attestationStatementSupportManager,
                ExtensionOutputCheckerHandler::create(),
            );

            $publicKeyCredentialId = $this->utilities->safeEncode($pkKeyCredentials->publicKeyCredentialId);

            $userId = $this->credentialHelper->updateOrCreateUser(
                $publicKeyCredentialId
            );

            if (is_wp_error($userId)) {
                return $this->utilities->handleWpError($userId);
            }

            $this->credentialHelper->saveCredentialSource($pkKeyCredentials);

            $this->utilities->setAuthCookie(
                get_user_by('id', $userId)->user_login,
                ! is_wp_error($userId) ? $userId : null,
            );

            $this->response = [
                'code'    => 200,
                'message' => $this->registerSuccessMessage(),
                'data'    => [
                    'redirectUrl' => $this->utilities->getRedirectUrl(),
                    'cardHtml'    => $this->userPasskeysCardRender->renderPasskeyCard(
                        $publicKeyCredentialId
                    ) ?? '',
                ],
            ];

            return new WP_REST_Response($this->response, 200);
        } catch (JsonException | InvalidCredentialsException | InsertUserException $e) {
            $response = $this->utilities->handleException($e, $e->getCode());
        } catch (InvalidArgument $e) {
            $response = $this->utilities->handleException($e, 'Invalid Argument');
        } catch (Throwable $e) {
            $response = $this->utilities->handleException($e);
        }

        return $response;
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

    public function getPkCredentialResponse(PublicKeyCredential $pkCredential): AuthenticatorResponse
    {
        return $pkCredential->response;
    }

    public function registerSuccessMessage(): string
    {
        if (is_user_logged_in()) {
            return 'You have registered your passkeys successfully. Now you can use it to login instead of password.';
        }

        return 'Your account has been created. You are being redirect now to dashboard...';
    }

    public function getRedirectUrl(): string
    {
        return ! is_user_logged_in() ? $this->utilities->getRedirectUrl() : '';
    }

    public function getVerifiedResponse(): array
    {
        return $this->response;
    }
}
