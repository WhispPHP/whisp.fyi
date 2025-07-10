<?php

declare(strict_types=1);

namespace Apps;

use Laravel\Prompts\Themes\Default\Renderer;

class MemoRenderer extends Renderer
{
    private MemoPrompt $memoPrompt;

    public function __invoke(MemoPrompt $memoPrompt): string
    {
        $this->memoPrompt = $memoPrompt;

        $output = $this->renderHeader();
        $output .= $this->renderMemos();
        $output .= PHP_EOL.$this->renderInstructions().PHP_EOL;

        return $output;
    }

    private function renderHeader(): string
    {
        $username = $this->memoPrompt->user['username'] ?? 'User';
        $userColor = (int) ($this->memoPrompt->user['color'] ?? 15);
        $coloredUsername = $this->applyColor("@{$username}", $userColor);

        $headerText = " 📝 memos.sh — Welcome back {$coloredUsername}! ";
        $visualText = " 📝 memos.sh — Welcome back @{$username}! ";

        ['cols' => $cols] = $this->memoPrompt->freshDimensions();
        $visualLength = mb_strlen($visualText);
        $padding = max(0, (($cols - $visualLength) / 2) - 1);

        $centeredHeader = str_repeat(' ', (int) floor($padding)).$headerText;

        return "\n".$this->bold($this->magenta($centeredHeader))."\n\n";
    }

    private function renderMemos(): string
    {
        if (empty($this->memoPrompt->memos)) {
            $indent = str_repeat(' ', $this->memoPrompt->feedIndent);

            return $indent.'No memos yet. Press C to create one.'.PHP_EOL;
        }

        $indent = str_repeat(' ', $this->memoPrompt->feedIndent);
        $lines = [];
        foreach ($this->memoPrompt->memos as $memo) {
            $coloredUsername = $this->applyColor("@{$memo['username']}", (int) ($memo['color'] ?? 15));
            $relativeTime = $this->formatRelativeTime($memo['created_at']);

            // Split memo content into lines and indent each line properly
            $contentLines = explode("\n", $memo['content']);
            $firstLine = array_shift($contentLines);

            // First line with username and timestamp
            $lines[] = $indent."{$coloredUsername}: {$firstLine} ({$relativeTime})";

            // Additional lines (if any) with proper indentation
            foreach ($contentLines as $contentLine) {
                $lines[] = $indent.str_repeat(' ', mb_strlen("@{$memo['username']}: ")).$contentLine;
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderInstructions(): string
    {
        $text = 'R to refresh feed ∙ C to create memo ∙ Q to quit';
        ['cols' => $cols] = $this->memoPrompt->freshDimensions();
        $length = mb_strlen($text);
        $padding = max(0, (($cols - $length) / 2) - 1);

        return $this->dim(str_repeat(' ', (int) floor($padding)).$text);
    }

    /**
     * Apply 256-color ANSI foreground to text.
     */
    private function applyColor(string $text, int $colorCode): string
    {
        // Ensure code within 0-255
        $colorCode = max(0, min(255, $colorCode));

        return "\e[38;5;{$colorCode}m{$text}\e[0m";
    }

    /**
     * Convert an absolute timestamp (UTC) into a concise relative string like "2h 15m ago".
     */
    private function formatRelativeTime(string $timestamp): string
    {
        try {
            $created = new \DateTime($timestamp, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            // Fallback to raw timestamp on parse failure
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
        if ($minutes > 0 && $days === 0) { // Only show minutes for < 1 day old
            $parts[] = $minutes.'m';
        }

        return implode(' ', $parts).' ago';
    }
}
