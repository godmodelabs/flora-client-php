<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Psr\Http\Message\RequestInterface;

class RequestFactory
{
    public static function create(string $method, $uri): RequestInterface
    {
        return (new \Http\Factory\Guzzle\RequestFactory())
            ->createRequest($method, $uri);
    }
}
