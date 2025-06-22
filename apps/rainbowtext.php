<?php

// Print an animated gradient version of the word "howdy" in a true-colour capable terminal.
// Run: php apps/rainbowtext.php

$word = "howdy";
$length = strlen($word);

// Choose two hues (in degrees) to span a small gradient. Feel free to tweak.
$startHue = 300;  // magenta-ish
$endHue   = 60;   // yellow-ish

// Convenience: convert HSV (0-360,0-1,0-1) to RGB (0-255 each)
function hsv2rgb(float $h, float $s, float $v): array
{
    $c = $v * $s;           // chroma
    $x = $c * (1 - abs(fmod($h / 60.0, 2) - 1));
    $m = $v - $c;
    $r = $g = $b = 0;

    if ($h < 60) {
        [$r, $g, $b] = [$c, $x, 0];
    } elseif ($h < 120) {
        [$r, $g, $b] = [$x, $c, 0];
    } elseif ($h < 180) {
        [$r, $g, $b] = [0, $c, $x];
    } elseif ($h < 240) {
        [$r, $g, $b] = [0, $x, $c];
    } elseif ($h < 300) {
        [$r, $g, $b] = [$x, 0, $c];
    } else {
        [$r, $g, $b] = [$c, 0, $x];
    }

    return [
        (int)(($r + $m) * 255),
        (int)(($g + $m) * 255),
        (int)(($b + $m) * 255),
    ];
}

// Hide cursor so it's not visible during animation.
echo "\033[?25l";

// Start at a random hue and then step by a small delta each loop so
// consecutive colours are always close (prevents jarring jumps).
$currentHue = 10; //random_int(0, 359);

// Helper to echo coloured text without a trailing newline.
function echo_coloured(string $text, int $r, int $g, int $b): void
{
    echo "\033[38;2;{$r};{$g};{$b}m{$text}\033[0m";
}

// Print the initial word in the first hue.
[$r0, $g0, $b0] = hsv2rgb($currentHue, 1, 1);
echo_coloured($word, $r0, $g0, $b0);
flush();

// Ensure cursor reappears when the script exits (Ctrl-C, etc.).
register_shutdown_function(function () {
    echo "\033[?25h"; // show cursor
    echo PHP_EOL;       // move to next line cleanly
});

while (true) {
    // Compute a small delta (±1-4°) and update the hue.
    $delta = random_int(8, 16) * 1; //(random_int(0, 1) === 0 ? 1 : -1);
    $currentHue = ($currentHue + $delta + 360) % 360;
    [$r, $g, $b] = hsv2rgb($currentHue, 1, 1);

    // Re-paint each character sequentially.
    for ($i = 0; $i < $length; $i++) {
        echo "\r";            // return to column 0
        if ($i > 0) {
            echo "\033[{$i}C"; // move right i columns
        }

        echo_coloured($word[$i], $r, $g, $b);
        flush();
        usleep(30000); // 90ms delay between letters
    }
}
