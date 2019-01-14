<?php

namespace Flora\Client\Test;

use Flora;
use GuzzleHttp\Psr7\Response;

class AuthorizationTest extends FloraClientTest
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
        $this->expectExceptionMessage('Authorization provider is not configured');

        $this->client->execute([
            'resource'  => 'user',
            'id'        => 1337,
            'authorize' => true
        ]);
    }

    /**
     * @throws Flora\Exception
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
}
