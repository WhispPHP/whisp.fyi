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
        preg_match('/\x1b\[4;(\d+);(\d+)t/', $info, $matches) || exit("Failed to parse terminal dimensions. Your terminal might not support pixel queries.\nReceived: " . bin2hex($info) . "\nMake sure you're using Kitty, Ghostty, or WezTerm.\n");
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
        return $this->button === 0 || $this->button === 32;
    }

    public function isRightButtonHeld(): bool
    {
        return $this->button === 2 || $this->button === 33;
    }

    public function isMiddleButtonHeld(): bool
    {
        return $this->button === 1 || $this->button === 34;
    }
}

class Draw
{
    public array $color = [];
    public int $brushSize = 15;
    public int $brushSizeMin = 5;
    public int $brushSizeMax = 50;
    public int $imageId = 1;

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

    public function draw(int $x, int $y)
    {
        $id = $this->imageId++;
        $brushRadius = $this->brushSize / 2;

        // Create RGBA pixel data for a circle
        $pixels = '';
        for ($py = 0; $py < $this->brushSize; $py++) {
            for ($px = 0; $px < $this->brushSize; $px++) {
                $dx = $px - $brushRadius;
                $dy = $py - $brushRadius;
                $distance = sqrt($dx * $dx + $dy * $dy);

                if ($distance <= $brushRadius) {
                    // Inside circle - use brush color
                    $pixels .= chr($this->color['r']) . chr($this->color['g']) . chr($this->color['b']) . chr(255);
                } else {
                    // Outside circle - transparent
                    $pixels .= chr(0) . chr(0) . chr(0) . chr(0);
                }
            }
        }

        $payload = base64_encode($pixels);

        echo "\e_Gf=32,s={$this->brushSize},v={$this->brushSize},a=T,i={$id},X={$x},Y={$y},z=-1,C=1;{$payload}\e\\";
    }
}

$terminal = new Terminal();
$draw = new Draw($terminal, 'af87ff');

// Enable mouse tracking with pixel precision
$terminal->trackMouse();
$terminal->clearText();
$terminal->hideCursor();

echo "Click and drag to draw! Right-click to clear.\r";

$lastX = null;
$lastY = null;
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

    $shouldDraw = $mouse->isLeftButtonHeld();
    $shouldClear = $mouse->isRightButton() && $mouse->down;

    if ($shouldClear) {
        $draw->clear();
        $terminal->clearBuffer();
        continue;
    } elseif ($mouse->isReleased()) {
        $lastX = null;
        $lastY = null;
        $terminal->clearBuffer();
        continue;
    }

    if ($shouldDraw === false) {
        $terminal->clearBuffer();
        continue;
    }

    // Interpolate between points if moved too far (fills gaps from fast movement)
    // Add after we have/show the dotted approach
    if ($lastX !== null && $lastY !== null) {
        $distance = sqrt(pow($mouse->x - $lastX, 2) + pow($mouse->y - $lastY, 2));

        // If we've moved far, draw intermediate points
        if ($distance > $minDistance) {
            $steps = ceil($distance / $minDistance);
            for ($step = 1; $step <= $steps; $step++) {
                $t = $step / $steps;
                $interpX = ($lastX + ($mouse->x - $lastX) * $t);
                $interpY = ($lastY + ($mouse->y - $lastY) * $t);

                $drawX = $interpX - ($draw->brushSize / 2);
                $drawY = $interpY - ($draw->brushSize / 2);

                $draw->draw((int)$drawX, (int)$drawY);
            }
        }
    }
    // First point
    $drawX = $mouse->x - ($draw->brushSize / 2);
    $drawY = $mouse->y - ($draw->brushSize / 2);
    $draw->draw((int)$drawX, (int)$drawY);

    // Update last position
    $lastX = $mouse->x;
    $lastY = $mouse->y;

    $terminal->clearBuffer();
}

$terminal->cleanup();
$draw->clear();
