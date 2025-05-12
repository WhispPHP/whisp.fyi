<?php

// TODO: Move to the proper Prompt class, maybe add Chewie/Termwind/Screen/similar for a full TUI ecosystem

declare(strict_types=1);

require_once realpath(__DIR__.'/vendor/autoload.php');

use function Laravel\Prompts\clear;

$responsive = ! empty($argv[1]) || getenv('RESPONSIVE');

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

// Confetti
// Center a box saying "You won! The elephant _is_ called _name_, congrats!"
// Then, around it we generate confetti that falls down - so it should slowly drift down from the top of the screen, different colors, different sizes, different speeds, sometimes wiggling side to side slightly

/**
 * Generates a random hex color (#RRGGBB) with perceived luminance within a specified range.
 *
 * @param  float  $minLuminance  Minimum acceptable luminance (0-255, e.g., 50).
 * @param  float  $maxLuminance  Maximum acceptable luminance (0-255, e.g., 200).
 * @return string The hex color code (e.g., "#A8C45F").
 */
function getRandomHexColorInRange(float $minLuminance = 50.0, float $maxLuminance = 200.0): string
{
    do {
        // 1. Generate random RGB values
        $r = rand(0, 255);
        $g = rand(0, 255);
        $b = rand(0, 255);

        // 2. Calculate perceived luminance
        $luminance = (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);

        // 3. Check if luminance is within the desired range
        $isValid = ($luminance >= $minLuminance && $luminance <= $maxLuminance);

    } while (! $isValid); // 4. Repeat if not in range

    // 5. Convert valid RGB to Hex format
    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

// Confetti
$confetti = [];
for ($i = 0; $i < 140; $i++) {
    $x = rand(0, $cols - 1);
    $y = rand(0, (int) floor($rows / 2.5));
    $yPercentage = round($y / $rows, 2);

    // My terminal supports true color, so we should pick a random 'true color' (hex?) for color instead
    // Except, try to pick a color that's not too dark
    $color = getRandomHexColorInRange(80, 240);
    [$r, $g, $b] = sscanf($color, '#%02X%02X%02X');
    $confetti[] = [
        'color' => "\x1b[38;2;{$r};{$g};{$b}m",
        'shape' => array_rand([
            '▀' => 'block',
            '▄' => 'block',
            '▐' => 'block',
            '∙' => 'block',
            '#' => 'block',
            '*' => 'block',
            '~' => 'block',
            '◦' => 'block',
            '◯' => 'block',
        ]),
        'speed' => rand(3, 10) / 10,
        'speedPercentage' => rand(3, 12) / 1000,
        'originalXPercentage' => round($x / $cols, 2),
        'originalYPercentage' => $yPercentage,
        'currentYPercentage' => $yPercentage,
        'x' => $x,
        'y' => $y,
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

$lastCols = $cols;
$lastRows = $rows;

if ($responsive) {
    pcntl_signal(SIGWINCH, function () use ($prompt, &$confetti, &$lastCols, &$lastRows) {
        global $cols, $rows;
        ['cols' => $cols, 'lines' => $rows] = $prompt->freshDimensions();
        // TODO: Clear the entire screen as the message could be much higher now too, can't trust 'y' from this point on
        // Hmmmm, do we calculate the Y difference from the previous frame, then we know if any have jumped down? because of wrapping to the next line?
        $widthSmaller = $cols < $lastCols;
        $heightSmaller = $rows < $lastRows;
        $widthGreater = $cols > $lastCols;
        $heightGreater = $rows > $lastRows;

        foreach ($confetti as &$confettiItem) {
            $confettiItem['x'] = max((int) floor($confettiItem['originalXPercentage'] * $cols), 1);
            $confettiItem['y'] = min((int) floor($confettiItem['currentYPercentage'] * $rows), $rows);

            // If the confetti has reached the bottom, then don't do anything to 'y', we're fine
            if ($confettiItem['y'] > $rows) { // They've been moved to the right _x_ to be correct
                continue;
            }

            // If the confetti has 'wrapped' - '$cols' is smaller than '$confettiItem['x']', the terminal wraps it and it jumps down one Y
            // So we need to move it up the screen one Y now we've moved its X to the new screen size
            if ($widthSmaller) {
                if ($confettiItem['x'] >= $cols) { // Width got smaller, some things wrapped
                    $confettiItem['y'] -= 1;
                }
            }

            // $confettiItem['y'] = $confettiItem['originalYPercentage'] * $rows;
        }
    });
}

$fps = 5;
$usleep = 1000000 / $fps;
$lastRenderTime = microtime(true);
$prevLowestY = 1;

clear();
while (microtime(true) - $startTime < $maxTime && ! $allLanded($confetti)) {
    // Process any pending signals
    pcntl_signal_dispatch();
    $usecsSinceLastRender = (microtime(true) - $lastRenderTime) * 1000000;
    $isTimeToRender = $usecsSinceLastRender >= $usleep;
    if (! $isTimeToRender) {
        usleep((int) ($usleep / 3)); // We can't sleep the usec diff, because we want to handle signals more frequently than our frame rate

        continue;
    }
    $lastRenderTime = microtime(true);

    $lowestY = max(1, $prevLowestY - 1);
    echo "\e[{$lowestY};0H";
    echo "\e[J";

    // Set 'y' based on 'currentYPercentage'
    if ($responsive) {
        foreach ($confetti as &$confettiItem) {
            $confettiItem['y'] = min((int) floor($confettiItem['currentYPercentage'] * $rows), $rows);
        }
    }

    $confettis = '';
    foreach ($confetti as &$confettiItem) {
        $confettis .= sprintf("\033[%d;%dH", floor($confettiItem['y']), $confettiItem['x']);
        $confettis .= $confettiItem['color'].$confettiItem['shape']."\x1b[0m";

        // Reached the bottom, don't move it
        if (ceil($confettiItem['y']) >= $rows) {
            continue;
        }

        if ($responsive) {
            $confettiItem['currentYPercentage'] += $confettiItem['speedPercentage'];
        } else {
            $confettiItem['y'] += $confettiItem['speed'];
        }

        if (rand(0, 100) < 30) {
            $confettiItem['x'] += rand(-1, 1); // Randomly wiggle side to side
        }
    }

    $celebrationMessage = sprintf("Enjoy this hand-crafted confetti in your {$cols}x{$rows} terminal");
    $padding = 4;
    $celebrationMessageLength = strlen($celebrationMessage) + ($padding * 2);
    $celebrationMessage = $prompt->bold($celebrationMessage);
    $cursorX = floor(($cols - $celebrationMessageLength) / 2);
    $cursorY = floor(($rows - 1) / 2);

    $confettis .= sprintf("\033[%d;%dH", $cursorY - 1, $cursorX);
    $confettis .= $prompt->bgGreen(str_repeat(' ', $celebrationMessageLength));

    $confettis .= sprintf("\033[%d;%dH", $cursorY, $cursorX);
    $confettis .= $prompt->bgGreen(str_repeat(' ', $padding).$prompt->black($celebrationMessage).str_repeat(' ', $padding));

    $confettis .= sprintf("\033[%d;%dH", $cursorY + 1, $cursorX);
    $confettis .= $prompt->bgGreen(str_repeat(' ', $celebrationMessageLength));

    echo $confettis;

    $ys = array_column($confetti, 'y');
    $prevLowestY = (int) floor(min($ys));

    $prompt->hideCursor();
    usleep((int) $usleep / 4);
}
