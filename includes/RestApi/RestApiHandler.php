<?php

namespace WpPasskeys\RestApi;

use ReflectionClass;
use ReflectionException;
use WpPasskeys\Credentials\CredentialEndpointsInterface;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Ceremonies\AuthEndpointsInterface;
use WpPasskeys\Ceremonies\RegisterEndpointsInterface;

/**
 * @property AuthEndpointsInterface $authEndpoints
 * @property RegisterEndpointsInterface $registerEndpoints
 * @property CredentialEndpointsInterface $credentialEndpoints
 */
class RestApiHandler extends AbstractApiHandler
{
    private const OPTIONS_CALLBACK = 'createPublicKeyCredentialOptions';
    private const VERIFY_CALLBACK = 'verifyPublicKeyCredentials';
    private const REGISTER_NAMESPACE = '/register';
    private const AUTH_NAMESPACE = '/authenticator';
    private const CREDENTIAL_NAMESPACE = '/creds';


    public function __construct(
        private readonly AuthEndpointsInterface $authEndpoints,
        private readonly RegisterEndpointsInterface $registerEndpoints,
        private readonly CredentialEndpointsInterface $credentialEndpoints,
    ) {
        add_action('rest_api_init', [$this, 'registerAuthRoutes']);
        (new SessionHandler())->start();
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
            [
                $this->registerEndpoints,
                self::VERIFY_CALLBACK,
            ]
        );
        // Auth routes
        $this->registerRoute(
            self::AUTH_NAMESPACE . '/options',
            'GET',
            [
                $this->authEndpoints,
                self::OPTIONS_CALLBACK,
            ]
        );
        $this->registerRoute(
            self::AUTH_NAMESPACE . '/verify',
            'POST',
            [
                $this->authEndpoints,
                self::VERIFY_CALLBACK,
            ]
        );
        // Other
        $this->registerRoute(
            self::CREDENTIAL_NAMESPACE . '/user',
            'POST',
            [
                $this->credentialEndpoints,
                'setUserCredentials',
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
            $endpoints  = $reflection->getMethods();
        }

        return '';
    }
}
