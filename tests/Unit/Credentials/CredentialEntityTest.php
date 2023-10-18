<?php

use WpPasskeys\Credentials\CredentialEntity;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Mockery;
use PHPUnit\Framework\TestCase;

class CredentialEntityTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testCreateRpEntity()
    {
        // Arrange
        $credentialEntity = new CredentialEntity();
        $blogName = 'My Blog';
        $hostname = 'http://localhost';

        Mockery::mock('alias:WpPasskeys\Utilities')
               ->shouldReceive('getHostname')
               ->andReturn($hostname);

        Mockery::mock('overload:' . get_bloginfo::class)
               ->shouldReceive('name')
               ->andReturn($blogName);

        // Act
        $result = $credentialEntity->createRpEntity();

        // Assert
        $this->assertInstanceOf(PublicKeyCredentialRpEntity::class, $result);
        $this->assertEquals($blogName, $result->getName());
        $this->assertEquals($hostname, $result->getId());
    }

    public function testCreateUserEntity()
    {
        // Arrange
        $credentialEntity = new CredentialEntity();
        $userLogin = 'john';

        // Act
        $result = $credentialEntity->createUserEntity($userLogin);

        // Assert
        $this->assertInstanceOf(PublicKeyCredentialUserEntity::class, $result);
        $this->assertEquals($userLogin, $result->getName());
        $this->assertEquals($userLogin, $result->getDisplayName());
    }

    public function testGenerateBinaryId()
    {
        // Arrange
        $credentialEntity = new CredentialEntity();

        // Since generateBinaryId is private, we'll use Reflection to test it
        $reflection = new \ReflectionClass($credentialEntity);
        $method = $reflection->getMethod('generateBinaryId');
        $method->setAccessible(true);

        // Mocking wp_generate_uuid4() function
        Mockery::mock('overload:' . wp_generate_uuid4::class)
               ->shouldReceive()
               ->andReturn('123e4567-e89b-12d3-a456-426614174000');

        // Act
        $result = $method->invoke($credentialEntity);

        // Assert
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
