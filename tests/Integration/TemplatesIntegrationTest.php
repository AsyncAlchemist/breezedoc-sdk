<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use Breezedoc\Breezedoc;
use Breezedoc\Models\Template;
use Breezedoc\Pagination\PaginatedResult;

class TemplatesIntegrationTest extends IntegrationTestCase
{
    public function testList(): void
    {
        $client = Breezedoc::client($this->apiToken);

        $result = $client->templates()->list();

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertIsInt($result->getTotal());

        // If there are templates, verify the first one
        if (count($result) > 0) {
            $template = $result->getItems()[0];
            $this->assertInstanceOf(Template::class, $template);
            $this->assertIsInt($template->getId());
            $this->assertNotEmpty($template->getTitle());
        }
    }

    public function testFind(): void
    {
        $client = Breezedoc::client($this->apiToken);

        // First get a list to find an existing template
        $list = $client->templates()->list();
        if (count($list) === 0) {
            $this->markTestSkipped('No templates available for testing');
        }

        $firstTemplate = $list->getItems()[0];
        $template = $client->templates()->find($firstTemplate->getId());

        $this->assertInstanceOf(Template::class, $template);
        $this->assertSame($firstTemplate->getId(), $template->getId());
    }
}
