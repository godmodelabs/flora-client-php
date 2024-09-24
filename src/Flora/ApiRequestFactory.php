<?php declare(strict_types=1);

namespace Flora;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\{RequestInterface, UriInterface};

class ApiRequestFactory
{
    private $uri;
    private $authProvider;
    private $defaultParams;
    private $forceGetParams;

    public function __construct(
        UriInterface $uri,
        ?AuthProviderInterface $authProvider = null,
        array $defaultParams = [],
        array $forceGetParams = []
    )
    {
        $this->uri = $uri;
        $this->authProvider = $authProvider;
        $this->defaultParams = $defaultParams;
        $this->forceGetParams = $forceGetParams;
    }

    public function create(array $params): RequestInterface
    {
        if (!isset($params['resource']) || empty($params['resource'])) {
            throw new Exception\ImplementationException('Resource must be set');
        }

        $doAuth = isset($params['auth']) && (bool) $params['auth'];

        $uri = $this->uri->withPath($this->getPath($params));
        foreach (['resource', 'id', 'format', 'auth'] as $param) { // remove path params from request params
            if (isset($params[$param])) {
                unset($params[$param]);
            }
        }

        if (array_key_exists('cache', $params)) {
            if ((bool) $params['cache'] === false) {
                $params['_'] = time();
            }
            unset($params['cache']);
        }

        if (isset($params['select']) && is_array($params['select'])) {
            $params['select'] = stringify_select($params['select']);
        }

        if (isset($params['action']) && $params['action'] === 'retrieve') {
            unset($params['action']);
        }

        $request = new Request($this->getHttpMethod($params), $uri, ['Referer' => $this->getCurrentUri()]);

        if (!empty($this->defaultParams)) {
            $params = array_merge($this->defaultParams, $params);
        }

        if (!empty($params)) {
            $request = $this->applyParameters($request, $params, $this->forceGetParams);
        }

        if ($doAuth) {
            if ($this->authProvider === null) {
                throw new Exception\ImplementationException('Auth provider is not configured');
            }
            $request = $this->authProvider->auth($request);
        }

        return $request;
    }

    /**
     * @param array $params
     * @return string
     */
    private function getPath(array $params): string
    {
        $path = "/{$params['resource']}/";

        if (isset($params['id'])) {
            $path .= $params['id'];
        }

        if (isset($params['format'])) {
            $path .= '.' . $params['format'];
        }

        return $path;
    }

    private function getHttpMethod(array $params): string
    {
        if (isset($params['httpMethod'])) {
            return strtoupper($params['httpMethod']);
        }

        if (isset($params['action']) && $params['action'] !== 'retrieve') {
            return 'POST';
        }

        if (strlen(http_build_query($params)) > 2000) {
            return 'POST';
        }

        return 'GET';
    }

    /**
     * Add parameters to request based on HTTP method
     *
     * @param RequestInterface $request
     * @param array $params
     * @param array $forceGetParams Optional Transfer parameters as part of url
     * @return RequestInterface
     */
    private function applyParameters(
        RequestInterface $request,
        array $params,
        array $forceGetParams = []
    ): RequestInterface
    {
        if (empty($params)) return $request;

        ksort($params);

        if ($request->getMethod() === 'GET') {
            $uri = $request->getUri()->withQuery(http_build_query($params));
            return $request->withUri($uri);
        }

        return PostRequestFactory::create($request, $params, $forceGetParams);
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
}
