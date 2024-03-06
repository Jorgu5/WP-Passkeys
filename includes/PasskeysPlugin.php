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
    private static ?self $instance = null;
    public RestApiHandler $restApiHandler;
    public UserSettings $userSettings;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        private readonly Container $container
    ) {
        $this->restApiHandler = $this->container->get(RestApiHandler::class);
        $this->userSettings   = $this->container->get(UserSettings::class);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function activate(): void
    {
        $plugin = self::getInstance();
        $plugin->createCredentialsTable();
        $plugin->setDefaultPluginOptions();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $container = new Container();
            $container->addServiceProvider(new ServiceProvider());
            self::$instance = new self($container);
        }

        return self::$instance;
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_os varchar(255) NOT NULL,
        last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_used_os varchar(255) NULL,
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

    // Activation hook.

    private function setDefaultOption(string $optionName, string|array $defaultValue): void
    {
        if (get_option($optionName) === false) {
            update_option($optionName, $defaultValue);
        }
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
}
