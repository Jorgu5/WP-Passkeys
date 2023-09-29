<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class FormHandler
{
    use SingletonTrait;

    public function init(): void {
        add_action('login_head', static function () {
            ob_start();
        });
        add_action('login_footer', [ $this, 'addWebauthnAutocomplete' ]);
        add_action('login_form', [ $this, 'addPasskeysFlow' ], 1);
    }

    public function addWebauthnAutocomplete(): void
    {
        $form = ob_get_clean();
        $form = preg_replace('/(autocomplete)="username"/', 'autocomplete="username webauthn"', $form);

        echo $form;
    }

    public function addPasskeysFlow(): void {
        echo '
            <div class="passkeys-login">
                <span class="passkeys-separator">'. sprintf('<span>%s</span>', __( 'or', 'wp-passkeys')).'</span>
                <button
                    type="button"
                    class="button button-primary passkeys-login__button passkeys-login__button--switcher">'.
                __('Continue with passkeys', 'wp-passkeys').
                '</button>
                <button
                    type="submit"
                    class="button button-primary passkeys-login__button passkeys-login__button--auth">'.
                 __('Continue', 'wp-passkeys').
                 '</button>
            </div>
            <button class="passkeys-backtodefault">'. __('&larr; Back to default login', 'wp-passkeys') . '</button>
            ';
    }
}
