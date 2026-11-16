<?php

declare(strict_types=1);

namespace Breezedoc\V1;

use Breezedoc\Config\Configuration;
use Breezedoc\Http\HttpClientFactory;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\V1\Api\Account;
use Breezedoc\V1\Api\Documents;
use Breezedoc\V1\Api\Templates;
use Breezedoc\V1\Api\Webhooks;
use Breezedoc\V1\Models\TeamAccount;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;

/**
 * Client for the BreezeDoc Team API v1 (`https://breezedoc.com/api/v1`).
 *
 * The Team API v1 takes a team API key (`bd_live_...`), which a team owner creates in the
 * API settings with read scope or with read and write scope. It works with the documents
 * and templates of that one team. Team keys and webhooks need a team, which comes with the
 * Agency and Pro plans.
 *
 *     $team = Breezedoc::teamClient('bd_live_...');
 *     $draft = $team->documents()->createFromTemplate('tpl_123', [
 *         ['party' => 1, 'name' => 'Jordan', 'email' => 'jordan@example.com'],
 *     ], 'NDA', 'nda-jordan-001');
 *     $team->documents()->send($draft->getId(), [], 'nda-jordan-send-001');
 */
class TeamClient
{
    private ClientInterface $httpClient;
    private RequestBuilder $requestBuilder;

    private ?Account $account = null;
    private ?Templates $templates = null;
    private ?Documents $documents = null;
    private ?Webhooks $webhooks = null;

    /**
     * @param string|Configuration $config Team API key, or a Configuration with a team API key
     * @param ClientInterface|null $httpClient Optional PSR-18 HTTP client
     */
    public function __construct($config, ?ClientInterface $httpClient = null)
    {
        if (is_string($config)) {
            $config = Configuration::forTeamApiKey($config);
        }

        if (!$config->hasTeamApiKey()) {
            throw new InvalidArgumentException(
                'A team API key is required for the Team API v1. Call Configuration::setTeamApiKey($key).'
            );
        }

        $this->httpClient = HttpClientFactory::createHttpClient($httpClient);
        $this->requestBuilder = RequestBuilder::forTeamApi(
            $config,
            HttpClientFactory::createRequestFactory(),
            HttpClientFactory::createStreamFactory()
        );
    }

    /**
     * `GET /v1/me`: the team of the key, the owner's plan and the team usage.
     */
    public function me(): TeamAccount
    {
        if ($this->account === null) {
            $this->account = new Account($this->httpClient, $this->requestBuilder);
        }

        return $this->account->me();
    }

    public function templates(): Templates
    {
        if ($this->templates === null) {
            $this->templates = new Templates($this->httpClient, $this->requestBuilder);
        }

        return $this->templates;
    }

    public function documents(): Documents
    {
        if ($this->documents === null) {
            $this->documents = new Documents($this->httpClient, $this->requestBuilder);
        }

        return $this->documents;
    }

    public function webhooks(): Webhooks
    {
        if ($this->webhooks === null) {
            $this->webhooks = new Webhooks($this->httpClient, $this->requestBuilder);
        }

        return $this->webhooks;
    }
}
