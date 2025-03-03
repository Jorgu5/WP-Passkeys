<?php

/**
 * Utilities helper for WP Pass Keys.
 */

namespace WpPasskeys;

use JsonException;
use Throwable;
use WP_Error;
use WP_REST_Response;
use WP_User;

class Utilities
{
    /**
     * Handle exceptions in a secure way, only exposing detailed information in development environments
     *
     * @param Throwable $exception The exception to handle
     * @param int|string|null $errorCode The HTTP error code to return
     *
     * @return WP_REST_Response
     */
    public function handleException(Throwable $exception, int|string|null $errorCode = 500): WP_REST_Response
    {
        // Log the exception for debugging
        $this->logger($exception);

        // Prepare a user-friendly error message
        $userMessage = $this->getUserFriendlyErrorMessage($exception);

        $errorData = [
            'code'    => $errorCode,
            'message' => $userMessage,
        ];

        // Only include detailed error information in development environments
        if (
            (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development') ||
            (defined('WP_DEBUG') && WP_DEBUG)
        ) {
            $errorData['dev_message'] = $exception->getMessage();
            $errorData['trace'] = $exception->getTrace();
        }

        return new WP_REST_Response($errorData, $errorCode);
    }

    /**
     * Get a user-friendly error message based on the exception type
     * 
     * @param Throwable $exception The exception
     * @return string A user-friendly error message
     */
    private function getUserFriendlyErrorMessage(Throwable $exception): string
    {
        // Default generic message
        $message = 'An error occurred while processing your request.';

        // Customize message based on exception type
        if (strpos($exception->getMessage(), 'credential') !== false) {
            $message = 'There was an issue with your passkey credentials. Please try again.';
        } elseif (strpos($exception->getMessage(), 'session') !== false) {
            $message = 'Your session has expired. Please refresh the page and try again.';
        } elseif (strpos($exception->getMessage(), 'user') !== false) {
            $message = 'There was an issue with your user account. Please contact support.';
        }

        return $message;
    }

    /**
     * Log errors and exceptions
     * 
     * @param mixed $error The error to log
     * @return void
     */
    public function logger($error): void
    {
        if ($error instanceof Throwable) {
            $logMessage = sprintf(
                "[WP Passkeys] Exception occurred: %s in %s:%d\nStack trace:\n%s",
                $error->getMessage(),
                $error->getFile(),
                $error->getLine(),
                $error->getTraceAsString()
            );
        } elseif ($error instanceof WP_Error) {
            $logMessage = sprintf(
                "[WP Passkeys] WordPress Error: '%s' with code '%s'",
                $error->get_error_message(),
                $error->get_error_code()
            );
        } else {
            return;
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG) {
            error_log($logMessage);
        }
    }

    /**
     * @param WP_Error $error
     *
     * @return array
     */

    public function handleWpError(WP_Error $error): array
    {
        $this->logger($error);

        return [
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data'    => $error->get_error_data(),
        ];
    }

    public function safeEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function getHostname(): string
    {
        $site_url = get_site_url();

        return parse_url($site_url, PHP_URL_HOST);
    }

    public function setAuthCookie(string $username = null, int $userId = null): void
    {
        $user = null;

        if ($userId) {
            $user = get_user_by('id', $userId);
            if ($user) {
                $this->setUserAndCookie($user);
            }
        }

        if ($username) {
            $user = get_user_by('login', $username);
            if ($user) {
                $this->setUserAndCookie($user);
            }
        }

        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }
    }

    public function setUserAndCookie(WP_User|null $user): void
    {
        if (! $user) {
            return;
        }
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true);
    }

    public function getRedirectUrl(): string
    {
        $redirectUrl = get_option('wppk_passkeys_redirect');
        if (empty($redirectUrl)) {
            $redirectUrl = get_admin_url();
        }

        return $redirectUrl;
    }

    public function isLocalhost(): bool
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        $localhost_urls = ['localhost', '127.0.0.1', '::1'];
        if (in_array($_SERVER['SERVER_NAME'], $localhost_urls)) {
            return true;
        }

        return false;
    }

    public function getCurrentFormattedDate(): string
    {
        return date('F jS, Y, \a\t H:i:s');
    }

    public function getDeviceOS(): string
    {
        $os = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '';
        $os = stripslashes($os);

        return trim($os, '"');
    }
}
