<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use Breezedoc\Breezedoc;
use Breezedoc\Models\Recipient;
use Breezedoc\Pagination\PaginatedResult;

class RecipientsIntegrationTest extends IntegrationTestCase
{
    public function testList(): void
    {
        $client = Breezedoc::client($this->apiToken);

        $result = $client->recipients()->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertIsInt($result->getTotal());

        // If there are recipients, verify the first one
        if (count($result) > 0) {
            $recipient = $result->getItems()[0];
            $this->assertInstanceOf(Recipient::class, $recipient);
            $this->assertIsInt($recipient->getId());
            // Email is required but may be empty in some cases
            $this->assertIsString($recipient->getEmail());
        }
    }
}
