<?php

require_once __DIR__ . '/vendor/autoload.php';

use function Laravel\Prompts\{info, intro, outro};

intro('It is notification time!');

echo "\033]9;Hello notification my old friend\007";

info('Not all terminals support this unfortunately, but let\'s give it a bash.' . PHP_EOL);

outro('Check your notification center') . PHP_EOL;
