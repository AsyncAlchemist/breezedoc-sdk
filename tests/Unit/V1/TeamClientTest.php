<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\V1;

use Breezedoc\Breezedoc;
use Breezedoc\Client;
use Breezedoc\Config\Configuration;
use Breezedoc\Tests\Unit\UnitTestCase;
use Breezedoc\V1\Api\Documents;
use Breezedoc\V1\Api\Templates;
use Breezedoc\V1\Api\Webhooks;
use Breezedoc\V1\Models\TeamAccount;
use Breezedoc\V1\TeamClient;
use InvalidArgumentException;

class TeamClientTest extends UnitTestCase
{
    public function testTeamClientFromKey(): void
    {
        $team = Breezedoc::teamClient('bd_live_abc', $this->createMockHttpClient());

        $this->assertInstanceOf(TeamClient::class, $team);
        $this->assertInstanceOf(Documents::class, $team->documents());
        $this->assertSame($team->documents(), $team->documents());
        $this->assertInstanceOf(Templates::class, $team->templates());
        $this->assertInstanceOf(Webhooks::class, $team->webhooks());
    }

    public function testTeamClientRequiresATeamKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TeamClient(new Configuration('personal-token'), $this->createMockHttpClient());
    }

    public function testMe(): void
    {
        $http = $this->createMockHttpClient();
        $http->addResponse($this->createJsonResponse([
            'team' => ['id' => 7, 'name' => 'Ops'],
            'usage' => ['remaining_documents' => null],
        ]));
        $team = Breezedoc::teamClient('bd_live_abc', $http);

        $account = $team->me();

        $this->assertInstanceOf(TeamAccount::class, $account);
        $this->assertSame(['id' => 7, 'name' => 'Ops'], $account->get('team'));
        $this->assertNull($account->getRemainingDocuments());
        $request = $http->getRequests()[0];
        $this->assertSame('https://breezedoc.com/api/v1/me', (string) $request->getUri());
        $this->assertSame('Bearer bd_live_abc', $request->getHeaderLine('Authorization'));
    }

    public function testClientTeamUsesTheTeamKeyAndTheOriginalApiKeepsTheToken(): void
    {
        $http = $this->createMockHttpClient();
        $http->addResponse($this->createJsonResponse(['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com']));
        $http->addResponse($this->createJsonResponse(['id' => 'doc_1', 'status' => 'sent']));
        $config = (new Configuration('personal-token'))->setTeamApiKey('bd_live_abc');
        $client = new Client($config, $http);

        $client->users()->me();
        $client->team()->documents()->find('doc_1');

        $requests = $http->getRequests();
        $this->assertSame('https://breezedoc.com/api/me', (string) $requests[0]->getUri());
        $this->assertSame('Bearer personal-token', $requests[0]->getHeaderLine('Authorization'));
        $this->assertSame('https://breezedoc.com/api/v1/documents/doc_1', (string) $requests[1]->getUri());
        $this->assertSame('Bearer bd_live_abc', $requests[1]->getHeaderLine('Authorization'));
        $this->assertSame($client->team(), $client->team());
    }

    public function testClientTeamWithoutKeyThrows(): void
    {
        $client = new Client('personal-token', $this->createMockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('setTeamApiKey');
        $client->team();
    }
}
