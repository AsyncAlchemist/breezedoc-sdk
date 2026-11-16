<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Webhooks;

use Breezedoc\Exceptions\WebhookVerificationException;
use Breezedoc\Tests\Unit\UnitTestCase;
use Breezedoc\Webhooks\WebhookEvent;
use Breezedoc\Webhooks\WebhookEventType;
use DateTimeImmutable;

class WebhookEventTest extends UnitTestCase
{
    public function testDocumentCompletedEvent(): void
    {
        $event = WebhookEvent::fromArray([
            'id' => 'evt_2',
            'type' => 'document.completed',
            'created_at' => '2026-10-23T12:00:00Z',
            'data' => ['document_id' => 'doc_456', 'status' => 'completed'],
        ]);

        $this->assertTrue($event->isDocumentCompleted());
        $this->assertTrue($event->is(WebhookEventType::DOCUMENT_COMPLETED));
        $this->assertFalse($event->isTest());
        $this->assertSame('doc_456', $event->getDocumentId());
        $this->assertSame('completed', $event->getDocumentStatus());
        $this->assertNull($event->getRecipient());
        $this->assertEquals(new DateTimeImmutable('2026-10-23T12:00:00Z'), $event->getCreatedAt());
        $this->assertSame(['document_id' => 'doc_456', 'status' => 'completed'], $event->getData());
        $this->assertSame('evt_2', $event->toArray()['id']);
    }

    public function testTestEventWithoutData(): void
    {
        $event = WebhookEvent::fromArray(['id' => 'evt_t', 'type' => 'webhook.test', 'created_at' => 'garbage']);

        $this->assertTrue($event->isTest());
        $this->assertNull($event->getDocumentId());
        $this->assertNull($event->getDocumentStatus());
        $this->assertNull($event->getCreatedAt());
        $this->assertSame([], $event->getData());
    }

    public function testRequiresIdAndType(): void
    {
        $this->expectException(WebhookVerificationException::class);
        WebhookEvent::fromArray(['id' => 'evt_1']);
    }

    public function testEventTypeLists(): void
    {
        $this->assertCount(6, WebhookEventType::subscribable());
        $this->assertNotContains(WebhookEventType::WEBHOOK_TEST, WebhookEventType::subscribable());
        $this->assertContains(WebhookEventType::WEBHOOK_TEST, WebhookEventType::all());
        $this->assertCount(7, WebhookEventType::all());
    }
}
