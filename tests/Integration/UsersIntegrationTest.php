<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use Breezedoc\Breezedoc;
use Breezedoc\Models\User;

class UsersIntegrationTest extends IntegrationTestCase
{
    public function testMe(): void
    {
        $client = Breezedoc::client($this->apiToken);

        $user = $client->users()->me();

        $this->assertInstanceOf(User::class, $user);
        $this->assertIsInt($user->getId());
        $this->assertNotEmpty($user->getName());
        $this->assertNotEmpty($user->getEmail());
        $this->assertNotNull($user->getCreatedAt());
    }
}
