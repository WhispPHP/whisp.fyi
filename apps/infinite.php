<?php

declare(strict_types=1);


require_once __DIR__.'/vendor/autoload.php';

use Laravel\Prompts\Prompt;
use Laravel\Prompts\Key;
use Laravel\Prompts\TextPrompt;
use Whisp\Mouse\Mouse;
use Whisp\Mouse\MouseButton;
use Whisp\Mouse\MouseEvent;
use Whisp\Mouse\MouseMotion;

class AsciiArt implements \JsonSerializable
{
    private array $artArray = [];
    private int $width = 0;
    private int $height = 0;
    private array $position;
    private string $color;
    private string $originalArt;

    public function __construct(string $art, array $position, string $color = "\033[36m")
    {
        $this->position = $position;
        $this->color = $color;
        $this->originalArt = $art;
        $this->parseArt($art);
    }

    public function jsonSerialize(): array
    {
        return [
            'art' => $this->originalArt,
            'position' => $this->position,
            'color' => $this->color,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self($data['art'], $data['position'], $data['color']);
    }

    private function parseArt(string $art): void
    {
        $lines = explode("\n", $art);
        $this->height = count($lines);
        $this->width = max(array_map('strlen', $lines));

        foreach ($lines as $y => $line) {
            $chars = mb_str_split($line);
            foreach ($chars as $x => $char) {
                $this->artArray[$x][$y] = $char;
            }
        }
    }

    public function isWithinBounds(int $x, int $y): bool
    {
        $relativeX = $x - $this->position['x'];
        $relativeY = $y - $this->position['y'];
        return isset($this->artArray[$relativeX][$relativeY]);
    }

    public function getGlyph(int $x, int $y): ?string
    {
        if (!$this->isWithinBounds($x, $y)) {
            return null;
        }

        $relativeX = $x - $this->position['x'];
        $relativeY = $y - $this->position['y'];
        return $this->color . $this->artArray[$relativeX][$relativeY] . "\033[0m";
    }

    public function getPosition(): array
    {
        return $this->position;
    }

    public function setPosition(array $position): void
    {
        $this->position = $position;
    }

    public function getDimensions(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }
}

class InfiniteCanvas extends Prompt
{
    private array $viewport = [
        'x' => 0,
        'y' => 0,
        'width' => 0,
        'height' => 0,
    ];
    private array $initialViewport = [
        'x' => 0,
        'y' => 0,
    ];
    private array $lastViewport = [
        'x' => 0,
        'y' => 0,
        'width' => 0,
        'height' => 0,
    ];
    private string $lastFrame = '';
    private bool $viewportChanged = false;
    private string $instanceId;
    private array $canvas = [];  // sparse array: $canvas[$x][$y] = ['glyph' => string, 'color' => string]
    private array $glyphs = ['✦', '✧', '⋆', '✫', '✬', '✯', '✡', ' '];  // Unicode stars
    private string $originStar = "\033[33m⭐\033[0m";  // Yellow star with ANSI color
    private Mouse $mouse;
    private ?array $dragStart = null;
    private array $asciiArt = [];
    private string $storageFile;

    private string $earth = <<<EARTH
             _____
          .-'.  ':'-.
        .''::: .:    '.
       /   :::::'      \
      ;.    ':' `       ;
      |       '..       |
      ; '      ::::.    ;
       \       '::::   /
        '.      :::  .'
jgs        '-.___'_.-'
EARTH;

    public function __construct()
    {
        $this->instanceId = uniqid('canvas_', true);
        $this->storageFile = $this->createStorageFilePath();
        $this->registerSignalHandlers();
        $this->freshDimensions();
        $this->setupMouseListening();
        $this->listenToKeys();

        // Initialize viewport dimensions
        $this->viewport['width'] = $this->terminal()->cols();
        $this->viewport['height'] = $this->terminal()->lines();

        // Center the viewport on (0,0)
        $this->viewport['x'] = -intdiv($this->viewport['width'], 2);
        $this->viewport['y'] = -intdiv($this->viewport['height'], 2);

        // Store initial viewport coordinates
        $this->initialViewport['x'] = $this->viewport['x'];
        $this->initialViewport['y'] = $this->viewport['y'];

        // Load saved state or add Earth as first ASCII art
        if (!$this->loadState()) {
            $this->addEarth();
        }
    }

    public function __destruct()
    {
        parent::__destruct();
        $this->writeDirectly($this->mouse->disable());
        // Nothing to clean up anymore.
    }

    private function createStorageFilePath(): string
    {
        // Determine a unique identifier for this user
        $identifier = $_SERVER['WHISP_USER_PUBLIC_KEY']
            ?? $_ENV['WHISP_USER_PUBLIC_KEY']
            ?? $_SERVER['WHISP_CLIENT_IP']
            ?? $_ENV['WHISP_CLIENT_IP']
            ?? uniqid('anon_', true);

        // Create a hash of the identifier - this will give us a fixed length string
        $hash = hash('sha256', $identifier);

        // Ensure the storage directory exists (…/storage/canvas)
        $dir = dirname(__DIR__) . '/storage/canvas';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . '/' . $hash . '.gz';
    }

    private function addEarth(): void
    {
        $position = [
            'x' => $this->initialViewport['x'] - 15,  // Adjust these values as needed
            'y' => $this->initialViewport['y'] - 15,
        ];
        $this->asciiArt['earth'] = new AsciiArt($this->earth, $position, "\033[36m");  // Cyan color
    }

    public function addAsciiArt(string $id, string $art, array $position, string $color = "\033[36m"): void
    {
        $this->asciiArt[$id] = new AsciiArt($art, $position, $color);
        $this->viewportChanged = true;
    }

    private function centerArt(string $id): void
    {
        if (!isset($this->asciiArt[$id])) {
            return;
        }

        $art = $this->asciiArt[$id];
        $dimensions = $art->getDimensions();
        $position = $art->getPosition();

        // Calculate where we need to move the viewport to center the art
        $this->viewport['x'] = $position['x'] + intdiv($dimensions['width'], 2) - intdiv($this->viewport['width'], 2);
        $this->viewport['y'] = $position['y'] + intdiv($dimensions['height'], 2) - intdiv($this->viewport['height'], 2);

        $this->viewportChanged = true;
    }

    public function value(): mixed
    {
        return true; // Required by parent class
    }

    private function registerSignalHandlers(): void
    {
        pcntl_signal(SIGINT, function () {
            $this->saveState();
            $this->terminal()->exit();  // Restore cursor and terminal state
            exit;
        });

        pcntl_signal(SIGTERM, function () {
            $this->saveState();
            $this->terminal()->exit();
            exit;
        });

        pcntl_signal(SIGWINCH, function () {
            $this->freshDimensions();
            $this->viewport['width'] = $this->terminal()->cols();
            $this->viewport['height'] = $this->terminal()->lines();
            $this->render();
        });
    }

    private function freshDimensions(): array
    {
        $this->terminal()->initDimensions();
        return [
            'cols' => $this->terminal()->cols(),
            'lines' => $this->terminal()->lines(),
        ];
    }

    private function generateBrightColor(): string
    {
        // Generate bright RGB values - at least one component will be 255
        $r = mt_rand(100, 255);
        $g = mt_rand(100, 255);
        $b = mt_rand(100, 255);

        // Ensure at least one component is maximum brightness
        $maxComponent = mt_rand(0, 2);
        match($maxComponent) {
            0 => $r = 255,
            1 => $g = 255,
            2 => $b = 255,
        };

        return sprintf("\033[38;2;%d;%d;%dm", $r, $g, $b);
    }

    private function getGlyphAt(int $x, int $y): string
    {
        // Check all ASCII art first
        foreach ($this->asciiArt as $art) {
            $glyph = $art->getGlyph($x, $y);
            if ($glyph !== null) {
                return $glyph;
            }
        }

        // Always show the yellow star at origin (0,0)
        if ($x === 0 && $y === 0) {
            return $this->originStar;
        }

        // Lazy generation of glyphs - only when first accessed
        if (!isset($this->canvas[$x][$y])) {
            // Use coordinates to seed RNG for deterministic generation
            mt_srand($x * 1000000 + $y);

            // Lower probability for stars to make them more special
            $shouldBeStar = mt_rand(0, 1000) < 1;
            $glyph = $shouldBeStar ? $this->glyphs[mt_rand(0, count($this->glyphs) - 2)] : ' ';

            // Generate and store both glyph and color
            $this->canvas[$x][$y] = [
                'glyph' => $glyph,
                'color' => $glyph === ' ' ? '' : $this->generateBrightColor(),
            ];

            mt_srand(); // Reset RNG state
        }

        $cell = $this->canvas[$x][$y];
        return $cell['color'] . $cell['glyph'] . "\033[0m";
    }

    protected function setupMouseListening(): void
    {
        $this->mouse = new Mouse();
        static::writeDirectly($this->mouse->enable());
        register_shutdown_function(function () {
            static::writeDirectly($this->mouse->disable());
        });
    }

    private function createBoxedText(string $text): string
    {
        $lines = explode("\n", $text);
        $maxLength = max(array_map('strlen', $lines));

        // Add padding to each line
        $lines = array_map(function ($line) use ($maxLength) {
            return "│ " . str_pad($line, $maxLength) . " │";
        }, $lines);

        // Create top and bottom borders
        $top = "╭" . str_repeat("─", $maxLength + 2) . "╮";
        $bottom = "╰" . str_repeat("─", $maxLength + 2) . "╯";

        // Combine all parts
        array_unshift($lines, $top);
        array_push($lines, $bottom);

        return implode("\n", $lines);
    }

    private function handleRightClick(MouseEvent $event): MouseEvent
    {
        // Store current viewport position
        $worldX = $event->x + $this->viewport['x'];
        $worldY = $event->y + $this->viewport['y'];

        // Check if we clicked inside any existing art
        foreach ($this->asciiArt as $id => $art) {
            if ($art->isWithinBounds($worldX, $worldY)) {
                return $event; // Ignore right clicks on existing art
            }
        }

        // Move cursor to clicked position
        echo sprintf("\033[%d;%dH", $event->y + 1, 1);

        $prompt = new TextPrompt(
            label: "What should this area say?",
            placeholder: "Your text here...",
            required: true,
            validate: fn (string $value) => match (true) {
                empty($value) => 'Text is required',
                strlen($value) > 60 => 'Text is too long',
                default => null,
            }
        );

        $text = $prompt->prompt();

        // After prompt, restore canvas state
        unset($prompt);
        $this->hideCursor();
        $this->terminal()->setTty('-icanon -isig -echo');
        $this->mouse->enable();

        if (!empty($text)) {
            // Create boxed version of the text
            $boxedText = $this->createBoxedText($text);

            // Generate a unique ID for this text art
            $id = 'text_' . uniqid();

            // Add as ASCII art at the clicked position
            $this->addAsciiArt($id, $boxedText, [
                'x' => $worldX,
                'y' => $worldY
            ], "\033[37m"); // White color for text boxes
        }

        $this->viewportChanged = true;
        return $event;
    }

    protected function handleMouseEvent(string $key): void
    {
        $event = $this->mouse->parseEvent($key);
        if (!$event) {
            return;
        }

        match ($event->mouseEvent) {
            MouseButton::LEFT => $this->handleLeftClick($event),
            MouseButton::RIGHT => $this->handleRightClick($event),
            MouseButton::MIDDLE => $this->startDrag($event),
            MouseMotion::MOTION_MIDDLE => $this->handleDrag($event),
            MouseButton::RELEASED_MIDDLE => $this->endDrag(),
            MouseButton::WHEEL_UP => $this->viewport['y'] -= 3,
            MouseButton::WHEEL_DOWN => $this->viewport['y'] += 3,
            default => null,
        };
    }

    private function handleLeftClick(MouseEvent $event): ?MouseEvent
    {
        // Convert screen coordinates to world coordinates
        $worldX = $event->x + $this->viewport['x'] - 1;
        $worldY = $event->y + $this->viewport['y'];

        // Check if we clicked inside any text box
        foreach ($this->asciiArt as $id => $art) {
            if ($art->isWithinBounds($worldX, $worldY)) {
                $newColor = $this->generateBrightColor();
                $art->setColor($newColor);
                $this->viewportChanged = true;
                return $event;
            }
        }

        // Don't allow overwriting the origin star
        if ($worldX === 0 && $worldY === 0) {
            return null;
        }

        // Add a new random star
        $glyph = $this->glyphs[mt_rand(0, count($this->glyphs) - 2)]; // Exclude space character
        $data = [
            'glyph' => $glyph,
            'color' => $this->generateBrightColor(),
        ];
        $this->canvas[$worldX][$worldY] = $data;
        $this->viewportChanged = true;

        return $event;
    }

    private function startDrag(MouseEvent $event): ?MouseEvent
    {
        $this->dragStart = [
            'mouseX' => $event->x,
            'mouseY' => $event->y,
            'viewportX' => $this->viewport['x'],
            'viewportY' => $this->viewport['y'],
        ];

        return $event;
    }

    private function handleDrag(MouseEvent $event): ?MouseEvent
    {
        if (!$this->dragStart) {
            return $event;
        }

        // Calculate how far we've dragged from the start position
        $deltaX = $event->x - $this->dragStart['mouseX'];
        $deltaY = $event->y - $this->dragStart['mouseY'];

        // Move viewport in opposite direction of drag (for natural feeling)
        $this->viewport['x'] = $this->dragStart['viewportX'] - $deltaX;
        $this->viewport['y'] = $this->dragStart['viewportY'] - $deltaY;

        return $event;
    }

    private function endDrag(): ?MouseEvent
    {
        $this->dragStart = null;

        return null;
    }

    public function listenToKeys(): void
    {
        $this->on('key', function ($key) {
            // Check for mouse events first (ESC [ M sequence)
            if ($key[0] === "\e" && strlen($key) > 2 && $key[2] === 'M') {
                $this->handleMouseEvent($key);
                return;
            }

            // Handle regular keyboard input
            $action = match ($key) {
                Key::UP => $this->viewport['y']--,
                Key::DOWN => $this->viewport['y']++,
                Key::LEFT => $this->viewport['x']--,
                Key::RIGHT => $this->viewport['x']++,
                'h' => $this->returnToHome(),
                'e' => $this->centerArt('earth'),
                'q' => $this->quit(),
                's' => $this->saveState(),
                default => null,
            };
        });
    }

    private function returnToHome(): void
    {
        $this->viewportChanged = ($this->viewport['x'] !== $this->initialViewport['x'] || $this->viewport['y'] !== $this->initialViewport['y']);
        $this->viewport['x'] = $this->initialViewport['x'];
        $this->viewport['y'] = $this->initialViewport['y'];
    }

    private function quit(): void
    {
        $this->saveState();
        $this->terminal()->exit();
        exit;
    }

    private function saveState(): void
    {
        $state = [
            'canvas' => $this->canvas,
            'asciiArt' => array_map(fn ($art) => $art->jsonSerialize(), $this->asciiArt),
        ];

        $compressed = gzcompress(json_encode($state), 9);

        $tempFile = $this->storageFile . '.tmp';
        file_put_contents($tempFile, $compressed);
        rename($tempFile, $this->storageFile);
    }

    private function loadState(): bool
    {
        try {
            if (!file_exists($this->storageFile)) {
                return false;
            }

            $compressed = file_get_contents($this->storageFile);
            if (!$compressed) {
                return false;
            }

            $data = json_decode(gzuncompress($compressed), true);
            if (!$data) {
                return false;
            }

            $this->canvas = $data['canvas'] ?? [];
            $this->asciiArt = [];
            foreach ($data['asciiArt'] ?? [] as $id => $artData) {
                $this->asciiArt[$id] = AsciiArt::fromArray($artData);
            }

            return true;
        } catch (\Exception $e) {
            error_log("Error loading state: " . $e->getMessage());
            return false;
        }
    }

    public function render(): void
    {
        $viewportPositionChanged =
            $this->viewport['x'] !== $this->lastViewport['x'] ||
            $this->viewport['y'] !== $this->lastViewport['y'] ||
            $this->viewport['width'] !== $this->lastViewport['width'] ||
            $this->viewport['height'] !== $this->lastViewport['height'];

        if (!$viewportPositionChanged && !$this->viewportChanged && $this->lastFrame !== '') {
            return;
        }

        $this->saveState();
        $this->viewportChanged = false;

        // Update last viewport position
        $this->lastViewport = [
            'x' => $this->viewport['x'],
            'y' => $this->viewport['y'],
            'width' => $this->viewport['width'],
            'height' => $this->viewport['height'],
        ];

        $output = "\033[H"; // Move cursor to home position

        for ($y = 0; $y < $this->viewport['height']; $y++) {
            $row = '';
            for ($x = 0; $x < $this->viewport['width']; $x++) {
                $worldX = $x + $this->viewport['x'];
                $worldY = $y + $this->viewport['y'];
                $row .= $this->getGlyphAt($worldX, $worldY);
            }
            $output .= $row . "\n";
        }

        // Add help text and viewport coordinates in bottom right
        $coords = sprintf('(%d,%d)', $this->viewport['x'], $this->viewport['y']);
        $help = "h = home ∙ e = earth ∙ q = quit ∙ Mouse: left = star/cycle color, right = text, middle drag = move";
        $statusLine = $help . " ∙ " . $coords;
        $output .= sprintf(
            "\033[%d;%dH%s",
            $this->viewport['height'],
            max(1, $this->viewport['width'] - strlen($statusLine)),
            $statusLine
        );

        $this->lastFrame = $output;
        echo $output;
    }
}

// Run it!
$canvas = new InfiniteCanvas();
$canvas->prompt();
