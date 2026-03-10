<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Pagination;

use Breezedoc\Models\Document;
use Breezedoc\Pagination\PaginatedResult;
use Breezedoc\Pagination\PaginationMetadata;
use Breezedoc\Tests\Unit\UnitTestCase;

class PaginatedResultTest extends UnitTestCase
{
    public function testGetItems(): void
    {
        $items = [
            Document::fromArray(['id' => 1, 'title' => 'Doc 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z']),
            Document::fromArray(['id' => 2, 'title' => 'Doc 2', 'slug' => 'b', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z']),
        ];
        $metadata = new PaginationMetadata(1, 10, 2, 1);
        $result = new PaginatedResult($items, $metadata);

        $this->assertSame($items, $result->getItems());
    }

    public function testGetMetadata(): void
    {
        $items = [];
        $metadata = new PaginationMetadata(1, 10, 0, 1);
        $result = new PaginatedResult($items, $metadata);

        $this->assertSame($metadata, $result->getMetadata());
    }

    public function testHasNextPage(): void
    {
        $items = [];

        $metadataWithNext = new PaginationMetadata(1, 10, 15, 2);
        $resultWithNext = new PaginatedResult($items, $metadataWithNext);
        $this->assertTrue($resultWithNext->hasNextPage());

        $metadataWithoutNext = new PaginationMetadata(2, 10, 15, 2);
        $resultWithoutNext = new PaginatedResult($items, $metadataWithoutNext);
        $this->assertFalse($resultWithoutNext->hasNextPage());
    }

    public function testHasPreviousPage(): void
    {
        $items = [];

        $metadataFirstPage = new PaginationMetadata(1, 10, 15, 2);
        $resultFirstPage = new PaginatedResult($items, $metadataFirstPage);
        $this->assertFalse($resultFirstPage->hasPreviousPage());

        $metadataSecondPage = new PaginationMetadata(2, 10, 15, 2);
        $resultSecondPage = new PaginatedResult($items, $metadataSecondPage);
        $this->assertTrue($resultSecondPage->hasPreviousPage());
    }

    public function testGetCurrentPage(): void
    {
        $items = [];
        $metadata = new PaginationMetadata(3, 10, 50, 5);
        $result = new PaginatedResult($items, $metadata);

        $this->assertSame(3, $result->getCurrentPage());
    }

    public function testGetLastPage(): void
    {
        $items = [];
        $metadata = new PaginationMetadata(1, 10, 50, 5);
        $result = new PaginatedResult($items, $metadata);

        $this->assertSame(5, $result->getLastPage());
    }

    public function testGetTotal(): void
    {
        $items = [];
        $metadata = new PaginationMetadata(1, 10, 47, 5);
        $result = new PaginatedResult($items, $metadata);

        $this->assertSame(47, $result->getTotal());
    }

    public function testGetPerPage(): void
    {
        $items = [];
        $metadata = new PaginationMetadata(1, 25, 100, 4);
        $result = new PaginatedResult($items, $metadata);

        $this->assertSame(25, $result->getPerPage());
    }

    public function testCount(): void
    {
        $items = [
            Document::fromArray(['id' => 1, 'title' => 'Doc 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z']),
            Document::fromArray(['id' => 2, 'title' => 'Doc 2', 'slug' => 'b', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z']),
            Document::fromArray(['id' => 3, 'title' => 'Doc 3', 'slug' => 'c', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z']),
        ];
        $metadata = new PaginationMetadata(1, 10, 3, 1);
        $result = new PaginatedResult($items, $metadata);

        $this->assertCount(3, $result);
    }

    public function testIterator(): void
    {
        $items = [
            Document::fromArray(['id' => 1, 'title' => 'Doc 1', 'slug' => 'a', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z']),
            Document::fromArray(['id' => 2, 'title' => 'Doc 2', 'slug' => 'b', 'created_at' => '2025-01-01T00:00:00Z', 'updated_at' => '2025-01-01T00:00:00Z']),
        ];
        $metadata = new PaginationMetadata(1, 10, 2, 1);
        $result = new PaginatedResult($items, $metadata);

        $ids = [];
        foreach ($result as $item) {
            $ids[] = $item->getId();
        }

        $this->assertSame([1, 2], $ids);
    }
}
