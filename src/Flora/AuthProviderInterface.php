<?php

namespace Flora;

use Psr\Http\Message\RequestInterface;

interface AuthProviderInterface
{
    /**
     * @param RequestInterface $apiRequest
     * @return RequestInterface
     */
    public function authorize(RequestInterface $apiRequest);
}
