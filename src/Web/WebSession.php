<?php

declare(strict_types=1);

namespace Breezedoc\Web;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\RateLimitException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

/**
 * An authenticated breezedoc.com website session used to download signed PDFs
 * that the REST API does not expose.
 *
 * Performs the Laravel form login over plain HTTP (no browser), caches the
 * resulting session via a {@see SessionStore} so it is reused across runs, and
 * transparently re-logs-in when the cached session has expired.
 */
class WebSession
{
    /**
     * A browser User-Agent is mandatory: Cloudflare blocks default HTTP-client
     * user agents with a 403 before the request reaches the application.
     */
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private ClientInterface $http;
    private SessionStore $store;
    private string $email;
    private string $password;
    private int $ttl;
    private string $baseUrl;

    private ?CookieJar $jar = null;
    private int $createdAt = 0;

    public function __construct(
        ClientInterface $http,
        SessionStore $store,
        string $email,
        string $password,
        int $ttl,
        string $baseUrl
    ) {
        $this->http = $http;
        $this->store = $store;
        $this->email = $email;
        $this->password = $password;
        $this->ttl = $ttl;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Download the signed/completed PDF for a document.
     *
     * @return string Raw PDF bytes
     * @throws AuthenticationException If the session cannot be authenticated
     * @throws AuthorizationException If the account cannot access the document
     * @throws NotFoundException If the document does not exist
     * @throws RateLimitException If rate limited
     * @throws ApiException On any other unexpected response
     */
    public function getPdf(int $id): string
    {
        $this->ensureSession();

        $response = $this->requestDownload($id);

        if ($this->isDeadSession($response)) {
            // Cached session expired mid-use: re-login once and retry.
            $this->login();
            $response = $this->requestDownload($id);

            if ($this->isDeadSession($response)) {
                throw new AuthenticationException(
                    'Breezedoc web session could not be authenticated after re-login.'
                );
            }
        }

        return $this->handleDownloadResponse($response, $id);
    }

    /**
     * Check whether the currently cached session is still authenticated,
     * without downloading anything. Does not trigger a login.
     */
    public function validate(): bool
    {
        $state = $this->store->load();
        if ($state === null || $state->getEmail() !== $this->email) {
            return false;
        }

        $jar = new CookieJar(false, $state->getCookies());
        $response = $this->http->request('GET', $this->baseUrl . '/documents', $this->options([
            'cookies' => $jar,
        ]));

        return $response->getStatusCode() === 200;
    }

    /**
     * Discard the cached session (local and persisted).
     */
    public function logout(): void
    {
        $this->store->clear();
        $this->jar = null;
        $this->createdAt = 0;
    }

    /**
     * Ensure we have a usable in-memory session, reusing the cached one when it
     * belongs to this account and is within the TTL, otherwise logging in.
     */
    private function ensureSession(): void
    {
        if ($this->jar !== null) {
            return;
        }

        $state = $this->store->load();
        if (
            $state !== null
            && $state->getEmail() === $this->email
            && !$state->isExpired($this->ttl, time())
        ) {
            $this->jar = new CookieJar(false, $state->getCookies());
            $this->createdAt = $state->getCreatedAt();
            return;
        }

        $this->login();
    }

    /**
     * Perform the Laravel form login and persist the resulting session.
     *
     * @throws AuthenticationException
     */
    private function login(): void
    {
        $this->jar = new CookieJar();

        $formResponse = $this->http->request('GET', $this->baseUrl . '/login', $this->options([
            'headers' => $this->headers(['Accept' => 'text/html']),
        ]));

        $token = $this->extractToken((string) $formResponse->getBody());
        if ($token === null) {
            throw new AuthenticationException(
                'Could not obtain a login token from Breezedoc. The login page may be '
                . 'blocked by bot protection.'
            );
        }

        $loginResponse = $this->http->request('POST', $this->baseUrl . '/login', $this->options([
            'headers' => $this->headers([
                'Accept' => 'text/html',
                'Origin' => $this->baseUrl,
                'Referer' => $this->baseUrl . '/login',
            ]),
            'form_params' => [
                'email' => $this->email,
                'password' => $this->password,
                '_token' => $token,
            ],
        ]));

        if (!$this->isLoginSuccess($loginResponse)) {
            throw new AuthenticationException(
                'Breezedoc login failed. Check the web login email and password.'
            );
        }

        $this->createdAt = time();
        $this->persistState();
    }

    private function requestDownload(int $id): ResponseInterface
    {
        return $this->http->request(
            'GET',
            $this->baseUrl . '/documents/' . $id . '/download',
            $this->options()
        );
    }

    /**
     * Interpret a successful download response and map failures to exceptions.
     *
     * @throws AuthorizationException
     * @throws NotFoundException
     * @throws RateLimitException
     * @throws ApiException
     */
    private function handleDownloadResponse(ResponseInterface $response, int $id): string
    {
        $status = $response->getStatusCode();

        if ($status === 200) {
            // A non-PDF 200 was already treated as a dead session before we got here.
            $this->persistState();
            return (string) $response->getBody();
        }

        if ($status === 403) {
            throw new AuthorizationException(
                'The web login account does not have access to document ' . $id . '.'
            );
        }

        if ($status === 404) {
            throw new NotFoundException('Document ' . $id . ' was not found.');
        }

        if ($status === 429) {
            throw new RateLimitException('Rate limited while downloading document ' . $id . '.');
        }

        throw new ApiException(
            'Unexpected response while downloading document ' . $id . ' (HTTP ' . $status . ').',
            $status
        );
    }

    /**
     * A dead/expired session redirects authenticated routes to /login, or (if the
     * client followed the redirect) returns the login HTML instead of a PDF.
     */
    private function isDeadSession(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        if ($status >= 300 && $status < 400) {
            return strpos($response->getHeaderLine('Location'), '/login') !== false;
        }

        if ($status === 200) {
            return stripos($response->getHeaderLine('Content-Type'), 'application/pdf') === false;
        }

        return false;
    }

    /**
     * Login succeeds when the POST redirects somewhere other than back to /login.
     * A 200 means the form was re-rendered with validation errors.
     */
    private function isLoginSuccess(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        if ($status >= 300 && $status < 400) {
            return strpos($response->getHeaderLine('Location'), '/login') === false;
        }

        return false;
    }

    private function extractToken(string $html): ?string
    {
        if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/value="([^"]+)"[^>]*name="_token"/', $html, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function persistState(): void
    {
        if ($this->jar === null) {
            return;
        }

        /** @var array<int, array<string, mixed>> $cookies */
        $cookies = $this->jar->toArray();
        $this->store->save(new SessionState($this->email, $this->createdAt, $cookies));
    }

    /**
     * Merge Guzzle request options onto the defaults shared by every request.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function options(array $extra = []): array
    {
        return array_merge([
            'cookies' => $this->jar,
            'allow_redirects' => false,
            'http_errors' => false,
            'headers' => $this->headers(),
        ], $extra);
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function headers(array $extra = []): array
    {
        return array_merge([
            'User-Agent' => self::USER_AGENT,
            'Accept' => '*/*',
        ], $extra);
    }
}
