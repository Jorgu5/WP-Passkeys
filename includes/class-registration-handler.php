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
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registration Handler for WP Pass Keys.
 */
class Registration_Handler {

	use SingletonTrait;

	private const NAMESPACE = 'wp-passkeys/v1';
	private const DOMAIN    = 'localhost';

	/**
	 * The authenticator attestation response.
	 *
	 * @var AuthenticatorAttestationResponse|null
	 */
	private ?AuthenticatorAttestationResponse $authenticator_attestation_response = null;
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
	private mixed $authenticator_attestation_response_validator;
	/**
	 * The public key credential loader.
	 *
	 * @var mixed
	 */
	private mixed $public_key_credential_loader;

	/**
	 * Initializes a new instance of the class.
	 *
	 * @param mixed $public_key_credential_loader The public key credential loader.
	 * @param mixed $authenticator_attestation_response_validator The authenticator attestation response validator.
	 */
	public function __construct(
		mixed $public_key_credential_loader,
		mixed $authenticator_attestation_response_validator
	) {
		$this->authenticator_attestation_response_validator = $authenticator_attestation_response_validator;
		$this->public_key_credential_loader                 = $public_key_credential_loader;
		$this->register_routes();
	}

	/**
	 * Register the routes for the API.
	 *
	 * @return void
	 */
	private function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/startRegistration',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'create_public_key_credential_creation_options' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/verifyCreation',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'handle_creation_response' ),
			)
		);
	}

	/**
	 * Stores the public key credential source.
	 *
	 * @throws RuntimeException If the AuthenticatorAttestationResponse is missing.
	 * @throws RuntimeException If failed to store the credential source.
	 */
	public function store_public_key_credential_source(): void {
		if ( ! $this->authenticator_attestation_response ) {
			throw new RuntimeException( 'Failed to store: AuthenticatorAttestationResponse is missing.' );
		}

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
	public function create_public_key_credential_creation_options( WP_REST_Request $request
	): WP_REST_Response {
		// TODO: Pass the valid data to Entities.
		try {
			$rp_entity   = PublicKeyCredentialRpEntity::create( 'foo', 'bar', null );
			$user_entity = PublicKeyCredentialUserEntity::create(
				'foo',
				'bar',
				'foo',
				null
			);

			$challenge = random_bytes( 16 );

			$public_key_credential_parameters_list = array(
				// TODO: Check what parameters we can use here and add it.
			);

			$this->public_key_credential_creation_options = PublicKeyCredentialCreationOptions::create(
				$rp_entity,
				$user_entity,
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
	public function handle_creation_response(
		WP_REST_Request $request
	): WP_REST_Response|WP_Error {
		// format $request properly to fit into $data.
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
		}
	}
}
