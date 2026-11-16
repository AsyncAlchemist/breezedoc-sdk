<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Config;

use Breezedoc\Config\Configuration;
use Breezedoc\Tests\Unit\UnitTestCase;
use InvalidArgumentException;

class TeamConfigurationTest extends UnitTestCase
{
    public function testNoTeamApiKeyByDefault(): void
    {
        $config = new Configuration('personal-token');

        $this->assertFalse($config->hasTeamApiKey());
        $this->assertNull($config->getTeamApiKey());
    }

    public function testSetTeamApiKeyKeepsThePersonalToken(): void
    {
        $config = new Configuration('personal-token');
        $result = $config->setTeamApiKey('bd_live_abc');

        $this->assertSame($config, $result);
        $this->assertTrue($config->hasTeamApiKey());
        $this->assertSame('bd_live_abc', $config->getTeamApiKey());
        $this->assertSame('personal-token', $config->getToken());
    }

    public function testSetTeamApiKeyRejectsEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Configuration('personal-token'))->setTeamApiKey('');
    }

    public function testForTeamApiKey(): void
    {
        $config = Configuration::forTeamApiKey('bd_live_abc');

        $this->assertSame('bd_live_abc', $config->getTeamApiKey());
        $this->assertSame('bd_live_abc', $config->getToken());
    }

    public function testDefaultTeamBaseUrl(): void
    {
        $config = new Configuration('personal-token');

        $this->assertSame('https://breezedoc.com/api/v1', $config->getTeamBaseUrl());
        $this->assertSame('https://breezedoc.com/api', $config->getBaseUrl());
    }

    public function testSetTeamBaseUrl(): void
    {
        $config = new Configuration('personal-token');
        $result = $config->setTeamBaseUrl('https://example.test/api/v1');

        $this->assertSame($config, $result);
        $this->assertSame('https://example.test/api/v1', $config->getTeamBaseUrl());
    }
}
