<?php

// TODO: Move to the proper Prompt class, maybe add Chewie/Termwind/Screen/similar for a full TUI ecosystem

declare(strict_types=1);

require_once realpath(__DIR__ . '/../vendor/autoload.php');

use function Laravel\Prompts\{clear, error, info, spin, text, progress, search, table};
use App\Elephant;
use App\GuessTheElephantPrompt;

$prompt = new GuessTheElephantPrompt();
$cols = $prompt->terminal()->cols();
$rows = $prompt->terminal()->lines();

$name = 'Ashley';

$name = text(
    label: 'What\'s your first name?',
    placeholder: 'E.g. Ashley',
    required: true,
    hint: 'We just need to know who we\'re talking to'
);

echo spin(function () use ($prompt, $name) {
    sleep(1);
    return sprintf("Howdy, %s!\n", $prompt->green($name));
}, 'One moment please..');

$users = progress(
    label: "Generating friendly elephant for {$name}",
    steps: 10,
    callback: function ($return, $progress) use ($prompt, $name) {
        usleep(300000);
        $progress
            ->hint("There are just too many cool things about {$name} to list here");

        return true;
    },
    hint: 'This may take some time.'
);

$elephant = new Elephant($name);
$elephantLines = explode("\n", $elephant->takePicture());
$elephantWidth = max(array_map('strlen', $elephantLines));
$questionLength = strlen(' This elephant is %s called %s, but what %s it called? ');

// We want the elephant to travel until its trunk touches the end of the 'question' box/background, if it can, otherwise, just whatever space is available
if ($cols >= $questionLength) {
    $availableToMove = $questionLength - $elephantWidth;
} else {
    $availableToMove = $cols - $elephantWidth - 2;
}

// Move forward
for ($i = 1; $i < $availableToMove; $i++) {
    clear();
    array_map(function ($line) use ($prompt, $i) {
        echo str_repeat(' ', $i);
        echo $prompt->dim($line) . "\n";
    }, $elephantLines);
    echo $prompt->bgGreen(
        $prompt->black(
        sprintf(" This elephant is %s called %s, but what %s it called? \n\n", $prompt->italic('not'), $prompt->bold($name), $prompt->italic('is')))
    );
    usleep(40000);
}

// Flip, move backwards
$elephantLines = explode("\n", $elephant->takePictureFlipped());
for ($i = $availableToMove; $i > 0; $i--) {
    clear();
    array_map(function ($line) use ($prompt, $i) {
        echo str_repeat(' ', $i);
        echo $prompt->dim($line) . "\n";
    }, $elephantLines);
    echo $prompt->bgGreen(
        $prompt->black(
        sprintf(" This elephant is %s called %s, but what %s it called? \n\n", $prompt->italic('not'), $prompt->bold($name), $prompt->italic('is')))
    );
    usleep(40000);
}


clear();
echo $prompt->dim($elephant->takePicture()) . "\n\n";
echo $prompt->bgGreen(
    $prompt->black(
    sprintf(" This elephant is %s called %s, but what %s it called? \n\n", $prompt->italic('not'), $prompt->bold($name), $prompt->italic('is')))
);

$maxGuesses = 3;
$guessesMade = 0;
$guessedCorrectly = false;
$guessedNames = [];
info('You get ' . $maxGuesses . ' guesses..');

while ($guessesMade < $maxGuesses && $guessedCorrectly === false) {
    $guessedNameKey = search(
        label: 'Guess the elephant',
        placeholder: 'E.g. Aisha',
        hint: 'Start typing to see possible names',
        options: fn (string $value) => strlen($value) > 0
            ? $elephant->searchAvailableNames($value, $guessedNames)
            : $elephant->listAvailableNames($guessedNames)
    );

    if (is_int($guessedNameKey)) {
        $guessedName = $elephant->listAvailableNames($guessedNames)[$guessedNameKey];
    } else {
        $guessedName = $guessedNameKey;
    }
    $guessedNames[] = $guessedName;
    $guessedCorrectly = $elephant->guessName($guessedName);
    $guessesMade++;
    if ($guessedCorrectly === false) {
        echo sprintf("  %d/%d guesses made, try again..\n\n", $guessesMade, $maxGuesses);
    }
}

if ($guessedCorrectly) {
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

        $celebrationMessage = sprintf(" You won! The elephant _is_ called %s, congrats!", $elephant->getName());
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
} else {
    // The opposite of confetti
    error('You didn\'t guess the elephant correctly..Oof..Sorry about that..');

    table(
        headers: ['Name', 'Random Numbers'],
        rows: array_map(function ($name) {
            return [
                $name,
                implode(', ', array_map(fn () => rand(1, 100), range(1, 5)))
            ];
        }, $elephant->listAvailableNames())
    );

    info('The elephant is actually called ' . $elephant->getName() . "\n\n");
}
