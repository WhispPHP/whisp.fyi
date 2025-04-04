#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/GuestbookPrompt.php';

// Run the guestbook
(new GuestbookPrompt)->prompt();
