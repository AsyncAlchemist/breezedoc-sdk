<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Config\FieldType;
use Breezedoc\Models\Document;
use Breezedoc\Models\DocumentFile;
use Breezedoc\Models\Field;
use Breezedoc\Models\Recipient;
use Breezedoc\Models\SubmittedField;
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

    public function testGetSubmittedFields(): void
    {
        $document = Document::fromArray($this->buildCompletedDocumentData());
        $submitted = $document->getSubmittedFields();

        $this->assertCount(3, $submitted);
        $this->assertContainsOnlyInstancesOf(SubmittedField::class, $submitted);

        $this->assertSame('Text', $submitted[0]->getName());
        $this->assertSame('Acme Corp', $submitted[0]->getValue());
        $this->assertSame('John Doe', $submitted[0]->getRecipientName());

        $this->assertSame('Signature', $submitted[1]->getName());
        $this->assertSame('signatures/sig1.png', $submitted[1]->getImage());

        $this->assertSame('Signature', $submitted[2]->getName());
        $this->assertSame('signatures/sig2.png', $submitted[2]->getImage());
        $this->assertSame('Jane Smith', $submitted[2]->getRecipientName());
    }

    public function testGetSubmittedFieldByName(): void
    {
        $document = Document::fromArray($this->buildCompletedDocumentData());

        $field = $document->getSubmittedField('Text');

        $this->assertNotNull($field);
        $this->assertSame('Acme Corp', $field->getValue());
        $this->assertSame('Text', $field->getFieldTypeName());
    }

    public function testGetSubmittedFieldByNameReturnsNull(): void
    {
        $document = Document::fromArray($this->buildCompletedDocumentData());

        $this->assertNull($document->getSubmittedField('Nonexistent Field'));
    }

    public function testGetSubmittedFieldsForRecipient(): void
    {
        $document = Document::fromArray($this->buildCompletedDocumentData());
        $recipients = $document->getRecipients();

        $johnsFields = $document->getSubmittedFieldsFor($recipients[0]);
        $this->assertCount(2, $johnsFields);
        $this->assertSame('Text', $johnsFields[0]->getName());
        $this->assertSame('Signature', $johnsFields[1]->getName());

        $janesFields = $document->getSubmittedFieldsFor($recipients[1]);
        $this->assertCount(1, $janesFields);
        $this->assertSame('Signature', $janesFields[0]->getName());
    }

    public function testGetSubmittedFieldsEmpty(): void
    {
        $document = Document::fromArray($this->sampleData);

        $this->assertSame([], $document->getSubmittedFields());
    }

    public function testGetSubmittedFieldsIsCached(): void
    {
        $document = Document::fromArray($this->buildCompletedDocumentData());

        $first = $document->getSubmittedFields();
        $second = $document->getSubmittedFields();

        $this->assertSame($first, $second);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCompletedDocumentData(): array
    {
        return [
            'id' => 100,
            'title' => 'Test Agreement',
            'slug' => 'abc123',
            'redirect_url' => null,
            'created_at' => '2025-01-15T10:30:00.000000Z',
            'updated_at' => '2025-01-15T12:00:00.000000Z',
            'completed_at' => '2025-01-15T14:00:00.000000Z',
            'document_files' => [],
            'fields' => [
                [
                    'id' => 300,
                    'document_file_id' => 200,
                    'page' => 1,
                    'party' => 1,
                    'field_type_id' => FieldType::TEXT,
                    'name' => 'Text',
                    'recipient_id' => 400,
                    'properties' => ['h' => 0.035, 'w' => 0.3, 'x' => 0.1, 'y' => 0.5, 'required' => true, 'label' => 'Company Name'],
                    'recipient_field' => [
                        'field_id' => 300,
                        'properties' => ['text' => 'Acme Corp', 'committed' => true],
                    ],
                ],
                [
                    'id' => 301,
                    'document_file_id' => 200,
                    'page' => 1,
                    'party' => 1,
                    'field_type_id' => FieldType::SIGNATURE,
                    'name' => 'Signature',
                    'recipient_id' => 400,
                    'properties' => ['h' => 0.035, 'w' => 0.217, 'x' => 0.149, 'y' => 0.724, 'required' => true],
                    'recipient_field' => [
                        'field_id' => 301,
                        'properties' => ['text' => 'John Doe', 'fontFamily' => 'MonteCarlo', 'committed' => true, 'image' => 'signatures/sig1.png'],
                    ],
                ],
                [
                    'id' => 302,
                    'document_file_id' => 200,
                    'page' => 1,
                    'party' => 2,
                    'field_type_id' => FieldType::SIGNATURE,
                    'name' => 'Signature',
                    'recipient_id' => 500,
                    'properties' => ['h' => 0.035, 'w' => 0.217, 'x' => 0.149, 'y' => 0.85, 'required' => true],
                    'recipient_field' => [
                        'field_id' => 302,
                        'properties' => ['text' => 'Jane Smith', 'fontFamily' => 'MonteCarlo', 'committed' => true, 'image' => 'signatures/sig2.png'],
                    ],
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
                    'sent_at' => '2025-01-15T10:00:00.000000Z',
                    'opened_at' => null,
                    'created_at' => '2025-01-15T10:30:00.000000Z',
                    'updated_at' => '2025-01-15T11:00:00.000000Z',
                    'completed_at' => '2025-01-15T11:00:00.000000Z',
                ],
                [
                    'id' => 500,
                    'slug' => 'ghi789',
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'party' => 2,
                    'owner' => true,
                    'sent_at' => '2025-01-15T12:00:00.000000Z',
                    'opened_at' => null,
                    'created_at' => '2025-01-15T10:30:00.000000Z',
                    'updated_at' => '2025-01-15T14:00:00.000000Z',
                    'completed_at' => '2025-01-15T14:00:00.000000Z',
                ],
            ],
        ];
    }
}
