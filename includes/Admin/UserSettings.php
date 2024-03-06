<?php

namespace WpPasskeys\Admin;

use WP_Session_Tokens;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Exceptions\InvalidCredentialsException;

class UserSettings
{
    private ?string $pkCredentialId;

    public function __construct(
        private readonly CredentialHelper $credentialHelper
    ) {
        $this->pkCredentialId = '';

        add_action('show_user_profile', [$this, 'displayUserPasskeySettings'], 1);
        add_action('edit_user_profile', [$this, 'displayAdminPasskeySettings'], 1);
        add_action('admin_notices', [$this, 'showGeneralAdminNotice']);
        add_action('admin_notices', [$this, 'removeUserPasskeysNotice']);
        add_action('init', [$this, 'getPkCredentialId']);
        add_action('edit_user_profile_update', [$this, 'handleClearUserPasskeys']);
    }

    /**
     * Wrapper method to group user passkey settings
     */
    public function displayUserPasskeySettings(): void
    {
        if (! current_user_can('read')) {
            return;
        }
        echo '<div class="user-passkeys-settings" id="registerform">
                <h2 class="user-passkeys-setting__title">' . __('Your passkeys', 'wp-passkeys') . '</h2>';
        $this->renderPasskeysList();
        echo '</div>';
    }

    private function renderPasskeysList(): void
    {
        // Always show the table
        echo '<table id="user-passkeys" class="wp-list-table widefat fixed striped table-view-list posts">
        <thead>
            <tr>
                <th>' . __('Public Credential Source ID', 'wp-passkeys') . '</th>
                <th>' . __('Registration Date', 'wp-passkeys') . '</th>
                <th>' . __('Registration OS', 'wp-passkeys') . '</th>
                <th>' . __('Last Used At', 'wp-passkeys') . '</th>
                <th>' . __('Last Used OS', 'wp-passkeys') . '</th>
                <th>' . __('Actions', 'wp-passkeys') . '</th>
            </tr>
        </thead>
        <tbody>';

        // Assuming $this->pkCredentialId is available and valid
        $registrationDate = $this->credentialHelper->getDataByCredentialId(
            $this->pkCredentialId,
            'created_at'
        ) ?: 'N/A';
        $registrationOS   = $this->credentialHelper->getDataByCredentialId(
            $this->pkCredentialId,
            'created_os'
        ) ?: 'N/A';
        $lastUsedAt       = $this->credentialHelper->getDataByCredentialId(
            $this->pkCredentialId,
            'last_used_at'
        ) ?: 'N/A';
        $lastUsedOS       = $this->credentialHelper->getDataByCredentialId(
            $this->pkCredentialId,
            'last_used_os'
        ) ?: 'N/A';

        echo "<tr>
            <td id='pk_credential_id'>{$this->pkCredentialId}</td>
            <td>{$registrationDate}</td>
            <td>{$registrationOS}</td>
            <td>{$lastUsedAt}</td>
            <td>{$lastUsedOS}</td>
            <td>" . $this->renderButtons() . "</td> 
          </tr>
        </tbody>
      </table>";
    }

    private function renderButtons(): string
    {
        if ($this->pkCredentialId) {
            return $this->renderButton(
                __(
                    'Remove passkey',
                    'wp-passkeys'
                ),
                'passkeys-login__button--remove'
            );
        }

        return $this->renderButton(__('Add passkey', 'wp-passkeys'), 'button-primary passkeys-login__button--add');
    }

    /**
     * General method to render buttons
     */
    private function renderButton(string $label, string $extraClass): string
    {
        return sprintf(
            '<button 
style="color: #f00; border: none; padding: 0;"
type="button" 
class="button passkeys-login__button %s">%s</button>',
            $extraClass,
            $label
        );
    }

    public function displayAdminPasskeySettings(): void
    {
        echo '<div class="user-passkeys-settings">
        <h2 class="user-passkeys-setting__title">' . __('Passkeys', 'wp-passkeys') . '</h2>
        <button type="submit" name="submit" value="clear_user_passkeys" class="button button-link-delete">'
             . __('Clear User Passkeys', 'wp-passkeys') .
             '</button>
        <span><strong><p>'
             . __(
                 'Note: Use this button with caution. It will remove all passkeys for this user. If he has not set up password before he will not be able to log in.
            It should be only used when client has lost access to his device, for debugging purposes and for specific edge cases.'
             ) .
             '</p></strong></span>
    </div>';
    }


    public function getPkCredentialId(): void
    {
        $user_id              = get_current_user_id();
        $this->pkCredentialId = get_user_meta($user_id, 'pk_credential_id', true);
    }

    public function showGeneralAdminNotice(): void
    {
        if (! $this->pkCredentialId && get_option('wppk_prompt_password_users') === 'on') {
            echo '<div style="display: flex; align-items: center;" class="notice notice-info is-dismissible">
                <p>' . __(
                'We now offer passkeys support, and it looks like you have not set up your keys yet.
                Get started now to enhance your security and enjoy easier logins across multiple devices.',
                'wp-passkeys'
            ) . '</p><a href="' . get_edit_profile_url() . '#user-passkeys" class="button button-primary">' . __(
                'Create passkeys',
                'wp-passkeys'
            ) . '</a>
            </div>';
        }
    }

    public function removeUserPasskeysNotice(): void
    {
        $user_id = get_current_user_id();
        if ($msg = get_transient('user_passkeys_clear_msg_' . $user_id)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            delete_transient('user_passkeys_clear_msg_' . $user_id);
        }
        if ($error = get_transient('user_passkeys_clear_error_' . $user_id)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
            delete_transient('user_passkeys_clear_error_' . $user_id);
        }
    }

    public function handleClearUserPasskeys(int $user_id): void
    {
        $currentUserId = get_current_user_id();
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['submit']) &&
            $_POST['submit'] === 'clear_user_passkeys'
        ) {
            if (! current_user_can('edit_user', $currentUserId)) {
                return;
            }

            $removeUser = $this->credentialHelper->removeUserCredentials($user_id);

            if (is_bool($removeUser) && $removeUser === false) {
                set_transient(
                    'user_passkeys_clear_error_' . $currentUserId,
                    __(
                        'The passkeys could not be removed due to unknown error.',
                        'wp-passkeys'
                    ),
                    10 * MINUTE_IN_SECONDS
                );
            }

            if (! is_bool($removeUser) && is_wp_error($removeUser)) {
                set_transient(
                    'user_passkeys_clear_error_' . $currentUserId,
                    $removeUser->get_error_message(),
                    10 * MINUTE_IN_SECONDS
                );
            }

            set_transient(
                'user_passkeys_clear_msg_' . $currentUserId,
                __(
                    'Passkeys have been removed successfully.',
                    'wp-passkeys'
                ),
                10 * MINUTE_IN_SECONDS
            );
        }
    }
}
