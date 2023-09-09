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
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialSource;
use WP_User;
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
	 * @var PublicKeyCredentialCreationOptions|null $public_key_credential_creation_options
	 */
	public readonly ?PublicKeyCredentialCreationOptions $public_key_credential_creation_options;
	/**
	 * The authenticator attestation response validator.
	 *
	 * @var AuthenticatorAttestationResponseValidator $authenticator_attestation_response_validator
	 */
	public readonly AuthenticatorAttestationResponseValidator $authenticator_attestation_response_validator;
	/**
	 * @var PublicKeyCredentialLoader $public_key_credential_loader
	 */
	public readonly PublicKeyCredentialLoader $public_key_credential_loader;
	/**
	 * @var AttestationObjectLoader $attestation_object_loader
	 */
	public readonly AttestationObjectLoader $attestation_object_loader;
	/**
	 * @var AttestationStatementSupportManager $attestation_statement_support_manager
	 */
	public readonly AttestationStatementSupportManager $attestation_statement_support_manager;

	public readonly WP_User $user;

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
	public function store_public_key_credential_source( PublicKeyCredentialSource $public_key_credential_source ): void {
		try {
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
			$challenge                        = random_bytes( 32 );
			$algorithm_manager                = Algorithm_Manager::instance();
			$algorithm_manager_keys           = $algorithm_manager->get_algorithm_identifiers();
			$public_key_credential_parameters = array();

			foreach ( $algorithm_manager_keys as $algorithm_number ) {
				$public_key_credential_parameters[] = new PublicKeyCredentialParameters(
					'public-key',
					$algorithm_number
				);
			}

			$this->public_key_credential_creation_options = PublicKeyCredentialCreationOptions::create(
				Util::get_rp_entity(),
				Util::get_user_entity( null ),
				$challenge,
				$public_key_credential_parameters,
			)->setTimeout( 30000 )
			->setAuthenticatorSelection( AuthenticatorSelectionCriteria::create() )
			->setAttestation( PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE );

			Session_Handler::instance()->save_session_credential_options(
				$this->public_key_credential_creation_options
			);

			return new WP_REST_Response( $this->public_key_credential_creation_options, 200 );
		} catch ( Exception $e ) {
			throw new RuntimeException( $e->getMessage() );
		}
	}

	/**
	 * Handles the creation response.
	 *
	 * @param WP_REST_Request $request the REST request object.
	 *
	 * @return WP_Error|WP_REST_Response a REST response object with the result.
	 */
	public function response_authenticator(
		WP_REST_Request $request
	): WP_Error|WP_REST_Response {
		try {
			$data                                        = $request->get_body();
			$this->attestation_statement_support_manager = AttestationStatementSupportManager::create();
			$this->attestation_statement_support_manager->add( NoneAttestationStatementSupport::create() );

			$this->attestation_object_loader    = new AttestationObjectLoader( $this->attestation_statement_support_manager );
			$this->public_key_credential_loader = new PublicKeyCredentialLoader( $this->attestation_object_loader );
			$public_key_credential              = $this->public_key_credential_loader->load( $data );
			$authenticator_attestation_response = $public_key_credential->getResponse();
			if ( ! $authenticator_attestation_response instanceof AuthenticatorAttestationResponse ) {
				return new WP_Error( 'Invalid_response', 'AuthenticatorAttestationResponse expected', array( 'status' => 400 ) );
			}
			$this->authenticator_attestation_response = $authenticator_attestation_response;
			$this->store_public_key_credential_source(
				$this->get_validated_credentials(
					$this->authenticator_attestation_response,
					$this->attestation_statement_support_manager,
					ExtensionOutputCheckerHandler::create(),
				)
			);

			return new WP_REST_Response( 'Successfully registered', 200 );
		} catch ( JsonException | Exception $e ) {
			return new WP_Error( 'Invalid_response', $e->getMessage(), array( 'status' => 400 ) );
		} catch ( Throwable $e ) {
			return new WP_Error( 'Invalid_response', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/**
	 * Retrieves the validated credentials.
	 *
	 * @return PublicKeyCredentialSource The validated credentials.
	 * @throws Throwable
	 */
	private function get_validated_credentials(
		AuthenticatorAttestationResponse $authenticator_attestation_response,
		AttestationStatementSupportManager $support_manager,
		ExtensionOutputCheckerHandler $checker_handler
	): PublicKeyCredentialSource {
		$this->authenticator_attestation_response_validator = new AuthenticatorAttestationResponseValidator(
			$support_manager,
			null,
			null,
			$checker_handler,
			null
		);

		$this->public_key_credential_creation_options = Session_Handler::instance()->get_session_credential_options();

		return $this->authenticator_attestation_response_validator->check(
			$authenticator_attestation_response,
			$this->public_key_credential_creation_options,
			Util::get_hostname(),
		);
	}
}
