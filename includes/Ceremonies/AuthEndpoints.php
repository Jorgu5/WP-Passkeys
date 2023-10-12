<?php

/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

namespace WpPasskeys\Ceremonies;

use Exception;
use JsonException;
use Random\RandomException;
use Throwable;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpPasskeys\AlgorithmManager\AlgorithmManager;
use WpPasskeys\AlgorithmManager\AlgorithmManagerInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Interfaces\WebAuthnInterface;
use WpPasskeys\Utilities;

class AuthEndpoints implements WebAuthnInterface
{
    public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader;
    public readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator;

    public const API_NAMESPACE = '/authenticator';
    public readonly CredentialHelperInterface $credentialHelper;
    public readonly AlgorithmManagerInterface $algorithmManager;

    private PublicKeyCredentialRequestOptions $options;
    private array $verifiedResponse;

    public function __construct(
        PublicKeyCredentialLoader $publicKeyCredentialLoader,
        AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator,
        CredentialHelperInterface $credentialHelper,
        AlgorithmManagerInterface $algorithmManager
    ) {
        $this->publicKeyCredentialLoader = $publicKeyCredentialLoader;
        $this->authenticatorAssertionResponseValidator = $authenticatorAssertionResponseValidator;
        $this->credentialHelper = $credentialHelper;
        $this->algorithmManager = $algorithmManager;
    }

    /**
     * @param WP_REST_Request $request *
     *
     * @throws Exception
*/
    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $challenge                         = random_bytes(32);
            $publicKeyCredentialRequestOptions = PublicKeyCredentialRequestOptions
                ::create(
                    $challenge
                );
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 500);
        }

        $publicKeyCredentialRequestOptions->allowCredentials = []; // ... $this->getAllowedCredentials()
        $publicKeyCredentialRequestOptions->userVerification =
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED;
        $publicKeyCredentialRequestOptions->rpId = Utilities::getHostname();

        $this->options = $publicKeyCredentialRequestOptions;
        SessionHandler::set('pk_credential_request_options', $this->options);
        return new WP_REST_Response($this->options, 200);
    }

    /**
     * Generate the function comment for the response_authenticator function.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error The REST response or error.
     */
    public function verifyPublicKeyCredentials(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $data = $request->get_body();
            $publicKeyCredential            = $this->publicKeyCredentialLoader->load($data);
            $authenticatorAssertionResponse = $publicKeyCredential->getResponse();
            if ($this->isValidAuthenticatorAssertionResponse($authenticatorAssertionResponse) === false) {
                return new WP_Error(
                    'Invalid_response',
                    'AuthenticatorAssertionResponse expected',
                    array( 'status' => 400 )
                );
            }

            $this->authenticatorAssertionResponseValidator::create(
                $this->credentialHelper,
                null,
                ExtensionOutputCheckerHandler::create(),
                $this->algorithmManager->init(),
                null,
            )->check(
                $request->get_param('rawId'),
                $authenticatorAssertionResponse,
                SessionHandler::get('pk_credential_request_options'),
                Utilities::getHostname(),
                $authenticatorAssertionResponse->userHandle,
                ['localhost']
            );

            if ($request->has_param('id')) {
                $userId = $this->credentialHelper->getUserByCredentialId($request->get_param('id'));
                Utilities::setAuthCookie(null, $userId);
            } else {
                Utilities::setAuthCookie(SessionHandler::get('user_data')['user_login']);
            }

            $this->verifiedResponse = [
                    'status' => 'Verified',
                    'statusText' => 'Successfully verified the credential.',
                    'redirectUrl' => Utilities::getRedirectUrl(),
            ];

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

    public function getOptions(): PublicKeyCredentialRequestOptions
    {
        return $this->options;
    }

    public function getVerifiedResponse(): array
    {
        return $this->verifiedResponse;
    }

    protected function isValidAuthenticatorAssertionResponse($response): bool
    {
        return $response instanceof AuthenticatorAssertionResponse;
    }
}
