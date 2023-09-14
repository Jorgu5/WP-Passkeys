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
        add_action('login_enqueue_scripts', array( $this, 'enqueueScripts' ));
        add_action('login_enqueue_scripts', array( $this, 'enqueueStyles' ));
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
            $this->getAssetsPath() . 'index.js',
            array(),
            WP_PASSKEYS_VERSION,
            true
        );
    }

    public function enqueueStyles(): void
    {
        wp_enqueue_style(
            'passkeys-main-styles',
            $this->getAssetsPath() . 'index.css',
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
