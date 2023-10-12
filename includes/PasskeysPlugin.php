<?php

namespace WpPasskeys;

use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use WpPasskeys\Admin\PluginSettings;
use WpPasskeys\Admin\UserSettings;
use WpPasskeys\Ceremonies\AuthEndpoints;
use WpPasskeys\Ceremonies\RegisterEndpoints;
use WpPasskeys\Credentials\CredentialEntity;
use WpPasskeys\Credentials\CredentialsEndpoints;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Form\FormHandler;
use WpPasskeys\RestApi\RestApiHandler;
use WpPasskeys\AlgorithmManager\AlgorithmManager;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Main plugin class.
 */
class PasskeysPlugin
{
    private RestApiHandler $restApiHandler;

    public function __construct(RestApiHandler $restApiHandler)
    {
        $this->restApiHandler = $restApiHandler;
    }


    public static function make(): self
    {
        $restApiHandler = new RestApiHandler(
            new AuthEndpoints(
                new PublicKeyCredentialLoader(
                    AttestationObjectLoader::create(
                        AttestationStatementSupportManager::create()
                    )
                ),
                new AuthenticatorAssertionResponseValidator(
                    null,
                    null,
                    ExtensionOutputCheckerHandler::create(),
                    null,
                ),
                new CredentialHelper(),
                new AlgorithmManager(),
            ),
            new RegisterEndpoints(
                new CredentialHelper(),
                new CredentialEntity()
            ),
            new CredentialsEndpoints()
        );

        return new self($restApiHandler);
    }

    public function run(): void
    {
        $this->registerHooks();
        $this->activate();
    }

    private function registerHooks(): void
    {
        // Register ceremonies
        $this->restApiHandler::register($this->restApiHandler);
        // Register form and front-end
        EnqueueAssets::register();
        FormHandler::register();
        // Register admin
        PluginSettings::register();
        UserSettings::register();
    }

    public function activate(): void
    {
        $this->createCredentialsTable();
        $this->setDefaultPluginOptions();
        $this->setPluginVersion();
    }


    private function createCredentialsTable(): void
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

    private function setDefaultPluginOptions(): void
    {
        $this->setDefaultOption('wppk_require_userdata', []);
        $this->setDefaultOption('wppk_passkeys_redirect', admin_url());
        $this->setDefaultOption('wppk_passkeys_timeout', 30000);
        $this->setDefaultOption('wppk_prompt_password_users', 'off');
        $this->setDefaultOption('wppk_remove_password_field', 'off');
    }

    private function setDefaultOption(string $optionName, string|array $defaultValue): void
    {
        if (get_option($optionName) === false) {
            update_option($optionName, $defaultValue);
        }
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
