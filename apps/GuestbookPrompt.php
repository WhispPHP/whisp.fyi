#!/usr/bin/env php
<?php

use Laravel\Prompts\Prompt;

require_once __DIR__.'/GuestbookRenderer.php';

class GuestbookPrompt extends Prompt
{
    public array $guestbook = [];

    private string $storageFile;

    public function __construct()
    {
        date_default_timezone_set('UTC');
        $this->storageFile = realpath(__DIR__.'/../').'/guestbook.json';
        $this->loadGuestbook();
        static::$themes['default'][static::class] = GuestbookRenderer::class;
        $this->on('key', function (string $key) {
            var_dump($key);
            if ($key === 'q') {
                static::terminal()->exit();
            } else {
                var_dump(['cols' => $this->terminal()->cols(), 'lines' => $this->terminal()->lines()]);
            }
        });
    }

    public function exit()
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
