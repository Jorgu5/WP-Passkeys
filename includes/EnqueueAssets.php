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
        add_action('login_enqueue_scripts', [$pluginAssets, 'enqueueLoginScripts']);
        add_action('login_enqueue_scripts', [$pluginAssets, 'enqueueLoginStyles']);

        add_action('admin_enqueue_scripts', [$pluginAssets, 'enqueueSettingStyles']);
        add_action('admin_enqueue_scripts', [$pluginAssets, 'enqueueUserProfileScript']);
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
            [],
            WP_PASSKEYS_VERSION,
            true
        );

        wp_enqueue_script(
            'passkeys-form',
            $this->getAssetsPath() . 'js/form/index.js',
            [],
            WP_PASSKEYS_VERSION,
            false
        );

        if (! isset($_GET['action']) || ($_GET['action'] !== 'register')) {
            wp_enqueue_script(
                'passkeys-auth',
                $this->getAssetsPath() . 'js/authentication/index.js',
                [],
                WP_PASSKEYS_VERSION,
                false,
            );
        }

        wp_localize_script(
            'passkeys-form',
            'pkUser',
            [
                'restEndpoints' => [
                    'main' => rest_url('wp-passkeys'),
                ],
            ]
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

    public function enqueueUserProfileScript(): void
    {
        if (get_current_screen()->id !== 'profile') {
            return;
        }
        wp_enqueue_script(
            'passkeys-user-profile-scripts',
            $this->getAssetsPath() . 'js/admin/index.js',
            [],
            WP_PASSKEYS_VERSION,
            true
        );
        wp_localize_script(
            'passkeys-user-profile-scripts',
            'pkUser',
            [
                'nonce' => wp_create_nonce('wp_rest'),
                'restEndpoints' => [
                    'main' => rest_url('wp-passkeys'),
                    'user' => rest_url('wp-passkeys/creds/user'),
                ],
            ]
        );
    }

    public function enqueueLoginStyles(): void
    {
        wp_enqueue_style(
            'passkeys-main-styles',
            $this->getAssetsPath() . 'css/default-login.css',
            [],
            WP_PASSKEYS_VERSION,
            'all'
        );
    }

    public function enqueueSettingStyles($hook): void
    {
        if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
            return;
        }
        wp_enqueue_style(
            'passkeys-plugin-settings-styles',
            $this->getAssetsPath() . 'css/plugin-settings.css',
            [],
            WP_PASSKEYS_VERSION,
            'all'
        );
    }
}
