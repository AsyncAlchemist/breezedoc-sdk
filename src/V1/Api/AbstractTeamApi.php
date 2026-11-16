<?php

declare(strict_types=1);

namespace Breezedoc\V1\Api;

use Breezedoc\Api\AbstractApi;
use Breezedoc\Exceptions\ApiException;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\V1\Models\AbstractResource;
use Breezedoc\V1\Pagination\CursorPage;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Base class for Team API v1 resources.
 *
 * Differences from the original API that this class handles:
 * - IDs are strings (`doc_`, `tpl_`, `rcp_`, `evt_`), so they are URL-encoded into the path.
 * - POST requests accept an `Idempotency-Key` header (replayed for 24 hours; a reuse with a
 *   different payload answers 409, {@see \Breezedoc\Exceptions\ConflictException}).
 * - Lists are cursor-paginated: `data` and `next_cursor`, with `cursor` and `limit` (1-100).
 * - Errors use the envelope `{"error": {"code": "...", "message": "..."}}`.
 */
abstract class AbstractTeamApi extends AbstractApi
{
    private const MAX_IDEMPOTENCY_KEY_LENGTH = 255;

    public function __construct(ClientInterface $httpClient, RequestBuilder $requestBuilder)
    {
        if (!$requestBuilder->isTeamApi()) {
            throw new InvalidArgumentException(
                'Team API v1 resources need a RequestBuilder made with RequestBuilder::forTeamApi().'
            );
        }

        parent::__construct($httpClient, $requestBuilder);
    }

    /**
     * Send a Team API v1 request and decode the JSON response.
     *
     * An empty array body is sent as the JSON object `{}`.
     *
     * @param array<string, mixed>|null $body JSON body, or null for no body
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws ApiException
     */
    protected function call(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        ?string $idempotencyKey = null
    ): array {
        $request = $this->requestBuilder->build($method, $path, null, $query);

        if ($body !== null) {
            $encoded = json_encode($body === [] ? new \stdClass() : $body);
            if ($encoded === false) {
                throw new InvalidArgumentException('Failed to encode request body as JSON: ' . json_last_error_msg());
            }
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->requestBuilder->createStream($encoded));
        }

        $request = $this->withIdempotencyKey($request, $idempotencyKey);

        return $this->handleResponse($this->httpClient->sendRequest($request));
    }

    /**
     * Add the Idempotency-Key header when a key is given.
     */
    protected function withIdempotencyKey(RequestInterface $request, ?string $idempotencyKey): RequestInterface
    {
        if ($idempotencyKey === null) {
            return $request;
        }

        if ($idempotencyKey === '' || strlen($idempotencyKey) > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            throw new InvalidArgumentException('An Idempotency-Key must be 1 to 255 characters long.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $idempotencyKey) === 1) {
            throw new InvalidArgumentException('An Idempotency-Key cannot contain control characters.');
        }

        return $request->withHeader('Idempotency-Key', $idempotencyKey);
    }

    /**
     * URL-encode one ID for use as a path segment.
     */
    protected static function encodeId(string $id, string $what = 'ID'): string
    {
        if ($id === '') {
            throw new InvalidArgumentException('The ' . $what . ' cannot be empty.');
        }

        return rawurlencode($id);
    }

    /**
     * Return the resource of a single-resource response.
     *
     * The single-resource responses are not documented to have a wrapper. When the
     * response has no `id` but a `data` object, the `data` object is returned.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    protected static function unwrap(array $response): array
    {
        if (!array_key_exists('id', $response)) {
            $data = $response['data'] ?? null;
            if (is_array($data) && $data !== [] && !AbstractResource::isList($data)) {
                return $data;
            }
        }

        return $response;
    }

    /**
     * Read the items of a list response: the `data` list, or the response itself when it
     * is a list.
     *
     * @param array<mixed> $response
     * @return array<array<string, mixed>>
     */
    protected static function listItems(array $response): array
    {
        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            $data = AbstractResource::isList($response) ? $response : [];
        }

        $items = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Build a cursor page from a list response.
     *
     * @template T
     * @param array<string, mixed> $response
     * @param callable(array<string, mixed>): T $factory
     * @return CursorPage<T>
     */
    protected static function cursorPage(array $response, callable $factory): CursorPage
    {
        $items = array_map($factory, self::listItems($response));
        $next = $response['next_cursor'] ?? null;

        return new CursorPage($items, is_string($next) ? $next : null);
    }

    /**
     * Validate and normalize list parameters (`limit`, `cursor`, and a DateTimeInterface
     * `created_after`, which is sent as ISO 8601 UTC).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected static function listQuery(array $params): array
    {
        if (array_key_exists('limit', $params)) {
            $limit = $params['limit'];
            if (!is_int($limit) || $limit < 1 || $limit > 100) {
                throw new InvalidArgumentException('limit must be an integer from 1 to 100.');
            }
        }

        if (array_key_exists('cursor', $params) && ($params['cursor'] === null || $params['cursor'] === '')) {
            unset($params['cursor']);
        }

        if (isset($params['created_after']) && $params['created_after'] instanceof DateTimeInterface) {
            $utc = \DateTimeImmutable::createFromFormat(
                'U',
                (string) $params['created_after']->getTimestamp(),
                new DateTimeZone('UTC')
            );
            if ($utc !== false) {
                $params['created_after'] = $utc->format('Y-m-d\TH:i:s\Z');
            }
        }

        return $params;
    }

    /**
     * Walk every page of a cursor list.
     *
     * @template T
     * @param callable(array<string, mixed>): CursorPage<T> $fetchPage gets the query of each page
     * @param array<string, mixed> $params
     * @return \Generator<int, T, mixed, void>
     */
    protected static function walk(callable $fetchPage, array $params): \Generator
    {
        $seen = [];

        while (true) {
            $page = $fetchPage($params);
            foreach ($page->getItems() as $item) {
                yield $item;
            }

            $next = $page->getNextCursor();
            if ($next === null || isset($seen[$next])) {
                return;
            }

            $seen[$next] = true;
            $params['cursor'] = $next;
        }
    }
}
