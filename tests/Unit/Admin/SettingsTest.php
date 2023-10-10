<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Admin;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use PsalmWordPress\Plugin;
use WpPasskeys\Admin\PluginSettings;
use WpPasskeys\Tests\TestCase;

/**
 * @covers \WpPasskeys\Admin\PluginSettings
 */
class SettingsTest extends TestCase
{
    public function testInitWithWordPressFunctions(): void
    {
        $settings = PluginSettings::instance();
        $settings->init();

        self::assertNotFalse(has_action('admin_init', [$settings, 'setupSettings']));
        self::assertNotFalse(has_action('admin_menu', [$settings, 'addAdminMenu']));
    }

    public function testInitWithExpectations(): void
    {

        $settings = PluginSettings::instance();
        Actions\expectAdded('admin_init');
        Actions\expectAdded('admin_menu');
        $settings->init();
    }
}
