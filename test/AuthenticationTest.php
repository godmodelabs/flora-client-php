<?php

namespace Flora\Client\Test;

use Flora\Auth\Provider as AuthProvider;
use Flora\Auth\Strategy\BasicAuthentication as BasicAuthenticationStrategy;
use Flora\Auth\Strategy\OAuth2 as OAuth2Strategy;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;

class AuthenticationTest extends FloraClientTest
{
    public function setUp()
    {
        parent::setUp();
        $this->mockHandler->append(new Response());
    }

    public function testBasicAuthenticationProvider()
    {
        $authProvider = new AuthProvider(new BasicAuthenticationStrategy('johndoe', 'secret'));
        $this->client->setAuthProvider($authProvider);
        $this->client->execute(['resource' => 'user', 'id' => 1337]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertTrue($request->hasHeader('Authorization'), 'Authorization header not available');
        $this->assertEquals(['am9obmRvZTpzZWNyZXQ='], $request->getHeader('Authorization'));
    }

    public function testOauth2AuthenticationProvider()
    {
        $accessToken = new AccessToken(['access_token' => 'xyz']);

        /** @var \League\OAuth2\Client\Provider\GenericProvider $oauth2ProviderMock */
        $oauth2ProviderMock = $this->getMockBuilder('\\League\\OAuth2\\Client\\Provider\\GenericProvider')
            ->disableOriginalConstructor()
            ->setMethods(['getAccessToken'])
            ->getMock();

        $oauth2ProviderMock->expects($this->once())
            ->method('getAccessToken')
            ->with($this->equalTo('client_credentials'))
            ->will($this->returnValue($accessToken));

        $authProvider = new AuthProvider(new OAuth2Strategy($oauth2ProviderMock));
        $this->client->setAuthProvider($authProvider);
        $this->client->execute(['resource' => 'user', 'id' => 1337]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertTrue($request->hasHeader('Authorization'), 'Authorization header not available');
        $this->assertEquals(['Bearer xyz'], $request->getHeader('Authorization'));
    }
}
