<?php

declare(strict_types=1);

namespace Breezedoc\Api;

use Breezedoc\Models\Recipient;
use Breezedoc\Pagination\FormatBPaginator;
use Breezedoc\Pagination\PaginatedResult;

/**
 * Recipients API resource.
 */
class Recipients extends AbstractApi
{
    /**
     * List all recipients across all documents.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - order_by: Sort field (completed_at, id)
     *   - direction: Sort direction (asc, desc)
     * @return PaginatedResult<Recipient>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->get('/recipients', $params);
        return (new FormatBPaginator())->parse($data, Recipient::class);
    }
}
