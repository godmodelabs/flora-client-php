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

    public function testActionParameter()
    {
        // non-retrieve actions are transmitted with HTTP POST request (if not overridden by httpMethod parameter)
        $this->client->execute(['resource' => 'user', 'action' => 'awesomeAction']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('action=awesomeAction', $request->getBody());
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
}
