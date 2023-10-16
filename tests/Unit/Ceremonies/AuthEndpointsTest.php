<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Ceremonies;

use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use WP_REST_Request;
use WP_REST_Response;
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
                                        'validateAuthenticatorAssertionResponse'
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

    public function testGetVerifiedResponse()
    {
        $verifiedResponse = ['status' => 'Verified', 'statusText' => 'Successfully verified the credential.'];
        $this->authEndpoints->verifiedResponse = $verifiedResponse;
        $this->assertEquals($verifiedResponse, $this->authEndpoints->getVerifiedResponse());
    }

    public function testGetAuthenticatorAssertionResponse()
    {
        $publicKeyCredentialMock = $this->createMock(PublicKeyCredential::class);
        $authenticatorAssertionResponseMock = $this->createMock(AuthenticatorAssertionResponse::class);

        $publicKeyCredentialMock->response = $authenticatorAssertionResponseMock;

        $response = $this->authEndpoints->getAuthenticatorAssertionResponse($publicKeyCredentialMock);
        $this->assertInstanceOf(AuthenticatorAssertionResponse::class, $response);

        $publicKeyCredentialMock->response = new stdClass();
        $this->expectException(InvalidArgumentException::class);
        $this->authEndpoints->getAuthenticatorAssertionResponse($publicKeyCredentialMock);
    }
}
