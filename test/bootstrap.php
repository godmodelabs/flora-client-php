<?php
// TODO: remove once php-auth-client is available as seperate composer package
/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require __DIR__  . '/../vendor/autoload.php';
$loader->addPsr4('Flora\\Auth\\', __DIR__ . '/../../php-auth-client/src/Flora/Auth/');
$loader->addPsr4('League\\OAuth2\\Client\\', __DIR__ . '/../../php-auth-client/vendor/league/oauth2-client/src/');
