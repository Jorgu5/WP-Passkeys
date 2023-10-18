<?php

namespace WpPasskeys\Tests\Unit\Ceremonies;

use Mockery;
use WpPasskeys\AlgorithmManager\AlgorithmManager;
use WpPasskeys\Ceremonies\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialParameters as WebauthnParams;
use WpPasskeys\Ceremonies\PublicKeyCredentialParametersFactory;
use WpPasskeys\Tests\TestCase;

class PublicKeyCredentialParametersTest extends TestCase {

    public function testGet(): void
    {
        $mockParameters = Mockery::mock(WebauthnParams::class);
        $mockAlgorithmManager = Mockery::mock(AlgorithmManager::class);
        $mockAlgorithmManager->shouldReceive('getAlgorithmIdentifiers')
                             ->once()
                             ->andReturn([1, 2, 3]);

        $mockFactory = Mockery::mock(PublicKeyCredentialParametersFactory::class);
        $mockFactory->shouldReceive('create')
                    ->times(3)
                    ->andReturn($mockParameters);  // Return the mock instead of a string

        $publicKeyCredentialParameters = new PublicKeyCredentialParameters($mockAlgorithmManager, $mockFactory);

        $result = $publicKeyCredentialParameters->get();

        $this->assertCount(3, $result);
        $this->assertSame($mockParameters, $result[0]);
        $this->assertSame($mockParameters, $result[1]);
        $this->assertSame($mockParameters, $result[2]);
    }


}