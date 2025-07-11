<?php

// Set terminal title
echo "\033]0;※ MEMOS ※\007";

require_once __DIR__.'/vendor/autoload.php';

require_once __DIR__.'/MemoDb.php';
require_once __DIR__.'/StdinReader.php';

$whispTty = $_SERVER['WHISP_TTY'] ?? $_ENV['WHISP_TTY'] ?? null;
$piping = ! $whispTty;

use Apps\MemoPrompt;
use Apps\StdinReader;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

$db = new MemoDb(__DIR__.'/../../memos.db');

// Get SSH key from environment
$sshKey = $_SERVER['WHISP_USER_PUBLIC_KEY'] ?? $_ENV['WHISP_USER_PUBLIC_KEY'] ?? '';
if (empty($sshKey)) {
    error('No SSH key provided, couldn\'t verify your identity');
    exit(1);
}

// Find or create user
$user = $db->findOrCreateUser($sshKey);

// Check if user needs username
if (! $user['username']) {
    $username = text(
        label: 'Welcome! Please choose a username:',
        placeholder: 'Enter your username',
        required: true,
        validate: fn ($value) => strlen($value) < 3 || preg_match('/^[a-zA-Z0-9]+$/', $value) ? null : 'Username must be at least 3 characters long and alphanumeric',
    );

    try {
        $db->setUsername($user['id'], $username);
        $user['username'] = $username;
        info('Registration successful, snazzy!');
    } catch (Exception $e) {
        echo 'Error: '.$e->getMessage()."\n";
        exit(1);
    }
}

if ($piping) {
    // Check if we have piped input
    $stdinReader = new StdinReader;
    $pipedContent = $stdinReader->read();

    if ($pipedContent !== null) {
        // We have piped content, create memo directly
        $content = trim($pipedContent);

        if (empty($content)) {
            error('No content provided to create memo');
            exit(1);
        }

        try {
            $db->createMemo($user['id'], $content);
            info("Memo created successfully: {$content}");
            exit(0);
        } catch (Exception $e) {
            error('Failed to create memo: '.$e->getMessage());
            exit(1);
        }
    }
}

// No piped input, launch interactive memo prompt
$prompt = new MemoPrompt($db, $user);
$prompt->prompt();
