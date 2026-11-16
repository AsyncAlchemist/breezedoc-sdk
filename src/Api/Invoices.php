<?php

declare(strict_types=1);

namespace Breezedoc\Api;

use Breezedoc\Models\Invoice;
use Breezedoc\Pagination\FormatBPaginator;
use Breezedoc\Pagination\PaginatedResult;

/**
 * Invoices API resource.
 *
 * Note: All invoice responses from the API are wrapped in {"data": {...}}.
 * This class handles unwrapping automatically.
 */
class Invoices extends AbstractApi
{
    /**
     * List invoices.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - status: Filter by status (the spec lists draft, sent, paid, uncollectible, void)
     *   - order_by: Sort field (id, created_at, payment_due)
     *   - direction: Sort direction (asc, desc)
     *   - page: Page number
     * @return PaginatedResult<Invoice>
     */
    public function list(array $params = []): PaginatedResult
    {
        $data = $this->doGet('/invoices', $params);
        return (new FormatBPaginator())->parse($data, Invoice::class);
    }

    /**
     * Get an invoice by ID.
     */
    public function find(int $id): Invoice
    {
        $data = $this->doGet('/invoices/' . $id);
        return Invoice::fromArray($this->unwrapData($data));
    }

    /**
     * Create a new invoice.
     *
     * @param array<string, mixed> $data Invoice data:
     *   - customer_email: Customer email (required)
     *   - customer_name: Customer name (optional)
     *   - currency: ISO 4217 currency code (required, will be uppercased)
     *   - description: Invoice description (required)
     *   - payment_due: Due date YYYY-MM-DD (required)
     *   - footer_note: Footer note (optional)
     *   - title: Invoice title, max 50 chars (optional, default "Invoice")
     *   - invoice_number: Reference number, max 20 chars of [a-zA-Z0-9-_] (optional)
     *   - payment_terms: due_on_receipt, net_15, net_30, net_60, net_90 or custom (optional;
     *     still send the calculated payment_due)
     *   - items: Array of line items (required, min 1):
     *     - description: Item description (required)
     *     - details: Item details (optional)
     *     - quantity: Quantity (required, min 0.01; fractions allowed)
     *     - unit_price: Unit price in cents (required)
     *   - discount_type: percentage or flat (optional)
     *   - discount_percentage: 0-100, required with discount_type percentage
     *   - discount_amount_cents: required with discount_type flat (max: the subtotal)
     *   - discount_label: max 50 chars (optional)
     *   - payment_platform_ids: Array of payment platform UUIDs (optional)
     *   - send: Send immediately if true (optional)
     */
    public function create(array $data): Invoice
    {
        $data = $this->normalizeData($data);
        $response = $this->post('/invoices', $data);
        return Invoice::fromArray($this->unwrapData($response));
    }

    /**
     * Update an invoice.
     *
     * @param array<string, mixed> $data Fields to update (same as create, all optional)
     */
    public function update(int $id, array $data): Invoice
    {
        $data = $this->normalizeData($data);
        $response = $this->put('/invoices/' . $id, $data);
        return Invoice::fromArray($this->unwrapData($response));
    }

    /**
     * Partially update an invoice.
     *
     * @param array<string, mixed> $data Fields to update
     */
    public function partialUpdate(int $id, array $data): Invoice
    {
        $data = $this->normalizeData($data);
        $response = $this->doPatch('/invoices/' . $id, $data);
        return Invoice::fromArray($this->unwrapData($response));
    }

    /**
     * Delete a draft invoice.
     *
     * Only draft invoices can be deleted.
     */
    public function destroy(int $id): bool
    {
        $this->doDelete('/invoices/' . $id);
        return true;
    }

    /**
     * Send an invoice to the customer.
     */
    public function send(int $id): Invoice
    {
        $response = $this->post('/invoices/' . $id . '/send');
        return Invoice::fromArray($this->unwrapData($response));
    }

    /**
     * Normalize invoice data before sending.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        // Uppercase currency as required by the API
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        return $data;
    }

    /**
     * Unwrap the data from the API response.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function unwrapData(array $response): array
    {
        return $response['data'] ?? $response;
    }

    /**
     * Internal GET request (to avoid collision with parent::get).
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function doGet(string $path, array $query = []): array
    {
        return parent::get($path, $query);
    }

    /**
     * Internal PATCH request (to avoid collision with public patch method).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function doPatch(string $path, array $body): array
    {
        return parent::patch($path, $body);
    }

    /**
     * Internal DELETE request.
     *
     * @return array<string, mixed>
     */
    private function doDelete(string $path): array
    {
        return parent::delete($path);
    }
}
