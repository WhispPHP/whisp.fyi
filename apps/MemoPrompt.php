<?php

declare(strict_types=1);

namespace Apps;

use Exception;
use Laravel\Prompts\Prompt;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\error;
use function Laravel\Prompts\textarea;

class MemoPrompt extends Prompt
{
    /** @var array<int, array> */
    public array $memos = [];

    public array $prevDimensions;

    /** @var int Number of spaces to indent the memo feed */
    public int $feedIndent = 1;

    public function __construct(private \MemoDb $db, public array $user)
    {
        date_default_timezone_set('UTC');

        $this->loadMemos();

        // Register our custom renderer for the default theme.
        static::$themes['default'][self::class] = MemoRenderer::class;

        $this->listenForKeys();
        $this->prevDimensions = $this->freshDimensions();
        clear();
    }

    public function loadMemos(): void
    {
        // Get a large batch of recent memos first
        $allRecentMemos = $this->db->getLatestMemos(100);

        // Filter to only show what fits in the display
        $this->memos = $this->getDisplayableMemos($allRecentMemos);
    }

    /**
     * Calculate how many memos can fit in the current terminal display
     * and return only those memos.
     */
    private function getDisplayableMemos(array $memos): array
    {
        $dimensions = $this->freshDimensions();
        $availableLines = $dimensions['lines'];

        // Calculate lines used by header and footer
        $headerLines = 4; // Header takes ~4 lines
        $footerLines = 2; // Footer takes ~2 lines
        $contentLines = $availableLines - $headerLines - $footerLines;

        // Reserve at least 3 lines for content
        if ($contentLines < 3) {
            return array_slice($memos, 0, 1); // Show at least 1 memo if possible
        }

        $displayableMemos = [];
        $usedLines = 0;

        foreach ($memos as $memo) {
            // Calculate how many lines this memo will take
            $memoLines = $this->calculateMemoLines($memo);

            // Check if we have room for this memo
            if ($usedLines + $memoLines > $contentLines) {
                break;
            }

            $displayableMemos[] = $memo;
            $usedLines += $memoLines;
        }

        // If no memos fit, return empty array (will show "No memos" message)
        return $displayableMemos;
    }

    /**
     * Calculate how many terminal lines a memo will occupy when rendered.
     */
    private function calculateMemoLines(array $memo): int
    {
        $dimensions = $this->freshDimensions();
        $terminalWidth = $dimensions['cols'];

        // Account for indentation
        $indent = $this->feedIndent;
        $usernamePrefix = "@{$memo['username']}: ";
        $timestamp = $this->formatRelativeTime($memo['created_at']);
        $timestampSuffix = " ({$timestamp})";

        // Split content by newlines
        $contentLines = explode("\n", $memo['content']);
        $totalLines = 0;

        foreach ($contentLines as $index => $line) {
            if ($index === 0) {
                // First line includes username and timestamp
                $fullLine = str_repeat(' ', $indent).$usernamePrefix.$line.$timestampSuffix;
            } else {
                // Subsequent lines are indented to align with content
                $fullLine = str_repeat(' ', $indent + mb_strlen($usernamePrefix)).$line;
            }

            // Calculate how many terminal lines this will wrap to
            $lineLength = mb_strlen($fullLine);
            $wrappedLines = max(1, (int) ceil($lineLength / $terminalWidth));
            $totalLines += $wrappedLines;
        }

        return $totalLines;
    }

    /**
     * Format relative time (simplified version from MemoRenderer for calculations).
     */
    private function formatRelativeTime(string $timestamp): string
    {
        try {
            $created = new \DateTime($timestamp, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return $timestamp;
        }

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $diffInSeconds = max(0, $now->getTimestamp() - $created->getTimestamp());

        if ($diffInSeconds < 60) {
            return 'just now';
        }

        $minutes = intdiv($diffInSeconds, 60);
        $hours = intdiv($minutes, 60);
        $days = intdiv($hours, 24);

        $minutes %= 60;
        $hours %= 24;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.'d';
        }
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }
        if ($minutes > 0 && $days === 0) {
            $parts[] = $minutes.'m';
        }

        return implode(' ', $parts).' ago';
    }

    private function listenForKeys(): void
    {
        $this->on('key', function ($key): void {
            // Keys may be buffered, split into individual chars.
            foreach (mb_str_split($key) as $char) {
                match ($char) {
                    'r', 'R' => $this->refresh(),
                    'c', 'C' => $this->createMemo(),
                    'q', 'Q' => $this->quit(),
                    default => null,
                };
            }
        });
    }

    /* ----------------- Actions ----------------- */

    public function refresh(): void
    {
        $this->loadMemos();

        // Update dimensions in case terminal was resized
        $this->prevDimensions = $this->freshDimensions();
    }

    public function createMemo(): void
    {
        // Stop listening while we collect input.
        $this->clearListeners();

        $content = textarea(
            label: "What's on your mind?",
            placeholder: 'Type your memo...'
        );

        if (trim($content) !== '') {
            try {
                $this->db->createMemo($this->user['id'], $content);
            } catch (Exception $e) {
                // Show error message to user
                error($e->getMessage());
                exit(1);
            }
        }

        $this->loadMemos();

        // Resume interactive loop.
        $this->listenForKeys();
        $this->prompt();
    }

    public function quit(): void
    {
        exit(0);
    }

    /* ----------------- Prompt API ----------------- */

    public function value(): mixed
    {
        return true; // Not used but required by Prompt contract.
    }

    public function freshDimensions(): array
    {
        $this->terminal()->initDimensions();

        return [
            'cols' => $this->terminal()->cols(),
            'lines' => $this->terminal()->lines(),
        ];
    }

    /**
     * Handle rendering diffing similar to GuestbookPrompt but simplified.
     */
    protected function render(): void
    {
        $this->terminal()->initDimensions();

        $frame = $this->renderTheme();

        if ($frame === $this->prevFrame) {
            return;
        }

        if ($this->state === 'initial') {
            static::output()->write($frame);

            $this->state = 'active';
            $this->prevFrame = $frame;

            return;
        }

        $lineWhereDifferenceOccurs = 0;
        $newLines = explode(PHP_EOL, $frame);
        $oldLines = explode(PHP_EOL, $this->prevFrame);
        foreach ($newLines as $line => $newLine) {
            if (($oldLines[$line] ?? null) !== $newLine) {
                $lineWhereDifferenceOccurs = $line;
                break;
            }
        }

        $clearDown = false;
        if ($this->prevDimensions !== $this->freshDimensions()) {
            $this->prevDimensions = $this->freshDimensions();
            // Recalculate displayable memos for the new terminal size
            $this->loadMemos();
            $lineWhereDifferenceOccurs = 0;
            $clearDown = true;
        }

        $renderableLines = array_slice($newLines, max(0, $lineWhereDifferenceOccurs - 1));

        // Move the cursor to the start of the line where the difference occurs
        static::writeDirectly("\e[{$lineWhereDifferenceOccurs};0H");
        // if ($clearDown) {
        static::writeDirectly("\e[J");
        // }
        $this->output()->write(implode(PHP_EOL, $renderableLines));

        $this->prevFrame = $frame;
    }
}
