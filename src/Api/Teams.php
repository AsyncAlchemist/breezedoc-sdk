<?php

declare(strict_types=1);

namespace Breezedoc\Api;

use Breezedoc\Models\Document;
use Breezedoc\Models\Template;
use Breezedoc\Pagination\FormatAPaginator;
use Breezedoc\Pagination\PaginatedResult;

/**
 * Teams API resource.
 *
 * Note: Teams endpoints require an Agency plan subscription.
 * This implementation is spec-compliant but has not been live-tested.
 */
class Teams extends AbstractApi
{
    /**
     * List documents for a team.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - order_by: Sort field (id, completed_at, created_at)
     *   - direction: Sort direction (asc, desc)
     *   - completed: Filter by completion (true, false)
     * @return PaginatedResult<Document>
     */
    public function documents(int $teamId, array $params = []): PaginatedResult
    {
        $data = $this->get('/teams/' . $teamId . '/documents', $params);
        return (new FormatAPaginator())->parse($data, Document::class);
    }

    /**
     * List templates shared with a team.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - order_by: Sort field (id, created_at, updated_at)
     *   - direction: Sort direction (asc, desc)
     * @return PaginatedResult<Template>
     */
    public function templates(int $teamId, array $params = []): PaginatedResult
    {
        $data = $this->get('/teams/' . $teamId . '/templates', $params);
        return (new FormatAPaginator())->parse($data, Template::class);
    }
}
