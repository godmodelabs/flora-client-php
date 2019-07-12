<?php declare(strict_types=1);

namespace Flora\Client\Test;

use PHPUnit\Framework\TestCase;

class OptionTest extends TestCase
{
    public function testDefaultHttpRequestTimeout(): void
    {
        $client = ClientFactory::create();
        $response = ResponseFactory::create(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{}'));

        $client
            ->getMockHandler()
            ->append($response);

        $client->execute(['resource' => 'user', 'id' => 1337]);
        $options = $client
            ->getMockHandler()
            ->getLastOptions();

        $this->assertArrayHasKey('timeout', $options);
        $this->assertEquals(30, $options['timeout'], 'Default request timeout not set');
    }

    public function testHttpRequestTimeoutOption(): void
    {
        $client = ClientFactory::create(['httpOptions'=> ['timeout' => 5]]);
        $mockHandler = $client->getMockHandler();
        $response = ResponseFactory::create(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{}'));

        $mockHandler->append($response);

        $client->execute(['resource' => 'user', 'id' => 1337]);

        $options = $mockHandler->getLastOptions();
        $this->assertEquals(5, $options['timeout'], 'Request timeout not set correctly');
    }
}
