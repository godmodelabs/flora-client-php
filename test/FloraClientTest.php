<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Client as FloraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for Flora client tests
 */
abstract class FloraClientTest extends TestCase
{
    /**
     * @var FloraClient
     */
    protected $client;

    /**
     * @var MockHandler
     */
    protected $mockHandler;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockHandler  = new MockHandler();
        $httpClient         = new Client(['handler' => $this->mockHandler]);
        $this->client       = new FloraClient('http://api.example.com', ['httpClient' => $httpClient]);
    }

    /**
     * @param string $file
     * @return ResponseInterface
     */
    protected function getHttpResponseFromFile($file): ResponseInterface
    {
        $content = file_get_contents($file);
        $data = json_decode($content, false);

        $body = new Stream(fopen('php://memory', 'wb+'));
        $body->write($data->body);
        $body->rewind();

        return new Response($data->statusCode, (array) $data->headers, $body);
    }
}
