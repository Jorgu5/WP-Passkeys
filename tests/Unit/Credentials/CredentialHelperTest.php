<?php

namespace WpPasskeys\Tests\Unit\Credentials;

use Mockery;
use WpPasskeys\Tests\TestCase;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialUserEntity;
use WpPasskeys\Credentials\UsernameHandler;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use Brain\Monkey\Functions;
use WpPasskeys\Credentials\SessionHandler;

class CredentialHelperTest extends TestCase
{
    private CredentialHelper $credentialHelper;
    private $credentialSourceAlias;
    private $mockUserEntity;
    private $mockDescriptor;
    private $mockCredentialHelper;
    private $mockUsernameHandler;
    private $mockSessionHandler;
    private $creationOptionsAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');

        $this->mockUserEntity = Mockery::mock(PublicKeyCredentialUserEntity::class);
        $this->mockDescriptor = Mockery::mock(PublicKeyCredentialDescriptor::class);
        $this->credentialSourceAlias = Mockery::mock('alias:Webauthn\PublicKeyCredentialSource');
        $this->creationOptionsAlias = Mockery::mock('alias:Webauthn\PublicKeyCredentialCreationOptions');

        $this->mockSessionHandler = Mockery::mock(SessionHandler::class, SessionHandlerInterface::class);
        $this->mockUsernameHandler = Mockery::mock(UsernameHandler::class);

        $this->mockCredentialHelper = Mockery::mock(CredentialHelper::class, CredentialHelperInterface::class, [
            $this->mockSessionHandler,
            $this->mockUsernameHandler,
        ])->makePartial();

        $this->credentialHelper = new CredentialHelper(
            $this->mockSessionHandler,
            $this->mockUsernameHandler
        );

        $this->dummyUserData = [
            'user_login' => 'dumby',
            'user_email' => 'bumby@dumby.com',
            'display_name' => 'rumby',
        ];

        Mockery::mock('alias:WpPasskeys\Utilities')->shouldReceive('safeEncode')->andReturn('some_id');
    }

    public function testFindOneByCredentialIdWithValidData(): void
    {
        global $wpdb;
        $wpdb->shouldReceive('get_var')->andReturn(json_encode(['some' => 'data'], JSON_THROW_ON_ERROR));
        $wpdb->shouldReceive('prepare')->andReturn('some_prepared_sql');
        $this->credentialSourceAlias
            ->shouldReceive('createFromArray')
            ->andReturn($this->credentialSourceAlias);
        $result = $this->credentialHelper->findOneByCredentialId('some_id');
        $this->assertEquals($this->credentialSourceAlias, $result);
        $this->credentialHelper->findOneByCredentialId('some_id');
    }

    public function testFindOneByCredentialIdWithNullData(): void
    {
        global $wpdb;
        $wpdb->shouldReceive('get_var')->andReturn(null);
        $wpdb->shouldReceive('prepare')->andReturn('some_prepared_sql');

        $result = $this->credentialHelper->findOneByCredentialId('some_id');

        $this->assertNull($result);
    }

    public function testFindOneByCredentialIdWithInvalidType(): void
    {
        $this->expectException(\TypeError::class);

        $this->credentialHelper->findOneByCredentialId([]);
    }

    public function testSaveCredentialSourceWithExistingCredential(): void
    {
        global $wpdb;
        $wpdb->shouldReceive('insert')->once()->andReturn(1);

        $this->credentialSourceAlias->publicKeyCredentialId = 'existing_id';

        $wpdb->shouldReceive('prepare')->andReturn(null);
        $wpdb->shouldReceive('get_var')->andReturn(null);

        $wpdb->insert_id = 1;

        $this->credentialHelper->saveCredentialSource($this->credentialSourceAlias);

        $this->addToAssertionCount(1);
    }

    public function testSaveCredentialSourceFails(): void
    {
        global $wpdb;

        $wpdb->shouldReceive('insert')->once()->andReturn(null);
        $wpdb->insert_id = 0;

        $this->expectException(CredentialException::class);

        $this->credentialSourceAlias->publicKeyCredentialId = 'non_existing_id';

        $this->mockCredentialHelper->shouldReceive('findOneByCredentialId')->andReturn(null);
        $this->mockCredentialHelper->saveCredentialSource($this->credentialSourceAlias);
    }

    public function testCreateUserWithPkCredentialId(): void
    {

        $this->dummyUserData['meta_input'] = [
            'pk_credential_id' => 'somePkCredentialId',
        ];

        $this->mockUsernameHandler->shouldReceive('getUserData')->andReturn();

        Functions\when('wp_insert_user')->justReturn(1);
        Functions\expect('is_wp_error')->once()->andReturn(false);

        $this->credentialHelper->createUserWithPkCredentialId('somePkCredentialId');

        $this->assertCount(4, $this->dummyUserData);
    }

    public function testFailToUpdateUserWithPkCredentialId(): void
    {
        $mockWpError = Mockery::mock('WP_Error')
                              ->shouldReceive('get_error_code')
                              ->andReturn('existing_user_login')
                              ->getMock();

        $mockWpError->shouldReceive('get_error_message')
                    ->once()
                    ->andReturn('something not working');

        $this->mockUsernameHandler->shouldReceive('getUserData')->once()->andReturn($this->dummyUserData);
        $this->mockCredentialHelper->shouldAllowMockingProtectedMethods();
        $this->mockCredentialHelper->shouldReceive('getExistingUserId')->once()->andReturn(1);

        Functions\when('wp_insert_user')->justReturn($mockWpError);

        $this->expectException(CredentialException::class);

        $this->mockCredentialHelper->createUserWithPkCredentialId('somePkCredentialId');
    }



    public function testSaveSessionCredentialOptions(): void
    {
        $this->mockSessionHandler->shouldReceive('start')->once();
        $this->mockSessionHandler->shouldReceive('set')->once()->withSomeOfArgs(
            'webauthn_credential_options'
        );

        $this->credentialHelper->saveSessionCredentialOptions($this->creationOptionsAlias);

        $this->addToAssertionCount(1);
    }

    public function testFailToGetSessionCredentialOptions(): void
    {
        $this->mockSessionHandler->shouldReceive('has')->once()->withSomeOfArgs(
            'webauthn_credential_options'
        )->andReturn(false);

        $this->assertNull($this->credentialHelper->getSessionCredentialOptions());
    }

    public function testFailToGetUserForPublicKeySources(): void
    {
        Functions\when('get_user_by')->justReturn(false);
        $this->expectException(CredentialException::class);

        $this->credentialHelper->getUserPublicKeySources('some_username');
    }

    public function testFailToGetUserPublicKeySourcesFromMeta(): void
    {
        $mockUser = Mockery::mock('WP_User');
        $mockUser->ID = 0;

        Functions\when('get_user_by')->justReturn($mockUser);
        Functions\when('get_user_meta')->justReturn(false);
        $this->expectException(CredentialException::class);
        $this->expectExceptionMessage('No credentials assigned to this user.');

        $this->credentialHelper->getUserPublicKeySources('some_username');
    }

    public function testGetUserPublicKeySourcesFromMeta(): void
    {
        $mockUser = Mockery::mock('WP_User');
        $mockUser->ID = 0;

        Functions\when('get_user_by')->justReturn($mockUser);
        Functions\when('get_user_meta')->justReturn('some_pk_credential_id');
        $this->mockCredentialHelper->shouldReceive('findOneByCredentialId')->andReturn($this->credentialSourceAlias);

        $this->mockCredentialHelper->getUserPublicKeySources('some_username');

        $this->addToAssertionCount(1);
    }

    public function testFailToGetUserByCredentialId(): void
    {
        global $wpdb;

        $wpdb->shouldReceive('get_var')->once()->andReturn(null);
        $wpdb->shouldReceive('prepare')->once();

        $this->expectException(CredentialException::class);
        $this->expectExceptionMessage('There is no user with this credential ID');

        $this->credentialHelper->getUserByCredentialId('some_pk_credential_id');
    }

    public function testGetProperUserByCredentialId(): void
    {
        global $wpdb;

        $wpdb->shouldReceive('get_var')->once()->andReturn(1);
        $wpdb->shouldReceive('prepare')->once();

        $user = $this->credentialHelper->getUserByCredentialId('some_pk_credential_id');

        $this->assertIsInt($user);
    }

    public function testGetExistingUserIdWithNoUserLoggedIn(): void
    {
        Functions\when('is_user_logged_in')->justReturn(false);

        $result = $this->mockCredentialHelper->getExistingUserId('some_username');

        $this->assertInstanceOf('WP_Error', $result);
    }

    public function testGetExistingUserIdWithInvalidNonce(): void
    {
         Functions\when('is_user_logged_in')->justReturn(true);
         Functions\when('wp_verify_nonce')->justReturn(false);

         $_POST['wp_passkeys_nonce'] = 'invalid_nonce';


         $result = $this->mockCredentialHelper->getExistingUserId('some_username');

         $this->assertInstanceOf('WP_Error', $result);
    }

    public function testGetExistingUserId(): void
    {
        // Mock global WordPress functions.
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(true);

        $user = new \stdClass();
        $user->ID = 123;
        Functions\when('get_user_by')->justReturn($user);

        $_POST['wp_passkeys_nonce'] = 'valid_nonce';

        $result = $this->credentialHelper->getExistingUserId('some_username');

        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
    }

    public function testGetuserLogin(): void
    {
        $this->mockSessionHandler->shouldReceive('get')->once()->withSomeOfArgs('user_data')->andReturn($this->dummyUserData);

        $result = $this->mockCredentialHelper->getUserLogin();

        $this->assertEquals('dumby', $result);
    }

    public function testGetuserLoginWithNoUserData(): void
    {
        $this->mockSessionHandler->shouldReceive('get')->once()->withSomeOfArgs('user_data')->andReturn([]);

        $result = $this->mockCredentialHelper->getUserLogin();

        $this->assertEquals('', $result);
    }
}
