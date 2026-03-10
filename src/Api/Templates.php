<?php

declare(strict_types=1);

namespace Breezedoc\Api;

use Breezedoc\Models\Document;
use Breezedoc\Models\Template;
use Breezedoc\Pagination\FormatAPaginator;
use Breezedoc\Pagination\PaginatedResult;

/**
 * Templates API resource.
 */
class Templates extends AbstractApi
{
    /**
     * List templates.
     *
     * @param array<string, mixed> $params Query parameters
     * @return PaginatedResult<Template>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->doGet('/templates', $params);
        return (new FormatAPaginator())->parse($data, Template::class);
    }

    /**
     * Get a template by ID.
     */
    public function find(int $id): Template
    {
        $data = $this->doGet('/templates/' . $id);
        return Template::fromArray($data);
    }

    /**
     * Create a document from a template.
     */
    public function createDocument(int $templateId): Document
    {
        $data = $this->post('/templates/' . $templateId . '/create-document');
        return Document::fromArray($data);
    }

    /**
     * Internal GET request (to avoid collision with parent::get).
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function doGet(string $path, array $query = []): array
    {
        return parent::get($path, $query);
    }
}
