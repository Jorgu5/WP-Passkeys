<?php

namespace WpPasskeys\Tests\Unit\Credentials;

use Mockery;
use WpPasskeys\Credentials\SessionHandlerInterface;
use WpPasskeys\Credentials\UsernameHandler;
use WpPasskeys\Credentials\SessionHandler;
use WpPasskeys\Tests\TestCase;
use Brain\Monkey\Functions;

class UsernameHandlerTest extends TestCase
{
    private UsernameHandler $usernameHandler;
    private SessionHandler $mockSessionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSessionHandler = Mockery::mock(SessionHandler::class, SessionHandlerInterface::class);
        $this->usernameHandler = new UsernameHandler(
            $this->mockSessionHandler
        );

        $this->dummyUserData = [
            'user_login' => 'dumby',
            'user_email' => 'bumby@dumby.com',
            'display_name' => 'rumby',
        ];
        Functions\when('wp_unique_id')->justReturn('user_unique');

    }

    public function usernameDataProvider(): array
    {
        return [
            'Username provided' => ['john', '', '', 'john'],
            'Email provided, display name empty' => ['', 'john@example.com', '', 'john'],
            'Email and display name provided' => ['', 'john@example.com', 'John Doe', 'john'],
            'Display name provided, others empty' => ['', '', 'John Doe', 'johndoe'],
            'All empty' => ['', '', '', ''],
        ];
    }

    public function displayNameDataProvider(): array
    {
        return [
            ['John', 'john', 'john@example.com', 'John'],
            ['', 'jane', 'jane@example.com', 'jane'],
            ['', '', 'mike@example.com', 'mike'],
            ['', '', '', 'user_unique'],
            ['Anna', '', '', 'Anna'],
            ['Anna', 'john', '', 'Anna'],
        ];
    }

    public function getDisplayAndUserNameDataProvider(): array
    {
        return [
            [['user_login' => 'john', 'display_name' => 'John', 'user_email' => 'john@example.com'], ['john', 'John']],
            [[], ['user_unique', 'user_unique']],
            [['user_login' => 'jane'], ['jane', 'jane']],
            [['user_login' => '', 'user_email' => 'mike@example.com'], ['mike', 'mike']],
            [['user_login' => '', 'display_name' => 'Mike'], ['mike', 'Mike']],
        ];
    }

    public function testGetUserDataWithNoSessionData(): void
    {
        $this->mockSessionHandler->shouldReceive('has')
                                 ->once()
                                 ->with('user_data')
                                 ->andReturn(false);
        $this->mockSessionHandler->shouldReceive('get')
                                 ->never();

        $this->assertEmpty($this->usernameHandler->getUserData());
    }

    public function testGetUserDataWithSessionData(): void
    {
        $this->mockSessionHandler->shouldReceive('has')
                                 ->once()
                                 ->with('user_data')
                                 ->andReturn(true);

        $this->mockSessionHandler->shouldReceive('get')
                                 ->once()
                                 ->with('user_data')
                                 ->andReturn($this->dummyUserData);

        $result = $this->usernameHandler->getUserData();

        $this->assertSame($this->dummyUserData['user_login'], $result['user_login']);
    }
    /**
     * @dataProvider usernameDataProvider
     */
    public function testUsername(string $username, string $email, string $displayName, string $expected): void
    {
        $result = $this->usernameHandler->getUsername($username, $email, $displayName);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider displayNameDataProvider
     */
    public function testDisplayName(string $displayName, string $username, string $email, string $expected): void
    {
        $result = $this->usernameHandler->getDisplayName($displayName, $username, $email);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider getDisplayAndUserNameDataProvider
     */

    public function testGetDisplayAndUserName(array $userData, array $expected): void
    {
        $result = $this->usernameHandler->getDisplayAndUserName($userData);
        $this->assertEquals($expected, $result);
    }
}
