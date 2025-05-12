<?php

require_once __DIR__.'/vendor/autoload.php';
echo base64_decode(
    trim((new Apps\StdinReader)->read())
);
