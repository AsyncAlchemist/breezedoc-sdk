<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Config\FieldType;
use Breezedoc\Models\Field;
use Breezedoc\Models\FieldProperties;
use Breezedoc\Models\Recipient;
use Breezedoc\Models\SubmittedField;
use Breezedoc\Tests\Unit\UnitTestCase;

class SubmittedFieldTest extends UnitTestCase
{
    private Field $field;
    private Recipient $recipient;
    private SubmittedField $submittedField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->field = Field::fromArray([
            'id' => 300,
            'document_file_id' => 200,
            'page' => 1,
            'party' => 1,
            'field_type_id' => FieldType::TEXT,
            'name' => 'Company Name',
            'recipient_id' => 400,
            'properties' => ['h' => 0.035, 'w' => 0.217, 'x' => 0.149, 'y' => 0.724, 'required' => true, 'label' => 'Company Name'],
            'recipient_field' => [
                'field_id' => 300,
                'properties' => ['text' => 'Acme Corp', 'committed' => true],
            ],
        ]);

        $this->recipient = Recipient::fromArray([
            'id' => 400,
            'slug' => 'def456',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'party' => 1,
            'owner' => false,
            'sent_at' => '2025-01-15T10:00:00.000000Z',
            'opened_at' => null,
            'created_at' => '2025-01-15T10:30:00.000000Z',
            'updated_at' => '2025-01-15T10:30:00.000000Z',
            'completed_at' => '2025-01-15T11:00:00.000000Z',
        ]);

        $this->submittedField = SubmittedField::create($this->field, $this->recipient);
    }

    public function testCreateReturnsInstance(): void
    {
        $this->assertInstanceOf(SubmittedField::class, $this->submittedField);
    }

    public function testGetIdReturnsFieldId(): void
    {
        $this->assertSame(300, $this->submittedField->getId());
    }

    public function testGetNameReturnsFieldName(): void
    {
        $this->assertSame('Company Name', $this->submittedField->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Company Name', $this->submittedField->getLabel());
    }

    public function testGetFieldTypeId(): void
    {
        $this->assertSame(FieldType::TEXT, $this->submittedField->getFieldTypeId());
    }

    public function testGetFieldTypeName(): void
    {
        $this->assertSame('Text', $this->submittedField->getFieldTypeName());
    }

    public function testGetValue(): void
    {
        $this->assertSame('Acme Corp', $this->submittedField->getValue());
    }

    public function testGetText(): void
    {
        $this->assertSame('Acme Corp', $this->submittedField->getText());
    }

    public function testGetImageReturnsNull(): void
    {
        $this->assertNull($this->submittedField->getImage());
    }

    public function testSignatureField(): void
    {
        $signatureField = Field::fromArray([
            'id' => 301,
            'document_file_id' => 200,
            'page' => 1,
            'party' => 1,
            'field_type_id' => FieldType::SIGNATURE,
            'name' => 'Signature',
            'recipient_id' => 400,
            'properties' => ['h' => 0.035, 'w' => 0.217, 'x' => 0.149, 'y' => 0.8, 'required' => true],
            'recipient_field' => [
                'field_id' => 301,
                'properties' => [
                    'text' => 'John Doe',
                    'fontFamily' => 'MonteCarlo',
                    'committed' => true,
                    'image' => 'signatures/abc123.png',
                ],
            ],
        ]);

        $submitted = SubmittedField::create($signatureField, $this->recipient);

        $this->assertSame('signatures/abc123.png', $submitted->getImage());
        $this->assertSame('John Doe', $submitted->getText());
        $this->assertSame('John Doe', $submitted->getValue());
        $this->assertTrue($submitted->isSignature());
        $this->assertFalse($submitted->isInitials());
    }

    public function testDateField(): void
    {
        $dateField = Field::fromArray([
            'id' => 302,
            'document_file_id' => 200,
            'page' => 1,
            'party' => 1,
            'field_type_id' => FieldType::DATE,
            'name' => 'Date',
            'recipient_id' => 400,
            'properties' => ['h' => 0.018, 'w' => 0.19, 'x' => 0.52, 'y' => 0.74, 'required' => false],
            'recipient_field' => [
                'field_id' => 302,
                'properties' => ['date' => '03-31-2026'],
            ],
        ]);

        $submitted = SubmittedField::create($dateField, $this->recipient);

        $this->assertSame('03-31-2026', $submitted->getDate());
        $this->assertSame('03-31-2026', $submitted->getValue());
    }

    public function testCheckboxField(): void
    {
        $checkboxField = Field::fromArray([
            'id' => 303,
            'document_file_id' => 200,
            'page' => 1,
            'party' => 1,
            'field_type_id' => FieldType::CHECKBOX,
            'name' => 'Checkbox',
            'recipient_id' => 400,
            'properties' => ['h' => 0.02, 'w' => 0.02, 'x' => 0.1, 'y' => 0.5, 'required' => true, 'label' => 'I Agree'],
            'recipient_field' => [
                'field_id' => 303,
                'properties' => ['checked' => true, 'committed' => true],
            ],
        ]);

        $submitted = SubmittedField::create($checkboxField, $this->recipient);

        $this->assertTrue($submitted->isChecked());
        $this->assertSame('true', $submitted->getValue());
        $this->assertSame('I Agree', $submitted->getLabel());
    }

    public function testGetProperties(): void
    {
        $this->assertInstanceOf(FieldProperties::class, $this->submittedField->getProperties());
    }

    public function testIsRequired(): void
    {
        $this->assertTrue($this->submittedField->isRequired());
    }

    public function testIsSignature(): void
    {
        $this->assertFalse($this->submittedField->isSignature());
    }

    public function testIsInitials(): void
    {
        $this->assertFalse($this->submittedField->isInitials());
    }

    public function testGetRecipientId(): void
    {
        $this->assertSame(400, $this->submittedField->getRecipientId());
    }

    public function testGetRecipientName(): void
    {
        $this->assertSame('John Doe', $this->submittedField->getRecipientName());
    }

    public function testGetRecipientEmail(): void
    {
        $this->assertSame('john@example.com', $this->submittedField->getRecipientEmail());
    }

    public function testGetField(): void
    {
        $this->assertSame($this->field, $this->submittedField->getField());
    }

    public function testGetRecipient(): void
    {
        $this->assertSame($this->recipient, $this->submittedField->getRecipient());
    }

    public function testToArray(): void
    {
        $array = $this->submittedField->toArray();

        $this->assertSame(300, $array['id']);
        $this->assertSame('Company Name', $array['name']);
        $this->assertSame('Company Name', $array['label']);
        $this->assertSame(FieldType::TEXT, $array['field_type_id']);
        $this->assertSame('Text', $array['field_type_name']);
        $this->assertSame('Acme Corp', $array['value']);
        $this->assertNull($array['image']);
        $this->assertTrue($array['is_required']);
        $this->assertFalse($array['is_signature']);
        $this->assertFalse($array['is_initials']);
        $this->assertSame(400, $array['recipient_id']);
        $this->assertSame('John Doe', $array['recipient_name']);
        $this->assertSame('john@example.com', $array['recipient_email']);
    }
}
