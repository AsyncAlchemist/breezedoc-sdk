<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Web;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Exceptions\RateLimitException;
use Breezedoc\Web\ArraySessionStore;
use Breezedoc\Web\SessionState;
use Breezedoc\Web\SessionStore;
use Breezedoc\Web\WebSession;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 transport failure (implements the PSR-18 exception marker interface).
 */
class FakeTransportException extends \RuntimeException implements ClientExceptionInterface
{
}

class WebSessionTest extends TestCase
{
    private const EMAIL = 'user@example.com';
    private const PASSWORD = 'secret';
    private const BASE = 'https://breezedoc.com';

    private MockClient $http;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new MockClient();
        $this->psr17 = new Psr17Factory();
    }

    /**
     * @param array<int, ResponseInterface|\Exception> $results
     */
    private function makeSession(array $results, ?SessionStore $store = null, int $ttl = 3600): WebSession
    {
        foreach ($results as $result) {
            if ($result instanceof \Exception) {
                $this->http->addException($result);
            } else {
                $this->http->addResponse($result);
            }
        }

        return new WebSession(
            $this->http,
            $this->psr17,
            $this->psr17,
            $store ?? new ArraySessionStore(),
            self::EMAIL,
            self::PASSWORD,
            $ttl,
            self::BASE
        );
    }

    // --- response builders -------------------------------------------------

    /**
     * @param array<int, string> $setCookies
     */
    private function loginForm(string $token = 'tok123', array $setCookies = ['XSRF-TOKEN=xsrf1; path=/']): ResponseInterface
    {
        $html = '<form method="POST" action="/login">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . '<input name="email"><input name="password" type="password"></form>';
        return $this->html(200, $html, 'text/html', $setCookies);
    }

    /**
     * @param array<int, string> $setCookies
     */
    private function loginSuccessRedirect(array $setCookies = ['breezedoc_session=sess1; path=/']): ResponseInterface
    {
        $response = $this->psr17->createResponse(302)->withHeader('Location', self::BASE . '/documents');
        return $this->withCookies($response, $setCookies);
    }

    private function loginSuccessFollowed(): ResponseInterface
    {
        return $this->html(200, '<html><body><a href="/logout">Log out</a></body></html>');
    }

    private function loginFailRedirect(): ResponseInterface
    {
        return $this->psr17->createResponse(302)->withHeader('Location', self::BASE . '/login');
    }

    private function loginFailFollowed(): ResponseInterface
    {
        return $this->html(200, '<form action="/login"><input name="password" type="password"></form>');
    }

    private function pdf(string $body = '%PDF-1.7 fake'): ResponseInterface
    {
        return $this->html(200, $body, 'application/pdf');
    }

    private function redirectToLogin(): ResponseInterface
    {
        return $this->psr17->createResponse(302)->withHeader('Location', self::BASE . '/login');
    }

    /**
     * @param array<int, string> $setCookies
     */
    private function html(int $status, string $body, string $contentType = 'text/html', array $setCookies = []): ResponseInterface
    {
        $response = $this->psr17->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->psr17->createStream($body));
        return $this->withCookies($response, $setCookies);
    }

    /**
     * @param array<int, string> $setCookies
     */
    private function withCookies(ResponseInterface $response, array $setCookies): ResponseInterface
    {
        foreach ($setCookies as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie);
        }
        return $response;
    }

    private function validState(?int $createdAt = null, array $cookies = ['breezedoc_session' => 'abc']): SessionState
    {
        return new SessionState(self::EMAIL, $createdAt ?? time(), $cookies);
    }

    // --- request inspection ------------------------------------------------

    /**
     * @return array<int, string>
     */
    private function requestLines(): array
    {
        return array_map(static function (RequestInterface $r): string {
            return $r->getMethod() . ' ' . $r->getUri()->getPath();
        }, $this->http->getRequests());
    }

    private function requestAt(int $index): RequestInterface
    {
        return $this->http->getRequests()[$index];
    }

    // --- login + download happy paths -------------------------------------

    public function testLogsInThenDownloadsWhenNoCachedSession(): void
    {
        $store = new ArraySessionStore();
        $session = $this->makeSession([$this->loginForm(), $this->loginSuccessRedirect(), $this->pdf('%PDF-hi')], $store);

        $this->assertSame('%PDF-hi', $session->getPdf(311939));
        $this->assertSame(
            ['GET /login', 'POST /login', 'GET /documents/311939/download'],
            $this->requestLines()
        );
        $loaded = $store->load();
        $this->assertNotNull($loaded);
        $this->assertSame(self::EMAIL, $loaded->getEmail());
    }

    public function testLoginSucceedsWhenClientFollowsRedirectToDashboard(): void
    {
        $session = $this->makeSession([$this->loginForm(), $this->loginSuccessFollowed(), $this->pdf('%PDF-followed')]);

        $this->assertSame('%PDF-followed', $session->getPdf(1));
    }

    public function testSendsCredentialsAndTokenOnLogin(): void
    {
        $session = $this->makeSession([$this->loginForm('csrf-xyz'), $this->loginSuccessRedirect(), $this->pdf()]);
        $session->getPdf(1);

        parse_str((string) $this->requestAt(1)->getBody(), $fields);
        $this->assertSame(self::EMAIL, $fields['email']);
        $this->assertSame(self::PASSWORD, $fields['password']);
        $this->assertSame('csrf-xyz', $fields['_token']);
    }

    public function testLoginPostIsFormUrlencoded(): void
    {
        $session = $this->makeSession([$this->loginForm(), $this->loginSuccessRedirect(), $this->pdf()]);
        $session->getPdf(1);

        $this->assertSame(
            'application/x-www-form-urlencoded',
            $this->requestAt(1)->getHeaderLine('Content-Type')
        );
    }

    public function testDownloadHitsCorrectUrl(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->pdf()], $store);
        $session->getPdf(4242);

        $this->assertSame(self::BASE . '/documents/4242/download', (string) $this->requestAt(0)->getUri());
    }

    // --- browser headers / no auth leakage --------------------------------

    public function testBrowserUserAgentSentOnEveryRequest(): void
    {
        $session = $this->makeSession([$this->loginForm(), $this->loginSuccessRedirect(), $this->pdf()]);
        $session->getPdf(1);

        foreach ($this->http->getRequests() as $request) {
            $this->assertStringContainsString('Mozilla/5.0', $request->getHeaderLine('User-Agent'));
            $this->assertStringContainsString('Chrome/', $request->getHeaderLine('User-Agent'));
        }
    }

    public function testNoAuthorizationHeaderLeaksToWebRequests(): void
    {
        $session = $this->makeSession([$this->loginForm(), $this->loginSuccessRedirect(), $this->pdf()]);
        $session->getPdf(1);

        foreach ($this->http->getRequests() as $request) {
            $this->assertFalse($request->hasHeader('Authorization'));
        }
    }

    // --- cookie handling ---------------------------------------------------

    public function testCapturesCookiesFromLoginAndSendsThemOnDownload(): void
    {
        $session = $this->makeSession([
            $this->loginForm('t', ['XSRF-TOKEN=xsrf1; path=/']),
            $this->loginSuccessRedirect(['breezedoc_session=sess1; path=/; HttpOnly']),
            $this->pdf(),
        ]);
        $session->getPdf(1);

        $cookieHeader = $this->requestAt(2)->getHeaderLine('Cookie');
        $this->assertStringContainsString('XSRF-TOKEN=xsrf1', $cookieHeader);
        $this->assertStringContainsString('breezedoc_session=sess1', $cookieHeader);
    }

    public function testCapturesMultipleSetCookieHeadersOnOneResponse(): void
    {
        $store = new ArraySessionStore();
        $session = $this->makeSession([
            $this->loginForm('t', ['a=1; path=/', 'b=2; path=/']),
            $this->loginSuccessRedirect([]),
            $this->pdf(),
        ], $store);
        $session->getPdf(1);

        $cookies = $store->load()->getCookies();
        $this->assertSame('1', $cookies['a']);
        $this->assertSame('2', $cookies['b']);
    }

    public function testSendsCachedCookiesOnDownload(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState(null, ['breezedoc_session' => 'cachedval']));
        $session = $this->makeSession([$this->pdf()], $store);
        $session->getPdf(9);

        $this->assertSame('breezedoc_session=cachedval', $this->requestAt(0)->getHeaderLine('Cookie'));
    }

    public function testDeletionCookieRemovesFromJar(): void
    {
        $store = new ArraySessionStore();
        $session = $this->makeSession([
            $this->loginForm('t', ['temp=here; path=/']),
            $this->loginSuccessRedirect(['temp=; Max-Age=0', 'breezedoc_session=s; path=/']),
            $this->pdf(),
        ], $store);
        $session->getPdf(1);

        $cookies = $store->load()->getCookies();
        $this->assertArrayNotHasKey('temp', $cookies);
        $this->assertSame('s', $cookies['breezedoc_session']);
        $this->assertStringNotContainsString('temp', $this->requestAt(2)->getHeaderLine('Cookie'));
    }

    public function testPersistedSessionStoresCookiesAsNameValueMap(): void
    {
        $store = new ArraySessionStore();
        $session = $this->makeSession([
            $this->loginForm('t', ['XSRF-TOKEN=x; path=/']),
            $this->loginSuccessRedirect(['breezedoc_session=live; path=/']),
            $this->pdf(),
        ], $store);
        $session->getPdf(1);

        $this->assertSame(
            ['XSRF-TOKEN' => 'x', 'breezedoc_session' => 'live'],
            $store->load()->getCookies()
        );
    }

    // --- session reuse / TTL ----------------------------------------------

    public function testReusesCachedSessionWithinTtlWithoutLoggingIn(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->pdf('%PDF-cached')], $store);

        $this->assertSame('%PDF-cached', $session->getPdf(42));
        $this->assertSame(['GET /documents/42/download'], $this->requestLines());
    }

    public function testProactivelyLogsInWhenCachedSessionExpired(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState(time() - 100000));
        $session = $this->makeSession([$this->loginForm(), $this->loginSuccessRedirect(), $this->pdf()], $store, 3600);

        $session->getPdf(7);
        $this->assertSame(['GET /login', 'POST /login', 'GET /documents/7/download'], $this->requestLines());
    }

    public function testLogsInFreshWhenCachedSessionBelongsToAnotherEmail(): void
    {
        $store = new ArraySessionStore();
        $store->save(new SessionState('someone-else@example.com', time(), ['x' => 'y']));
        $session = $this->makeSession([$this->loginForm(), $this->loginSuccessRedirect(), $this->pdf()], $store);

        $session->getPdf(1);
        $this->assertContains('POST /login', $this->requestLines());
        $this->assertSame(self::EMAIL, $store->load()->getEmail());
    }

    // --- reactive re-login -------------------------------------------------

    public function testReactivelyReLogsInWhenSessionDiesViaRedirect(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->pdf('%PDF-recovered'),
        ], $store);

        $this->assertSame('%PDF-recovered', $session->getPdf(99));
        $this->assertSame(
            ['GET /documents/99/download', 'GET /login', 'POST /login', 'GET /documents/99/download'],
            $this->requestLines()
        );
    }

    public function testReactivelyReLogsInWhenSessionDiesViaFollowedLoginPage(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        // A redirect-following client would return the login HTML (200) instead of a PDF.
        $session = $this->makeSession([
            $this->html(200, '<form><input name="password" type="password"></form>'),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->pdf('%PDF-ok'),
        ], $store);

        $this->assertSame('%PDF-ok', $session->getPdf(3));
    }

    public function testGivesUpAfterOneReLoginAttempt(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->redirectToLogin(),
        ], $store);

        $this->expectException(AuthenticationException::class);
        $session->getPdf(5);
    }

    // --- download error mapping -------------------------------------------

    public function testThrowsAuthorizationExceptionOnForbiddenWithoutReLogin(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->html(403, 'forbidden')], $store);

        try {
            $session->getPdf(484041);
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame(['GET /documents/484041/download'], $this->requestLines());
        }
    }

    public function testThrowsNotFoundExceptionOnMissingDocument(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->html(404, 'missing')], $store);

        $this->expectException(NotFoundException::class);
        $session->getPdf(123456);
    }

    public function testThrowsRateLimitExceptionWhenThrottled(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->html(429, 'slow down')], $store);

        $this->expectException(RateLimitException::class);
        $session->getPdf(1);
    }

    public function testThrowsApiExceptionOnUnexpectedStatus(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->html(500, 'boom')], $store);

        $this->expectException(ApiException::class);
        $session->getPdf(1);
    }

    public function testWrapsTransportErrorInApiException(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $boom = new FakeTransportException('connection refused');
        $session = $this->makeSession([$boom], $store);

        try {
            $session->getPdf(1);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertStringContainsString('connection refused', $e->getMessage());
            $this->assertSame($boom, $e->getPrevious());
        }
    }

    // --- login failures ----------------------------------------------------

    public function testThrowsWhenLoginRejectedViaRedirect(): void
    {
        $session = $this->makeSession([$this->loginForm(), $this->loginFailRedirect()]);

        $this->expectException(AuthenticationException::class);
        $session->getPdf(1);
    }

    public function testThrowsWhenLoginRejectedViaFollowedLoginPage(): void
    {
        $session = $this->makeSession([$this->loginForm(), $this->loginFailFollowed()]);

        $this->expectException(AuthenticationException::class);
        $session->getPdf(1);
    }

    public function testThrowsWhenLoginTokenCannotBeFound(): void
    {
        $session = $this->makeSession([
            $this->html(200, '<html><body>Just a moment... please enable JavaScript</body></html>'),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('login token');
        $session->getPdf(1);
    }

    // --- token extraction variants ----------------------------------------

    public function testExtractsTokenFromMetaTagFallback(): void
    {
        $html = '<html><head><meta name="csrf-token" content="meta-token"></head>'
            . '<body><form action="/login"></form></body></html>';
        $session = $this->makeSession([
            $this->html(200, $html),
            $this->loginSuccessRedirect(),
            $this->pdf(),
        ]);
        $session->getPdf(1);

        parse_str((string) $this->requestAt(1)->getBody(), $fields);
        $this->assertSame('meta-token', $fields['_token']);
    }

    public function testExtractsTokenWhenValuePrecedesName(): void
    {
        $html = '<form action="/login"><input type="hidden" value="rev-token" name="_token"></form>';
        $session = $this->makeSession([
            $this->html(200, $html),
            $this->loginSuccessRedirect(),
            $this->pdf(),
        ]);
        $session->getPdf(1);

        parse_str((string) $this->requestAt(1)->getBody(), $fields);
        $this->assertSame('rev-token', $fields['_token']);
    }

    // --- validate ----------------------------------------------------------

    public function testValidateReturnsTrueForLiveSession(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->html(200, '<a href="/logout">out</a>')], $store);

        $this->assertTrue($session->validate());
        $this->assertSame(['GET /documents'], $this->requestLines());
    }

    public function testValidateReturnsFalseWhenRedirectedToLogin(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([$this->redirectToLogin()], $store);

        $this->assertFalse($session->validate());
    }

    public function testValidateReturnsFalseWhenFollowedToLoginPage(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        // A redirect-following client would land on the login form (200) when the
        // session is dead; validate() must recognize that as not-authenticated.
        $session = $this->makeSession([
            $this->html(200, '<form action="/login"><input name="password" type="password"></form>'),
        ], $store);

        $this->assertFalse($session->validate());
    }

    public function testValidateReturnsFalseWithNoStoredSession(): void
    {
        $session = $this->makeSession([]);

        $this->assertFalse($session->validate());
        $this->assertSame([], $this->requestLines());
    }

    public function testValidateReturnsFalseForDifferentEmail(): void
    {
        $store = new ArraySessionStore();
        $store->save(new SessionState('other@example.com', time(), ['x' => 'y']));
        $session = $this->makeSession([], $store);

        $this->assertFalse($session->validate());
        $this->assertSame([], $this->requestLines());
    }

    // --- logout ------------------------------------------------------------

    public function testLogoutClearsStoredSession(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        $session = $this->makeSession([], $store);

        $session->logout();
        $this->assertNull($store->load());
    }
}
