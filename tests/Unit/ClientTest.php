<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit;

use Breezedoc\Api\Documents;
use Breezedoc\Api\Invoices;
use Breezedoc\Api\Recipients;
use Breezedoc\Api\Teams;
use Breezedoc\Api\Templates;
use Breezedoc\Api\Users;
use Breezedoc\Client;
use Breezedoc\Config\Configuration;
use Http\Mock\Client as MockHttpClient;

class ClientTest extends UnitTestCase
{
    public function testConstructorWithToken(): void
    {
        $client = new Client('test-token');

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testConstructorWithConfiguration(): void
    {
        $config = new Configuration('test-token');
        $config->setTimeout(60);
        $client = new Client($config);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testConstructorWithCustomHttpClient(): void
    {
        $mockClient = new MockHttpClient();
        $client = new Client('test-token', $mockClient);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testUsersReturnsUsersApi(): void
    {
        $client = new Client('test-token');

        $users = $client->users();

        $this->assertInstanceOf(Users::class, $users);
    }

    public function testUsersReturnsSameInstance(): void
    {
        $client = new Client('test-token');

        $users1 = $client->users();
        $users2 = $client->users();

        $this->assertSame($users1, $users2);
    }

    public function testDocumentsReturnsDocumentsApi(): void
    {
        $client = new Client('test-token');

        $documents = $client->documents();

        $this->assertInstanceOf(Documents::class, $documents);
    }

    public function testTemplatesReturnsTemplatesApi(): void
    {
        $client = new Client('test-token');

        $templates = $client->templates();

        $this->assertInstanceOf(Templates::class, $templates);
    }

    public function testRecipientsReturnsRecipientsApi(): void
    {
        $client = new Client('test-token');

        $recipients = $client->recipients();

        $this->assertInstanceOf(Recipients::class, $recipients);
    }

    public function testInvoicesReturnsInvoicesApi(): void
    {
        $client = new Client('test-token');

        $invoices = $client->invoices();

        $this->assertInstanceOf(Invoices::class, $invoices);
    }

    public function testTeamsReturnsTeamsApi(): void
    {
        $client = new Client('test-token');

        $teams = $client->teams();

        $this->assertInstanceOf(Teams::class, $teams);
    }
}
