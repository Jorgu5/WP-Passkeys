<?php

/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

declare(strict_types=1);

namespace WpPasskeys\Ceremonies;

use Exception;
use InvalidArgumentException;
use JsonException;
use Throwable;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpOrg\Requests\Exception\InvalidArgument;
use WpPasskeys\AlgorithmManager\AlgorithmManagerInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Exceptions\RandomException;
use WpPasskeys\Utilities;
use WpPasskeys\UtilitiesInterface;

class AuthEndpoints implements AuthEndpointsInterface
{
    public const SESSION_KEY = 'pk_credential_request_options';
    private const CHALLENGE_LENGTH = 32;
    /**
     * @var array <array-key, mixed>
     */
    public array $verifiedResponse;

    public function __construct(
        public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader,
        public readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator,
        public readonly CredentialHelperInterface $credentialHelper,
        public readonly AlgorithmManagerInterface $algorithmManager,
        public readonly UtilitiesInterface $utilities,
        public readonly SessionHandlerInterface $sessionHandler
    ) {
        $this->verifiedResponse = [];
    }

    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response
    {
        $publicKeyCredentialRequestOptions = $this->requestOptions();
        $this->sessionHandler->set(self::SESSION_KEY, $publicKeyCredentialRequestOptions);
        return new WP_REST_Response($publicKeyCredentialRequestOptions, 200);
    }

    public function verifyPublicKeyCredentials(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $authenticatorAssertionResponse =
                $this->getAuthenticatorAssertionResponse(
                    $this->getPkCredential($request)
                );

            $this->validateAuthenticatorAssertionResponse($authenticatorAssertionResponse, $request);
            $this->loginUserWithCookie($request);

            $this->verifiedResponse = [
                'status' => 'Verified',
                'statusText' => 'Successfully verified the credential.',
                'redirectUrl' => $this->utilities->getRedirectUrl()
            ];

            $response = new WP_REST_Response($this->verifiedResponse, 200);
        } catch (JsonException $e) {
            $response = new WP_Error('json', $e->getMessage(), $e->getTrace());
        } catch (InvalidArgument $e) {
            $response = new WP_Error('invalid-argument', $e->getMessage(), $e->getTrace());
        } catch (CredentialException $e) {
            $response = new WP_Error('credential-error', $e->getMessage(), $e->getTrace());
        } catch (Throwable $e) {
            $response = new WP_Error('server', $e->getMessage(), $e->getTrace());
        }
        return $response;
    }

    public function getVerifiedResponse(): array
    {
        return $this->verifiedResponse;
    }

    public function getAuthenticatorAssertionResponse(
        PublicKeyCredential $pkCredential
    ): AuthenticatorAssertionResponse {
        $authenticatorAssertionResponse = $this->getPkCredentialResponse($pkCredential);
        if (!($authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse)) {
            throw new \InvalidArgumentException('AuthenticatorAssertionResponse expected');
        }

        return $authenticatorAssertionResponse;
    }

    public function getPkCredentialResponse(PublicKeyCredential $pkCredential)
    {
        return $pkCredential->response;
    }

    public function validateAuthenticatorAssertionResponse(
        AuthenticatorAssertionResponse $authenticatorAssertionResponse,
        WP_REST_Request $request
    ): void {
        $this->createAuthenticatorAssertionResponse()->check(
            $this->getRawId($request),
            $authenticatorAssertionResponse,
            $this->sessionHandler->get(self::SESSION_KEY),
            Utilities::getHostname(),
            $authenticatorAssertionResponse->userHandle,
            ['localhost']
        );
    }

    public function createAuthenticatorAssertionResponse(): AuthenticatorAssertionResponseValidator
    {
        return $this->authenticatorAssertionResponseValidator::create(
            $this->credentialHelper,
            null,
            ExtensionOutputCheckerHandler::create(),
            $this->algorithmManager->init(),
            null,
        );
    }

    public function getPkCredential(WP_REST_Request $request): PublicKeyCredential
    {
        $data = $request->get_body();
        return $this->publicKeyCredentialLoader->load($data);
    }

    public function loginUserWithCookie(WP_REST_Request $request): void
    {
        if ($request->has_param('id')) {
            $userId = $this->credentialHelper->getUserByCredentialId($request->get_param('id'));
            $this->utilities->setAuthCookie(null, $userId);
        } else {
            $userLogin = $this->credentialHelper->getUserLogin();
            $this->utilities->setAuthCookie($userLogin);
        }
    }

    public function getRawId(WP_REST_Request $request): string
    {
        $rawId = $request->get_param('rawId');
        if (!is_string($rawId)) {
            throw new InvalidArgumentException('Raw ID must be a string');
        }
        if (empty($rawId)) {
            throw new InvalidArgumentException('Raw ID is empty');
        }
        return $rawId;
    }

    public function requestOptions(): PublicKeyCredentialRequestOptions | WP_Error
    {
        try {
            $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions::create($this->getChallenge());
            $publicKeyCredentialRequestOptions->allowCredentials = [];
            $publicKeyCredentialRequestOptions->userVerification =
                $publicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED;
            $publicKeyCredentialRequestOptions->rpId = Utilities::getHostname();
            return $publicKeyCredentialRequestOptions;
        } catch (Exception $e) {
            return new WP_Error('server', $e->getMessage(), $e->getTrace());
        }
    }

    public function getChallenge(): string
    {
        return random_bytes(self::CHALLENGE_LENGTH);
    }
}
