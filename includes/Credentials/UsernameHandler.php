<?php

declare(strict_types=1);

namespace WpPasskeys\Credentials;

class UsernameHandler
{
    public function __construct(
        private readonly SessionHandler $sessionHandler
    ) {}
    public function getUserData(): array
    {
        $userData = [];

        if ($this->sessionHandler->has('user_data')) {
            $userData = $this->sessionHandler->get('user_data');
        }

        $userEmail = $userData['user_email'] ?? '';

        [$username, $displayName] = (new self())->getDisplayAndUserName($userData);

        return [
            'user_login' => $username,
            'user_email' => $userEmail,
            'display_name' => $displayName,
        ];
    }

    private function username(string $username, string $email, string $displayName): string
    {
        if (!empty($username) || (!empty($email) && empty($displayName))) {
            return $username ?: $this->convertEmail($email);
        }
        return !empty($email) ? $this->convertEmail($email) : $this->convertDisplayName($displayName);
    }

    private function displayName(string $displayName, string $username, string $email): string
    {
        if (!empty($username) || !empty($displayName)) {
            return $displayName ?: $username;
        }
        return !empty($email) ? explode('@', $email)[0] : $username;
    }

    private function getDisplayAndUserName(array $userData): array
    {
        $username = $userData['user_login'] ?? '';
        $displayName = $userData['display_name'] ?? '';
        $email = $userData['user_email'] ?? '';

        // Case 7: No data provided
        if (empty($userData)) {
            $username = wp_unique_id('user_');
            return [$username, $username];
        }

        $username = $this->username($username, $email, $displayName);
        $displayName = $this->displayName($displayName, $username, $email);

        // Fallback to username if displayName is still empty
        $displayName = $displayName ?: $username;

        return [$username, $displayName];
    }



    private function convertDisplayName(string $displayName): string
    {
        $displayName = str_replace(' ', '', $displayName);

        return strtolower($displayName);
    }

    private function convertEmail(string $email): string
    {
        return explode('@', $email)[0];
    }
}
