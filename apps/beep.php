<?php

require_once __DIR__.'/vendor/autoload.php';

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

intro('It is beeping time!');

// Beep the terminal
echo "\007";

// From testing it worked in Warp, Terminal.app, iTerm, but not Ghostty.
info('Not all terminals support this unfortunately, but let\'s give it a bash.'.PHP_EOL);

outro('Hear anything?').PHP_EOL;
