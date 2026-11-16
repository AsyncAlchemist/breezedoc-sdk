<?php

declare(strict_types=1);

namespace Breezedoc\Models;

/**
 * Represents a line item on an invoice.
 *
 * Note: All monetary values (unit_price, total_price) are in cents.
 */
class InvoiceItem extends AbstractModel
{
    private int $id;
    private string $description;
    private ?string $details;
    private int $quantity;
    private float $quantityDecimal;
    private int $unitPrice;
    private int $totalPrice;
    private ?string $localizedUnitPrice;
    private ?string $localizedTotal;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $item = new self();
        $item->rawData = $data;

        $item->id = (int) $data['id'];
        $item->description = (string) $data['description'];
        $item->details = $data['details'] ?? null;
        $item->quantity = (int) $data['quantity'];
        $item->quantityDecimal = (float) $data['quantity'];
        $item->unitPrice = (int) $data['unit_price'];
        $item->totalPrice = (int) $data['total_price'];
        $item->localizedUnitPrice = $data['localized_unit_price'] ?? null;
        $item->localizedTotal = $data['localized_total'] ?? null;

        return $item;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    /**
     * The quantity as an integer.
     *
     * The API now allows fractional quantities (minimum 0.01); this getter truncates them.
     * Use {@see getQuantityDecimal()} for the exact value.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * The exact quantity, which can be fractional (for example 1.5).
     */
    public function getQuantityDecimal(): float
    {
        return $this->quantityDecimal;
    }

    /**
     * The quantity formatted for display, with trailing zeros trimmed, or null.
     */
    public function getFormattedQuantity(): ?string
    {
        $value = $this->rawData['formatted_quantity'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Get the unit price in cents.
     */
    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    /**
     * Get the unit price in dollars/currency.
     */
    public function getUnitPriceDecimal(): float
    {
        return $this->unitPrice / 100;
    }

    /**
     * Get the total price in cents.
     */
    public function getTotalPrice(): int
    {
        return $this->totalPrice;
    }

    /**
     * Get the total price in dollars/currency.
     */
    public function getTotalPriceDecimal(): float
    {
        return $this->totalPrice / 100;
    }

    public function getLocalizedUnitPrice(): ?string
    {
        return $this->localizedUnitPrice;
    }

    public function getLocalizedTotal(): ?string
    {
        return $this->localizedTotal;
    }
}
