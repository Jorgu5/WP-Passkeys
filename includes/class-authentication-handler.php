<?php
/**
 * Authentication Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 */

// TODO: Add authentication handler.

namespace WpPasskeys;

use Exception;
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
use WpPasskeys\Interfaces\Authentication_Handler_Interface;
use WpPassKeys\SingletonTrait\SingletonTrait;

class Authentication_Handler implements Authentication_Handler_Interface {

	use SingletonTrait;

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
	 * @throws \Exception
	 */
	public function create_public_key_credential_options( WP_REST_Request $request
	): WP_REST_Response {
		// TODO: Pass the valid data to Entities.
		try {
			$challenge                         = random_bytes( 32 );
			$publicKeyCredentialRequestOptions =
				PublicKeyCredentialRequestOptions::create(
					$challenge
				)->allowCredentials( ...$this->get_allowed_credentials )
												->setUserVerification(
													PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
												);
			return new WP_REST_Response( array( 'message' => 'Success' ), 200 );
		} catch ( Exception $e ) {
			throw new RuntimeException( $e->getMessage() );
		}
	}

	private function get_allowed_credentials( $userEntity ): array {
		$registeredAuthenticators = $this->public_key_credential_source_repository->findAllForUserEntity( $userEntity );

		return array_map(
			static function ( PublicKeyCredentialSource $credential ): PublicKeyCredentialDescriptor {
				return $credential->getPublicKeyCredentialDescriptor();
			},
			$registeredAuthenticators
		);
	}

	public function response_authenticator( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$authenticator_assertion_response = $this->public_key_credential->getResponse();
			if ( ! $authenticator_assertion_response instanceof AuthenticatorAssertionResponse ) {
				return new WP_Error( 'Invalid_response', 'AuthenticatorAssertionResponse expected', array( 'status' => 400 ) );
			}

			$publicKeyCredentialSource = $this->authenticator_assertion_response_validator->check(
				$this->public_key_credential->getRawId(),
				$authenticator_assertion_response,
				$publicKeyCredentialRequestOptions, // TODO: save this in transient when you create the options successfully
				'my-application.com', // TODO: use the same domain you used when you created the options
				$userHandle // TODO: this is the user handle you got from the options $publicKeyCredentialSource->getUserHandle()
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
