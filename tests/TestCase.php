<?php

namespace WpPasskeys\Tests;

use Mockery;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey\Functions;
use Brain\Monkey;

class TestCase extends PHPUnitTestCase {

    // Adds Mockery expectations to the PHPUnit assertions count.
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        Functions\stubTranslationFunctions();

        Mockery::mock('WP_Error');
        Mockery::mock('WP_REST_Response');
    }

    protected function tearDown(): void
    {
        tearDown();
        Mockery::close();
        parent::tearDown();
    }
}