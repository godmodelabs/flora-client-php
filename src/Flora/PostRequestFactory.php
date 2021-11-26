<?php declare(strict_types=1);

namespace Flora;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

class PostRequestFactory
{
    public static function create(
        RequestInterface $request,
        array $params,
        array $forceGetParams = []
    ): RequestInterface
    {
        $isJsonRequest = array_key_exists('data', $params) && !empty($params['data']);
        if ($isJsonRequest) {
            $data = $params['data'];
            unset($params['data']);
            return $request
                ->withUri($request->getUri()->withQuery(http_build_query($params)))
                ->withHeader('Content-Type', 'application/json')
                ->withBody(Utils::streamFor(json_encode($data)));
        }

        if (!empty($forceGetParams)) {
            $getParams = [];
            foreach ($forceGetParams as $defaultGetParam) {
                if (!isset($params[$defaultGetParam])) {
                    continue;
                }
                $getParams[$defaultGetParam] = $params[$defaultGetParam];
                unset($params[$defaultGetParam]);
            }

            $uri = $request->getUri()->withQuery(http_build_query($getParams));
            $request = $request->withUri($uri);
        }

        return $request
            ->withAddedHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody(Utils::streamFor(http_build_query($params)));
    }
}
