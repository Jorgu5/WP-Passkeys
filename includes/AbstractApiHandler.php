<?php

namespace WpPasskeys;

abstract class AbstractApiHandler
{
    protected function registerRoute(string $endpoint, string $method, array $callback): void
    {
        register_rest_route(
            WP_PASSKEYS_API_NAMESPACE,
            $endpoint,
            array(
                'methods'  => $method,
                'callback' => $callback,
                'permission_callback' => '__return_true',
            )
        );
    }
}
