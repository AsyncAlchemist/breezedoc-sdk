<?php

declare(strict_types=1);

namespace Breezedoc\Pagination;

/**
 * Parser for Format B pagination (recipients, invoices).
 *
 * Response structure:
 * {
 *   "data": [...],
 *   "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
 *   "meta": {
 *     "current_page": 1,
 *     "per_page": 10,
 *     "total": 25,
 *     "last_page": 3
 *   }
 * }
 */
class FormatBPaginator
{
    /**
     * Parse a Format B paginated response.
     *
     * @template T
     * @param array<string, mixed> $response
     * @param class-string<T> $modelClass
     * @return PaginatedResult<T>
     */
    public function parse(array $response, string $modelClass): PaginatedResult
    {
        $data = $response['data'] ?? [];
        $meta = $response['meta'] ?? [];
        $items = [];

        foreach ($data as $item) {
            $items[] = $modelClass::fromArray($item);
        }

        $metadata = new PaginationMetadata(
            (int) ($meta['current_page'] ?? 1),
            (int) ($meta['per_page'] ?? 10),
            (int) ($meta['total'] ?? 0),
            (int) ($meta['last_page'] ?? 1)
        );

        return new PaginatedResult($items, $metadata);
    }
}
