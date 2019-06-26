<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Exception\NotFoundException;
use GuzzleHttp\Promise\RejectionException;
use GuzzleHttp\Psr7\Response;

class AsyncTest extends FloraClientTest
{
    public function testMethodExists(): void
    {
        $this->assertTrue(
            method_exists($this->client, 'executeAsync'),
            'executeAsync method does not exists'
        );
    }

    public function testRejectionIfResourceIsMissing(): void
    {
        $this->expectException(RejectionException::class);
        $this->expectExceptionMessage('Resource must be set');

        $this->client
            ->executeAsync([])
            ->wait();
    }

    public function testRejectionIfAuthProviderIsNotConfigured(): void
    {
        $this->expectException(RejectionException::class);
        $this->expectExceptionMessage('Auth provider is not configured');

        $this->client
            ->executeAsync([
                'resource' => 'article',
                'auth' => true
            ])
            ->wait();
    }

    public function testAsyncRequest(): void
    {
        $expectedResponse = (object) ['meta' => (object) [], 'data' => [], 'cursor' => (object) []];
        $body = json_encode($expectedResponse);
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], $body));

        $response = $this->client
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
        $response = new Response(404, ['Content-Type' => 'application/json'], json_encode($expectedResponse));
        $this->mockHandler->append($response);

        $this->client
            ->executeAsync(['resource' => 'article', 'id' => 1337])
            ->wait();
    }
}
