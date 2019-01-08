<?php

namespace Flora;

use Flora\Auth\Provider as AuthProvider;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\RequestInterface;

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

    /** @var AuthProvider */
    private $authProvider;

    /** @var array */
    private $defaultParams = [];

    /** @var array */
    private $forceGetParams = ['client_id', 'action', 'access_token'];

    /**
     * @param string $url URL of Flora instance
     * @param array $options {
     *      @var HttpClient     $httpClient             optional    Http client instance handling API requests
     *      @var array $httpOptions optional {
     *          @var int    $timeout    Request timeout
     *      }
     *      @var AuthProvider   $authProvider           optional    Authenticate requests with given provider
     *      @var array          $defaultParams          optional    Automatically add params to each request
     *      @var array          $forceGetParams         optional    Params always send as part of the url
     * }
     */
    public function __construct($url, array $options = [])
    {
        $this->uri = new Uri($url);
        $this->httpClient = !isset($options['httpClient']) ? new HttpClient() : $options['httpClient'];
        if (isset($options['httpOptions'])) $this->setHttpOptions($options['httpOptions']);
        if (isset($options['authProvider'])) $this->setAuthProvider($options['authProvider']);
        if (isset($options['defaultParams'])) $this->setDefaultParams($options['defaultParams']);
        if (isset($options['forceGetParams'])) $this->setForceGetParams($options['forceGetParams']);
    }

    /**
     * @param array $params {
     *      @var string             $resource Flora resource
     *      @var int|string         $id             optional    Unique item identifier
     *      @var string             $format         optional    Output format (default json)
     *      @var string             $action         optional    API action (default: retrieve)
     *      @var string             $select         optional    Retrieve only specified attributes
     *      @var string             $filter         optional    Filter items by criteria
     *      @var int                $limit          optional    Limit result set
     *      @var int                $page           optional    Paginate through result set
     *      @var string             $search         optional    Search items by full-text search
     *      @var bool               $cache          optional    En-/disable caching (default: true)
     *      @var bool               $authenticate   optional    Use authentication provider to add some authentication information to request
     *      @var string             $httpMethod     optional    Explicitly set/override HTTP (GET, POST,...) method
     *      @var array|\stdClass    $data           optional    Send $data as JSON
     * }
     * @return \stdClass
     * @throws \Flora\Exception
     */
    public function execute(array $params)
    {
        if (!isset($params['resource']) || empty($params['resource'])) throw new Exception('Resource must be set');

        $uri = $this->uri->withPath($this->getPath($params));

        foreach (['resource', 'id', 'format'] as $param) { // remove path params from request params
            if (isset($params[$param])) unset($params[$param]);
        }

        if (array_key_exists('cache', $params)) {
            if ((bool) $params['cache'] === false) $params['_'] = time();
            unset($params['cache']);
        }

        if (isset($params['action']) && $params['action'] === 'retrieve') unset($params['action']);

        $httpMethod = $this->getHttpMethod($params);
        $request = new Request($httpMethod, $uri, ['Referer' => $this->getCurrentUri()]);

        if (isset($params['authenticate'])) {
            if ((bool) $params['authenticate']) {
                if ($this->authProvider === null) throw new Exception('Authentication provider is not configured');
                $request = $this->authProvider->authenticate($request);
            }
            unset($params['authenticate']);
        }

        if (!empty($this->defaultParams)) $params = array_merge($this->defaultParams, $params);
        if (!empty($params)) $request = $this->applyParameters($request, $params, $this->forceGetParams);

        try {
            $response = $this->httpClient->send($request, $this->httpOptions);
        } catch (RequestException $e) {
            throw new Exception($e->getMessage());
        }

        $result = $response->getBody();
        $contentType = $response->getHeaderLine('Content-Type');
        if (strpos($contentType, 'application/json') !== false) $result = json_decode($result);
        $statusCode = $response->getStatusCode();
        if ($statusCode < 400) return $result;

        $this->throwError($statusCode,
            (strpos($contentType, 'application/json') !== false)
                ? $result->error
                : (object)['message' => $response->getReasonPhrase()]);
    }

    /**
     * @param array $params
     * @return string
     */
    private function getPath(array $params)
    {
        $path = '';

        if (isset($params['resource'])) $path = '/' . $params['resource'] . '/';
        if (isset($params['id'])) $path .= $params['id'];
        if (isset($params['format'])) $path .= '.' . $params['format'];

        return $path;
    }

    private function getHttpMethod(array $params)
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
     * @return \Psr\Http\Message\RequestInterface
     */
    private function applyParameters(RequestInterface $request, array $params, array $forceGetParams = [])
    {
        if (empty($params)) return $request;

        ksort($params);
        if ($request->getMethod() === 'GET') return $this->handleGetRequest($request, $params);
        return $this->handlePostRequest($request, $params, $forceGetParams);
    }

    private function handleGetRequest(RequestInterface $request, array $params)
    {
        $uri = $request->getUri()->withQuery(http_build_query($params));
        return $request->withUri($uri);
    }

    private function handlePostRequest(RequestInterface $request, array $params, array $forceGetParams = [])
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

    private function getCurrentUri()
    {
        if (php_sapi_name() === 'cli') $currentUri = 'file://';
        elseif (isset($_SERVER['HTTP_HOST'])) $currentUri = 'http://' . $_SERVER['HTTP_HOST'];
        else $currentUri = 'unknown://';

        if (isset($_SERVER['REQUEST_URI'])) $currentUri .= $_SERVER['REQUEST_URI'];
        elseif (php_sapi_name() === 'cli' && isset($_SERVER['argv'])) $currentUri .= implode(' ', $_SERVER['argv']);
        elseif (isset($_SERVER['SCRIPT_FILENAME'])) $currentUri .= $_SERVER['SCRIPT_FILENAME'];

        return $currentUri;
    }

    /**
     * @param array $httpOptions
     */
    private function setHttpOptions(array $httpOptions)
    {
        if (isset($httpOptions['timeout'])) {
            $timeout = (int) $httpOptions['timeout'];
            $this->httpOptions['timeout'] = $timeout;
        }
    }

    /**
     * Use given provider to add some authentication information to request
     *
     * @param AuthProvider $authProvider
     * @return $this
     */
    public function setAuthProvider(AuthProvider $authProvider)
    {
        $this->authProvider = $authProvider;
        return $this;
    }

    public function setDefaultParams(array $params)
    {
        $this->defaultParams = $params;
        return $this;
    }

    public function setForceGetParams(array $forceGetParams)
    {
        if (count($forceGetParams)) $this->forceGetParams = array_merge($this->forceGetParams, $forceGetParams);
        return $this;
    }

    private function throwError($statusCode, \stdClass $error)
    {
        $message = $error->message;

        if ($statusCode === 400) throw new Exception\BadRequest($message);
        else if ($statusCode === 401) throw new Exception\Unauthorized($message);
        else if ($statusCode === 403) throw new Exception\Forbidden($message);
        else if ($statusCode === 404) throw new Exception\NotFound($message);
        else if ($statusCode === 500) throw new Exception\Server($message);
        else if ($statusCode === 503) throw new Exception\ServiceUnavailable($message);
        else throw new Exception($message);
    }
}
