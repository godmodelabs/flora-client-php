<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\ApiRequestFactory;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Flora\AuthProviderInterface;
use Psr\Http\Message\UriInterface;

class ParameterTest extends TestCase
{
    /** @var UriInterface */
    private $uri;

    public function setUp(): void
    {
        parent::setUp();
        $this->uri = new Uri('http://api.example.com');
    }

    /**
     * @dataProvider parameters
     * @param string        $name      Parameter name
     * @param string|int    $value     Parameter value
     * @param string        $encoded   URL-encoded parameter value
     */
    public function testRequestParameter($name, $value, $encoded): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user', $name => $value]);
        self::assertEquals($name . '=' . $encoded, $request->getUri()->getQuery());
    }

    public static function parameters(): array
    {
        return [
            ['select', 'id,address.city,comments(order=ts:desc)[id,body]', 'id%2Caddress.city%2Ccomments%28order%3Dts%3Adesc%29%5Bid%2Cbody%5D'],
            ['select', ['id', 'address' => ['city'], 'comments' => ['id', 'body']], 'id%2Caddress.city%2Ccomments%5Bid%2Cbody%5D'],
            ['filter', 'address[country.iso2=DE AND city=Munich]', 'address%5Bcountry.iso2%3DDE+AND+city%3DMunich%5D'],
            ['order', 'lastname:asc,firstname:desc', 'lastname%3Aasc%2Cfirstname%3Adesc'],
            ['limit', 15, 15],
            ['page', 2, 2],
            ['search', 'full text search', 'full+text+search']
        ];
    }

    public function testDefaultActionParameter(): void
    {
        $request = (new ApiRequestFactory($this->uri))->create(['resource' => 'user', 'action' => 'retrieve']);
        self::assertStringNotContainsString('action=', $request->getUri()->getQuery(), 'action=retrieve should not be transmitted');
    }

    public function testSendRandomDataAsJson(): void
    {
        $request = (new ApiRequestFactory($this->uri))
            ->create([
                'resource'  => 'article',
                'action'    => 'create',
                'data'      => [
                    'title' => 'Lorem Ipsum',
                    'author'=> ['id'=> 1337]
                ]
            ]);

        self::assertEquals(['application/json'], $request->getHeader('Content-Type'));
        self::assertEquals('{"title":"Lorem Ipsum","author":{"id":1337}}', (string) $request->getBody());
    }

    public function testFormatParameter(): void
    {
        $request = (new ApiRequestFactory($this->uri))
            ->create([
                'resource'  => 'user',
                'id'        => 1337,
                'format'    => 'image'
            ]);

        self::assertEquals('/user/1337.image', $request->getUri()->getPath());
        self::assertStringNotContainsString('format=', $request->getUri()->getQuery());
    }

    public function testParameterOrder(): void
    {
        $expectedQueryString =
            'filter=address.country.iso2%3DAT'
            . '&limit=10'
            . '&order=lastname%3Adesc'
            . '&page=3'
            . '&search=John'
            . '&select=id%2Cfirstname%2Clastname';

        $request = (new ApiRequestFactory($this->uri))
            ->create([
                'resource'  => 'user',
                'search'    => 'John',
                'page'      => 3,
                'limit'     => 10,
                'order'     => 'lastname:desc',
                'select'    => 'id,firstname,lastname',
                'filter'    => 'address.country.iso2=AT'
            ]);

        self::assertEquals($expectedQueryString, $request->getUri()->getQuery());
    }

    public function testAuthorizeParameter(): void
    {
        /** @var MockObject|AuthProviderInterface $authProviderMock */
        $authProviderMock = $this->getMockBuilder(AuthProviderInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['auth'])
            ->getMock();

        $authProviderMock
            ->expects($this->once())
            ->method('auth')
            ->with($this->isInstanceOf(RequestInterface::class))
            ->willReturn(RequestFactory::create('GET', 'http://api.example.com/'));

        $request = (new ApiRequestFactory($this->uri, $authProviderMock))
            ->create([
                'resource'  => 'user',
                'id'        => 1337,
                'auth'      => true
            ]);

        self::assertStringNotContainsString(
            'auth=',
            $request->getUri()->getQuery(),
            'auth parameter must be removed from querystring'
        );
    }

    public function testDefaultParameter(): void
    {
        $request = (new ApiRequestFactory($this->uri, null, ['portalId' => 1]))
            ->create(['resource' => 'user', 'id' => 1337]);

        self::assertStringContainsString('portalId=1', $request->getUri()->getQuery());
    }

    public function testOverwriteDefaultParameter(): void
    {
        $request = (new ApiRequestFactory($this->uri, null, ['portalId' => 1]))
            ->create(['resource' => 'user', 'id' => 1337, 'portalId' => 4711]);

        self::assertStringContainsString('portalId=4711', $request->getUri()->getQuery());
    }

    /**
     * @param string $param
     * @dataProvider forceGetParamProvider
     */
    public function testDefaultGetParameters(string $param): void
    {
        $response = ResponseFactory::create()
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{}'));

        $client = ClientFactory::create();
        $mockHandler = $client->getMockHandler();
        $mockHandler->append($response);

        $client->execute([
            'resource' => 'article',
            'filter' => str_repeat('foo', 2048),
            $param => 'test'
        ]);

        $request = $mockHandler->getLastRequest();
        $body = (string) $request->getBody();

        self::assertStringContainsString("$param=test", $request->getUri()->getQuery());
        self::assertNotEmpty($body);
        self::assertStringNotContainsString("$param=test", $body);
    }

    public function testForceGetParameter(): void
    {
        $request = (new ApiRequestFactory($this->uri, null, ['client_id' => 1], ['foobar']))
            ->create([
                'resource' => 'article',
                'filter' => str_repeat('foo', 2048),
                'foobar' => 1
            ]);

        self::assertStringContainsString('foobar=1', $request->getUri()->getQuery());
    }

    public function testJsonForceGetParameter(): void
    {
        $request = (new ApiRequestFactory($this->uri, null, ['client_id' => 1], ['client_id']))
            ->create(['resource' => 'article', 'data' => str_repeat('foo', 2048)]);

        self::assertStringContainsString('client_id=1', $request->getUri()->getQuery());
        self::assertStringNotContainsString('client_id=1', $request->getBody()->getContents());
    }

    public static function forceGetParamProvider(): array
    {
        return [
            ['client_id'],
            ['action'],
            ['access_token']
        ];
    }
}
