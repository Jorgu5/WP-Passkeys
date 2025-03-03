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
use Random\RandomException;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpOrg\Requests\Exception\InvalidArgument;
use WpPasskeys\AlgorithmManager\AlgorithmManagerInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\Exceptions\InvalidCredentialsException;
use WpPasskeys\Exceptions\InvalidUserDataException;
use WpPasskeys\Utilities;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use function random_bytes;

class AuthEndpoints implements AuthEndpointsInterface
{
    public const SESSION_KEY = 'pk_credential_request_options';
    private const CHALLENGE_LENGTH = 32;
    /**
     * @var array <array-key, mixed>
     */
    public array $verifiedResponse;

    public function __construct(
        public readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator,
        public readonly CredentialHelperInterface $credentialHelper,
        public readonly AlgorithmManagerInterface $algorithmManager,
        public readonly Utilities $utilities,
        public readonly SessionHandlerInterface $sessionHandler,
        public readonly WebauthnSerializerFactory $serializer,
    ) {
        $this->verifiedResponse = [];
    }

    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response
    {
        $publicKeyCredentialRequestOptions = $this->requestOptions();
        $this->sessionHandler->set(self::SESSION_KEY, $publicKeyCredentialRequestOptions);

        return new WP_REST_Response($publicKeyCredentialRequestOptions, 200);
    }

    public function requestOptions(): PublicKeyCredentialRequestOptions|WP_Error
    {
        try {
            $publicKeyCredentialRequestOptions                   = PublicKeyCredentialRequestOptions::create(
                $this->getChallenge()
            );
            $publicKeyCredentialRequestOptions->allowCredentials = [];
            $publicKeyCredentialRequestOptions->userVerification =
                $publicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED;
            $publicKeyCredentialRequestOptions->rpId             = $this->utilities->getHostname();

            return $publicKeyCredentialRequestOptions;
        } catch (Exception $e) {
            return new WP_Error('server', $e->getMessage(), $e->getTrace());
        }
    }

    /**
     * Generate a random challenge for WebAuthn authentication
     * 
     * @return string Base64 encoded random bytes
     */
    public function getChallenge(): string
    {
        // Use openssl_random_pseudo_bytes as an alternative to random_bytes
        return base64_encode(openssl_random_pseudo_bytes(self::CHALLENGE_LENGTH));
    }

    public function verifyPublicKeyCredentials(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Log the request for debugging
            error_log('[WP Passkeys] Verify request received with ID: ' . $request->get_param('id'));

            $authenticatorAssertionResponse =
                $this->getAuthenticatorAssertionResponse(
                    $this->getPublicKeyCredential($request)
                );

            $this->validateAuthenticatorAssertionResponse($authenticatorAssertionResponse, $request);
            $this->loginUserWithCookie($request);

            $this->verifiedResponse = [
                'code'    => 200,
                'message' => 'Successfully verified the credential',
                'data'    => [
                    'redirectUrl'  => $this->utilities->getRedirectUrl(),
                    'last_used_os' => $this->utilities->getDeviceOS(),
                    'last_used_at' => date('Y-m-d H:i:s'),
                ],
            ];

            $this->credentialHelper->updateCredentialSourceData(
                $request->get_param('id')
            );

            $response = new WP_REST_Response($this->verifiedResponse, 200);

            // Log successful verification
            error_log('[WP Passkeys] Successfully verified credential for ID: ' . $request->get_param('id'));
        } catch (JsonException | InvalidCredentialsException $e) {
            // For 404 errors, ensure we return a 404 status code
            $statusCode = $e->getCode() === 404 ? 404 : 400;
            $errorMessage = $e->getMessage();

            error_log('[WP Passkeys] Verification error (' . $statusCode . '): ' . $errorMessage);

            $response = new WP_REST_Response([
                'code'    => $e->getCode() ?: $statusCode,
                'message' => 'An error occurred while processing your request.',
                'dev_message' => $errorMessage,
            ], $statusCode);
        } catch (InvalidArgumentException $e) {
            error_log('[WP Passkeys] Invalid argument error: ' . $e->getMessage());

            $response = new WP_REST_Response([
                'code'    => 'Invalid Argument',
                'message' => 'An error occurred while processing your request.',
                'dev_message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
            error_log('[WP Passkeys] Unexpected error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            $response = new WP_REST_Response([
                'code'    => 500,
                'message' => 'An error occurred while processing your request.',
                'dev_message' => $e->getMessage(),
            ], 500);
        }

        return $response;
    }

    public function getAuthenticatorAssertionResponse(
        PublicKeyCredential $pkCredential
    ): AuthenticatorAssertionResponse {
        $authenticatorAssertionResponse = $this->getPublicKeyCredentialResponse($pkCredential);
        if (! ($authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse)) {
            throw new InvalidArgumentException('AuthenticatorAssertionResponse expected');
        }

        return $authenticatorAssertionResponse;
    }

    public function getPublicKeyCredentialResponse(PublicKeyCredential $pkCredential): AuthenticatorResponse
    {
        return $pkCredential->response;
    }

    public function getPublicKeyCredential(WP_REST_Request $request): PublicKeyCredential
    {
        try {
            $body = $request->get_body();

            // Log the request body for debugging
            error_log('[WP Passkeys] Request body: ' . substr($body, 0, 200) . '...');

            if (empty($body)) {
                throw new InvalidArgumentException('Empty request body');
            }

            return $this->serializer->create()->deserialize($body, PublicKeyCredential::class, 'json');
        } catch (Throwable $e) {
            error_log('[WP Passkeys] Error deserializing PublicKeyCredential: ' . $e->getMessage());
            throw $e;
        }
    }

    public function validateAuthenticatorAssertionResponse(
        AuthenticatorAssertionResponse $authenticatorAssertionResponse,
        WP_REST_Request $request
    ): void {
        try {
            $credentialId = $this->getPublicKeyCredential($request)->rawId;
            error_log('[WP Passkeys] Validating credential ID: ' . $this->utilities->safeEncode($credentialId));

            if ($credentialId === '') {
                error_log('[WP Passkeys] Empty credential ID');
                throw new InvalidCredentialsException(
                    'You do not have any passkeys registered.',
                    404
                );
            }

            $encodedCredentialId = $this->utilities->safeEncode($credentialId);
            error_log('[WP Passkeys] Looking up credential ID: ' . $encodedCredentialId);

            $publicKeyCredentialSource = $this->credentialHelper->findOneByCredentialId(
                $encodedCredentialId,
            );

            if ($publicKeyCredentialSource === null) {
                error_log('[WP Passkeys] No credential source found for ID: ' . $encodedCredentialId);
                throw new InvalidCredentialsException(
                    'No user found with this credential. Please register a passkey first.',
                    404
                );
            }

            error_log('[WP Passkeys] Credential source found, proceeding with validation');

            // Check if session data exists
            $sessionData = $this->sessionHandler->get(self::SESSION_KEY);
            if ($sessionData === null) {
                error_log('[WP Passkeys] Session data is missing for key: ' . self::SESSION_KEY);
                throw new InvalidCredentialsException(
                    'Session data is missing. Please try again.',
                    400
                );
            }

            // Log session data for debugging
            error_log('[WP Passkeys] Session data: ' . json_encode($sessionData));

            // Perform the validation
            try {
                $this->authenticatorAssertionResponseValidator->check(
                    $publicKeyCredentialSource,
                    $authenticatorAssertionResponse,
                    $sessionData,
                    $this->utilities->getHostname(),
                    $authenticatorAssertionResponse->userHandle,
                    null
                );
                error_log('[WP Passkeys] Validation successful');
            } catch (Throwable $e) {
                error_log('[WP Passkeys] Validation error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                throw new InvalidCredentialsException(
                    'Validation failed: ' . $e->getMessage(),
                    400
                );
            }
        } catch (Throwable $e) {
            error_log('[WP Passkeys] Error in validateAuthenticatorAssertionResponse: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @throws InvalidCredentialsException
     * @throws InvalidUserDataException
     */
    public function loginUserWithCookie(WP_REST_Request $request): void
    {
        if ($request->has_param('id')) {
            $userId = $this->credentialHelper->getUserByCredentialId($request->get_param('id'));
            if ($userId instanceof WP_Error) {
                throw new InvalidCredentialsException($userId->get_error_message());
            }
            $this->utilities->setAuthCookie(null, $userId);
        } else {
            $userLogin = $this->credentialHelper->getSessionUserLogin();
            $this->utilities->setAuthCookie($userLogin);
        }
    }

    public function getRawId(WP_REST_Request $request): string
    {
        $rawId = $request->get_param('rawId');
        if (! is_string($rawId)) {
            throw new InvalidArgumentException('Raw ID must be a string');
        }
        if (empty($rawId)) {
            throw new InvalidArgumentException('Raw ID is empty');
        }

        return $rawId;
    }

    public function getVerifiedResponse(): array
    {
        return $this->verifiedResponse;
    }
}
