<?php

namespace WpPasskeys\Tests\Unit\Ceremonies;

use Exception;
use InvalidArgumentException;
use WpPasskeys\Tests\TestCase;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use WP_REST_Request;
use WpPasskeys\AlgorithmManager\AlgorithmManagerInterface;
use WpPasskeys\Ceremonies\AuthEndpoints;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\UtilitiesInterface;

use function random_bytes;

class AuthEndpointsTest extends TestCase
{
    protected $authEndpoints;
    protected $mockUtilities;
    protected $mockSession;
    protected $mockRequest;
    protected $mockLoader;
    protected $mockValidator;
    protected $mockHelper;
    protected $mockManager;
    protected $dummyRequestOptions;
    protected $mockCredential;
    protected $mockAssertion;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->mockLoader = Mockery::mock(PublicKeyCredentialLoader::class);
        $this->mockValidator = Mockery::mock(AuthenticatorAssertionResponseValidator::class);
        $this->mockHelper = Mockery::mock(CredentialHelperInterface::class);
        $this->mockManager = Mockery::mock(AlgorithmManagerInterface::class);
        $this->mockUtilities = Mockery::mock(UtilitiesInterface::class);
        $this->mockSession = Mockery::mock(SessionHandlerInterface::class);
        $this->mockRequest = Mockery::mock(WP_REST_Request::class);
        $this->mockCredential = Mockery::mock(PublicKeyCredential::class);
        $this->mockAssertion = Mockery::mock(AuthenticatorAssertionResponse::class);

        $this->authEndpoints = Mockery::mock(AuthEndpoints::class, [
            $this->mockLoader,
            $this->mockValidator,
            $this->mockHelper,
            $this->mockManager,
            $this->mockUtilities,
            $this->mockSession,
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $this->dummyRequestOptions = new PublicKeyCredentialRequestOptions(
            random_bytes(32),
            'example.com',
            [],
            null,
            null,
            null,
        );
    }

    /**
     * @throws Exception
     */
    public function testCreatePublicKeyCredentialOptionsLogic(): void
    {
        $this->mockSession->shouldReceive('set')
                          ->once()->with(AuthEndpoints::SESSION_KEY, $this->dummyRequestOptions);

        $this->authEndpoints->shouldReceive('requestOptions')
                            ->once()
                            ->andReturn($this->dummyRequestOptions);

        Functions\when('get_site_url')->justReturn('https://example.com');
        $this->authEndpoints->createPublicKeyCredentialOptions($this->mockRequest);

        $this->addToAssertionCount(1);

    }

    public function testVerifyPublicKeyCredentialsLogic(): void
    {
        $this->authEndpoints->shouldReceive('getPkCredential')
                            ->once()
                            ->andReturn($this->mockCredential);

        $this->authEndpoints->shouldReceive('getAuthenticatorAssertionResponse')
                            ->once()
                            ->andReturn($this->mockAssertion);

        $this->authEndpoints->shouldReceive('validateAuthenticatorAssertionResponse')
                            ->once()
                            ->with($this->mockAssertion, $this->mockRequest);

        // Execute the method
        $this->authEndpoints->verifyPublicKeyCredentials($this->mockRequest);

        $this->addToAssertionCount(1);

    }

    public function testGetAuthenticatorAssertionResponse(): void
    {
        $authEndpointsPartialMock = Mockery::mock(AuthEndpoints::class)->makePartial();
        $authEndpointsPartialMock->shouldReceive('getPkCredentialResponse')
                                 ->andReturn(null);  // Return null to trigger the exception.
        $publicKeyCredentialMock = $this->createMock(PublicKeyCredential::class);
        $this->expectException(InvalidArgumentException::class);
        $authEndpointsPartialMock->getAuthenticatorAssertionResponse($publicKeyCredentialMock);
    }

    public function testLoginUserWithCookieWithId(): void
    {
        $this->mockRequest->shouldReceive('has_param')->andReturn(true);
        $this->mockRequest->shouldReceive('get_param')->andReturn('some-id');

        $this->mockHelper->shouldReceive('getUserByCredentialId')
                                   ->once()
                                   ->with('some-id')
                                   ->andReturn(123);

        $this->mockUtilities->shouldReceive('setAuthCookie')->once()->with(null, 123);
        $this->authEndpoints->loginUserWithCookie($this->mockRequest);

        $this->addToAssertionCount(1);
    }

    public function testLoginUserWithCookieWithoutId(): void
    {
        $this->mockRequest->shouldReceive('has_param')->andReturn(false);
        $this->mockHelper->shouldReceive('getUserByCredentialId')
                         ->never();
        $this->mockHelper->shouldReceive('getUserLogin')->once()->andReturn('john_doe');
        $this->mockUtilities->shouldReceive('setAuthCookie')->once();
        $this->authEndpoints->loginUserWithCookie($this->mockRequest);

        $this->addToAssertionCount(1);
    }

    public function testEmptyRawId(): void
    {
        $this->mockRequest->shouldReceive('get_param')->with('rawId')->andReturn('');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Raw ID is empty');

        $this->authEndpoints->getRawId($this->mockRequest);
    }

    public function testNumberRawId(): void
    {
        $this->mockRequest->shouldReceive('get_param')->with('rawId')->andReturn(123);

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Raw ID must be a string');

        $this->authEndpoints->getRawId($this->mockRequest);
    }

    public function testGetRawId(): void
    {
        $this->mockRequest->shouldReceive('get_param')->with('rawId')->andReturn('dzidek');
        $this->assertEquals('dzidek', $this->authEndpoints->getRawId($this->mockRequest));
    }
}
