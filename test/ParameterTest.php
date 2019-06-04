<?php declare(strict_types=1);

namespace Flora\Client\Test;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Flora\AuthProviderInterface;
use ReflectionException;

class ParameterTest extends FloraClientTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], '{}'));
    }

    /**
     * @dataProvider parameters
     * @param string        $name      Parameter name
     * @param string|int    $value     Parameter value
     * @param string        $encoded   URL-encoded parameter value
     */
    public function testRequestParameter($name, $value, $encoded): void
    {
        $this->client->execute(['resource' => 'user', $name => $value]);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals($name . '=' . $encoded, $request->getUri()->getQuery());
    }

    public function parameters(): array
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
        $this->client->execute(['resource' => 'user', 'action' => 'retrieve']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertStringNotContainsString('action=', $request->getUri()->getQuery(), 'action=retrieve should not be transmitted');
    }

    public function testSendRandomDataAsJson(): void
    {
        $this->client->execute([
            'resource'  => 'article',
            'action'    => 'create',
            'data'      => [
                'title' => 'Lorem Ipsum',
                'author'=> ['id'=> 1337]
            ]
        ]);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals(['application/json'], $request->getHeader('Content-Type'));
        $this->assertEquals('{"title":"Lorem Ipsum","author":{"id":1337}}', (string) $request->getBody());
    }

    public function testFormatParameter(): void
    {
        $this->client->execute([
            'resource'  => 'user',
            'id'        => 1337,
            'format'    => 'image'
        ]);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('/user/1337.image', $request->getUri()->getPath());
        $this->assertStringNotContainsString('format=', $request->getUri()->getQuery());
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

        $this->client->execute([
            'resource'  => 'user',
            'search'    => 'John',
            'page'      => 3,
            'limit'     => 10,
            'order'     => 'lastname:desc',
            'select'    => 'id,firstname,lastname',
            'filter'    => 'address.country.iso2=AT'
        ]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals($expectedQueryString, $request->getUri()->getQuery());
    }

    /**
     * @throws ReflectionException
     */
    public function testAuthorizeParameter(): void
    {
        /** @var MockObject|AuthProviderInterface $authProviderMock */
        $authProviderMock = $this->getMockBuilder(AuthProviderInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['auth'])
            ->getMock();

        $authProviderMock
            ->expects($this->once())
            ->method('auth')
            ->with($this->isInstanceOf(RequestInterface::class))
            ->willReturn(new Request('GET', 'http://api.example.com/'));

        $this->client
            ->setAuthProvider($authProviderMock)
            ->execute([
                'resource'  => 'user',
                'id'        => 1337,
                'auth'      => true
            ]);

        $querystring = $this->mockHandler->getLastRequest()->getUri()->getQuery();
        $this->assertStringNotContainsString('auth=', $querystring, 'auth parameter must be removed from querystring');
    }

    public function testDefaultParameter(): void
    {
        $this->client
            ->setDefaultParams(['portalId' => 1])
            ->execute(['resource' => 'user', 'id' => 1337]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertStringContainsString('portalId=1', $request->getUri()->getQuery());
    }

    public function testOverwriteDefaultParameter(): void
    {
        $this->client
            ->setDefaultParams(['portalId' => 1])
            ->execute(['resource' => 'user', 'id' => 1337, 'portalId' => 4711]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertStringContainsString('portalId=4711', $request->getUri()->getQuery());
    }

    /**
     * @param string $param
     * @dataProvider forceGetParamProvider
     */
    public function testDefaultGetParameters(string $param): void
    {
        $this->client
            ->execute([
                'resource' => 'article',
                'filter' => str_repeat('foo', 2048),
                $param => 'test'
            ]);

        $request = $this->mockHandler->getLastRequest();
        $body = (string) $request->getBody();

        $this->assertStringContainsString("$param=test", $request->getUri()->getQuery());
        $this->assertNotEmpty($body);
        $this->assertStringNotContainsString("$param=test", $body);
    }

    public function testForceGetParameter(): void
    {
        $this->client
            ->setDefaultParams(['client_id' => 1])
            ->setForceGetParams(['foobar'])
            ->execute([
                'resource' => 'article',
                'filter' => str_repeat('foo', 2048),
                'foobar' => 1
            ]);

        $this->assertStringContainsString('foobar=1', $this->mockHandler->getLastRequest()->getUri()->getQuery());
    }

    public function testJsonForceGetParameter(): void
    {
        $this->client
            ->setDefaultParams(['client_id' => 1])
            ->setForceGetParams(['client_id'])
            ->execute(['resource' => 'article', 'data' => str_repeat('foo', 2048)]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertStringContainsString('client_id=1', $request->getUri()->getQuery());
        $this->assertStringNotContainsString('client_id=1', (string) $request->getBody());
    }

    public function forceGetParamProvider(): array
    {
        return [
            ['client_id'],
            ['action'],
            ['access_token']
        ];
    }
}
