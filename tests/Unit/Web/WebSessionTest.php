<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Web;

use Breezedoc\Exceptions\ApiException;
use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\BreezedocException;
use Breezedoc\Exceptions\DeleteNotConfirmedException;
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

    // --- deleteDocument ----------------------------------------------------

    /**
     * An authenticated document activity page carrying the CSRF meta tag (as the
     * site's layout does) and, optionally, a different form `_token`.
     */
    private function documentPage(string $metaToken = 'page-tok', ?string $formToken = null): ResponseInterface
    {
        $html = '<html><head><meta name="csrf-token" content="' . $metaToken . '"></head><body>'
            . '<h1>Activity</h1><a href="/logout">Log out</a>';
        if ($formToken !== null) {
            $html .= '<form action="' . self::BASE . '/documents/1" method="post">'
                . '<input type="hidden" name="_token" value="' . $formToken . '">'
                . '<input type="hidden" name="_method" value="DELETE"></form>';
        }
        $html .= '</body></html>';

        return $this->html(200, $html);
    }

    private function notFoundPage(): ResponseInterface
    {
        return $this->html(404, '<html><body>Not Found</body></html>');
    }

    private function deletedRedirect(): ResponseInterface
    {
        return $this->psr17->createResponse(302)->withHeader('Location', self::BASE . '/documents');
    }

    private function pageExpired(): ResponseInterface
    {
        return $this->html(419, '<html><body>Page Expired</body></html>');
    }

    private function cachedStore(): ArraySessionStore
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());
        return $store;
    }

    /**
     * @return array<string, string>
     */
    private function formFieldsAt(int $index): array
    {
        parse_str((string) $this->requestAt($index)->getBody(), $fields);
        /** @var array<string, string> $fields */
        return $fields;
    }

    public function testDeleteDocumentSucceedsWhenFollowUpCheckAnswers404(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->deletedRedirect(), $this->notFoundPage()],
            $this->cachedStore()
        );

        $session->deleteDocument(311939);

        $this->assertSame(
            ['GET /documents/311939/activity', 'POST /documents/311939', 'GET /documents/311939/activity'],
            $this->requestLines()
        );
    }

    public function testDeleteDocumentPostsTheDeleteFormAsABrowserWould(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState(null, ['breezedoc_session' => 'cachedval']));
        $session = $this->makeSession(
            [$this->documentPage('csrf-abc'), $this->deletedRedirect(), $this->notFoundPage()],
            $store
        );

        $session->deleteDocument(42);

        $post = $this->requestAt(1);
        $this->assertSame('POST', $post->getMethod());
        $this->assertSame(self::BASE . '/documents/42', (string) $post->getUri());
        $this->assertSame(['_token' => 'csrf-abc', '_method' => 'DELETE'], $this->formFieldsAt(1));
        $this->assertSame('application/x-www-form-urlencoded', $post->getHeaderLine('Content-Type'));
        $this->assertSame(self::BASE, $post->getHeaderLine('Origin'));
        $this->assertSame(self::BASE . '/documents', $post->getHeaderLine('Referer'));
        $this->assertStringContainsString('breezedoc_session=cachedval', $post->getHeaderLine('Cookie'));
        $this->assertStringContainsString('Mozilla/5.0', $post->getHeaderLine('User-Agent'));
        $this->assertFalse($post->hasHeader('Authorization'));
    }

    public function testDeleteDocumentPrefersTheMetaTokenOverAFormToken(): void
    {
        $session = $this->makeSession(
            [$this->documentPage('meta-tok', 'form-tok'), $this->deletedRedirect(), $this->notFoundPage()],
            $this->cachedStore()
        );

        $session->deleteDocument(1);

        $this->assertSame('meta-tok', $this->formFieldsAt(1)['_token']);
    }

    public function testDeleteDocumentFallsBackToTheFormToken(): void
    {
        $page = $this->html(200, '<form method="post"><input type="hidden" name="_token" value="form-only">'
            . '<input type="hidden" name="_method" value="DELETE"></form>');
        $session = $this->makeSession([$page, $this->deletedRedirect(), $this->notFoundPage()], $this->cachedStore());

        $session->deleteDocument(1);

        $this->assertSame('form-only', $this->formFieldsAt(1)['_token']);
    }

    public function testDeleteDocumentSucceedsWithARedirectFollowingClient(): void
    {
        // A following client lands on the documents list (200) after the delete.
        $documentsList = $this->html(200, '<html><body><a href="/logout">Log out</a> Documents</body></html>');
        $session = $this->makeSession(
            [$this->documentPage(), $documentsList, $this->notFoundPage()],
            $this->cachedStore()
        );

        $session->deleteDocument(8);

        $this->assertSame(
            ['GET /documents/8/activity', 'POST /documents/8', 'GET /documents/8/activity'],
            $this->requestLines()
        );
    }

    public function testDeleteDocumentThrowsNotFoundWithoutPostingWhenThePageAnswers404(): void
    {
        $session = $this->makeSession([$this->notFoundPage()], $this->cachedStore());

        try {
            $session->deleteDocument(77);
            $this->fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame(['GET /documents/77/activity'], $this->requestLines());
            $this->assertStringContainsString('77', $e->getMessage());
        }
    }

    public function testDeleteDocumentLogsInWhenNoCachedSession(): void
    {
        $session = $this->makeSession([
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->documentPage(),
            $this->deletedRedirect(),
            $this->notFoundPage(),
        ]);

        $session->deleteDocument(5);

        $this->assertSame(
            ['GET /login', 'POST /login', 'GET /documents/5/activity', 'POST /documents/5', 'GET /documents/5/activity'],
            $this->requestLines()
        );
    }

    public function testDeleteDocumentReLogsInOnceWhenTheCachedSessionIsDead(): void
    {
        $store = $this->cachedStore();
        $session = $this->makeSession([
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(['breezedoc_session=fresh; path=/']),
            $this->documentPage('after-login'),
            $this->deletedRedirect(),
            $this->notFoundPage(),
        ], $store);

        $session->deleteDocument(99);

        $this->assertSame(
            [
                'GET /documents/99/activity',
                'GET /login',
                'POST /login',
                'GET /documents/99/activity',
                'POST /documents/99',
                'GET /documents/99/activity',
            ],
            $this->requestLines()
        );
        $this->assertSame('after-login', $this->formFieldsAt(4)['_token']);
        $this->assertSame('fresh', $store->load()->getCookies()['breezedoc_session']);
    }

    public function testDeleteDocumentReLogsInWhenThePostMeetsADeadSession(): void
    {
        $session = $this->makeSession([
            $this->documentPage('old-tok'),
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->documentPage('new-tok'),
            $this->deletedRedirect(),
            $this->notFoundPage(),
        ], $this->cachedStore());

        $session->deleteDocument(12);

        $this->assertSame(
            [
                'GET /documents/12/activity',
                'POST /documents/12',
                'GET /login',
                'POST /login',
                'GET /documents/12/activity',
                'POST /documents/12',
                'GET /documents/12/activity',
            ],
            $this->requestLines()
        );
        $this->assertSame('new-tok', $this->formFieldsAt(5)['_token']);
    }

    public function testDeleteDocumentGivesUpAfterOneReLogin(): void
    {
        $session = $this->makeSession([
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->redirectToLogin(),
        ], $this->cachedStore());

        try {
            $session->deleteDocument(3);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertNotContains('POST /documents/3', $this->requestLines());
        }
    }

    public function testDeleteDocumentThrowsAuthenticationExceptionWhenLoginFails(): void
    {
        $session = $this->makeSession([$this->loginForm(), $this->loginFailRedirect()]);

        try {
            $session->deleteDocument(4);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(['GET /login', 'POST /login'], $this->requestLines());
        }
    }

    public function testDeleteDocumentRefreshesTheTokenOnceAfter419(): void
    {
        $session = $this->makeSession([
            $this->documentPage('stale-tok'),
            $this->pageExpired(),
            $this->documentPage('fresh-tok'),
            $this->deletedRedirect(),
            $this->notFoundPage(),
        ], $this->cachedStore());

        $session->deleteDocument(21);

        $this->assertSame(
            [
                'GET /documents/21/activity',
                'POST /documents/21',
                'GET /documents/21/activity',
                'POST /documents/21',
                'GET /documents/21/activity',
            ],
            $this->requestLines()
        );
        $this->assertSame('stale-tok', $this->formFieldsAt(1)['_token']);
        $this->assertSame('fresh-tok', $this->formFieldsAt(3)['_token']);
    }

    public function testDeleteDocumentThrowsWhen419RepeatsAfterTheTokenRefresh(): void
    {
        $session = $this->makeSession([
            $this->documentPage(),
            $this->pageExpired(),
            $this->documentPage(),
            $this->pageExpired(),
        ], $this->cachedStore());

        try {
            $session->deleteDocument(22);
            $this->fail('Expected BreezedocException');
        } catch (BreezedocException $e) {
            $this->assertNotInstanceOf(NotFoundException::class, $e);
            $this->assertNotInstanceOf(AuthenticationException::class, $e);
            $this->assertInstanceOf(ApiException::class, $e);
            $this->assertSame(419, $e->getCode());
            $this->assertCount(4, $this->requestLines());
        }
    }

    public function testDeleteDocumentThrowsOnForbiddenPostWithoutRetry(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->html(403, 'Forbidden', 'text/plain')],
            $this->cachedStore()
        );

        try {
            $session->deleteDocument(484041);
            $this->fail('Expected BreezedocException');
        } catch (BreezedocException $e) {
            $this->assertNotInstanceOf(NotFoundException::class, $e);
            $this->assertSame(403, $e->getCode());
            $this->assertSame(
                ['GET /documents/484041/activity', 'POST /documents/484041'],
                $this->requestLines()
            );
        }
    }

    public function testDeleteDocumentThrowsOnForbiddenPageWithoutPosting(): void
    {
        $session = $this->makeSession([$this->html(403, 'Forbidden', 'text/plain')], $this->cachedStore());

        try {
            $session->deleteDocument(6);
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertSame(['GET /documents/6/activity'], $this->requestLines());
            // The message names the step that failed: no delete was refused, none was sent.
            $this->assertStringContainsString('activity page of document 6 before the delete', $e->getMessage());
            $this->assertStringNotContainsString('refused the delete', $e->getMessage());
        }
    }

    public function testDeleteDocumentNamesThePostStepWhenThePostIsRefused(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->html(500, 'Server Error')],
            $this->cachedStore()
        );

        try {
            $session->deleteDocument(15);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(
                'The Breezedoc website refused the delete of document 15 (HTTP 500).',
                $e->getMessage()
            );
        }
    }

    public function testDeleteDocumentThrowsOnServerErrorFromThePost(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->html(500, 'Server Error')],
            $this->cachedStore()
        );

        try {
            $session->deleteDocument(9);
            $this->fail('Expected BreezedocException');
        } catch (BreezedocException $e) {
            $this->assertNotInstanceOf(NotFoundException::class, $e);
            $this->assertSame(500, $e->getCode());
            $this->assertCount(2, $this->requestLines());
        }
    }

    public function testDeleteDocumentThrowsWithoutPostingWhenThePageHasNoToken(): void
    {
        $page = $this->html(200, '<html><body><a href="/logout">Log out</a></body></html>');
        $session = $this->makeSession([$page], $this->cachedStore());

        try {
            $session->deleteDocument(10);
            $this->fail('Expected BreezedocException');
        } catch (BreezedocException $e) {
            $this->assertNotInstanceOf(NotFoundException::class, $e);
            $this->assertSame(['GET /documents/10/activity'], $this->requestLines());
        }
    }

    public function testDeleteDocumentThrowsWhenTheDocumentIsStillThereAfterTheDelete(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->deletedRedirect(), $this->documentPage()],
            $this->cachedStore()
        );

        try {
            $session->deleteDocument(311939);
            $this->fail('Expected DeleteNotConfirmedException');
        } catch (DeleteNotConfirmedException $e) {
            $this->assertSame(
                'The website still shows document 311939 after the delete.',
                $e->getMessage()
            );
        }
    }

    public function testDeleteDocumentThrowsWhenTheFollowUpCheckIsUnexpected(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->deletedRedirect(), $this->html(500, 'Server Error')],
            $this->cachedStore()
        );

        try {
            $session->deleteDocument(13);
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertNotInstanceOf(NotFoundException::class, $e);
            $this->assertSame(500, $e->getCode());
            $this->assertStringContainsString('activity page of document 13 after the delete', $e->getMessage());
        }
    }

    public function testDeleteDocumentMapsForbiddenAtTheFollowUpCheck(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->deletedRedirect(), $this->html(403, 'Forbidden', 'text/plain')],
            $this->cachedStore()
        );

        $this->expectException(AuthorizationException::class);
        $session->deleteDocument(16);
    }

    public function testDeleteDocumentMapsRateLimitAtTheFollowUpCheck(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->deletedRedirect(), $this->html(429, 'Too Many Requests')],
            $this->cachedStore()
        );

        $this->expectException(RateLimitException::class);
        $session->deleteDocument(17);
    }

    public function testDeleteDocumentPersistsTheRolledSessionAfterSuccess(): void
    {
        $createdAt = time() - 100;
        $store = new ArraySessionStore();
        $store->save($this->validState($createdAt, ['breezedoc_session' => 'abc']));
        $session = $this->makeSession([
            $this->documentPage(),
            $this->deletedRedirect(),
            $this->withCookies($this->notFoundPage(), ['XSRF-TOKEN=rolled; path=/']),
        ], $store);

        $session->deleteDocument(18);

        // Only the save after the follow-up check can put the rolled cookie in the store:
        // no login ran, so nothing else saved the session.
        $saved = $store->load();
        $this->assertNotNull($saved);
        $this->assertSame(['breezedoc_session' => 'abc', 'XSRF-TOKEN' => 'rolled'], $saved->getCookies());
        $this->assertSame($createdAt, $saved->getCreatedAt());
    }

    public function testDeleteDocumentReLogsInWhenTheFollowUpCheckMeetsADeadSession(): void
    {
        $session = $this->makeSession([
            $this->documentPage(),
            $this->deletedRedirect(),
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->notFoundPage(),
        ], $this->cachedStore());

        $session->deleteDocument(19);

        $this->assertSame(
            [
                'GET /documents/19/activity',
                'POST /documents/19',
                'GET /documents/19/activity',
                'GET /login',
                'POST /login',
                'GET /documents/19/activity',
            ],
            $this->requestLines()
        );
    }

    public function testDeleteDocumentFollowUpCheckRecognisesAFollowedLoginPage(): void
    {
        // A redirect-following client lands on the login form (200) when the session
        // dies before the check; that is a dead session, not "the document is still there".
        $session = $this->makeSession([
            $this->documentPage(),
            $this->deletedRedirect(),
            $this->loginFailFollowed(),
            $this->loginForm(),
            $this->loginSuccessFollowed(),
            $this->notFoundPage(),
        ], $this->cachedStore());

        $session->deleteDocument(20);

        $this->assertSame(
            [
                'GET /documents/20/activity',
                'POST /documents/20',
                'GET /documents/20/activity',
                'GET /login',
                'POST /login',
                'GET /documents/20/activity',
            ],
            $this->requestLines()
        );
    }

    public function testDeleteDocumentThrowsWhenTheReLoginIsSpentAtTheFollowUpCheck(): void
    {
        $session = $this->makeSession([
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->documentPage(),
            $this->deletedRedirect(),
            $this->redirectToLogin(),
        ], $this->cachedStore());

        try {
            $session->deleteDocument(23);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame(
                [
                    'GET /documents/23/activity',
                    'GET /login',
                    'POST /login',
                    'GET /documents/23/activity',
                    'POST /documents/23',
                    'GET /documents/23/activity',
                ],
                $this->requestLines()
            );
        }
    }

    public function testDeleteDocumentReLogsInWhenADeadSessionShowsAs419OnThePost(): void
    {
        // Laravel checks the CSRF token before auth, so a dead session can answer the
        // POST with 419. The token refresh then meets the dead session and re-logs in.
        $session = $this->makeSession([
            $this->documentPage('old-tok'),
            $this->pageExpired(),
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccessRedirect(),
            $this->documentPage('new-tok'),
            $this->deletedRedirect(),
            $this->notFoundPage(),
        ], $this->cachedStore());

        $session->deleteDocument(24);

        $this->assertSame(
            [
                'GET /documents/24/activity',
                'POST /documents/24',
                'GET /documents/24/activity',
                'GET /login',
                'POST /login',
                'GET /documents/24/activity',
                'POST /documents/24',
                'GET /documents/24/activity',
            ],
            $this->requestLines()
        );
        $this->assertSame('new-tok', $this->formFieldsAt(6)['_token']);
    }

    public function testDeleteDocumentWithARedirectFollowingClientAndADeadSession(): void
    {
        $session = $this->makeSession([
            $this->loginFailFollowed(),
            $this->loginForm(),
            $this->loginSuccessFollowed(),
            $this->documentPage('after-login'),
            $this->html(200, '<html><body><a href="/logout">Log out</a> Documents</body></html>'),
            $this->notFoundPage(),
        ], $this->cachedStore());

        $session->deleteDocument(25);

        $this->assertSame(
            [
                'GET /documents/25/activity',
                'GET /login',
                'POST /login',
                'GET /documents/25/activity',
                'POST /documents/25',
                'GET /documents/25/activity',
            ],
            $this->requestLines()
        );
        $this->assertSame('after-login', $this->formFieldsAt(4)['_token']);
    }

    public function testDeleteDocumentWithARedirectFollowingClientAndADeadSessionAtThePost(): void
    {
        $session = $this->makeSession([
            $this->documentPage('old-tok'),
            $this->loginFailFollowed(),
            $this->loginForm(),
            $this->loginSuccessFollowed(),
            $this->documentPage('new-tok'),
            $this->html(200, '<html><body><a href="/logout">Log out</a> Documents</body></html>'),
            $this->notFoundPage(),
        ], $this->cachedStore());

        $session->deleteDocument(26);

        $this->assertSame(
            [
                'GET /documents/26/activity',
                'POST /documents/26',
                'GET /login',
                'POST /login',
                'GET /documents/26/activity',
                'POST /documents/26',
                'GET /documents/26/activity',
            ],
            $this->requestLines()
        );
        $this->assertSame('new-tok', $this->formFieldsAt(5)['_token']);
    }

    public function testDeleteDocumentPostThat404sIsDecidedByTheFollowUpCheck(): void
    {
        $session = $this->makeSession(
            [$this->documentPage(), $this->notFoundPage(), $this->notFoundPage()],
            $this->cachedStore()
        );

        $session->deleteDocument(14);

        $this->assertSame(
            ['GET /documents/14/activity', 'POST /documents/14', 'GET /documents/14/activity'],
            $this->requestLines()
        );
    }

    public function testDeleteDocumentExceptionMessagesCarryNoSecret(): void
    {
        $password = 'pw-Very-Secret-9f3a';
        $cookie = 'cookie-Very-Secret-77c1';
        $token = 'csrf-Very-Secret-41de';

        $scenarios = [
            'login fails' => [
                $this->loginForm($token, ['XSRF-TOKEN=' . $cookie . '; path=/']),
                $this->loginFailRedirect(),
            ],
            'already gone' => [
                $this->loginForm($token, ['XSRF-TOKEN=' . $cookie . '; path=/']),
                $this->loginSuccessRedirect(['breezedoc_session=' . $cookie . '; path=/']),
                $this->notFoundPage(),
            ],
            '419 twice' => [
                $this->loginForm($token),
                $this->loginSuccessRedirect(['breezedoc_session=' . $cookie . '; path=/']),
                $this->documentPage($token),
                $this->pageExpired(),
                $this->documentPage($token),
                $this->pageExpired(),
            ],
            'forbidden' => [
                $this->loginForm($token),
                $this->loginSuccessRedirect(['breezedoc_session=' . $cookie . '; path=/']),
                $this->documentPage($token),
                $this->html(403, 'Forbidden ' . $token, 'text/plain'),
            ],
            'still there' => [
                $this->loginForm($token),
                $this->loginSuccessRedirect(['breezedoc_session=' . $cookie . '; path=/']),
                $this->documentPage($token),
                $this->deletedRedirect(),
                $this->documentPage($token),
            ],
            'relogin spent' => [
                $this->loginForm($token),
                $this->loginSuccessRedirect(['breezedoc_session=' . $cookie . '; path=/']),
                $this->redirectToLogin(),
                $this->loginForm($token),
                $this->loginSuccessRedirect(['breezedoc_session=' . $cookie . '; path=/']),
                $this->redirectToLogin(),
            ],
        ];

        foreach ($scenarios as $name => $responses) {
            $http = new MockClient();
            foreach ($responses as $response) {
                $http->addResponse($response);
            }
            $session = new WebSession(
                $http,
                $this->psr17,
                $this->psr17,
                new ArraySessionStore(),
                self::EMAIL,
                $password,
                3600,
                self::BASE
            );

            try {
                $session->deleteDocument(555);
                $this->fail('Expected an exception for scenario: ' . $name);
            } catch (BreezedocException $e) {
                $message = $e->getMessage();
                $this->assertStringNotContainsString($password, $message, $name);
                $this->assertStringNotContainsString($cookie, $message, $name);
                $this->assertStringNotContainsString($token, $message, $name);
                $this->assertStringNotContainsString('Very-Secret', $message, $name);
            }
        }
    }
}
