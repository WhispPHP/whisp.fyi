<?php

require_once __DIR__.'/vendor/autoload.php';

use Ramsey\Uuid\Uuid;

$version = (int) $argv[1];
if ($version < 1 || $version > 7) {
    echo 'Invalid version'.PHP_EOL;
    exit(1);
}

$args = match ($version) {
    2 => [Uuid::DCE_DOMAIN_PERSON],
    3 => [Uuid::NAMESPACE_URL, 'https://whisp.fyi'],
    5 => [Uuid::NAMESPACE_URL, 'https://whisp.fyi'],
    default => [],
};

$method = 'uuid'.$version;
echo Ramsey\Uuid\Uuid::$method(...$args).PHP_EOL;
