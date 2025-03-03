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
                'permission_callback' => $permission ?? '__return_true',
            )
        );
    }
}
