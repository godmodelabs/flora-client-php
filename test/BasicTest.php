<?php declare(strict_types=1);

namespace Flora\Client\Test;

use GuzzleHttp\Psr7\Response;

class BasicTest extends FloraClientTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], '{}'));
    }

    public function testApiHost(): void
    {
        $this->client->execute(['resource' => 'user']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals(['api.example.com'], $request->getHeader('Host'));
    }

    public function testResourceInUrl(): void
    {
        $this->client->execute(['resource' => 'user']);
        $uri = $this->mockHandler->getLastRequest()->getUri();

        $this->assertEquals('/user/', $uri->getPath());
        $this->assertStringNotContainsString('resource=', $uri->getQuery());
    }

    public function testIdInUrl(): void
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337]);
        $uri = $this->mockHandler->getLastRequest()->getUri();

        $this->assertStringStartsWith('/user/1337', $uri->getPath());
        $this->assertStringNotContainsString('id=', $uri->getQuery());
    }

    public function testReferer(): void
    {
        $this->client->execute(['resource' => 'user']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertArrayHasKey('Referer', $request->getHeaders(), 'Referer not set');
        $this->assertStringStartsWith('file://', $request->getHeaderLine('Referer'));
        $this->assertStringContainsString('phpunit', $request->getHeaderLine('Referer'));
    }

    public function testCacheBuster(): void
    {
        $this->client->execute(['resource' => 'user', 'cache' => false]);
        $queryString = $this->mockHandler->getLastRequest()->getUri()->getQuery();

        $this->assertStringNotContainsString('cache', $queryString);
        $this->assertStringContainsString('_=', $queryString);
        $this->assertRegExp('#_=\d+#', $queryString);
    }
}
