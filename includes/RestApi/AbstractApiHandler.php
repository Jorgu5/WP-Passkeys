<?php

namespace WpPasskeys\RestApi;

abstract class AbstractApiHandler
{
    protected function registerRoute(
        string $endpoint,
        string $method,
        array $callback,
        callable | string|null $permission = null
    ): void {
        register_rest_route(
            WP_PASSKEYS_API_NAMESPACE,
            $endpoint,
            array(
                'methods'  => $method,
                'callback' => $callback,
                'permission_callback' => $permission ?? [$this, 'defaultPermissionCallback'],
                'args' => $this->getDefaultArgs(),
            )
        );
    }

    /**
     * Default permission callback that verifies nonce for authenticated requests
     * 
     * @return bool
     */
    public function defaultPermissionCallback(): bool
    {
        // Skip nonce verification for unauthenticated users (login process)
        if (!is_user_logged_in()) {
            return true;
        }

        // For authenticated users, verify nonce
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field($_SERVER['HTTP_X_WP_NONCE']) : '';
        return wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Get default arguments for REST API endpoints
     * 
     * @return array
     */
    protected function getDefaultArgs(): array
    {
        return [
            '_wpnonce' => [
                'required' => false, // Not required as it's in the header
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}
