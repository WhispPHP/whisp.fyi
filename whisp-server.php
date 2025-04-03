#! env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

(new Whisp\Server(port: (int) $argv[1] ?? 2020))
    ->setLogger(new Whisp\Loggers\FileLogger(__DIR__ . '/server.log'))
    ->run(); // Auto discovers apps from the 'apps' directory
