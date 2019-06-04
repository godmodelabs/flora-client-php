<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\AuthProviderInterface;
use Psr\Http\Message\RequestInterface;

class BasicAuthentication implements AuthProviderInterface
{
    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @param string $username
     * @param string $password
     */
    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @inheritdoc
     */
    public function auth(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', base64_encode($this->username . ':' . $this->password));
    }
}
