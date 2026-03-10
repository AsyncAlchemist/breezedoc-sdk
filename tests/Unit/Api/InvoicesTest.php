<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Api;

use Breezedoc\Api\Invoices;
use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Models\Invoice;
use Breezedoc\Pagination\PaginatedResult;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

class InvoicesTest extends UnitTestCase
{
    private MockHttpClient $httpClient;
    private Invoices $invoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMockHttpClient();
        $config = new Configuration('test-token');
        $factory = new Psr17Factory();
        $requestBuilder = new RequestBuilder($config, $factory, $factory);
        $this->invoices = new Invoices($this->httpClient, $requestBuilder);
    }

    public function testListReturnsPaginatedResult(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                ['id' => 1, 'slug' => 'inv1', 'currency' => 'USD', 'status' => 'draft', 'description' => 'Test', 'customer_email' => 'a@b.com', 'total' => 100],
            ],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1, 'last_page' => 1],
        ]));

        $result = $this->invoices->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Invoice::class, $result->getItems()[0]);
    }

    public function testFindReturnsInvoice(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                'id' => 123,
                'slug' => 'inv123',
                'currency' => 'USD',
                'status' => 'draft',
                'description' => 'Test Invoice',
                'customer_email' => 'test@example.com',
                'total' => 100.00,
            ],
        ]));

        $invoice = $this->invoices->find(123);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(123, $invoice->getId());
    }

    public function testCreateReturnsInvoice(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                'id' => 456,
                'slug' => 'inv456',
                'currency' => 'USD',
                'status' => 'draft',
                'description' => 'New Invoice',
                'customer_email' => 'customer@example.com',
                'total' => 150.00,
            ],
        ], 201));

        $invoice = $this->invoices->create([
            'customer_email' => 'customer@example.com',
            'currency' => 'usd',
            'description' => 'New Invoice',
            'payment_due' => '2026-02-28',
            'items' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price' => 15000],
            ],
        ]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(456, $invoice->getId());
    }

    public function testCreateUppercasesCurrency(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                'id' => 1,
                'slug' => 'inv1',
                'currency' => 'USD',
                'status' => 'draft',
                'description' => 'Test',
                'customer_email' => 'a@b.com',
                'total' => 100,
            ],
        ], 201));

        $this->invoices->create([
            'customer_email' => 'a@b.com',
            'currency' => 'usd',
            'description' => 'Test',
            'payment_due' => '2026-02-28',
            'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10000]],
        ]);

        $requests = $this->httpClient->getRequests();
        $body = json_decode((string) $requests[0]->getBody(), true);
        $this->assertSame('USD', $body['currency']);
    }

    public function testUpdateReturnsInvoice(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                'id' => 123,
                'slug' => 'inv123',
                'currency' => 'USD',
                'status' => 'draft',
                'description' => 'Updated Invoice',
                'customer_email' => 'test@example.com',
                'total' => 200.00,
            ],
        ]));

        $invoice = $this->invoices->update(123, ['description' => 'Updated Invoice']);

        $this->assertInstanceOf(Invoice::class, $invoice);

        $requests = $this->httpClient->getRequests();
        $this->assertSame('PUT', $requests[0]->getMethod());
    }

    public function testDestroyReturnsTrue(): void
    {
        $body = $this->psr17Factory->createStream('');
        $response = $this->psr17Factory->createResponse(204)->withBody($body);
        $this->httpClient->addResponse($response);

        $result = $this->invoices->destroy(123);

        $this->assertTrue($result);

        $requests = $this->httpClient->getRequests();
        $this->assertSame('DELETE', $requests[0]->getMethod());
    }

    public function testSendReturnsInvoice(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                'id' => 123,
                'slug' => 'inv123',
                'currency' => 'USD',
                'status' => 'sent',
                'description' => 'Sent Invoice',
                'customer_email' => 'test@example.com',
                'total' => 100.00,
                'sent_at' => '2026-01-30T12:00:00.000000Z',
            ],
        ]));

        $invoice = $this->invoices->send(123);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertTrue($invoice->isSent());

        $requests = $this->httpClient->getRequests();
        $this->assertSame('POST', $requests[0]->getMethod());
        $this->assertStringEndsWith('/invoices/123/send', (string) $requests[0]->getUri());
    }
}
