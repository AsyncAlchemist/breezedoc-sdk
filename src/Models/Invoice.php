<?php

declare(strict_types=1);

namespace Breezedoc\Models;

use DateTimeImmutable;

/**
 * Represents a Breezedoc invoice.
 */
class Invoice extends AbstractModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PAID = 'paid';
    public const STATUS_UNCOLLECTIBLE = 'uncollectible';
    public const STATUS_VOID = 'void';

    private int $id;
    private string $slug;
    private string $currency;
    private string $status;
    private string $description;
    private ?string $customerName;
    private string $customerEmail;
    private ?string $paymentDue;
    private ?string $footerNote;
    private float $total;
    private ?string $localizedTotal;
    private ?DateTimeImmutable $sentAt;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;
    private ?string $payUrl;

    /**
     * @var array<InvoiceItem>
     */
    private array $items = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $invoice = new self();
        $invoice->rawData = $data;

        $invoice->id = (int) $data['id'];
        $invoice->slug = (string) $data['slug'];
        $invoice->currency = (string) $data['currency'];
        $invoice->status = (string) $data['status'];
        $invoice->description = (string) $data['description'];
        $invoice->customerName = $data['customer_name'] ?? null;
        $invoice->customerEmail = (string) $data['customer_email'];
        $invoice->paymentDue = $data['payment_due'] ?? null;
        $invoice->footerNote = $data['footer_note'] ?? null;
        $invoice->total = (float) $data['total'];
        $invoice->localizedTotal = $data['localized_total'] ?? null;
        $invoice->sentAt = self::parseDateTime($data['sent_at'] ?? null);
        $invoice->createdAt = self::parseDateTime($data['created_at'] ?? null);
        $invoice->updatedAt = self::parseDateTime($data['updated_at'] ?? null);
        $invoice->payUrl = $data['pay_url'] ?? null;

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $itemData) {
                $invoice->items[] = InvoiceItem::fromArray($itemData);
            }
        }

        return $invoice;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isUncollectible(): bool
    {
        return $this->status === self::STATUS_UNCOLLECTIBLE;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getPaymentDue(): ?string
    {
        return $this->paymentDue;
    }

    public function getFooterNote(): ?string
    {
        return $this->footerNote;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getLocalizedTotal(): ?string
    {
        return $this->localizedTotal;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPayUrl(): ?string
    {
        return $this->payUrl;
    }

    /**
     * @return array<InvoiceItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
