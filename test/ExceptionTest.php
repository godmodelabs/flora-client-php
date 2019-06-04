<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Exception\BadRequestException;
use Flora\Exception\ForbiddenException;
use Flora\Exception\ImplementationException;
use Flora\Exception\NotFoundException;
use Flora\Exception\RuntimeException;
use Flora\Exception\ServerException;
use Flora\Exception\ServiceUnavailableException;
use Flora\Exception\TransferException;
use Flora\Exception\UnauthorizedException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\{Request, Response};

class ExceptionTest extends FloraClientTest
{
    /**
     * @param string $exceptionClass
     * @param string $message
     * @param string $responseFile
     * @dataProvider exceptionDataProvider
     */
    public function testRequestExceptions(string $exceptionClass, string $message, string $responseFile): void
    {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($message);

        $response = $this->getHttpResponseFromFile($responseFile);
        $this->mockHandler->append($response);

        $this->client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testFallbackException(): void
    {
        $this->expectException(RuntimeException::class);

        $response = $this->getHttpResponseFromFile(__DIR__ . '/_files/unknown.json');
        $this->mockHandler->append($response);

        $this->client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testHttpRuntimeExceptions(): void
    {
        $this->expectException(TransferException::class);

        $this->mockHandler->append(
            new RequestException(
            'Cannot connect to server',
                new Request('GET', 'http://non-existent.api.localhost/user/id')
            )
        );

        $this->client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testResourceParameterRequiredException(): void
    {
        $this->expectException(ImplementationException::class);
        $this->expectExceptionMessage('Resource must be set');

        $this->mockHandler->append(new Response());

        $this->client->execute([]);
    }

    public function exceptionDataProvider(): array
    {
        return [
            [BadRequestException::class, 'Something went wrong', __DIR__ . '/_files/badrequest.json'],
            [UnauthorizedException::class, 'Authentication required', __DIR__ . '/_files/unauthorized.json'],
            [ForbiddenException::class, 'You\'re not allowed to access this item', __DIR__ . '/_files/forbidden.json'],
            [NotFoundException::class, 'Item not found', __DIR__ . '/_files/notfound.json'],
            [ServerException::class, 'Something bad happened', __DIR__ . '/_files/server.json'],
            [ServiceUnavailableException::class, 'Please try again later', __DIR__ . '/_files/serviceunavailable.json']
        ];
    }
}
