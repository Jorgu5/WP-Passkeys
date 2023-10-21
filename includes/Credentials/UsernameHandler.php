<?php

declare(strict_types=1);

namespace WpPasskeys\Credentials;

class UsernameHandler
{
    public function __construct(
        private readonly SessionHandler $sessionHandler
    ) {
    }

    public function getUserData(): array
    {
        if (!$this->sessionHandler->has('user_data')) {
            return [];
        }

        $userData = $this->sessionHandler->get('user_data');
        [$username, $displayName] = $this->getDisplayAndUserName($userData);

        return [
            'user_login' => $username,
            'user_email' => $userData['user_email'] ?? '',
            'display_name' => $displayName,
        ];
    }

    public function getUsername(string $username = '', string $email = '', string $displayName = ''): string
    {
        $theUsername = '';

        if (!empty($username)) {
            $theUsername = $username;
        } elseif (!empty($email)) {
            $theUsername = $this->extractUsernameFromEmail($email);
        } elseif (!empty($displayName)) {
            $theUsername = $this->setUsernameAsDisplayName($displayName);
        }

        return $theUsername;
    }



    public function getDisplayName(string $displayName, string $username, string $email): string
    {
        if (empty($username) && empty($email) && empty($displayName)) {
            return wp_unique_id('user_');
        }

        if (!empty($username) || !empty($displayName)) {
            return $displayName ?: $username;
        }
        return !empty($email) ? explode('@', $email)[0] : $username;
    }


    public function getDisplayAndUserName(array $userData = []): array
    {
        $username = $this->getUsername(
            $userData['user_login'] ?? '',
            $userData['user_email'] ?? '',
            $userData['display_name'] ?? ''
        );

        $displayName = $this->getDisplayName(
            $userData['display_name'] ?? '',
            $username,
            $userData['user_email'] ?? ''
        );

        return [$username ?: wp_unique_id('user_'), $displayName ?: $username];
    }

    public function setUsernameAsDisplayName(string $displayName = ''): string
    {
        return strtolower(str_replace(' ', '', $displayName));
    }

    public function extractUsernameFromEmail(string $email = ''): string
    {
        return explode('@', $email)[0];
    }
}
