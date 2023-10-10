<?php

namespace WpPasskeys\Credentials;

interface SessionHandlerInterface
{
    public static function set($key, $value): void;

    public static function get($key);

    public static function has($key): bool;

    public static function remove($key): void;

    public static function start(): void;

    public static function destroy(): void;
}
