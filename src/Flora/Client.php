<?php declare(strict_types=1);

namespace Flora;

use Closure;
use Flora\Exception\ImplementationException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\{Promise, PromiseInterface};
use GuzzleHttp\Psr7\{Request, Stream, Uri};
use GuzzleHttp\Client as HttpClient;
use const PHP_SAPI;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use stdClass;

class Client
{
    /** @var Uri */
    private $uri;

    /** @var HttpClient */
    private $httpClient;

    /** @var array */
    private $httpOptions = [
        'http_errors'   => false,   // don't throw response with status code >= 400 as exception
        'timeout'       => 30       // Guzzle HTTP client waits forever by default
    ];

    /** @var AuthProviderInterface */
    private $authProvider;

    /** @var array */
    private $defaultParams = [];

    /** @var array */
    private $forceGetParams;

    /**
     * @param string $url URL of Flora instance
     * @param array $options {
     *      @var HttpClient             $httpClient             optional    Http client instance handling API requests
     *      @var array $httpOptions optional {
     *          @var int                $timeout                            Request timeout
     *      }
     *      @var AuthProviderInterface  $authProvider           optional    Authorize requests with given provider
     *      @var array                  $defaultParams          optional    Automatically add params to each request
     *      @var array                  $forceGetParams         optional    Params always send as part of the url
     * }
     */
    public function __construct($url, array $options = [])
    {
        $this->uri = new Uri($url);
        $this->httpClient = isset($options['httpClient']) && $options['httpClient'] instanceof HttpClient
            ? $options['httpClient']
            : new HttpClient();

        if (isset($options['httpOptions'])) $this->setHttpOptions($options['httpOptions']);

        if (isset($options['authProvider'])) {
            if (!($options['authProvider'] instanceof AuthProviderInterface)) {
                throw new ImplementationException('authProvider must be in instance of AuthProviderInterface');
            }
            $this->authProvider = $options['authProvider'];
        }

        if (isset($options['defaultParams']) && is_array($options['defaultParams']) && count($options['defaultParams'])) {
            $this->defaultParams = $options['defaultParams'];
        }

        $this->forceGetParams = ['client_id', 'action', 'access_token'];
        if (isset($options['forceGetParams']) && is_array($options['forceGetParams']) && count($options['forceGetParams'])) {
            $this->forceGetParams = array_merge($this->forceGetParams, $options['forceGetParams']);
        }
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
    public function execute(array $params): stdClass
    {
        if (!isset($params['resource']) || empty($params['resource'])) throw new Exception\ImplementationException('Resource must be set');

        $request = $this->prepareRequest($params);

        $auth = isset($params['auth']) && (bool) $params['auth'];
        if ($auth) {
            if ($this->authProvider === null) throw new Exception\ImplementationException('Auth provider is not configured');
            $request = $this->authProvider->auth($request);
        }

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
        if (!isset($params['resource']) || empty($params['resource'])) return self::reject('Resource must be set');

        $request = $this->prepareRequest($params);

        $auth = isset($params['auth']) && (bool) $params['auth'];
        if ($auth) {
            if ($this->authProvider === null) return self::reject('Auth provider is not configured');
            $request = $this->authProvider->auth($request);
        }

        return $this->httpClient
            ->sendAsync($request)
            ->then(
                Closure::fromCallable([__CLASS__, 'handleResponse']),
                static function (GuzzleException $e) {
                    return new Exception\TransferException($e->getMessage(), $e->getCode(), $e);
                }
            );
    }

    private static function reject(string $reason): PromiseInterface
    {
        $promise = new Promise();
        $promise->reject($reason);
        return $promise;
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

    private function prepareRequest(array $params): RequestInterface
    {
        $uri = $this->uri->withPath($this->getPath($params));

        foreach (['resource', 'id', 'format'] as $param) { // remove path params from request params
            if (isset($params[$param])) unset($params[$param]);
        }

        if (array_key_exists('cache', $params)) {
            if ((bool) $params['cache'] === false) $params['_'] = time();
            unset($params['cache']);
        }

        if (isset($params['select']) && is_array($params['select'])) $params['select'] = stringify_select($params['select']);
        if (isset($params['action']) && $params['action'] === 'retrieve') unset($params['action']);

        $httpMethod = $this->getHttpMethod($params);
        $request = new Request($httpMethod, $uri, ['Referer' => $this->getCurrentUri()]);

        if (!empty($this->defaultParams)) $params = array_merge($this->defaultParams, $params);
        if (!empty($params)) $request = $this->applyParameters($request, $params, $this->forceGetParams);

        return $request;
    }

    /**
     * @param array $params
     * @return string
     */
    private function getPath(array $params): string
    {
        $path = '';

        if (isset($params['resource'])) $path = '/' . $params['resource'] . '/';
        if (isset($params['id'])) $path .= $params['id'];
        if (isset($params['format'])) $path .= '.' . $params['format'];

        return $path;
    }

    private function getHttpMethod(array $params): string
    {
        $httpMethod = 'GET';

        if (isset($params['action']) && $params['action'] !== 'retrieve') $httpMethod = 'POST';
        if (strlen(http_build_query($params)) > 2000) $httpMethod = 'POST';
        if (isset($params['httpMethod'])) $httpMethod = strtoupper($params['httpMethod']);

        return $httpMethod;
    }

    /**
     * Add parameters to request based on HTTP method
     *
     * @param RequestInterface $request
     * @param array $params
     * @param array $forceGetParams Optional Transfer parameters as part of url
     * @return RequestInterface
     */
    private function applyParameters(RequestInterface $request, array $params, array $forceGetParams = []): RequestInterface
    {
        if (empty($params)) return $request;

        ksort($params);
        if ($request->getMethod() === 'GET') return $this->handleGetRequest($request, $params);
        return $this->handlePostRequest($request, $params, $forceGetParams);
    }

    private function handleGetRequest(RequestInterface $request, array $params): RequestInterface
    {
        $uri = $request->getUri()->withQuery(http_build_query($params));
        return $request->withUri($uri);
    }

    private function handlePostRequest(RequestInterface $request, array $params, array $forceGetParams = []): RequestInterface
    {
        $isJsonRequest = array_key_exists('data', $params) && !empty($params['data']);

        if (!empty($forceGetParams) && !$isJsonRequest) {
            $getParams = [];
            foreach ($forceGetParams as $defaultGetParam) {
                if (!isset($params[$defaultGetParam])) continue;
                $getParams[$defaultGetParam] = $params[$defaultGetParam];
                unset($params[$defaultGetParam]);
            }
            $request = $request->withUri($request->getUri()->withQuery(http_build_query($getParams)));
        }

        $stream = fopen('php://memory', 'wb+');
        $body   = new Stream($stream);

        if ($isJsonRequest) {
            $body->write(json_encode($params['data']));
            unset($params['data']);
            $request = $request
                ->withUri($request->getUri()->withQuery(http_build_query($params)))
                ->withHeader('Content-Type', 'application/json');
        } else {
            $body->write(http_build_query($params));
            $request = $request->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        return $request->withBody($body);
    }

    private function getCurrentUri(): string
    {
        if (PHP_SAPI === 'cli') $currentUri = 'file://';
        elseif (isset($_SERVER['HTTP_HOST'])) $currentUri = 'http://' . $_SERVER['HTTP_HOST'];
        else $currentUri = 'unknown://';

        if (isset($_SERVER['REQUEST_URI'])) $currentUri .= $_SERVER['REQUEST_URI'];
        elseif (PHP_SAPI === 'cli' && isset($_SERVER['argv'])) $currentUri .= implode(' ', $_SERVER['argv']);
        elseif (isset($_SERVER['SCRIPT_FILENAME'])) $currentUri .= $_SERVER['SCRIPT_FILENAME'];

        return $currentUri;
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
        else if ($statusCode === 503) throw new Exception\ServiceUnavailableException($message);
        else throw new Exception\RuntimeException($message);
    }
}
