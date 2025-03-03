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

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function start(): void
    {
        // Only start session if it's not already active
        if (session_status() === PHP_SESSION_NONE) {
            // Check if headers have been sent
            if (headers_sent($file, $line)) {
                error_log('[WP Passkeys] Cannot start session - headers already sent in ' . $file . ' on line ' . $line);
                return;
            }

            // Use session cookie parameters that work with WordPress
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            try {
                @session_start();
                error_log('[WP Passkeys] Session started successfully');
            } catch (\Exception $e) {
                error_log('[WP Passkeys] Error starting session: ' . $e->getMessage());
            }
        } else if (session_status() === PHP_SESSION_ACTIVE) {
            error_log('[WP Passkeys] Session already active');
        } else {
            error_log('[WP Passkeys] Sessions are disabled');
        }
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
