<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

/**
 * A team template (`tpl_...`) of the Team API v1.
 *
 * `GET /v1/templates/{id}` returns the template metadata, its numbered parties and its
 * field definitions. Supply exactly one recipient per party when you create a document.
 */
class TeamTemplate extends AbstractResource
{
    private string $id;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $template = new self();
        $template->rawData = $data;
        $template->id = self::idOf($data);

        return $template;
    }

    /**
     * The template ID, for example "tpl_123".
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * The template title (`title`, else `name`), or null.
     */
    public function getTitle(): ?string
    {
        return self::stringOrNull($this->rawData['title'] ?? ($this->rawData['name'] ?? null));
    }

    /**
     * The numbered parties (`parties`), as returned by the API.
     *
     * @return array<mixed>
     */
    public function getParties(): array
    {
        $parties = $this->rawData['parties'] ?? null;

        return is_array($parties) ? $parties : [];
    }

    /**
     * The field definitions (`fields`), as returned by the API.
     *
     * @return array<mixed>
     */
    public function getFields(): array
    {
        $fields = $this->rawData['fields'] ?? null;

        return is_array($fields) ? $fields : [];
    }
}
