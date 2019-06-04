<?php declare(strict_types=1);

namespace Flora\Client\Test;

use GuzzleHttp\Psr7\Response;

class RequestMethodTest extends FloraClientTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], '{}'));
    }

    /**
     * @param array $params
     * @param string $message
     * @dataProvider defaultHttpMethodDataProvider
     */
    public function testHttpGetMethod(array $params, string $message): void
    {
        $this->client->execute($params);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('GET', $request->getMethod(), $message);
    }

    public function testHttpPostMethodForNonRetrieveActions(): void
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337, 'action' => 'foobar']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
    }

    public function testHttpMethodParameterOverwrite(): void
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337, 'httpMethod' => 'POST']);
        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
    }

    public function testHttpPostMethodForLongQueryStrings(): void
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
        $this->assertStringContainsString('select=', (string) $request->getBody(), 'POST body doesn\'t contain parameters');
    }

    public function testHttpPostMethodForJsonData(): void
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

    public function defaultHttpMethodDataProvider(): array
    {
        return [
            [['resource' => 'user', 'id' => 1337], 'Use GET method fallback if action parameter is empty'],
            [['resource' => 'user', 'id' => 1337, 'action' => 'retrieve'], 'Use GET method for retrieve actions']
        ];
    }
}
