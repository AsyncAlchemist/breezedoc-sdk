<?php

declare(strict_types=1);

namespace Breezedoc\Api;

use Breezedoc\Models\Document;
use Breezedoc\Models\Recipient;
use Breezedoc\Pagination\FormatAPaginator;
use Breezedoc\Pagination\PaginatedResult;

/**
 * Documents API resource.
 */
class Documents extends AbstractApi
{
    /**
     * List documents.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - page: Page number
     *   - order_by: Sort field (completed_at, id)
     *   - direction: Sort direction (asc, desc)
     * @return PaginatedResult<Document>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->get('/documents', $params);
        return (new FormatAPaginator())->parse($data, Document::class);
    }

    /**
     * Get a document by ID.
     */
    public function find(int $id): Document
    {
        $data = $this->doGet('/documents/' . $id);
        return Document::fromArray($data);
    }

    /**
     * Create a new document.
     *
     * @param array<string, mixed> $data Document data:
     *   - title: Document title (required, max 191 chars)
     *   - recipients: Array of recipients (optional):
     *     - email: Recipient email (required)
     *     - name: Recipient name (required, max 191 chars)
     *     - party: Signing order (required)
     */
    public function create(array $data): Document
    {
        $response = $this->post('/documents', $data);
        return Document::fromArray($response);
    }

    /**
     * Send a document to recipients for signing.
     *
     * @param array<array{name: string, email: string}> $recipients
     *   Recipients must match the number of recipients on the document.
     */
    public function send(int $id, array $recipients): Document
    {
        $data = $this->post('/documents/' . $id . '/send', [
            'recipients' => $recipients,
        ]);
        return Document::fromArray($data);
    }

    /**
     * List recipients for a document.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - order_by: Sort field (completed_at, id)
     *   - direction: Sort direction (asc, desc)
     *   - completed: Filter by completion (true, false)
     * @return PaginatedResult<Recipient>
     */
    public function recipients(int $id, array $params = []): PaginatedResult
    {
        $data = $this->doGet('/documents/' . $id . '/recipients', $params);
        return (new FormatAPaginator())->parse($data, Recipient::class);
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
