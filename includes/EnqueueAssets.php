<?php

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

class EnqueueAssets
{
    use SingletonTrait;


    /**
     * Initializes the function.
     *
     * @return void
     */
    public function init(): void
    {
        add_action('login_enqueue_scripts', [ $this, 'enqueueScripts' ]);
        add_action('login_enqueue_scripts', [ $this, 'enqueueStyles' ]);
        add_action('login_head', static function () {
            ob_start();
        });
        add_action('login_footer', [ $this, 'customizeLoginFormInputs' ]);
    }

    /**
     * Enqueues the scripts.e
     *
     * @return void
     */

    public function enqueueScripts(): void
    {
        wp_enqueue_script(
            'passkeys-main-scripts',
            $this->getAssetsPath() . 'js/index.js',
            array(),
            WP_PASSKEYS_VERSION,
            true
        );

        wp_enqueue_script(
            'passkeys-auth-script',
            $this->getAssetsPath() . 'js/AuthenticationHandler.js',
            array(),
            WP_PASSKEYS_VERSION,
            false,
        );
    }

    public function enqueueStyles(): void
    {
        wp_enqueue_style(
            'passkeys-main-styles',
            $this->getAssetsPath() . 'css/admin.css',
            array(),
            WP_PASSKEYS_VERSION,
            'all'
        );
    }

    /**
     * Retrieves the path to the asset's directory.
     *
     * @return string The path to the asset's directory.
     */
    private function getAssetsPath(): string
    {
        return plugin_dir_url(__DIR__) . 'dist/';
    }


    public function customizeLoginFormInputs(): void
    {
        $form = ob_get_clean();
        $form = preg_replace('/(autocomplete)="username"/', 'autocomplete="username webauthn"', $form);
        $form = preg_replace(
            '/<div class="user-pass-wrap">\s*<label for="user_pass">.*?<\/div>\s*<\/div>/s',
            '',
            $form
        );

        echo $form;
    }
}
