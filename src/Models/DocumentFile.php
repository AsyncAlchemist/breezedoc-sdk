<?php

declare(strict_types=1);

namespace Breezedoc\Models;

/**
 * Represents a file attached to a document.
 */
class DocumentFile extends AbstractModel
{
    private int $id;

    /**
     * @var array<DocumentFilePage>
     */
    private array $pages = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $file = new self();
        $file->rawData = $data;

        $file->id = (int) $data['id'];

        if (isset($data['document_file_pages']) && is_array($data['document_file_pages'])) {
            foreach ($data['document_file_pages'] as $pageData) {
                $file->pages[] = DocumentFilePage::fromArray($pageData);
            }
        }

        return $file;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return array<DocumentFilePage>
     */
    public function getPages(): array
    {
        return $this->pages;
    }
}
