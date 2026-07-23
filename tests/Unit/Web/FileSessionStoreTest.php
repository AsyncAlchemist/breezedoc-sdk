<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Web;

use Breezedoc\Web\FileSessionStore;
use Breezedoc\Web\SessionState;
use PHPUnit\Framework\TestCase;

class FileSessionStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir() . '/breezedoc_session_test_' . uniqid() . '/session.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        $dir = dirname($this->path);
        if (is_dir($dir)) {
            rmdir($dir);
        }
        parent::tearDown();
    }

    public function testLoadReturnsNullWhenFileMissing(): void
    {
        $store = new FileSessionStore($this->path);
        $this->assertNull($store->load());
    }

    public function testSaveThenLoadRoundTrips(): void
    {
        $store = new FileSessionStore($this->path);
        $state = new SessionState(
            'user@example.com',
            1700000000,
            [['Name' => 'breezedoc_session', 'Value' => 'abc', 'Domain' => 'breezedoc.com']]
        );

        $store->save($state);
        $loaded = $store->load();

        $this->assertNotNull($loaded);
        $this->assertSame('user@example.com', $loaded->getEmail());
        $this->assertSame(1700000000, $loaded->getCreatedAt());
        $this->assertSame($state->getCookies(), $loaded->getCookies());
    }

    public function testSaveCreatesDirectoryAndFileIsPrivate(): void
    {
        $store = new FileSessionStore($this->path);
        $store->save(new SessionState('a@b.com', 1, []));

        $this->assertFileExists($this->path);
        // 0600 = owner read/write only (skip on Windows where perms differ).
        if (DIRECTORY_SEPARATOR === '/') {
            $this->assertSame('0600', substr(sprintf('%o', fileperms($this->path)), -4));
        }
    }

    public function testClearRemovesFile(): void
    {
        $store = new FileSessionStore($this->path);
        $store->save(new SessionState('a@b.com', 1, []));
        $this->assertFileExists($this->path);

        $store->clear();
        $this->assertFileDoesNotExist($this->path);
    }

    public function testLoadReturnsNullForCorruptFile(): void
    {
        $dir = dirname($this->path);
        mkdir($dir, 0700, true);
        file_put_contents($this->path, 'not-json{{{');

        $store = new FileSessionStore($this->path);
        $this->assertNull($store->load());
    }
}
