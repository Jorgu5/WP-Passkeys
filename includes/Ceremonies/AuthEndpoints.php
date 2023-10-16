<?php

/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

namespace WpPasskeys\Ceremonies;

use Exception;
use InvalidArgumentException;
use JsonException;
use Throwable;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Exception\InvalidDataException;
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
use WpPasskeys\Interfaces\WebAuthnInterface;
use WpPasskeys\UtilitiesInterface;

class AuthEndpoints implements WebAuthnInterface
{
    public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader;
    public readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator;

    public const SESSION_KEY = 'pk_credential_request_options';
    private const CHALLENGE_LENGTH = 32;

    public readonly CredentialHelperInterface $credentialHelper;
    public readonly AlgorithmManagerInterface $algorithmManager;
    public readonly UtilitiesInterface $utilities;
    public readonly SessionHandlerInterface $sessionHandler;
    /**
     * @var array <array-key, mixed>
     */
    public array $verifiedResponse;

    public function __construct(
        PublicKeyCredentialLoader $publicKeyCredentialLoader,
        AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator,
        CredentialHelperInterface $credentialHelper,
        AlgorithmManagerInterface $algorithmManager,
        UtilitiesInterface $utilities,
        SessionHandlerInterface $sessionHandler
    ) {
        $this->publicKeyCredentialLoader = $publicKeyCredentialLoader;
        $this->authenticatorAssertionResponseValidator = $authenticatorAssertionResponseValidator;
        $this->credentialHelper = $credentialHelper;
        $this->algorithmManager = $algorithmManager;
        $this->utilities = $utilities;
        $this->sessionHandler = $sessionHandler;
        $this->verifiedResponse = [];
    }

    /**
     * @param WP_REST_Request $request *
     *
     * @throws Exception
     */
    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response
    {
        $publicKeyCredentialRequestOptions = $this->createOptions();
        $publicKeyCredentialRequestOptions->allowCredentials = []; // ... $this->getAllowedCredentials()
        $publicKeyCredentialRequestOptions->userVerification =
            $publicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED;
        $publicKeyCredentialRequestOptions->rpId = $this->utilities->getHostname();
        $this->sessionHandler->set(self::SESSION_KEY, $publicKeyCredentialRequestOptions);
        return new WP_REST_Response($publicKeyCredentialRequestOptions, 200);
    }

    /**
     *
     * @param WP_REST_Request<array<array-key, mixed>> $request
     * @return WP_REST_Response|WP_Error The REST response or error.
     */
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

    /**
     * @return array<array-key, mixed>
     *
     */
    public function getVerifiedResponse(): array
    {
        return $this->verifiedResponse;
    }

    public function getAuthenticatorAssertionResponse(
        PublicKeyCredential $pkCredential
    ): AuthenticatorAssertionResponse {
        $authenticatorAssertionResponse = $pkCredential->response;
        if (!($authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse)) {
            throw new \InvalidArgumentException('AuthenticatorAssertionResponse expected');
        }

        return $authenticatorAssertionResponse;
    }

    /**
     * @param WP_REST_Request<array<array-key, mixed>> $request
     * @throws Throwable
     */
    public function validateAuthenticatorAssertionResponse(
        AuthenticatorAssertionResponse $authenticatorAssertionResponse,
        WP_REST_Request $request
    ): void {
        $this->authenticatorAssertionResponseValidator::create(
            $this->credentialHelper,
            null,
            ExtensionOutputCheckerHandler::create(),
            $this->algorithmManager->init(),
            null,
        )->check(
            $this->getRawId($request),
            $authenticatorAssertionResponse,
            $this->sessionHandler->get(self::SESSION_KEY),
            $this->utilities->getHostname(),
            $authenticatorAssertionResponse->userHandle,
            ['localhost']
        );
    }

    public function getUserLogin(): string
    {
        $userData = $this->sessionHandler->get('user_data');
        $userLogin = '';
        if (is_array($userData) && isset($userData['user_login'])) {
            $userLogin = $userData['user_login'];
        }

        return (string)$userLogin;
    }

    /**
     * @throws InvalidDataException
     * @throws Throwable
     */
    public function getPkCredential(WP_REST_Request $request): PublicKeyCredential
    {
        $data = $request->get_body();
        return $this->publicKeyCredentialLoader->load($data);
    }

    /**
     * @throws CredentialException
     */
    public function loginUserWithCookie(WP_REST_Request $request): void
    {
        if ($request->has_param('id')) {
            $userId = $this->credentialHelper->getUserByCredentialId($request->get_param('id'));
            $this->utilities->setAuthCookie(null, $userId);
        } else {
            $this->utilities->setAuthCookie($this->getUserLogin());
        }
    }

    public function getRawId(WP_REST_Request $request): string
    {
        $rawId = $request->get_param('rawId');
        if (!is_string($rawId)) {
            throw new InvalidArgumentException('Raw ID must be a string');
        }
        if (empty($rawId)) {
            throw new InvalidArgument('Raw ID is empty');
        }
        return $rawId;
    }

    /**
     * @throws RandomException
     */
    public function createOptions(): PublicKeyCredentialRequestOptions
    {
        try {
            return PublicKeyCredentialRequestOptions::create($this->getChallenge());
        } catch (Exception $e) {
            throw new RandomException($e->getMessage(), 500);
        }
    }

    /**
     * @throws Exception
     */
    public function getChallenge(): string
    {
        return random_bytes(self::CHALLENGE_LENGTH);
    }
}
