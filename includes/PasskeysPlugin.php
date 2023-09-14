<?php

/**
 * General plugin functionality.
 *
 * @package WpPasskeys
 * @since 0.1.0
 */

namespace WpPasskeys;

use WpPasskeys\Traits\SingletonTrait;

/**
 * Main plugin class.
 */
class PasskeysPlugin
{
    use SingletonTrait;

    /**
     * Hook into actions and filters.
     */
    private function initHooks(): void
    {
        EnqueueAssets::instance()->init();
        AuthenticationHandler::instance()->init();
        RegistrationHandler::instance()->init();
    }

    /**
     * Activation hook.
     */
    public static function activate(): void
    {
        Utilities::setPluginVersion();
    }

    /**
     * Run the plugin.
     */
    public function run(): void
    {
        $this->initHooks();
        self::activate();
    }
}
