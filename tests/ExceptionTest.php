<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Exception\BadRequestException;
use Flora\Exception\ExceptionInterface as FloraException;
use Flora\Exception\ForbiddenException;
use Flora\Exception\ImplementationException;
use Flora\Exception\NotFoundException;
use Flora\Exception\RuntimeException;
use Flora\Exception\ServerException;
use Flora\Exception\ServiceUnavailableException;
use Flora\Exception\TransferException;
use Flora\Exception\UnauthorizedException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\{Request};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ExceptionTest extends TestCase
{
    /**
     * @param string $exceptionClass
     * @param string $message
     * @param ResponseInterface $response
     * @dataProvider requestExceptionDataProvider
     */
    public function testRequestExceptions(string $exceptionClass, string $message, ResponseInterface $response): void
    {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($message);

        $client = ClientFactory::create();
        $client
            ->getMockHandler()
            ->append($response);

        $client->execute(['resource' => 'user', 'id' => 1337]);
    }

    /**
     * @param string $exceptionClass
     * @dataProvider exceptionClassDataProvider
     */
    public function testExceptionBaseClass(string $exceptionClass): void
    {
        self::assertInstanceOf(FloraException::class, new $exceptionClass());
    }

    public function testFallbackException(): void
    {
        $this->expectException(RuntimeException::class);

        $body = '{"meta":{},"data":null,"error":{"message":"Fallback message"},"cursor":null}';
        $response = ResponseFactory::create(418)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create($body));

        $client = ClientFactory::create();
        $client
            ->getMockHandler()
            ->append($response);

        $client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testHttpRuntimeExceptions(): void
    {
        $this->expectException(TransferException::class);

        $client = ClientFactory::create();
        $client
            ->getMockHandler()
            ->append(
                new RequestException(
                'Cannot connect to server',
                    new Request('GET', 'http://non-existent.api.localhost/user/id')
                )
            );

        $client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testResourceParameterRequiredException(): void
    {
        $this->expectException(ImplementationException::class);
        $this->expectExceptionMessage('Resource must be set');

        $client = ClientFactory::create();
        $client
            ->getMockHandler()
            ->append(ResponseFactory::create());

        $client->execute([]);
    }

    public function requestExceptionDataProvider(): array
    {
        return [
            [
                BadRequestException::class,
                'Something went wrong',
                ResponseFactory::create(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(StreamFactory::create('{"meta":{},"data":null,"error":{"message":"Something went wrong"},"cursor":null}'))
            ],
            [
                UnauthorizedException::class,
                'Authentication required',
                ResponseFactory::create(401)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(StreamFactory::create('{"meta":{},"data":null,"error":{"message":"Authentication required"},"cursor":null}'))
            ],
            [
                ForbiddenException::class,
                'You\'re not allowed to access this item',
                ResponseFactory::create(403)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(StreamFactory::create('{"meta":{},"data":null,"error":{"message":"You\'re not allowed to access this item"},"cursor":null}'))
            ],
            [
                NotFoundException::class,
                'Item not found',
                ResponseFactory::create(404)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(StreamFactory::create('{"meta":{},"data":null,"error":{"message":"Item not found"},"cursor":null}'))
            ],
            [
                ServerException::class,
                'Something bad happened',
                ResponseFactory::create(500)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(StreamFactory::create('{"meta":{},"data":null,"error":{"message":"Something bad happened"},"cursor":null}'))
            ],
            [
                ServiceUnavailableException::class,
                'Please try again later',
                ResponseFactory::create(503)
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(StreamFactory::create('{"meta":{},"data":null,"error":{"message":"Please try again later"},"cursor":null}'))
            ]
        ];
    }

    public function exceptionClassDataProvider(): array
    {
        return [
            [BadRequestException::class],
            [ForbiddenException::class],
            [ImplementationException::class],
            [NotFoundException::class],
            [ServerException::class],
            [ServiceUnavailableException::class],
            [TransferException::class],
            [UnauthorizedException::class],
        ];
    }
}
