<?php

namespace WpPasskeys\RestApi;

use ReflectionClass;
use ReflectionException;
use WpPasskeys\Credentials\CredentialsEndpointsInterface;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Interfaces\WebAuthnInterface;

/**
 * @property WebAuthnInterface $authEndpoints
 * @property WebAuthnInterface $registerEndpoints
 * @property CredentialsEndpointsInterface $credentialEndpoints
 */
class RestApiHandler extends AbstractApiHandler
{
    private const OPTIONS_CALLBACK = 'createPublicKeyCredentialOptions';
    private const VERIFY_CALLBACK = 'verifyPublicKeyCredentials';
    private const REGISTER_NAMESPACE = '/register';
    private const AUTH_NAMESPACE = '/authenticator';
    private const CREDENTIAL_NAMESPACE = 'creds';
    private WebAuthnInterface $authEndpoints;
    private WebAuthnInterface $registerEndpoints;
    private CredentialsEndpointsInterface $credentialEndpoints;

    public function __construct(
        WebAuthnInterface $authEndpoints,
        WebAuthnInterface $registerEndpoints,
        CredentialsEndpointsInterface $credentialEndpoints
    ) {
        $this->authEndpoints = $authEndpoints;
        $this->registerEndpoints = $registerEndpoints;
        $this->credentialEndpoints = $credentialEndpoints;
    }
    public static function register(RestApiHandler $apiHandler): void
    {
        add_action('rest_api_init', [$apiHandler, 'registerAuthRoutes']);
        SessionHandler::start();
    }

    public function registerAuthRoutes(): void
    {
        // Register routes
        $this->registerRoute(
            self::REGISTER_NAMESPACE . '/options',
            'GET',
            [$this->registerEndpoints, self::OPTIONS_CALLBACK]
        );
        $this->registerRoute(
            self::REGISTER_NAMESPACE . '/verify',
            'POST',
            [$this->registerEndpoints,
                self::VERIFY_CALLBACK
            ]
        );
        // Auth routes
        $this->registerRoute(
            self::AUTH_NAMESPACE . '/options',
            'GET',
            [$this->authEndpoints, self::OPTIONS_CALLBACK
            ]
        );
        $this->registerRoute(
            self::AUTH_NAMESPACE . '/verify',
            'POST',
            [$this->authEndpoints, self::VERIFY_CALLBACK
            ]
        );
        // Other
        $this->registerRoute(
            self::CREDENTIAL_NAMESPACE . '/user',
            'POST',
            [$this->credentialEndpoints, 'setUserCredentials'
            ]
        );
        $this->registerRoute(
            self::CREDENTIAL_NAMESPACE . '/user/remove',
            'DELETE',
            [$this->credentialEndpoints, 'removeUserCredentials'],
            fn() => current_user_can('read')
        );
    }

    /**
     * @throws ReflectionException
     * TODO: Refactor to use Reflection for getting endpoint name instead of hardcoding at the start of the class.
     */
    private function getEndpointCallbacks(): string
    {
        $endpointClasses = [
            $this->credentialEndpoints::class,
            $this->authEndpoints::class,
            $this->registerEndpoints::class,
        ];

        foreach ($endpointClasses as $endpointClass) {
            $reflection = new ReflectionClass($endpointClass);
            $endpoints = $reflection->getMethods();
        }

        return '';
    }
}
