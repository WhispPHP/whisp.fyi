<?php

// Save current terminal settings
$stty_settings = shell_exec('stty -g');

// Function to cleanup terminal on exit
function cleanup() {
    global $stty_settings;
    echo "\e_Ga=d\e\\";   // Delete all visible Kitty graphics placements
    echo "\033[2J\033[H"; // Clear screen and move to home
    echo "\033[?1003l";   // Disable mouse tracking
    echo "\033[?1016l";   // Disable SGR pixel mode
    echo "\033[?25h";     // Show cursor
    echo "\n\nCleaning up...\n";
    shell_exec("stty $stty_settings");
    exit(0);
}

// Register signal handlers for graceful shutdown
pcntl_signal(SIGINT, function() {
    cleanup();
});
pcntl_signal(SIGTERM, function() {
    cleanup();
});

// Put terminal in raw mode
shell_exec('stty -icanon -echo');

// Request pixel dimensions
echo "\e[14t";
fflush(STDOUT);

// Set non-blocking mode to prevent hanging
stream_set_blocking(STDIN, false);
usleep(200000); // Wait 200ms for response

$info = '';
$attempts = 0;
while ($attempts < 20) {
    $char = fread(STDIN, 1);
    if ($char !== false && $char !== '') {
        $info .= $char;
        if ($char === 't') {
            break;
        }
    }
    usleep(10000); // 10ms between attempts
    $attempts++;
}

// Restore blocking mode
stream_set_blocking(STDIN, true);

// Parse the response
if (preg_match('/\x1b\[4;(\d+);(\d+)t/', $info, $matches)) {
    $termHeight = (int)$matches[1];
    $termWidth = (int)$matches[2];
} else {
    die("Failed to parse terminal dimensions. Your terminal might not support pixel queries.\nReceived: " . bin2hex($info) . "\nMake sure you're using Kitty, Ghostty, or WezTerm.\n");
}

// Calculate brush size as 0.5% of terminal width (smaller)
$brushSize = (int)($termWidth * 0.005);

// Brush color: #af87ff (default)
$brushR = 175;
$brushG = 135;
$brushB = 255;

// Color palette
$colors = [
    ['r' => 255, 'g' => 0, 'b' => 0],     // Red
    ['r' => 255, 'g' => 127, 'b' => 0],   // Orange
    ['r' => 255, 'g' => 255, 'b' => 0],   // Yellow
    ['r' => 0, 'g' => 255, 'b' => 0],     // Green
    ['r' => 0, 'g' => 255, 'b' => 255],   // Cyan
    ['r' => 0, 'g' => 0, 'b' => 255],     // Blue
    ['r' => 175, 'g' => 135, 'b' => 255], // Purple (default)
    ['r' => 255, 'g' => 0, 'b' => 255],   // Magenta
    ['r' => 255, 'g' => 255, 'b' => 255], // White
    ['r' => 0, 'g' => 0, 'b' => 0],       // Black
];

// Color swatch settings
$swatchSize = 60;
$borderSize = 4;
$numColors = count($colors);
$totalSwatchWidth = $numColors * $swatchSize;
$spacing = ($termWidth - $totalSwatchWidth) / ($numColors + 1);
$swatchY = $termHeight - $swatchSize - 20;

// Color swatch bounds for hover detection
$colorSwatches = [];
$activeSwatchIndex = 6; // Start with purple (index 6)

// Enable mouse tracking with pixel precision
echo "\033[?1003h";  // Track all mouse events
echo "\033[?1016h";  // Use SGR pixel format
fflush(STDOUT);

// Clear screen and hide cursor
echo "\033[2J\033[H\033[?25l";

echo "Terminal: {$termWidth}x{$termHeight}px, Brush size: {$brushSize}px\n";
echo "Click and drag to draw! Right-click to clear. Hover over colors to switch.\n\n";

// Function to draw a color swatch square with optional border
function drawColorSwatch($x, $y, $size, $r, $g, $b, $id, $withBorder = false, $borderSize = 4) {
    $x = (int)$x;
    $y = (int)$y;

    if ($withBorder) {
        // Draw white border background first
        $borderTotal = $size + ($borderSize * 2);
        $borderPixels = '';
        for ($py = 0; $py < $borderTotal; $py++) {
            for ($px = 0; $px < $borderTotal; $px++) {
                $borderPixels .= chr(255) . chr(255) . chr(255) . chr(255); // White
            }
        }
        $borderPayload = base64_encode($borderPixels);
        echo "\033[H";
        echo "\e_Gf=32,s={$borderTotal},v={$borderTotal},a=T,i=" . ($id - 1) . ",X=" . ($x - $borderSize) . ",Y=" . ($y - $borderSize) . ",z=-1;{$borderPayload}\e\\";
    } else {
        // Delete the border image if no border wanted
        echo "\033[H";
        echo "\e_Ga=d,i=" . ($id - 1) . "\e\\";
    }

    // Create RGBA pixel data for the color square
    $pixels = '';
    for ($py = 0; $py < $size; $py++) {
        for ($px = 0; $px < $size; $px++) {
            $pixels .= chr($r) . chr($g) . chr($b) . chr(255);
        }
    }

    $payload = base64_encode($pixels);

    echo "\033[H";
    echo "\e_Gf=32,s={$size},v={$size},a=T,i={$id},X={$x},Y={$y},z=-1;{$payload}\e\\";
    fflush(STDOUT);
}

// Draw color palette swatches
$swatchId = 1000000; // Use high IDs to avoid conflicts with brush strokes
for ($i = 0; $i < $numColors; $i++) {
    $swatchX = (int)($spacing * ($i + 1) + $swatchSize * $i);

    // Draw with border if this is the active swatch
    $isActive = ($i === $activeSwatchIndex);
    drawColorSwatch($swatchX, $swatchY, $swatchSize, $colors[$i]['r'], $colors[$i]['g'], $colors[$i]['b'], $swatchId + ($i * 2) + 1, $isActive, $borderSize);

    // Store bounds for hover detection
    $colorSwatches[] = [
        'x1' => $swatchX,
        'y1' => $swatchY,
        'x2' => $swatchX + $swatchSize,
        'y2' => $swatchY + $swatchSize,
        'color' => $colors[$i],
        'x' => $swatchX,
        'index' => $i
    ];
}

// Function to draw a circular brush at a given position
function drawBrush($x, $y, $brushSize, $r, $g, $b, $id) {
    // Ensure coordinates are integers
    $x = (int)$x;
    $y = (int)$y;

    $brushRadius = $brushSize / 2;

    // Create RGBA pixel data for a circle
    $pixels = '';
    for ($py = 0; $py < $brushSize; $py++) {
        for ($px = 0; $px < $brushSize; $px++) {
            $dx = $px - $brushRadius;
            $dy = $py - $brushRadius;
            $distance = sqrt($dx * $dx + $dy * $dy);

            if ($distance <= $brushRadius) {
                // Inside circle - use brush color
                $pixels .= chr($r) . chr($g) . chr($b) . chr(255);
            } else {
                // Outside circle - transparent
                $pixels .= chr(0) . chr(0) . chr(0) . chr(0);
            }
        }
    }

    $payload = base64_encode($pixels);

    // Move cursor to home position, then send graphics command
    echo "\033[H"; // Move cursor to home (1,1)
    echo "\e_Gf=32,s={$brushSize},v={$brushSize},a=T,i={$id},X={$x},Y={$y},z=-1;{$payload}\e\\";
    fflush(STDOUT);
}

// Read mouse events
$buffer = '';
$imageId = 1;
$lastX = null;
$lastY = null;
$minDistance = $brushSize * 0.2; // Draw more frequently for smoother lines
$leftButtonDown = false;

try {
    while (true) {
        // Dispatch signals
        pcntl_signal_dispatch();

        $char = fread(STDIN, 1);
        if ($char === false) break;

        $buffer .= $char;

        // SGR pixel format: ESC[<Cb;Cx;Cy;M or ESC[<Cb;Cx;Cy;m
        if (preg_match('/\x1b\[<(\d+);(\d+);(\d+)([Mm])/', $buffer, $matches)) {
            $button = (int)$matches[1];
            $pixelX = (int)$matches[2];
            $pixelY = (int)$matches[3];
            $event = $matches[4]; // M = press, m = release

            // Check if hovering over a color swatch
            foreach ($colorSwatches as $swatch) {
                if ($pixelX >= $swatch['x1'] && $pixelX <= $swatch['x2'] &&
                    $pixelY >= $swatch['y1'] && $pixelY <= $swatch['y2']) {

                    // Only update if switching to a different color
                    if ($swatch['index'] !== $activeSwatchIndex) {
                        $oldActiveIndex = $activeSwatchIndex;
                        $activeSwatchIndex = $swatch['index'];

                        // Switch to this color
                        $brushR = $swatch['color']['r'];
                        $brushG = $swatch['color']['g'];
                        $brushB = $swatch['color']['b'];

                        // Redraw old active swatch without border
                        $oldSwatch = $colorSwatches[$oldActiveIndex];
                        drawColorSwatch($oldSwatch['x'], $swatchY, $swatchSize,
                            $colors[$oldActiveIndex]['r'], $colors[$oldActiveIndex]['g'], $colors[$oldActiveIndex]['b'],
                            $swatchId + ($oldActiveIndex * 2) + 1, false, $borderSize);

                        // Redraw new active swatch with border
                        drawColorSwatch($swatch['x'], $swatchY, $swatchSize,
                            $swatch['color']['r'], $swatch['color']['g'], $swatch['color']['b'],
                            $swatchId + ($swatch['index'] * 2) + 1, true, $borderSize);
                    }
                    break;
                }
            }

            // Track button state
            if ($button === 0 && $event === 'M') {
                // Left button pressed
                $leftButtonDown = true;
            } elseif ($button === 2 && $event === 'M') {
                // Right button pressed - clear all drawings
                echo "\e_Ga=d\e\\";
                fflush(STDOUT);
                $imageId = 1; // Reset image counter

                // Redraw color swatches with active border
                for ($i = 0; $i < $numColors; $i++) {
                    $swatchX = (int)($spacing * ($i + 1) + $swatchSize * $i);
                    $isActive = ($i === $activeSwatchIndex);
                    drawColorSwatch($swatchX, $swatchY, $swatchSize, $colors[$i]['r'], $colors[$i]['g'], $colors[$i]['b'],
                        $swatchId + ($i * 2) + 1, $isActive, $borderSize);
                }

                $buffer = '';
                continue;
            } elseif ($event === 'm') {
                // Any button released
                $leftButtonDown = false;
                $lastX = null;
                $lastY = null;
            }

            // Only draw if left button is held down (button 0 pressed or button 32 motion)
            $isLeftButtonHeld = ($button === 0 || $button === 32) && $leftButtonDown;

            if ($isLeftButtonHeld) {
                // Interpolate between points if moved too far (fills gaps from fast movement)
                if ($lastX !== null && $lastY !== null) {
                    $distance = sqrt(pow($pixelX - $lastX, 2) + pow($pixelY - $lastY, 2));

                    // If we've moved far, draw intermediate points
                    if ($distance > $minDistance) {
                        $steps = ceil($distance / $minDistance);
                        for ($step = 1; $step <= $steps; $step++) {
                            $t = $step / $steps;
                            $interpX = (int)($lastX + ($pixelX - $lastX) * $t);
                            $interpY = (int)($lastY + ($pixelY - $lastY) * $t);

                            $drawX = $interpX - ($brushSize / 2);
                            $drawY = $interpY - ($brushSize / 2);

                            drawBrush($drawX, $drawY, $brushSize, $brushR, $brushG, $brushB, $imageId++);
                        }
                    } else {
                        // Normal draw
                        $drawX = $pixelX - ($brushSize / 2);
                        $drawY = $pixelY - ($brushSize / 2);
                        drawBrush($drawX, $drawY, $brushSize, $brushR, $brushG, $brushB, $imageId++);
                    }
                } else {
                    // First point
                    $drawX = $pixelX - ($brushSize / 2);
                    $drawY = $pixelY - ($brushSize / 2);
                    drawBrush($drawX, $drawY, $brushSize, $brushR, $brushG, $brushB, $imageId++);
                }

                // Update last position
                $lastX = $pixelX;
                $lastY = $pixelY;
            }

            // Clear the buffer
            $buffer = '';
        }

        // Keep buffer from growing too large
        if (strlen($buffer) > 100) {
            $buffer = substr($buffer, -50);
        }
    }
} finally {
    cleanup();
}
