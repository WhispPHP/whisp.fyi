<?php

require_once realpath(__DIR__.'/vendor/autoload.php');

$prompt = new class extends Laravel\Prompts\Prompt
{
    public int $cols;

    public int $rows;

    public function __construct()
    {
        $this->registerSignalHandlers();
        $this->freshDimensions();
    }

    public function value(): mixed
    {
        return true;
    }

    public function freshDimensions(): array
    {
        $this->terminal()->initDimensions();
        $this->cols = $this->terminal()->cols();
        $this->rows = $this->terminal()->lines();

        return [
            'cols' => $this->cols,
            'lines' => $this->rows,
        ];
    }

    public function registerSignalHandlers()
    {
        pcntl_signal(SIGINT, function () { // Interrupt - Ctrl+C
            echo "\033[?25h"; // Show cursor
            exit;
        });

        pcntl_signal(SIGTERM, function () { // Terminate
            echo "\033[?25h"; // Show cursor
            exit;
        });

        pcntl_signal(SIGWINCH, function () {
            // Get & use fresh dimensions from stty (non-PHP apps call TIOCGWINSZ)
            $this->freshDimensions();
        });
    }
};

$startTime = time();
$fullWidth = 14;
$startX = 3;
$startY = 2;
$lastRows = $prompt->rows;
$lastCols = $prompt->cols;

$drawMessage = function () use ($prompt, $fullWidth, $startX, $startY) {
    $paddedX = str_pad($prompt->cols, 4, ' ', STR_PAD_LEFT);
    $paddedY = str_pad($prompt->rows, 4, ' ', STR_PAD_RIGHT);
    $padding = str_repeat(' ', ($fullWidth - 2 - strlen($paddedX) - strlen($paddedY)) / 2);
    $celebrationMessage = $prompt->bold(sprintf('%s%s x %s%s', $padding, $paddedX, $paddedY, $padding));
    $output = sprintf("\033[%d;%dH", $startY + 1, $startX);
    $output .= $prompt->bgMagenta($prompt->black($celebrationMessage));

    return $output;
};

$draw = function () use ($prompt, $fullWidth, $drawMessage, $startX, $startY) {
    $paddingString = str_repeat(' ', $fullWidth / 2);

    // First line of box, green line for padding
    $output = sprintf("\033[%d;%dH", $startY, $startX);
    $output .= $prompt->bgGreen($paddingString).$prompt->bgMagenta(' ').$prompt->bgGreen($paddingString);

    $output .= $drawMessage();

    // Third line of box, green line for padding
    $output .= sprintf("\033[%d;%dH", $startY + 2, $startX);
    $output .= $prompt->bgGreen($paddingString).$prompt->bgMagenta(' ').$prompt->bgGreen($paddingString);

    echo $output;

    $prompt->hideCursor();
};

// Initial render - clear everything, draw everything
echo "\033[H\033[J"; // clear screen
$draw();

while (time() - $startTime < 60) { // Run max of 60 seconds
    // Process any pending signals
    pcntl_signal_dispatch();

    // Changed resolution - clear the prior box, draw the box in the middle
    if ($lastCols !== $prompt->cols || $lastRows !== $prompt->rows) {
        $draw();
    }

    usleep(40000);
    $lastCols = $prompt->cols;
    $lastRows = $prompt->rows;
}
