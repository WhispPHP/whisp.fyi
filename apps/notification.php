<?php

require_once __DIR__ . '/vendor/autoload.php';

use function Laravel\Prompts\{info, intro, outro};

intro('It is notification time!');

// Send notification to terminal for the terminal emulator to display
echo "\033]9;👋 Howdy from Whisp 🔮, keep being awesome! 💪 \007";

info('Not all terminals support this unfortunately, but let\'s give it a bash.' . PHP_EOL);

outro('Check your notification center') . PHP_EOL;
