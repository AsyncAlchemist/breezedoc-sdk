<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Unit\Http;

use Breezedoc\Config\Configuration;
use Breezedoc\Http\RequestBuilder;
use Breezedoc\Tests\Unit\UnitTestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

class RequestBuilderTest extends UnitTestCase
{
    private Configuration $config;
    private RequestBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new Configuration('test-token');
        $this->builder = new RequestBuilder($this->config, new Psr17Factory(), new Psr17Factory());
    }

    public function testBuildGetRequest(): void
    {
        $request = $this->builder->build('GET', '/documents');

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://breezedoc.com/api/documents', (string) $request->getUri());
    }

    public function testBuildRequestWithAuthorizationHeader(): void
    {
        $request = $this->builder->build('GET', '/documents');

        $this->assertTrue($request->hasHeader('Authorization'));
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
    }

    public function testBuildRequestWithAcceptHeader(): void
    {
        $request = $this->builder->build('GET', '/documents');

        $this->assertTrue($request->hasHeader('Accept'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testBuildRequestWithContentTypeHeader(): void
    {
        $request = $this->builder->build('POST', '/documents', ['title' => 'Test']);

        $this->assertTrue($request->hasHeader('Content-Type'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testBuildRequestWithJsonBody(): void
    {
        $body = ['title' => 'Test Document', 'recipients' => []];
        $request = $this->builder->build('POST', '/documents', $body);

        $this->assertSame(json_encode($body), (string) $request->getBody());
    }

    public function testBuildRequestWithoutBodyForGet(): void
    {
        $request = $this->builder->build('GET', '/documents');

        $this->assertSame('', (string) $request->getBody());
    }

    public function testBuildRequestWithQueryParams(): void
    {
        $request = $this->builder->build('GET', '/documents', null, ['page' => 2, 'per_page' => 10]);

        $uri = $request->getUri();
        $this->assertSame('page=2&per_page=10', $uri->getQuery());
    }

    public function testBuildRequestWithEmptyQueryParams(): void
    {
        $request = $this->builder->build('GET', '/documents', null, []);

        $uri = $request->getUri();
        $this->assertSame('', $uri->getQuery());
    }

    public function testBuildRequestWithPathParams(): void
    {
        $request = $this->builder->build('GET', '/documents/123');

        $this->assertSame('https://breezedoc.com/api/documents/123', (string) $request->getUri());
    }

    public function testBuildRequestWithCustomBaseUrl(): void
    {
        $config = new Configuration('test-token');
        $config->setBaseUrl('https://custom.example.com/api');
        $builder = new RequestBuilder($config, new Psr17Factory(), new Psr17Factory());

        $request = $builder->build('GET', '/documents');

        $this->assertSame('https://custom.example.com/api/documents', (string) $request->getUri());
    }

    public function testBuildPostRequest(): void
    {
        $request = $this->builder->build('POST', '/documents', ['title' => 'Test']);

        $this->assertSame('POST', $request->getMethod());
    }

    public function testBuildPutRequest(): void
    {
        $request = $this->builder->build('PUT', '/invoices/123', ['status' => 'paid']);

        $this->assertSame('PUT', $request->getMethod());
    }

    public function testBuildPatchRequest(): void
    {
        $request = $this->builder->build('PATCH', '/invoices/123', ['status' => 'paid']);

        $this->assertSame('PATCH', $request->getMethod());
    }

    public function testBuildDeleteRequest(): void
    {
        $request = $this->builder->build('DELETE', '/invoices/123');

        $this->assertSame('DELETE', $request->getMethod());
    }

    public function testBuildRequestWithQueryParamsAndBody(): void
    {
        $request = $this->builder->build(
            'POST',
            '/documents',
            ['title' => 'Test'],
            ['send' => 'true']
        );

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('send=true', $request->getUri()->getQuery());
        $this->assertSame('{"title":"Test"}', (string) $request->getBody());
    }

    public function testPathWithLeadingSlash(): void
    {
        $request = $this->builder->build('GET', '/documents');
        $this->assertSame('https://breezedoc.com/api/documents', (string) $request->getUri());
    }

    public function testPathWithoutLeadingSlash(): void
    {
        $request = $this->builder->build('GET', 'documents');
        $this->assertSame('https://breezedoc.com/api/documents', (string) $request->getUri());
    }
}
