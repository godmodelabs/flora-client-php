<?php declare(strict_types=1);

namespace Flora\Client\Test;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    /** @var TestClient */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $response = ResponseFactory::create()
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{}'));

        $this->client = ClientFactory::create();
        $this->client
            ->getMockHandler()
            ->append($response);
    }

    public function testApiHost(): void
    {
        $this->client->execute(['resource' => 'user']);
        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

        $this->assertEquals(['api.example.com'], $request->getHeader('Host'));
    }

    public function testResourceInUrl(): void
    {
        $this->client->execute(['resource' => 'user']);
        $uri = $this->client
            ->getMockHandler()
            ->getLastRequest()
            ->getUri();

        $this->assertEquals('/user/', $uri->getPath());
        $this->assertStringNotContainsString('resource=', $uri->getQuery());
    }

    public function testIdInUrl(): void
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337]);
        $uri = $this->client
            ->getMockHandler()
            ->getLastRequest()
            ->getUri();

        $this->assertStringStartsWith('/user/1337', $uri->getPath());
        $this->assertStringNotContainsString('id=', $uri->getQuery());
    }

    public function testReferer(): void
    {
        $this->client->execute(['resource' => 'user']);
        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

        $this->assertArrayHasKey('Referer', $request->getHeaders(), 'Referer not set');
        $this->assertStringStartsWith('file://', $request->getHeaderLine('Referer'));
        $this->assertStringContainsString('phpunit', $request->getHeaderLine('Referer'));
    }

    public function testCacheBuster(): void
    {
        $this->client->execute(['resource' => 'user', 'cache' => false]);
        $queryString = $this->client
            ->getMockHandler()
            ->getLastRequest()
            ->getUri()
            ->getQuery();

        $this->assertStringNotContainsString('cache', $queryString);
        $this->assertStringContainsString('_=', $queryString);
        $this->assertRegExp('#_=\d+#', $queryString);
    }
}
