<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Models\Document;
use Breezedoc\Models\DocumentFile;
use Breezedoc\Models\Field;
use Breezedoc\Models\Recipient;
use Breezedoc\Tests\Unit\UnitTestCase;
use DateTimeImmutable;

class DocumentTest extends UnitTestCase
{
    private array $sampleData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleData = [
            'id' => 100,
            'title' => 'Test Document',
            'slug' => 'abc123',
            'redirect_url' => 'https://example.com/callback',
            'created_at' => '2025-01-15T10:30:00.000000Z',
            'updated_at' => '2025-01-15T12:00:00.000000Z',
            'completed_at' => null,
            'document_files' => [
                [
                    'id' => 200,
                    'document_file_pages' => [
                        ['id' => 1, 'page' => 1, 'url' => 'https://example.com/page1.png'],
                    ],
                ],
            ],
            'fields' => [
                [
                    'id' => 300,
                    'document_file_id' => 200,
                    'page' => 1,
                    'party' => 1,
                    'field_type_id' => '13eb6f62-cc8b-466a-9e25-400eb8f596aa',
                    'name' => 'Signature',
                    'recipient_id' => 400,
                    'properties' => ['h' => 0.035, 'w' => 0.217, 'x' => 0.149, 'y' => 0.724, 'required' => true],
                ],
            ],
            'recipients' => [
                [
                    'id' => 400,
                    'slug' => 'def456',
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'party' => 1,
                    'owner' => false,
                    'created_at' => '2025-01-15T10:30:00.000000Z',
                    'updated_at' => '2025-01-15T10:30:00.000000Z',
                    'completed_at' => null,
                ],
            ],
        ];
    }

    public function testFromArrayCreatesDocument(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertInstanceOf(Document::class, $document);
    }

    public function testGetId(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertSame(100, $document->getId());
    }

    public function testGetTitle(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertSame('Test Document', $document->getTitle());
    }

    public function testGetSlug(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertSame('abc123', $document->getSlug());
    }

    public function testGetRedirectUrl(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertSame('https://example.com/callback', $document->getRedirectUrl());
    }

    public function testGetCreatedAt(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertInstanceOf(DateTimeImmutable::class, $document->getCreatedAt());
    }

    public function testGetUpdatedAt(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertInstanceOf(DateTimeImmutable::class, $document->getUpdatedAt());
    }

    public function testGetCompletedAtWhenNull(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertNull($document->getCompletedAt());
    }

    public function testGetCompletedAtWhenSet(): void
    {
        $data = $this->sampleData;
        $data['completed_at'] = '2025-01-16T14:00:00.000000Z';
        $document = Document::fromArray($data);

        $this->assertInstanceOf(DateTimeImmutable::class, $document->getCompletedAt());
    }

    public function testIsCompleted(): void
    {
        $document = Document::fromArray($this->sampleData);
        $this->assertFalse($document->isCompleted());

        $data = $this->sampleData;
        $data['completed_at'] = '2025-01-16T14:00:00.000000Z';
        $completedDocument = Document::fromArray($data);
        $this->assertTrue($completedDocument->isCompleted());
    }

    public function testGetDocumentFiles(): void
    {
        $document = Document::fromArray($this->sampleData);
        $files = $document->getDocumentFiles();

        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertInstanceOf(DocumentFile::class, $files[0]);
    }

    public function testGetFields(): void
    {
        $document = Document::fromArray($this->sampleData);
        $fields = $document->getFields();

        $this->assertIsArray($fields);
        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Field::class, $fields[0]);
    }

    public function testGetRecipients(): void
    {
        $document = Document::fromArray($this->sampleData);
        $recipients = $document->getRecipients();

        $this->assertIsArray($recipients);
        $this->assertCount(1, $recipients);
        $this->assertInstanceOf(Recipient::class, $recipients[0]);
    }

    public function testDocumentWithoutOptionalFields(): void
    {
        $minimalData = [
            'id' => 1,
            'title' => 'Minimal Doc',
            'slug' => 'min123',
            'created_at' => '2025-01-15T10:30:00.000000Z',
            'updated_at' => '2025-01-15T10:30:00.000000Z',
        ];

        $document = Document::fromArray($minimalData);

        $this->assertSame(1, $document->getId());
        $this->assertNull($document->getRedirectUrl());
        $this->assertSame([], $document->getDocumentFiles());
        $this->assertSame([], $document->getFields());
        $this->assertSame([], $document->getRecipients());
    }
}
