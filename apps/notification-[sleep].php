<?php

require_once __DIR__.'/vendor/autoload.php';

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

function checkTerminalFocus(): ?bool
{
    // Send focus query
    fwrite(STDOUT, "\033]104;?\007");

    // Set stream to non-blocking to check for response
    stream_set_blocking(STDIN, false);

    // Wait briefly for response (100ms)
    usleep(100000);

    // Try to read response
    $response = fread(STDIN, 1024);

    // Reset stream to blocking
    stream_set_blocking(STDIN, true);

    if (strpos($response, "\033]104;0\007") !== false) {
        return true; // Terminal has focus
    } elseif (strpos($response, "\033]104;1\007") !== false) {
        return false; // Terminal doesn't have focus
    }

    return null; // Terminal doesn't support focus reporting
}

function sendNotification(string $message): void
{
    // $focusState = checkTerminalFocus();

    // Send notification if terminal is not in focus or doesn't support focus reporting
    // if ($focusState === false || $focusState === null) {
    echo "\033]9;{$message}\007";
    // }
}

intro('It is notification time, sleeping for '.$argv[1].' seconds!');

// Save current terminal settings
$termios = shell_exec('stty -g');

// Turn off echo and canonical mode
shell_exec('stty -echo -icanon');

// Only send the notification if the terminal is not in focus, or doesn't support focus reporting
sleep($argv[1]);
sendNotification('👋 Howdy from Whisp 🔮, keep being awesome! 💪');

// From testing it worked in Ghostty & iTerm, but not Warp or Terminal.app.
info('Not all terminals support this unfortunately, but let\'s give it a bash.'.PHP_EOL);

outro('Check your notification center').PHP_EOL;

// Restore terminal settings
shell_exec("stty $termios");
