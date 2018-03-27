<?php

namespace Flora\Client\Test;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class ParameterTest extends FloraClientTest
{
    public function setUp()
    {
        parent::setUp();
        $this->mockHandler->append(new Response());
    }

    /**
     * @dataProvider parameters
     * @param string        $name      Parameter name
     * @param string|int    $value     Parameter value
     * @param string        $encoded   URL-encoded parameter value
     */
    public function testRequestParameter($name, $value, $encoded)
    {
        $this->client->execute(['resource' => 'user', $name => $value]);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals($name . '=' . $encoded, $request->getUri()->getQuery());
    }

    public function parameters()
    {
        return [
            ['select', 'id,address.city,comments(order=ts:desc)[id,body]', 'id%2Caddress.city%2Ccomments%28order%3Dts%3Adesc%29%5Bid%2Cbody%5D'],
            ['filter', 'address[country.iso2=DE AND city=Munich]', 'address%5Bcountry.iso2%3DDE+AND+city%3DMunich%5D'],
            ['order', 'lastname:asc,firstname:desc', 'lastname%3Aasc%2Cfirstname%3Adesc'],
            ['limit', 15, 15],
            ['page', 2, 2],
            ['search', 'full text search', 'full+text+search']
        ];
    }

    public function testDefaultActionParameter()
    {
        $this->client->execute(['resource' => 'user', 'action' => 'retrieve']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertNotContains('action=', $request->getUri()->getQuery(), 'action=retrieve should not be transmitted');
    }

    public function testSendRandomDataAsJson()
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

    public function testFormatParameter()
    {
        $this->client->execute([
            'resource'  => 'user',
            'id'        => 1337,
            'format'    => 'image'
        ]);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('/user/1337.image', $request->getUri()->getPath());
        $this->assertNotContains('format=', $request->getUri()->getQuery());
    }

    public function testParameterOrder()
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

    public function testAuthenticateParameter()
    {
        /** @var \Flora\Auth\Provider $authProviderMock */
        $authProviderMock = $this->getMockBuilder('\\Flora\\Auth\\Provider')
            ->disableOriginalConstructor()
            ->setMethods(['authenticate'])
            ->getMock();

        $authProviderMock
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->callback(function ($request) {
                return $request instanceof RequestInterface;
            }))
            ->will($this->returnValue(new Request('GET', 'http://api.example.com/')));

        $this->client
            ->setAuthProvider($authProviderMock)
            ->execute([
                'resource'      => 'user',
                'id'            => 1337,
                'authenticate'  => true
            ]);
    }

    public function testDefaultParameter()
    {
        $this->client
            ->setDefaultParams(['portalId' => 1])
            ->execute(['resource' => 'user', 'id' => 1337]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertContains('portalId=1', $request->getUri()->getQuery());
    }

    public function testOverwriteDefaultParameter()
    {
        $this->client
            ->setDefaultParams(['portalId' => 1])
            ->execute(['resource' => 'user', 'id' => 1337, 'portalId' => 4711]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertContains('portalId=4711', $request->getUri()->getQuery());
    }

    /**
     * @param string $param
     * @throws \Flora\Exception
     * @dataProvider forceGetParamProvider
     */
    public function testDefaultGetParameters($param)
    {
        $this->client
            ->execute([
                'resource' => 'article',
                'filter' => str_repeat('foo', 2048),
                $param => 'test'
            ]);

        $request = $this->mockHandler->getLastRequest();
        $body = (string) $request->getBody();

        $this->assertContains("$param=test", $request->getUri()->getQuery());
        $this->assertNotEmpty($body);
        $this->assertNotContains("$param=test", $body);
    }

    public function testForceGetParameter()
    {
        $this->client
            ->setDefaultParams(['client_id' => 1])
            ->setForceGetParams(['foobar'])
            ->execute([
                'resource' => 'article',
                'filter' => str_repeat('foo', 2048),
                'foobar' => 1
            ]);

        $this->assertContains('foobar=1', $this->mockHandler->getLastRequest()->getUri()->getQuery());
    }

    public function testJsonForceGetParameter()
    {
        $this->client
            ->setDefaultParams(['client_id' => 1])
            ->setForceGetParams(['client_id'])
            ->execute(['resource' => 'article', 'data' => str_repeat('foo', 2048)]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertContains('client_id=1', $request->getUri()->getQuery());
        $this->assertNotContains('client_id=1', (string) $request->getBody());
    }

    /**
     * @return array
     */
    public function forceGetParamProvider()
    {
        return [
            ['client_id'],
            ['action'],
            ['access_token']
        ];
    }
}
