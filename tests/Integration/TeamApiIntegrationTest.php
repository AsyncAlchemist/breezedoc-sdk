<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use Breezedoc\Breezedoc;
use Breezedoc\V1\Models\TeamAccount;
use Breezedoc\V1\Pagination\CursorPage;
use Breezedoc\V1\TeamClient;
use PHPUnit\Framework\TestCase;

/**
 * Read-only checks of the Team API v1 with a live team API key.
 *
 * BreezeDoc publishes no response schemas for the Team API v1. These tests confirm the
 * shapes that the SDK reads (`data`/`next_cursor` lists, string IDs). They never create,
 * send, cancel or change anything. They are skipped unless BREEZEDOC_TEAM_API_KEY is set.
 */
class TeamApiIntegrationTest extends TestCase
{
    private TeamClient $team;

    protected function setUp(): void
    {
        parent::setUp();

        $key = getenv('BREEZEDOC_TEAM_API_KEY');
        if ($key === false || $key === '') {
            $key = $_ENV['BREEZEDOC_TEAM_API_KEY'] ?? $_SERVER['BREEZEDOC_TEAM_API_KEY'] ?? '';
        }

        if (!is_string($key) || $key === '') {
            $this->markTestSkipped('Team API integration tests require BREEZEDOC_TEAM_API_KEY.');
        }

        $this->team = Breezedoc::teamClient($key);
    }

    public function testMe(): void
    {
        $account = $this->team->me();

        $this->assertInstanceOf(TeamAccount::class, $account);
        $this->assertNotEmpty($account->toArray());
    }

    public function testListTemplates(): void
    {
        $page = $this->team->templates()->list(['limit' => 1]);

        $this->assertInstanceOf(CursorPage::class, $page);
        foreach ($page as $template) {
            $this->assertStringStartsWith('tpl_', $template->getId());
        }
    }

    public function testListAndFindDocuments(): void
    {
        $page = $this->team->documents()->list(['limit' => 1]);

        $this->assertInstanceOf(CursorPage::class, $page);
        foreach ($page as $document) {
            $this->assertStringStartsWith('doc_', $document->getId());
            $found = $this->team->documents()->find($document->getId());
            $this->assertSame($document->getId(), $found->getId());
            $this->assertNotNull($found->getStatus());
        }
    }
}
