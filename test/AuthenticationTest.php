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

    public function testNoProviderConfiguredException()
    {
        $this->setExpectedException('\\Flora\\Exception', 'Authentication provider is not configured');

        $this->client->execute([
            'resource'      => 'user',
            'id'            => 1337,
            'authenticate'  => true
        ]);
    }

    public function testAuthenticationProviderInteraction()
    {
        $authProvider = new AuthProvider(new BasicAuthenticationStrategy('johndoe', 'secret'));
        $this->client->setAuthProvider($authProvider);
        $this->client->execute([
            'resource'      => 'user',
            'id'            => 1337,
            'authenticate'  => true
        ]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertTrue($request->hasHeader('Authorization'), 'Authorization header not available');
        $this->assertEquals(['am9obmRvZTpzZWNyZXQ='], $request->getHeader('Authorization'));
    }
}
