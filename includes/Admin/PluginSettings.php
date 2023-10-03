<?php

declare(strict_types=1);

namespace WpPasskeys\Admin;

use WpPasskeys\Traits\SingletonTrait;

class PluginSettings
{
    use SingletonTrait;

    public function init(): void
    {
        add_action('admin_init', array($this, 'setupSettings'));
        add_action('admin_menu', array($this, 'addAdminMenu'));
    }

    public function addAdminMenu(): void
    {
        add_options_page(
            'Passkeys Settings',
            'Passkeys',
            'manage_options',
            'wppk_passkeys_settings',
            array($this, 'settingsPageContent')
        );
    }

    public function settingsPageContent(): void
    {
        ?>
        <div class="wrap">
            <h2>Passkeys Settings</h2>
            <form action="options.php" method="POST">
                <?php
                settings_fields('general');
                do_settings_sections('general');
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
            'General',
            [$this, 'settingsGeneralSectionCallback'],
            'general'
        );

        add_settings_section(
            'wppk_password_settings_section',
            'Users with legacy password',
            [$this, 'settingsPasswordSectionCallback'],
            'general'
        );

        add_settings_field(
            'wppk_login_priority_type',
            'Allow passkeys',
            [$this, 'loginPriorityCallback'],
            'general',
            'wppk_password_settings_section',
            [
                'description' => 'If user device and browser have support for WebAuthn, 
                he will be prompted to add passkeys on his next login that will be tied to his existing account.',
            ]
        );
        register_setting('general', 'wppk_login_priority_type', 'sanitize_text_field');

        add_settings_field(
            'wppk_allow_registration',
            'Allow registration',
            [$this, 'allowRegistrationCallback'],
            'general',
            'wppk_general_settings_section',
            [
                'description' => 'Allow new users to register with a passkey. If left unchecked, 
                only registered users with password will be able to add passkeys to their account.',
            ]
        );
        register_setting('general', 'wppk_login_priority_type', 'sanitize_text_field');

        add_settings_field(
            'wppk_require_userdata',
            'User details',
            [$this, 'requireUserdataCallback'],
            'general',
            'wppk_general_settings_section',
            [
                'description' => 'Choose what user details are required to be entered by the user on registration.',
                'additional_note' => 'If you choose "Require nothing" the user will be recognized by the passkey only
                and his username will be randomly generated.'
            ],
        );
        register_setting('general', 'wppk_require_userdata', 'boolval');

        add_settings_field(
            'wppk_passkeys_redirect',
            'Redirect URL',
            [$this, 'passkeysRedirectCallback'],
            'general',
            'wppk_general_settings_section',
            [
                'description' => 'The URL to redirect to after a successful login with passkeys.'
            ]
        );
        register_setting('general', 'wppk_passkeys_redirect', 'sanitize_url');

        add_settings_field(
            'wppk_passkeys_timeout',
            'Timeout',
            [$this, 'passkeysTimeoutCallback'],
            'general',
            'wppk_general_settings_section',
            [
                'description' => 'The time (in milliseconds) that the user has to respond 
                to a prompt for registration before an error is returned.'
            ]
        );
        register_setting('general', 'wppk_passkeys_timeout', 'intval');
    }

    public function settingsGeneralSectionCallback(): void
    {
        echo '';
    }

    public function settingsPasswordSectionCallback(): void
    {
        echo 'Specifically for users who have registered with a password before passkeys';
    }

    public function requireUserdataCallback(array $args): void
    {
        $setting = get_option('wppk_require_userdata');
        if (isset($args['description'])) {
            echo "<p class='description'>{$args['description']}</p>";
        }
        $items = array(
            "Require email only",
            "Require email and display name",
            "Require display name only",
            "Require nothing"
        );
        foreach ($items as $item) {
            $checked = ($setting === $item) ? 'checked="checked"' : '';
            echo "<input type='radio' name='wppk_require_userdata' value='$item' $checked>$item<br>";
        }
        if (isset($args['additional_note'])) {
            echo "<span class='additional_note'>Note: {$args['additional_note']}</span>";
        }
    }

    public function loginPriorityCallback(array $args): void
    {
        $option = get_option('login_priority_type');
        $items = array("Default WP Login", "Passkeys");
        echo "<select id='login_priority_type' name='login_priority_type'>";
        foreach ($items as $item) {
            $selected = ($option === $item) ? 'selected="selected"' : '';
            echo "<option value='$item' $selected>$item</option>";
        }
        echo "</select>";
        if (isset($args['description'])) {
            echo "<span class='description'>{$args['description']}</span>";
        }
    }

    public function passkeysRedirectCallback($args): void
    {
        $option = get_option('wppk_passkeys_redirect');
        echo "<input type='text' name='wppk_passkeys_redirect' value='$option'>";
        if (isset($args['description'])) {
            echo "<span class='description'>{$args['description']}</span>";
        }
    }

    public function passkeysTimeoutCallback($args): void
    {
        $option = get_option('wppk_passkeys_timeout');
        echo "<input type='number' placeholder='3000' name='wppk_passkeys_timeout' value='$option'>";
        if (isset($args['description'])) {
            echo "<span class='description'>{$args['description']}</span>";
        }
    }

    public function allowRegistrationCallback($args): void
    {
        $option = get_option('wppk_allow_registration');
        $checked = ($option === 'on') ? 'checked="checked"' : '';
        echo "<input type='checkbox' name='wppk_allow_registration' $checked>";
        if (isset($args['description'])) {
            echo "<span class='description'>{$args['description']}</span>";
        }
    }
}
