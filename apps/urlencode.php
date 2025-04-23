<?php

require_once __DIR__ . '/vendor/autoload.php';

echo urlencode(
    (new Apps\StdinReader())->read()
);
