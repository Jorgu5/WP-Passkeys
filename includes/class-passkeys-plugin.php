<?php
/**
 * General plugin functionality.
 *
 * @package WpPasskeys
 * @since 0.1.0
 */

namespace WpPasskeys;

use WpPasskeys\Traits\Singleton;

/**
 * Main plugin class.
 */
class Passkeys_Plugin {
	use Singleton;

	/**
	 * Hook into actions and filters.
	 */
    private function init_hooks(): void {
        Enqueue_Assets::instance()->init();
        Authentication_Handler::instance()->init();
        Registration_Handler::instance()->init();
    }

	/**
	 * Activation hook.
	 */
    public static function activate(): void {
        Utilities::set_plugin_version();
    }

	/**
	 * Run the plugin.
	 */
    public function run(): void {
        $this->init_hooks();
        self::activate();
    }
}
