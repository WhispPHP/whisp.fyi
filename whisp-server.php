#! env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$port = (!empty($argv[1])) ? (int) $argv[1] : 2020;
(new Whisp\Server(port: $port))
    ->setLogger(new Whisp\Loggers\FileLogger(__DIR__ . '/server.log'))
    ->run(); // Auto discovers apps from the 'apps' directory
