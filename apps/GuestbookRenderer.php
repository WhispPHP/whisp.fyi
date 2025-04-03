<?php

use Laravel\Prompts\Themes\Default\Renderer;

require_once __DIR__.'/GuestbookPrompt.php';

use function Laravel\Prompts\clear;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use Laravel\Prompts\Table;
use Laravel\Prompts\Themes\Default\TableRenderer;

class GuestbookRenderer extends Renderer
{
    private GuestbookPrompt $guestbook;

    public function __invoke(GuestbookPrompt $guestbook): string
    {
        $this->guestbook = $guestbook;

        $this->renderGuestbook();

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

        // Re-render the guestbook to show the new entry
        clear();
        $this->renderGuestbook();

        // Show confirmation and pause briefly
        info("Thank you for signing my guestbook, {$name}! 🎉");
        sleep(1); // Give them time to see their entry

        $this->guestbook->exit();
        // TODO: This isn't the correct way of using a renderer, improve this!
        return '';
    }

    /**
     * Center the text in the terminal with a background color, and opposite bold text.
     * We also add an empty line before and after the text to make it look nicer, this is the same bgColor, and the full terminal width
     */
    private function header(string $text): void
    {
        ['cols' => $terminalWidth] = $this->guestbook->freshDimensions(); // cols() is cached, so doesn't work with window resizing, so call 'stty' again to get the _current_ width/height

        $textLength = mb_strlen($text);

        // Calculate padding needed to center the text
        $padding = (($terminalWidth - $textLength) / 2) - 1;

        // Create the full-width string with centered text
        $fullLine = $padding > 0 ? str_repeat(' ', floor($padding)).$text.str_repeat(' ', ceil($padding)) : $text;

        // Style the entire line
        $styled = $this->bold($fullLine);
        $styled = $this->black($styled);
        $styled = $this->bgMagenta($styled);
        $emptyBgColorLine = $this->bgMagenta(str_repeat(' ', $terminalWidth));

        echo "{$emptyBgColorLine}\n{$styled}\n{$emptyBgColorLine}\n\n";
    }

    private function getVisibleEntries(): array
    {
        // Reload the guestbook to get latest entries
        $this->guestbook->loadGuestbook();
        if (empty($this->guestbook)) {
            return [];
        }

        // Calculate available height
        $terminalHeight = $this->guestbook->terminal()->lines() - 2;

        // Reserve space for:
        // - 3 lines for header (1 line + padding)
        // - 2 lines for "Latest Guests:" and table header
        // - 3 lines for input prompt
        // - 2 lines for confirmation message
        // - x for dividers
        $reservedLines = 13;

        // Calculate how many entries we can show
        $availableLines = $terminalHeight - $reservedLines;

        // Return the most recent entries that will fit
        $entries = array_slice(array_reverse($this->guestbook->guestbook), 0, max(0, $availableLines));

        $availableWidth = $this->guestbook->terminal()->cols() - 16;
        // We need to truncate the message to fit the available width. We know the length of the name and the timestamp, so we can subtract that from the available width and leave 2 characters for the ellipsis.
        if (!empty($entries)) {
            $maxNameLength = array_map(fn ($entry) => mb_strlen($entry['name']), $entries);
            $maxTimestampLength = array_map(fn ($entry) => mb_strlen($this->formatTimestamp($entry['timestamp'])), $entries);
        } else {
            $maxNameLength = [8];
            $maxTimestampLength = [8];
        }

        $maxMessageLength = $availableWidth - max($maxNameLength) - max($maxTimestampLength) - 2;

        // Format entries for display
        return array_map(function ($entry) use ($maxMessageLength) {
            if (! empty($entry['message'])) {
                $entry['message'] = strlen($entry['message']) > $maxMessageLength ? substr($entry['message'], 0, $maxMessageLength).'..' : $entry['message'];
            } else {
                $entry['message'] = '(no message)';
            }

            return [
                'name' => $entry['name'],
                'message' => $entry['message'],
                'signed at' => $this->formatTimestamp($entry['timestamp']),
            ];
        }, $entries);
    }

    private function formatTimestamp(?string $timestamp = null): string
    {
        $time = $timestamp ? strtotime($timestamp) : time();

        return date('D, M j, H:i T', $time);
    }

    private function renderGuestbook()
    {
        // Show header with bold text and colored background
        $this->header('✨ SIGN MY SSH GUESTBOOK, made with Whisp + Laravel Prompts ✨');

        // Show latest guests that fit in the terminal
        echo $this->bold($this->magenta('Latest Guests:')).PHP_EOL;

        $table = new Table(['Name', 'Message', 'Signed At'], $this->getVisibleEntries());
        echo (new TableRenderer($table))($table);
    }
}
