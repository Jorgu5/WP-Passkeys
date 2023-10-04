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
        ?>
        <div class="wrap">
            <h2><?php __('Passkeys Settings', 'wp-passkeys') ?></h2>
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
            __('General', 'wp-passkeys'),
            [$this, 'settingsGeneralSectionCallback'],
            'general'
        );

        add_settings_section(
            'wppk_password_settings_section',
            __('Users with legacy password', 'wp-passkeys'),
            [$this, 'settingsPasswordSectionCallback'],
            'general'
        );

        add_settings_field(
            'wppk_prompt_password_users',
            __('Prompt for passkeys', 'wp-passkeys'),
            [$this, 'promptPasswordUsers'],
            'general',
            'wppk_password_settings_section',
            [
                'additional_note' => __('Upon first login after activating this plugin,
                prompt the old users to set up their passkeys.', 'wp-passkeys'),
            ]
        );
        register_setting('general', 'wppk_prompt_password_users', 'sanitize_text_field');

        add_settings_field(
            'wppk_login_priority',
            __('Priority', 'wp-passkeys'),
            [$this, 'loginPriorityCallback'],
            'general',
            'wppk_general_settings_section',
            [
                'description' => __(
                    'Select a preferred login method. Choosing "Passkeys" will make
                username and password optional, requiring an extra step for fallback authentication.
                Note: Future updates aim to automatically identify users with passkeys and prompt
                them to add them during their next login, but this feature is not yet available.',
                    'wp-passkeys'
                ),
                'additional_note' => __(
                    'Recommended Setting: For older sites with users primarily using passwords,
                start with "Default WP Login" and switch once the majority have adopted passkeys.
                For new sites, its advisable to select "Passkeys" from the beginning.',
                    'wp-passkeys'
                ),
            ]
        );
        register_setting('general', 'wppk_login_priority', 'sanitize_text_field');

        add_settings_field(
            'wppk_require_userdata',
            'User details',
            [$this, 'requireUserdataCallback'],
            'general',
            'wppk_general_settings_section',
            [
                'description' => __('Select the user information fields that
                must be completed during the registration process.', 'wp-passkeys'),
                'additional_note' => __('If you select "Require nothing"
                the user will be registered with a random username', 'wp-passkeys')
            ],
        );
        register_setting('general', 'wppk_require_userdata');

        add_settings_field(
            'wppk_passkeys_redirect',
            'Redirect URL',
            [$this, 'passkeysRedirectCallback'],
            'general',
            'wppk_general_settings_section',
            [
                'description' => __(
                    'The URL to redirect to after a successful login with passkeys.',
                    'wp-passkeys'
                )
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
                'description' => __(
                    'The time (in milliseconds) that the user has to respond 
                        to a prompt for registration before an error is returned.',
                    'wp-passkeys'
                ),
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
        _e('Specifically for users who have registered with a password before passkeys', 'wp-passkeys');
    }

    public function requireUserdataCallback(array $args): void
    {
        $option = $this->setDefaultOptions('wppk_require_userdata', 'Require email only');
        $this->renderDescription($args);
        $items = [
            "Require email only",
            "Require email and display name",
            "Require display name only",
            "Require nothing"
        ];
        foreach ($items as $item) {
            $checked = ($option === $item) ? 'checked="checked"' : '';
            echo "<input type='radio' name='wppk_require_userdata' value='$item' $checked>$item<br>";
        }
        $this->renderAdditionalNote($args);
    }

    public function loginPriorityCallback(array $args): void
    {
        $option = $this->setDefaultOptions('wppk_login_priority', 'Default WP Login');
        $this->renderDescription($args);
        $items = ["Default WP Login", "Passkeys"];
        echo "<select id='login_priority_type' name='login_priority_type'>";
        foreach ($items as $item) {
            $selected = ($option === $item) ? 'selected="selected"' : '';
            echo "<option value='$item' $selected>$item</option>";
        }
        echo "</select>";
        $this->renderAdditionalNote($args);
    }

    public function passkeysRedirectCallback($args): void
    {
        $option = $this->setDefaultOptions('wppk_passkeys_redirect', admin_url());
        echo "<input type='text' name='wppk_passkeys_redirect' value='$option'>";
        $this->renderDescription($args);
    }

    public function passkeysTimeoutCallback($args): void
    {
        $option = $this->setDefaultOptions('wppk_passkeys_timeout', '3000');
        echo "<input type='number' placeholder='3000' name='wppk_passkeys_timeout' value='$option'>";
        $this->renderDescription($args);
    }

    public function promptPasswordUsers($args): void
    {
        $option = $this->setDefaultOptions('wppk_prompt_password_users', 'off');
        $checked = ($option === 'on') ? 'checked="checked"' : '';
        echo "<input type='checkbox' name='wppk_prompt_password_users' $checked>";
        $this->renderAdditionalNote($args);
    }

    private function renderDescription(array $args): void
    {
        if (isset($args['description'])) {
            echo "<p class='description'>{$args['description']}</p>";
        }
    }

    private function renderAdditionalNote(array $args): void
    {
        if (isset($args['additional_note'])) {
            echo "<span class='additional_note'>{$args['additional_note']}</span>";
        }
    }

    private function setDefaultOptions(string $optionName, string $defaultValue): string
    {
        $option = get_option($optionName);
        if (empty($option)) {
            update_option($optionName, $defaultValue);
        }
        return $option;
    }
}
