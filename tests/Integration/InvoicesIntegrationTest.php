<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use Breezedoc\Breezedoc;
use Breezedoc\Models\Invoice;
use Breezedoc\Pagination\PaginatedResult;

class InvoicesIntegrationTest extends IntegrationTestCase
{
    public function testList(): void
    {
        $client = Breezedoc::client($this->apiToken);

        $result = $client->invoices()->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertIsInt($result->getTotal());

        // If there are invoices, verify the first one
        if (count($result) > 0) {
            $invoice = $result->getItems()[0];
            $this->assertInstanceOf(Invoice::class, $invoice);
            $this->assertIsInt($invoice->getId());
        }
    }

    public function testCreateAndDeleteInvoice(): void
    {
        $client = Breezedoc::client($this->apiToken);

        // Create a test invoice
        $invoice = $client->invoices()->create([
            'customer_email' => $this->getTestEmail(),
            'customer_name' => 'SDK Test',
            'currency' => 'USD',
            'description' => 'SDK Integration Test Invoice',
            'payment_due' => date('Y-m-d', strtotime('+30 days')),
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 1000, // $10.00 in cents
                ],
            ],
        ]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertIsInt($invoice->getId());
        $this->assertTrue($invoice->isDraft());
        $this->assertSame('USD', $invoice->getCurrency());
        $this->assertSame($this->getTestEmail(), $invoice->getCustomerEmail());

        // Clean up - delete the test invoice
        $result = $client->invoices()->destroy($invoice->getId());
        $this->assertTrue($result);
    }

    public function testUpdateInvoice(): void
    {
        $client = Breezedoc::client($this->apiToken);

        // Create a test invoice
        $invoice = $client->invoices()->create([
            'customer_email' => $this->getTestEmail(),
            'currency' => 'USD',
            'description' => 'Original Description',
            'payment_due' => date('Y-m-d', strtotime('+30 days')),
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ]);

        // Update the invoice
        $updated = $client->invoices()->update($invoice->getId(), [
            'description' => 'Updated Description',
        ]);

        $this->assertSame('Updated Description', $updated->getDescription());

        // Clean up
        $client->invoices()->destroy($invoice->getId());
    }
}
