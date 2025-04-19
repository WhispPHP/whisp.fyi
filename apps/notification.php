<?php

require_once __DIR__.'/vendor/autoload.php';

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

enum FocusState
{
    case FOCUSED;
    case UNFOCUSED;
    case UNSUPPORTED;
}

function checkTerminalFocus(): FocusState
{
    // Query terminal focus state using OSC 104
    // OSC 104;? queries the current focus state
    fwrite(STDOUT, 'Howdy');
    fwrite(STDOUT, "\033]104;?\007");
    fflush(STDOUT);

    stream_set_blocking(STDIN, false);
    usleep(200000);

    $response = fread(STDIN, 1024);

    // Reset stream to blocking mode
    var_dump('STDIN response:', bin2hex($response));

    stream_set_blocking(STDIN, true);

    if (strpos($response, "\033]104;0\007") !== false) {
        return FocusState::FOCUSED; // Terminal has focus
    } elseif (strpos($response, "\033]104;1\007") !== false) {
        return FocusState::UNFOCUSED; // Terminal doesn't have focus
    }

    return FocusState::UNSUPPORTED; // Terminal doesn't support focus reporting
}

function sendNotification(string $message): void
{
    // $focusState = checkTerminalFocus();

    // if ($focusState === FocusState::UNSUPPORTED) {
    // info('Your terminal doesn\'t support focus reporting, so we are going to notify regardless :)' . PHP_EOL);
    // } elseif ($focusState === FocusState::UNFOCUSED) {
    // info('Terminal is not in focus, sending notification to get your attention..' . PHP_EOL);
    // }

    // Send notification if terminal is not in focus or doesn't support focus reporting
    // if ($focusState === FocusState::UNFOCUSED || $focusState === FocusState::UNSUPPORTED) {
    echo "\033]9;{$message}\007";
    // }
}

intro('It is notification time!');

// Save current terminal settings
$termios = shell_exec('stty -g');

// Turn off echo and canonical mode
shell_exec('stty -echo -icanon');

// Only send the notification if the terminal is not in focus, or doesn't support focus reporting
sendNotification('👋 Howdy from Whisp 🔮, keep being awesome! 💪');

// From testing it worked in Ghostty & iTerm, but not Warp or Terminal.app.
info('Not all terminals support this unfortunately, but let\'s give it a bash.'.PHP_EOL);

outro('Check your notification center').PHP_EOL;

// Restore terminal settings
shell_exec("stty $termios");
