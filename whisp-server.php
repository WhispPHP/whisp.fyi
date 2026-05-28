#! env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$localLibcrypto = __DIR__ . '/runtime/openssl/lib64/libcrypto.so.3';
if (! getenv('WHISP_LIBCRYPTO') && is_file($localLibcrypto)) {
    putenv("WHISP_LIBCRYPTO={$localLibcrypto}");
}

$port = (!empty($argv[1])) ? (int) $argv[1] : 2020;
(new Whisp\Server(port: $port))
    ->setLogger(new Whisp\Loggers\FileLogger(__DIR__ . '/server.log'))
    // ->setLogger(new Whisp\Loggers\ConsoleLogger())
    ->run(); // Auto discovers apps from the 'apps' directory
