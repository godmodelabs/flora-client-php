<?php

namespace Flora\Client\Test;

use Flora\Client as FloraClient;
use \GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class ExceptionTest extends FloraClientTest
{
    /**
     * @dataProvider exceptions
     * @param string $exception
     * @param string $message
     * @throws \Flora\Exception
     */
    public function testRequestExceptions($exception, $message)
    {
        $this->setExpectedException('\\Flora\\Exception\\' . $exception, $message);

        $file = strtolower($exception) . '.json';
        $response = $this->getHttpResponseFromFile($file);
        $this->mockHandler->append($response);

        $this->client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testFallbackException()
    {
        $this->setExpectedException('\\Flora\\Exception', 'Fallback message');

        $file = strtolower('unknown') . '.json';
        $response = $this->getHttpResponseFromFile($file);
        $this->mockHandler->append($response);

        $this->client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testHttpRuntimeExceptions()
    {
        $this->setExpectedException('\\Flora\\Exception');

        $this->mockHandler->append(
            new RequestException(
            'Cannot connect to server',
            new Request('GET', 'http://non-existent.api.localhost/user/id')
            )
        );

        $this->client->execute(['resource' => 'user', 'id' => 1337]);
    }

    public function testResourceParameterRequiredException()
    {
        $this->setExpectedException('\\Flora\\Exception', 'Resource must be set');
        $this->mockHandler->append(new Response());

        $this->client->execute([]);
    }

    public function exceptions()
    {
        return [
            ['BadRequest', 'Something went wrong'],
            ['Unauthorized', 'Authentication required'],
            ['Forbidden', 'You\'re not allowed to access this item'],
            ['NotFound', 'Item not found'],
            ['Server', 'Something bad happened'],
            ['ServiceUnavailable', 'Please try again later']
        ];
    }
}
