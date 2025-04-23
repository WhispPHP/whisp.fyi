<?php

require_once __DIR__ . '/vendor/autoload.php';

echo strtoupper(
    (new Apps\StdinReader())->read()
);
