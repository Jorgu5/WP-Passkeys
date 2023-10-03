<?php

namespace WpPasskeys\Admin;

use phpDocumentor\Reflection\Types\Callable_;
use Webauthn\Exception\InvalidDataException;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Traits\SingletonTrait;
use WpPasskeys\CredentialHelper;

class UserSettings
{
    use SingletonTrait;

    private readonly string $pkCredentialId;

    public function init(): void
    {
        add_action('show_user_profile', [$this, 'displayUserPasskeySettings'], 1);
        add_action('admin_notices', [$this, 'renderAdminNotice']);
        add_action('init', [$this, 'getPkCredentialId']);
    }

    /**
     * Wrapper method to group user passkey settings
     */
    public function displayUserPasskeySettings(): void
    {
        echo '<div class="user-passkeys-settings">
                <h2 class="user-passkeys-setting__title">' . __('Your passkeys', 'wp-passkeys') . '</h2>';
                $this->renderPasskeysList();
        echo '</div>';
    }

    public function getPkCredentialId(): void
    {
        $user_id = get_current_user_id();
        $this->pkCredentialId = get_user_meta($user_id, 'pk_credential_id', true);
    }

    private function renderPasskeysList(): void
    {
        // Always show the table
        echo '<table id="user-passkeys" class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
                <tr>
                    <th>' . __('Public Credential Source ID', 'wp-passkeys') . '</th>
                    <th>' . __('Actions', 'wp-passkeys') . '</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>' . ($this->pkCredentialId ?? 'N/A') . '</td>
                    <td>' . $this->renderButtons() . '</td> 
                </tr>
            </tbody>
          </table>';
    }


    /**
     * General method to render buttons
     */
    private function renderButton(string $label, string $extraClass): string
    {
        return sprintf(
            '<button type="button" class="button button-primary passkeys-login__button %s">%s</button>',
            $extraClass,
            $label
        );
    }

    public function renderAdminNotice(): void
    {
        if (!$this->pkCredentialId) {
            echo '<div style="display: flex; align-items: center;" class="notice notice-info is-dismissible">
                <p>' . __(
                'We now offer passkeys support, and it looks like you havent set up your keys yet. 
                Get started now to enhance your security and enjoy easier logins across multiple devices.',
                'wp-passkeys'
            ) . '</p><a href="' . get_edit_profile_url() . '#user-passkeys" class="button button-primary">' . __(
                'Create passkeys',
                'wp-passkeys'
            ) . '</a>
            </div>';
        }
    }

    private function renderButtons(): string
    {
        if ($this->pkCredentialId) {
            return $this->renderButton(__('Remove passkey', 'wp-passkeys'), 'passkeys-login__button--remove');
        }

        return $this->renderButton(__('Add passkey', 'wp-passkeys'), 'passkeys-login__button--add');
    }
}
