<?php declare(strict_types=1);

namespace Flora\Client\Test;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;

class ClientFactory
{
    public static function create(array $options = []): TestClient
    {
        $mockHandler = new MockHandler();
        $httpClient = new GuzzleClient(['handler' => $mockHandler]);

        $opts = array_merge([], $options, [
            'httpClient' => $httpClient,
            'mockHandler' => $mockHandler
        ]);

        return new TestClient('http://api.example.com', $opts);
    }
}
