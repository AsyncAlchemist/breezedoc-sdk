<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Webhooks;

use Breezedoc\Exceptions\BreezedocException;
use Breezedoc\Exceptions\WebhookVerificationException;
use Breezedoc\Tests\Unit\UnitTestCase;
use Breezedoc\Webhooks\WebhookEvent;
use Breezedoc\Webhooks\WebhookEventType;
use Breezedoc\Webhooks\WebhookVerifier;
use InvalidArgumentException;

class WebhookVerifierTest extends UnitTestCase
{
    private const SECRET = 'whsec_test_known_answer';
    private const TIMESTAMP = 1792756800;
    private const PAYLOAD = '{"id":"evt_01","type":"recipient.signed","created_at":"2026-10-23T12:00:00Z",'
        . '"data":{"document_id":"doc_456","status":"sent","recipient":{"id":"rcp_789","email":"signer@example.com"}}}';

    /**
     * Computed independently with BreezeDoc's published Python reference code.
     */
    private const KNOWN_SIGNATURE = '9a51c8f02d75692fb0ac13827ef17dffdab5ea56dac00ec1d5e8e327ccfa4d2d';

    private function header(): string
    {
        return 't=' . self::TIMESTAMP . ',v1=' . self::KNOWN_SIGNATURE;
    }

    public function testKnownAnswerSignature(): void
    {
        $this->assertSame(
            self::KNOWN_SIGNATURE,
            WebhookVerifier::computeSignature(self::SECRET, (string) self::TIMESTAMP, self::PAYLOAD)
        );
    }

    public function testVerifiesAKnownGoodRequest(): void
    {
        $verifier = new WebhookVerifier(self::SECRET);

        $this->assertTrue($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP));
    }

    public function testToleranceIsFiveMinutesInBothDirections(): void
    {
        $verifier = new WebhookVerifier(self::SECRET);

        $this->assertTrue($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP + 300));
        $this->assertTrue($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP - 300));
        $this->assertFalse($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP + 301));
        $this->assertFalse($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP - 301));
    }

    public function testCustomTolerance(): void
    {
        $verifier = new WebhookVerifier(self::SECRET, 60);

        $this->assertFalse($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP + 61));
    }

    public function testRejectsAModifiedBody(): void
    {
        $verifier = new WebhookVerifier(self::SECRET);

        $this->assertFalse($verifier->verify(self::PAYLOAD . ' ', $this->header(), self::TIMESTAMP));
        $this->assertFalse($verifier->verify(str_replace('"sent"', '"completed"', self::PAYLOAD), $this->header(), self::TIMESTAMP));
    }

    public function testRejectsAWrongSecret(): void
    {
        $verifier = new WebhookVerifier('whsec_other');

        $this->assertFalse($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP));
    }

    public function testAcceptsAnyMatchingSignatureDuringRotation(): void
    {
        $header = WebhookVerifier::generateSignatureHeader(['whsec_old', 'whsec_new'], self::PAYLOAD, self::TIMESTAMP);

        $this->assertTrue((new WebhookVerifier('whsec_new'))->verify(self::PAYLOAD, $header, self::TIMESTAMP));
        $this->assertTrue((new WebhookVerifier('whsec_old'))->verify(self::PAYLOAD, $header, self::TIMESTAMP));
        $this->assertFalse((new WebhookVerifier('whsec_neither'))->verify(self::PAYLOAD, $header, self::TIMESTAMP));
    }

    public function testAcceptsSeveralConfiguredSecrets(): void
    {
        $verifier = new WebhookVerifier(['whsec_other', self::SECRET]);

        $this->assertTrue($verifier->verify(self::PAYLOAD, $this->header(), self::TIMESTAMP));
    }

    /**
     * @return array<string, array{string}>
     */
    public function malformedHeaderProvider(): array
    {
        $sig = self::KNOWN_SIGNATURE;
        $ts = (string) self::TIMESTAMP;

        return [
            'empty' => [''],
            'no timestamp' => ['v1=' . $sig],
            'two timestamps' => ['t=' . $ts . ',t=' . $ts . ',v1=' . $sig],
            'no signature' => ['t=' . $ts],
            'uppercase hex' => ['t=' . $ts . ',v1=' . strtoupper($sig)],
            'short hex' => ['t=' . $ts . ',v1=' . substr($sig, 0, 63)],
            'space after comma' => ['t=' . $ts . ', v1=' . $sig],
            'trailing newline' => ['t=' . $ts . ',v1=' . $sig . "\n"],
            'timestamp too long' => ['t=1' . str_repeat('0', 12) . ',v1=' . $sig],
            'v0 scheme only' => ['t=' . $ts . ',v0=' . $sig],
        ];
    }

    /**
     * @dataProvider malformedHeaderProvider
     */
    public function testRejectsMalformedHeaders(string $header): void
    {
        $verifier = new WebhookVerifier(self::SECRET);

        $this->assertFalse($verifier->verify(self::PAYLOAD, $header, self::TIMESTAMP));
    }

    public function testIgnoresUnknownPartsNextToAValidSignature(): void
    {
        $header = 't=' . self::TIMESTAMP . ',v0=deadbeef,v1=' . self::KNOWN_SIGNATURE;

        $this->assertTrue((new WebhookVerifier(self::SECRET))->verify(self::PAYLOAD, $header, self::TIMESTAMP));
    }

    public function testAssertValidGivesTheReasonWithoutTheSecret(): void
    {
        try {
            (new WebhookVerifier(self::SECRET))->assertValid(self::PAYLOAD, $this->header(), self::TIMESTAMP + 3600);
            $this->fail('Expected WebhookVerificationException');
        } catch (WebhookVerificationException $e) {
            $this->assertInstanceOf(BreezedocException::class, $e);
            $this->assertStringContainsString('tolerance', $e->getMessage());
            $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
        }
    }

    public function testConstructEvent(): void
    {
        $event = (new WebhookVerifier(self::SECRET))->constructEvent(self::PAYLOAD, $this->header(), self::TIMESTAMP);

        $this->assertInstanceOf(WebhookEvent::class, $event);
        $this->assertSame('evt_01', $event->getId());
        $this->assertSame(WebhookEventType::RECIPIENT_SIGNED, $event->getType());
        $this->assertSame('doc_456', $event->getDocumentId());
        $this->assertSame('sent', $event->getDocumentStatus());
        $this->assertNotNull($event->getRecipient());
        $this->assertSame('rcp_789', $event->getRecipient()->getId());
        $this->assertSame('signer@example.com', $event->getRecipient()->getEmail());
    }

    public function testConstructEventRejectsAnInvalidSignatureBeforeParsing(): void
    {
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier(self::SECRET))->constructEvent('not json', $this->header(), self::TIMESTAMP);
    }

    public function testConstructEventRejectsASignedNonObject(): void
    {
        $payload = '"just a string"';
        $header = WebhookVerifier::generateSignatureHeader(self::SECRET, $payload, self::TIMESTAMP);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('not a JSON object');
        (new WebhookVerifier(self::SECRET))->constructEvent($payload, $header, self::TIMESTAMP);
    }

    public function testConstructEventRejectsAPayloadWithoutIdOrType(): void
    {
        $payload = '{"data":{}}';
        $header = WebhookVerifier::generateSignatureHeader(self::SECRET, $payload, self::TIMESTAMP);

        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier(self::SECRET))->constructEvent($payload, $header, self::TIMESTAMP);
    }

    public function testConstructEventFromRequest(): void
    {
        $stream = $this->psr17Factory->createStream(self::PAYLOAD);
        $stream->getContents();
        $request = $this->psr17Factory->createServerRequest('POST', 'https://example.com/hook')
            ->withHeader('breezedoc-signature', $this->header())
            ->withBody($stream);

        $event = (new WebhookVerifier(self::SECRET))->constructEventFromRequest($request, self::TIMESTAMP);

        $this->assertSame('evt_01', $event->getId());
    }

    public function testDefaultClockRejectsAnOldKnownVector(): void
    {
        $this->assertFalse((new WebhookVerifier(self::SECRET))->verify(self::PAYLOAD, $this->header()));
    }

    public function testGeneratedHeaderVerifiesWithTheDefaultClock(): void
    {
        $header = WebhookVerifier::generateSignatureHeader(self::SECRET, self::PAYLOAD);

        $this->assertTrue((new WebhookVerifier(self::SECRET))->verify(self::PAYLOAD, $header));
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public function invalidConstructorProvider(): array
    {
        return [
            'empty secret' => ['', 300],
            'empty list' => [[], 300],
            'non-string secret' => [[123], 300],
            'zero tolerance' => [self::SECRET, 0],
        ];
    }

    /**
     * @dataProvider invalidConstructorProvider
     * @param mixed $secrets
     */
    public function testConstructorValidation($secrets, int $tolerance): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WebhookVerifier($secrets, $tolerance);
    }
}
