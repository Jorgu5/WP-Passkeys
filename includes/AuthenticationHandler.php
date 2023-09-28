<?php

/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

namespace WpPasskeys;

use Cose\Algorithm\Manager;
use Exception;
use JsonException;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Interfaces\WebAuthnInterface;

class AuthenticationHandler implements WebAuthnInterface
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
        $this->credentialHelper = CredentialHelper::instance();
        $this->authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
            $this->credentialHelper,
            null,
            ExtensionOutputCheckerHandler::create(),
            null,
        );
    }

    /**
     * @param WP_REST_Request $request *
     *
     * @throws \Exception
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


                SessionHandler::instance()->set('pk_credential_request_options', $publicKeyCredentialRequestOptions);

            return new WP_REST_Response($publicKeyCredentialRequestOptions, 200);
        } catch (Exception $e) {
            return new WP_REST_Response($e->getMessage(), 400);
        }
    }

    /**
     * Retrieves the allowed credentials for a user entity.
     *
     * @return array The array of allowed credentials.
     * @throws InvalidDataException
     * @throws JsonException
     * @throws CredentialException
     */
    private function getAllowedCredentials(): array
    {
        return $this
            ->credentialHelper
            ->findAllForUserEntity(Utilities::createUserEntity(
                SessionHandler::instance()->get('user_login')
            ));
    }

    /**
     * Generate the function comment for the response_authenticator function.
     *
     * @param WP_REST_Request $request The REST request object.
     * @throws WP_Error If the response is invalid.
     * @return WP_REST_Response|WP_Error The REST response or error.
     */
    public function verifyPublicKeyCredentials(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $this->publicKeyCredential = $this->publicKeyCredentialLoader->load($request->get_body());
            $authenticatorAssertionResponse = $this->publicKeyCredential->getResponse();
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
                AlgorithmManager::instance()->init(),
                null,
            )->check(
                $request->get_param('rawId'),
                $authenticatorAssertionResponse,
                SessionHandler::instance()->get('pk_credential_request_options'),
                Utilities::getHostname(),
                $authenticatorAssertionResponse->userHandle,
                ['localhost']
            );

            if ($request->has_param('id')) {
                $userId = $this->credentialHelper->getUserByCredentialId($request->get_param('id'));
                Utilities::setAuthCookie(null, $userId);
            } else {
                Utilities::setAuthCookie(SessionHandler::instance()->get('user_login'));
            }

            $response = new WP_REST_Response(array(
                'status' => 'Verified',
                'statusText' => 'Successfully verified the credential.',
                'redirectUrl' => get_admin_url(),
            ), 200);
        } catch (JsonException | Exception $e) {
            $response = new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 400 ));
        } catch (\Throwable $e) {
            $response = new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 400 ));
        }

        return $response;
    }
}
