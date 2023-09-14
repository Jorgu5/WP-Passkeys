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
use WpPasskeys\Interfaces\AuthenticationInterface;
use WpPasskeys\Traits\SingletonTrait;
use WpPasskeys\utilities as Util;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registration Handler for WP Pass Keys.
 */
class RegistrationHandler implements AuthenticationInterface
{
    use SingletonTrait;

    public readonly AuthenticatorAttestationResponse $authenticatorAttestationResponse;
    public readonly ?PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;
    public readonly AuthenticatorAttestationResponseValidator $authenticatorAttestationResponseValidator;
    public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader;
    public readonly AttestationObjectLoader $attestationObjectLoader;
    public readonly AttestationStatementSupportManager $attestationStatementSupportManager;
    public readonly WP_User $user;

    public function init(): void
    {
        add_action('rest_api_init', array( $this, 'registerAuthRoutes' ));
    }

    /**
     * Register the routes for the API.
     *
     * @return void
     */
    public function registerAuthRoutes(): void
    {
        register_rest_route(
            WP_PASSKEYS_API_NAMESPACE . '/register',
            '/start',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'createPublicKeyCredentialOptions' ),
            )
        );

        register_rest_route(
            WP_PASSKEYS_API_NAMESPACE . '/register',
            '/authenticate',
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'responseAuthenticator' ),
            )
        );
    }

    /**
     * Stores the public key credential source.
     *
     * @throws RuntimeException If the AuthenticatorAttestationResponse is missing.
     * @throws RuntimeException|Throwable If failed to store the credential source.
     */
    public function storePublicKeyCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        try {
            $credentialHelper = new CredentialHelper();
            $credentialHelper->saveCredentialSource($publicKeyCredentialSource);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to store credential source: {$e->getMessage()}");
        }
    }

    /**
     * Creates the public key credential creation options.
     *
     * @param WP_REST_Request $request the REST request object.
     * @throws RuntimeException If an error occurs during the process.
     * @return WP_REST_Response A REST response object with the result.
     */
    public function createPublicKeyCredentialOptions(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $challenge                        = random_bytes(16);
            $algorithmManager                = AlgorithmManager::instance();
            $algorithmManagerKeys           = $algorithmManager->getAlgorithmIdentifiers();
            $publicKeyCredentialParameters = array();

            foreach ($algorithmManagerKeys as $algorithmNumber) {
                $publicKeyCredentialParameters[] = new PublicKeyCredentialParameters(
                    'public-key',
                    $algorithmNumber
                );
            }

            $this->publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
                Util::getRpEntity(),
                Util::getUserEntity(null),
                $challenge,
                $publicKeyCredentialParameters,
            )->setTimeout(30000)
            ->setAuthenticatorSelection(AuthenticatorSelectionCriteria::create())
            ->setAttestation(PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE);

            SessionHandler::instance()->saveSessionCredentialOptions(
                $this->publicKeyCredentialCreationOptions
            );

            return new WP_REST_Response($this->publicKeyCredentialCreationOptions, 200);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Handles the creation response.
     *
     * @param WP_REST_Request $request the REST request object.
     *
     * @return WP_Error|WP_REST_Response a REST response object with the result.
     */
    public function responseAuthenticator(
        WP_REST_Request $request
    ): WP_Error|WP_REST_Response {
        try {
            $data                                        = $request->get_body();
            $this->attestationStatementSupportManager = AttestationStatementSupportManager::create();
            $this->attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());

            $this->attestationObjectLoader    = new AttestationObjectLoader($this->attestationStatementSupportManager);
            $this->publicKeyCredentialLoader = new PublicKeyCredentialLoader($this->attestationObjectLoader);
            $publicKeyCredential              = $this->publicKeyCredentialLoader->load($data);
            $authenticator_attestation_response = $publicKeyCredential->getResponse();
            if (! $authenticator_attestation_response instanceof AuthenticatorAttestationResponse) {
                return new WP_Error(
                    'Invalid_response',
                    'AuthenticatorAttestationResponse expected',
                    array( 'status' => 400 )
                );
            }
            $this->authenticatorAttestationResponse = $authenticator_attestation_response;
            $this->storePublicKeyCredentialSource(
                $this->getValidatedCredentials(
                    $this->authenticatorAttestationResponse,
                    $this->attestationStatementSupportManager,
                    ExtensionOutputCheckerHandler::create(),
                )
            );

            return new WP_REST_Response('Successfully registered', 200);
        } catch (JsonException | Exception $e) {
            return new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 400 ));
        } catch (Throwable $e) {
            return new WP_Error('Invalid_response', $e->getMessage(), array( 'status' => 400 ));
        }
    }

    /**
     * Retrieves the validated credentials.
     *
     * @return PublicKeyCredentialSource The validated credentials.
     * @throws Throwable
     */
    private function getValidatedCredentials(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        AttestationStatementSupportManager $supportManager,
        ExtensionOutputCheckerHandler $checkerHandler
    ): PublicKeyCredentialSource {
        $this->authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
            $supportManager,
            null,
            null,
            $checkerHandler,
            null
        );

        $this->publicKeyCredentialCreationOptions = SessionHandler::instance()->getSessionCredentialOptions();

        return $this->authenticatorAttestationResponseValidator->check(
            $authenticatorAttestationResponse,
            $this->publicKeyCredentialCreationOptions,
            Util::getHostname(),
            ['localhost']
        );
    }
}
