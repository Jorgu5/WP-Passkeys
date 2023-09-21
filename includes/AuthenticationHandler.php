<?php

/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

namespace WpPasskeys;

use Exception;
use JsonException;
use RuntimeException;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Interfaces\WebAuthnInterface;
use WpPasskeys\Traits\SingletonTrait;
use WpPasskeys\utilities;

class AuthenticationHandler implements WebAuthnInterface
{
    public readonly CredentialHelper $credentialHelper;
    public readonly PublicKeyCredential $publicKeyCredential;
    public readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator;
    public readonly WP_User $user;
    public const API_NAMESPACE = '/authenticator';

    public function __construct()
    {
        $this->credentialHelper = CredentialHelper::instance();
    }

    /**
     * @param WP_REST_Request $request *
     *
     * @throws \Exception
     */
    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $challenge                             = random_bytes(32);
            $publicKeyCredentialRequestOptions =
                PublicKeyCredentialRequestOptions::create(
                    $challenge
                )->allowCredentials(
                    ...$this->getAllowedCredentials()
                )->setUserVerification(
                    PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED
                );

            $responseData = array(
                'message'           => 'Success',
                'credentialOptions' =>  $publicKeyCredentialRequestOptions,
            );

            return new WP_REST_Response($responseData, 200);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Retrieves the allowed credentials for a user entity.
     *
     * @return array The array of allowed credentials.
     * @throws InvalidDataException
     */
    private function getAllowedCredentials(): array
    {
        $registeredAuthenticators = $this->credentialHelper->findAllForUserEntity();

        return array_map(
            static function (PublicKeyCredentialSource $credential): PublicKeyCredentialDescriptor {
                return $credential->getPublicKeyCredentialDescriptor();
            },
            $registeredAuthenticators
        );
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
            $authenticator_assertion_response = $this->publicKeyCredential->getResponse();
            if (! $authenticator_assertion_response instanceof AuthenticatorAssertionResponse) {
                return new WP_Error(
                    'Invalid_response',
                    'AuthenticatorAssertionResponse expected',
                    array( 'status' => 400 )
                );
            }

            $this->authenticatorAssertionResponseValidator->check(
                $this->publicKeyCredential->getRawId(),
                $authenticator_assertion_response,
                get_transient('public_key_credential_request_options_' . $this->getCurrentUser()->ID),
                get_site_url(),
                $authenticator_assertion_response->getUserHandle(),
            );

            return new WP_REST_Response(array( 'message' => 'Success' ), 200);
        } catch (JsonException | Exception $e) {
            return new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 400 ));
        } catch (\Throwable $e) {
            return new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 400 ));
        }
    }

    public function loginWithAuthCookie($username): void
    {
        $user = get_user_by('login', $username);
        if ($user) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID, true);
            do_action('wp_login', $user->user_login, $user);
        }
    }
}
