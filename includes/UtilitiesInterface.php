<?php

namespace WpPasskeys;

use WP_User;

interface UtilitiesInterface
{
    public function setAuthCookie(string $username = null, int $userId = null): void;

    public function getRedirectUrl(): string;

    public function setUserAndCookie(WP_User|null $user): void;

    public static function safeEncode(string $data): string;

    public static function getHostname(): string;

}
