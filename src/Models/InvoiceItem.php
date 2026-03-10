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

    public function getQuantity(): int
    {
        return $this->quantity;
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
