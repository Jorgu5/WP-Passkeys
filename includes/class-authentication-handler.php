<?php
/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

// TODO: Add authentication handler.

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
use WpPasskeys\Interfaces\Authentication_Handler_Interface;
use WpPassKeys\SingletonTrait\SingletonTrait;
use WpPasskeys\Utilities;

class Authentication_Handler implements Authentication_Handler_Interface {

	use SingletonTrait;
	/**
	 * @var PublicKeyCredentialSource $public_key_credential_source
	 */
	private PublicKeyCredentialSource $public_key_credential_source;
	/**
	 * @var PublicKeyCredentialSourceRepository $public_key_credential_source_repository
	 */
	private PublicKeyCredentialSourceRepository $public_key_credential_source_repository;
	/**
	 * @var array $get_allowed_credentials
	 */
	private array $get_allowed_credentials;
	/**
	 * @var PublicKeyCredential $public_key_credential
	 */
	private PublicKeyCredential $public_key_credential;
	/**
	 * @var AuthenticatorAssertionResponseValidator $authenticator_assertion_response_validator
	 */
	private AuthenticatorAssertionResponseValidator $authenticator_assertion_response_validator;
	/**
	 * @var WP_User $user
	 */
	private WP_User $user;

	public function init(): void {
		$this->user = wp_get_current_user();
	}

	/**
	 * @throws \Exception
	 */
	public function create_public_key_credential_options( WP_REST_Request $request
	): WP_REST_Response {
		try {
			$challenge                             = random_bytes( 32 );
			$public_key_credential_request_options =
				PublicKeyCredentialRequestOptions::create(
					$challenge
				)->allowCredentials( ...$this->get_allowed_credentials() )
												->setUserVerification(
													PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
												);

			set_transient( 'public_key_credential_request_options_' . $this->user->ID, $public_key_credential_request_options, 300 );

			return new WP_REST_Response( array( 'message' => 'Success' ), 200 );
		} catch ( Exception $e ) {
			throw new RuntimeException( $e->getMessage() );
		}
	}

    /**
     * Retrieves the allowed credentials for a user entity.
     *
     * @return array The array of allowed credentials.
     */
	private function get_allowed_credentials(): array {
		$user_entity = Utilities::get_user_entity(wp_get_current_user());
		$registeredAuthenticators = $this->public_key_credential_source_repository->findAllForUserEntity( $user_entity );

		return array_map(
			static function ( PublicKeyCredentialSource $credential ): PublicKeyCredentialDescriptor {
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
	public function response_authenticator( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$authenticator_assertion_response = $this->public_key_credential->getResponse();
			if ( ! $authenticator_assertion_response instanceof AuthenticatorAssertionResponse ) {
				return new WP_Error( 'Invalid_response', 'AuthenticatorAssertionResponse expected', array( 'status' => 400 ) );
			}

			$this->authenticator_assertion_response_validator->check(
				$this->public_key_credential->getRawId(),
				$authenticator_assertion_response,
				get_transient( 'public_key_credential_request_options_' . $this->user->ID ),
				get_site_url(),
				$authenticator_assertion_response->getUserHandle(),
			);

			return new WP_REST_Response( array( 'message' => 'Success' ), 200 );
		} catch ( JsonException | Exception $e ) {
			return new WP_Error( 'Invalid_response', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'Invalid_response', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function register_auth_routes(): void {
		// TODO: Implement register_routes() method.
	}
}
