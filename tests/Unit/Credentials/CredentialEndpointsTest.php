<?php

namespace WpPasskeys\Tests\Unit\Credentials;

use Mockery;
use WpPasskeys\Credentials\CredentialEndpoints;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Credentials\SessionHandlerInterface;
use Brain\Monkey\Functions;
use WpPasskeys\Exceptions\InvalidCredentialsException;
use WpPasskeys\Tests\TestCase;

class CredentialEndpointsTest extends TestCase
{
    private CredentialEndpoints $credentialEndpoints;
    private SessionHandlerInterface $mockSessionHandler;
    private $wpMockRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSessionHandler = Mockery::mock(SessionHandler::class, SessionHandlerInterface::class);
        $this->wpMockRequest = Mockery::mock('WP_REST_Request')->makePartial();

        $this->credentialEndpoints = new CredentialEndpoints(
            $this->mockSessionHandler
        );

        $this->dummyUserData = [
            'user_login' => 'dumby',
            'user_email' => 'bumby@dumby.com',
            'display_name' => 'rumby boe',
        ];
    }

    public function testSetUserCredentialsWithNoData(): void {
        $this->wpMockRequest->shouldReceive('get_params')
                            ->once()
                            ->andReturn([]);
        $this->mockSessionHandler->shouldReceive('set')
                             ->never();
        $this->credentialEndpoints->setUserCredentials($this->wpMockRequest);
    }

    public function testSetUserCredentialsFiltersUserDataCorrectly(): void
    {
        $sampleUserData = [
            'user_email' => $this->dummyUserData['user_email'],
            'user_login' => $this->dummyUserData['user_login'],
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => $this->dummyUserData['display_name'],
            'password' => 'superSecret'
        ];

        $this->wpMockRequest->shouldReceive('get_params')
                    ->once()
                    ->andReturn($sampleUserData);

        Functions\expect('sanitize_email')->once()->with('bumby@dumby.com')->andReturn('bumby@dumby.com');
        Functions\expect('sanitize_text_field')->once()->with('dumby')->andReturn('dumby');
        Functions\expect('sanitize_text_field')->once()->with('rumby boe')->andReturn('rumby boe');

        $this->mockSessionHandler->shouldReceive('set')
                       ->once()
                       ->with('user_data', $this->dummyUserData);

        $this->credentialEndpoints->setUserCredentials($this->wpMockRequest);
    }

    public function testRemoveUserCredentialsThrowsExceptionIfNoPkCredentialId(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('No pk_credential_id found for this user in meta');

        Functions\expect('get_current_user_id')->once()->andReturn(1);
        Functions\expect('get_user_meta')->once()->with(1, 'pk_credential_id', true)->andReturn(false);

        $this->credentialEndpoints->removeUserCredentials($this->wpMockRequest);
    }

    public function testRemoveUserCredentialsThrowsExceptionIfDeleteUserMetaFails(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Failed to remove pk_credential_id meta input');

        Functions\expect('get_current_user_id')->once()->andReturn(1);
        Functions\expect('get_user_meta')->once()->with(1, 'pk_credential_id', true)->andReturn('pk_credential_id');
        Functions\expect('delete_user_meta')->once()->with(1, 'pk_credential_id')->andReturn(false);

        $this->credentialEndpoints->removeUserCredentials($this->wpMockRequest);
    }

    public function testRemoveUserCredentialsThrowsExceptionIfDeleteUserSourceFails(): void
    {
        global $wpdb;

        $wpdb = Mockery::mock('wpdb');

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Failed to remove credential source');

        Functions\expect('get_current_user_id')->once()->andReturn(1);
        Functions\expect('get_user_meta')->once()->with(1, 'pk_credential_id', true)->andReturn('pk_credential_id');
        Functions\expect('delete_user_meta')->once()->with(1, 'pk_credential_id')->andReturn(true);

        $wpdb->shouldReceive('delete')->once()->with('wp_pk_credential_sources', ['pk_credential_id' => 'pk_credential_id'], ['%s'])->andReturn(false);

        Functions\expect('update_user_meta')->once()->with(1, 'pk_credential_id', 'pk_credential_id');

        $this->credentialEndpoints->removeUserCredentials($this->wpMockRequest);
    }


}
