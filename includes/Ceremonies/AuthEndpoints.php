<?php

/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

namespace WpPasskeys\Ceremonies;

use Exception;
use JsonException;
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
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Interfaces\WebAuthnInterface;
use WpPasskeys\Utilities;

class AuthEndpoints implements WebAuthnInterface
{
    private readonly PublicKeyCredentialLoader $publicKeyCredentialLoader;
    private readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator;

    public const API_NAMESPACE = '/authenticator';
    private readonly CredentialHelperInterface $credentialHelper;
    private readonly AlgorithmManager $algorithmManager;

    public function __construct(
        PublicKeyCredentialLoader $publicKeyCredentialLoader,
        AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator,
        CredentialHelperInterface $credentialHelper,
        AlgorithmManager $algorithmManager
    )
    {
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
            $publicKeyCredentialRequestOptions->allowCredentials = []; // ... $this->getAllowedCredentials()
            $publicKeyCredentialRequestOptions->userVerification =
                PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED;
            $publicKeyCredentialRequestOptions->rpId = Utilities::getHostname();

            SessionHandler::set('pk_credential_request_options', $publicKeyCredentialRequestOptions);

            return new WP_REST_Response($publicKeyCredentialRequestOptions, 200);
        } catch (Exception $e) {
            return new WP_REST_Response($e->getMessage(), 400);
        }
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
            $publicKeyCredential            = $this->publicKeyCredentialLoader->load($request->get_body());
            $authenticatorAssertionResponse = $publicKeyCredential->response;
            if (! $authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse) {
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

            $response = new WP_REST_Response(array(
                'status' => 'Verified',
                'statusText' => 'Successfully verified the credential.',
                'redirectUrl' => Utilities::getRedirectUrl(),
            ), 200);
        } catch (JsonException | CredentialException $e) {
            $response = new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 400 ));
        } catch (Throwable $e) {
            $response = new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 500 ));
        }

        return $response;
    }
}
