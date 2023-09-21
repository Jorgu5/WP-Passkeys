<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class RestApiHandler extends AbstractApiHandler
{
    private RegistrationHandler $reg;
    private AuthenticationHandler $auth;
    private const OPTIONS_CALLBACK = 'createPublicKeyCredentialOptions';
    private const VERIFY_CALLBACK = 'verifyPublicKeyCredentials';

    public function __construct(
        AuthenticationHandler $auth,
        RegistrationHandler $reg
    ) {
        $this->auth = $auth;
        $this->reg = $reg;
    }

    public function init(): void
    {
        SessionHandler::instance()->start();
        add_action('rest_api_init', array($this, 'registerAuthRoutes'));
    }

    public function getNamespace(bool $register = false): string
    {
        if ($register) {
            return $this->reg::API_NAMESPACE;
        }
        return $this->auth::API_NAMESPACE;
    }

    public function registerAuthRoutes(): void
    {
        // Register routes
        $this->registerRoute('/options', 'GET', array($this->reg, self::OPTIONS_CALLBACK), true);
        $this->registerRoute('/verify', 'POST', array($this->reg, self::VERIFY_CALLBACK), true);
        // Auth routes
        $this->registerRoute('/options', 'GET', array($this->auth, self::OPTIONS_CALLBACK));
        $this->registerRoute('/verify', 'POST', array($this->auth, self::VERIFY_CALLBACK));
        // Other
        $this->registerRoute('/user', 'POST', array($this->reg, 'setUserLogin'), true);
    }
}
