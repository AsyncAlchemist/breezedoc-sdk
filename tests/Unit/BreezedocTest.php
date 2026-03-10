<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit;

use Breezedoc\Breezedoc;
use Breezedoc\Client;
use Breezedoc\Config\Configuration;

class BreezedocTest extends UnitTestCase
{
    public function testClientCreatesClientWithToken(): void
    {
        $client = Breezedoc::client('test-token');

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testClientCreatesClientWithConfiguration(): void
    {
        $config = new Configuration('test-token');
        $config->setTimeout(60);

        $client = Breezedoc::client($config);

        $this->assertInstanceOf(Client::class, $client);
    }
}
