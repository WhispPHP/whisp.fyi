<?php
// Few parts that goes into this:
// 1. Find out terminal dimensions, in pixels
// 2. Detect mouse position & button state
// 3. Draw at the mouse position
// 4. Tidy up the drawings (alt buffer)

class Terminal
{
    protected string $originalStty;
    public int $width;
    public int $height;
    public string $buffer = '';

    public function __construct()
    {
        $this->originalStty = shell_exec('stty -g');
        shell_exec('stty -icanon -echo'); // Raw mode: icanon = no line buffering, echo = no echo
        [$this->height, $this->width] = $this->size();
    }

    public function read()
    {
        $this->buffer .= fread(STDIN, 1);
        return $this->buffer;
    }

    public function clearBuffer()
    {
        $this->buffer = '';
    }

    public function trackMouse()
    {
        echo "\033[?1003h";  // Track all mouse events
        echo "\033[?1016h";  // Use SGR pixel format
    }

    public function untrackMouse()
    {
        echo "\033[?1003l";   // Disable mouse tracking
        echo "\033[?1016l";   // Disable SGR pixel mode
    }

    public function hideCursor()
    {
        echo "\033[?25l";
    }

    public function showCursor()
    {
        echo "\033[?25h";
    }

    public function clearText()
    {
        echo "\033[2J\033[H"; // Clear screen and move to home
    }

    public function cleanup()
    {
        $this->untrackMouse();
        $this->showCursor();
        shell_exec("stty $this->originalStty");
    }

    public function size(): array
    {
        echo "\e[14t"; // Request pixel dimensions (CSI )
        $info = fread(STDIN, 14);
        $result = preg_match('/\x1b\[4;(\d+);(\d+)t/', $info, $matches);

        if ($result === false || $result === 0) {
            $this->cleanup();
            throw new Exception("Failed to parse terminal dimensions. Your terminal might not support pixel queries.\nReceived: " . bin2hex($info) . "\nMake sure you're using Kitty, Ghostty, or WezTerm.\n");
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}

class Mouse
{
    public bool $isMouseEvent = false;
    public int $button = 0;
    public int $x = 0;
    public int $y = 0;
    public string $event = '';
    public bool $down = false;
    public bool $isShift = false;
    public bool $isAlt = false;
    public bool $isCtrl = false;

    public function __construct(protected string $buffer)
    {
        $this->parseMouseEvent($buffer);
    }

    public function parseMouseEvent(string $buffer): void
    {
        $this->isMouseEvent = preg_match('/\x1b\[<(?<button>\d+);(?<x>\d+);(?<y>\d+)(?<event>[Mm])/', $buffer, $matches);
        if ($this->isMouseEvent === false || $this->isMouseEvent === 0) {
            $this->isMouseEvent = false;
            return;
        }
        $this->isMouseEvent = true;

        $this->button = (int) $matches['button'];
        $this->x = (int) $matches['x'];
        $this->y = (int) $matches['y'];
        $this->event = $matches['event'];
        $this->down = $this->event === 'M';
        $this->isShift = ($this->button & 4) !== 0;
        $this->isAlt = ($this->button & 8) !== 0;
        $this->isCtrl = ($this->button & 16) !== 0;

        $this->button = $this->button & 3; // Get the actual button (0=left, 1=middle, 2=right, 3=released, 32=motion_left, 33=motion_middle, 34=motion_right, 35=motion, 64=scroll wheel up, 65=scroll wheel down)
    }

    public function isLeftButton(): bool
    {
        return $this->button === 0;
    }

    public function isRightButton(): bool
    {
        return $this->button === 2;
    }

    public function isMiddleButton(): bool
    {
        return $this->button === 1;
    }

    public function isReleased(): bool
    {
        return $this->button === 3;
    }

    public function isMotion(): bool
    {
        return $this->button === 35;
    }

    public function isScrollWheelUp(): bool
    {
        return $this->button === 64;
    }

    public function isScrollWheelDown(): bool
    {
        return $this->button === 65;
    }

    public function isLeftButtonHeld(): bool
    {
        return ($this->button === 0 && $this->down) || $this->button === 32;
    }

    public function isRightButtonHeld(): bool
    {
        return ($this->button === 2 && $this->down) || $this->button === 33;
    }

    public function isMiddleButtonHeld(): bool
    {
        return ($this->button === 1 && $this->down) || $this->button === 34;
    }
}

class Draw
{
    public array $color = [];
    public int $brushSize = 15;
    public int $minDistance = 3;
    public int $imageId = 1;
    public int $lastX = 0;
    public int $lastY = 0;

    protected Terminal $terminal;

    public function __construct(Terminal $terminal, string $color)
    {
        $this->terminal = $terminal;
        $this->color = [
            'r' => hexdec(substr($color, 0, 2)),
            'g' => hexdec(substr($color, 2, 2)),
            'b' => hexdec(substr($color, 4, 2)),
            'a' => 255,
        ];
    }

    public function clear()
    {
        echo "\e_Ga=d\e\\"; // Delete all visible Kitty graphics placements
        $this->imageId = 1;
    }

    public function filled()
    {
        return chr($this->color['r']) . chr($this->color['g']) . chr($this->color['b']) . chr(255);
    }

    public function transparent()
    {
        return chr(0) . chr(0) . chr(0) . chr(0);
    }

    public function draw(int $x, int $y)
    {
        $brushRadius = (int) ($this->brushSize / 2);
        $x -= $brushRadius;
        $y -= $brushRadius;

        // Collect all centres to be stamped
        $centres = [];

        if ($this->lastX !== 0 && $this->lastY !== 0) {
            $distance = sqrt(pow($x - $this->lastX, 2) + pow($y - $this->lastY, 2));

            // If we've moved far, collect interpolated centres
            if ($distance > $this->minDistance) {
                $steps = ceil($distance / $this->minDistance);
                for ($step = 1; $step <= $steps; $step++) {
                    $t = $step / $steps;
                    $interpX = (int) ($this->lastX + ($x - $this->lastX) * $t);
                    $interpY = (int) ($this->lastY + ($y - $this->lastY) * $t);
                    $centres[] = ['x' => $interpX, 'y' => $interpY];
                }
            } else {
                $centres[] = ['x' => (int)$x, 'y' => (int)$y];
            }
        }

        // Always include the final point
        $centres[] = ['x' => (int)$x, 'y' => (int)$y];

        // Compute bounding box that encompasses all brush discs
        $minX = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $minY = PHP_INT_MAX;
        $maxY = PHP_INT_MIN;

        foreach ($centres as $c) {
            $minX = min($minX, $c['x'] - $brushRadius);
            $maxX = max($maxX, $c['x'] + $brushRadius);
            $minY = min($minY, $c['y'] - $brushRadius);
            $maxY = max($maxY, $c['y'] + $brushRadius);
        }

        $width = ($maxX - $minX) + 1;
        $height = ($maxY - $minY) + 1;

        // Allocate RGBA canvas (4 bytes per pixel)
        $pixels = str_repeat($this->transparent(), $width * $height);

        // Stamp each brush disc into the canvas
        foreach ($centres as $c) {
            $cx = $c['x'] - $minX;  // Local coordinates inside canvas
            $cy = $c['y'] - $minY;

            for ($py = -$brushRadius; $py <= $brushRadius; $py++) {
                for ($px = -$brushRadius; $px <= $brushRadius; $px++) {
                    // Check if pixel is within brush radius
                    $distSq = $px * $px + $py * $py;
                    if ($distSq > $brushRadius * $brushRadius) {
                        continue;
                    }

                    $ix = $cx + $px;
                    $iy = $cy + $py;

                    // Skip if out of bounds
                    if ($ix < 0 || $ix >= $width || $iy < 0 || $iy >= $height) {
                        continue;
                    }

                    // Write RGBA pixel
                    $offset = ($iy * $width + $ix) * 4;
                    $pixels[$offset]     = chr($this->color['r']);
                    $pixels[$offset + 1] = chr($this->color['g']);
                    $pixels[$offset + 2] = chr($this->color['b']);
                    $pixels[$offset + 3] = chr(255);  // Opaque
                }
            }
        }

        // Compress, encode, and emit single escape sequence
        $compressed = gzcompress($pixels);
        $payload = base64_encode($compressed);
        $sequence = "\e_Gf=32,o=z,s={$width},v={$height},a=T,i={$this->imageId},X={$minX},Y={$minY},z=-1,C=1;{$payload}\e\\";
        echo $sequence;

        // House-keeping
        $this->lastX = $x;
        $this->lastY = $y;
        $this->imageId++;
    }
}

function generateRandomPastelColor(): string
{
    // Generate random hue (0-360)
    $hue = rand(0, 360);
    // Keep saturation medium-high (60-80%) for vibrant but not too intense colors
    $saturation = rand(60, 80);
    // Keep lightness medium-high (65-80%) for pastel/bright colors that work on any background
    $lightness = rand(65, 80);

    // Convert HSL to RGB
    $h = $hue / 360;
    $s = $saturation / 100;
    $l = $lightness / 100;

    if ($s == 0) {
        $r = $g = $b = $l;
    } else {
        $hue2rgb = function ($p, $q, $t) {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1 / 2) return $q;
            if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
            return $p;
        };

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = $hue2rgb($p, $q, $h + 1 / 3);
        $g = $hue2rgb($p, $q, $h);
        $b = $hue2rgb($p, $q, $h - 1 / 3);
    }

    return sprintf('%02x%02x%02x', round($r * 255), round($g * 255), round($b * 255));
}

$terminal = new Terminal();
$draw = new Draw($terminal, generateRandomPastelColor());

// Enable mouse tracking with pixel precision
$terminal->trackMouse();
$terminal->clearText();
$terminal->hideCursor();


// Register shutdown function to ensure cleanup happens no matter how script exits
register_shutdown_function(function () use ($terminal, $draw) {
    $terminal->cleanup();
    $draw->clear();
    echo "\n"; // Add newline for clean exit
});

// Handle signals (Ctrl+C, etc.) if pcntl is available
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $signalHandler = function () use ($terminal, $draw) {
        $terminal->cleanup();
        $draw->clear();
        echo "\n";
        exit(0);
    };
    pcntl_signal(SIGINT, $signalHandler);  // Ctrl+C
    pcntl_signal(SIGTERM, $signalHandler); // kill command
}

echo "Click and drag to draw! Right-click to clear. Press q to quit.\r";

$minDistance = $draw->brushSize * 0.2; // Draw more frequently for smoother lines
$shouldDraw = false;
$shouldRun = true;

while ($shouldRun) {
    $buffer = $terminal->read();
    if ($buffer === false) {
        $shouldRun = false;
        break;
    }

    if ($buffer === 'q') {
        $shouldRun = false;
        break;
    }

    if ($buffer === 'c') {
        $draw->clear();
        continue;
    }

    $mouse = new Mouse($buffer);
    if ($mouse->isMouseEvent === false) {
        continue;
    }

    $terminal->clearBuffer();

    $shouldDraw = $mouse->isLeftButtonHeld();
    $shouldClear = $mouse->isRightButton() && $mouse->down;

    if ($shouldClear) {
        $draw->clear();
        continue;
    } elseif ($mouse->isReleased()) {
        $draw->lastX = 0;
        $draw->lastY = 0;
        continue;
    }

    if ($shouldDraw === false) {
        continue;
    }

    $draw->draw($mouse->x, $mouse->y);
}

$terminal->cleanup();
$draw->clear();
