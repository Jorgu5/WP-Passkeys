<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class RestApiHandler extends AbstractApiHandler
{
    private RegistrationHandler $reg;
    private AuthenticationHandler $auth;
    private const OPTIONS_CALLBACK = 'createPublicKeyCredentialOptions';
    private const VERIFY_CALLBACK = 'verifyPublicKeyCredentials';
    private const REGISTER_NAMESPACE = '/register';
    private const AUTH_NAMESPACE = '/authenticator';
    private const CREDENTIAL_NAMESPACE = 'creds';
    private CredentialsApi $creds;

    public function __construct(
        AuthenticationHandler $auth,
        RegistrationHandler $reg,
        CredentialsApi $creds
    ) {
        $this->auth = $auth;
        $this->reg = $reg;
        $this->creds = $creds;
    }

    public function init(): void
    {
        SessionHandler::instance()->start();
        add_action('rest_api_init', [$this, 'registerAuthRoutes']);
    }

    public function registerAuthRoutes(): void
    {
        // Register routes
        $this->registerRoute(self::REGISTER_NAMESPACE . '/options', 'GET', [$this->reg, self::OPTIONS_CALLBACK]);
        $this->registerRoute(self::REGISTER_NAMESPACE . '/verify', 'POST', [$this->reg, self::VERIFY_CALLBACK]);
        // Auth routes
        $this->registerRoute(self::AUTH_NAMESPACE . '/options', 'GET', [$this->auth, self::OPTIONS_CALLBACK]);
        $this->registerRoute(self::AUTH_NAMESPACE . '/verify', 'POST', [$this->auth, self::VERIFY_CALLBACK]);
        // Other
        $this->registerRoute(self::CREDENTIAL_NAMESPACE . '/user/set', 'POST', [$this->creds, 'setUserLogin']);
        $this->registerRoute(
            self::CREDENTIAL_NAMESPACE . '/user/remove',
            'DELETE',
            [$this->creds, 'removeUserCredentials'],
            fn() => current_user_can('read')
        );
    }
}
