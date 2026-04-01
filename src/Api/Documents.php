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
     * Download all page images for a document.
     *
     * Returns an array of JPEG image data strings, one per page, in page order.
     * The page images are fetched from pre-signed S3 URLs returned by the API.
     *
     * @return array<string> Array of JPEG binary strings, indexed by page order
     * @throws \Breezedoc\Exceptions\NotFoundException If the document does not exist
     * @throws \Breezedoc\Exceptions\ApiException If fetching the document fails
     * @throws \RuntimeException If a page image cannot be downloaded
     */
    public function downloadPageImages(int $id): array
    {
        $document = $this->find($id);
        $images = [];

        foreach ($document->getDocumentFiles() as $file) {
            foreach ($file->getPages() as $page) {
                $url = $page->getUrl();
                if ($url !== '') {
                    $images[] = $this->fetchExternalUrl($url);
                }
            }
        }

        return $images;
    }

    /**
     * Download all page images for a document and save them to a directory.
     *
     * Files are saved as "{basename}-1.jpg", "{basename}-2.jpg", etc.
     *
     * @param int $id Document ID
     * @param string $directory Directory to save images to
     * @param string $basename Base filename (default: "page")
     * @return array<string> Array of saved file paths
     * @throws \Breezedoc\Exceptions\NotFoundException If the document does not exist
     * @throws \RuntimeException If the directory is not writable or a file cannot be saved
     */
    public function downloadPageImagesTo(int $id, string $directory, string $basename = 'page'): array
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \RuntimeException('Directory is not writable: ' . $directory);
        }

        $images = $this->downloadPageImages($id);
        $paths = [];

        foreach ($images as $index => $imageData) {
            $filePath = rtrim($directory, '/') . '/' . $basename . '-' . ($index + 1) . '.jpg';
            $bytes = file_put_contents($filePath, $imageData);

            if ($bytes === false) {
                throw new \RuntimeException('Failed to write image to: ' . $filePath);
            }

            $paths[] = $filePath;
        }

        return $paths;
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
