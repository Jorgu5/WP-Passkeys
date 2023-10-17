<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Admin;

use Brain\Monkey\Expectation\Exception\ExpectationArgsRequired;
use Brain\Monkey\Functions;
use WpPasskeys\Admin\PluginSettings;
use WpPasskeys\Tests\TestCase;

/**
 * @covers \WpPasskeys\Admin\PluginSettings
 */
class PluginSettingsTest extends TestCase
{
    /**
     * @throws ExpectationArgsRequired
     */
    public function testRegister(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('admin_init', \Mockery::type('array'));

        Functions\expect('add_action')
            ->once()
            ->with('admin_menu', \Mockery::type('array'));

        PluginSettings::register();
    }

    public function testSettingsPageContentWithoutPermission(): void
    {
        Functions\expect('current_user_can')
            ->once()
            ->andReturn(false);

        $pluginSettings = new PluginSettings();
        $pluginSettings->settingsPageContent();

        $this->expectOutputString('');  // No output expected
    }

    /**
     * @throws ExpectationArgsRequired
     */
    public function testSettingsPageContentWithPermission(): void
    {
        Functions\expect('current_user_can')
            ->once()
            ->andReturn(true);

        Functions\expect('settings_fields')
            ->once()
            ->with('passkeys-options');

        Functions\expect('do_settings_sections')
            ->once()
            ->with('passkeys-options');

        Functions\expect('submit_button')
            ->once();

        $pluginSettings = new PluginSettings();

        Functions\stubTranslationFunctions();
        ob_start();
        $pluginSettings->settingsPageContent();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div class="wrap">', $output);
    }

    /**
     * @throws ExpectationArgsRequired
     */
    public function testAddAdminMenu(): void
    {
        Functions\expect('add_options_page')
            ->once()
            ->with(
                'Passkeys Settings',
                'Passkeys',
                'manage_options',
                'wppk_passkeys_settings',
                \Mockery::type('array')
            );

        $pluginSettings = new PluginSettings();
        $pluginSettings->addAdminMenu();
    }

    public function testSetupSettings(): void
    {
        Functions\expect('add_settings_section')->times(2);
        Functions\expect('add_settings_field')->times(5);
        Functions\expect('register_setting')->times(5);

        $pluginSettings = new PluginSettings();
        $pluginSettings->setupSettings();
    }

    /**
     * @throws ExpectationArgsRequired
     */
    public function testRequireUserdataCallback(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('wppk_require_userdata')
            ->andReturn([]);
        Functions\expect('update_option')
            ->once()
            ->with('wppk_require_userdata', []);

        $pluginSettings = new PluginSettings();
        ob_start();
        $pluginSettings->requireUserdataCallback(['label' => 'test label']);
        ob_get_clean();
    }

    /**
     * @throws ExpectationArgsRequired
     */
    public function testRemoveLoginPassword(): void
    {
        $capturedOptionName = null;

        Functions\expect('get_option')
            ->once()
            ->with('wppk_remove_password_field')
            ->andReturn([]);

        Functions\expect('update_option')
            ->once()
            ->andReturnUsing(function ($optionName, $value) use (&$capturedOptionName) {
                $this->assertSame('off', $value);
                $capturedOptionName = $optionName;
                return $optionName;
            });

        $pluginSettings = new PluginSettings();

        ob_start();
        $pluginSettings->removeLoginPassword(['label' => 'test label']);
        $output = ob_get_clean();

        $expectedOutput = "<label for={$capturedOptionName}>test label</label>";
        $this->assertStringContainsString($expectedOutput, $output);
    }
}
