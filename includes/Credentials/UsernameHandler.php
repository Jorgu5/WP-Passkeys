<?php

declare(strict_types=1);

namespace WpPasskeys\Credentials;

use WpPasskeys\Utilities;

class UsernameHandler
{
    public function __construct(
        private readonly SessionHandler $sessionHandler
    ) {
    }

    public function getOrCreateUserData(): array
    {
        $userData = [];
        if ($this->sessionHandler->has('user_data')) {
            $userData = $this->sessionHandler->get('user_data');
            [$username, $displayName] = $this->getDisplayAndUserName($userData);
        } elseif (is_user_logged_in()) {
            $user        = wp_get_current_user();
            $username    = $user?->user_login;
            $displayName = $user?->display_name;
        } else {
            [$username, $displayName] = $this->getDisplayAndUserName();
        }

        $userData = [
            'user_login'   => $username,
            'user_email'   => $userData['user_email'] ?? '',
            'display_name' => $displayName,
        ];

        $this->sessionHandler->set('user_data', $userData);

        return $userData;
    }

    public function getDisplayAndUserName(array $userData = []): array
    {
        $userLogin           = $userData['user_login'] ?? '';
        $userEmail           = $userData['user_email'] ?? '';
        $displayNameFromData = $userData['display_name'] ?? '';

        $username    = $this->getUsername($userLogin, $userEmail, $displayNameFromData);
        $displayName = $this->getDisplayName($displayNameFromData, $username, $userEmail);

        return [$username, $displayName];
    }

    public function getUsername(string $username = '', string $email = '', string $displayName = ''): string
    {
        if (! empty($username)) {
            $theUsername = $username;
        } elseif (! empty($email)) {
            $theUsername = $this->extractUsernameFromEmail($email);
        } elseif (! empty($displayName)) {
            $theUsername = $this->setUsernameAsDisplayName($displayName);
        } else {
            $theUsername = $this->passkeysUniqueId('user', $this->getUserCurrentMaxIndex());
        }

        return $theUsername;
    }

    public function extractUsernameFromEmail(string $email = ''): string
    {
        return explode('@', $email)[0];
    }

    public function setUsernameAsDisplayName(string $displayName = ''): string
    {
        return strtolower(str_replace(' ', '', $displayName));
    }

    public function passkeysUniqueId(string $prefix = '', int $startIndex = 0): string
    {
        return $prefix . '_' . ++$startIndex;
    }

    public function getUserCurrentMaxIndex(): int
    {
        $users   = get_users(['fields' => 'user_login']);
        $indexes = array_map(static function ($user) {
            return (int)str_replace('user_', '', $user);
        }, $users);

        if (empty($indexes)) {
            return 0;
        }

        return max($indexes);
    }

    public function getDisplayName(string $displayName, string $username, string $email): string
    {
        if (empty($username) && empty($email) && empty($displayName)) {
            return $this->passkeysUniqueId('user', $this->getUserCurrentMaxIndex());
        }

        if (! empty($username) || ! empty($displayName)) {
            return $displayName ?: $username;
        }

        return ! empty($email) ? explode('@', $email)[0] : $username;
    }

    public function getUserEarlyData(): array
    {
        if (isset($_COOKIE[LOGGED_IN_COOKIE])) {
            // Manually inspect the logged-in cookie.
            // Note: This does not guarantee the user is valid or has not tampered with the cookie.
            $user_id = wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in');
            if ($user_id) {
                // Now, you have a user ID before the usual 'init' action.
                // You can load the user object manually if needed.
                $user = get_user_by('id', $user_id);
                // Note: Use this data cautiously, as full user session verification may not have occurred yet.
            }
        }
    }
}
