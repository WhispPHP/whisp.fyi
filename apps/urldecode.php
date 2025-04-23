<?php

require_once __DIR__ . '/vendor/autoload.php';

echo urldecode(
    (new Apps\StdinReader())->read()
);
