<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Models\Invoice;
use Breezedoc\Models\InvoiceItem;
use Breezedoc\Tests\Unit\UnitTestCase;
use DateTimeImmutable;

class InvoiceTest extends UnitTestCase
{
    private array $sampleData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleData = [
            'id' => 123,
            'slug' => 'inv123',
            'currency' => 'USD',
            'status' => 'draft',
            'description' => 'Invoice for services',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'payment_due' => '2026-02-01',
            'footer_note' => 'Thank you for your business',
            'total' => 150.00,
            'localized_total' => '$150.00',
            'sent_at' => null,
            'created_at' => '2026-01-30T12:00:00.000000Z',
            'updated_at' => '2026-01-30T12:00:00.000000Z',
            'items' => [
                [
                    'id' => 1,
                    'description' => 'Consulting services',
                    'details' => 'January 2026',
                    'quantity' => 3,
                    'unit_price' => 5000,
                    'total_price' => 15000,
                    'localized_unit_price' => '$50.00',
                    'localized_total' => '$150.00',
                ],
            ],
            'pay_url' => 'https://breezedoc.com/pay/inv123',
        ];
    }

    public function testFromArrayCreatesInvoice(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertInstanceOf(Invoice::class, $invoice);
    }

    public function testGetId(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame(123, $invoice->getId());
    }

    public function testGetSlug(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('inv123', $invoice->getSlug());
    }

    public function testGetCurrency(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('USD', $invoice->getCurrency());
    }

    public function testGetStatus(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('draft', $invoice->getStatus());
    }

    public function testIsDraft(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertTrue($invoice->isDraft());
    }

    public function testIsPaid(): void
    {
        $data = $this->sampleData;
        $data['status'] = 'paid';
        $invoice = Invoice::fromArray($data);

        $this->assertTrue($invoice->isPaid());
        $this->assertFalse($invoice->isDraft());
    }

    public function testGetDescription(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('Invoice for services', $invoice->getDescription());
    }

    public function testGetCustomerName(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('John Doe', $invoice->getCustomerName());
    }

    public function testGetCustomerEmail(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('john@example.com', $invoice->getCustomerEmail());
    }

    public function testGetPaymentDue(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('2026-02-01', $invoice->getPaymentDue());
    }

    public function testGetFooterNote(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('Thank you for your business', $invoice->getFooterNote());
    }

    public function testGetTotal(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame(150.00, $invoice->getTotal());
    }

    public function testGetLocalizedTotal(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('$150.00', $invoice->getLocalizedTotal());
    }

    public function testGetSentAtWhenNull(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertNull($invoice->getSentAt());
        $this->assertFalse($invoice->isSent());
    }

    public function testGetSentAtWhenSet(): void
    {
        $data = $this->sampleData;
        $data['sent_at'] = '2026-01-31T10:00:00.000000Z';
        $invoice = Invoice::fromArray($data);

        $this->assertInstanceOf(DateTimeImmutable::class, $invoice->getSentAt());
        $this->assertTrue($invoice->isSent());
    }

    public function testGetCreatedAt(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertInstanceOf(DateTimeImmutable::class, $invoice->getCreatedAt());
    }

    public function testGetItems(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);
        $items = $invoice->getItems();

        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertInstanceOf(InvoiceItem::class, $items[0]);
    }

    public function testGetPayUrl(): void
    {
        $invoice = Invoice::fromArray($this->sampleData);

        $this->assertSame('https://breezedoc.com/pay/inv123', $invoice->getPayUrl());
    }
}
