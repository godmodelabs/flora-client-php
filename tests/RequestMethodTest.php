<?php declare(strict_types=1);

namespace Flora\Client\Test;

use PHPUnit\Framework\TestCase;

class RequestMethodTest extends TestCase
{
    /** @var TestClient */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = ClientFactory::create();
        $response = ResponseFactory::create()
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{}'));

        $this->client
            ->getMockHandler()
            ->append($response);
    }

    /**
     * @param array $params
     * @param string $message
     * @dataProvider defaultHttpMethodDataProvider
     */
    public function testHttpGetMethod(array $params, string $message): void
    {
        $this->client->execute($params);
        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

        $this->assertEquals('GET', $request->getMethod(), $message);
    }

    public function testHttpPostMethodForNonRetrieveActions(): void
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337, 'action' => 'foobar']);
        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
    }

    public function testHttpMethodParameterOverwrite(): void
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337, 'httpMethod' => 'POST']);
        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

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
        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

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
        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

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
