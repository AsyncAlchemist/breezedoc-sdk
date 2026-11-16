<?php

declare(strict_types=1);

namespace Breezedoc\V1\Api;

use Breezedoc\V1\Models\TeamTemplate;
use Breezedoc\V1\Pagination\CursorPage;

/**
 * Team API v1: team templates (read scope).
 */
class Templates extends AbstractTeamApi
{
    /**
     * `GET /v1/templates`: one page of team templates.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - limit: 1 to 100 (default 25)
     *   - cursor: the next_cursor of the previous page
     * @return CursorPage<TeamTemplate>
     */
    public function list(array $params = []): CursorPage
    {
        $response = $this->call('GET', '/templates', null, self::listQuery($params));

        return self::cursorPage($response, [TeamTemplate::class, 'fromArray']);
    }

    /**
     * Walk all team templates, page by page.
     *
     * @param array<string, mixed> $params limit (page size) and an optional start cursor
     * @return \Generator<int, TeamTemplate, mixed, void>
     */
    public function all(array $params = []): \Generator
    {
        return self::walk(function (array $query): CursorPage {
            return $this->list($query);
        }, $params);
    }

    /**
     * `GET /v1/templates/{id}`: template metadata, numbered parties and field definitions.
     */
    public function find(string $id): TeamTemplate
    {
        $response = $this->call('GET', '/templates/' . self::encodeId($id, 'template ID'));

        return TeamTemplate::fromArray(self::unwrap($response));
    }
}
