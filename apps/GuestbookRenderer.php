<?php

declare(strict_types=1);

namespace Apps;

use Laravel\Prompts\Themes\Default\Renderer;
use SoloTerm\Grapheme\Grapheme;

use function Laravel\Prompts\clear;
use function Laravel\Prompts\text;

class GuestbookRenderer extends Renderer
{
    private GuestbookPrompt $guestbook;

    public function __invoke(GuestbookPrompt $guestbook): string
    {
        $this->guestbook = $guestbook;

        if ($guestbook->signing) {
            clear();
            echo $this->renderGuestbook().PHP_EOL;
            $this->renderSigningForm();
        }

        return $this->renderGuestbook().$this->renderInstructions().PHP_EOL;
    }

    public function renderInstructions(): string
    {
        return $this->footer('S to sign ∙ Mouse wheel to scroll entries ∙ Q to exit');
    }

    public function footer(string $text): string
    {
        ['cols' => $terminalWidth] = $this->guestbook->freshDimensions(); // cols() is cached, so doesn't work with window resizing, so call 'stty' again to get the _current_ width/height

        $textLength = $this->_stringWidth($text);
        $padding = max(0, (($terminalWidth - $textLength) / 2) - 1);

        // TODO: Why isn't this actually centered? Though heading is?
        return $this->dim(str_repeat(' ', (int) floor($padding)).$text);
    }

    /**
     * Center the text in the terminal with a background color, and opposite bold text.
     * We also add an empty line before and after the text to make it look nicer, this is the same bgColor, and the full terminal width
     */
    private function header(string $text): string
    {
        ['cols' => $terminalWidth] = $this->guestbook->freshDimensions(); // cols() is cached, so doesn't work with window resizing, so call 'stty' again to get the _current_ width/height

        $textLength = mb_strlen($text);

        // Calculate padding needed to center the text
        $padding = (($terminalWidth - $textLength) / 2) - 1;

        // Create the full-width string with centered text
        $fullLine = $padding > 0 ? str_repeat(' ', (int) floor($padding)).$text.str_repeat(' ', (int) ceil($padding)) : $text;

        // Style the entire line
        $styled = $this->bold($fullLine);
        $styled = $this->black($styled);
        $styled = $this->bgMagenta($styled);
        $emptyBgColorLine = $this->bgMagenta(str_repeat(' ', $terminalWidth));

        return "{$emptyBgColorLine}\n{$styled}\n{$emptyBgColorLine}\n\n";
    }

    private function getEntries(bool $visible = true): array
    {
        // Reload the guestbook to get latest entries
        $this->guestbook->loadGuestbook();
        $allEntries = $this->guestbook->guestbook;
        if (empty($allEntries)) {
            return [];
        }

        // Get terminal dimensions
        ['cols' => $terminalWidth] = $this->guestbook->freshDimensions();

        // Determine visible entries based on scroll index
        if ($visible) {
            $visibleEntriesRaw = array_slice(
                array_reverse($allEntries),
                $this->guestbook->startIndex,
                $this->guestbook->entriesToShow()
            );
        } else {
            $visibleEntriesRaw = array_reverse($allEntries);
        }

        if (empty($visibleEntriesRaw)) {
            return [];
        }

        // Calculate actual max widths for Name and Timestamp columns from visible entries
        $maxNameWidth = 0;
        $maxTimestampWidth = 0;
        foreach ($visibleEntriesRaw as $entry) {
            $maxNameWidth = max($maxNameWidth, $this->_stringWidth($entry['name']));
            $maxTimestampWidth = max($maxTimestampWidth, $this->_stringWidth($this->formatTimestamp($entry['timestamp'])));
        }

        // Calculate fixed structural width (borders + padding)
        $numColumns = 3;
        $paddingPerCellSide = 1;
        $verticalBordersCount = $numColumns + 1; // Includes outer borders
        $totalPaddingWidth = $numColumns * $paddingPerCellSide * 2;
        $fixedStructureWidth = $verticalBordersCount + $totalPaddingWidth;

        // Calculate the maximum allowed *content* width for the Message column
        $maxMessageContentWidth = $terminalWidth - $fixedStructureWidth - $maxNameWidth - $maxTimestampWidth;
        $maxMessageContentWidth = max(1, $maxMessageContentWidth); // Ensure at least 1 char width

        // Format entries for display, truncating message precisely
        return array_map(function ($entry) use ($maxMessageContentWidth) {
            $message = $entry['message'] ?? ''; // Handle null messages
            $originalMessageWidth = $this->_stringWidth($message);

            if (empty($message)) {
                $truncatedMessage = '(no message)';
                // Recalculate width if message was empty
                $maxMessageContentWidth = max($maxMessageContentWidth, $this->_stringWidth($truncatedMessage));
            } elseif ($originalMessageWidth > $maxMessageContentWidth) {
                // Ensure space for '..'
                $allowedLength = $maxMessageContentWidth - 2; // Width of '..'
                $allowedLength = max(0, $allowedLength); // Prevent negative length
                // Use mb_strimwidth for width-based truncation
                $truncatedMessage = mb_strimwidth($message, 0, $allowedLength, '..', 'UTF-8');
            } else {
                $truncatedMessage = $message;
            }

            return [
                'name' => $entry['name'],
                'message' => $truncatedMessage,
                'signed at' => $this->formatTimestamp($entry['timestamp']),
            ];
        }, $visibleEntriesRaw);
    }

    private function formatTimestamp(?string $timestamp = null): string
    {
        $time = $timestamp ? strtotime($timestamp) : time();

        return date('D, M j, H:i T', $time);
    }

    /**
     * Calculate the visual width of a string, handling multi-byte characters and removing ANSI formatting.
     */
    private function _stringWidth(?string $string): int
    {
        $string ??= '';
        // Remove ANSI escape codes
        $string = preg_replace("/\033\[[^m]*m/", '', $string);
        // Remove terminal hyperlinks (like the ones Laravel Prompts might add)
        $string = preg_replace('/\\033]8;[^;]*;[^\\033]*\\033\\\\/', '', $string);

        $graphemeWidth = array_sum(array_map(fn ($char) => Grapheme::wcwidth($char), grapheme_str_split($string)));
        $mbWidth = mb_strlen($string);
        if ($graphemeWidth !== $mbWidth) {
            return $graphemeWidth; // + 1;
        }

        return $graphemeWidth;
    }

    /**
     * Renders a table with borders without external dependencies.
     */
    private function _renderCustomTable(array $headers, array $rows, array $widthRows = []): string
    {
        $colWidths = [];
        $padding = 1; // Spaces on each side of the content
        $numColumns = count($headers);

        // Calculate required widths for fixed columns (Name: 0, Signed At: 2)
        $requiredWidths = [];
        $messageColIndex = 1; // Assuming Message is always the second column
        $maxNameWidth = max(array_map(fn ($row) => $this->_stringWidth($row['name']), $widthRows));
        $maxTimestampWidth = max(array_map(fn ($signedAt) => $this->_stringWidth($signedAt), array_column($widthRows, 'signed at')));
        $maxMessageWidth = max(array_map(fn ($row) => $this->_stringWidth($row['message']), $widthRows));

        $requiredWidths = [$maxNameWidth + $padding * 2, 4, $maxTimestampWidth + $padding * 2];

        $verticalBordersCount = $numColumns + 1;
        $fixedStructureWidthWithoutMessagePadding = $verticalBordersCount + array_sum($requiredWidths);

        // Get terminal width
        ['cols' => $terminalWidth] = $this->guestbook->freshDimensions();
        $messageColTotalWidth = $terminalWidth - $fixedStructureWidthWithoutMessagePadding - 8;
        $messageColTotalWidth = max($this->_stringWidth($headers[$messageColIndex]) + ($padding * 2), $messageColTotalWidth); // Ensure it's at least wide enough for header+padding
        $messageColTotalWidth = max(1 + ($padding * 2), $messageColTotalWidth); // Ensure minimum width for content + padding

        // Set final column widths
        $colWidths = $requiredWidths;
        $colWidths[$messageColIndex] = $messageColTotalWidth;
        ksort($colWidths); // Ensure correct order [0, 1, 2]

        // Define border characters
        $chars = [
            'top_left' => '┌', 'top_mid' => '┬', 'top_right' => '┐',
            'mid_left' => '├', 'mid_mid' => '┼', 'mid_right' => '┤',
            'bottom_left' => '└', 'bottom_mid' => '┴', 'bottom_right' => '┘',
            'horizontal' => '─', 'vertical' => '│',
        ];

        // Helper function to build horizontal lines
        $buildHorizontalLine = function (string $left, string $mid, string $right) use ($colWidths, $chars): string {
            $line = $left;
            foreach ($colWidths as $index => $width) {
                $line .= str_repeat($chars['horizontal'], $width);
                $line .= ($index === count($colWidths) - 1) ? $right : $mid;
            }

            return $line;
        };

        // Helper function to build content rows
        $buildContentRow = function (array $rowData, string $format = '<fg=default>%s</>') use ($colWidths, $chars): string {
            $line = $chars['vertical'];
            $rowDataNumeric = array_values($rowData); // Ensure numeric keys
            foreach ($colWidths as $index => $colWidth) {
                $cellContent = $rowDataNumeric[$index] ?? '';
                $contentWidth = $this->_stringWidth($cellContent);
                $padTotal = $colWidth - $contentWidth;

                $line .= ' '.$cellContent.str_repeat(' ', max(0, $padTotal - 1));
                $line .= $chars['vertical'];
            }

            return $line;
        };

        $output = [];

        // Top border
        $output[] = $buildHorizontalLine($chars['top_left'], $chars['top_mid'], $chars['top_right']);

        // Header row
        $output[] = $buildContentRow($headers, $this->dim('<fg=default>%s</>')); // Apply dim formatting to headers

        // Header separator
        $output[] = $buildHorizontalLine($chars['mid_left'], $chars['mid_mid'], $chars['mid_right']);

        // Data rows
        foreach ($rows as $row) {
            $output[] = $buildContentRow(array_values($row)); // Ensure keys are numeric starting from 0
        }

        // Bottom border
        $output[] = $buildHorizontalLine($chars['bottom_left'], $chars['bottom_mid'], $chars['bottom_right']);

        return implode(PHP_EOL, $output);
    }

    private function renderGuestbook(): string
    {
        // Show header with bold text and colored background
        $header = $this->header('✨ SIGN MY SSH GUESTBOOK, made with Whisp + Laravel Prompts ✨');

        // Show latest guests that fit in the terminal
        $guestTitle = $this->bold($this->magenta('Guestbook Entries:')).PHP_EOL;

        // Use the custom table rendering method
        $tableOutput = $this->_renderCustomTable(
            ['Name', 'Message', 'Signed At'],
            $this->getEntries(),
            $this->getEntries(false),
        );

        return "{$header}{$guestTitle}\n{$tableOutput}";
    }

    private function renderSigningForm()
    {
        $this->guestbook->clearListeners();
        // Ask for name
        $name = text(
            label: 'What is your name?',
            placeholder: 'Enter your name to sign the guestbook...',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 2 => 'The name must be at least 2 characters.',
                strlen($value) > 40 => 'The name must not exceed 40 characters.',
                default => null
            },
            hint: 'This will be public in the guestbook.'
        );

        // Ask for message (optional)
        $message = text(
            label: 'Leave a message (optional)',
            placeholder: 'Your message for the guestbook...',
            required: false,
            hint: 'Your message must be fewer than 50 characters.',
            validate: fn (string $value) => match (true) {
                strlen($value) > 50 => 'Your message must be fewer than 50 characters.',
                default => null
            }
        );

        // Create new entry
        $entry = [
            'name' => $name,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // Add entry with safe concurrent access
        $this->guestbook->addEntry($entry);
        $this->guestbook->signing = false;
        $this->guestbook->listenForKeys();
        $this->guestbook->prompt();
    }
}
