<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Exception\NotFoundException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;

class ResponseTest extends FloraClientTest
{
    public function testJsonResponse(): void
    {
        $expectedPayload = (object) [
            'meta'  => (object) [],
            'data'  => (object) [
                'id'        => 1337,
                'firstname' => 'John',
                'lastname'  => 'Doe'
            ],
            'error' => null,
            'cursor'=> null
        ];

        $this->mockHandler->append($this->getHttpResponseFromFile(__DIR__ . '/_files/json.json'));
        $response = $this->client->execute(['resource' => 'user', 'id' => 1337]);

        $this->assertEquals($expectedPayload, $response);
    }

    public function testNonJsonResponse(): void
    {
        $responseBody = new Stream(fopen('php://memory', 'wb+'));
        $responseBody->write('image-content');
        $response = new Response(200, ['Content-Type' => 'image/jpeg'], $responseBody);

        $this->mockHandler->append($response);
        $response = $this->client->execute(['resource' => 'user', 'id' => 1337, 'format' => 'image']);

        $this->assertEquals('image-content', $response);
    }

    public function testNonJsonErrorResponse(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Not Found');

        $responseBody = new Stream(fopen('php://memory', 'wb+'));
        $responseBody->write('image-content');
        $response = new Response(404, ['Content-Type' => 'text/html'], $responseBody);

        $this->mockHandler->append($response);
        $this->client->execute(['resource' => 'user', 'id' => 1337, 'format' => 'image']);
    }
}
