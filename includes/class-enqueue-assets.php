<?php

namespace WpPasskeys;

use WpPasskeys\Traits\Singleton;

class Enqueue_Assets {

    use Singleton;


	/**
	 * Initializes the function.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueues the scripts.
	 *
	 * @return void
	 */

	public function enqueue_scripts(): void {
		wp_enqueue_script(
			'passkeys-main-scripts',
			$this->get_assets_path() . 'index.js',
			array(),
			WP_PASSKEYS_VERSION,
			true
		);
	}

	public function enqueue_styles(): void {
		wp_enqueue_style(
			'passkeys-main-styles',
			$this->get_assets_path() . 'index.css',
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
    private function get_assets_path(): string {
        return plugin_dir_url( __DIR__ ) . 'dist/';
    }

}
