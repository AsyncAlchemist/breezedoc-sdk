<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\Users;
use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Models\User;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class UsersTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private Users $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $config = new Configuration('test-token');
        $factory = new Psr17Factory();
        $requestBuilder = new RequestBuilder($config, $factory, $factory);
        $this->users = new Users($this->httpClient, $requestBuilder);
    }

    public function testMeReturnsUser(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2024-04-24T12:56:45.000000Z',
            'updated_at' => '2025-12-23T07:26:24.000000Z',
        ]));

        $user = $this->users->me();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(1, $user->getId());
        $this->assertSame('John Doe', $user->getName());
        $this->assertSame('john@example.com', $user->getEmail());
    }

    public function testMeCallsCorrectEndpoint(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
        ]));

        $this->users->me();

        $requests = $this->httpClient->getRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('GET', $requests[0]->getMethod());
        $this->assertStringEndsWith('/me', (string) $requests[0]->getUri());
    }
}
