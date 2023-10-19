<?php

namespace WpPasskeys\Tests\Unit\Credentials;
use Mockery;
use WpPasskeys\Credentials\CredentialEntity;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class CredentialEntityTest extends TestCase
{
    private CredentialEntity $credentialEntity;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->credentialEntity = new CredentialEntity();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testCreateRpEntity(): void
    {
        $blogName = 'My Blog';
        $hostname = 'http://localhost';

        Mockery::mock('alias:WpPasskeys\Utilities')
               ->shouldReceive('getHostname')
               ->once()
               ->andReturn($hostname);

        Functions\when('get_bloginfo')->justReturn($blogName);

        $result = $this->credentialEntity->createRpEntity();

        $this->assertEquals($blogName, $result->getName());
        $this->assertEquals($hostname, $result->getId());
    }

    public function testCreateUserEntity(): void
    {
        $userLogin = 'john';

        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        Functions\when('wp_generate_uuid4')->justReturn($uuid);

        $result = $this->credentialEntity->createUserEntity($userLogin);

        $this->assertEquals($userLogin, $result->getName());
        $this->assertEquals($userLogin, $result->getDisplayName());
    }

    public function testGenerateBinaryId(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $strippedUuid = str_replace('-', '', $uuid);
        $binaryUuid = hex2bin($strippedUuid);
        $encodedValue = base64_encode($binaryUuid);

        Functions\expect('wp_generate_uuid4')
            ->once()
            ->andReturn($uuid);

        $result = $this->credentialEntity->generateBinaryId();

        $this->assertEquals($encodedValue, $result);
    }
}
