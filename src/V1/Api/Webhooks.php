<?php

declare(strict_types=1);

namespace Breezedoc\V1\Api;

use Breezedoc\V1\Models\WebhookDelivery;
use Breezedoc\V1\Models\WebhookEndpoint;
use InvalidArgumentException;

/**
 * Team API v1: webhook endpoint management.
 *
 * Every webhook call needs a key with write scope. An endpoint must be a public HTTPS URL
 * on port 443; a team can have up to ten. Endpoints can also be managed on the Webhooks page
 * beside the team's API keys. To verify the requests that BreezeDoc sends, use
 * {@see \Breezedoc\Webhooks\WebhookVerifier}.
 */
class Webhooks extends AbstractTeamApi
{
    /**
     * `GET /v1/webhooks`: the team's webhook endpoints.
     *
     * @return array<WebhookEndpoint>
     */
    public function list(): array
    {
        return array_map([WebhookEndpoint::class, 'fromArray'], self::listItems($this->call('GET', '/webhooks')));
    }

    /**
     * `POST /v1/webhooks`: create an endpoint.
     *
     * The response holds the signing secret (`whsec_...`) once:
     * {@see WebhookEndpoint::getSecret()}. Store it.
     *
     * @param array<mixed> $events Event types (strings), for example
     *   {@see \Breezedoc\Webhooks\WebhookEventType::subscribable()}
     */
    public function create(string $url, array $events, ?string $idempotencyKey = null): WebhookEndpoint
    {
        if ($url === '') {
            throw new InvalidArgumentException('The webhook URL cannot be empty.');
        }

        if ($events === []) {
            throw new InvalidArgumentException('Select at least one webhook event.');
        }

        $eventTypes = [];
        foreach ($events as $event) {
            if (!is_string($event) || $event === '') {
                throw new InvalidArgumentException('Webhook events must be non-empty strings.');
            }
            $eventTypes[] = $event;
        }

        $response = $this->call(
            'POST',
            '/webhooks',
            ['url' => $url, 'events' => $eventTypes],
            [],
            $idempotencyKey
        );

        return WebhookEndpoint::fromArray(self::unwrap($response));
    }

    /**
     * `DELETE /v1/webhooks/{id}`: remove an endpoint. BreezeDoc stops sending to it at once.
     */
    public function destroy(string $id): void
    {
        $this->call('DELETE', '/webhooks/' . self::encodeId($id, 'webhook ID'));
    }

    /**
     * `POST /v1/webhooks/{id}/test`: queue a `webhook.test` event.
     *
     * @return array<string, mixed> The raw response (the API answers 202 Queued)
     */
    public function test(string $id, ?string $idempotencyKey = null): array
    {
        $path = '/webhooks/' . self::encodeId($id, 'webhook ID') . '/test';

        return $this->call('POST', $path, null, [], $idempotencyKey);
    }

    /**
     * `POST /v1/webhooks/{id}/rotate`: create a new signing secret.
     *
     * For 24 hours BreezeDoc signs each delivery with both the old and the new secret, so
     * the receiver can switch to the new secret in that time.
     */
    public function rotateSecret(string $id, ?string $idempotencyKey = null): WebhookEndpoint
    {
        $path = '/webhooks/' . self::encodeId($id, 'webhook ID') . '/rotate';

        return WebhookEndpoint::fromArray(self::unwrap($this->call('POST', $path, null, [], $idempotencyKey)));
    }

    /**
     * `GET /v1/webhooks/{id}/deliveries`: the delivery logs of an endpoint (kept for 30 days).
     *
     * @return array<WebhookDelivery>
     */
    public function deliveries(string $id): array
    {
        $response = $this->call('GET', '/webhooks/' . self::encodeId($id, 'webhook ID') . '/deliveries');

        return array_map([WebhookDelivery::class, 'fromArray'], self::listItems($response));
    }

    /**
     * `POST /v1/webhooks/{id}/deliveries/{delivery}/redeliver`: queue a redelivery with the
     * original event ID.
     *
     * @return array<string, mixed> The raw response (the API answers 202 Queued)
     */
    public function redeliver(string $id, string $deliveryId, ?string $idempotencyKey = null): array
    {
        $path = '/webhooks/' . self::encodeId($id, 'webhook ID')
            . '/deliveries/' . self::encodeId($deliveryId, 'delivery ID') . '/redeliver';

        return $this->call('POST', $path, null, [], $idempotencyKey);
    }
}
