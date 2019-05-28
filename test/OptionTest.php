<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Client;
use GuzzleHttp\Psr7\Response;

class OptionTest extends FloraClientTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->mockHandler->append(new Response());
    }

    public function testDefaultHttpRequestTimeout(): void
    {
        $this->client->execute(['resource' => 'user', 'id' => 1337]);
        /** @var array $options */
        $options = $this->mockHandler->getLastOptions();

        $this->assertArrayHasKey('timeout', $options);
        $this->assertEquals(30, $options['timeout'], 'Default request timeout not set');
    }

    public function testHttpRequestTimeoutOption(): void
    {
        $httpClient = new \GuzzleHttp\Client(['handler' => $this->mockHandler]);
        $client = new Client('http://api.example.com/', [
            'httpClient' => $httpClient,
            'httpOptions'=> ['timeout' => 5]
        ]);

        $client->execute(['resource' => 'user', 'id' => 1337]);
        /** @var array $options */
        $options = $this->mockHandler->getLastOptions();

        $this->assertEquals(5, $options['timeout'], 'Request timeout not set correctly');
    }
}
