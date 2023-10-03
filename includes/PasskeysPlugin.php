<?php

/**
 * General plugin functionality.
 *
 * @package WpPasskeys
 * @since 0.1.0
 */

namespace WpPasskeys;

use Psalm\Internal\Cli\Plugin;
use WpPasskeys\Admin\PluginSettings;
use WpPasskeys\Admin\UserSettings;
use WpPasskeys\Traits\SingletonTrait;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
        FormHandler::instance()->init();
        // Register the REST API routes.
        $restApiHandler = new RestApiHandler(
            new AuthenticationHandler(),
            new RegistrationHandler(),
            new CredentialsApi()
        );
        $restApiHandler->init();

        // Admin
        PluginSettings::instance()->init();
        UserSettings::instance()->init();
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
        $this->createCredentialsTable();
        self::activate();
    }

    public function createCredentialsTable(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'pk_credential_sources';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pk_credential_id varchar(255) NOT NULL,
            credential_source longtext NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY pk_credential_id (pk_credential_id)
        ) $charset_collate;";

        dbDelta($sql);
    }
}
