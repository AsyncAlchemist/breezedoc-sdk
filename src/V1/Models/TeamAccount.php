<?php

declare(strict_types=1);

namespace Breezedoc\V1\Models;

/**
 * The response of `GET /v1/me`: the team of the key, the owner's plan and the team usage.
 *
 * BreezeDoc does not publish the field names of this response. Use {@see toArray()} or
 * {@see get()} for the fields.
 */
class TeamAccount extends AbstractResource
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $account = new self();
        $account->rawData = $data;

        return $account;
    }

    /**
     * The documents the team can still create in the current period, or null.
     *
     * BreezeDoc documents that `remaining_documents` is null for teams that keep their
     * monthly quota exemption. This getter reads `remaining_documents` at the top level or
     * one level down (for example inside a `usage` object).
     */
    public function getRemainingDocuments(): ?int
    {
        if (array_key_exists('remaining_documents', $this->rawData)) {
            return self::intOrNull($this->rawData['remaining_documents']);
        }

        foreach ($this->rawData as $value) {
            if (is_array($value) && array_key_exists('remaining_documents', $value)) {
                return self::intOrNull($value['remaining_documents']);
            }
        }

        return null;
    }
}
