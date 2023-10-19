<?php

/**
 * Credential Helper for WP Pass Keys.
 *
 * @package WpPassKeys
 * @since   1.0.0
 * @version 1.0.0
 */

namespace WpPasskeys\Credentials;

use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use WP_Error;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Utilities;

class CredentialHelper implements CredentialHelperInterface, PublicKeyCredentialSourceRepository
{
    public readonly SessionHandlerInterface $sessionHandler;
    public function __construct(
        SessionHandlerInterface $sessionHandler
    ) {
        $this->sessionHandler = $sessionHandler;
    }

    public readonly PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions;

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        global $wpdb;

        $credentialSource = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT credential_source FROM wp_pk_credential_sources WHERE pk_credential_id = %s",
                $publicKeyCredentialId
            )
        );

        if (empty($credentialSource)) {
            return null;
        }

        $data = json_decode($credentialSource, true, 512, JSON_THROW_ON_ERROR);


        return PublicKeyCredentialSource::createFromArray($data);
    }


    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $username = $publicKeyCredentialUserEntity->name;
        $credentialSource = $this->getUserPublicKeySources($username);
        $publicKeySource = PublicKeyCredentialSource::createFromArray($credentialSource);
        $credentialDescriptorsStore[] = $publicKeySource->getPublicKeyCredentialDescriptor();

        return $credentialDescriptorsStore;
    }


    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        global $wpdb;

        $safeEncodedPkId = Utilities::safeEncode($publicKeyCredentialSource->publicKeyCredentialId);

        if ($this->findOneByCredentialId($safeEncodedPkId)) {
            return;
        }
        // Insert only the credential source into the custom table wp_pk_credential_sources
        $wpdb->insert('wp_pk_credential_sources', [
            'pk_credential_id' => $safeEncodedPkId,
            'credential_source' => json_encode($publicKeyCredentialSource, JSON_THROW_ON_ERROR)
        ], ['%s']);

        // Check if the insert was successful, throw exception otherwise
        if (!$wpdb->insert_id) {
            throw new CredentialException('Failed to save credential source.');
        }
    }

    public function createUserWithPkCredentialId(string $publicKeyCredentialId): void
    {
        $userData = UsernameHandler::userData();
        $userData['meta_input'] = [
            'pk_credential_id' => Utilities::safeEncode($publicKeyCredentialId)
        ];

        $addUser = wp_insert_user($userData);

        // TODO: Take adding passkeys from the dashboard to a different method.
        if (is_wp_error($addUser)) {
            if ($addUser->get_error_code() === 'existing_user_login') {
                $userId     = $this->getExistingUserId($userData['user_login']);
                if (is_wp_error($userId)) {
                    throw new CredentialException($userId->get_error_message());
                }
                $user['ID'] = $userId;
                $addUser    = wp_insert_user(
                    $user
                );
            }
            throw new CredentialException($addUser->get_error_message());
        }
    }

    /**
     * @throws \JsonException
     */
    public function saveSessionCredentialOptions(
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions
    ): void {
        $this->sessionHandler->start();
        $this->sessionHandler->set(
            'webauthn_credential_options',
            json_encode($publicKeyCredentialCreationOptions, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @throws \JsonException
     */
    public function getSessionCredentialOptions(): ?PublicKeyCredentialCreationOptions
    {
        if ($this->sessionHandler->has('webauthn_credential_options')) {
            return PublicKeyCredentialCreationOptions::createFromString(
                $this->sessionHandler->get('webauthn_credential_options')
            );
        }
        return null;
    }

    /**
     * Retrieves public key credential sources for a specific user.
     *
     * @param string $username
     *
     * @return array An array of credential sources.
     * @throws \JsonException
     * @throws CredentialException
     * @throws InvalidDataException
     */
    protected function getUserPublicKeySources(string $username): array
    {
        $user = get_user_by('login', $username);

        if (!$user) {
            throw new CredentialException('User not found.');
        }

        $pkCredentialId = get_user_meta($user->ID, 'pk_credential_id', true);

        if (!$pkCredentialId) {
            throw new CredentialException('No credential ID found for user.');
        }

        $credentialSource = $this->findOneByCredentialId($pkCredentialId);

        return json_decode($credentialSource, true, 512, JSON_THROW_ON_ERROR);
    }

    public function getUserByCredentialId(string $pkCredentialId): int
    {
        global $wpdb;

        $user = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM wp_usermeta WHERE meta_key = 'pk_credential_id' AND meta_value = %s",
                $pkCredentialId
            )
        );

        if (!$user) {
            throw new CredentialException('There is no user with this credential ID.');
        }

        return $user;
    }

    protected function getExistingUserId($username): int | WP_Error
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'unauthorized',
                'You have to login to add a credential to the existing account.',
                ['status' => 401]
            );
        }
        if (!wp_verify_nonce($_POST['wp_passkeys_nonce'], 'wp_passkeys_nonce')) {
            return new WP_Error(
                'forbidden',
                'Adding credentials failed, nonce is not correct, please refresh the page and try again.',
                ['status' => 403
                ]
            );
        }

        return get_user_by('login', $username)->ID;
    }

    public function getPublicKeyCredentials(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        AttestationStatementSupportManager $supportManager,
        ExtensionOutputCheckerHandler $checkerHandler
    ): PublicKeyCredentialSource {
        $authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
            $supportManager,
            null,
            null,
            $checkerHandler,
            null
        );

        $publicKeyCredentialCreationOptions = $this->getSessionCredentialOptions();

        return $authenticatorAttestationResponseValidator->check(
            $authenticatorAttestationResponse,
            $publicKeyCredentialCreationOptions,
            Utilities::getHostname(),
            ['localhost']
        );
    }

    public function storePublicKeyCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $this->createUserWithPkCredentialId($publicKeyCredentialSource->publicKeyCredentialId);
        $this->saveCredentialSource($publicKeyCredentialSource);
    }

    public function getUserLogin(): string
    {
        $userData = $this->sessionHandler->get('user_data');
        $userLogin = '';
        if (is_array($userData) && isset($userData['user_login'])) {
            $userLogin = $userData['user_login'];
        }

        return (string)$userLogin;
    }
}
