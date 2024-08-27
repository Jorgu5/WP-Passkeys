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
use InvalidArgumentException;
use JsonException;
use Random\RandomException;
use Throwable;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;
use WP_REST_Request;
use WP_REST_Response;
use WpOrg\Requests\Exception\InvalidArgument;
use WpPasskeys\Admin\UserPasskeysCardRender;
use WpPasskeys\Credentials\CredentialEntityInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\UsernameHandler;
use WpPasskeys\Exceptions\InsertUserException;
use WpPasskeys\Exceptions\InvalidCredentialsException;
use WpPasskeys\Utilities;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

class RegisterEndpoints implements RegisterEndpointsInterface
{
    private array $response = []; // Inicjalizacja właściwości $response

    public function __construct(
        public readonly AuthenticatorAttestationResponseValidator $authenticatorAttestationResponseValidator,
        public readonly CredentialHelperInterface $credentialHelper,
        public readonly CredentialEntityInterface $credentialEntity,
        public readonly Utilities $utilities,
        public readonly UsernameHandler $usernameHandler,
        public readonly PublicKeyCredentialParameters $publicKeyCredentialParameters,
        public readonly UserPasskeysCardRender $userPasskeysCardRender,
        public readonly EmailConfirmation $emailConfirmation,
        public readonly WebauthnSerializerFactory $serializer,
    ) {
    }

    /**
     * @throws Exception
     */
    public function createPublicKeyCredentialOptions(): WP_REST_Response
    {
        try {
            ['user_login' => $username, 'user_email' => $email] = $this->usernameHandler->getOrCreateUserData();

            $publicKeyCredentialCreationOptions = $this->creationOptions($username);
            $publicKeyCredentialCreationOptions = $this->serializer->create()->serialize($publicKeyCredentialCreationOptions, 'json');
            $this->credentialHelper->saveSessionCredentialOptions($publicKeyCredentialCreationOptions);

            $this->response = [
                'code'    => 200,
                'message' => 'Passkeys credentials options created successfully.',
                'data'    => [
                    'credentials' => $publicKeyCredentialCreationOptions,
                    'email'       => $email,
                    'username'    => $username,
                ],
            ];
        } catch (Exception $e) {
            $this->utilities->handleException($e);
            $this->response = [
                'code'    => 500,
                'message' => 'An error occurred while creating credentials options.',
                'data'    => [],
            ];
        }

        // Return a WP_REST_Response with the prepared response.
        return new WP_REST_Response($this->response, $this->response['code']);
    }


    /**
     * @throws Exception
     */
    public function creationOptions(string $userLogin): PublicKeyCredentialCreationOptions
    {
        $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
            $this->credentialEntity->createRpEntity(),
            $this->credentialEntity->createUserEntity($userLogin),
            $this->getChallenge(),
            $this->publicKeyCredentialParameters->get(),
        );

        $publicKeyCredentialCreationOptions->timeout                = $this->getTimeout();
        $publicKeyCredentialCreationOptions->authenticatorSelection = AuthenticatorSelectionCriteria::create(
            AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            true
        );

        return $publicKeyCredentialCreationOptions;
    }

    /**
     * @throws RandomException
     */
    public function getChallenge(): string
    {
        return random_bytes(32);
    }

    public function getTimeout(): int
    {
        return (int)get_option('wppk_passkeys_timeout', 30000);
    }

    public function verifyPublicKeyCredentials(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $publicKeyCredentials = $request->get_body();
            $publicKeyCredentialsId = json_decode($publicKeyCredentials, false, 512, JSON_THROW_ON_ERROR)->id;
            $publicKeyCredentialSource = $this->validateAuthenticatorAttestationResponse($publicKeyCredentials);
            $email                 = (string)$request->get_param('email');
            $username              = (string)$request->get_param('username');

            $this->credentialHelper->saveCredentialSource($publicKeyCredentialSource, $publicKeyCredentialsId, $email);

            // If the email is empty, immediately handle user creation.
            if (empty($email)) {
                $this->response = $this->handleUserCreation($publicKeyCredentialsId);

                return new WP_REST_Response($this->response, $this->response['code']);
            }

            // Check if the email exists in the system already and handle accordingly.
            if (email_exists($email)) {
                $this->response = [
                    'code'    => 409,
                    'message' => 'Email already exists. If you want to update your passkeys, please login and go to your profile or use "forgot password".',
                ];

                return new WP_REST_Response($this->response, 409);
            }

            if (username_exists($username)) {
                $this->response = [
                    'code'    => 409,
                    'message' => 'Username already exists. Please choose a different one.',
                ];

                return new WP_REST_Response($this->response, 409);
            }

            // For a new email, proceed with sending a confirmation.
            $this->emailConfirmation->sendConfirmationEmail($email);
            $this->response = [
                'code'    => 202,
                'message' => 'Your passkey registration has started successfully. An email has been sent to your address with a confirmation link. Please check your inbox (and spam folder) and click on the link.',
                'data'    => ['email' => $email],
            ];

            return new WP_REST_Response($this->response, 202);
        } catch (JsonException | InvalidCredentialsException | InsertUserException $e) {
            return $this->utilities->handleException($e, $e->getCode());
        } catch (InvalidArgument $e) {
            return $this->utilities->handleException($e, 'Invalid Argument');
        } catch (Throwable $e) {
            if ($e->getMessage() === 'Invalid scheme. HTTPS required.') {
                return $this->utilities->handleException($e, 426);
            }

            return $this->utilities->handleException($e);
        }
    }

    /**
     * @throws Throwable
     * @throws InvalidCredentialsException
     * @throws JsonException
     */
    public function validateAuthenticatorAttestationResponse(string|PublicKeyCredential $publicKeyCredential): PublicKeyCredentialSource
    {
        $publicKeyCredentialJSON = $this->getPublicKeyCredential($publicKeyCredential);
        $publicKeyCredentialCreationOptions = $this->credentialHelper->getSessionCredentialOptions();

        if ($publicKeyCredentialCreationOptions === null) {
            throw new InvalidCredentialsException('Credential options not found in session.');
        }

        return $this->authenticatorAttestationResponseValidator->check(
            $this->getAuthenticatorAttestationResponse($publicKeyCredentialJSON),
            $publicKeyCredentialCreationOptions,
            $this->utilities->getHostname(),
        );
    }

    public function getPublicKeyCredential(string $data): PublicKeyCredential
    {
        $publicKeyCredential = $this->serializer->create()->deserialize(
            $data,
            PublicKeyCredential::class,
            'json'
        );

        if (! $publicKeyCredential instanceof PublicKeyCredential) {
            throw new InvalidArgumentException("Invalid public key credential");
        }

        return $publicKeyCredential;
    }

    public function getAuthenticatorAttestationResponse(
        PublicKeyCredential $publicKeyCredential
    ): AuthenticatorAttestationResponse {
        if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new InvalidArgumentException("Invalid attestation response");
        }

        return $publicKeyCredential->response;
    }

    /**
     * @throws Exception
     */
    public function handleUserCreation(
        string $pkCredentialId,
    ): array {
        $userId = $this->credentialHelper->updateOrCreateUser(
            $pkCredentialId
        );

        if (is_wp_error($userId)) {
            return $this->utilities->handleWpError($userId);
        }

        $this->utilities->setAuthCookie(
            get_user_by('id', $userId)->user_login,
            $userId
        );

        return [
            'code'    => 200,
            'message' => $this->registerSuccessMessage(),
            'data'    => [
                'redirectUrl' => $this->utilities->getRedirectUrl(),
                'cardHtml'    => $this->userPasskeysCardRender->renderPasskeyCard(
                    $pkCredentialId
                ) ?? '',
            ],
        ];
    }

    public function registerSuccessMessage(): string
    {
        if (is_user_logged_in()) {
            return 'You have registered your passkeys successfully. Now you can use it to login instead of password.';
        }

        return 'Your account has been created. You are being redirect now to dashboard...';
    }

    public function getRedirectUrl(): string
    {
        return ! is_user_logged_in() ? $this->utilities->getRedirectUrl() : '';
    }

    /**
     * @throws Exception
     */
    public function userEmailConfirmation(WP_REST_Request $request): WP_REST_Response
    {
        // Start output buffering
        ob_start();

        try {
            $email = sanitize_email($request->get_param('email'));
            $token = sanitize_text_field($request->get_param('pkEmailToken'));

            if ($this->emailConfirmation->confirmUserEmail($email, $token)) {
                $pkCredentialId = $this->credentialHelper->findCredentialIdByEmail($email);
                $this->response = $this->handleUserCreation($pkCredentialId);
            } else {
                $this->response = [
                    'code'    => 401,
                    'message' => 'Invalid token, probably expired or already used, ' .
                                 'please try to create account again. If the problem persists, contact support.',
                    'data'    => [],
                ];
            }
        } catch (Throwable $e) {
            // Handle any exceptions using the utilities method
            $this->response = $this->utilities->handleException($e, $e->getCode())->get_data();
        }

        // Get the output buffer content and clean the buffer
        $bufferContent = ob_get_clean();

        // Log any buffered content if it contains deprecation warnings or other notices
        if (! empty($bufferContent)) {
            $this->utilities->logger($bufferContent);
        }

        return new WP_REST_Response($this->response, $this->response['code']);
    }
}
