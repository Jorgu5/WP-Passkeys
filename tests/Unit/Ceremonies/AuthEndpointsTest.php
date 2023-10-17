<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Ceremonies;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;
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
    protected AuthEndpoints $authEndpoints;
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
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Mockery::mock('WP_Error');
        $this->mockRequest = Mockery::mock('WP_REST_Request');

        $this->mockLoader = Mockery::mock(PublicKeyCredentialLoader::class);
        $this->mockValidator = Mockery::mock(AuthenticatorAssertionResponseValidator::class);
        $this->mockHelper = Mockery::mock(CredentialHelperInterface::class);
        $this->mockManager = Mockery::mock(AlgorithmManagerInterface::class);
        $this->mockUtilities = Mockery::mock(UtilitiesInterface::class);
        $this->mockSession = Mockery::mock(SessionHandlerInterface::class);
        $this->mockRequest = Mockery::mock(WP_REST_Request::class);
        $this->mockCredential = Mockery::mock(PublicKeyCredential::class);
        $this->mockAssertion = Mockery::mock(AuthenticatorAssertionResponse::class);

        Mockery::mock('WP_REST_Response')->shouldReceive('get_status');

        $this->authEndpoints = $this->getMockBuilder(AuthEndpoints::class)
                                    ->setConstructorArgs([
                                        $this->mockLoader,
                                        $this->mockValidator,
                                        $this->mockHelper,
                                        $this->mockManager,
                                        $this->mockUtilities,
                                        $this->mockSession
                                    ])
                                    ->setMethods([
                                        'createOptions',
                                        'getPkCredential',
                                        'getAuthenticatorAssertionResponse',
                                        'validateAuthenticatorAssertionResponse',
                                        'getResponseFromPkCredential'
                                    ])
                                    ->getMock();

        $this->dummyRequestOptions = new PublicKeyCredentialRequestOptions(
            random_bytes(32),
            'example.com',
            [],
            null,
            null,
            null,
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testCreatePublicKeyCredentialOptionsLogic(): void
    {
        $this->mockUtilities->shouldReceive('getHostname')
                            ->once()
                            ->andReturn('example.com');
        $this->mockSession->shouldReceive('set')
                          ->once()
                          ->withArgs([AuthEndpoints::SESSION_KEY, $this->dummyRequestOptions]);

        $this->authEndpoints->expects($this->once())
                            ->method('createOptions')
                            ->willReturn($this->dummyRequestOptions);  // replace with your expected return value

        // Execute the method
        $this->authEndpoints->createPublicKeyCredentialOptions($this->mockRequest);
    }

    public function testVerifyPublicKeyCredentialsLogic()
    {
        $this->authEndpoints->expects($this->once())
                          ->method('getPkCredential')
                          ->willReturn($this->mockCredential);

        $this->authEndpoints->expects($this->once())
                          ->method('getAuthenticatorAssertionResponse')
                          ->willReturn($this->mockAssertion);

        // Mock the validateAuthenticatorAssertionResponse method to ensure it's called correctly
        $this->authEndpoints->expects($this->once())
                          ->method('validateAuthenticatorAssertionResponse')
                          ->with(
                              $this->equalTo($this->mockAssertion),
                              $this->isInstanceOf(WP_REST_Request::class)
                          );

        // Execute the method
        $this->authEndpoints->verifyPublicKeyCredentials($this->mockRequest);
    }

    public function testGetVerifiedResponse(): void
    {
        $verifiedResponse = ['status' => 'Verified', 'statusText' => 'Successfully verified the credential.'];
        $this->authEndpoints->verifiedResponse = $verifiedResponse;
        $this->assertEquals($verifiedResponse, $this->authEndpoints->getVerifiedResponse());
    }

    public function testGetAuthenticatorAssertionResponse(): void
    {
        $authEndpointsPartialMock = Mockery::mock(AuthEndpoints::class)->makePartial();
        $authEndpointsPartialMock->shouldReceive('getResponseFromPkCredential')
                                 ->andReturn(null);  // Return null to trigger the exception.
        $publicKeyCredentialMock = $this->createMock(PublicKeyCredential::class);
        $this->expectException(InvalidArgumentException::class);
        $authEndpointsPartialMock->getAuthenticatorAssertionResponse($publicKeyCredentialMock);
    }

    public function testGetUserLoginWithData(): void
    {
        $this->mockSession->shouldReceive('get')
                                 ->once()
                                 ->with('user_data')
                                 ->andReturn(['user_login' => 'john_doe']);

        $result = $this->authEndpoints->getUserLogin();
        $this->assertEquals('john_doe', $result);
    }

    public function testGetUserLoginWithoutUserLoginKey(): void
    {
        $this->mockSession->shouldReceive('get')
                                 ->once()
                                 ->with('user_data')
                                 ->andReturn(['some_other_key' => 'some_value']);

        $result = $this->authEndpoints->getUserLogin();
        $this->assertEquals('', $result);
    }

    public function testGetUserLoginWithoutArray(): void
    {
        $this->mockSession->shouldReceive('get')
                                 ->once()
                                 ->with('user_data')
                                 ->andReturn(null);

        $result = $this->authEndpoints->getUserLogin();
        $this->assertEquals('', $result);
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
        $this->mockRequest->shouldReceive('get_param')->andReturn('some-id');

        $this->mockHelper->shouldReceive('getUserByCredentialId')
                         ->never();

        $this->mockSession->shouldReceive('get')->once()->with('user_data')->andReturn(['user_login' => 'john_doe']);

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

    public function getRawId(): void
    {
        $this->mockRequest->shouldReceive('get_param')->with('rawId')->andReturn('adh108hd1d');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Raw ID must be a string');

        $this->authEndpoints->getRawId($this->mockRequest);
    }
}
