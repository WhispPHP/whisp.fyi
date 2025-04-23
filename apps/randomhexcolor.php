<?php

require_once __DIR__ . '/vendor/autoload.php';

$r = random_int(0, 255);
$g = random_int(0, 255);
$b = random_int(0, 255);

echo '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
