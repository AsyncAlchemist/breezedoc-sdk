<?php

declare(strict_types=1);

namespace Breezedoc\V1\Api;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\BreezedocException;
use Breezedoc\V1\Models\DocumentEvent;
use Breezedoc\V1\Models\DownloadLink;
use Breezedoc\V1\Models\TeamDocument;
use Breezedoc\V1\Pagination\CursorPage;
use InvalidArgumentException;

/**
 * Team API v1: team documents.
 *
 * Reads need a key with read scope; create, send, remind and cancel need write scope.
 * Documents of another team answer 404. Creating a draft never sends email; send it with
 * {@see send()}.
 */
class Documents extends AbstractTeamApi
{
    /**
     * The documented upload limit for a PDF: 25 MB.
     */
    public const MAX_PDF_BYTES = 25 * 1024 * 1024;

    /**
     * `GET /v1/documents`: one page of team documents.
     *
     * @param array<string, mixed> $params Query parameters:
     *   - limit: 1 to 100 (default 25)
     *   - cursor: the next_cursor of the previous page
     *   - status: draft, sent, completed, cancelled or expired ({@see \Breezedoc\V1\Models\DocumentStatus})
     *   - created_after: ISO 8601 string or DateTimeInterface
     * @return CursorPage<TeamDocument>
     */
    public function list(array $params = []): CursorPage
    {
        $response = $this->call('GET', '/documents', null, self::listQuery($params));

        return self::cursorPage($response, [TeamDocument::class, 'fromArray']);
    }

    /**
     * Walk all team documents that match the filters, page by page.
     *
     * @param array<string, mixed> $params the same parameters as {@see list()}
     * @return \Generator<int, TeamDocument, mixed, void>
     */
    public function all(array $params = []): \Generator
    {
        return self::walk(function (array $query): CursorPage {
            return $this->list($query);
        }, $params);
    }

    /**
     * `GET /v1/documents/{id}`: document and recipient status.
     */
    public function find(string $id): TeamDocument
    {
        $response = $this->call('GET', '/documents/' . self::encodeId($id, 'document ID'));

        return TeamDocument::fromArray(self::unwrap($response));
    }

    /**
     * `POST /v1/documents` with a JSON body: create a draft from a template or from html.
     *
     * Pass exactly one of `template_id` or `html`, plus `recipients` (one per party:
     * `party`, `name`, `email`) and an optional `title`.
     *
     * @param array<string, mixed> $data
     * @param string|null $idempotencyKey Replays the first response for 24 hours
     */
    public function create(array $data, ?string $idempotencyKey = null): TeamDocument
    {
        $hasTemplate = isset($data['template_id']);
        $hasHtml = isset($data['html']);
        if ($hasTemplate === $hasHtml) {
            throw new InvalidArgumentException(
                'Pass exactly one of template_id or html. Use createFromPdf() to upload a PDF.'
            );
        }

        $response = $this->call('POST', '/documents', $data, [], $idempotencyKey);

        return TeamDocument::fromArray(self::unwrap($response));
    }

    /**
     * Create a draft from a team template.
     *
     * Read the template's numbered parties first ({@see Templates::find()}) and supply
     * exactly one recipient per party.
     *
     * @param array<array{party: int, name: string, email: string}> $recipients
     */
    public function createFromTemplate(
        string $templateId,
        array $recipients,
        ?string $title = null,
        ?string $idempotencyKey = null
    ): TeamDocument {
        $data = ['template_id' => $templateId, 'recipients' => $recipients];
        if ($title !== null) {
            $data['title'] = $title;
        }

        return $this->create($data, $idempotencyKey);
    }

    /**
     * Create a draft from an agreement's wording in html.
     *
     * BreezeDoc accepts only h1, h2, h3, p, ul, ol, li, strong, em, u and br, with no
     * attributes, up to 200,000 characters. It adds a signature page with signature, name
     * and date fields for one or two recipients (parties 1 and 2).
     *
     * @param array<array{party: int, name: string, email: string}> $recipients
     */
    public function createFromHtml(
        string $html,
        array $recipients,
        ?string $title = null,
        ?string $idempotencyKey = null
    ): TeamDocument {
        $data = ['html' => $html, 'recipients' => $recipients];
        if ($title !== null) {
            $data['title'] = $title;
        }

        return $this->create($data, $idempotencyKey);
    }

    /**
     * `POST /v1/documents` as multipart/form-data: create a draft from an uploaded PDF.
     *
     * BreezeDoc accepts a real PDF of at most 25 MB and 100 pages. Each field has `party`,
     * `page` (from 1), `type` (signature, initials, text, email, date, checkbox, dropdown)
     * and `properties` with `x`, `y`, `w`, `h` as page fractions (0 to 1) and an optional
     * `options` array for dropdowns.
     *
     * The published spec does not say how the `recipients` and `fields` arrays are encoded
     * in the multipart body. This SDK sends them in the bracket form that PHP and Laravel
     * parse into arrays, for example `recipients[0][email]` and `fields[0][properties][x]`.
     *
     * @param string $pdf The PDF bytes
     * @param array<array{party: int, name: string, email: string}> $recipients
     * @param array<array<string, mixed>> $fields
     */
    public function createFromPdf(
        string $pdf,
        array $recipients,
        array $fields,
        ?string $title = null,
        ?string $idempotencyKey = null,
        string $filename = 'document.pdf'
    ): TeamDocument {
        if (!self::looksLikePdf($pdf)) {
            throw new InvalidArgumentException('The upload is not a PDF.');
        }

        if (strlen($pdf) > self::MAX_PDF_BYTES) {
            throw new InvalidArgumentException('The PDF is larger than 25 MB.');
        }

        $parts = [];
        if ($title !== null) {
            $parts[] = ['title', $title];
        }
        self::flatten('recipients', $recipients, $parts);
        self::flatten('fields', $fields, $parts);

        $boundary = self::boundary();
        $body = '';
        foreach ($parts as [$name, $value]) {
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . self::quote($name) . '"' . "\r\n\r\n"
                . $value . "\r\n";
        }
        $body .= '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="pdf"; filename="' . self::quote($filename) . '"' . "\r\n"
            . "Content-Type: application/pdf\r\n\r\n"
            . $pdf . "\r\n"
            . '--' . $boundary . "--\r\n";

        $request = $this->requestBuilder->build('POST', '/documents')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
            ->withBody($this->requestBuilder->createStream($body));
        $request = $this->withIdempotencyKey($request, $idempotencyKey);

        $response = $this->handleResponse($this->httpClient->sendRequest($request));

        return TeamDocument::fromArray(self::unwrap($response));
    }

    /**
     * `POST /v1/documents/{id}/send`: send a draft.
     *
     * @param array<string, mixed> $options Optional:
     *   - signing_order: bool (default true)
     *   - message: string (needs the custom-email plan feature)
     *   - expires_in_days: 1 to 365 (default 30)
     */
    public function send(string $id, array $options = [], ?string $idempotencyKey = null): TeamDocument
    {
        $path = '/documents/' . self::encodeId($id, 'document ID') . '/send';
        $response = $this->call('POST', $path, $options, [], $idempotencyKey);

        return TeamDocument::fromArray(self::unwrap($response));
    }

    /**
     * `POST /v1/documents/{id}/remind`: remind the invited recipients who have not signed.
     *
     * BreezeDoc allows one reminder per 24 hours.
     */
    public function remind(string $id, ?string $idempotencyKey = null): TeamDocument
    {
        $path = '/documents/' . self::encodeId($id, 'document ID') . '/remind';
        $response = $this->call('POST', $path, null, [], $idempotencyKey);

        return TeamDocument::fromArray(self::unwrap($response));
    }

    /**
     * `POST /v1/documents/{id}/cancel`: cancel a sent document so that nobody can sign it.
     *
     * Cancelling does not delete the document. The Team API v1 has no document delete.
     */
    public function cancel(string $id, ?string $idempotencyKey = null): TeamDocument
    {
        $path = '/documents/' . self::encodeId($id, 'document ID') . '/cancel';
        $response = $this->call('POST', $path, null, [], $idempotencyKey);

        return TeamDocument::fromArray(self::unwrap($response));
    }

    /**
     * `GET /v1/documents/{id}/download`: a 15-minute signed URL for the completed PDF,
     * which includes the signing audit certificate.
     *
     * Treat the URL as a credential: do not log it or store it.
     */
    public function getDownloadLink(string $id): DownloadLink
    {
        $path = '/documents/' . self::encodeId($id, 'document ID') . '/download';

        return DownloadLink::fromArray(self::unwrap($this->call('GET', $path)));
    }

    /**
     * Download the completed PDF (with the audit certificate) through the Team API v1.
     *
     * This gets a fresh signed URL ({@see getDownloadLink()}) and fetches it without the
     * API key. Exception messages never contain the signed URL.
     *
     * @return string Raw PDF bytes
     * @throws \Breezedoc\Exceptions\NotFoundException If the document is not in the key's team
     * @throws ApiException If the API or the signed URL answers an error
     * @throws BreezedocException If the response has no URL or the file is not a PDF
     */
    public function downloadPdf(string $id): string
    {
        $url = $this->getDownloadLink($id)->getUrl();
        if ($url === null) {
            throw new BreezedocException('The download response for document ' . $id . ' has no https URL.');
        }

        $response = $this->httpClient->sendRequest($this->requestBuilder->buildExternalRequest('GET', $url));
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new ApiException(
                'Fetching the signed PDF URL of document ' . $id . ' failed (HTTP ' . $status . ').',
                $status
            );
        }

        $pdf = (string) $response->getBody();
        if (!self::looksLikePdf($pdf)) {
            throw new BreezedocException('The download of document ' . $id . ' is not a PDF.');
        }

        return $pdf;
    }

    /**
     * Download the completed PDF and save it to a directory.
     *
     * @param string|null $filename File name (default: "{id}.pdf", with unsafe characters replaced)
     * @return string The saved file path
     */
    public function downloadPdfTo(string $id, string $directory, ?string $filename = null): string
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \RuntimeException('Directory is not writable: ' . $directory);
        }

        $pdf = $this->downloadPdf($id);

        $filename = $filename ?? (preg_replace('/[^A-Za-z0-9_-]/', '_', $id) . '.pdf');
        $filePath = rtrim($directory, '/') . '/' . $filename;

        if (file_put_contents($filePath, $pdf) === false) {
            throw new \RuntimeException('Failed to write PDF to: ' . $filePath);
        }

        return $filePath;
    }

    /**
     * `GET /v1/documents/{id}/events`: one page of the document's audit events.
     *
     * @param array<string, mixed> $params limit (1 to 100) and cursor
     * @return CursorPage<DocumentEvent>
     */
    public function events(string $id, array $params = []): CursorPage
    {
        $path = '/documents/' . self::encodeId($id, 'document ID') . '/events';
        $response = $this->call('GET', $path, null, self::listQuery($params));

        return self::cursorPage($response, [DocumentEvent::class, 'fromArray']);
    }

    /**
     * Walk all audit events of a document, page by page.
     *
     * @param array<string, mixed> $params limit (page size) and an optional start cursor
     * @return \Generator<int, DocumentEvent, mixed, void>
     */
    public function allEvents(string $id, array $params = []): \Generator
    {
        return self::walk(function (array $query) use ($id): CursorPage {
            return $this->events($id, $query);
        }, $params);
    }

    private static function looksLikePdf(string $bytes): bool
    {
        return strpos(substr($bytes, 0, 1024), '%PDF-') !== false;
    }

    /**
     * Flatten a nested array into multipart fields with bracket names.
     *
     * @param mixed $value
     * @param array<array{0: string, 1: string}> $parts
     */
    private static function flatten(string $name, $value, array &$parts): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                self::flatten($name . '[' . $key . ']', $item, $parts);
            }
            return;
        }

        if ($value === null) {
            return;
        }

        if (is_bool($value)) {
            $parts[] = [$name, $value ? '1' : '0'];
            return;
        }

        if (is_scalar($value)) {
            $parts[] = [$name, (string) $value];
            return;
        }

        throw new InvalidArgumentException('Unsupported multipart value at ' . $name . '.');
    }

    private static function boundary(): string
    {
        return '----BreezedocSdk' . bin2hex(random_bytes(16));
    }

    /**
     * Make a value safe inside a quoted Content-Disposition parameter.
     */
    private static function quote(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '', ''], $value);
    }
}
