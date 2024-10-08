<?php declare(strict_types=1);

namespace Flora\Client\Test;

use Flora\ApiRequestFactory;
use GuzzleHttp\Psr7\Uri;
use PHPUnit;
use Psr\Http\Message\UriInterface;

class RequestMethodTest extends PHPUnit\Framework\TestCase
{
    /** @var UriInterface */
    private $uri;

    public function setUp(): void
    {
        parent::setUp();
        $this->uri = new Uri('http://api.example.com');
    }

    #[PHPUnit\Framework\Attributes\DataProvider('defaultHttpMethodDataProvider')]
    public function testHttpGetMethod(array $params, string $message): void
    {
        $request = (new ApiRequestFactory($this->uri))->create($params);
        self::assertSame('GET', $request->getMethod(), $message);
    }

    public function testHttpPostMethodForNonRetrieveActions(): void
    {
        $request = (new ApiRequestFactory($this->uri))
            ->create(['resource' => 'user', 'id' => 1337, 'action' => 'foobar']);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
    }

    public function testHttpMethodParameterOverwrite(): void
    {
        $request = (new ApiRequestFactory($this->uri))
            ->create(['resource' => 'user', 'id' => 1337, 'httpMethod' => 'POST']);

        self::assertSame('POST', $request->getMethod());
    }

    public function testHttpPostMethodForLongQueryStrings(): void
    {
        $request = (new ApiRequestFactory($this->uri))
            ->create([
                'resource'  => 'article',
                'select'    => str_repeat('select', 150),
                'filter'    => str_repeat('filter', 150),
                'search'    => str_repeat('term', 150),
                'limit'     => 100,
                'page'      => 10
            ]);

        self::assertSame('POST', $request->getMethod());
        self::assertStringContainsString(
            'select=',
            (string) $request->getBody(),
            'POST body doesn\'t contain parameters'
        );
    }

    public function testHttpPostMethodForJsonData(): void
    {
        $request = (new ApiRequestFactory($this->uri))
            ->create([
                'resource'  => 'article',
                'action'    => 'create',
                'data'      => ['title' => 'Lorem Ipsum']
            ]);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('action=create', $request->getUri()->getQuery());
    }

    public static function defaultHttpMethodDataProvider(): array
    {
        return [
            [['resource' => 'user', 'id' => 1337], 'Use GET method fallback if action parameter is empty'],
            [['resource' => 'user', 'id' => 1337, 'action' => 'retrieve'], 'Use GET method for retrieve actions']
        ];
    }
}
