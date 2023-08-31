<?php
/**
 * Registration Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since 1.0.0
 * @version 1.0.0
 */

declare(strict_types=1);

namespace WpPasskeys;

use Exception;
use JsonException;
use RuntimeException;
use Throwable;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;
use WpPasskeys\Interfaces\Authentication_Handler_Interface;
use WpPasskeys\Utilities as Util;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registration Handler for WP Pass Keys.
 */
class Registration_Handler implements Authentication_Handler_Interface {

	use SingletonTrait;

	private const NAMESPACE = 'wp-passkeys/v1';
	private const DOMAIN    = 'localhost';

	/**
	 * The authenticator attestation response.
	 *
	 * @var AuthenticatorAttestationResponse
	 */
	private AuthenticatorAttestationResponse $authenticator_attestation_response;
	/**
	 * The public key credential creation options.
	 *
	 * @var PublicKeyCredentialCreationOptions|null
	 */
	private ?PublicKeyCredentialCreationOptions $public_key_credential_creation_options;
	/**
	 * The authenticator attestation response validator.
	 *
	 * @var mixed
	 */
	private AuthenticatorAttestationResponseValidator $authenticator_attestation_response_validator;
	/**
	 * @var mixed
	 */
	private PublicKeyCredentialLoader $public_key_credential_loader;

	/**
	 * Register the routes for the API.
	 *
	 * @return void
	 */
	public function register_auth_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/startRegistration',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'create_pk_credential_creation_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/verifyCreation',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'response_authentication' ),
			)
		);
	}

	/**
	 * Stores the public key credential source.
	 *
	 * @throws RuntimeException If the AuthenticatorAttestationResponse is missing.
	 * @throws RuntimeException|Throwable If failed to store the credential source.
	 */
	public function store_public_key_credential_source(): void {
		try {
			$public_key_credential_source = $this->authenticator_attestation_response_validator->check(
				$this->authenticator_attestation_response,
				$this->public_key_credential_creation_options,
				self::DOMAIN
			);

			$credential_helper = new Credential_Helper();
			$credential_helper->saveCredentialSource( $public_key_credential_source );
		} catch ( Exception $e ) {
			throw new RuntimeException( "Failed to store credential source: {$e->getMessage()}" );
		}
	}

	/**
	 * Creates the public key credential creation options.
	 *
	 * @param WP_REST_Request $request the REST request object.
	 * @throws RuntimeException If an error occurs during the process.
	 * @return WP_REST_Response A REST response object with the result.
	 */
	public function create_public_key_credential_options( WP_REST_Request $request
	): WP_REST_Response {
		// TODO: Pass the valid data to Entities.
		try {
			$challenge                             = random_bytes( 16 );
			$public_key_credential_parameters_list = array(
				// TODO: Check what parameters we can use here and add it.
			);
			$this->public_key_credential_creation_options = PublicKeyCredentialCreationOptions::create(
				Util::create_rp_entity(),
				Util::create_user_entity(),
				$challenge,
				$public_key_credential_parameters_list,
			);

			return new WP_REST_Response( array( 'message' => 'Success' ), 200 );
		} catch ( Exception $e ) {
			throw new RuntimeException( $e->getMessage() );
		}
	}

	/**
	 * Handles the creation response.
	 *
	 * @param WP_REST_Request $request the REST request object.
	 *
	 * @return WP_REST_Response|WP_Error a REST response object with the result.
	 */
	public function response_authenticator(
		WP_REST_Request $request
	): WP_REST_Response|WP_Error {
		// TODO: Format $request properly to fit into $data.
		try {
			$data                               = $request->get_body_params();
			$public_key_credential              = $this->public_key_credential_loader->load( $data );
			$authenticator_attestation_response = $public_key_credential->getResponse();

			if ( ! $authenticator_attestation_response instanceof AuthenticatorAttestationResponse ) {
				return new WP_Error( 'Invalid_response', 'AuthenticatorAttestationResponse expected', array( 'status' => 400 ) );
			}

			$this->authenticator_attestation_response = $authenticator_attestation_response;

			return new WP_REST_Response( array( 'message' => 'Success' ), 200 );
		} catch ( JsonException | Exception $e ) {
			return new WP_Error( 'Invalid_response', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( Throwable $e ) {
			return new WP_Error( 'Invalid_response', $e->getMessage(), array( 'status' => 400 ) );
		}
	}
}
