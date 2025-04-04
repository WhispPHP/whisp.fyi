<?php

// TODO: Move to the proper Prompt class, maybe add Chewie/Termwind/Screen/similar for a full TUI ecosystem

declare(strict_types=1);

require_once realpath(__DIR__ . '/../vendor/autoload.php');

use function Laravel\Prompts\{clear, error, info, spin, text, progress, search, table};
use App\Elephant;
use App\GuessTheElephantPrompt;

$prompt = new class extends Laravel\Prompts\Prompt {
    public function value(): mixed {
        return true;
    }
};
$cols = $prompt->terminal()->cols();
$rows = $prompt->terminal()->lines();

    // Confetti
    // Center a box saying "You won! The elephant _is_ called _name_, congrats!"
    // Then, around it we generate confetti that falls down - so it should slowly drift down from the top of the screen, different colors, different sizes, different speeds, sometimes wiggling side to side slightly

    // Confetti
    $confetti = [];
    for ($i = 0; $i < 140; $i++) {
        $confetti[] = [
            'color' => array_rand([
                "\033[31m" => 'red',
                "\033[32m" => 'green',
                "\033[34m" => 'blue',
                "\033[33m" => 'yellow',
                "\033[35m" => 'purple',
                "\033[38;5;208m" => 'orange',
                "\033[38;5;205m" => 'pink',
            ]),
            'shape' => array_rand([
                '▀' => 'block',
                '▄' => 'block',
                '▐' => 'block',
                '∙' => 'block',
                '#' => 'block',
                ]),
            'speed' => rand(1, 3) / 10,
            'x' => rand(0, $cols),
            'y' => rand(0, (int) floor($rows / 3)), // start near the top of the screen
        ];
    }

    // Animate the confetti
    $maxTime = 3000000; // 3 seconds in microseconds
    $startTime = microtime(true);
    $allLanded = function ($confetti) use ($rows) {
        foreach ($confetti as $confettiItem) {
            if ($confettiItem['y'] < $rows) {
                return false;
            }
        }
        return true;
    };

    // Register signal handler before the loop
    pcntl_signal(SIGINT, function () {
        echo "\033[?25h"; // Show cursor
        exit;
    });

    while (microtime(true) - $startTime < $maxTime && !$allLanded($confetti)) {
        // Process any pending signals
        pcntl_signal_dispatch();

        clear();

        foreach ($confetti as &$confettiItem) {
            echo sprintf("\033[%d;%dH", floor($confettiItem['y']), $confettiItem['x']);
            echo $confettiItem['color'] . $confettiItem['shape'] . "\033[0m";

            // Reached the bottom, don't move it
            if (floor($confettiItem['y']) > $rows) {
                continue;
            }

            $confettiItem['y'] += $confettiItem['speed'];
            if (rand(0, 100) < 30) {
                $confettiItem['x'] += rand(-1, 1);
            }
        }

        $celebrationMessage = sprintf(" You won something! Well, you could have? I'm not sure tbh, but enjoy the confetti regardless");
        $padding = 4;
        $celebrationMessageLength = strlen($celebrationMessage) + ($padding * 2);
        $celebrationMessage = $prompt->bold($celebrationMessage);
        $cursorX = floor(($cols - $celebrationMessageLength) / 2);
        $cursorY = floor(($rows - 1) / 2);

        echo sprintf("\033[%d;%dH", $cursorY-1, $cursorX);
        echo $prompt->bgGreen(str_repeat(' ', $celebrationMessageLength));

        echo sprintf("\033[%d;%dH", $cursorY, $cursorX);
        echo $prompt->bgGreen(str_repeat(' ', $padding) . $prompt->black($celebrationMessage) . str_repeat(' ', $padding));

        echo sprintf("\033[%d;%dH", $cursorY+1, $cursorX);
        echo $prompt->bgGreen(str_repeat(' ', $celebrationMessageLength));

        $prompt->hideCursor();
        usleep(80000);
    }
