<?php

namespace Flora\Client\Test;

use Flora;
use GuzzleHttp\Psr7\Response;

class AuthenticationTest extends FloraClientTest
{
    public function setUp()
    {
        parent::setUp();
        $this->mockHandler->append(new Response());
    }

    /**
     * @throws Flora\Exception
     */
    public function testNoProviderConfiguredException()
    {
        $this->expectException(Flora\Exception::class);
        $this->expectExceptionMessage('Authentication provider is not configured');

        $this->client->execute([
            'resource'      => 'user',
            'id'            => 1337,
            'authenticate'  => true
        ]);
    }

    /**
     * @throws Flora\Exception
     */
    public function testAuthenticationProviderInteraction()
    {
        $this->client
            ->setAuthProvider(new BasicAuthentication('johndoe', 'secret'))
            ->execute([
                'resource'      => 'user',
                'id'            => 1337,
                'authenticate'  => true
            ]);

        $request = $this->mockHandler->getLastRequest();

        $this->assertTrue($request->hasHeader('Authorization'), 'Authorization header not available');
        $this->assertEquals(['am9obmRvZTpzZWNyZXQ='], $request->getHeader('Authorization'));
    }
}
