<?php

namespace WpPasskeys\RestApi;

use ReflectionClass;
use ReflectionException;
use WpPasskeys\Credentials\CredentialEndpointsInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\Ceremonies\AuthEndpointsInterface;
use WpPasskeys\Ceremonies\RegisterEndpointsInterface;

/**
 * @property AuthEndpointsInterface $authEndpoints
 * @property RegisterEndpointsInterface $registerEndpoints
 * @property CredentialEndpointsInterface $credentialEndpoints
 * @property SessionHandlerInterface $sessionHandler
 */
class RestApiHandler extends AbstractApiHandler
{
    private const OPTIONS_CALLBACK = 'createPublicKeyCredentialOptions';
    private const VERIFY_CALLBACK = 'verifyPublicKeyCredentials';


    public function __construct(
        private readonly AuthEndpointsInterface $authEndpoints,
        private readonly RegisterEndpointsInterface $registerEndpoints,
        private readonly CredentialEndpointsInterface $credentialEndpoints,
        private readonly SessionHandlerInterface $sessionHandler,
    ) {
        add_action('rest_api_init', [$this, 'registerAuthRoutes']);
        add_action('init', [$this, 'initSession']);
    }

    public function initSession(): void
{
    $this->sessionHandler->start();
}

    public function registerAuthRoutes(): void
    {
        // Endpoint: /wp-json/wp-passkeys/register/options
        $this->registerRoute(
            '/register/options',
            'GET',
            [$this->registerEndpoints, self::OPTIONS_CALLBACK]
        );

        // Endpoint: /wp-json/wp-passkeys/register/verify
        $this->registerRoute(
            '/register/verify',
            'POST',
            [
                $this->registerEndpoints,
                self::VERIFY_CALLBACK,
            ]
        );

        // Endpoint: /wp-json/wp-passkeys/authenticator/options
        $this->registerRoute(
            '/authenticator/options',
            'GET',
            [
                $this->authEndpoints,
                self::OPTIONS_CALLBACK,
            ]
        );

        // Endpoint: /wp-json/wp-passkeys/authenticator/verify
        $this->registerRoute(
            '/authenticator/verify',
            'POST',
            [
                $this->authEndpoints,
                self::VERIFY_CALLBACK,
            ]
        );

        // Endpoint: /wp-json/wp-passkeys/register/user/email
        $this->registerRoute(
            '/register/user/email',
            'GET',
            [$this->registerEndpoints, 'userEmailConfirmation']
        );

        // Endpoint: /wp-json/wp-passkeys/creds/user
        $this->registerRoute(
            '/creds/user',
            'GET',
            [
                $this->credentialEndpoints,
                'getUserCredentials',
            ]
        );

        // Endpoint: /wp-json/wp-passkeys/creds/user
        $this->registerRoute(
            '/creds/user',
            'POST',
            [
                $this->credentialEndpoints,
                'setUserCredentials',
            ]
        );

        // Endpoint: /wp-json/wp-passkeys/creds/user/remove/{id}
        $this->registerRoute(
            '/creds/user/remove/(?P<id>[^/]+)',
            'DELETE',
            [$this->credentialEndpoints, 'removeUserCredentials'],
            fn() => current_user_can('read')
        );
    }
}
