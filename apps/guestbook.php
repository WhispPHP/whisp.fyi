#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

// Run the guestbook
$guestbook = new \Apps\GuestbookPrompt;
$guestbook->prompt();
