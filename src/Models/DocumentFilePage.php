<?php

declare(strict_types=1);

namespace Breezedoc\Models;

/**
 * Represents a page in a document file.
 */
class DocumentFilePage extends AbstractModel
{
    private int $id;
    private int $page;
    private string $url;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $page = new self();
        $page->rawData = $data;

        $page->id = (int) ($data['id'] ?? 0);
        $page->page = (int) ($data['page'] ?? 0);
        $page->url = (string) ($data['url'] ?? '');

        return $page;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
