<?php

/**
 * List of settings
 * get_option('wppk_require_userdata')
 * get_option('wppk_passkeys_redirect')
 * get_option('wppk_passkeys_timeout')
 * get_option('wppk_prompt_password_users')
 *
 */

declare(strict_types=1);

namespace WpPasskeys\Admin;

class PluginSettings
{
    public static function register(): void
    {
        $pluginSettings = new self();
        add_action('admin_init', [$pluginSettings, 'setupSettings']);
        add_action('admin_menu', [$pluginSettings, 'addAdminMenu']);
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
        ?>
        <div class="wrap">
            <h2><?php
                __('Passkeys Settings', 'wp-passkeys') ?></h2>
            <form action="options.php" method="POST">
                <?php
                do_settings_sections('passkeys-options');
                settings_fields('passkeys-options');
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
                'label' => __(
                    'Upon first login after activating this plugin,
                show dismissible notification to old users for encouraging setting up their passkeys.',
                    'wp-passkeys'
                ),
            ]
        );
        register_setting('passkeys-options', 'wppk_prompt_password_users', 'sanitize_text_field');

        add_settings_field(
            'wppk_require_userdata',
            'Required user data',
            [$this, 'requireUserdataCallback'],
            'passkeys-options',
            'wppk_general_settings_section',
            [
                'label' => __(
                    'Choose the user data to require during registration. 
                If left unchecked, users will be registered usernameless.
                Unchecking both username and email will disable default WordPress registration 
                with a password and force passkey registration.
                ',
                    'wp-passkeys'
                ),
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
                ),
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
                    'The time (in milliseconds) that the user has to respond once they are prompted for registering their passkeys before an error is returned.',
                    'wp-passkeys'
                ),
            ]
        );
        register_setting('passkeys-options', 'wppk_passkeys_timeout');
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
        $option     = get_option($optionName);
        $items      = [
            "Email"        => 'user_email',
            "Username"     => 'user_login',
            "Display name" => 'display_name',
        ];
        if (empty($option)) {
            update_option($optionName, []);
        }

        echo '<fieldset>
            <legend><span>' . __($args['label'], 'wp-passkeys') . '</span></legend>';
        foreach ($items as $itemName => $itemValue) {
            $checked = '';

            if (is_array($option)) {
                $checked = in_array($itemValue, $option, true) ? 'checked="checked"' : '';
            }

            echo "<label for={$optionName}>${itemName}</label>
            <input type='checkbox' name='{$optionName}[]' value='{$itemValue}' $checked>";
        }
        echo '</fieldset>';
    }

    /**
     * @param string[] $args
     */
    public function removeLoginPassword(array $args): void
    {
        $optionName = 'wppk_remove_password_field';
        $option     = get_option($optionName);
        if (empty($option)) {
            update_option($optionName, 'off');
        }
        $this->renderLabel($args, $optionName);
        $checked = ($option === 'on') ? 'checked="checked"' : '';
        echo "<input type='checkbox' name='wppk_remove_password_field' $checked>";
    }

    /**
     * @param string[] $args
     * @param string $name
     */
    private function renderLabel(array $args, string $name): void
    {
        if (isset($args['label'])) {
            echo "<label for=$name>{$args['label']}</label>";
        }
    }

    /**
     * @param string[] $args
     */
    public function passkeysRedirectCallback(array $args): void
    {
        $optionName = 'wppk_passkeys_redirect';
        $option     = get_option($optionName);
        $this->renderLabel($args, $optionName);
        echo "<input type='text' name='wppk_passkeys_redirect' value='$option'>";
    }

    /**
     * @param string[] $args
     */
    public function passkeysTimeoutCallback(array $args): void
    {
        $optionName = 'wppk_passkeys_timeout';
        $option     = get_option($optionName);
        $this->renderLabel($args, $optionName);
        echo "<input type='number' placeholder='30000' name='wppk_passkeys_timeout' value='$option'>";
    }

    /**
     * @param string[] $args
     */
    public function promptPasswordUsers(array $args): void
    {
        $optionName = 'wppk_prompt_password_users';
        $option     = get_option($optionName);
        if (empty($option)) {
            update_option($optionName, 'off');
        }
        $this->renderLabel($args, $optionName);
        $checked = ($option === 'on') ? 'checked="checked"' : '';
        echo "<input type='checkbox' name='wppk_prompt_password_users' $checked>";
    }
}
