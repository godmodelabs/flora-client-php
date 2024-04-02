# Flora PHP client

![](https://github.com/godmodelabs/flora-client-php/workflows/ci/badge.svg)

Easily access [Flora](https://github.com/godmodelabs/flora) based APIs.

```php
$client = new \Flora\Client('http://api.example.com/');
$response = $client->execute([
    'resource'  => 'foo',
    'select'    => 'id,name'
]);
```

## Raw responses

Return PSR-7 [response object](https://github.com/php-fig/http-message/blob/master/src/ResponseInterface.php) (e.g. to handle binary data).

```php
$client = new \Flora\Client('http://api.example.com/');
$response = $client->executeRaw([
    'resource' => 'article',
    'id' => 1337,
    'action' => 'pdf',
]);
```

## Asynchronous requests (using `guzzlehttp/promises`)

```php
use GuzzleHttp\Promise;

$client = new \Flora\Client('http://api.example.com/');
try {
    $fooPromise = $client->executeAsync([
        'resource' => 'foo',
        'select' => 'id,name'
    ]);
    $barPromise = $client->executeAsync([
        'resource' => 'bar',
        'select' => 'id,name'
    ]);
    
    [$fooResponse, $barResponse] = Promise\Utils::unwrap([$fooPromise, $barPromise]);
    // process responses...
} catch (Throwable $e) {
    echo $e->getMessage(), PHP_EOL;
}
```

## Parallel requests

Simple interface for simultaneously executing multiple API requests. Basically hides complexity from example above. 

```php
$client = new \Flora\Client('http://api.example.com/');
try {
    [$fooResponse, $barResponse] = $client->executeParallel([
        ['resource' => 'foo', 'select' => 'id,name'],
        ['resource' => 'bar', 'select' => 'id,name']
    ]);
    // process responses...
} catch (Throwable $e) {
    echo $e->getMessage(), PHP_EOL;
}
```
