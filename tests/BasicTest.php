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
        self::assertSame('api.example.com', $request->getHeaderLine('Host'));
    }

    public function testResourceInUrl(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user']);
        $uri = $request->getUri();

        self::assertSame('/user/', $uri->getPath());
        self::assertStringNotContainsString('resource=', $uri->getQuery());
    }

    public function testIdInUrl(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user', 'id' => 1337]);
        $uri = $request->getUri();

        self::assertStringStartsWith('/user/1337', $uri->getPath());
        self::assertStringNotContainsString('id=', $uri->getQuery());
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

        self::assertArrayHasKey('Referer', $request->getHeaders(), 'Referer not set');
        self::assertStringStartsWith('file://', $request->getHeaderLine('Referer'));
        self::assertStringContainsString('phpunit', $request->getHeaderLine('Referer'));
    }

    public function testCacheBuster(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user', 'id' => 1337, 'cache' => false]);
        $queryString = $request
            ->getUri()
            ->getQuery();

        self::assertStringNotContainsString('cache', $queryString);
        self::assertStringContainsString('_=', $queryString);
        self::assertMatchesRegularExpression('#_=\d+#', $queryString);
    }
}
