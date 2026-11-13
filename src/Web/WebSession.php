<?php

declare(strict_types=1);

namespace Breezedoc\Web;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\BreezedocException;
use Breezedoc\Exceptions\DeleteNotConfirmedException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\RateLimitException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * An authenticated breezedoc.com website session used for what the REST API does
 * not expose: downloading signed PDFs and deleting documents.
 *
 * Performs the Laravel form login over plain HTTP (no browser) using any PSR-18
 * client, caches the resulting session via a {@see SessionStore} so it is reused
 * across runs, and transparently re-logs-in when the cached session has expired.
 *
 * Cookies and redirects are handled here rather than by the HTTP client: cookies
 * are captured from `Set-Cookie` and resent on the `Cookie` header, and the flow
 * is written to work with a PSR-18 client whose `sendRequest()` does not follow
 * redirects (the norm — e.g. Guzzle's PSR-18 adapter). Detection also tolerates a
 * client that does follow, by inspecting the landed-on page.
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
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private SessionStore $store;
    private string $email;
    private string $password;
    private int $ttl;
    private string $baseUrl;

    /**
     * @var array<string, string>|null In-memory cookie jar (name => value); null until a session is established.
     */
    private ?array $cookies = null;
    private int $createdAt = 0;

    public function __construct(
        ClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        SessionStore $store,
        string $email,
        string $password,
        int $ttl,
        string $baseUrl
    ) {
        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
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
     * @throws ApiException On a transport failure or any other unexpected response
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
     * Delete document $id on the BreezeDoc website (the REST API has no delete).
     *
     * This is the low-level website step. Callers should use
     * {@see \Breezedoc\Api\Documents::deleteDocument()}, which checks the result
     * against the REST API: a website 404 alone does not prove a delete (a renamed
     * route or a web login on another account also gives one).
     *
     * Submits the same form as the "Delete" button of the website's documents list
     * (POST /documents/{id} with `_method=DELETE` and the page's CSRF token). The
     * result is decided by a follow-up check, not by the answer to the POST: this
     * returns normally only when a GET of the document's activity page answers 404.
     *
     * A dead session is re-logged-in once per call; an expired CSRF token (HTTP 419)
     * is refreshed once. Exception messages carry the document id, the failed step and
     * the HTTP status only, never a credential, cookie or token.
     *
     * @throws NotFoundException The website answered 404 for the document's activity page
     *                           when it read the CSRF token (before a delete POST, or before
     *                           a retry of it). It does not prove that the document is gone.
     * @throws AuthenticationException The web login fails (as getPdf)
     * @throws DeleteNotConfirmedException The website still shows the document after the delete
     * @throws BreezedocException Any other refusal (403, 419 after one token refresh, 5xx, an
     *                            unexpected page); 403 and 429 as AuthorizationException and
     *                            RateLimitException at every step
     */
    public function deleteDocument(int $id): void
    {
        $this->ensureSession();

        $reloggedIn = false;
        $tokenRefreshed = false;

        $token = $this->fetchDeleteToken($id, $reloggedIn);

        // Bounded: each retry spends either the one re-login or the one token refresh.
        while (true) {
            $response = $this->postDelete($id, $token);

            if ($this->isLoggedOut($response)) {
                $this->reLoginOnce($reloggedIn);
                $token = $this->fetchDeleteToken($id, $reloggedIn);
                continue;
            }

            if ($response->getStatusCode() === 419) {
                if ($tokenRefreshed) {
                    throw $this->refusal(419, $this->postRefusedMessage($id, 419));
                }
                $tokenRefreshed = true;
                $token = $this->fetchDeleteToken($id, $reloggedIn);
                continue;
            }

            break;
        }

        $status = $response->getStatusCode();
        // A redirect (the Laravel norm), a landed-on page (redirect-following client),
        // or a 404 (gone in the meantime) all go to the follow-up check.
        if (!($status >= 200 && $status < 400) && $status !== 404) {
            throw $this->refusal($status, $this->postRefusedMessage($id, $status));
        }

        $check = $this->requestDocumentPage($id, $reloggedIn);
        $checkStatus = $check->getStatusCode();

        if ($checkStatus === 404) {
            $this->persistState();
            return;
        }

        if ($checkStatus >= 200 && $checkStatus < 300) {
            throw new DeleteNotConfirmedException('The website still shows document ' . $id . ' after the delete.');
        }

        throw $this->refusal(
            $checkStatus,
            'The Breezedoc website answered HTTP ' . $checkStatus . ' for the activity page of document '
            . $id . ' after the delete.'
        );
    }

    /**
     * Check whether the currently cached session is still authenticated, without
     * downloading anything. Does not trigger a login.
     */
    public function validate(): bool
    {
        $state = $this->store->load();
        if ($state === null || $state->getEmail() !== $this->email) {
            return false;
        }

        $this->cookies = $state->getCookies();
        $response = $this->get($this->baseUrl . '/documents');

        return $this->landedAuthenticated($response);
    }

    /**
     * Discard the cached session (local and persisted).
     */
    public function logout(): void
    {
        $this->store->clear();
        $this->cookies = null;
        $this->createdAt = 0;
    }

    /**
     * Ensure we have a usable in-memory session, reusing the cached one when it
     * belongs to this account and is within the TTL, otherwise logging in.
     */
    private function ensureSession(): void
    {
        if ($this->cookies !== null) {
            return;
        }

        $state = $this->store->load();
        if (
            $state !== null
            && $state->getEmail() === $this->email
            && !$state->isExpired($this->ttl, time())
        ) {
            $this->cookies = $state->getCookies();
            $this->createdAt = $state->getCreatedAt();
            return;
        }

        $this->login();
    }

    /**
     * Perform the Laravel form login and persist the resulting session.
     *
     * @throws AuthenticationException
     * @throws ApiException On a transport failure
     */
    private function login(): void
    {
        $this->cookies = [];

        $formResponse = $this->get($this->baseUrl . '/login', ['Accept' => 'text/html']);

        $token = $this->extractToken((string) $formResponse->getBody());
        if ($token === null) {
            throw new AuthenticationException(
                'Could not obtain a login token from Breezedoc. The login page may be '
                . 'blocked by bot protection.'
            );
        }

        $loginResponse = $this->postForm(
            $this->baseUrl . '/login',
            [
                'email' => $this->email,
                'password' => $this->password,
                '_token' => $token,
            ],
            [
                'Accept' => 'text/html',
                'Origin' => $this->baseUrl,
                'Referer' => $this->baseUrl . '/login',
            ]
        );

        if (!$this->landedAuthenticated($loginResponse)) {
            throw new AuthenticationException(
                'Breezedoc login failed. Check the web login email and password.'
            );
        }

        $this->createdAt = time();
        $this->persistState();
    }

    private function requestDownload(int $id): ResponseInterface
    {
        return $this->get($this->baseUrl . '/documents/' . $id . '/download');
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
     * Check that the document is there and read the CSRF token for its delete form.
     *
     * @param bool $reloggedIn Whether this call's one re-login is already spent (updated)
     * @throws NotFoundException If the document is not there
     * @throws AuthenticationException If the session cannot be authenticated
     * @throws ApiException On any other answer, or a page without a token
     */
    private function fetchDeleteToken(int $id, bool &$reloggedIn): string
    {
        $response = $this->requestDocumentPage($id, $reloggedIn);
        $status = $response->getStatusCode();

        if ($status === 404) {
            throw new NotFoundException('Document ' . $id . ' was not found.');
        }

        if ($status < 200 || $status >= 300) {
            throw $this->refusal(
                $status,
                'The Breezedoc website answered HTTP ' . $status . ' for the activity page of document '
                . $id . ' before the delete.'
            );
        }

        $token = $this->extractPageToken((string) $response->getBody());
        if ($token === null) {
            throw new ApiException(
                'Could not read a CSRF token from the activity page of document ' . $id
                . ' before the delete (HTTP ' . $status . ').',
                $status
            );
        }

        return $token;
    }

    /**
     * GET the document's activity page. On a dead session, re-login once per
     * deleteDocument() call and retry.
     *
     * @param bool $reloggedIn Whether this call's one re-login is already spent (updated)
     * @throws AuthenticationException If the session is still dead after the re-login
     */
    private function requestDocumentPage(int $id, bool &$reloggedIn): ResponseInterface
    {
        $url = $this->baseUrl . '/documents/' . $id . '/activity';
        $response = $this->get($url, ['Accept' => 'text/html']);

        if (!$this->isLoggedOut($response)) {
            return $response;
        }

        $this->reLoginOnce($reloggedIn);
        $response = $this->get($url, ['Accept' => 'text/html']);

        if ($this->isLoggedOut($response)) {
            throw new AuthenticationException(
                'Breezedoc web session could not be authenticated after re-login.'
            );
        }

        return $response;
    }

    /**
     * @param bool $reloggedIn Whether this call's one re-login is already spent (updated)
     * @throws AuthenticationException If the re-login is already spent, or the login fails
     */
    private function reLoginOnce(bool &$reloggedIn): void
    {
        if ($reloggedIn) {
            throw new AuthenticationException(
                'Breezedoc web session could not be authenticated after re-login.'
            );
        }

        $reloggedIn = true;
        $this->login();
    }

    /**
     * Submit the documents list's delete form for one document, as a browser would.
     */
    private function postDelete(int $id, string $token): ResponseInterface
    {
        return $this->postForm(
            $this->baseUrl . '/documents/' . $id,
            [
                '_token' => $token,
                '_method' => 'DELETE',
            ],
            [
                'Accept' => 'text/html',
                'Origin' => $this->baseUrl,
                'Referer' => $this->baseUrl . '/documents',
            ]
        );
    }

    private function postRefusedMessage(int $id, int $status): string
    {
        return 'The Breezedoc website refused the delete of document ' . $id . ' (HTTP ' . $status . ').';
    }

    /**
     * Map a refused delete step to an exception, the same way at every step. The
     * message holds the document id, the step and the HTTP status only.
     */
    private function refusal(int $status, string $message): ApiException
    {
        if ($status === 403) {
            return new AuthorizationException($message);
        }

        if ($status === 429) {
            return new RateLimitException($message);
        }

        return new ApiException($message, $status);
    }

    /**
     * Whether a response shows a dead session on an HTML route: a redirect to the
     * login page, or (with a redirect-following client) the login form itself.
     */
    private function isLoggedOut(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        if ($status >= 300 && $status < 400) {
            return strpos($response->getHeaderLine('Location'), '/login') !== false;
        }

        if ($status >= 200 && $status < 300) {
            return $this->looksLikeLoginForm((string) $response->getBody());
        }

        return false;
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
     * Whether a response indicates we ended up on an authenticated page rather than
     * the login screen: a redirect to a non-/login route, or (with a
     * redirect-following client) a 2xx page that is not the login form. Used for
     * both login-success and session-validity checks.
     */
    private function landedAuthenticated(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        if ($status >= 300 && $status < 400) {
            return strpos($response->getHeaderLine('Location'), '/login') === false;
        }

        if ($status >= 200 && $status < 300) {
            return !$this->looksLikeLoginForm((string) $response->getBody());
        }

        return false;
    }

    private function looksLikeLoginForm(string $body): bool
    {
        return stripos($body, 'name="password"') !== false;
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

    /**
     * The CSRF token of an authenticated page: the `csrf-token` meta tag, else a
     * form's `_token` input.
     */
    private function extractPageToken(string $html): ?string
    {
        $metaPatterns = [
            '/<meta\b[^>]*\bname=["\']csrf-token["\'][^>]*\bcontent=["\']([^"\']+)["\']/i',
            '/<meta\b[^>]*\bcontent=["\']([^"\']+)["\'][^>]*\bname=["\']csrf-token["\']/i',
        ];

        foreach ($metaPatterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return $matches[1];
            }
        }

        return $this->extractToken($html);
    }

    private function persistState(): void
    {
        $this->store->save(new SessionState($this->email, $this->createdAt, $this->cookies ?? []));
    }

    /**
     * Send a GET request carrying the current cookies, and capture any Set-Cookie.
     *
     * @param array<string, string> $headers
     */
    private function get(string $url, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('GET', $url);
        return $this->send($this->prepare($request, $headers));
    }

    /**
     * Send a POST request with a urlencoded form body.
     *
     * @param array<string, string> $fields
     * @param array<string, string> $headers
     */
    private function postForm(string $url, array $fields, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query($fields)));

        return $this->send($this->prepare($request, $headers));
    }

    /**
     * Attach the browser headers and current cookies to a request.
     *
     * @param array<string, string> $headers
     */
    private function prepare(RequestInterface $request, array $headers): RequestInterface
    {
        $allHeaders = array_merge(['User-Agent' => self::USER_AGENT, 'Accept' => '*/*'], $headers);
        foreach ($allHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($this->cookies !== null && $this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $request = $request->withHeader('Cookie', implode('; ', $pairs));
        }

        return $request;
    }

    /**
     * Dispatch the request and fold the response's cookies into the jar.
     *
     * @throws ApiException On a PSR-18 transport failure
     */
    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException(
                'HTTP request to the Breezedoc website failed: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $this->captureCookies($response);

        return $response;
    }

    private function captureCookies(ResponseInterface $response): void
    {
        if ($this->cookies === null) {
            $this->cookies = [];
        }

        foreach ($response->getHeader('Set-Cookie') as $line) {
            $pair = trim(explode(';', $line, 2)[0]);
            if ($pair === '' || strpos($pair, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $pair, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '') {
                continue;
            }

            if ($value === '' || $this->isDeletionCookie($line)) {
                unset($this->cookies[$name]);
                continue;
            }

            $this->cookies[$name] = $value;
        }
    }

    private function isDeletionCookie(string $line): bool
    {
        if (preg_match('/max-age\s*=\s*0(?!\d)/i', $line) === 1) {
            return true;
        }

        return preg_match('/expires\s*=\s*[^;]*1970/i', $line) === 1;
    }
}
