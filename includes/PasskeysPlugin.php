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
        // Register the REST API routes.
        $restApiHandler = new RestApiHandler(
            new AuthenticationHandler(),
            new RegistrationHandler(),
        );
        $restApiHandler->init();
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
