<?php

namespace WpPasskeys;

abstract class AbstractApiHandler
{
    abstract public function getNamespace(bool $register = false): string;

    protected function registerRoute(string $endpoint, string $method, array $callback, bool $register = false): void
    {
        register_rest_route(
            WP_PASSKEYS_API_NAMESPACE . $this->getNamespace($register),
            $endpoint,
            array(
                'methods'  => $method,
                'callback' => $callback,
                'permission_callback' => '__return_true',
            )
        );
    }
}
