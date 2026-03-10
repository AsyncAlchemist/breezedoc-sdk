<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\Recipients;
use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Models\Recipient;
use Breezedoc\Pagination\PaginatedResult;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class RecipientsTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private Recipients $recipients;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $config = new Configuration('test-token');
        $factory = new Psr17Factory();
        $requestBuilder = new RequestBuilder($config, $factory, $factory);
        $this->recipients = new Recipients($this->httpClient, $requestBuilder);
    }

    public function testListReturnsPaginatedResult(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                ['id' => 1, 'slug' => 'r1', 'name' => 'John', 'email' => 'john@example.com', 'party' => 1, 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
                ['id' => 2, 'slug' => 'r2', 'name' => 'Jane', 'email' => 'jane@example.com', 'party' => 2, 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
            ],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 2, 'last_page' => 1],
        ]));

        $result = $this->recipients->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Recipient::class, $result->getItems()[0]);
    }

    public function testListWithParams(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 0, 'last_page' => 1],
        ]));

        $this->recipients->list(['order_by' => 'completed_at', 'direction' => 'desc']);

        $requests = $this->httpClient->getRequests();
        $uri = (string) $requests[0]->getUri();
        $this->assertStringContainsString('order_by=completed_at', $uri);
        $this->assertStringContainsString('direction=desc', $uri);
    }
}
