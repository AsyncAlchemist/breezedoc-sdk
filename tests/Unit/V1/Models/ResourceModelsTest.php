<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\V1\Models;

use Breezedoc\Tests\Unit\UnitTestCase;
use Breezedoc\V1\Models\DocumentStatus;
use Breezedoc\V1\Models\DownloadLink;
use Breezedoc\V1\Models\TeamAccount;
use Breezedoc\V1\Models\TeamDocument;
use Breezedoc\V1\Models\TeamRecipient;
use Breezedoc\V1\Models\WebhookEndpoint;
use DateTimeImmutable;

class ResourceModelsTest extends UnitTestCase
{
    public function testTeamDocumentReadsDocumentedFields(): void
    {
        $document = TeamDocument::fromArray([
            'id' => 'doc_456',
            'title' => 'NDA',
            'status' => 'completed',
            'review_url' => null,
            'created_at' => '2026-10-23T12:00:00Z',
            'completed_at' => '2026-10-24T08:30:00Z',
            'recipients' => [
                ['id' => 'rcp_789', 'party' => 1, 'name' => 'Jordan', 'email' => 'jordan@example.com', 'status' => 'signed'],
                'not-an-object',
            ],
        ]);

        $this->assertSame('doc_456', $document->getId());
        $this->assertSame('NDA', $document->getTitle());
        $this->assertTrue($document->isCompleted());
        $this->assertFalse($document->isSent());
        $this->assertNull($document->getReviewUrl());
        $this->assertEquals(new DateTimeImmutable('2026-10-23T12:00:00Z'), $document->getCreatedAt());
        $this->assertEquals(new DateTimeImmutable('2026-10-24T08:30:00Z'), $document->getCompletedAt());
        $this->assertCount(1, $document->getRecipients());

        $recipient = $document->getRecipients()[0];
        $this->assertInstanceOf(TeamRecipient::class, $recipient);
        $this->assertSame('rcp_789', $recipient->getId());
        $this->assertSame(1, $recipient->getParty());
        $this->assertSame('Jordan', $recipient->getName());
        $this->assertSame('jordan@example.com', $recipient->getEmail());
        $this->assertSame('signed', $recipient->getStatus());
    }

    public function testTeamDocumentIsLenient(): void
    {
        $document = TeamDocument::fromArray(['status' => ['unexpected'], 'created_at' => 'not a date', 'recipients' => 'x']);

        $this->assertSame('', $document->getId());
        $this->assertNull($document->getStatus());
        $this->assertNull($document->getTitle());
        $this->assertNull($document->getCreatedAt());
        $this->assertSame([], $document->getRecipients());
        $this->assertSame('fallback', $document->get('missing', 'fallback'));
    }

    public function testStatusPredicates(): void
    {
        foreach (DocumentStatus::all() as $status) {
            $document = TeamDocument::fromArray(['id' => 'doc_1', 'status' => $status]);
            $flags = [
                $document->isDraft(),
                $document->isSent(),
                $document->isCompleted(),
                $document->isCancelled(),
                $document->isExpired(),
            ];
            $this->assertSame(1, count(array_filter($flags)), $status);
        }
    }

    public function testTeamAccountRemainingDocuments(): void
    {
        $this->assertSame(42, TeamAccount::fromArray(['remaining_documents' => 42])->getRemainingDocuments());
        $this->assertSame(5, TeamAccount::fromArray(['usage' => ['remaining_documents' => '5']])->getRemainingDocuments());
        $this->assertNull(TeamAccount::fromArray(['remaining_documents' => null])->getRemainingDocuments());
        $this->assertNull(TeamAccount::fromArray([])->getRemainingDocuments());
    }

    public function testDownloadLinkKnownKeys(): void
    {
        $link = DownloadLink::fromArray([
            'download_url' => 'https://files.example.test/a.pdf',
            'expires_at' => '2026-11-16T12:15:00Z',
        ]);

        $this->assertSame('https://files.example.test/a.pdf', $link->getUrl());
        $this->assertEquals(new DateTimeImmutable('2026-11-16T12:15:00Z'), $link->getExpiresAt());
    }

    public function testDownloadLinkSingleHttpsValue(): void
    {
        $link = DownloadLink::fromArray(['document_id' => 'doc_1', 'link' => 'https://files.example.test/b.pdf']);

        $this->assertSame('https://files.example.test/b.pdf', $link->getUrl());
    }

    public function testDownloadLinkIgnoresPlainHttpAndAmbiguousValues(): void
    {
        $this->assertNull(DownloadLink::fromArray(['url' => 'http://files.example.test/a.pdf'])->getUrl());
        $this->assertNull(DownloadLink::fromArray([
            'a' => 'https://one.example.test',
            'b' => 'https://two.example.test',
        ])->getUrl());
    }

    public function testWebhookEndpointSecretByPrefix(): void
    {
        $endpoint = WebhookEndpoint::fromArray([
            'id' => 'wh_1',
            'url' => 'https://example.com/hook',
            'events' => ['document.completed', 7],
            'secret_value' => 'whsec_xyz',
            'status' => 'active',
            'created_at' => '2026-11-16T09:00:00Z',
        ]);

        $this->assertSame('whsec_xyz', $endpoint->getSecret());
        $this->assertSame(['document.completed'], $endpoint->getEvents());
        $this->assertSame('active', $endpoint->getStatus());
        $this->assertNotNull($endpoint->getCreatedAt());
    }
}
