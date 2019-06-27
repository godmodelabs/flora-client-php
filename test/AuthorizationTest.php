<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora;
use Flora\AuthProviderInterface;
use Flora\Exception\ExceptionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class AuthorizationTest extends TestCase
{
    /** @var TestClient */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $response = ResponseFactory::create()
            ->withHeader('Content-Type', 'application/json')
            ->withBody(StreamFactory::create('{}'));

        $this->client = ClientFactory::create();
        $this->client
            ->getMockHandler()
            ->append($response);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testNoProviderConfiguredException(): void
    {
        $this->expectException(Flora\Exception\ImplementationException::class);
        $this->expectExceptionMessage('Auth provider is not configured');

        $this->client->execute([
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
        $this->client
            ->setAuthProvider(new BasicAuthentication('johndoe', 'secret'))
            ->execute([
                'resource'  => 'user',
                'id'        => 1337,
                'auth'      => true
            ]);

        $request = $this->client
            ->getMockHandler()
            ->getLastRequest();

        $this->assertTrue($request->hasHeader('Authorization'), 'Authorization header not available');
        $this->assertEquals(['am9obmRvZTpzZWNyZXQ='], $request->getHeader('Authorization'));
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

        $this->client
            ->setDefaultParams(['client_id' => 'test'])
            ->setAuthProvider($authProviderMock)
            ->execute([
                'resource'  => 'user',
                'id'        => 1337,
                'auth'      => true
            ]);

        $querystring = $this->client
            ->getMockHandler()
            ->getLastRequest()
            ->getUri()
            ->getQuery();

        $this->assertStringContainsString('client_id=test', $querystring);
        $this->assertStringContainsString('access_token=x.y.z', $querystring);
    }
}
