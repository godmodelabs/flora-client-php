<?php declare(strict_types=1);

namespace Flora\Client\Test;

use GuzzleHttp\Psr7\HttpFactory as GuzzleResponseFactory;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory
{
    public static function create(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return (new GuzzleResponseFactory())
            ->createResponse($code, $reasonPhrase);
    }
}
