<?php declare(strict_types=1);

namespace Flora;

use Psr\Http\Message\RequestInterface;

interface AuthProviderInterface
{
    public function auth(RequestInterface $apiRequest): RequestInterface;
}
