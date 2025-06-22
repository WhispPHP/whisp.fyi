<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Laravel\Prompts\Prompt;
use Whisp\Mouse\Mouse;
use Whisp\Mouse\MouseButton;
use Whisp\Mouse\MouseEvent;
use Whisp\Mouse\Boundable;
use Whisp\Mouse\MouseMotion;
use Laravel\Prompts\Key;


class Checkbox
{
    use Boundable;

    public function __construct(public string $label, public bool $checked, public bool $hovered) {}
    public function render(): string
    {
        $greenColor = "\033[38;2;61;154;87m"; // Green for checked (normal)
        // A lighter variant of the checked colour for hover-when-checked
        $checkedHoverColor = "\033[38;2;101;194;127m"; // Even lighter green
        $hoverColor = "\033[38;2;61;120;154m"; // Blue-ish for hover
        $lines = explode("\n", $this->checked ? '✓' : ' ');
        $maxLength = 1; //max(array_map('strlen', $lines));

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


        // Prioritise the combined state first (checked + hovered)
        if ($this->checked && $this->hovered) {
            return $checkedHoverColor . implode("\n", $lines) . "\033[0m";
        }

        if ($this->checked) {
            return $greenColor . implode("\n", $lines) . "\033[0m";
        }

        if ($this->hovered) {
            return $hoverColor . implode("\n", $lines) . "\033[0m";
        }

        return implode("\n", $lines);
    }
}

class Checkboxes extends Prompt
{
    private array $viewport = [
        'x' => 0,
        'y' => 0,
        'width' => 0,
        'height' => 0,
    ];

    private Mouse $mouse;

    // Indicates whether the component needs to be re-rendered
    private bool $shouldRender = true;
    private bool $rendered = false;

    private array $checkboxes = [];
    private string $question = '';
    private int $y = 1;
    // Tracks the currently "hovered" checkbox when navigating via keyboard
    private int $currentIndex = 0;

    public function __construct(private bool $radio = false)
    {
        $this->registerSignalHandlers();
        $this->freshDimensions();
        $this->setupMouseListening();
        $this->listenToKeys();

        // Initialize viewport dimensions
        $this->viewport['width'] = $this->terminal()->cols();
        $this->viewport['height'] = $this->terminal()->lines();

        if ($this->radio) {
            $this->question = 'Choose your frontend destiny 🔮';
            $this->checkboxes = [
                new Checkbox('React', true, true),
                new Checkbox('Vue', false, false),
                new Checkbox('Livewire', false, false),
            ];
        } else {
            $this->question = 'Choose AI features to install 🤖';
            $this->checkboxes = [
                new Checkbox('Chat', true, true),
                new Checkbox('RAG', false, false),
                new Checkbox('Tools', true, false),
                new Checkbox('Analytics', false, false),
            ];
        }
    }

    public function __destruct()
    {
        parent::__destruct();
        $this->writeDirectly($this->mouse->disable());
    }

    public function value(): mixed
    {
        return true; // Required by parent class
    }

    private function registerSignalHandlers(): void
    {
        pcntl_signal(SIGINT, function () {
            $this->terminal()->exit();  // Restore cursor and terminal state
            exit;
        });

        pcntl_signal(SIGTERM, function () {
            $this->terminal()->exit();
            exit;
        });

        pcntl_signal(SIGWINCH, function () {
            $this->freshDimensions();
            $this->viewport['width'] = $this->terminal()->cols();
            $this->viewport['height'] = $this->terminal()->lines();
            $this->shouldRender = true;
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

    protected function setupMouseListening(): void
    {
        $this->mouse = new Mouse();
        static::writeDirectly($this->mouse->enable());
        register_shutdown_function(function () {
            static::writeDirectly($this->mouse->disable());
        });
    }

    private function renderHeader(): void
    {
        $headerText = $this->radio ? 'Video killed the radio star' : 'Take your pick';
        echo "\033[48;2;255;255;0m\033[38;2;0;0;0m\033[1m";
        echo str_repeat(' ', $this->viewport['width']);
        echo str_pad($headerText, $this->viewport['width'], ' ', STR_PAD_BOTH);
        echo str_repeat(' ', $this->viewport['width']);
        echo "\033[0m";
        echo PHP_EOL;
    }

    protected function handleMouseEvent(string $key): void
    {
        $event = $this->mouse->parseEvent($key);
        if (!$event) {
            return;
        }

        // If the event is a motion event, handle hover detection first
        if ($event->mouseEvent instanceof MouseMotion) {
            $this->handleHover($event);
        }

        match ($event->mouseEvent) {
            MouseButton::LEFT => $this->handleLeftClick($event),
            default => null,
        };
    }

    private function handleLeftClick(MouseEvent $event): ?MouseEvent
    {
        foreach ($this->checkboxes as $checkbox) {
            if ($checkbox->inBounds($event->x, $event->y)) {
                $checkbox->checked = !$checkbox->checked;
                // Update current index for keyboard navigation
                $this->currentIndex = array_search($checkbox, $this->checkboxes);
                // Update hover states
                foreach ($this->checkboxes as $j => $cb) {
                    $cb->hovered = ($j === $this->currentIndex);
                }
                $this->shouldRender = true;
                if ($this->radio) {
                    foreach ($this->checkboxes as $otherCheckbox) {
                        if ($otherCheckbox !== $checkbox) {
                            $otherCheckbox->checked = false;
                        }
                    }
                }
            }
        }

        return $event;
    }

    private function handleHover(MouseEvent $event): void
    {
        foreach ($this->checkboxes as $checkbox) {
            $prevHovered = $checkbox->hovered;
            $checkbox->hovered = $checkbox->inBounds($event->x, $event->y);
            if ($prevHovered !== $checkbox->hovered) {
                $this->shouldRender = true;
            }
        }
    }

    public function listenToKeys(): void
    {
        $this->on('key', function ($key) {
            // Check for mouse events first (ESC [ M sequence)
            if ($key[0] === "\e" && strlen($key) > 2 && $key[2] === 'M') {
                $this->handleMouseEvent($key);
                return;
            }

            // Keyboard navigation & actions

            // Quit shortcut
            if ($key === 'q') {
                $this->quit();
                return;
            }

            // Arrow Up
            if (Key::oneOf([Key::UP, Key::UP_ARROW], $key) !== null) {
                $this->moveHover(-1);
                return;
            }

            // Arrow Down
            if (Key::oneOf([Key::DOWN, Key::DOWN_ARROW], $key) !== null) {
                $this->moveHover(1);
                return;
            }

            // Space toggles the current checkbox
            if ($key === Key::SPACE) {
                $this->toggleCurrentCheckbox();
                return;
            }

            // Enter/Return prints choices and exits
            if (Key::oneOf([Key::ENTER], $key) !== null) {
                $this->printChoicesAndExit();
                return;
            }
        });
    }

    private function quit(): void
    {
        $this->terminal()->exit();
        exit;
    }

    private function printChoicesAndExit(): void
    {
        // Clear screen and show cursor
        echo "\033[2J\033[H";
        $this->showCursor();

        // Get checked items
        $checkedItems = array_filter($this->checkboxes, fn(Checkbox $checkbox) => $checkbox->checked);
        $checkedLabels = array_map(fn(Checkbox $checkbox) => $checkbox->label, $checkedItems);

        // Print the results
        $prefix = $this->radio ? 'Selected frontend:' : 'Selected AI features:';
        echo $prefix . PHP_EOL;

        if (empty($checkedLabels)) {
            echo "  (none selected)" . PHP_EOL;
        } else {
            foreach ($checkedLabels as $label) {
                echo "  - {$label}" . PHP_EOL;
            }
        }

        echo PHP_EOL;
        $this->terminal()->exit();
        exit;
    }

    public function render(): void
    {
        if (!$this->shouldRender) {
            return;
        }

        // Only need to hide the cursor once
        if ($this->rendered === false) {
            $this->hideCursor();
        }

        echo "\033[2J";
        echo "\033[H";
        $this->shouldRender = false;
        $this->rendered = true;

        $this->renderHeader();
        echo PHP_EOL;
        echo $this->question . PHP_EOL;
        $this->y = 6;

        foreach ($this->checkboxes as $i => $checkbox) {
            $output = $checkbox->render();

            if (empty($checkbox->bounds)) {
                $checkbox->setOutput($output);
                $checkbox->setBounds($checkbox->calculateBounds(cursorX: 0, cursorY: $this->y));
                $this->y += $checkbox->height - 1;
            }
            echo $output;
            // move cursor up one line
            echo "\033[1A";
            echo ' ' . $checkbox->label . ($checkbox->hovered ? ' •︎' : '');
            echo PHP_EOL . PHP_EOL;
            $this->y++;
        }

        echo PHP_EOL;
        $checkedLabels = array_filter($this->checkboxes, fn(Checkbox $checkbox) => $checkbox->checked);
        $footerText = ($this->radio) ? 'Frontend stack: ' : 'AI Features: ';
        echo ' ' . $footerText . implode(', ', array_map(fn(Checkbox $checkbox) => $checkbox->label, $checkedLabels));
        echo PHP_EOL;
    }

    /**
     * Move the keyboard hover up or down the checkbox list.
     */
    private function moveHover(int $direction): void
    {
        $newIndex = $this->currentIndex + $direction;
        if ($newIndex < 0 || $newIndex >= count($this->checkboxes)) {
            return; // Stop at the edges – do not wrap.
        }

        // Update hover states
        $this->checkboxes[$this->currentIndex]->hovered = false;
        $this->currentIndex = $newIndex;
        $this->checkboxes[$this->currentIndex]->hovered = true;
        $this->shouldRender = true;
    }

    /**
     * Toggle the checked state of the currently hovered checkbox.
     */
    private function toggleCurrentCheckbox(): void
    {
        $checkbox = $this->checkboxes[$this->currentIndex];
        $checkbox->checked = !$checkbox->checked;

        if ($this->radio) {
            foreach ($this->checkboxes as $i => $otherCheckbox) {
                if ($i !== $this->currentIndex) {
                    $otherCheckbox->checked = false;
                }
            }
        }

        $this->shouldRender = true;
    }
}

// Run it!
$canvas = new Checkboxes(radio: !empty($argv[1]));
$canvas->prompt();
