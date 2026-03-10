<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\Teams;
use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Models\Document;
use Breezedoc\Models\Template;
use Breezedoc\Pagination\PaginatedResult;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class TeamsTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private Teams $teams;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $config = new Configuration('test-token');
        $factory = new Psr17Factory();
        $requestBuilder = new RequestBuilder($config, $factory, $factory);
        $this->teams = new Teams($this->httpClient, $requestBuilder);
    }

    public function testDocumentsReturnsPaginatedResult(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'current_page' => 1,
            'data' => [
                ['id' => 1, 'title' => 'Doc 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
            ],
            'per_page' => 10,
            'total' => 1,
            'last_page' => 1,
        ]));

        $result = $this->teams->documents(123);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertInstanceOf(Document::class, $result->getItems()[0]);

        $requests = $this->httpClient->getRequests();
        $this->assertStringEndsWith('/teams/123/documents', (string) $requests[0]->getUri());
    }

    public function testDocumentsWithParams(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'current_page' => 1,
            'data' => [],
            'per_page' => 10,
            'total' => 0,
            'last_page' => 1,
        ]));

        $this->teams->documents(123, ['order_by' => 'created_at', 'direction' => 'desc', 'completed' => 'true']);

        $requests = $this->httpClient->getRequests();
        $uri = (string) $requests[0]->getUri();
        $this->assertStringContainsString('order_by=created_at', $uri);
        $this->assertStringContainsString('direction=desc', $uri);
        $this->assertStringContainsString('completed=true', $uri);
    }

    public function testTemplatesReturnsPaginatedResult(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'current_page' => 1,
            'data' => [
                ['id' => 1, 'title' => 'Template 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
            ],
            'per_page' => 10,
            'total' => 1,
            'last_page' => 1,
        ]));

        $result = $this->teams->templates(123);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertInstanceOf(Template::class, $result->getItems()[0]);

        $requests = $this->httpClient->getRequests();
        $this->assertStringEndsWith('/teams/123/templates', (string) $requests[0]->getUri());
    }
}
