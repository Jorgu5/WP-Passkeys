<?php

namespace WpPasskeys\Tests\Unit\Credentials;

use Mockery;
use PHPUnit\Framework\TestCase;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use WpPasskeys\Exceptions\CredentialException;
use WpPasskeys\Credentials\CredentialHelper;
use WpPasskeys\Credentials\CredentialHelperInterface;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\UtilitiesInterface;

class CredentialHelperTest extends TestCase
{
    private CredentialHelper $credentialHelper;
    private $credentialSourceAlias;
    private $mockUserEntity;
    private $mockDescriptor;
    private $mockCredentialHelper;
    private $mockUtilities;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wpdb'] = Mockery::mock('overload:wpdb');
        $sessionHandler = Mockery::mock(SessionHandlerInterface::class);
        $this->mockUserEntity = Mockery::mock(PublicKeyCredentialUserEntity::class);
        $this->mockDescriptor = Mockery::mock(PublicKeyCredentialDescriptor::class);
        $this->credentialSourceAlias = Mockery::mock('alias:Webauthn\PublicKeyCredentialSource');
        $this->mockCredentialHelper = Mockery::mock(CredentialHelper::class, CredentialHelperInterface::class)
                                             ->makePartial()
                                             ->shouldIgnoreMissing();
        $this->mockUtilities = Mockery::mock(UtilitiesInterface::class);
        $this->credentialHelper = new CredentialHelper($sessionHandler);
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

        $this->credentialSourceAlias->publicKeyCredentialId = 'non_existing_id';

        Mockery::mock('alias:WpPasskeys\Utilities')
               ->shouldReceive('safeEncode')
               ->andReturn($this->credentialSourceAlias->publicKeyCredentialId);

        $wpdb->shouldReceive('prepare')->andReturn(null);
        $wpdb->shouldReceive('get_var')->andReturn(null);

        $wpdb->insert_id = 1;

        $this->credentialHelper->saveCredentialSource($this->credentialSourceAlias);

        $this->addToAssertionCount(1);
    }

    public function testSaveCredentialSourceFails()
    {
        global $wpdb;

        $wpdb->shouldReceive('insert')->once()->andReturn(null);
        $wpdb->insert_id = 0;

        $this->expectException(CredentialException::class);

        $this->credentialSourceAlias->publicKeyCredentialId = 'non_existing_id';

        $this->mockCredentialHelper->shouldReceive('findOneByCredentialId')->andReturn(null);
        $this->mockCredentialHelper->saveCredentialSource($this->credentialSourceAlias);
    }
}
