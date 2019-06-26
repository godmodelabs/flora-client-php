Flora PHP client
================

[![Build Status](https://travis-ci.org/godmodelabs/flora-client-php.svg?branch=master)](https://travis-ci.org/godmodelabs/flora-client-php)

Easily access [Flora](https://github.com/godmodelabs/flora) based APIs.

```php
$client = new \Flora\Client('http://api.example.com/');
$response = $client->execute([
    'resource'  => 'foo',
    'select'    => 'id,name'
]);
```

The client also supports asynchronous requests:

```php
use function GuzzleHttp\Promise\unwrap;

$client = new \Flora\Client('http://api.example.com/');
try {
    [$fooResponse, $barResponse] = unwrap([
        $client->executeAsync([
            'resource' => 'foo',
            'select' => 'id,name'
        ]),
        $client->executeAsync([
            'resource' => 'bar',
            'select' => 'id,name'
        ])
    ]);
    // process responses...
} catch (Throwable $e) {
    echo $e->getMessage(), PHP_EOL;
}
```
