<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Exception\{ImplementationException, NotFoundException};
use GuzzleHttp\Promise\RejectionException;
use PHPUnit\Framework\TestCase;

class AsyncTest extends TestCase
{
    public function testMethodExists(): void
    {
        $client = ClientFactory::create();

        $this->assertTrue(
            method_exists($client, 'executeAsync'),
            'executeAsync method does not exists'
        );
    }

    public function testRejectionIfResourceIsMissing(): void
    {
        $this->expectException(ImplementationException::class);
        $this->expectExceptionMessage('Resource must be set');

        ClientFactory::create()
            ->executeAsync([])
            ->wait();
    }

    public function testRejectionIfAuthProviderIsNotConfigured(): void
    {
        $this->expectException(ImplementationException::class);
        $this->expectExceptionMessage('Auth provider is not configured');

        ClientFactory::create()
            ->executeAsync([
                'resource' => 'article',
                'auth' => true
            ])
            ->wait();
    }

    public function testAsyncRequest(): void
    {
        $client = ClientFactory::create();

        $expectedResponse = (object) ['meta' => (object) [], 'data' => [], 'cursor' => (object) []];
        $response = ResponseFactory::create()
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create(json_encode($expectedResponse)));

        $client
            ->getMockHandler()
            ->append($response);

        $response = $client
            ->executeAsync(['resource' => 'article'])
            ->wait();

        $this->assertEquals($expectedResponse, $response);
    }

    public function testAsyncRequestFail(): void
    {
        $message = 'Not found';

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage($message);

        $expectedResponse = (object) [
            'meta' => (object) [],
            'data' => [],
            'cursor' => (object) [],
            'error' => (object) ['message' => $message]
        ];

        $response = ResponseFactory::create(404)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create(json_encode($expectedResponse)));

        $client = ClientFactory::create();
        $client
            ->getMockHandler()
            ->append($response);

        $client
            ->executeAsync(['resource' => 'article', 'id' => 1337])
            ->wait();
    }
}
