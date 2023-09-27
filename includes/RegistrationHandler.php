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
use WpPasskeys\Interfaces\WebAuthnInterface;
use WpPasskeys\Traits\SingletonTrait;
use WpPasskeys\utilities as Util;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registration Handler for WP Pass Keys.
 */
class RegistrationHandler implements WebAuthnInterface
{
    public readonly AuthenticatorAttestationResponse $authenticatorAttestationResponse;
    public readonly ?PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;
    public readonly AuthenticatorAttestationResponseValidator $authenticatorAttestationResponseValidator;
    public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader;
    public readonly AttestationObjectLoader $attestationObjectLoader;
    public readonly AttestationStatementSupportManager $attestationStatementSupportManager;
    public const API_NAMESPACE = '/register';

    /**
     * Stores the public key credential source.
     *
     * @throws RuntimeException If the AuthenticatorAttestationResponse is missing.
     * @throws RuntimeException|Throwable If failed to store the credential source.
     */
    public function storePublicKeyCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        try {
            $credentialHelper = CredentialHelper::instance();
            $credentialHelper->createUserWithPkCredentialId($publicKeyCredentialSource->publicKeyCredentialId);
            $credentialHelper->saveCredentialSource($publicKeyCredentialSource);
        } catch (Exception $e) {
            throw new CredentialException($e->getMessage());
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
            $challenge                        = base64_encode(random_bytes(32));
            $algorithmManager                = AlgorithmManager::instance();
            $algorithmManagerKeys           = $algorithmManager->getAlgorithmIdentifiers();
            $publicKeyCredentialParameters = array();
            $userLogin = SessionHandler::instance()->get('user_login');

            foreach ($algorithmManagerKeys as $algorithmNumber) {
                $publicKeyCredentialParameters[] = new PublicKeyCredentialParameters(
                    'public-key',
                    $algorithmNumber
                );
            }

            $this->publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
                Util::createRpEntity(),
                Util::createUserEntity($userLogin),
                $challenge,
                $publicKeyCredentialParameters,
            )->setTimeout(30000)
            ->setAuthenticatorSelection(AuthenticatorSelectionCriteria::create())
            ->setAttestation(PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE);

            CredentialHelper::instance()->saveSessionCredentialOptions(
                $this->publicKeyCredentialCreationOptions
            );

            return new WP_REST_Response($this->publicKeyCredentialCreationOptions, 200);
        } catch (Exception $e) {
            throw new ($e->getMessage());
        }
    }

    /**
     * Handles the creation response.
     *
     * @param WP_REST_Request $request the REST request object.
     *
     * @return WP_Error|WP_REST_Response a REST response object with the result.
     */
    public function verifyPublicKeyCredentials(
        WP_REST_Request $request
    ): WP_Error|WP_REST_Response {
        try {
            $data                                        = $request->get_body();
            $this->attestationStatementSupportManager = AttestationStatementSupportManager::create();
            $this->attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());

            $this->attestationObjectLoader    = new AttestationObjectLoader($this->attestationStatementSupportManager);
            $this->publicKeyCredentialLoader = new PublicKeyCredentialLoader($this->attestationObjectLoader);
            $publicKeyCredential              = $this->publicKeyCredentialLoader->load($data);
            $authenticatorAttestationResponse = $publicKeyCredential->getResponse();
            if (! $authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) {
                return new WP_Error('400', 'Invalid AuthenticatorAttestationResponse');
            }
            $this->authenticatorAttestationResponse = $authenticatorAttestationResponse;
            $this->storePublicKeyCredentialSource(
                $this->getPublicKeyCredentials(
                    $this->authenticatorAttestationResponse,
                    $this->attestationStatementSupportManager,
                    ExtensionOutputCheckerHandler::create(),
                )
            );

            $response = new WP_REST_Response([
                'status' => 'Verified',
                'statusText' => 'Your account has been created. You are being redirect now to dashboard...',
                'redirectUrl' => get_admin_url(),
            ], 200);
        } catch (JsonException $e) {
            $response = new WP_Error(400, $e->getMessage());
        } catch (Exception $e) {
            $response = new WP_Error(500, $e->getMessage());
        } catch (Throwable $e) {
            $response = new WP_Error(500, $e->getMessage());
        }

        return $response;
    }

    /**
     * Retrieves the validated credentials.
     *
     * @return PublicKeyCredentialSource The validated credentials.
     * @throws Throwable
     */
    private function getPublicKeyCredentials(
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

        $this->publicKeyCredentialCreationOptions = CredentialHelper::instance()->getSessionCredentialOptions();

        return $this->authenticatorAttestationResponseValidator->check(
            $authenticatorAttestationResponse,
            $this->publicKeyCredentialCreationOptions,
            Util::getHostname(),
            ['localhost']
        );
    }

    public function setUserLogin(WP_REST_Request $request): WP_Error | WP_REST_Response
    {
        if (empty($request->get_params())) {
            return new WP_Error(400, 'No parameters have been passed');
        }
        $userLogin = $request->get_param('name');

        $user = get_user_by('login', $userLogin);

        if ($user) {
            SessionHandler::instance()->set('user_id', $user->ID);
            return new WP_REST_Response([
                'isExistingUser' => true,
            ], 200);
        }

        SessionHandler::instance()->set('user_login', sanitize_text_field($userLogin));

        return new WP_REST_Response(
            [
                'isExistingUser' => false
            ],
            200
        );
    }
}
