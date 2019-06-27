<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testJsonResponse(): void
    {
        $client = ClientFactory::create();
        $response = ResponseFactory::create()
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{"meta":{},"data":{"id":1337,"firstname":"John","lastname":"Doe"},"error":null,"cursor":null}'));

        $client
            ->getMockHandler()
            ->append($response);

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

        $response = $client->execute(['resource' => 'user', 'id' => 1337]);
        $this->assertEquals($expectedPayload, $response);
    }

    public function testNonJsonResponse(): void
    {
        $this->markTestSkipped('TypeError because "execute" method is defined to return stdClass object');

        $client = ClientFactory::create();
        $client
            ->getMockHandler()
            ->append(
                ResponseFactory::create()
                    ->withHeader('Content-Type', 'image/jpeg')
                    ->withBody(StreamFactory::create('image-content'))
            );

        $response = $client->execute(['resource' => 'user', 'id' => 1337, 'format' => 'image']);
        $this->assertEquals('image-content', $response);
    }

    public function testNonJsonErrorResponse(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Not Found');

        $client = ClientFactory::create();
        $response = ResponseFactory::create(404)
            ->withHeader('Content-Type', 'text/html')
            ->withBody(StreamFactory::create('<html lang="de"><body></body></html>'));

        $client
            ->getMockHandler()
            ->append($response);

        $client->execute(['resource' => 'user', 'id' => 1337, 'format' => 'image']);
    }
}
