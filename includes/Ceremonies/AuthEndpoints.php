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
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
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
use WP_User;
use WpPasskeys\AlgorithmManager\AlgorithmManager;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Interfaces\WebAuthnInterface;
use WpPasskeys\Utilities;

class AuthEndpoints implements WebAuthnInterface
{
    public readonly CredentialHelper $credentialHelper;
    public readonly PublicKeyCredential $publicKeyCredential;
    public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader;
    public readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator;
    public readonly AlgorithmManager $algorithmManager;
    public readonly WP_User $user;
    public const API_NAMESPACE = '/authenticator';

    public function __construct()
    {
        $this->publicKeyCredentialLoader = new PublicKeyCredentialLoader(
            AttestationObjectLoader::create(
                AttestationStatementSupportManager::create()
            )
        );
        // Temporary create PK Source but should be deprecated soon
        $this->authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
            null,
            null,
            ExtensionOutputCheckerHandler::create(),
            null,
        );
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
            $this->publicKeyCredential = $this->publicKeyCredentialLoader->load($request->get_body());
            $authenticatorAssertionResponse = $this->publicKeyCredential->response;
            if (! $authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse) {
                return new WP_Error(
                    'Invalid_response',
                    'AuthenticatorAssertionResponse expected',
                    array( 'status' => 400 )
                );
            }

            $this->authenticatorAssertionResponseValidator::create(
                new CredentialHelper(),
                null,
                ExtensionOutputCheckerHandler::create(),
                (new AlgorithmManager())->init(),
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
                $userId = CredentialHelper::getUserByCredentialId($request->get_param('id'));
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
