<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Models\FieldProperties;
use Breezedoc\Tests\Unit\UnitTestCase;

class FieldPropertiesTest extends UnitTestCase
{
    private array $sampleData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleData = [
            'x' => 0.1,
            'y' => 0.2,
            'w' => 0.5,
            'h' => 0.05,
            'required' => true,
            'label' => 'Full Name',
            'fontFamily' => 'Helvetica',
            'defaultValue' => 'John Doe',
            'options' => ['option1', 'option2'],
        ];
    }

    public function testFromArrayCreatesFieldProperties(): void
    {
        $props = FieldProperties::fromArray($this->sampleData);

        $this->assertInstanceOf(FieldProperties::class, $props);
        $this->assertSame(0.1, $props->getX());
        $this->assertSame(0.2, $props->getY());
        $this->assertSame(0.5, $props->getWidth());
        $this->assertSame(0.05, $props->getHeight());
        $this->assertTrue($props->isRequired());
        $this->assertSame('Full Name', $props->getLabel());
        $this->assertSame('Helvetica', $props->getFontFamily());
        $this->assertSame('John Doe', $props->getDefaultValue());
        $this->assertSame(['option1', 'option2'], $props->getOptions());
    }

    public function testFromArrayHandlesFalseDefaultValue(): void
    {
        $data = $this->sampleData;
        $data['defaultValue'] = false;

        $props = FieldProperties::fromArray($data);

        $this->assertNull($props->getDefaultValue());
    }

    public function testFromArrayHandlesFalseLabel(): void
    {
        $data = $this->sampleData;
        $data['label'] = false;

        $props = FieldProperties::fromArray($data);

        $this->assertNull($props->getLabel());
    }

    public function testFromArrayHandlesFalseFontFamily(): void
    {
        $data = $this->sampleData;
        $data['fontFamily'] = false;

        $props = FieldProperties::fromArray($data);

        $this->assertNull($props->getFontFamily());
    }

    public function testFromArrayHandlesFalseOptions(): void
    {
        $data = $this->sampleData;
        $data['options'] = false;

        $props = FieldProperties::fromArray($data);

        $this->assertNull($props->getOptions());
    }

    public function testFromArrayHandlesNullValues(): void
    {
        $data = $this->sampleData;
        $data['defaultValue'] = null;
        $data['label'] = null;
        $data['fontFamily'] = null;
        $data['options'] = null;

        $props = FieldProperties::fromArray($data);

        $this->assertNull($props->getDefaultValue());
        $this->assertNull($props->getLabel());
        $this->assertNull($props->getFontFamily());
        $this->assertNull($props->getOptions());
    }

    public function testFromArrayHandlesMissingKeys(): void
    {
        $data = [
            'x' => 0.1,
            'y' => 0.2,
            'w' => 0.5,
            'h' => 0.05,
        ];

        $props = FieldProperties::fromArray($data);

        $this->assertFalse($props->isRequired());
        $this->assertNull($props->getDefaultValue());
        $this->assertNull($props->getLabel());
        $this->assertNull($props->getFontFamily());
        $this->assertNull($props->getOptions());
    }
}
