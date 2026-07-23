<?php

declare(strict_types=1);

namespace Breezedoc\Web;

use RuntimeException;

/**
 * Persists the web session to a JSON file.
 *
 * The file is written with 0600 permissions (owner read/write only) because it
 * contains live session cookies. The default location is
 * `~/.breezedoc/session.json`; keep it out of version control.
 */
class FileSessionStore implements SessionStore
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function load(): ?SessionState
    {
        if (!is_file($this->path)) {
            return null;
        }

        $json = @file_get_contents($this->path);
        if ($json === false || $json === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return SessionState::fromArray($data);
    }

    public function save(SessionState $state): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Could not create session directory: ' . $dir);
            }
        }

        $json = json_encode($state->toArray());
        if ($json === false) {
            throw new RuntimeException('Failed to encode session state as JSON');
        }

        if (@file_put_contents($this->path, $json) === false) {
            throw new RuntimeException('Failed to write session file: ' . $this->path);
        }

        @chmod($this->path, 0600);
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
