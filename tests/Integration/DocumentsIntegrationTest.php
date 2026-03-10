<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use Breezedoc\Breezedoc;
use Breezedoc\Models\Document;
use Breezedoc\Pagination\PaginatedResult;

class DocumentsIntegrationTest extends IntegrationTestCase
{
    public function testList(): void
    {
        $client = Breezedoc::client($this->apiToken);

        $result = $client->documents()->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertIsInt($result->getTotal());
        $this->assertIsInt($result->getCurrentPage());

        // If there are documents, verify the first one
        if (count($result) > 0) {
            $document = $result->getItems()[0];
            $this->assertInstanceOf(Document::class, $document);
            $this->assertIsInt($document->getId());
            $this->assertNotEmpty($document->getTitle());
        }
    }

    public function testListWithPagination(): void
    {
        $client = Breezedoc::client($this->apiToken);

        $result = $client->documents()->list(['page' => 1]);

        $this->assertSame(1, $result->getCurrentPage());
    }

    public function testFind(): void
    {
        $client = Breezedoc::client($this->apiToken);

        // First get a list to find an existing document
        $list = $client->documents()->list();
        if (count($list) === 0) {
            $this->markTestSkipped('No documents available for testing');
        }

        $firstDoc = $list->getItems()[0];
        $document = $client->documents()->find($firstDoc->getId());

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame($firstDoc->getId(), $document->getId());
    }
}
