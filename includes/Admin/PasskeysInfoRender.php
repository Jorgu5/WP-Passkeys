<?php

namespace WpPasskeys\Admin;

use WpPasskeys\Admin\AdminUtilities;

class PasskeysInfoRender
{
    public function renderInfo(): string
    {
        $authCollage = file_get_contents(WP_PASSKEYS_PLUGIN_PATH . 'assets/img/auth-collage.svg');

        return '<div class="passkeys-info">
            <div class="passkeys-info__collage">' . $authCollage . '</div>
                <div class="passkeys-info__text">
                    <p>
                    <strong>' . __(
                'With passkeys, you don’t need to remember complex passwords.',
                'wp-passkeys'
            ) . '</strong>
                    </p>
                    <h2 class="passkeys-info__title">' . __('What are passkeys?', 'wp-passkeys') . '</h2>
                    <p class="passkeys-info__text">' . __(
                        'Passkeys are encrypted digital keys you create using your fingerprint, face, or screen lock.'
                    ) . '</p>
                    <h2 class="passkeys-info__title">' . __('Where are passkeys saved?', 'wp-passkeys') . '</h2>
                    <p class="passkeys-info__text">' . __(
                        'Passkeys are saved to your password manager, so you can sign in on other devices. '
                    ) . '</p>' .
               AdminUtilities::renderButton(
                   __('Create passkey', 'wp-passkeys'),
                   'create_passkey',
                   'button-primary passkeys__button--add'
               ) . '
                </div>
            </div>';
    }
}
