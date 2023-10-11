<?php

/**
 * Registration Handler for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since 1.0.0
 * @version 1.0.0
 */

declare(strict_types=1);

namespace WpPasskeys\Ceremonies;

use Exception;
use JsonException;
use RuntimeException;
use Throwable;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WpPasskeys\AlgorithmManager\AlgorithmManager;
use WpPasskeys\Credentials\CredentialEntityInterface;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Interfaces\WebAuthnInterface;
use WpPasskeys\Utilities as Util;

/**
 * Registration Handler for WP Pass Keys.
 */
class RegisterEndpoints implements WebAuthnInterface
{
    public readonly AuthenticatorAttestationResponse $authenticatorAttestationResponse;
    public readonly ?PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;
    public readonly PublicKeyCredentialLoader $publicKeyCredentialLoader;
    public readonly AttestationObjectLoader $attestationObjectLoader;
    public readonly AttestationStatementSupportManager $attestationStatementSupportManager;
    public readonly CredentialHelperInterface $credentialHelper;
    public readonly CredentialEntityInterface $credentialEntity;

    public function __construct(
        CredentialHelperInterface $credentialHelper,
        CredentialEntityInterface $credentialEntity
    ) {
        $this->credentialHelper = $credentialHelper;
        $this->credentialEntity = $credentialEntity;
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
            $algorithmManager = new AlgorithmManager();
            $challenge                        = base64_encode(random_bytes(32));
            $algorithmManagerKeys           = $algorithmManager->getAlgorithmIdentifiers();
            $publicKeyCredentialParameters = array();
            $userLogin = SessionHandler::get('user_data')['user_login'];

            foreach ($algorithmManagerKeys as $algorithmNumber) {
                $publicKeyCredentialParameters[] = new PublicKeyCredentialParameters(
                    'public-key',
                    $algorithmNumber
                );
            }

            $this->publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
                $this->credentialEntity->createRpEntity(),
                $this->credentialEntity->createUserEntity($userLogin),
                $challenge,
                $publicKeyCredentialParameters,
            );

            $this->publicKeyCredentialCreationOptions->timeout = (int)get_option('wppk_passkeys_timeout', '30000');
            $this->publicKeyCredentialCreationOptions->authenticatorSelection = AuthenticatorSelectionCriteria::create(
                AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
                true
            );

            $this->publicKeyCredentialCreationOptions->attestation =
                PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE;

            $this->credentialHelper->saveSessionCredentialOptions(
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
            $authenticatorAttestationResponse = $publicKeyCredential->response;
            if (! $authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) {
                wp_redirect(wp_login_url());
            }
            $this->authenticatorAttestationResponse = $authenticatorAttestationResponse;

            $this->credentialHelper->storePublicKeyCredentialSource(
                $this->credentialHelper->getPublicKeyCredentials(
                    $this->authenticatorAttestationResponse,
                    $this->attestationStatementSupportManager,
                    ExtensionOutputCheckerHandler::create(),
                )
            );

            $redirectUrl = !is_user_logged_in() ? Util::getRedirectUrl() : '';

            Util::setAuthCookie(
                SessionHandler::get('user_data')['user_login'],
                null
            );

            $response = new WP_REST_Response([
                'code' => 'verified',
                'message' => 'Your account has been created. You are being redirect now to dashboard...',
                'data' => [
                    'redirectUrl' => $redirectUrl,
                    'pk_credential_id' => $publicKeyCredential->id,
                ]
            ], 200);
        } catch (JsonException $e) {
            $response = new WP_Error('json', $e->getMessage());
        } catch (CredentialException $e) {
            $response = new WP_Error('credential-error', $e->getMessage());
        } catch (Throwable $e) {
            $response = new WP_Error('server', $e->getMessage());
        }

        return $response;
    }
}
