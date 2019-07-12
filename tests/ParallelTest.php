<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Exception\BadRequestException;
use PHPUnit\Framework\TestCase;
use stdClass;

class ParallelTest extends TestCase
{
    public function testMethodExists(): void
    {
        $this->assertTrue(
            method_exists(ClientFactory::create(), 'executeParallel'),
            'executeParallel method does not exists'
        );
    }

    public function testParallelRequestExecutionSuccess(): void
    {
        $client = ClientFactory::create();
        $mockHandler = $client->getMockHandler();

        $fooBody = (object) [
            'meta' => new stdClass(),
            'data' => [(object) ['id' => 1, 'name' => 'foo']],
            'cursor' => null
        ];
        $barBody = (object) [
            'meta' => new stdClass(),
            'data' => [(object) ['id' => 1, 'name' => 'bar']],
            'cursor' => null
        ];

        $mockHandler->append(
            ResponseFactory::create()
                ->withHeader('Content-Type', 'application/json')
                ->withBody(StreamFactory::create(json_encode($fooBody)))
        );
        $mockHandler->append(
            ResponseFactory::create()
                ->withHeader('Content-Type', 'application/json')
                ->withBody(StreamFactory::create(json_encode($barBody)))
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $responses = $client->executeParallel([
            ['resource' => 'foo', 'id' => 1],
            ['resource' => 'bar', 'id' => 1]
        ]);

        $this->assertEquals([$fooBody, $barBody], $responses);
    }

    public function testParallelRequestExecutionFail(): void
    {
        $errMsg = 'You\'re doing it wrong';

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage($errMsg);

        $client = ClientFactory::create();
        $mockHandler = $client->getMockHandler();

        $mockHandler->append(
            ResponseFactory::create()
                ->withHeader('Content-Type', 'application/json')
                ->withBody(StreamFactory::create(json_encode((object) [
                    'meta' => new stdClass(),
                    'data' => [(object) ['id' => 1, 'name' => 'foo']],
                    'cursor' => null
                ])))
        );
        $mockHandler->append(
            ResponseFactory::create(400)
                ->withHeader('Content-Type', 'application/json')
                ->withBody(StreamFactory::create(json_encode((object) [
                    'meta' => new stdClass(),
                    'data' => [],
                    'cursor' => null,
                    'error' => (object) ['message' => $errMsg]
                ])))
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $client->executeParallel([
            ['resource' => 'foo', 'id' => 1],
            ['resource' => 'bar', 'id' => 1]
        ]);
    }
}
