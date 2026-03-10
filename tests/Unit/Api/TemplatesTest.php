<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\Templates;
use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Models\Document;
use Breezedoc\Models\Template;
use Breezedoc\Pagination\PaginatedResult;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class TemplatesTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private Templates $templates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $config = new Configuration('test-token');
        $factory = new Psr17Factory();
        $requestBuilder = new RequestBuilder($config, $factory, $factory);
        $this->templates = new Templates($this->httpClient, $requestBuilder);
    }

    public function testListReturnsPaginatedResult(): void
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

        $result = $this->templates->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Template::class, $result->getItems()[0]);
    }

    public function testFindReturnsTemplate(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 123,
            'title' => 'Test Template',
            'slug' => 'test123',
            'created_at' => '2025-01-15T10:30:00.000000Z',
            'updated_at' => '2025-01-15T10:30:00.000000Z',
        ]));

        $template = $this->templates->find(123);

        $this->assertInstanceOf(Template::class, $template);
        $this->assertSame(123, $template->getId());
    }

    public function testCreateDocumentReturnsDocument(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 456,
            'title' => 'Document from Template',
            'slug' => 'doc456',
            'created_at' => '2025-01-20T10:00:00.000000Z',
            'updated_at' => '2025-01-20T10:00:00.000000Z',
        ], 201));

        $document = $this->templates->createDocument(123);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame(456, $document->getId());

        $requests = $this->httpClient->getRequests();
        $this->assertSame('POST', $requests[0]->getMethod());
        $this->assertStringEndsWith('/templates/123/create-document', (string) $requests[0]->getUri());
    }
}
