<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Pagination;

use Breezedoc\Models\Document;
use Breezedoc\Models\Invoice;
use Breezedoc\Pagination\FormatAPaginator;
use Breezedoc\Pagination\FormatBPaginator;
use Breezedoc\Tests\Unit\UnitTestCase;

class PaginatorTest extends UnitTestCase
{
    public function testFormatAPaginatorParsesResponse(): void
    {
        $response = [
            'current_page' => 1,
            'data' => [
                ['id' => 1, 'title' => 'Doc 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
                ['id' => 2, 'title' => 'Doc 2', 'slug' => 'b', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
            ],
            'per_page' => 10,
            'total' => 25,
            'last_page' => 3,
        ];

        $paginator = new FormatAPaginator();
        $result = $paginator->parse($response, Document::class);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result->getCurrentPage());
        $this->assertSame(10, $result->getPerPage());
        $this->assertSame(25, $result->getTotal());
        $this->assertSame(3, $result->getLastPage());
        $this->assertTrue($result->hasNextPage());
        $this->assertFalse($result->hasPreviousPage());
    }

    public function testFormatAPaginatorCreatesCorrectModelInstances(): void
    {
        $response = [
            'current_page' => 1,
            'data' => [
                ['id' => 1, 'title' => 'Doc 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z'],
            ],
            'per_page' => 10,
            'total' => 1,
            'last_page' => 1,
        ];

        $paginator = new FormatAPaginator();
        $result = $paginator->parse($response, Document::class);

        $items = $result->getItems();
        $this->assertInstanceOf(Document::class, $items[0]);
        $this->assertSame(1, $items[0]->getId());
        $this->assertSame('Doc 1', $items[0]->getTitle());
    }

    public function testFormatBPaginatorParsesResponse(): void
    {
        $response = [
            'data' => [
                ['id' => 123, 'slug' => 'inv1', 'currency' => 'USD', 'status' => 'draft', 'description' => 'Test', 'customer_email' => 'a@b.com', 'total' => 100],
            ],
            'links' => [
                'first' => 'https://api.example.com/invoices?page=1',
                'last' => 'https://api.example.com/invoices?page=2',
                'prev' => null,
                'next' => 'https://api.example.com/invoices?page=2',
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 15,
                'last_page' => 2,
                'from' => 1,
                'to' => 10,
            ],
        ];

        $paginator = new FormatBPaginator();
        $result = $paginator->parse($response, Invoice::class);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result->getCurrentPage());
        $this->assertSame(10, $result->getPerPage());
        $this->assertSame(15, $result->getTotal());
        $this->assertSame(2, $result->getLastPage());
        $this->assertTrue($result->hasNextPage());
        $this->assertFalse($result->hasPreviousPage());
    }

    public function testFormatBPaginatorCreatesCorrectModelInstances(): void
    {
        $response = [
            'data' => [
                ['id' => 123, 'slug' => 'inv1', 'currency' => 'USD', 'status' => 'paid', 'description' => 'Test Invoice', 'customer_email' => 'test@example.com', 'total' => 100.50],
            ],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 1, 'last_page' => 1],
        ];

        $paginator = new FormatBPaginator();
        $result = $paginator->parse($response, Invoice::class);

        $items = $result->getItems();
        $this->assertInstanceOf(Invoice::class, $items[0]);
        $this->assertSame(123, $items[0]->getId());
        $this->assertTrue($items[0]->isPaid());
    }

    public function testFormatAPaginatorHandlesEmptyData(): void
    {
        $response = [
            'current_page' => 1,
            'data' => [],
            'per_page' => 10,
            'total' => 0,
            'last_page' => 1,
        ];

        $paginator = new FormatAPaginator();
        $result = $paginator->parse($response, Document::class);

        $this->assertCount(0, $result);
        $this->assertSame(0, $result->getTotal());
        $this->assertFalse($result->hasNextPage());
    }

    public function testFormatBPaginatorHandlesEmptyData(): void
    {
        $response = [
            'data' => [],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'per_page' => 10, 'total' => 0, 'last_page' => 1],
        ];

        $paginator = new FormatBPaginator();
        $result = $paginator->parse($response, Invoice::class);

        $this->assertCount(0, $result);
        $this->assertSame(0, $result->getTotal());
        $this->assertFalse($result->hasNextPage());
    }
}
