<?php

namespace WpPasskeys\Credentials;

class SessionHandler implements SessionHandlerInterface
{
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key): mixed
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
