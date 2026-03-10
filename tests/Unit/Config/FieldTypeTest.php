<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Config;

use Breezedoc\Config\FieldType;
use Breezedoc\Tests\Unit\UnitTestCase;

class FieldTypeTest extends UnitTestCase
{
    public function testSignatureConstant(): void
    {
        $this->assertSame('13eb6f62-cc8b-466a-9e25-400eb8f596aa', FieldType::SIGNATURE);
    }

    public function testInitialsConstant(): void
    {
        $this->assertSame('195ff45a-6a44-40a6-b9c6-d24d69b6aac6', FieldType::INITIALS);
    }

    public function testDateConstant(): void
    {
        $this->assertSame('c96b9268-7266-4304-a0c3-2dc058c87a84', FieldType::DATE);
    }

    public function testTextConstant(): void
    {
        $this->assertSame('c8ca9a67-4f54-4429-a409-ac58418bd1bc', FieldType::TEXT);
    }

    public function testEmailConstant(): void
    {
        $this->assertSame('e3b2c44d-5f6a-4e8b-9d1c-2f3e4a5b6c7d', FieldType::EMAIL);
    }

    public function testDropdownConstant(): void
    {
        $this->assertSame('6f0fbecf-3bba-4b8a-87b0-b7eb49662661', FieldType::DROPDOWN);
    }

    public function testCheckboxConstant(): void
    {
        $this->assertSame('6bcdb12d-7364-4ada-9427-13831827a995', FieldType::CHECKBOX);
    }

    public function testGetAllReturnsAllTypes(): void
    {
        $all = FieldType::getAll();

        $this->assertIsArray($all);
        $this->assertCount(7, $all);
        $this->assertContains(FieldType::SIGNATURE, $all);
        $this->assertContains(FieldType::INITIALS, $all);
        $this->assertContains(FieldType::DATE, $all);
        $this->assertContains(FieldType::TEXT, $all);
        $this->assertContains(FieldType::EMAIL, $all);
        $this->assertContains(FieldType::DROPDOWN, $all);
        $this->assertContains(FieldType::CHECKBOX, $all);
    }

    public function testGetNameReturnsCorrectName(): void
    {
        $this->assertSame('Signature', FieldType::getName(FieldType::SIGNATURE));
        $this->assertSame('Initials', FieldType::getName(FieldType::INITIALS));
        $this->assertSame('Date', FieldType::getName(FieldType::DATE));
        $this->assertSame('Text', FieldType::getName(FieldType::TEXT));
        $this->assertSame('Email', FieldType::getName(FieldType::EMAIL));
        $this->assertSame('Dropdown', FieldType::getName(FieldType::DROPDOWN));
        $this->assertSame('Checkbox', FieldType::getName(FieldType::CHECKBOX));
    }

    public function testGetNameReturnsNullForUnknownType(): void
    {
        $this->assertNull(FieldType::getName('unknown-uuid'));
    }

    public function testIsValidReturnsTrueForValidTypes(): void
    {
        $this->assertTrue(FieldType::isValid(FieldType::SIGNATURE));
        $this->assertTrue(FieldType::isValid(FieldType::INITIALS));
        $this->assertTrue(FieldType::isValid(FieldType::DATE));
        $this->assertTrue(FieldType::isValid(FieldType::TEXT));
        $this->assertTrue(FieldType::isValid(FieldType::EMAIL));
        $this->assertTrue(FieldType::isValid(FieldType::DROPDOWN));
        $this->assertTrue(FieldType::isValid(FieldType::CHECKBOX));
    }

    public function testIsValidReturnsFalseForInvalidTypes(): void
    {
        $this->assertFalse(FieldType::isValid('unknown-uuid'));
        $this->assertFalse(FieldType::isValid(''));
        $this->assertFalse(FieldType::isValid('12345'));
    }
}
