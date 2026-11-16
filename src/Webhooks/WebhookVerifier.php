<?php

declare(strict_types=1);

namespace Breezedoc\Webhooks;

use Breezedoc\Exceptions\WebhookVerificationException;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies the signature of BreezeDoc webhook requests and parses their events.
 *
 * The scheme, as BreezeDoc documents it at https://breezedoc.com/developers:
 *
 * - The `BreezeDoc-Signature` header is a comma-separated list with exactly one
 *   `t=<unix seconds>` part and one or more `v1=<hex>` parts.
 * - Each `v1` value is the lowercase hex HMAC-SHA256 of `"<t>." . <raw request body>`,
 *   keyed with the endpoint's signing secret (`whsec_...`, used as given).
 * - Reject a timestamp more than five minutes from now.
 * - After a secret rotation, BreezeDoc sends a signature for each of the two secrets for
 *   24 hours. The request is valid when any `v1` value matches.
 *
 * Verify the exact request bytes before any JSON parsing. Comparisons use hash_equals().
 *
 *     $verifier = new WebhookVerifier($secret);
 *     try {
 *         $event = $verifier->constructEvent($rawBody, $signatureHeader);
 *     } catch (WebhookVerificationException $e) {
 *         http_response_code(400);
 *         exit;
 *     }
 *     // Deduplicate by $event->getId(), store or queue the event, then answer 2xx.
 */
class WebhookVerifier
{
    /**
     * The signature header name (header names are case-insensitive).
     */
    public const SIGNATURE_HEADER = 'BreezeDoc-Signature';

    /**
     * The documented timestamp tolerance: five minutes.
     */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * @var array<string>
     */
    private array $secrets;

    private int $tolerance;

    /**
     * @param string|array<mixed> $secrets The signing secret, or several secrets (for
     *                                     example the old and the new one during a rotation)
     * @param int $tolerance Maximum age of the timestamp in seconds, in both directions
     */
    public function __construct($secrets, int $tolerance = self::DEFAULT_TOLERANCE)
    {
        $candidates = is_array($secrets) ? $secrets : [$secrets];

        if ($candidates === []) {
            throw new InvalidArgumentException('At least one webhook signing secret is required.');
        }

        $valid = [];
        foreach ($candidates as $secret) {
            if (!is_string($secret) || $secret === '') {
                throw new InvalidArgumentException('A webhook signing secret must be a non-empty string.');
            }
            $valid[] = $secret;
        }

        if ($tolerance < 1) {
            throw new InvalidArgumentException('The timestamp tolerance must be at least one second.');
        }

        $this->secrets = $valid;
        $this->tolerance = $tolerance;
    }

    /**
     * Check a signature header against the raw request body.
     *
     * Returns false for a missing or malformed header, a stale timestamp or a wrong
     * signature. It never throws for bad input.
     *
     * @param string $payload The raw request body, exactly as received
     * @param string $signatureHeader The value of the BreezeDoc-Signature header
     * @param int|null $now Current Unix time (for tests); defaults to time()
     */
    public function verify(string $payload, string $signatureHeader, ?int $now = null): bool
    {
        try {
            $this->assertValid($payload, $signatureHeader, $now);
        } catch (WebhookVerificationException $e) {
            return false;
        }

        return true;
    }

    /**
     * Check a signature header against the raw request body, or throw.
     *
     * @throws WebhookVerificationException With the reason, which never contains a secret
     */
    public function assertValid(string $payload, string $signatureHeader, ?int $now = null): void
    {
        $parts = explode(',', $signatureHeader);

        $timestamps = [];
        $signatures = [];
        foreach ($parts as $part) {
            if (preg_match('/^t=[0-9]{1,12}\z/', $part) === 1) {
                $timestamps[] = substr($part, 2);
            } elseif (preg_match('/^v1=[a-f0-9]{64}\z/', $part) === 1) {
                $signatures[] = substr($part, 3);
            }
        }

        if (count($timestamps) !== 1) {
            throw new WebhookVerificationException('The signature header must have exactly one timestamp.');
        }

        if ($signatures === []) {
            throw new WebhookVerificationException('The signature header has no v1 signature.');
        }

        $timestamp = $timestamps[0];
        $now = $now ?? time();
        if (abs($now - (int) $timestamp) > $this->tolerance) {
            throw new WebhookVerificationException('The signature timestamp is outside the tolerance.');
        }

        foreach ($this->secrets as $secret) {
            $expected = self::computeSignature($secret, $timestamp, $payload);
            foreach ($signatures as $signature) {
                if (hash_equals($expected, $signature)) {
                    return;
                }
            }
        }

        throw new WebhookVerificationException('No signature matches the payload.');
    }

    /**
     * Verify a request and return its event.
     *
     * @throws WebhookVerificationException If the signature is not valid, or the payload is
     *                                      not a JSON object with an event id and type
     */
    public function constructEvent(string $payload, string $signatureHeader, ?int $now = null): WebhookEvent
    {
        $this->assertValid($payload, $signatureHeader, $now);

        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookVerificationException('The webhook payload is not a JSON object.');
        }

        return WebhookEvent::fromArray($decoded);
    }

    /**
     * Verify a PSR-7 server request and return its event.
     *
     * The body stream must still hold the original bytes.
     *
     * @throws WebhookVerificationException
     */
    public function constructEventFromRequest(ServerRequestInterface $request, ?int $now = null): WebhookEvent
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $this->constructEvent(
            $body->getContents(),
            $request->getHeaderLine(self::SIGNATURE_HEADER),
            $now
        );
    }

    /**
     * Compute one `v1` signature: hex HMAC-SHA256 of "<timestamp>.<payload>".
     */
    public static function computeSignature(string $secret, string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /**
     * Build a BreezeDoc-Signature header value, for example to test a webhook handler.
     *
     * @param string|array<string> $secrets One secret, or several (one v1 part each)
     */
    public static function generateSignatureHeader($secrets, string $payload, ?int $timestamp = null): string
    {
        $timestamp = (string) ($timestamp ?? time());
        $header = 't=' . $timestamp;
        foreach ((array) $secrets as $secret) {
            $header .= ',v1=' . self::computeSignature((string) $secret, $timestamp, $payload);
        }

        return $header;
    }
}
