<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Models;

use Breezedoc\Models\User;
use Breezedoc\Tests\Unit\UnitTestCase;
use DateTimeImmutable;

class UserTest extends UnitTestCase
{
    private array $sampleData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2024-04-24T12:56:45.000000Z',
            'updated_at' => '2025-12-23T07:26:24.000000Z',
        ];
    }

    public function testFromArrayCreatesUser(): void
    {
        $user = User::fromArray($this->sampleData);

        $this->assertInstanceOf(User::class, $user);
    }

    public function testGetId(): void
    {
        $user = User::fromArray($this->sampleData);

        $this->assertSame(1, $user->getId());
    }

    public function testGetName(): void
    {
        $user = User::fromArray($this->sampleData);

        $this->assertSame('John Doe', $user->getName());
    }

    public function testGetEmail(): void
    {
        $user = User::fromArray($this->sampleData);

        $this->assertSame('john@example.com', $user->getEmail());
    }

    public function testGetCreatedAt(): void
    {
        $user = User::fromArray($this->sampleData);

        $this->assertInstanceOf(DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertSame('2024-04-24', $user->getCreatedAt()->format('Y-m-d'));
    }

    public function testGetUpdatedAt(): void
    {
        $user = User::fromArray($this->sampleData);

        $this->assertInstanceOf(DateTimeImmutable::class, $user->getUpdatedAt());
        $this->assertSame('2025-12-23', $user->getUpdatedAt()->format('Y-m-d'));
    }

    public function testToArrayReturnsOriginalData(): void
    {
        $user = User::fromArray($this->sampleData);
        $array = $user->toArray();

        $this->assertSame(1, $array['id']);
        $this->assertSame('John Doe', $array['name']);
        $this->assertSame('john@example.com', $array['email']);
    }
}
