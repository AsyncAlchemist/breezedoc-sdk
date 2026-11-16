<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\V1\Api;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\BreezedocException;
use Breezedoc\Exceptions\ConflictException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Tests\Unit\V1\TeamApiTestCase;
use Breezedoc\V1\Api\Documents;
use Breezedoc\V1\Models\DocumentEvent;
use Breezedoc\V1\Models\DocumentStatus;
use Breezedoc\V1\Models\TeamDocument;
use Breezedoc\V1\Pagination\CursorPage;
use InvalidArgumentException;

class DocumentsTest extends TeamApiTestCase
{
    private const PDF = "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF\n";

    private Documents $documents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documents = new Documents($this->httpClient, $this->requestBuilder);
    }

    public function testListReturnsCursorPage(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [
                ['id' => 'doc_1', 'title' => 'NDA', 'status' => 'sent', 'review_url' => null],
                ['id' => 'doc_2', 'title' => 'MSA', 'status' => 'draft', 'review_url' => 'https://breezedoc.com/r/x'],
            ],
            'next_cursor' => 'cur_2',
        ]));

        $page = $this->documents->list(['limit' => 2, 'status' => DocumentStatus::SENT]);

        $this->assertInstanceOf(CursorPage::class, $page);
        $this->assertCount(2, $page);
        $this->assertInstanceOf(TeamDocument::class, $page->getItems()[0]);
        $this->assertSame('doc_1', $page->getItems()[0]->getId());
        $this->assertSame('cur_2', $page->getNextCursor());
        $this->assertTrue($page->hasMore());

        $request = $this->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://breezedoc.com/api/v1/documents?limit=2&status=sent', (string) $request->getUri());
        $this->assertSame('Bearer bd_live_test', $request->getHeaderLine('Authorization'));
    }

    public function testListConvertsCreatedAfterToUtc(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['data' => [], 'next_cursor' => null]));

        $this->documents->list([
            'created_after' => new \DateTimeImmutable('2026-11-01T10:00:00+02:00'),
        ]);

        $this->assertStringContainsString(
            'created_after=2026-11-01T08%3A00%3A00Z',
            (string) $this->lastRequest()->getUri()
        );
    }

    public function testListDropsEmptyCursor(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['data' => [], 'next_cursor' => null]));

        $page = $this->documents->list(['cursor' => '']);

        $this->assertSame('https://breezedoc.com/api/v1/documents', (string) $this->lastRequest()->getUri());
        $this->assertFalse($page->hasMore());
        $this->assertNull($page->getNextCursor());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public function invalidLimitProvider(): array
    {
        return ['zero' => [0], 'too big' => [101], 'string' => ['10']];
    }

    /**
     * @dataProvider invalidLimitProvider
     * @param mixed $limit
     */
    public function testListRejectsInvalidLimit($limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->documents->list(['limit' => $limit]);
    }

    public function testAllWalksEveryPage(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [['id' => 'doc_1'], ['id' => 'doc_2']],
            'next_cursor' => 'c2',
        ]));
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [['id' => 'doc_3']],
            'next_cursor' => null,
        ]));

        $ids = [];
        foreach ($this->documents->all(['limit' => 2, 'status' => 'completed']) as $document) {
            $ids[] = $document->getId();
        }

        $this->assertSame(['doc_1', 'doc_2', 'doc_3'], $ids);
        $requests = $this->httpClient->getRequests();
        $this->assertCount(2, $requests);
        $this->assertSame(
            'https://breezedoc.com/api/v1/documents?limit=2&status=completed&cursor=c2',
            (string) $requests[1]->getUri()
        );
    }

    public function testAllStopsWhenTheCursorRepeats(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['data' => [['id' => 'doc_1']], 'next_cursor' => 'same']));
        $this->httpClient->addResponse($this->createJsonResponse(['data' => [['id' => 'doc_2']], 'next_cursor' => 'same']));

        $ids = [];
        foreach ($this->documents->all() as $document) {
            $ids[] = $document->getId();
        }

        $this->assertSame(['doc_1', 'doc_2'], $ids);
        $this->assertCount(2, $this->httpClient->getRequests());
    }

    public function testFindEncodesTheId(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_456', 'status' => 'completed']));

        $document = $this->documents->find('doc_456');

        $this->assertSame('doc_456', $document->getId());
        $this->assertTrue($document->isCompleted());
        $this->assertSame('https://breezedoc.com/api/v1/documents/doc_456', (string) $this->lastRequest()->getUri());
    }

    public function testFindCannotLeaveThePathSegment(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'x']));

        $this->documents->find('../webhooks');

        $this->assertSame(
            'https://breezedoc.com/api/v1/documents/..%2Fwebhooks',
            (string) $this->lastRequest()->getUri()
        );
    }

    public function testFindRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->documents->find('');
    }

    public function testFindUnwrapsADataObject(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['data' => ['id' => 'doc_9', 'status' => 'draft']]));

        $this->assertSame('doc_9', $this->documents->find('doc_9')->getId());
    }

    public function testFindNotFoundInAnotherTeam(): void
    {
        $this->httpClient->addResponse($this->createEnvelopeError('not_found', 'Document not found.', 404));

        try {
            $this->documents->find('doc_other');
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('Document not found.', $e->getMessage());
            $this->assertSame('not_found', $e->getErrorCode());
        }
    }

    public function testCreateFromTemplateSendsJsonAndIdempotencyKey(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(
            ['id' => 'doc_456', 'status' => 'draft', 'review_url' => 'https://breezedoc.com/review/doc_456'],
            201
        ));

        $recipients = [['party' => 1, 'name' => 'Jordan', 'email' => 'jordan@example.com']];
        $document = $this->documents->createFromTemplate('tpl_123', $recipients, 'NDA', 'nda-jordan-001');

        $this->assertSame('doc_456', $document->getId());
        $this->assertTrue($document->isDraft());
        $this->assertSame('https://breezedoc.com/review/doc_456', $document->getReviewUrl());

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://breezedoc.com/api/v1/documents', (string) $request->getUri());
        $this->assertSame('nda-jordan-001', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['template_id' => 'tpl_123', 'recipients' => $recipients, 'title' => 'NDA'],
            $this->jsonBody($request)
        );
    }

    public function testCreateWithoutIdempotencyKeySendsNoHeader(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_1'], 201));

        $this->documents->createFromTemplate('tpl_1', [['party' => 1, 'name' => 'A', 'email' => 'a@example.com']]);

        $request = $this->lastRequest();
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
        $this->assertArrayNotHasKey('title', $this->jsonBody($request));
    }

    public function testCreateFromHtml(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_h'], 201));

        $this->documents->createFromHtml('<h1>Agreement</h1><p>Text</p>', [
            ['party' => 1, 'name' => 'A', 'email' => 'a@example.com'],
            ['party' => 2, 'name' => 'B', 'email' => 'b@example.com'],
        ], 'Agreement');

        $body = $this->jsonBody($this->lastRequest());
        $this->assertSame('<h1>Agreement</h1><p>Text</p>', $body['html']);
        $this->assertArrayNotHasKey('template_id', $body);
        $this->assertCount(2, $body['recipients']);
    }

    public function testCreateRequiresExactlyOneSource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->documents->create(['template_id' => 'tpl_1', 'html' => '<p>x</p>', 'recipients' => []]);
    }

    public function testCreateRequiresASource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->documents->create(['recipients' => []]);
    }

    public function testIdempotencyConflict(): void
    {
        $this->httpClient->addResponse($this->createEnvelopeError('idempotency_conflict', 'Key reused.', 409));

        $this->expectException(ConflictException::class);
        $this->documents->createFromTemplate('tpl_1', [], null, 'same-key');
    }

    public function testRejectsInvalidIdempotencyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->documents->createFromTemplate('tpl_1', [], null, str_repeat('k', 256));
    }

    public function testRejectsIdempotencyKeyWithNewline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->documents->remind('doc_1', "key\r\nX-Injected: 1");
    }

    public function testCreateFromPdfSendsMultipart(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_pdf', 'status' => 'draft'], 201));

        $document = $this->documents->createFromPdf(
            self::PDF,
            [['party' => 1, 'name' => 'Jordan', 'email' => 'jordan@example.com']],
            [[
                'party' => 1,
                'page' => 1,
                'type' => 'signature',
                'properties' => ['x' => 0.1, 'y' => 0.8, 'w' => 0.2, 'h' => 0.05, 'required' => true],
            ]],
            'Contract',
            'pdf-upload-1',
            'contract "v2".pdf'
        );

        $this->assertSame('doc_pdf', $document->getId());

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://breezedoc.com/api/v1/documents', (string) $request->getUri());
        $this->assertSame('pdf-upload-1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame(1, preg_match(
            '/^multipart\/form-data; boundary=(----BreezedocSdk[0-9a-f]{32})$/',
            $request->getHeaderLine('Content-Type'),
            $match
        ));
        $boundary = $match[1];

        $body = (string) $request->getBody();
        $this->assertStringStartsWith('--' . $boundary . "\r\n", $body);
        $this->assertStringEndsWith('--' . $boundary . "--\r\n", $body);
        $this->assertStringContainsString("name=\"title\"\r\n\r\nContract\r\n", $body);
        $this->assertStringContainsString("name=\"recipients[0][party]\"\r\n\r\n1\r\n", $body);
        $this->assertStringContainsString("name=\"recipients[0][email]\"\r\n\r\njordan@example.com\r\n", $body);
        $this->assertStringContainsString("name=\"fields[0][type]\"\r\n\r\nsignature\r\n", $body);
        $this->assertStringContainsString("name=\"fields[0][properties][x]\"\r\n\r\n0.1\r\n", $body);
        $this->assertStringContainsString("name=\"fields[0][properties][required]\"\r\n\r\n1\r\n", $body);
        $this->assertStringContainsString(
            "name=\"pdf\"; filename=\"contract %22v2%22.pdf\"\r\nContent-Type: application/pdf\r\n\r\n" . self::PDF,
            $body
        );
    }

    public function testCreateFromPdfRejectsNonPdf(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->documents->createFromPdf('<html>no</html>', [], []);
    }

    public function testSendWithOptions(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_1', 'status' => 'sent']));

        $document = $this->documents->send('doc_1', ['expires_in_days' => 14, 'signing_order' => false], 'send-1');

        $this->assertTrue($document->isSent());
        $request = $this->lastRequest();
        $this->assertSame('https://breezedoc.com/api/v1/documents/doc_1/send', (string) $request->getUri());
        $this->assertSame('send-1', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame(['expires_in_days' => 14, 'signing_order' => false], $this->jsonBody($request));
    }

    public function testSendWithoutOptionsSendsAnEmptyObject(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_1', 'status' => 'sent']));

        $this->documents->send('doc_1');

        $this->assertSame('{}', (string) $this->lastRequest()->getBody());
    }

    public function testRemindAndCancel(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_1', 'status' => 'sent']));
        $this->httpClient->addResponse($this->createJsonResponse(['id' => 'doc_1', 'status' => 'cancelled']));

        $this->documents->remind('doc_1', 'remind-1');
        $cancelled = $this->documents->cancel('doc_1');

        $requests = $this->httpClient->getRequests();
        $this->assertSame('https://breezedoc.com/api/v1/documents/doc_1/remind', (string) $requests[0]->getUri());
        $this->assertSame('remind-1', $requests[0]->getHeaderLine('Idempotency-Key'));
        $this->assertSame('', (string) $requests[0]->getBody());
        $this->assertSame('https://breezedoc.com/api/v1/documents/doc_1/cancel', (string) $requests[1]->getUri());
        $this->assertTrue($cancelled->isCancelled());
    }

    public function testDownloadPdfFetchesTheSignedUrlWithoutTheKey(): void
    {
        $signedUrl = 'https://files.example.test/doc_1.pdf?signature=abc&expires=900';
        $this->httpClient->addResponse($this->createJsonResponse(['url' => $signedUrl, 'expires_at' => '2026-11-16T12:15:00Z']));
        $this->httpClient->addResponse($this->psr17Factory->createResponse(200)
            ->withHeader('Content-Type', 'application/pdf')
            ->withBody($this->psr17Factory->createStream(self::PDF)));

        $pdf = $this->documents->downloadPdf('doc_1');

        $this->assertSame(self::PDF, $pdf);
        $requests = $this->httpClient->getRequests();
        $this->assertSame('https://breezedoc.com/api/v1/documents/doc_1/download', (string) $requests[0]->getUri());
        $this->assertSame('Bearer bd_live_test', $requests[0]->getHeaderLine('Authorization'));
        $this->assertSame($signedUrl, (string) $requests[1]->getUri());
        $this->assertFalse($requests[1]->hasHeader('Authorization'));
    }

    public function testDownloadPdfErrorDoesNotLeakTheSignedUrl(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['url' => 'https://files.example.test/x?signature=SECRET']));
        $this->httpClient->addResponse($this->psr17Factory->createResponse(403));

        try {
            $this->documents->downloadPdf('doc_1');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
            $this->assertStringNotContainsString('files.example.test', $e->getMessage());
        }
    }

    public function testDownloadPdfRejectsANonPdfBody(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['url' => 'https://files.example.test/x']));
        $this->httpClient->addResponse($this->psr17Factory->createResponse(200)
            ->withBody($this->psr17Factory->createStream('<html>login</html>')));

        $this->expectException(BreezedocException::class);
        $this->expectExceptionMessage('is not a PDF');
        $this->documents->downloadPdf('doc_1');
    }

    public function testDownloadPdfWithoutUrl(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse(['status' => 'completed']));

        $this->expectException(BreezedocException::class);
        $this->expectExceptionMessage('has no https URL');
        $this->documents->downloadPdf('doc_1');
    }

    public function testDownloadPdfOfAnIncompleteDocument(): void
    {
        $this->httpClient->addResponse($this->createEnvelopeError('document_not_completed', 'Not completed.', 409));

        $this->expectException(ConflictException::class);
        $this->documents->downloadPdf('doc_1');
    }

    public function testDownloadPdfToSavesTheFile(): void
    {
        $directory = sys_get_temp_dir() . '/breezedoc-v1-test-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $this->httpClient->addResponse($this->createJsonResponse(['url' => 'https://files.example.test/x']));
        $this->httpClient->addResponse($this->psr17Factory->createResponse(200)
            ->withBody($this->psr17Factory->createStream(self::PDF)));

        try {
            $path = $this->documents->downloadPdfTo('doc_1/../x', $directory);

            $this->assertSame($directory . '/doc_1____x.pdf', $path);
            $this->assertSame(self::PDF, file_get_contents($path));
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function testEventsAndAllEvents(): void
    {
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [['id' => 'evt_1', 'type' => 'document.sent', 'created_at' => '2026-11-16T09:00:00Z']],
            'next_cursor' => 'n2',
        ]));
        $this->httpClient->addResponse($this->createJsonResponse([
            'data' => [['id' => 'evt_2', 'type' => 'recipient.signed']],
            'next_cursor' => null,
        ]));

        $types = [];
        foreach ($this->documents->allEvents('doc_1', ['limit' => 1]) as $event) {
            $this->assertInstanceOf(DocumentEvent::class, $event);
            $types[] = $event->getType();
        }

        $this->assertSame(['document.sent', 'recipient.signed'], $types);
        $requests = $this->httpClient->getRequests();
        $this->assertSame('https://breezedoc.com/api/v1/documents/doc_1/events?limit=1', (string) $requests[0]->getUri());
        $this->assertSame(
            'https://breezedoc.com/api/v1/documents/doc_1/events?limit=1&cursor=n2',
            (string) $requests[1]->getUri()
        );
    }

    public function testRequiresATeamRequestBuilder(): void
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $legacy = new \Breezedoc\Http\RequestBuilder(new \Breezedoc\Config\Configuration('t'), $factory, $factory);

        $this->expectException(InvalidArgumentException::class);
        new Documents($this->httpClient, $legacy);
    }
}
