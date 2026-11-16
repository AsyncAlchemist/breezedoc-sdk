<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Models\Invoice;
use Breezedoc\Models\InvoiceItem;
use Breezedoc\Tests\Unit\UnitTestCase;

/**
 * Invoice fields added to the published spec after 0.5.0.
 */
class InvoiceNewFieldsTest extends UnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function invoiceData(array $extra = []): array
    {
        return array_merge([
            'id' => 1,
            'slug' => 'inv1',
            'currency' => 'USD',
            'status' => 'open',
            'description' => 'Services',
            'customer_email' => 'client@example.com',
            'total' => 135.0,
            'items' => [],
        ], $extra);
    }

    public function testNewFields(): void
    {
        $invoice = Invoice::fromArray($this->invoiceData([
            'title' => 'Invoice',
            'invoice_number' => 'INV-1001',
            'invoice_date' => '2026-11-01',
            'payment_terms' => 'net_30',
            'subtotal' => 150.0,
            'localized_subtotal' => '$150.00',
            'discount_type' => 'percentage',
            'discount_percentage' => 10,
            'discount_amount_cents' => null,
            'discount_label' => 'Launch discount',
            'discount' => 15.0,
            'localized_discount' => '$15.00',
            'category' => ['slug' => 'consulting', 'name' => 'Consulting'],
        ]));

        $this->assertTrue($invoice->isOpen());
        $this->assertSame(Invoice::STATUS_OPEN, $invoice->getStatus());
        $this->assertSame('Invoice', $invoice->getTitle());
        $this->assertSame('INV-1001', $invoice->getInvoiceNumber());
        $this->assertSame('2026-11-01', $invoice->getInvoiceDate());
        $this->assertSame('net_30', $invoice->getPaymentTerms());
        $this->assertSame(150.0, $invoice->getSubtotal());
        $this->assertSame('$150.00', $invoice->getLocalizedSubtotal());
        $this->assertSame('percentage', $invoice->getDiscountType());
        $this->assertSame(10.0, $invoice->getDiscountPercentage());
        $this->assertNull($invoice->getDiscountAmountCents());
        $this->assertSame('Launch discount', $invoice->getDiscountLabel());
        $this->assertSame(15.0, $invoice->getDiscount());
        $this->assertSame('$15.00', $invoice->getLocalizedDiscount());
        $this->assertSame(['slug' => 'consulting', 'name' => 'Consulting'], $invoice->getCategory());
    }

    public function testFlatDiscountAndMissingFields(): void
    {
        $invoice = Invoice::fromArray($this->invoiceData(['discount_type' => 'flat', 'discount_amount_cents' => 500]));

        $this->assertSame(500, $invoice->getDiscountAmountCents());
        $this->assertNull($invoice->getTitle());
        $this->assertNull($invoice->getSubtotal());
        $this->assertNull($invoice->getCategory());
        $this->assertFalse($invoice->isDraft());
    }

    public function testFractionalQuantity(): void
    {
        $item = InvoiceItem::fromArray([
            'id' => 1,
            'description' => 'Hours',
            'quantity' => 1.5,
            'formatted_quantity' => '1.5',
            'unit_price' => 10000,
            'total_price' => 15000,
        ]);

        $this->assertSame(1, $item->getQuantity());
        $this->assertSame(1.5, $item->getQuantityDecimal());
        $this->assertSame('1.5', $item->getFormattedQuantity());
    }
}
