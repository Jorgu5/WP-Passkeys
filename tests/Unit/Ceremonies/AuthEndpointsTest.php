<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Ceremonies;

use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialLoader;
use WP_REST_Response;
use WpPasskeys\Ceremonies\AuthEndpoints;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\AlgorithmManager\AlgorithmManager;

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

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Mockery::mock('WP_Error');

        $this->mockLoader = Mockery::mock(PublicKeyCredentialLoader::class);
        $this->mockValidator = Mockery::mock(AuthenticatorAssertionResponseValidator::class);
        $this->mockHelper = Mockery::mock(CredentialHelperInterface::class);
        $this->mockManager = Mockery::mock(AlgorithmManager::class);

        $this->mockUtilities = Mockery::mock('alias:WpPasskeys\Utilities')
            ->shouldReceive('getHostname')
            ->andReturn('example.com');
        $this->mockSession = Mockery::mock('alias:WpPasskeys\SessionHandler')
            ->shouldReceive('set')
            ->andReturn(true);

        $this->mockRequest = Mockery::mock('WP_REST_Request');

        Mockery::mock('WP_REST_Response');

        $this->authEndpoints = new AuthEndpoints(
            $this->mockLoader,
            $this->mockValidator,
            $this->mockHelper,
            $this->mockManager
        );
        $this->authEndpoints->createPublicKeyCredentialOptions($this->mockRequest);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testChallengeLength(): void
    {
        $options = $this->authEndpoints->getOptions();
        $this->assertEquals(32, strlen($options->getChallenge()));
    }

    public function testAllowCredentials(): void
    {
        $options = $this->authEndpoints->getOptions();
        $this->assertEquals([], $options->allowCredentials);
    }

    public function testUserVerification(): void
    {
        $options = $this->authEndpoints->getOptions();
        $this->assertEquals('required', $options->userVerification);
    }

    public function testResponseType(): void
    {
        $response = $this->authEndpoints->createPublicKeyCredentialOptions($this->mockRequest);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
    }

    public function testSessionHandlerSetAndGet(): void
    {
        // Act
        $this->authEndpoints->createPublicKeyCredentialOptions($this->mockRequest);
        // Assert
        $sessionData = SessionHandler::get('pk_credential_request_options');
        $this->assertNotEmpty($sessionData);
    }

    public function testPublicKeyCredentialLoaderCalledWithCorrectBody(): void
    {
        $this->mockLoader->shouldReceive('load')->once()->with('requestBody');

        $this->mockRequest->shouldReceive('get_body')->andReturn('requestBody');

        $this->authEndpoints->verifyPublicKeyCredentials($this->mockRequest);

        $this->addToAssertionCount(1);
    }

    public function testAuthenticatorAssertionResponseValidatorCreateCalledWithExpectedArgs(): void
    {
        $mockedResponse = Mockery::mock(AuthenticatorAssertionResponse::class);

        $this->mockRequest->shouldReceive('get_body')->andReturn('requestBody');

        $mockedPublicKeyCredential = Mockery::mock(PublicKeyCredential::class);
        $mockedPublicKeyCredential->shouldReceive('getResponse')
                                  ->once()
                                  ->andReturn($mockedResponse);

        $mockedPublicKeyCredentialLoader = Mockery::mock(PublicKeyCredentialLoader::class);
        $mockedPublicKeyCredentialLoader->shouldReceive('load')
                                        ->once()
                                        ->andReturn($mockedPublicKeyCredential);

        $finalObject = new \Cose\Algorithm\Manager();
        $mock = \Mockery::mock($finalObject)->makePartial();

        $mock->shouldReceive('init')
                            ->once()->withNoArgs();

        $this->mockRequest->shouldReceive('has_param')->once();

        $authEndpoints = new AuthEndpoints(
            $mockedPublicKeyCredentialLoader,
            $this->mockValidator,
            $this->mockHelper,
            $this->mockManager
        );


        $authEndpoints->verifyPublicKeyCredentials($this->mockRequest);

        $this->addToAssertionCount(1);
    }
}
