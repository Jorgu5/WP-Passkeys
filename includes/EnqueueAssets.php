<?php

namespace WpPasskeys;

class EnqueueAssets
{
    /**
     * Initializes the function.
     *
     * @return void
     */
    public static function register(): void
    {
        $pluginAssets = new self();
        add_action('login_enqueue_scripts', [ $pluginAssets, 'enqueueLoginScripts' ]);
        add_action('login_enqueue_scripts', [ $pluginAssets, 'enqueueLoginStyles' ]);

        add_action('admin_enqueue_scripts', [ $pluginAssets, 'enqueueSettingStyles' ]);
        add_action('admin_enqueue_scripts', [ $pluginAssets, 'enqueueUserProfileScript' ]);
    }

    /**
     * Enqueues the scripts.e
     *
     * @return void
     */

    public function enqueueLoginScripts(): void
    {
        wp_enqueue_script(
            'passkeys-register',
            $this->getAssetsPath() . 'js/registration/index.js',
            array(),
            WP_PASSKEYS_VERSION,
            true
        );

        wp_enqueue_script(
            'passkeys-form',
            $this->getAssetsPath() . 'js/form/index.js',
            array(),
            WP_PASSKEYS_VERSION,
            true
        );

        if (!isset($_GET['action']) || ($_GET['action'] !== 'register')) {
            wp_enqueue_script(
                'passkeys-auth',
                $this->getAssetsPath() . 'js/authentication/index.js',
                array(),
                WP_PASSKEYS_VERSION,
                false,
            );
        }
    }

    public function enqueueUserProfileScript(): void
    {
        if (get_current_screen()->id !== 'profile') {
            return;
        }
        wp_enqueue_script(
            'passkeys-user-profile-scripts',
            $this->getAssetsPath() . 'js/admin/index.js',
            array(),
            WP_PASSKEYS_VERSION,
            true
        );
        wp_localize_script(
            'passkeys-user-profile-scripts',
            'passkeys',
            array(
                'nonce' => wp_create_nonce('wp_rest'),
            )
        );
    }

    public function enqueueLoginStyles(): void
    {
        wp_enqueue_style(
            'passkeys-main-styles',
            $this->getAssetsPath() . 'css/default-login.css',
            array(),
            WP_PASSKEYS_VERSION,
            'all'
        );
    }

    public function enqueueSettingStyles($hook): void
    {
        if ($hook !== 'settings_page_wppk_passkeys_settings') {
            return;
        }
        wp_enqueue_style(
            'passkeys-plugin-settings-styles',
            $this->getAssetsPath() . 'css/plugin-settings.css',
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
}
