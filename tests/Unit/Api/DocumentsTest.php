<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\Documents;
use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Models\Document;
use Breezedoc\Models\Recipient;
use Breezedoc\Pagination\PaginatedResult;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class DocumentsTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private Documents $documents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $config = new Configuration('test-token');
        $factory = new Psr17Factory();
        $requestBuilder = new RequestBuilder($config, $factory, $factory);
        $this->documents = new Documents($this->httpClient, $requestBuilder);
    }

    public function testListReturnsPaginatedResult(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'current_page' => 1,
            'data' => [
                ['id' => 1, 'title' => 'Doc 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
                ['id' => 2, 'title' => 'Doc 2', 'slug' => 'b', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
            ],
            'per_page' => 10,
            'total' => 2,
            'last_page' => 1,
        ]));

        $result = $this->documents->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Document::class, $result->getItems()[0]);
    }

    public function testListWithParams(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'current_page' => 2,
            'data' => [],
            'per_page' => 10,
            'total' => 20,
            'last_page' => 2,
        ]));

        $this->documents->list(['page' => 2, 'order_by' => 'created_at', 'direction' => 'desc']);

        $requests = $this->httpClient->getRequests();
        $uri = (string) $requests[0]->getUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('order_by=created_at', $uri);
        $this->assertStringContainsString('direction=desc', $uri);
    }

    public function testFindReturnsDocument(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 123,
            'title' => 'Test Document',
            'slug' => 'test123',
            'created_at' => '2025-01-15T10:30:00.000000Z',
            'updated_at' => '2025-01-15T10:30:00.000000Z',
        ]));

        $document = $this->documents->find(123);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame(123, $document->getId());
        $this->assertSame('Test Document', $document->getTitle());
    }

    public function testCreateReturnsDocument(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 456,
            'title' => 'New Document',
            'slug' => 'new456',
            'created_at' => '2025-01-20T10:00:00.000000Z',
            'updated_at' => '2025-01-20T10:00:00.000000Z',
        ], 201));

        $document = $this->documents->create([
            'title' => 'New Document',
            'recipients' => [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'party' => 1],
            ],
        ]);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame(456, $document->getId());
    }

    public function testSendReturnsDocument(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 123,
            'title' => 'Sent Document',
            'slug' => 'sent123',
            'created_at' => '2025-01-15T10:30:00.000000Z',
            'updated_at' => '2025-01-20T10:00:00.000000Z',
        ]));

        $document = $this->documents->send(123, [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]);

        $this->assertInstanceOf(Document::class, $document);

        $requests = $this->httpClient->getRequests();
        $this->assertSame('POST', $requests[0]->getMethod());
        $this->assertStringEndsWith('/documents/123/send', (string) $requests[0]->getUri());
    }

    public function testRecipientsReturnsPaginatedResult(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'current_page' => 1,
            'data' => [
                ['id' => 1, 'slug' => 'r1', 'name' => 'John', 'email' => 'john@example.com', 'party' => 1, 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
            ],
            'per_page' => 10,
            'total' => 1,
            'last_page' => 1,
        ]));

        $result = $this->documents->recipients(123);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertInstanceOf(Recipient::class, $result->getItems()[0]);
    }

    public function testDownloadPageImagesReturnsImageData(): void
    {
        // First response: find() call to get the document
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 123,
            'title' => 'Test Doc',
            'slug' => 'test123',
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'document_files' => [
                [
                    'id' => 200,
                    'document_file_pages' => [
                        ['id' => 1, 'page' => 1, 'url' => 'https://s3.example.com/page1.jpg'],
                        ['id' => 2, 'page' => 2, 'url' => 'https://s3.example.com/page2.jpg'],
                    ],
                ],
            ],
        ]));

        // Second/third responses: page image fetches
        $page1Data = 'fake-jpeg-page-1';
        $page2Data = 'fake-jpeg-page-2';
        $this->httpClient->addResponse(
            $this->psr17Factory->createResponse(200)
                ->withBody($this->psr17Factory->createStream($page1Data))
        );
        $this->httpClient->addResponse(
            $this->psr17Factory->createResponse(200)
                ->withBody($this->psr17Factory->createStream($page2Data))
        );

        $images = $this->documents->downloadPageImages(123);

        $this->assertCount(2, $images);
        $this->assertSame($page1Data, $images[0]);
        $this->assertSame($page2Data, $images[1]);

        // Verify the S3 requests have no auth headers
        $requests = $this->httpClient->getRequests();
        $this->assertFalse($requests[1]->hasHeader('Authorization'));
        $this->assertFalse($requests[2]->hasHeader('Authorization'));
    }

    public function testDownloadPageImagesEmptyWhenNoFiles(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 123,
            'title' => 'Test Doc',
            'slug' => 'test123',
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'document_files' => [],
        ]));

        $images = $this->documents->downloadPageImages(123);

        $this->assertSame([], $images);
    }

    public function testDownloadPageImagesToSavesFiles(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 123,
            'title' => 'Test Doc',
            'slug' => 'test123',
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
            'document_files' => [
                [
                    'id' => 200,
                    'document_file_pages' => [
                        ['id' => 1, 'page' => 1, 'url' => 'https://s3.example.com/page1.jpg'],
                    ],
                ],
            ],
        ]));

        $pageData = 'fake-jpeg-data';
        $this->httpClient->addResponse(
            $this->psr17Factory->createResponse(200)
                ->withBody($this->psr17Factory->createStream($pageData))
        );

        $dir = sys_get_temp_dir() . '/breezedoc_test_' . uniqid();
        mkdir($dir);

        try {
            $paths = $this->documents->downloadPageImagesTo(123, $dir, 'contract');

            $this->assertCount(1, $paths);
            $this->assertStringEndsWith('contract-1.jpg', $paths[0]);
            $this->assertFileExists($paths[0]);
            $this->assertSame($pageData, file_get_contents($paths[0]));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testDownloadPageImagesThrowsNotFound(): void
    {
        $this->httpClient->addResponse(
            $this->createErrorResponse('Document not found', 404)
        );

        $this->expectException(\Breezedoc\Exceptions\NotFoundException::class);

        $this->documents->downloadPageImages(999);
    }

    public function testDownloadPageImagesToThrowsForBadDirectory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory is not writable');

        $this->documents->downloadPageImagesTo(123, '/nonexistent/path');
    }
}
