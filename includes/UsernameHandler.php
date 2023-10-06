<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class UsernameHandler
{
    use SingletonTrait;

    public function handleUserData(): array
    {
        $userData = [];

        $session = SessionHandler::instance();

        if ($session->has('user_data')) {
            $userData = $session->get('user_data');
        }

        $userEmail = $userData['user_email'] ?? '';

        [$username, $displayName] = $this->getDisplayAndUserName($userData);

        return [
            'user_login' => $username,
            'user_email' => $userEmail,
            'display_name' => $displayName,
        ];
    }

    private function handleUsername($username, $email, $displayName): string
    {
        if (!empty($username) || (!empty($email) && empty($displayName))) {
            return $username ?: $this->convertEmail($email);
        }
        return !empty($email) ? $this->convertEmail($email) : $this->convertDisplayName($displayName);
    }

    private function handleDisplayName($displayName, $username, $email): string
    {
        if (!empty($username) || !empty($displayName)) {
            return $displayName ?: $username;
        }
        return !empty($email) ? explode('@', $email)[0] : $username;
    }

    private function getDisplayAndUserName($userData): array
    {
        $username = $userData['user_login'] ?? '';
        $displayName = $userData['display_name'] ?? '';
        $email = $userData['user_email'] ?? '';

        // Case 7: No data provided
        if (empty($userData)) {
            $username = wp_unique_id('user_');
            return [$username, $username];
        }

        $username = $this->handleUsername($username, $email, $displayName);
        $displayName = $this->handleDisplayName($displayName, $username, $email);

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
