<?php declare(strict_types=1);

namespace Flora;

use Closure;
use Flora\Exception\ImplementationException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use function GuzzleHttp\Promise\unwrap;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Throwable;

class Client
{
    /** @var HttpClient */
    private $httpClient;

    /** @var array */
    private $httpOptions = [
        'http_errors'   => false,   // don't throw response with status code >= 400 as exception
        'timeout'       => 30       // Guzzle HTTP client waits forever by default
    ];

    /** @var ApiRequestFactory */
    private $requestFactory;

    /**
     * @param string $url URL of Flora instance
     * @param array $opts {
     *      @var HttpClient             $httpClient             optional    Http client instance handling API requests
     *      @var array $httpOptions optional {
     *          @var int                $timeout                            Request timeout
     *      }
     *      @var AuthProviderInterface  $authProvider           optional    Authorize requests with given provider
     *      @var string[]               $defaultParams          optional    Automatically add params to each request
     *      @var string[]               $forceGetParams         optional    Params always send as part of the url
     * }
     */
    public function __construct(string $url, array $opts = [])
    {
        $this->httpClient = isset($opts['httpClient']) && $opts['httpClient'] instanceof HttpClient
            ? $opts['httpClient']
            : new HttpClient();

        if (isset($opts['httpOptions'])) $this->setHttpOptions($opts['httpOptions']);

        $authProvider = null;
        if (isset($opts['authProvider'])) {
            if (!($opts['authProvider'] instanceof AuthProviderInterface)) {
                throw new ImplementationException('authProvider must be in instance of AuthProviderInterface');
            }
            $authProvider = $opts['authProvider'];
        }

        $defaultParams = [];
        if (isset($opts['defaultParams']) && is_array($opts['defaultParams']) && count($opts['defaultParams']) > 0) {
            $defaultParams = $opts['defaultParams'];
        }

        $forceGetParams = ['client_id', 'action', 'access_token'];
        if (isset($opts['forceGetParams']) && is_array($opts['forceGetParams']) && count($opts['forceGetParams']) > 0) {
            $forceGetParams = array_merge($forceGetParams, $opts['forceGetParams']);
        }

        $this->requestFactory = new ApiRequestFactory(new Uri($url), $authProvider, $defaultParams, $forceGetParams);
    }

    /**
     * @param array $params {
     *      @var string             $resource                   Flora resource
     *      @var int|string         $id             optional    Unique item identifier
     *      @var string             $format         optional    Output format (default json)
     *      @var string             $action         optional    API action (default: retrieve)
     *      @var string|array       $select         optional    Retrieve only specified attributes
     *      @var string             $filter         optional    Filter items by criteria
     *      @var int                $limit          optional    Limit result set
     *      @var int                $page           optional    Paginate through result set
     *      @var string             $search         optional    Search items by full-text search
     *      @var bool               $cache          optional    En-/disable caching (default: true)
     *      @var bool               $auth           optional    Use authentication provider to add some authentication information to request
     *      @var string             $httpMethod     optional    Explicitly set/override HTTP (GET, POST,...) method
     *      @var array|stdClass     $data           optional    Send $data as JSON
     * }
     * @return stdClass
     * @throws Exception\ImplementationException
     * @throws Exception\RuntimeException
     */
    public function execute(array $params): object
    {
        $request = $this->requestFactory->create($params);

        try {
            $response = $this->httpClient->send($request, $this->httpOptions);
        } catch (GuzzleException $e) {
            throw new Exception\TransferException($e->getMessage(), $e->getCode(), $e);
        }

        return self::handleResponse($response);
    }

    /**
     * @param array $params
     * @return PromiseInterface
     */
    public function executeAsync(array $params): PromiseInterface
    {
        $request = $this->requestFactory->create($params);

        return $this->httpClient
            ->sendAsync($request)
            ->then(
                Closure::fromCallable([__CLASS__, 'handleResponse']),
                static function (GuzzleException $e) {
                    return new Exception\TransferException($e->getMessage(), $e->getCode(), $e);
                }
            );
    }

    /**
     * @param array<array> $params
     * @return array<int, object>
     * @throws Throwable
     */
    public function executeParallel(array $params): array
    {
        $promises = array_map([$this, 'executeAsync'], $params);
        return unwrap($promises);
    }

    private static function handleResponse(ResponseInterface $response): stdClass
    {
        $statusCode = $response->getStatusCode();
        $result = json_decode($response->getBody()->getContents(), false);
        $isJson = strpos($response->getHeaderLine('Content-Type'), 'application/json') !== false;

        if ($statusCode >= 400) {
            $error = $isJson ? $result->error : (object) ['message' => $response->getReasonPhrase()];
            self::throwError($statusCode, $error);
        }

        return $result;
    }

    /**
     * @param array $httpOptions
     */
    private function setHttpOptions(array $httpOptions): void
    {
        if (isset($httpOptions['timeout'])) {
            $timeout = (int) $httpOptions['timeout'];
            $this->httpOptions['timeout'] = $timeout;
        }
    }

    /**
     * @param int $statusCode
     * @param stdClass $error
     * @throws Exception\RuntimeException
     * @throws Exception\BadRequestException
     * @throws Exception\ForbiddenException
     * @throws Exception\NotFoundException
     * @throws Exception\ServerException
     * @throws Exception\ServiceUnavailableException
     * @throws Exception\UnauthorizedException
     */
    private static function throwError(int $statusCode, stdClass $error): void
    {
        $message = $error->message;

        if ($statusCode === 400) throw new Exception\BadRequestException($message);
        else if ($statusCode === 401) throw new Exception\UnauthorizedException($message);
        else if ($statusCode === 403) throw new Exception\ForbiddenException($message);
        else if ($statusCode === 404) throw new Exception\NotFoundException($message);
        else if ($statusCode === 500) throw new Exception\ServerException($message);
        else if ($statusCode === 502) throw new Exception\BadGatewayException($message);
        else if ($statusCode === 503) throw new Exception\ServiceUnavailableException($message);
        else if ($statusCode === 504) throw new Exception\GatewayTimeoutException($message);
        else throw new Exception\RuntimeException($message);
    }
}
