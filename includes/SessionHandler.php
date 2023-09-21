<?php

namespace WpPasskeys;

use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialCreationOptions;
use WpPasskeys\traits\SingletonTrait;

class SessionHandler
{
    use SingletonTrait;

    public function set($key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get($key)
    {
        return $_SESSION[$key] ?? null;
    }

    public function has($key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove($key): void
    {
        unset($_SESSION[$key]);
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function destroy(): void
    {
        session_destroy();
    }
}
