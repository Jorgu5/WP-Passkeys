<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use WpPasskeys\Admin\UserSettings;
use WpPasskeys\Tests\TestCase;

class UserSettingsTest extends TestCase
{
    public function testRegister(): void
    {
        Functions\expect('add_action')
            ->andReturnUsing(function ($hook, $callback) {
                $this->assertIsArray($callback);
                return true;
            });

        UserSettings::register();
    }

    public function testDisplayUserPasskeySettings(): void
    {
        Functions\when('current_user_can')->justReturn(true);

        $userSettings = new UserSettings();

        ob_start();
        $userSettings->displayUserPasskeySettings();
        $output = ob_get_clean();

        $this->assertStringContainsString('Your passkeys', $output);
    }

    public function testDisplayAdminPasskeySettings(): void
    {
        $userSettings = new UserSettings();

        ob_start();
        $userSettings->displayAdminPasskeySettings();
        $output = ob_get_clean();

        $this->assertStringContainsString('Clear user passkeys', $output);
    }

    public function testRenderAdminNotice(): void
    {
        Functions\when('get_option')->justReturn('on');
        Functions\when('get_edit_profile_url')->justReturn('http://example.com');

        $userSettings = new UserSettings();

        ob_start();
        $userSettings->renderAdminNotice();
        $output = ob_get_clean();

        $this->assertStringContainsString('Create passkeys', $output);
    }
}
