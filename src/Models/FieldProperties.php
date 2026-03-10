<?php

declare(strict_types=1);

namespace Breezedoc\Models;

/**
 * Represents field positioning and properties.
 *
 * Coordinates are percentages (0-1) relative to the page dimensions.
 */
class FieldProperties extends AbstractModel
{
    private float $x;
    private float $y;
    private float $width;
    private float $height;
    private bool $required;
    private ?string $label;
    private ?string $fontFamily;
    private ?string $defaultValue;

    /**
     * @var array<string>|null
     */
    private ?array $options;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $props = new self();
        $props->rawData = $data;

        $props->x = (float) ($data['x'] ?? 0);
        $props->y = (float) ($data['y'] ?? 0);
        $props->width = (float) ($data['w'] ?? 0);
        $props->height = (float) ($data['h'] ?? 0);
        $props->required = (bool) ($data['required'] ?? false);
        $props->label = $data['label'] ?? null;
        $props->fontFamily = $data['fontFamily'] ?? null;
        $props->defaultValue = $data['defaultValue'] ?? null;
        $props->options = $data['options'] ?? null;

        return $props;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getFontFamily(): ?string
    {
        return $this->fontFamily;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    /**
     * @return array<string>|null
     */
    public function getOptions(): ?array
    {
        return $this->options;
    }
}
