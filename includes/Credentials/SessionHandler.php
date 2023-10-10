<?php

namespace WpPasskeys\Credentials;

class SessionHandler implements SessionHandlerInterface
{
    public static function set($key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get($key)
    {
        return $_SESSION[$key] ?? null;
    }

    public static function has($key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove($key): void
    {
        unset($_SESSION[$key]);
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function destroy(): void
    {
        session_destroy();
    }
}
