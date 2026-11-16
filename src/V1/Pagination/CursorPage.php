<?php

declare(strict_types=1);

namespace Breezedoc\V1\Pagination;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a Team API v1 list: the `data` items and the `next_cursor`.
 *
 * Pass {@see getNextCursor()} as the `cursor` parameter to get the next page.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
class CursorPage implements Countable, IteratorAggregate
{
    /**
     * @var array<T>
     */
    private array $items;

    private ?string $nextCursor;

    /**
     * @param array<T> $items
     */
    public function __construct(array $items, ?string $nextCursor)
    {
        $this->items = $items;
        $this->nextCursor = $nextCursor === '' ? null : $nextCursor;
    }

    /**
     * @return array<T>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * The cursor of the next page, or null on the last page.
     */
    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
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
