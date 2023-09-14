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
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WpPasskeys\Interfaces\AuthenticationInterface;
use WpPasskeys\Traits\SingletonTrait;
use WpPasskeys\utilities;

class AuthenticationHandler implements AuthenticationInterface
{
    use SingletonTrait;

    public readonly PublicKeyCredentialSourceRepository $publicKeyCredentialSourceRepository;
    public readonly PublicKeyCredential $publicKeyCredential;
    public readonly AuthenticatorAssertionResponseValidator $authenticatorAssertionResponseValidator;
    public readonly WP_User $user;

    public function init(): void
    {
        add_action('wp', array( $this, 'getCurrentUser' ));
        add_action('rest_api_init', array( $this, 'registerAuthRoutes' ));
    }

    /**
     * Sets up the current user.
     *
     * @return WP_User The current user.
     */
    public function getCurrentUser(): WP_User
    {
        return $this->user = wp_get_current_user();
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
                    ...$this->get_allowed_credentials()
                )->setUserVerification(
                    PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
                );

            $responseData = array(
                'message'           => 'Success',
                'credentialOptions' =>  $publicKeyCredentialRequestOptions, // Your credentials data here
            );

            return new WP_REST_Response($responseData, 200);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Retrieves the allowed credentials for a user entity.
     *
     * @return array The array of allowed credentials.
     */
    private function getAllowedCredentials(): array
    {
        $user_entity              = Utilities::getUserEntity($this->getCurrentUser());
        $registeredAuthenticators = $this->publicKeyCredentialSourceRepository->findAllForUserEntity($user_entity);

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
    public function responseAuthenticator(WP_REST_Request $request): WP_REST_Response|WP_Error
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

    public function registerAuthRoutes(): void
    {
            register_rest_route(
                WP_PASSKEYS_API_NAMESPACE . '/login',
                '/start',
                array(
                    'methods'  => 'POST',
                    'callback' => array( $this, 'create_public_key_credential_options' ),
                )
            );

            register_rest_route(
                WP_PASSKEYS_API_NAMESPACE . '/login',
                '/authenticate',
                array(
                    'methods'  => 'POST',
                    'callback' => array( $this, 'response_authentication' ),
                )
            );
    }
}
