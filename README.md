Flora PHP client
================

[![Build Status](https://travis-ci.org/godmodelabs/flora-client-php.svg?branch=master)](https://travis-ci.org/godmodelabs/flora-client-php)

Easily access [Flora](https://github.com/godmodelabs/flora) based APIs.

```php
$client = new Flora\Client('http://api.example.com/');
$response = $client->execute([
    'resource'  => 'article',
    'select'    => 'id,title,date',
    'limit'     => 15
]);
```
