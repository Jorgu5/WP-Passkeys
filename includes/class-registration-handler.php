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
use WpPasskeys\Interfaces\Authentication;
use WpPasskeys\Traits\Singleton;
use WpPasskeys\utilities as Util;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registration Handler for WP Pass Keys.
 */
class Registration_Handler implements Authentication {

    use Singleton;

	/**
	 * The authenticator attestation response.
	 *
	 * @var AuthenticatorAttestationResponse
	 */
	public readonly AuthenticatorAttestationResponse $authenticator_attestation_response;
	/**
	 * The public key credential creation options.
	 *
	 * @var PublicKeyCredentialCreationOptions|null
	 */
	public readonly ?PublicKeyCredentialCreationOptions $public_key_credential_creation_options;
	/**
	 * The authenticator attestation response validator.
	 *
	 * @var mixed
	 */
	public readonly AuthenticatorAttestationResponseValidator $authenticator_attestation_response_validator;
	/**
	 * @var mixed
	 */
	public readonly PublicKeyCredentialLoader $public_key_credential_loader;

    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_auth_routes' ) );
    }

	/**
	 * Register the routes for the API.
	 *
	 * @return void
	 */
	public function register_auth_routes(): void {
		register_rest_route(
            WP_PASSKEYS_API_NAMESPACE . '/register',
			'/start',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'create_public_key_credential_options' ),
			)
		);

		register_rest_route(
            WP_PASSKEYS_API_NAMESPACE . '/register',
			'/authenticate',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'response_authenticator' ),
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
				get_site_url()
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
		try {
			$challenge                                    = random_bytes( 32 );
			$public_key_credential_parameters_list        = array(
				'type' => 'public-key',
				'alg'  => -7,
			);
			$this->public_key_credential_creation_options = PublicKeyCredentialCreationOptions::create(
				Util::get_rp_entity(),
				Util::get_user_entity( null ),
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
		// TODO: Format $response properly to fit into $data.
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
