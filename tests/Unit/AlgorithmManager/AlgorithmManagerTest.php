<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\AlgorithmManager;

use PHPUnit\Framework\TestCase;
use WpPasskeys\AlgorithmManager\AlgorithmManager;

class AlgorithmManagerTest extends TestCase
{
    protected \PHPUnit\Framework\MockObject\MockObject|AlgorithmManager $algorithmManager;

    protected function setUp(): void
    {
        $this->algorithmManager = $this->getMockBuilder(AlgorithmManager::class)
                                       ->onlyMethods(['has'])
                                       ->getMock();
    }

    public function testHasValidAlgorithm(): void
    {
        $this->algorithmManager->expects($this->once())
                               ->method('has')
                               ->with(-7)  // ES256 identifier
                               ->willReturn(true);

        $this->assertTrue($this->algorithmManager->has(-7));
    }

    public function testHasInvalidAlgorithm(): void
    {
        $this->algorithmManager->expects($this->once())
                               ->method('has')
                               ->with(9999)  // Invalid identifier
                               ->willReturn(false);

        $this->assertFalse($this->algorithmManager->has(9999));
    }

}
