<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Ceremonies;

use InvalidArgumentException;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialLoader;
use WP_REST_Request;
use WpPasskeys\Ceremonies\PublicKeyCredentialParameters;
use WpPasskeys\Ceremonies\RegisterEndpoints;
use WpPasskeys\Credentials\CredentialEntityInterface;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\Tests\TestCase;
use WpPasskeys\UtilitiesInterface;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class RegisterEndpointsTest extends TestCase {

    protected $registerEndpoints;
    protected $mockHelper;
    protected $mockEntity;
    protected $mockUtilities;
    protected $mockSession;
    protected $mockPkParameters;
    protected $mockPkCredential;
    protected $mockAttestationManager;
    protected $mockRequest;
    protected $dummyCreationOptions;
    protected $dummyRequestOptions;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->mockRequest = Mockery::mock(WP_REST_Request::class);
        $this->mockHelper = Mockery::mock(CredentialHelperInterface::class);
        $this->mockEntity = Mockery::mock(CredentialEntityInterface::class);
        $this->mockUtilities = Mockery::mock(UtilitiesInterface::class);
        $this->mockSession = Mockery::mock(SessionHandlerInterface::class);
        $this->mockPkParameters = Mockery::mock(PublicKeyCredentialParameters::class);
        $this->mockPkCredential = Mockery::mock(PublicKeyCredentialLoader::class);
        $this->mockAttestationManager = Mockery::mock(AttestationStatementSupportManager::class);
        $this->registerEndpoints = Mockery::mock(RegisterEndpoints::class)->makePartial();

    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testCreatePublicKeyCredentialOptions(): void {
        $this->mockHelper->shouldReceive('getUserLogin')
                         ->andReturn('testuser');
        $this->registerEndpoints->shouldReceive('creationsOptions')
                                ->andReturn();
        $this->mockHelper->shouldReceive('saveSessionCredentialOptions');
        $this->addToAssertionCount(1);
    }

    public function testGetAuthenticatorAttestationResponse(): void
    {
        $this->registerEndpoints->shouldReceive('getPkCredentialResponse')
                              ->andReturn(null);
        $publicKeyCredentialMock = $this->createMock(PublicKeyCredential::class);
        $this->expectException(InvalidArgumentException::class);
        $this->registerEndpoints->getAuthenticatorAttestationResponse($publicKeyCredentialMock);
    }
}