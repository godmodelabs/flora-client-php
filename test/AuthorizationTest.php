<?php

namespace Flora\Client\Test;

use Flora;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class AuthorizationTest extends FloraClientTest
{
    public function setUp()
    {
        parent::setUp();
        $this->mockHandler->append(new Response());
    }

    /**
     * @throws Flora\Exception\ExceptionInterface
     */
    public function testNoProviderConfiguredException()
    {
        $this->expectException(Flora\Exception\ImplementationException::class);
        $this->expectExceptionMessage('Authorization provider is not configured');

        $this->client->execute([
            'resource'  => 'user',
            'id'        => 1337,
            'authorize' => true
        ]);
    }

    /**
     * @throws Flora\Exception\ExceptionInterface
     */
    public function testAuthorizationProviderInteraction()
    {
        $this->client
            ->setAuthProvider(new BasicAuthentication('johndoe', 'secret'))
            ->execute([
                'resource'  => 'user',
                'id'        => 1337,
                'authorize' => true
            ]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertTrue($request->hasHeader('Authorization'), 'Authorization header not available');
        $this->assertEquals(['am9obmRvZTpzZWNyZXQ='], $request->getHeader('Authorization'));
    }

    /**
     * @throws Flora\Exception\ExceptionInterface
     */
    public function testAuthProviderRequestParameters()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|\Flora\AuthProviderInterface $authProviderMock */
        $authProviderMock = $this->getMockBuilder(Flora\AuthProviderInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['authorize'])
            ->getMock();

        $authProviderMock
            ->expects($this->once())
            ->method('authorize')
            ->will($this->returnCallback(function (RequestInterface $request) {
                $uri = $request->getUri();

                $params = [];
                parse_str($uri->getQuery(), $params);
                $params['access_token'] = 'x.y.z';

                return $request->withUri($uri->withQuery(http_build_query($params)));
            }));

        $this->client
            ->setDefaultParams(['client_id' => 'test'])
            ->setAuthProvider($authProviderMock)
            ->execute([
                'resource'  => 'user',
                'id'        => 1337,
                'authorize' => true
            ]);

        $querystring = $this->mockHandler->getLastRequest()->getUri()->getQuery();
        $this->assertContains('client_id=test', $querystring);
        $this->assertContains('access_token=x.y.z', $querystring);
    }
}
