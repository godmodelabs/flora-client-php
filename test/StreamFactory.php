<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Http\Factory\Guzzle\StreamFactory as GuzzleStreamFactory;
use Psr\Http\Message\StreamInterface;

class StreamFactory
{
    public static function create(string $content = ''): StreamInterface
    {
        return (new GuzzleStreamFactory())
            ->createStream($content);
    }

    public static function createStreamFromFile(string $file): StreamInterface
    {
        return (new GuzzleStreamFactory())
            ->createStreamFromFile($file);
    }
}
