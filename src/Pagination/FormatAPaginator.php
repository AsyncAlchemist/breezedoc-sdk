<?php

declare(strict_types=1);

namespace Breezedoc\Pagination;

/**
 * Parser for Format A pagination (documents, templates, document recipients).
 *
 * Response structure:
 * {
 *   "current_page": 1,
 *   "data": [...],
 *   "per_page": 10,
 *   "total": 25,
 *   "last_page": 3
 * }
 */
class FormatAPaginator
{
    /**
     * Parse a Format A paginated response.
     *
     * @template T
     * @param array<string, mixed> $response
     * @param class-string<T> $modelClass
     * @return PaginatedResult<T>
     */
    public function parse(array $response, string $modelClass): PaginatedResult
    {
        $data = $response['data'] ?? [];
        $items = [];

        foreach ($data as $item) {
            $items[] = $modelClass::fromArray($item);
        }

        $metadata = new PaginationMetadata(
            (int) ($response['current_page'] ?? 1),
            (int) ($response['per_page'] ?? 10),
            (int) ($response['total'] ?? 0),
            (int) ($response['last_page'] ?? 1)
        );

        return new PaginatedResult($items, $metadata);
    }
}
