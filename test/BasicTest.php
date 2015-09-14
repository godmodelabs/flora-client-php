<?php

namespace Flora\Client\Test;

use GuzzleHttp\Psr7\Response;

class BasicTest extends FloraClientTest
{
    public function setUp()
    {
        parent::setUp();
        $this->mockHandler->append(new Response());
    }

    public function testApiHost()
    {
        $this->client->execute(['resource' => 'user']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals(['api.example.com'], $request->getHeader('Host'));
    }

    public function testResourceInUrl()
    {
        $this->client->execute(['resource' => 'user']);
        $uri = $this->mockHandler->getLastRequest()->getUri();

        $this->assertEquals('/user/', $uri->getPath());
        $this->assertNotContains('resource=', $uri->getQuery());
    }

    public function testIdInUrl()
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337]);
        $uri = $this->mockHandler->getLastRequest()->getUri();

        $this->assertStringStartsWith('/user/1337', $uri->getPath());
        $this->assertNotContains('id=', $uri->getQuery('id'));
    }

    public function testReferer()
    {
        $this->client->execute(['resource' => 'user']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertArrayHasKey('Referer', $request->getHeaders(), 'Referer not set');
        $this->assertStringStartsWith('file://', $request->getHeaderLine('Referer'));
        $this->assertContains('phpunit', $request->getHeaderLine('Referer'));
    }

    public function testCacheBuster()
    {
        $this->client->execute(['resource' => 'user', 'cache' => false]);
        $queryString = $this->mockHandler->getLastRequest()->getUri()->getQuery();

        $this->assertNotContains('cache', $queryString);
        $this->assertContains('_=', $queryString);
        $this->assertRegExp('#_=\d+#', $queryString);
    }
}
