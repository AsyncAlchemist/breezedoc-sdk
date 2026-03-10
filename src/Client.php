<?php

declare(strict_types=1);

namespace Breezedoc;

use Breezedoc\Api\Documents;
use Breezedoc\Api\Invoices;
use Breezedoc\Api\Recipients;
use Breezedoc\Api\Teams;
use Breezedoc\Api\Templates;
use Breezedoc\Api\Users;
use Breezedoc\Config\Configuration;
use Breezedoc\Http\HttpClientFactory;
use Breezedoc\Http\RequestBuilder;
use Psr\Http\Client\ClientInterface;

/**
 * Main Breezedoc API client.
 *
 * This is the primary entry point for interacting with the Breezedoc API.
 */
class Client
{
    private Configuration $config;
    private ClientInterface $httpClient;
    private RequestBuilder $requestBuilder;

    private ?Users $users = null;
    private ?Documents $documents = null;
    private ?Templates $templates = null;
    private ?Recipients $recipients = null;
    private ?Invoices $invoices = null;
    private ?Teams $teams = null;

    /**
     * Create a new Breezedoc client.
     *
     * @param string|Configuration $config API token or Configuration instance
     * @param ClientInterface|null $httpClient Optional PSR-18 HTTP client
     */
    public function __construct($config, ?ClientInterface $httpClient = null)
    {
        if (is_string($config)) {
            $this->config = new Configuration($config);
        } else {
            $this->config = $config;
        }

        $this->httpClient = HttpClientFactory::createHttpClient($httpClient);
        $this->requestBuilder = new RequestBuilder(
            $this->config,
            HttpClientFactory::createRequestFactory(),
            HttpClientFactory::createStreamFactory()
        );
    }

    /**
     * Get the Users API resource.
     */
    public function users(): Users
    {
        if ($this->users === null) {
            $this->users = new Users($this->httpClient, $this->requestBuilder);
        }

        return $this->users;
    }

    /**
     * Get the Documents API resource.
     */
    public function documents(): Documents
    {
        if ($this->documents === null) {
            $this->documents = new Documents($this->httpClient, $this->requestBuilder);
        }

        return $this->documents;
    }

    /**
     * Get the Templates API resource.
     */
    public function templates(): Templates
    {
        if ($this->templates === null) {
            $this->templates = new Templates($this->httpClient, $this->requestBuilder);
        }

        return $this->templates;
    }

    /**
     * Get the Recipients API resource.
     */
    public function recipients(): Recipients
    {
        if ($this->recipients === null) {
            $this->recipients = new Recipients($this->httpClient, $this->requestBuilder);
        }

        return $this->recipients;
    }

    /**
     * Get the Invoices API resource.
     */
    public function invoices(): Invoices
    {
        if ($this->invoices === null) {
            $this->invoices = new Invoices($this->httpClient, $this->requestBuilder);
        }

        return $this->invoices;
    }

    /**
     * Get the Teams API resource.
     *
     * Note: Teams endpoints require an Agency plan subscription.
     */
    public function teams(): Teams
    {
        if ($this->teams === null) {
            $this->teams = new Teams($this->httpClient, $this->requestBuilder);
        }

        return $this->teams;
    }
}
