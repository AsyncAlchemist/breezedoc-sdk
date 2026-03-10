<?php

declare(strict_types=1);

namespace Breezedoc\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Container for a paginated API response.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
class PaginatedResult implements Countable, IteratorAggregate
{
    /**
     * @var array<T>
     */
    private array $items;

    private PaginationMetadata $metadata;

    /**
     * @param array<T> $items
     */
    public function __construct(array $items, PaginationMetadata $metadata)
    {
        $this->items = $items;
        $this->metadata = $metadata;
    }

    /**
     * @return array<T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getMetadata(): PaginationMetadata
    {
        return $this->metadata;
    }

    public function getCurrentPage(): int
    {
        return $this->metadata->getCurrentPage();
    }

    public function getPerPage(): int
    {
        return $this->metadata->getPerPage();
    }

    public function getTotal(): int
    {
        return $this->metadata->getTotal();
    }

    public function getLastPage(): int
    {
        return $this->metadata->getLastPage();
    }

    public function hasNextPage(): bool
    {
        return $this->metadata->hasNextPage();
    }

    public function hasPreviousPage(): bool
    {
        return $this->metadata->hasPreviousPage();
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
