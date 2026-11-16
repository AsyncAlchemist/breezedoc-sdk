<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\V1\Api;

use Breezedoc\Tests\Unit\V1\TeamApiTestCase;
use Breezedoc\V1\Api\Templates;
use Breezedoc\V1\Models\TeamTemplate;

class TemplatesTest extends TeamApiTestCase
{
    private Templates $templates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templates = new Templates($this->httpClient, $this->requestBuilder);
    }

    public function testList(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [['id' => 'tpl_123', 'title' => 'NDA']],
            'next_cursor' => null,
        ]));

        $page = $this->templates->list(['limit' => 50]);

        $this->assertCount(1, $page);
        $this->assertInstanceOf(TeamTemplate::class, $page->getItems()[0]);
        $this->assertSame('tpl_123', $page->getItems()[0]->getId());
        $this->assertSame('NDA', $page->getItems()[0]->getTitle());
        $this->assertSame('https://breezedoc.com/api/v1/templates?limit=50', (string) $this->lastRequest()->getUri());
    }

    public function testAll(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['data' => [['id' => 'tpl_1']], 'next_cursor' => 'x']));
        $this->httpClient->addResponse($this->createJsonResponse(['data' => [['id' => 'tpl_2']]]));

        $ids = [];
        foreach ($this->templates->all() as $template) {
            $ids[] = $template->getId();
        }

        $this->assertSame(['tpl_1', 'tpl_2'], $ids);
    }

    public function testFind(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'id' => 'tpl_123',
            'name' => 'NDA',
            'parties' => [['party' => 1, 'name' => 'Signer']],
            'fields' => [['type' => 'signature', 'party' => 1]],
        ]));

        $template = $this->templates->find('tpl_123');

        $this->assertSame('NDA', $template->getTitle());
        $this->assertSame([['party' => 1, 'name' => 'Signer']], $template->getParties());
        $this->assertSame([['type' => 'signature', 'party' => 1]], $template->getFields());
        $this->assertSame('https://breezedoc.com/api/v1/templates/tpl_123', (string) $this->lastRequest()->getUri());
    }
}
