<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\ApiRequestFactory;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class BasicTest extends TestCase
{
    /** @var UriInterface */
    private $uri;

    public function setUp(): void
    {
        parent::setUp();
        $this->uri = new Uri('http://api.example.com');
    }

    public function testApiHost(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user']);
        $this->assertEquals(['api.example.com'], $request->getHeader('Host'));
    }

    public function testResourceInUrl(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user']);
        $uri = $request->getUri();

        $this->assertEquals('/user/', $uri->getPath());
        $this->assertStringNotContainsString('resource=', $uri->getQuery());
    }

    public function testIdInUrl(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user', 'id' => 1337]);
        $uri = $request->getUri();

        $this->assertStringStartsWith('/user/1337', $uri->getPath());
        $this->assertStringNotContainsString('id=', $uri->getQuery());
    }

    public function testReferer(): void
    {
        $response = ResponseFactory::create()
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{}'));

        $client = ClientFactory::create();
        $client
            ->getMockHandler()
            ->append($response);

        $client->execute(['resource' => 'user']);
        $request = $client
            ->getMockHandler()
            ->getLastRequest();

        $this->assertArrayHasKey('Referer', $request->getHeaders(), 'Referer not set');
        $this->assertStringStartsWith('file://', $request->getHeaderLine('Referer'));
        $this->assertStringContainsString('phpunit', $request->getHeaderLine('Referer'));
    }

    public function testCacheBuster(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user', 'id' => 1337, 'cache' => false]);
        $queryString = $request
            ->getUri()
            ->getQuery();

        $this->assertStringNotContainsString('cache', $queryString);
        $this->assertStringContainsString('_=', $queryString);
        $this->assertRegExp('#_=\d+#', $queryString);
    }
}
