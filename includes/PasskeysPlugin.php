<?php

namespace WpPasskeys;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use WpPasskeys\Admin\PluginSettings;
use WpPasskeys\Admin\UserSettings;
use WpPasskeys\Form\FormModifier;
use WpPasskeys\RestApi\RestApiHandler;
use League\Container\Container;
use WP_REST_Server;

/**
 * Main plugin class.
 */
class PasskeysPlugin
{
    public RestApiHandler $restApiHandler;
    public UserSettings $userSettings;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        private readonly Container $container
    ) {
        register_activation_hook(__FILE__, [$this, 'activate']);

        $this->restApiHandler = $this->container->get(RestApiHandler::class);
        $this->userSettings   = $this->container->get(UserSettings::class);
    }


    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function make(): void
    {
        $container = new Container();
        $container->addServiceProvider(new ServiceProvider());
        $plugin = new self($container);
        $plugin->run();
    }

    public function run(): void
    {
        $this->registerHooks();
        $this->setPluginVersion();
    }

    private function registerHooks(): void
    {
        EnqueueAssets::register();
        FormModifier::register();
        PluginSettings::register();
    }

    private function setPluginVersion(): void
    {
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data(__FILE__);
        if (! defined('WP_PASSKEYS_VERSION')) {
            define('WP_PASSKEYS_VERSION', $plugin_data['Version']);
        }
    }

    // Activation hook.

    public function activate(): void
    {
        $this->createCredentialsTable();
        $this->setDefaultPluginOptions();
    }

    private function createCredentialsTable(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'pk_credential_sources';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pk_credential_id varchar(255) NOT NULL,
            credential_source longtext NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY pk_credential_id (pk_credential_id)
        ) $charset_collate;";

        dbDelta($sql);
    }

    private function setDefaultPluginOptions(): void
    {
        $this->setDefaultOption('wppk_require_userdata', []);
        $this->setDefaultOption('wppk_passkeys_redirect', admin_url());
        $this->setDefaultOption('wppk_passkeys_timeout', 30000);
        $this->setDefaultOption('wppk_prompt_password_users', 'off');
    }

    private function setDefaultOption(string $optionName, string|array $defaultValue): void
    {
        if (get_option($optionName) === false) {
            update_option($optionName, $defaultValue);
        }
    }
}
