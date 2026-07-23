<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Web;

use Breezedoc\Exceptions\AuthenticationException;
use Breezedoc\Exceptions\AuthorizationException;
use Breezedoc\Exceptions\NotFoundException;
use Breezedoc\Web\ArraySessionStore;
use Breezedoc\Web\SessionState;
use Breezedoc\Web\SessionStore;
use Breezedoc\Web\WebSession;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class WebSessionTest extends TestCase
{
    private const EMAIL = 'user@example.com';
    private const PASSWORD = 'secret';
    private const BASE = 'https://breezedoc.com';

    /**
     * @var array<int, array{request: Request, response: Response}>
     */
    private array $history = [];

    /**
     * Build a WebSession whose Guzzle client replays the given responses in order.
     *
     * @param array<int, Response> $responses
     */
    private function makeSession(array $responses, ?SessionStore $store = null, int $ttl = 3600): WebSession
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $client = new Client(['handler' => $stack]);

        return new WebSession(
            $client,
            $store ?? new ArraySessionStore(),
            self::EMAIL,
            self::PASSWORD,
            $ttl,
            self::BASE
        );
    }

    private function loginForm(string $token = 'tok123'): Response
    {
        $html = '<form method="POST" action="/login">'
            . '<input type="hidden" name="_token" value="' . $token . '">'
            . '<input name="email"><input name="password"></form>';
        return new Response(200, ['Set-Cookie' => 'breezedoc_session=abc; path=/'], $html);
    }

    private function loginSuccess(): Response
    {
        return new Response(302, ['Location' => self::BASE . '/documents']);
    }

    private function pdf(string $body = '%PDF-1.7 fake'): Response
    {
        return new Response(200, ['Content-Type' => 'application/pdf'], $body);
    }

    private function redirectToLogin(): Response
    {
        return new Response(302, ['Location' => self::BASE . '/login']);
    }

    /**
     * @return array<int, string> Request "METHOD path" strings, in order.
     */
    private function requestLines(): array
    {
        return array_map(static function (array $entry): string {
            $request = $entry['request'];
            return $request->getMethod() . ' ' . $request->getUri()->getPath();
        }, $this->history);
    }

    private function validState(?int $createdAt = null): SessionState
    {
        return new SessionState(
            self::EMAIL,
            $createdAt ?? time(),
            [['Name' => 'breezedoc_session', 'Value' => 'abc', 'Domain' => 'breezedoc.com']]
        );
    }

    public function testLogsInThenDownloadsWhenNoCachedSession(): void
    {
        $store = new ArraySessionStore();
        $session = $this->makeSession([
            $this->loginForm(),
            $this->loginSuccess(),
            $this->pdf('%PDF-hello'),
        ], $store);

        $bytes = $session->getPdf(311939);

        $this->assertSame('%PDF-hello', $bytes);
        $this->assertSame(
            ['GET /login', 'POST /login', 'GET /documents/311939/download'],
            $this->requestLines()
        );
        // Session was persisted for reuse.
        $this->assertNotNull($store->load());
        $this->assertSame(self::EMAIL, $store->load()->getEmail());
    }

    public function testSendsCredentialsAndTokenOnLogin(): void
    {
        $session = $this->makeSession([
            $this->loginForm('csrf-xyz'),
            $this->loginSuccess(),
            $this->pdf(),
        ]);

        $session->getPdf(1);

        $postBody = (string) $this->history[1]['request']->getBody();
        parse_str($postBody, $fields);
        $this->assertSame(self::EMAIL, $fields['email']);
        $this->assertSame(self::PASSWORD, $fields['password']);
        $this->assertSame('csrf-xyz', $fields['_token']);
    }

    public function testReusesCachedSessionWithinTtlWithoutLoggingIn(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());

        $session = $this->makeSession([$this->pdf('%PDF-cached')], $store);

        $bytes = $session->getPdf(42);

        $this->assertSame('%PDF-cached', $bytes);
        $this->assertSame(['GET /documents/42/download'], $this->requestLines());
    }

    public function testProactivelyLogsInWhenCachedSessionExpired(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState(time() - 100000)); // older than ttl

        $session = $this->makeSession([
            $this->loginForm(),
            $this->loginSuccess(),
            $this->pdf(),
        ], $store, 3600);

        $session->getPdf(7);

        $this->assertSame(
            ['GET /login', 'POST /login', 'GET /documents/7/download'],
            $this->requestLines()
        );
    }

    public function testReactivelyReLogsInWhenSessionDiesMidUse(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState()); // within TTL, but server says expired

        $session = $this->makeSession([
            $this->redirectToLogin(), // download -> dead session
            $this->loginForm(),
            $this->loginSuccess(),
            $this->pdf('%PDF-recovered'),
        ], $store);

        $bytes = $session->getPdf(99);

        $this->assertSame('%PDF-recovered', $bytes);
        $this->assertSame(
            ['GET /documents/99/download', 'GET /login', 'POST /login', 'GET /documents/99/download'],
            $this->requestLines()
        );
    }

    public function testGivesUpAfterOneReLoginAttempt(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());

        $session = $this->makeSession([
            $this->redirectToLogin(),
            $this->loginForm(),
            $this->loginSuccess(),
            $this->redirectToLogin(), // still dead after re-login
        ], $store);

        $this->expectException(AuthenticationException::class);
        $session->getPdf(5);
    }

    public function testThrowsAuthorizationExceptionOnForbiddenWithoutReLogin(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());

        $session = $this->makeSession([
            new Response(403, [], '<html>forbidden</html>'),
        ], $store);

        try {
            $session->getPdf(484041);
            $this->fail('Expected AuthorizationException');
        } catch (AuthorizationException $e) {
            // 403 must NOT trigger a re-login.
            $this->assertSame(['GET /documents/484041/download'], $this->requestLines());
        }
    }

    public function testThrowsNotFoundExceptionOnMissingDocument(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());

        $session = $this->makeSession([new Response(404, [], 'not found')], $store);

        $this->expectException(NotFoundException::class);
        $session->getPdf(123456);
    }

    public function testThrowsWhenLoginCredentialsRejected(): void
    {
        $session = $this->makeSession([
            $this->loginForm(),
            $this->redirectToLogin(), // POST redirected back to /login = failure
        ]);

        $this->expectException(AuthenticationException::class);
        $session->getPdf(1);
    }

    public function testThrowsWhenLoginTokenCannotBeFound(): void
    {
        $session = $this->makeSession([
            new Response(200, [], '<html><body>Just a moment... (challenge)</body></html>'),
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('login token');
        $session->getPdf(1);
    }

    public function testLogsInFreshWhenCachedSessionBelongsToAnotherEmail(): void
    {
        $store = new ArraySessionStore();
        $store->save(new SessionState('someone-else@example.com', time(), []));

        $session = $this->makeSession([
            $this->loginForm(),
            $this->loginSuccess(),
            $this->pdf(),
        ], $store);

        $session->getPdf(1);

        $this->assertContains('POST /login', $this->requestLines());
        // After login the stored session is rebound to the configured email.
        $this->assertSame(self::EMAIL, $store->load()->getEmail());
    }

    public function testValidateReturnsTrueForLiveSession(): void
    {
        $store = new ArraySessionStore();
        $store->save($this->validState());

        $session = $this->makeSession([new Response(200, [], '<html>dashboard /logout</html>')], $store);

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

    public function testValidateReturnsFalseWithNoStoredSession(): void
    {
        $session = $this->makeSession([]);

        $this->assertFalse($session->validate());
        $this->assertSame([], $this->requestLines()); // no HTTP call made
    }

    public function testPersistedSessionCapturesCookies(): void
    {
        $store = new ArraySessionStore();
        $session = $this->makeSession([
            $this->loginForm(),
            new Response(302, [
                'Location' => self::BASE . '/documents',
                'Set-Cookie' => 'breezedoc_session=live-value; path=/',
            ]),
            $this->pdf(),
        ], $store);

        $session->getPdf(1);

        $cookies = $store->load()->getCookies();
        $this->assertNotEmpty($cookies);
    }
}
