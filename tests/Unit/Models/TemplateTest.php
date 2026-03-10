<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Models\Template;
use Breezedoc\Tests\Unit\UnitTestCase;
use DateTimeImmutable;

class TemplateTest extends UnitTestCase
{
    private array $sampleData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleData = [
            'id' => 12345,
            'title' => 'Test Template',
            'slug' => 'template123',
            'created_at' => '2025-01-10T08:00:00.000000Z',
            'updated_at' => '2025-01-12T09:30:00.000000Z',
        ];
    }

    public function testFromArrayCreatesTemplate(): void
    {
        $template = Template::fromArray($this->sampleData);

        $this->assertInstanceOf(Template::class, $template);
    }

    public function testGetId(): void
    {
        $template = Template::fromArray($this->sampleData);

        $this->assertSame(12345, $template->getId());
    }

    public function testGetTitle(): void
    {
        $template = Template::fromArray($this->sampleData);

        $this->assertSame('Test Template', $template->getTitle());
    }

    public function testGetSlug(): void
    {
        $template = Template::fromArray($this->sampleData);

        $this->assertSame('template123', $template->getSlug());
    }

    public function testGetCreatedAt(): void
    {
        $template = Template::fromArray($this->sampleData);

        $this->assertInstanceOf(DateTimeImmutable::class, $template->getCreatedAt());
    }

    public function testGetUpdatedAt(): void
    {
        $template = Template::fromArray($this->sampleData);

        $this->assertInstanceOf(DateTimeImmutable::class, $template->getUpdatedAt());
    }
}
