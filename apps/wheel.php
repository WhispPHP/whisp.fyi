<?php
// apps/wheel.php
// Render an animated colour wheel in the terminal using 24-bit ANSI colours.
// Run: php apps/wheel.php

// === Helper: HSV → RGB ===
function hsv2rgb(float $h, float $s, float $v): array
{
    $c = $v * $s;
    $x = $c * (1 - abs(fmod($h / 60.0, 2) - 1));
    $m = $v - $c;

    switch (true) {
        case ($h < 60):
            $rp = [$c, $x, 0];
            break;
        case ($h < 120):
            $rp = [$x, $c, 0];
            break;
        case ($h < 180):
            $rp = [0, $c, $x];
            break;
        case ($h < 240):
            $rp = [0, $x, $c];
            break;
        case ($h < 300):
            $rp = [$x, 0, $c];
            break;
        default:
            $rp = [$c, 0, $x];
            break;
    }
    return [
        (int)(($rp[0] + $m) * 255),
        (int)(($rp[1] + $m) * 255),
        (int)(($rp[2] + $m) * 255),
    ];
}

// === Terminal helpers ===
function setColour(int $r, int $g, int $b): void
{
    echo "\033[38;2;{$r};{$g};{$b}m";
}
function resetColour(): void
{
    echo "\033[0m";
}

// Hide cursor; reveal on exit
echo "\033[?25l";
register_shutdown_function(function () {
    resetColour();
    echo "\033[?25h" . PHP_EOL;
});

// === Wheel parameters ===
$radius      = 12;            // characters (wheel radius)
$thickness   = 2;             // ring thickness
$angleOffset = 0;             // animated offset in degrees
$step        = 5;             // degrees to shift each frame
$delayUs     = 50000;         // 50 ms between frames
$diameter    = $radius * 2 + 1;

// Pre-compute coordinate grid
$coords = [];
for ($y = -$radius; $y <= $radius; $y++) {
    $row = [];
    for ($x = -$radius; $x <= $radius; $x++) {
        $dist = hypot($x, $y);
        if ($dist >= $radius - $thickness && $dist <= $radius + 0.5) {
            // Point is on the ring – store its base angle
            $angle = rad2deg(atan2($y, $x)); // -180 .. 180
            $angle = ($angle + 360) % 360;   // wrap to 0..359
            $row[] = $angle;
        } else {
            $row[] = null; // empty space
        }
    }
    $coords[] = $row;
}

// === Main animation loop ===
while (true) {
    echo "\033[H"; // move cursor to home (top-left)

    foreach ($coords as $row) {
        foreach ($row as $baseAngle) {
            if ($baseAngle === null) {
                echo " ";
            } else {
                $hue = ($baseAngle + $angleOffset) % 360;
                [$r, $g, $b] = hsv2rgb($hue, 1, 1);
                setColour($r, $g, $b);
                echo "█"; // full block with that colour
            }
        }
        resetColour();
        echo "\n";
    }

    $angleOffset = ($angleOffset + $step) % 360;
    resetColour();
    flush();
    usleep($delayUs);
}
