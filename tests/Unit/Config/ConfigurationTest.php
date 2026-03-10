<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Config;

use Breezedoc\Config\Configuration;
use Breezedoc\Tests\Unit\UnitTestCase;
use InvalidArgumentException;

class ConfigurationTest extends UnitTestCase
{
    public function testConstructorRequiresToken(): void
    {
        $config = new Configuration('test-token');
        $this->assertSame('test-token', $config->getToken());
    }

    public function testConstructorRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API token cannot be empty');
        new Configuration('');
    }

    public function testDefaultBaseUrl(): void
    {
        $config = new Configuration('test-token');
        $this->assertSame('https://breezedoc.com/api', $config->getBaseUrl());
    }

    public function testSetBaseUrl(): void
    {
        $config = new Configuration('test-token');
        $config->setBaseUrl('https://custom.example.com/api');
        $this->assertSame('https://custom.example.com/api', $config->getBaseUrl());
    }

    public function testSetBaseUrlReturnsSelf(): void
    {
        $config = new Configuration('test-token');
        $result = $config->setBaseUrl('https://custom.example.com/api');
        $this->assertSame($config, $result);
    }

    public function testDefaultTimeout(): void
    {
        $config = new Configuration('test-token');
        $this->assertSame(30, $config->getTimeout());
    }

    public function testSetTimeout(): void
    {
        $config = new Configuration('test-token');
        $config->setTimeout(60);
        $this->assertSame(60, $config->getTimeout());
    }

    public function testSetTimeoutReturnsSelf(): void
    {
        $config = new Configuration('test-token');
        $result = $config->setTimeout(60);
        $this->assertSame($config, $result);
    }

    public function testSetTimeoutRejectsNegativeValues(): void
    {
        $config = new Configuration('test-token');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be a positive integer');
        $config->setTimeout(-1);
    }

    public function testSetTimeoutRejectsZero(): void
    {
        $config = new Configuration('test-token');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be a positive integer');
        $config->setTimeout(0);
    }

    public function testDefaultMaxRetries(): void
    {
        $config = new Configuration('test-token');
        $this->assertSame(3, $config->getMaxRetries());
    }

    public function testSetMaxRetries(): void
    {
        $config = new Configuration('test-token');
        $config->setMaxRetries(5);
        $this->assertSame(5, $config->getMaxRetries());
    }

    public function testSetMaxRetriesReturnsSelf(): void
    {
        $config = new Configuration('test-token');
        $result = $config->setMaxRetries(5);
        $this->assertSame($config, $result);
    }

    public function testSetMaxRetriesAcceptsZero(): void
    {
        $config = new Configuration('test-token');
        $config->setMaxRetries(0);
        $this->assertSame(0, $config->getMaxRetries());
    }

    public function testSetMaxRetriesRejectsNegativeValues(): void
    {
        $config = new Configuration('test-token');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max retries cannot be negative');
        $config->setMaxRetries(-1);
    }

    public function testFluentInterface(): void
    {
        $config = new Configuration('test-token');

        $result = $config
            ->setBaseUrl('https://custom.example.com/api')
            ->setTimeout(60)
            ->setMaxRetries(5);

        $this->assertSame($config, $result);
        $this->assertSame('https://custom.example.com/api', $config->getBaseUrl());
        $this->assertSame(60, $config->getTimeout());
        $this->assertSame(5, $config->getMaxRetries());
    }
}
