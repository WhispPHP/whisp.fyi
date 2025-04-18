<?php

declare(strict_types=1);

namespace Apps;

use Laravel\Prompts\Prompt;
use function Laravel\Prompts\{clear, text, info};
use Apps\GuestbookRenderer;
use Whisp\Mouse\Mouse;
use Whisp\Mouse\MouseButton;
use Whisp\Mouse\MouseMotion;
use Whisp\Mouse\MouseEvent;
use Laravel\Prompts\Key;

class GuestbookPrompt extends Prompt
{
    public bool $signing = false;
    public array $guestbook = [];

    private string $storageFile;

    private Mouse $mouse;
    public int $startIndex = 0;

    public function __construct()
    {
        date_default_timezone_set('UTC');
        $this->storageFile = realpath(__DIR__.'/../').'/guestbook.json';
        $this->loadGuestbook();
        static::$themes['default'][GuestbookPrompt::class] = GuestbookRenderer::class;

        $this->setupMouseListening();
        $this->listenForKeys();
    }

    public function entriesToShow(): int
    {
        $entriesToShow = $this->terminal()->lines() - 3 - 8;
        if ($this->signing) {
            $entriesToShow -= 8;
        }

        return $entriesToShow;
    }

    protected function setupMouseListening(): void
    {
        $this->mouse = new Mouse();
        static::writeDirectly($this->mouse->enableBasic());
        register_shutdown_function(function () {
            static::writeDirectly($this->mouse->disable());
        });
    }

    protected function listenForMouse(string $key): void
    {
        $event = $this->mouse->parseEvent($key);
        if ($event->mouseEvent === MouseButton::WHEEL_UP) {
            if ($this->startIndex > 0) {
                $this->startIndex--;
            }
        } elseif ($event->mouseEvent === MouseButton::WHEEL_DOWN) {
            if ($this->startIndex < count($this->guestbook) - $this->entriesToShow()) {
                $this->startIndex++;
            }
        }
    }

    public function listenForKeys(): void
    {
        $this->on('key', function ($key) {
            // Mouse events are sent as \e[M, so we need to check for that
            if ($key[0] === "\e" && strlen($key) > 2 && $key[2] === 'M') {
                $this->listenForMouse($key);

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                match ($key) {
                    's' => $this->sign(),
                    'S' => $this->sign(),
                    'q' => $this->quit(),
                    'Q' => $this->quit(),
                    default => null,
                };
            }
        });
    }

    public function sign()
    {
        $this->signing = true;
    }

    public function quit()
    {
        exit(0);
    }

    public function freshDimensions(): array
    {
        // Technically we could unset the env var so it's not cached, then 'cols/lines' would call this for us but :shrug:
        $this->terminal()->initDimensions();

        return [
            'cols' => $this->terminal()->cols(),
            'lines' => $this->terminal()->lines(),
        ];
    }

    public function loadGuestbook(): void
    {
        if (file_exists($this->storageFile)) {
            $contents = file_get_contents($this->storageFile);
            $this->guestbook = json_decode($contents, true) ?? [];
        }
    }

    public function addEntry(array $entry): void
    {
        $fp = fopen($this->storageFile, 'c+');

        if (! $fp) {
            throw new RuntimeException('Could not open guestbook file for writing');
        }

        try {
            // Get an exclusive lock
            if (! flock($fp, LOCK_EX)) {
                throw new RuntimeException('Could not lock guestbook file');
            }

            // Read the current contents
            fseek($fp, 0, SEEK_SET);
            $contents = stream_get_contents($fp);
            $currentEntries = json_decode($contents, true) ?? [];

            // Add the new entry
            $currentEntries[] = $entry;

            // Truncate and write back
            ftruncate($fp, 0);
            fseek($fp, 0, SEEK_SET);
            fwrite($fp, json_encode($currentEntries, JSON_PRETTY_PRINT));

            // Update our local copy
            $this->guestbook = $currentEntries;
        } finally {
            // Always release the lock and close the file
            if (is_resource($fp)) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    public function value(): mixed
    {
        return true;
    }
}
