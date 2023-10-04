<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class FormHandler
{
    use SingletonTrait;

    public function init(): void
    {
        add_action('login_head', static function () {
            ob_start();
        });
        add_action('login_footer', [ $this, 'passkeysPriority' ]);
        add_action('login_form', [ $this, 'passkeysFlow' ], 1);
    }

    public function passkeysPriority(): void
    {
        $form = ob_get_clean();

        // Start output buffering to capture the output of do_action('login_form')
        ob_start();
        do_action('login_form');
        $loginFormActionHook = ob_get_clean();

        // Replace the entire form named "loginform" with the output of do_action('login_form')
        $form = preg_replace('/<form name="loginform"[\s\S]*?<\/form>/', $loginFormActionHook, $form);

        // Remove the <p id="nav"> element
        $form = preg_replace('/<p id="nav">[\s\S]*?<\/p>/', '', $form);

        echo $form;
    }

    public function passkeysFlow(): void
    {
        $value = $this->passkeysLoginForm();
        echo '
            <div class="passkeys-login">
                <span class="passkeys-separator">' . sprintf('<span>%s</span>', __('or', 'wp-passkeys')) . '</span>
                <button
                    class="button button-primary passkeys-login__button passkeys-login__button--switcher">' .
                __('Continue with passkeys', 'wp-passkeys') .
                '</button>
                <button
                    class="button button-primary passkeys-login__button passkeys-login__button--auth">' .
                 __('Continue', 'wp-passkeys') .
                 '</button>
            </div>
            <button class="passkeys-backtodefault">' . __('&larr; Back to default login', 'wp-passkeys') . '</button>
            ';
    }

    public function passkeysLoginForm(): string
    {
        return get_option('wppk_require_userdata');
    }
}
