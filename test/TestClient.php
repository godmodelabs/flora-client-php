<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\Client as FloraClient;
use GuzzleHttp\Handler\MockHandler;

class TestClient extends FloraClient
{
    /** @var MockHandler */
    private $mockHandler;

    public function __construct($url, array $opts = [])
    {
        parent::__construct($url, $opts);
        $this->mockHandler = $opts['mockHandler'];
    }

    public function getMockHandler(): MockHandler
    {
        return $this->mockHandler;
    }
}
