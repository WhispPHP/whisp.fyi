<?php

// TODO: Move to the proper Prompt class, maybe add Chewie/Termwind/Screen/similar for a full TUI ecosystem

declare(strict_types=1);

require_once realpath(__DIR__.'/vendor/autoload.php');

use function Laravel\Prompts\clear;

$prompt = new class extends Laravel\Prompts\Prompt
{
    public function value(): mixed
    {
        return true;
    }

    public function freshDimensions(): array
    {
        // Technically we could unset the env var so it's not cached, then 'cols/lines' would call this for us but :shrug:
        $this->terminal()->initDimensions();

        return [
            'cols' => $this->terminal()->cols(),
            'lines' => $this->terminal()->lines(),
        ];
    }
};

['cols' => $cols, 'lines' => $rows] = $prompt->freshDimensions();

// Register signal handler before the loop
pcntl_signal(SIGINT, function () {
    echo "\033[?25h"; // Show cursor
    exit;
});

pcntl_signal(SIGWINCH, function () use ($prompt) {
    global $cols, $rows;
    ['cols' => $cols, 'lines' => $rows] = $prompt->freshDimensions();
});

$maxTime = 60000000; // 60 seconds in microseconds
$startTime = microtime(true);

while (microtime(true) - $startTime < $maxTime) {
    // Process any pending signals
    pcntl_signal_dispatch();

    $celebrationMessage = sprintf(" %d x %d", $cols, $rows);
    $padding = 4;
    $celebrationMessageLength = strlen($celebrationMessage) + ($padding * 2);
    $celebrationMessage = $prompt->bold($celebrationMessage);
    $cursorX = floor(($cols - $celebrationMessageLength) / 2);
    $cursorY = floor(($rows - 1) / 2);

    clear();
    echo sprintf("\033[%d;%dH", $cursorY - 1, $cursorX);
    echo $prompt->bgGreen(str_repeat(' ', $celebrationMessageLength));

    echo sprintf("\033[%d;%dH", $cursorY, $cursorX);
    echo $prompt->bgGreen(str_repeat(' ', $padding).$prompt->black($celebrationMessage).str_repeat(' ', $padding));

    echo sprintf("\033[%d;%dH", $cursorY + 1, $cursorX);
    echo $prompt->bgGreen(str_repeat(' ', $celebrationMessageLength));


    $prompt->hideCursor();
    usleep(80000);
}
