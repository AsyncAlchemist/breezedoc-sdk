<?php

declare(strict_types=1);

namespace Breezedoc\Web;

/**
 * In-memory session store.
 *
 * The session lives only for the lifetime of this object, so nothing is written to
 * disk. Useful for tests and for callers that do not want a persistent session cache.
 */
class ArraySessionStore implements SessionStore
{
    private ?SessionState $state = null;

    public function load(): ?SessionState
    {
        return $this->state;
    }

    public function save(SessionState $state): void
    {
        $this->state = $state;
    }

    public function clear(): void
    {
        $this->state = null;
    }
}
