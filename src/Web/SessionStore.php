<?php

declare(strict_types=1);

namespace Breezedoc\Web;

/**
 * Persists an authenticated web {@see SessionState} so it can be reused across runs.
 *
 * Implementations must treat the stored data as sensitive: a saved session is a live
 * credential equivalent to a bearer token for the account.
 */
interface SessionStore
{
    /**
     * Load the persisted session, or null if none is stored.
     */
    public function load(): ?SessionState;

    /**
     * Persist the session, replacing any previously stored one.
     */
    public function save(SessionState $state): void;

    /**
     * Remove any persisted session.
     */
    public function clear(): void;
}
