<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Http;

use Breezedoc\Http\HttpClientFactory;
use Breezedoc\Tests\Unit\UnitTestCase;
use Http\Mock\Client as MockHttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class HttpClientFactoryTest extends UnitTestCase
{
    public function testCreateHttpClientReturnsClientInterface(): void
    {
        $client = HttpClientFactory::createHttpClient();

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testCreateHttpClientWithCustomClient(): void
    {
        $mockClient = new MockHttpClient();
        $client = HttpClientFactory::createHttpClient($mockClient);

        $this->assertSame($mockClient, $client);
    }

    public function testCreateRequestFactoryReturnsRequestFactoryInterface(): void
    {
        $factory = HttpClientFactory::createRequestFactory();

        $this->assertInstanceOf(RequestFactoryInterface::class, $factory);
    }

    public function testCreateStreamFactoryReturnsStreamFactoryInterface(): void
    {
        $factory = HttpClientFactory::createStreamFactory();

        $this->assertInstanceOf(StreamFactoryInterface::class, $factory);
    }
}
