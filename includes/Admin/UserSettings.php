<?php

namespace WpPasskeys\Admin;

use WpPasskeys\Credentials\CredentialHelper;

class UserSettings
{
    private ?array $pkCredentialIds;

    public function __construct(
        private readonly CredentialHelper $credentialHelper,
        private readonly UserPasskeysCardRender $cardRender,
        private readonly PasskeysInfoRender $passkeysInfoRender
    ) {
        $this->pkCredentialIds = [];

        add_action('show_user_profile', [$this, 'displayUserPasskeySettings'], 40);
        add_action('edit_user_profile', [$this, 'displayAdminPasskeySettings'], 40);
        add_action('admin_notices', [$this, 'showGeneralAdminNotice']);
        add_action('admin_notices', [$this, 'removeUserPasskeysNotice']);
        add_action('admin_init', [$this, 'getPkCredentialIds']);
        add_action('edit_user_profile_update', [$this, 'handleClearUserPasskeys']);
    }

    /**
     * Wrapper method to group user passkey settings
     */
    public function displayUserPasskeySettings(): void
    {
        if ( ! current_user_can('read')) {
            return;
        }

        $userPasskeySettings = $this->pkCredentialIds
            ? $this->cardRender->render($this->pkCredentialIds)
            : $this->passkeysInfoRender->renderInfo();

        echo '<div class="user-passkeys-settings" id="registerform">
                <h2 class="user-passkeys-setting__title">' . __('Account passkeys', 'wp-passkeys') . '</h2>' .
             $userPasskeySettings
             . '</div>';
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


    public function getPkCredentialIds(): void
    {
        $user_id               = get_current_user_id();
        $this->pkCredentialIds = get_user_meta($user_id, 'pk_credential_id', false);
    }


    public function showGeneralAdminNotice(): void
    {
        if ( ! $this->pkCredentialIds && get_option('wppk_prompt_password_users') === 'on') {
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
            if ( ! current_user_can('edit_user', $currentUserId)) {
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

            if ( ! is_bool($removeUser) && is_wp_error($removeUser)) {
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
