<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora;
use Flora\AuthProviderInterface;
use Flora\Exception\ExceptionInterface;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{RequestInterface, UriInterface};

class AuthorizationTest extends TestCase
{
    /** @var UriInterface */
    private $uri;

    public function setUp(): void
    {
        parent::setUp();
        $this->uri = new Uri('http://api.example.com');
    }

    /**
     * @throws ExceptionInterface
     */
    public function testNoProviderConfiguredException(): void
    {
        $this->expectException(Flora\Exception\ImplementationException::class);
        $this->expectExceptionMessage('Auth provider is not configured');

        (new Flora\ApiRequestFactory($this->uri, null))
            ->create([
                'resource'  => 'user',
                'id'        => 1337,
                'auth'      => true
            ]);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testAuthorizationProviderInteraction(): void
    {
        $request = (new Flora\ApiRequestFactory(
            $this->uri,
            new BasicAuthentication('johndoe', 'secret'))
        )->create([
            'resource'  => 'user',
            'id'        => 1337,
            'auth'      => true
        ]);

        self::assertTrue($request->hasHeader('Authorization'), 'Authorization header not available');
        self::assertEquals(['am9obmRvZTpzZWNyZXQ='], $request->getHeader('Authorization'));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testAuthProviderRequestParameters(): void
    {
        /** @var MockObject|AuthProviderInterface $authProviderMock */
        $authProviderMock = $this->getMockBuilder(AuthProviderInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['auth'])
            ->getMock();

        $authProviderMock
            ->expects($this->once())
            ->method('auth')
            ->willReturnCallback(static function (RequestInterface $request) {
                $uri = $request->getUri();

                parse_str($uri->getQuery(), $params);
                $params['access_token'] = 'x.y.z';

                return $request->withUri($uri->withQuery(http_build_query($params)));
            });

        $request = (new Flora\ApiRequestFactory($this->uri, $authProviderMock, ['client_id' => 'test']))
            ->create([
                'resource'  => 'user',
                'id'        => 1337,
                'auth'      => true
            ]);

        $querystring = $request
            ->getUri()
            ->getQuery();

        self::assertStringContainsString('client_id=test', $querystring);
        self::assertStringContainsString('access_token=x.y.z', $querystring);
        self::assertStringNotContainsString('auth=1', $querystring);
    }
}
