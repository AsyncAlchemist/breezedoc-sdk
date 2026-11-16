<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Http;

use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Tests\Unit\UnitTestCase;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;

class TeamRequestBuilderTest extends UnitTestCase
{
    public function testDefaultBuilderIsNotTeamApi(): void
    {
        $factory = new Psr17Factory();
        $builder = new RequestBuilder(new Configuration('personal-token'), $factory, $factory);

        $this->assertFalse($builder->isTeamApi());
    }

    public function testTeamBuilderUsesTeamBaseUrlAndKey(): void
    {
        $factory = new Psr17Factory();
        $config = (new Configuration('personal-token'))->setTeamApiKey('bd_live_abc');
        $builder = RequestBuilder::forTeamApi($config, $factory, $factory);

        $request = $builder->build('GET', '/documents', null, ['limit' => 10]);

        $this->assertTrue($builder->isTeamApi());
        $this->assertSame('https://breezedoc.com/api/v1/documents?limit=10', (string) $request->getUri());
        $this->assertSame('Bearer bd_live_abc', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testOriginalBuilderStillUsesTokenWhenATeamKeyIsSet(): void
    {
        $factory = new Psr17Factory();
        $config = (new Configuration('personal-token'))->setTeamApiKey('bd_live_abc');
        $builder = new RequestBuilder($config, $factory, $factory);

        $request = $builder->build('GET', '/documents');

        $this->assertSame('https://breezedoc.com/api/documents', (string) $request->getUri());
        $this->assertSame('Bearer personal-token', $request->getHeaderLine('Authorization'));
    }

    public function testTeamBuilderWithoutKeyThrows(): void
    {
        $factory = new Psr17Factory();
        $builder = RequestBuilder::forTeamApi(new Configuration('personal-token'), $factory, $factory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('team API key');
        $builder->build('GET', '/me');
    }

    public function testCustomTeamBaseUrl(): void
    {
        $factory = new Psr17Factory();
        $config = Configuration::forTeamApiKey('bd_live_abc')->setTeamBaseUrl('https://example.test/api/v1/');
        $builder = RequestBuilder::forTeamApi($config, $factory, $factory);

        $this->assertSame('https://example.test/api/v1/me', (string) $builder->build('GET', 'me')->getUri());
    }

    public function testCreateStream(): void
    {
        $factory = new Psr17Factory();
        $builder = new RequestBuilder(new Configuration('personal-token'), $factory, $factory);

        $this->assertSame('payload', (string) $builder->createStream('payload'));
    }
}
