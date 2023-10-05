<?php

/**
* List of settings
 * get_option('wppk_login_priority')
 * get_option('wppk_require_userdata')
 * get_option('wppk_passkeys_redirect')
 * get_option('wppk_passkeys_timeout')
 * get_option('wppk_prompt_password_users')
 *
 */

declare(strict_types=1);

namespace WpPasskeys\Admin;

use WpPasskeys\Traits\SingletonTrait;

class PluginSettings
{
    use SingletonTrait;

    public function init(): void
    {
        add_action('admin_init', [$this, 'setupSettings']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
    }

    public function addAdminMenu(): void
    {
        add_options_page(
            'Passkeys Settings',
            'Passkeys',
            'manage_options',
            'wppk_passkeys_settings',
            [$this, 'settingsPageContent']
        );
    }

    public function settingsPageContent(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h2><?php __('Passkeys Settings', 'wp-passkeys') ?></h2>
            <form action="options.php" method="POST">
                <?php
                settings_fields('passkeys-options');
                do_settings_sections('passkeys-options');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function setupSettings(): void
    {
        add_settings_section(
            'wppk_general_settings_section',
            __('General', 'wp-passkeys'),
            [$this, 'settingsGeneralSectionCallback'],
            'passkeys-options'
        );

        add_settings_section(
            'wppk_password_settings_section',
            __('Users with legacy password', 'wp-passkeys'),
            [$this, 'settingsPasswordSectionCallback'],
            'passkeys-options'
        );

        add_settings_field(
            'wppk_prompt_password_users',
            __('Prompt for passkeys', 'wp-passkeys'),
            [$this, 'promptPasswordUsers'],
            'passkeys-options',
            'wppk_password_settings_section',
            [
                'label' => __('Upon first login after activating this plugin,
                prompt the old users to set up their passkeys.', 'wp-passkeys'),
            ]
        );
        register_setting('passkeys-options', 'wppk_prompt_password_users', 'sanitize_text_field');

        add_settings_field(
            'wppk_remove_password_field',
            __('Remove passwords', 'wp-passkeys'),
            [$this, 'loginPriorityCallback'],
            'passkeys-options',
            'wppk_general_settings_section',
            [
                'label' => __(
                    'Use with caution! This setting is recommended only for new sites, or sites
                    where all users have already set up their passkeys however this can greatly 
                    improve security and user experience.',
                    'wp-passkeys'
                ),
            ]
        );
        register_setting('passkeys-options', 'wppk_remove_password_field');

        add_settings_field(
            'wppk_require_userdata',
            'User details',
            [$this, 'requireUserdataCallback'],
            'passkeys-options',
            'wppk_general_settings_section',
            [
                'label' => __('Select the user information fields that
                must be completed during the registration process.', 'wp-passkeys'),
                'additional_note' => __('If you select "Require nothing"
                the user will be registered with a random username', 'wp-passkeys')
            ],
        );
        register_setting('passkeys-options', 'wppk_require_userdata');

        add_settings_field(
            'wppk_passkeys_redirect',
            'Redirect URL',
            [$this, 'passkeysRedirectCallback'],
            'passkeys-options',
            'wppk_general_settings_section',
            [
                'label' => __(
                    'The URL to redirect to after a successful login with passkeys.',
                    'wp-passkeys'
                )
            ]
        );
        register_setting('passkeys-options', 'wppk_passkeys_redirect', 'sanitize_url');

        add_settings_field(
            'wppk_passkeys_timeout',
            'Timeout',
            [$this, 'passkeysTimeoutCallback'],
            'passkeys-options',
            'wppk_general_settings_section',
            [
                'label' => __(
                    'The time (in milliseconds) that the user has to respond 
                        to a prompt for registration before an error is returned.',
                    'wp-passkeys'
                ),
            ]
        );
        register_setting('passkeys-options', 'wppk_passkeys_timeout', 'intval');
    }

    public function settingsGeneralSectionCallback(): void
    {
        echo '';
    }

    public function settingsPasswordSectionCallback(): void
    {
        _e('Specifically for users who have registered with a password before passkeys', 'wp-passkeys');
    }

    public function requireUserdataCallback(array $args): void
    {
        $optionName = 'wppk_require_userdata';
        $option = get_option($optionName);
        $this->renderLabel($args, $optionName);
        $items = [
            "Require email only" => 'email',
            "Require email and display name" => 'email_display_name',
            "Require display name only" => 'display_name',
            "Require email and make display name optional" => 'email_optional_display_name',
            "Require nothing" => 'none'
        ];
        foreach ($items as $itemName => $itemValue) {
            $checked = ($option === $itemValue) ? 'checked="checked"' : '';
            echo "<input type='radio' name='wppk_require_userdata' value='$itemValue' $checked>$itemName<br>";
        }
    }

    public function loginPriorityCallback(array $args): void
    {
        $optionName = 'wppk_remove_password_field';
        $option = get_option($optionName);
        $this->renderLabel($args, $optionName);
        $checked = ($option === 'on') ? 'checked="checked"' : '';
        echo "<input type='checkbox' name='wppk_prompt_password_users' $checked>";
    }

    public function passkeysRedirectCallback($args): void
    {
        $optionName = 'wppk_passkeys_redirect';
        $option = get_option($optionName);
        $this->renderLabel($args, $optionName);
        echo "<input type='text' name='wppk_passkeys_redirect' value='$option'>";
    }

    public function passkeysTimeoutCallback($args): void
    {
        $optionName = 'wppk_passkeys_timeout';
        $option = get_option($optionName);
        $this->renderLabel($args, $optionName);
        echo "<input type='number' placeholder='3000' name='wppk_passkeys_timeout' value='$option'>";
    }

    public function promptPasswordUsers($args): void
    {
        $optionName = 'wppk_prompt_password_users';
        $option = get_option($optionName);
        $this->renderLabel($args, $optionName);
        $checked = ($option === 'on') ? 'checked="checked"' : '';
        echo "<input type='checkbox' name='wppk_prompt_password_users' $checked>";
    }

    private function renderLabel(array $args, string $name): void
    {
        if (isset($args['label'])) {
            echo "<label for=$name>{$args['label']}</label>";
        }
    }
}