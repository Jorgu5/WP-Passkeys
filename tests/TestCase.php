<?php

namespace WpPasskeys\Tests;

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey\Functions;

class TestCase extends PHPUnitTestCase {

    // Adds Mockery expectations to the PHPUnit assertions count.
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        setUp();
        Functions\stubTranslationFunctions();
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }
}