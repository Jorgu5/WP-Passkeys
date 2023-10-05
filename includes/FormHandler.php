<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class FormHandler
{
    use SingletonTrait;

    public function init(): void
    {
        $this->passkeysInit(get_option('wppk_login_priority'));
    }

    public function passkeysInit(string $option): void
    {
        add_action('login_head', static function () {
            ob_start();
        }, 99);

        add_action('login_footer', [ $this, 'addAutocompleteAttribute' ]);
        add_action('login_form', [ $this, 'passkeysButton' ], 1);
        add_action('register_form', [$this, 'passkeysButton']);
    }

    public function addAutocompleteAttribute(): void
    {
        $form = ob_get_clean();
        $form = preg_replace('/(autocomplete)="username"/', 'autocomplete="username webauthn"', $form);

        echo $form;
    }

    public function passkeysButton(): void
    {
        $passkeysButtonClass = 'passkeys-button--login';
        if (isset($_GET['action']) && $_GET['action'] === 'register') {
            $passkeysButtonClass = 'passkeys-button--register';
        }

        echo "
        <button class=\"button button-primary passkeys-button {$passkeysButtonClass}\">" .
             __('Continue', 'wp-passkeys') .
             "</button>
        ";
    }

    private function passkeysEmailInput(): string
    {
        return '
            <div class="passkeys-login__email">
                <label for="passkeys-email">' . __('Username or e-mail address', 'wp-passkeys') . '</label>
                <input type="text" id="passkeys-email" name="passkeys-email" required autocomplete="username webauthn">
            </div>
        ';
    }

    private function passkeysDisplayNameInput(): string
    {
        return '
            <div class="passkeys-login__display-name">
                <label for="passkeys-display-name">' . __('Display name', 'wp-passkeys') . '</label>
                <input type="text" id="passkeys-display-name" name="passkeys-display-name" required autocomplete="name">
            </div>
        ';
    }

    public function passkeysOptionalFlow(): void {
        echo '
            <div class="passkeys-login">
                <span class="passkeys-separator">' . sprintf('<span>%s</span>', __('or', 'wp-passkeys')) . '</span>
                <button
                    class="button passkeys-login__button passkeys-login__button--switcher">' .
                __('Continue with passkeys', 'wp-passkeys') .
                '</button>
            </div>';
    }
}
