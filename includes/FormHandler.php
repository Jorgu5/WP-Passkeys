<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class FormHandler
{
    use SingletonTrait;

    private readonly string $passkeysButtonClass;
    private readonly array $userDataSettings;

    public function init(): void
    {
        $this->passkeysButtonClass = $this->isRegisterFlow() ? 'passkeys-button--register' : 'passkeys-button--login';
        $this->userDataSettings = (array) get_option('wppk_require_userdata');
        $this->passkeysInit();
    }

    public function passkeysInit(): void
    {
        add_action('login_head', static function () {
            ob_start();
        }, 99);

        add_action('login_footer', [ $this, 'addAutocompleteAttribute' ]);
        add_action('login_form', [ $this, 'loginPasskeysFlow' ], 1);
        add_action('register_form', [$this, 'registerPasskeysFlow']);
    }

    public function addAutocompleteAttribute(): void
    {
        $form = ob_get_clean();

        $isPassword = get_option('wppk_remove_password_field') === 'off';

        if ($this->isRegisterFlow()) {
            if (!in_array('email', $this->userDataSettings, true)) {
                $form = preg_replace('/<p>\s*<label for="user_email">.*?<\/p>/s', '', $form);
            }
            if (!in_array('username', $this->userDataSettings, true)) {
                $form = preg_replace('/<p>\s*<label for="user_login">.*?<\/p>/s', '', $form);
            }
            if (
                !in_array('username', $this->userDataSettings, true)
                || !in_array('email', $this->userDataSettings, true)
            ) {
                $form = preg_replace('/<p class="submit">.*?<\/p>/s', '', $form);
            }
        }

        if (!$this->isRegisterFlow()) {
            if (!$isPassword) {
                $form = preg_replace(
                    '/<div class="user-pass-wrap">.*?<label for="user_pass">.*?<\/div>.*?<\/div>/s',
                    '',
                    $form
                );
                $form = preg_replace(
                    '/<p class="submit">.*?<\/p>/s',
                    '',
                    $form
                );
            }

            $form = preg_replace(
                '/(autocomplete)="current-password"/',
                'autocomplete="current-password webauthn"',
                $form
            );

            $form = preg_replace('/(autocomplete)="username"/', 'autocomplete="username webauthn"', $form);
        }


        echo $form;
    }

    public function registerPasskeysFlow(): void
    {
        if (in_array('display_name', $this->userDataSettings, true)) {
            echo $this->passkeysDisplayNameInput();
        }
        $this->passkeysButton();
    }

    public function loginPasskeysFlow(): void
    {
        $this->passkeysButton();
    }

    public function passkeysButton(): void
    {

        echo "
        <button type=\"submit\" class=\"button button-primary passkeys-button {$this->passkeysButtonClass}\">" .
             __('Continue', 'wp-passkeys') .
             "</button>
        ";
    }

    private function passkeysDisplayNameInput(): string
    {
        return '<p>
            <label for="display_name">' . __('Display name', 'wp-passkeys') . '</label>
            <input type="text" id="display_name" name="display_name" required="required" autocomplete="name">
            </p>
        ';
    }

    private function isRegisterFlow(): bool
    {
        return isset($_GET['action']) && $_GET['action'] === 'register';
    }
}
