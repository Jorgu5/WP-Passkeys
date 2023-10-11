<?php

declare(strict_types=1);

namespace WpPasskeys\Tests\Unit\Ceremonies;

use PHPUnit\Framework\TestCase;
use WpPasskeys\Ceremonies\AuthEndpoints;
use Brain\Monkey\Functions;
use Mockery;
use WP_Error;
use WP_REST_Response;

class AuthEndpointsTest extends TestCase
{
    protected $authEndpoints;
    protected $mockRequest;
    protected $mockError;
    protected $mockResponse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authEndpoints = Mockery::mock(AuthEndpoints::class)->makePartial();

        // Mock WP_REST_Request
        $this->mockRequest = Mockery::mock('WP_REST_Request');
        $this->mockRequest->shouldReceive('get_body')->andReturn('someRequestBody');
        $this->mockRequest->shouldReceive('get_param')->andReturn('someParam');

        // Mock WP_Error
        $this->mockError = Mockery::mock(WP_Error::class);
        $this->mockError->shouldReceive('get_error_data')
                        ->with('status')
                        ->andReturn(400);

        // Mock WP_REST_Response
        $this->mockResponse = Mockery::mock(WP_REST_Response::class);
        $this->mockResponse->shouldReceive('set_status')->andReturn(200);
        $this->mockResponse->shouldReceive('get_status')->andReturn(200);
        $this->mockResponse->shouldReceive('set_data')->andReturn(['someData']);
        $this->mockResponse->shouldReceive('get_data')->andReturn(['someData']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreatePublicKeyCredentialOptions(): void
    {
        Functions\when('get_site_url')->justReturn('http://example.com');

        $this->authEndpoints->shouldReceive('createPublicKeyCredentialOptions')
                            ->once()
                            ->with($this->mockRequest)
                            ->andReturn($this->mockResponse);

        $result = $this->authEndpoints->createPublicKeyCredentialOptions($this->mockRequest);

        $this->assertEquals(200, $result->get_status());
    }

    public function testVerifyPublicKeyCredentialsSuccess(): void
    {
        $this->authEndpoints->shouldReceive('verifyPublicKeyCredentials')
                            ->once()
                            ->with($this->mockRequest)
                            ->andReturn($this->mockResponse);

        $result = $this->authEndpoints->verifyPublicKeyCredentials($this->mockRequest);

        $this->assertEquals(200, $result->get_status());
    }

    public function testVerifyPublicKeyCredentialsError(): void
    {
        $this->authEndpoints->shouldReceive('verifyPublicKeyCredentials')
                            ->once()
                            ->with($this->mockRequest)
                            ->andReturn($this->mockError);

        $result = $this->authEndpoints->verifyPublicKeyCredentials($this->mockRequest);

        $this->assertEquals(400, $result->get_error_data('status'));
    }
}
