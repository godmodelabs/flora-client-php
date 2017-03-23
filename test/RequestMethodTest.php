<?php

namespace Flora\Client\Test;

use GuzzleHttp\Psr7\Response;

class RequestMethodTest extends FloraClientTest
{
    public function setUp()
    {
        parent::setUp();
        $this->mockHandler->append(new Response());
    }

    /**
     * @dataProvider defaultHttpMethodDataProvider
     * @param array $params
     * @param string $message
     */
    public function testHttpGetMethod(array $params, $message)
    {
        $this->client->execute($params);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('GET', $request->getMethod(), $message);
    }

    public function testHttpPostMethodForNonRetrieveActions()
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337, 'action' => 'foobar']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
    }

    public function testHttpMethodParameterOverwrite()
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337, 'httpMethod' => 'POST']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
    }

    public function testHttpPostMethodForLongQueryStrings()
    {
        $this->client->execute([
            'resource'  => 'article',
            'select'    => str_repeat('select', 150),
            'filter'    => str_repeat('filter', 150),
            'search'    => str_repeat('term', 150),
            'limit'     => 100,
            'page'      => 10
        ]);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertContains('select=', (string) $request->getBody(), 'POST body doesn\'t contain parameters');
    }

    public function testHttpPostMethodForJsonData()
    {
        $this->client->execute([
            'resource'  => 'article',
            'action'    => 'create',
            'data'      => ['title' => 'Lorem Ipsum']
        ]);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('action=create', $request->getUri()->getQuery());
    }

    public function defaultHttpMethodDataProvider()
    {
        return [
            [['resource' => 'user', 'id' => 1337], 'Use GET method fallback if action parameter is empty'],
            [['resource' => 'user', 'id' => 1337, 'action' => 'retrieve'], 'Use GET method for retrieve actions']
        ];
    }
}
