<?php

declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

use Laravel\Prompts\Prompt;
use Laravel\Prompts\Key;
use Whisp\Mouse\Mouse;
use Whisp\Mouse\MouseButton;
use Whisp\Mouse\MouseEvent;
use Whisp\Mouse\MouseMotion;

class GuessBox implements \JsonSerializable
{
    private array $position;
    private array $dimensions;
    private string $symbol;
    private string $borderColor;
    private string $backgroundColor;
    private string $symbolColor;
    private bool $locked;
    private bool $isGridSlot;
    private ?string $targetSymbol;

    public function __construct(array $position, array $dimensions, string $symbol = '', string $borderColor = "\033[36m", string $backgroundColor = '', string $symbolColor = '', bool $locked = false, bool $isGridSlot = false, ?string $targetSymbol = null)
    {
        $this->position = $position;
        $this->dimensions = $dimensions;
        $this->symbol = $symbol;
        $this->borderColor = $borderColor;
        $this->backgroundColor = $backgroundColor;
        $this->symbolColor = $symbolColor;
        $this->locked = $locked;
        $this->isGridSlot = $isGridSlot;
        $this->targetSymbol = $targetSymbol;
    }

    public function jsonSerialize(): array
    {
        return [
            'position' => $this->position,
            'dimensions' => $this->dimensions,
            'symbol' => $this->symbol,
            'borderColor' => $this->borderColor,
            'backgroundColor' => $this->backgroundColor,
            'symbolColor' => $this->symbolColor,
            'locked' => $this->locked,
            'isGridSlot' => $this->isGridSlot,
            'targetSymbol' => $this->targetSymbol,
        ];
    }

    public function isWithinBounds(int $x, int $y): bool
    {
        return $x >= $this->position['x'] && 
               $x < $this->position['x'] + $this->dimensions['width'] &&
               $y >= $this->position['y'] && 
               $y < $this->position['y'] + $this->dimensions['height'];
    }

    public function render(array $screen): array
    {
        $x = $this->position['x'];
        $y = $this->position['y'];
        $width = $this->dimensions['width'];
        $height = $this->dimensions['height'];

        // Don't render if outside screen bounds
        if ($x < 0 || $y < 0 || $x + $width > count($screen[0]) || $y + $height > count($screen)) {
            return $screen;
        }

            // Top border
            $screen[$y][$x] = $this->borderColor . '┌' . "\033[0m";
            $screen[$y][$x + 1] = $this->borderColor . '─' . "\033[0m";
            $screen[$y][$x + 2] = $this->borderColor . '─' . "\033[0m";
            $screen[$y][$x + 3] = $this->borderColor . '─' . "\033[0m";
            $screen[$y][$x + 4] = $this->borderColor . '┐' . "\033[0m";

            // Middle row with symbol and background
            $screen[$y + 1][$x] = $this->borderColor . '│' . "\033[0m";
            $screen[$y + 1][$x + 1] = $this->backgroundColor . ' ' . "\033[0m";
            if (!empty($this->symbol)) {
                $screen[$y + 1][$x + 2] = $this->backgroundColor . $this->symbolColor . $this->symbol . "\033[0m";
            } else {
                $screen[$y + 1][$x + 2] = $this->backgroundColor . ' ' . "\033[0m";
            }
            $screen[$y + 1][$x + 3] = $this->backgroundColor . ' ' . "\033[0m";
            $screen[$y + 1][$x + 4] = $this->borderColor . '│' . "\033[0m";

            // Bottom border
            $screen[$y + 2][$x] = $this->borderColor . '└' . "\033[0m";
            $screen[$y + 2][$x + 1] = $this->borderColor . '─' . "\033[0m";
            $screen[$y + 2][$x + 2] = $this->borderColor . '─' . "\033[0m";
            $screen[$y + 2][$x + 3] = $this->borderColor . '─' . "\033[0m";
            $screen[$y + 2][$x + 4] = $this->borderColor . '┘' . "\033[0m";

        return $screen;
    }

    public function getPosition(): array
    {
        return $this->position;
    }

    public function setPosition(array $position): void
    {
        if (!$this->locked) {
            $this->position = $position;
        }
    }

    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getBorderColor(): string
    {
        return $this->borderColor;
    }

    public function setBorderColor(string $borderColor): void
    {
        $this->borderColor = $borderColor;
    }

    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(string $backgroundColor): void
    {
        $this->backgroundColor = $backgroundColor;
    }

    public function getSymbolColor(): string
    {
        return $this->symbolColor;
    }

    public function setSymbolColor(string $symbolColor): void
    {
        $this->symbolColor = $symbolColor;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    public function isGridSlot(): bool
    {
        return $this->isGridSlot;
    }

    public function getTargetSymbol(): ?string
    {
        return $this->targetSymbol;
    }

    public function setTargetSymbol(?string $targetSymbol): void
    {
        $this->targetSymbol = $targetSymbol;
    }
}

class CaptchaGame extends Prompt
{
    private int $screenWidth;
    private int $screenHeight;
    private array $boxes = [];
    private Mouse $mouse;
    private ?array $boxDragStart = null;
    private ?string $draggedBox = null;
    private array $availableSymbols = ['●', '■', '▲', '◯', '◆', '♦', '♠', '♣', '#', '♪'];
    private array $targetPattern = [];
    private array $allSymbolData = [];
    private bool $gameComplete = false;
    private bool $needsRender = true;

    public function __construct()
    {
        $this->registerSignalHandlers();
        $this->freshDimensions();
        $this->setupMouseListening();
        $this->listenToKeys();

        $this->screenWidth = $this->terminal()->cols();
        $this->screenHeight = $this->terminal()->lines();

        $this->initializeGame();
    }

    public function __destruct()
    {
        parent::__destruct();
        $this->writeDirectly($this->mouse->disable());
    }

    private function generateContrastingColors(): array
    {
        // Generate vibrant, saturated background colors
        $colorTypes = [
            // Pure primary colors
            [255, rand(0, 100), rand(0, 100)], // Red variants
            [rand(0, 100), 255, rand(0, 100)], // Green variants
            [rand(0, 100), rand(0, 100), 255], // Blue variants
            
            // Vibrant secondary colors
            [255, 255, rand(0, 100)], // Yellow variants
            [255, rand(0, 100), 255], // Magenta variants
            [rand(0, 100), 255, 255], // Cyan variants
            
            // Vibrant mixed colors
            [255, rand(100, 200), rand(0, 100)], // Orange variants
            [rand(100, 200), 255, rand(0, 100)], // Lime variants
            [rand(100, 200), rand(0, 100), 255], // Purple variants
            [255, rand(0, 100), rand(100, 200)], // Pink variants
        ];
        
        $colorChoice = $colorTypes[rand(0, count($colorTypes) - 1)];
        $bgR = $colorChoice[0];
        $bgG = $colorChoice[1];
        $bgB = $colorChoice[2];
        
        // Calculate brightness of background (perceived luminance)
        $bgBrightness = ($bgR * 0.299) + ($bgG * 0.587) + ($bgB * 0.114);
        
        // Generate contrasting foreground color
        if ($bgBrightness > 140) {
            // Background is bright, use very dark foreground
            $fgR = rand(0, 50);
            $fgG = rand(0, 50);
            $fgB = rand(0, 50);
        } else {
            // Background is dark, use very bright foreground
            $fgR = rand(220, 255);
            $fgG = rand(220, 255);
            $fgB = rand(220, 255);
        }
        
        return [
            'bg' => "\033[48;2;{$bgR};{$bgG};{$bgB}m",
            'fg' => "\033[38;2;{$fgR};{$fgG};{$fgB}m"
        ];
    }

    private function generateSymbolData(): array
    {
        $symbolData = [];
        $usedSymbols = [];
        
        for ($i = 0; $i < 10; $i++) {
            // Get a unique symbol
            do {
                $symbolIndex = rand(0, count($this->availableSymbols) - 1);
                $symbol = $this->availableSymbols[$symbolIndex];
            } while (in_array($symbol, $usedSymbols));
            
            $usedSymbols[] = $symbol;
            $colors = $this->generateContrastingColors();
            
            $symbolData[] = [
                'symbol' => $symbol,
                'bg' => $colors['bg'],
                'fg' => $colors['fg']
            ];
        }
        
        return $symbolData;
    }

    private function initializeGame(): void
    {
        // Generate randomized symbol data with contrasting colors
        $allSymbolData = $this->generateSymbolData();
        
        // Select first 4 for target pattern
        $this->targetPattern = array_slice($allSymbolData, 0, 4);
        shuffle($this->targetPattern);

        // Store all symbol data for scattered boxes
        $this->allSymbolData = $allSymbolData;

        // Create target pattern display (non-interactive)
        $this->createTargetGrid();

        // Create empty user grid (interactive)
        $this->createUserGrid();

        // Create scattered symbol boxes
        $this->createScatteredSymbols();

        $this->needsRender = true;
    }

    private function createTargetGrid(): void
    {
        $boxSize = 5;
        $centerY = intdiv($this->screenHeight, 2) - 1;
        $startX = intdiv($this->screenWidth, 4) - 3;
        $startY = $centerY;

        for ($row = 0; $row < 2; $row++) {
            for ($col = 0; $col < 2; $col++) {
                $x = $startX + ($col * $boxSize);
                $y = $startY + ($row * 3); // Use 3 instead of $boxSize for closer rows
                $symbolIndex = $row * 2 + $col;
                $symbolData = $this->targetPattern[$symbolIndex];
                
                $this->boxes["target_{$row}_{$col}"] = new GuessBox(
                    ['x' => $x, 'y' => $y],
                    ['width' => $boxSize, 'height' => $boxSize],
                    $symbolData['symbol'],
                    "\033[32m", // Green border
                    $symbolData['bg'], // Background color
                    $symbolData['fg'], // Symbol color
                    true, // Locked
                    false, // Not grid slot
                    null
                );
            }
        }
    }

    private function createUserGrid(): void
    {
        $boxSize = 5;
        $centerY = intdiv($this->screenHeight, 2) - 1;
        $startX = intdiv($this->screenWidth * 3, 4) - 3;
        $startY = $centerY;

        for ($row = 0; $row < 2; $row++) {
            for ($col = 0; $col < 2; $col++) {
                $x = $startX + ($col * $boxSize);
                $y = $startY + ($row * 3); // Use 3 instead of $boxSize for closer rows
                $symbolIndex = $row * 2 + $col;
                
                $this->boxes["user_{$row}_{$col}"] = new GuessBox(
                    ['x' => $x, 'y' => $y],
                    ['width' => $boxSize, 'height' => $boxSize],
                    '', // Empty initially
                    "\033[36m", // Cyan border
                    "\033[48;2;50;50;50m", // Dark background
                    "", // No symbol color initially
                    false, // Not locked
                    true, // Is grid slot
                    $this->targetPattern[$symbolIndex]['symbol'] // Target symbol for this slot
                );
            }
        }
    }

    private function createScatteredSymbols(): void
    {
        $boxSize = 5;
        $centerY = intdiv($this->screenHeight, 2);
        $userGridX = intdiv($this->screenWidth * 3, 4) - 3;
        
        // Define the user grid area to avoid (with larger padding)
        $gridLeft = $userGridX;
        $gridRight = $userGridX + 9;
        $gridTop = $centerY - 1;
        $gridBottom = $centerY + 5;
        
        // Define the right side area where symbols can be placed
        $rightSideMinX = intdiv($this->screenWidth, 2) + 5;
        $rightSideMaxX = $this->screenWidth - $boxSize - 5;
        $minY = 4;
        $maxY = $this->screenHeight - $boxSize - 3;
	$availableFallbackYs = [];
	for ($i = 2; $i <= $this->screenHeight; $i += $boxSize) {
		$availableFallbackYs[] = $i;
        }

        for ($i = 0; $i < 10; $i++) {
            $attempts = 0;
            $validPosition = false;
            
            while (!$validPosition && $attempts < 800) {
                // Generate random position on right side
                $x = rand($rightSideMinX, $rightSideMaxX);
                $y = rand($minY, $maxY);
                
                // Check if position overlaps with user grid
                $overlapsGrid = ($x + $boxSize >= $gridLeft && $x <= $gridRight && 
                               $y + $boxSize >= $gridTop && $y <= $gridBottom);
                
                // Check if position overlaps with existing symbols
                $overlapsSymbol = false;
                foreach ($this->boxes as $existingId => $existingBox) {
                    if (strpos($existingId, 'scattered_') === 0) {
                        $existingPos = $existingBox->getPosition();
                        $existingDims = $existingBox->getDimensions();
                        
                        if ($x < $existingPos['x'] + $existingDims['width'] + 3 &&
                            $x + $boxSize + 3 > $existingPos['x'] &&
                            $y < $existingPos['y'] + $existingDims['height'] + 3 &&
                            $y + $boxSize + 3 > $existingPos['y']) {
                            $overlapsSymbol = true;
                            break;
                        }
                    }
                }
                
                if (!$overlapsGrid && !$overlapsSymbol) {
                    $validPosition = true;
                } else {
                    $attempts++;
                }
            }
            
            // Fallback to a safe position if we couldn't find one
            if (!$validPosition) {
		$x = intdiv($this->screenWidth, 2) - 5;
		$fallbackIndex = array_rand($availableFallbackYs);
		$y = $availableFallbackYs[$fallbackIndex];
		unset($availableFallbackYs[$fallbackIndex]);
            }
            
            $symbolData = $this->allSymbolData[$i];
            
            $this->boxes["scattered_{$i}"] = new GuessBox(
                ['x' => $x, 'y' => $y],
                ['width' => $boxSize, 'height' => $boxSize],
                $symbolData['symbol'],
                "\033[33m", // Yellow border
                $symbolData['bg'], // Background color
                $symbolData['fg'] // Symbol color
            );
        }
    }

    public function value(): mixed
    {
        return $this->gameComplete;
    }

    private function registerSignalHandlers(): void
    {
        pcntl_signal(SIGINT, function () {
            $this->terminal()->exit();
            exit;
        });

        pcntl_signal(SIGTERM, function () {
            $this->terminal()->exit();
            exit;
        });

        pcntl_signal(SIGWINCH, function () {
            $this->freshDimensions();
            $this->screenWidth = $this->terminal()->cols();
            $this->screenHeight = $this->terminal()->lines();
            $this->needsRender = true;
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

    protected function setupMouseListening(): void
    {
        $this->mouse = new Mouse();
        static::writeDirectly($this->mouse->enable());
        register_shutdown_function(function () {
            static::writeDirectly($this->mouse->disable());
        });
    }

    protected function handleMouseEvent(string $key): void
    {
        $event = $this->mouse->parseEvent($key);
        if (!$event) {
            return;
        }

        match ($event->mouseEvent) {
            MouseButton::LEFT => $this->handleLeftMouseDown($event),
            MouseButton::RELEASED_LEFT => $this->handleLeftMouseUp($event),
            MouseMotion::MOTION_LEFT => $this->handleLeftMouseDrag($event),
            default => null,
        };
    }

    private function handleLeftMouseDown(MouseEvent $event): ?MouseEvent
    {
        if ($this->gameComplete) {
            return $event;
        }

        foreach ($this->boxes as $id => $box) {
            if ($box->isWithinBounds($event->x, $event->y) && !$box->isLocked() && !$box->isGridSlot()) {
                $this->draggedBox = $id;
                $boxPos = $box->getPosition();
                $this->boxDragStart = [
                    'mouseX' => $event->x,
                    'mouseY' => $event->y,
                    'boxX' => $boxPos['x'],
                    'boxY' => $boxPos['y'],
                ];
                return $event;
            }
        }

        return $event;
    }

    private function handleLeftMouseUp(MouseEvent $event): ?MouseEvent
    {
        if ($this->gameComplete || !$this->draggedBox) {
            $this->draggedBox = null;
            $this->boxDragStart = null;
            return $event;
        }

        // Check if dropped on a grid slot
        foreach ($this->boxes as $id => $box) {
            if ($box->isGridSlot() && $box->isWithinBounds($event->x, $event->y) && $id !== $this->draggedBox) {
                $draggedSymbol = $this->boxes[$this->draggedBox]->getSymbol();
                $targetSymbol = $box->getTargetSymbol();
                
                // If this is the correct symbol for this slot
                if ($draggedSymbol === $targetSymbol) {
                    // Copy the symbol and its colors to the grid slot
                    $draggedBox = $this->boxes[$this->draggedBox];
                    $box->setSymbol($draggedBox->getSymbol());
                    $box->setBackgroundColor($draggedBox->getBackgroundColor());
                    $box->setSymbolColor($draggedBox->getSymbolColor());
                    $box->setLocked(true);
                    $box->setBorderColor("\033[32m"); // Green border for correct
                    
                    // Remove the dragged box
                    unset($this->boxes[$this->draggedBox]);
                    
                    // Check if game is complete
                    $this->checkGameComplete();
                } else {
                    // Wrong symbol, return to original position
                    $this->boxes[$this->draggedBox]->setPosition([
                        'x' => $this->boxDragStart['boxX'],
                        'y' => $this->boxDragStart['boxY']
                    ]);
                }
                
                $this->needsRender = true;
                break;
            }
        }

        $this->draggedBox = null;
        $this->boxDragStart = null;
        return $event;
    }

    private function handleLeftMouseDrag(MouseEvent $event): ?MouseEvent
    {
        if (!$this->draggedBox || !$this->boxDragStart || $this->gameComplete) {
            return $event;
        }

        $deltaX = $event->x - $this->boxDragStart['mouseX'];
        $deltaY = $event->y - $this->boxDragStart['mouseY'];

        $newX = $this->boxDragStart['boxX'] + $deltaX;
        $newY = $this->boxDragStart['boxY'] + $deltaY;

        $this->boxes[$this->draggedBox]->setPosition(['x' => $newX, 'y' => $newY]);
        $this->needsRender = true;

        return $event;
    }

    private function checkGameComplete(): void
    {
        $allCorrect = true;
        foreach ($this->boxes as $id => $box) {
            if ($box->isGridSlot() && !$box->isLocked()) {
                $allCorrect = false;
                break;
            }
        }
        
        if ($allCorrect) {
            $this->gameComplete = true;
            $this->needsRender = true;
            
            // Launch guestbook after a brief delay
            $this->launchGuestbook();
        }
    }

    private function launchGuestbook(): void
    {
        // Clear the screen and show success message
        $this->writeDirectly("\033[2J\033[H");
        
        $successMessage = "🎉 CAPTCHA COMPLETED! 🎉\n\nLaunching Guestbook...\n";
        $this->writeDirectly($successMessage);
        
        // Brief pause to show the message
        usleep(2000000); // 2 seconds
        
        // Clear screen again
        $this->writeDirectly("\033[2J\033[H");
        
        // Disable mouse before launching guestbook
        $this->writeDirectly($this->mouse->disable());
        
        // Launch the guestbook
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $guestbook = new \Apps\GuestbookPrompt();
            $guestbook->prompt();
        } catch (Exception $e) {
            $this->writeDirectly("Error launching guestbook: " . $e->getMessage() . "\n");
            $this->writeDirectly("Press any key to exit...\n");
            fgetc(STDIN);
        }
        
        // Exit the captcha game
        exit(0);
    }

    public function listenToKeys(): void
    {
        $this->on('key', function ($key) {
            if ($key[0] === "\e" && strlen($key) > 2 && $key[2] === 'M') {
                $this->handleMouseEvent($key);
                return;
            }

            match ($key) {
                'r' => $this->resetGame(),
                'q' => $this->quit(),
                default => null,
            };
        });
    }

    private function resetGame(): void
    {
        $this->boxes = [];
        $this->gameComplete = false;
        $this->allSymbolData = [];
        $this->targetPattern = [];
        $this->initializeGame();
    }

    private function quit(): void
    {
        $this->terminal()->exit();
        exit;
    }

    public function render(): void
    {
        if (!$this->needsRender) {
            return;
        }

        $this->needsRender = false;

        // Initialize screen buffer
        $screen = [];
        for ($y = 0; $y < $this->screenHeight; $y++) {
            $screen[$y] = array_fill(0, $this->screenWidth, ' ');
        }

        // Add title
        $title = "CAPTCHA GAME - Match the pattern on the right!";
        $titleX = max(0, intdiv($this->screenWidth - strlen($title), 2));
        if ($titleX + strlen($title) <= $this->screenWidth) {
            for ($i = 0; $i < strlen($title); $i++) {
                $screen[1][$titleX + $i] = $title[$i];
            }
        }

        // Add labels
        $targetLabel = "Target Pattern:";
        $userLabel = "Your Grid:";
        $centerY = intdiv($this->screenHeight, 2);
        $labelY = $centerY - 4;
        
        $targetLabelX = intdiv($this->screenWidth, 4) - intdiv(strlen($targetLabel), 2);
        $userLabelX = intdiv($this->screenWidth * 3, 4) - intdiv(strlen($userLabel), 2);
        
        if ($labelY > 0 && $targetLabelX >= 0 && $targetLabelX + strlen($targetLabel) <= $this->screenWidth) {
            for ($i = 0; $i < strlen($targetLabel); $i++) {
                $screen[$labelY][$targetLabelX + $i] = $targetLabel[$i];
            }
        }
        
        if ($labelY > 0 && $userLabelX >= 0 && $userLabelX + strlen($userLabel) <= $this->screenWidth) {
            for ($i = 0; $i < strlen($userLabel); $i++) {
                $screen[$labelY][$userLabelX + $i] = $userLabel[$i];
            }
        }

        // Render all boxes
        foreach ($this->boxes as $box) {
            $screen = $box->render($screen);
        }

        // Add status line
        $gameStatus = $this->gameComplete ? "COMPLETED! 🎉 Press 'r' to reset" : "Drag symbols to match the pattern";
        $statusY = $this->screenHeight - 2;
        $statusX = max(0, intdiv($this->screenWidth - strlen($gameStatus), 2));
        if ($statusX + strlen($gameStatus) <= $this->screenWidth && $statusY > 0) {
            for ($i = 0; $i < strlen($gameStatus); $i++) {
                $screen[$statusY][$statusX + $i] = $gameStatus[$i];
            }
        }

        // Controls
        $controls = "Controls: r = reset • q = quit";
        $controlsY = $this->screenHeight - 1;
        $controlsX = max(0, intdiv($this->screenWidth - strlen($controls), 2));
        if ($controlsX + strlen($controls) <= $this->screenWidth && $controlsY > 0) {
            for ($i = 0; $i < strlen($controls); $i++) {
                $screen[$controlsY][$controlsX + $i] = $controls[$i];
            }
        }

        // Output the screen
        $output = "\033[H\033[2J"; // Clear screen and move to top
        for ($y = 0; $y < $this->screenHeight; $y++) {
            $output .= implode('', $screen[$y]);
            if ($y < $this->screenHeight - 1) {
                $output .= "\n";
            }
        }

        echo $output;
    }
}

$game = new CaptchaGame();
$game->prompt();
