<?php

namespace WpPasskeys\Tests\Unit\AlgorithmManager;

use InvalidArgumentException;
use Mockery;
use WpPasskeys\AlgorithmManager\AlgorithmManager;
use WpPasskeys\Tests\TestCase;

class AlgorithmManagerTest extends TestCase
{
    private $mockedManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockedManager = Mockery::mock(AlgorithmManager::class)->makePartial();
    }

    public function testGetAlgorithmIdentifiers(): void
    {
        $this->mockedManager->shouldReceive('getAlgorithmIdentifiers')
                            ->once()
                            ->andReturn([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]);

        $result = $this->mockedManager->getAlgorithmIdentifiers();
        $this->assertIsArray($result);
        $this->assertCount(13, $result);
    }

    public function testGet(): void
    {
        $mockAlgorithm = Mockery::mock('Cose\Algorithm\Algorithm'); // Mock the algorithm object
        $identifier = -7; // For ES256
        $this->mockedManager->shouldReceive('get')
                            ->with($identifier)
                            ->once()
                            ->andReturn($mockAlgorithm);

        $mockAlgorithm->shouldReceive('identifier')
                            ->once()
                            ->andReturn($identifier);

        $result = $this->mockedManager->get($identifier);
        $this->assertEquals($identifier, $result::identifier());
    }

    public function testHas(): void
    {
        $identifier = -7; // For ES256
        $this->mockedManager->shouldReceive('has')
                            ->with($identifier)
                            ->once()
                            ->andReturnTrue();

        $result = $this->mockedManager->has($identifier);
        $this->assertTrue($result);
    }

    public function testGetThrowsExceptionForInvalidIdentifier(): void
    {
        $this->mockedManager->shouldReceive('get')
                            ->with(-999)
                            ->once()
                            ->andThrow(InvalidArgumentException::class);

        $this->expectException(InvalidArgumentException::class);
        $this->mockedManager->get(-999); // An unsupported identifier
    }
}
