<?php

namespace Flora\Client\Test;

use Flora\Client as FloraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Stream;

/**
 * Base class for Flora client tests
 */
abstract class FloraClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FloraClient
     */
    protected $client;

    /**
     * @var MockHandler
     */
    protected $mockHandler;

    public function setUp()
    {
        parent::setUp();

        $this->mockHandler  = new MockHandler();
        $httpClient         = new Client(['handler' => $this->mockHandler]);
        $this->client       = new FloraClient('http://api.example.com', ['httpClient' => $httpClient]);
    }

    /**
     * @param string $file
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function getHttpResponseFromFile($file)
    {
        $content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . $file);
        $data = json_decode($content);

        $body = new Stream(fopen('php://memory', 'wb+'));
        $body->write($data->body);

        $response = new Response($data->statusCode, (array) $data->headers, $body);

        return $response;
    }
}
