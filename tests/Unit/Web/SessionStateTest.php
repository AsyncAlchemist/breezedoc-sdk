<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Web;

use Breezedoc\Web\SessionState;
use PHPUnit\Framework\TestCase;

class SessionStateTest extends TestCase
{
    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $state = new SessionState('user@example.com', 1700000000, ['breezedoc_session' => 'abc', 'XSRF-TOKEN' => 'xyz']);

        $restored = SessionState::fromArray($state->toArray());

        $this->assertNotNull($restored);
        $this->assertSame('user@example.com', $restored->getEmail());
        $this->assertSame(1700000000, $restored->getCreatedAt());
        $this->assertSame(['breezedoc_session' => 'abc', 'XSRF-TOKEN' => 'xyz'], $restored->getCookies());
    }

    public function testIsExpiredComparesAgainstTtl(): void
    {
        $state = new SessionState('a@b.com', 1000, []);

        $this->assertFalse($state->isExpired(3600, 1000 + 3599));
        $this->assertTrue($state->isExpired(3600, 1000 + 3600));
        $this->assertTrue($state->isExpired(3600, 1000 + 10000));
    }

    public function testFromArrayReturnsNullOnMissingKeys(): void
    {
        $this->assertNull(SessionState::fromArray([]));
        $this->assertNull(SessionState::fromArray(['email' => 'a@b.com']));
        $this->assertNull(SessionState::fromArray(['email' => 'a@b.com', 'created_at' => 1]));
    }

    public function testFromArrayReturnsNullWhenTypesInvalid(): void
    {
        $this->assertNull(SessionState::fromArray([
            'email' => 123,
            'created_at' => 1,
            'cookies' => [],
        ]));

        $this->assertNull(SessionState::fromArray([
            'email' => 'a@b.com',
            'created_at' => 1,
            'cookies' => 'not-an-array',
        ]));
    }

    public function testFromArrayReturnsNullForLegacyGuzzleCookieFormat(): void
    {
        // Old format stored a list of cookie arrays; the name => value map now expects
        // string values, so a legacy cache is rejected (and a fresh login performed).
        $this->assertNull(SessionState::fromArray([
            'email' => 'a@b.com',
            'created_at' => 1,
            'cookies' => [['Name' => 'c', 'Value' => 'v']],
        ]));
    }
}
