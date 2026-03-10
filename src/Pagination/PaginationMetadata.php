<?php

declare(strict_types=1);

namespace Breezedoc\Pagination;

/**
 * Metadata about a paginated response.
 */
class PaginationMetadata
{
    private int $currentPage;
    private int $perPage;
    private int $total;
    private int $lastPage;

    public function __construct(int $currentPage, int $perPage, int $total, int $lastPage)
    {
        $this->currentPage = $currentPage;
        $this->perPage = $perPage;
        $this->total = $total;
        $this->lastPage = $lastPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }
}
