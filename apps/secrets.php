<?php
require_once __DIR__ . '/vendor/autoload.php';

use function Laravel\Prompts\{clear, text, textarea, intro, outro, info};
use Apps\Secret;

$secret = new Secret();
intro('One View Secrets protected by Whisp & SSH keys');

$authorizedGitHubUsername = text(
    label: 'GitHub username of who can view this secret',
    placeholder: 'ashleyhindle',
    hint: 'Only this user can view this secret, and only once',
    required: true,
    validate: fn (string $value) => match (true) {
        empty($value) => 'Username is required',
        !preg_match('/^[a-zA-Z0-9_-]+$/', $value) => 'Invalid username',
        false === $secret->verifyGitHubUsername($value) => 'User has no keys',
        default => null,
    },
);

$secretText = textarea(
    label: 'What\'s your secret?',
    placeholder: 'My secret is...',
    required: true,
    validate: fn (string $value) => match (true) {
        empty($value) => 'Secret is required',
        strlen($value) > 300_000 => 'Secret is too long',
        strlen($value) < 4 => 'Are there any secrets that short?',
        default => null,
    },
);


clear();
$hashid = $secret->create($secretText, $authorizedGitHubUsername);
info('Your secret is safe with Whisp');


function copyToClipboard(string $text): void {
    $encodedText = base64_encode($text);
    echo "\033]52;c;{$encodedText}\007";
}

$fullCommand = 'ssh secret-' . $hashid . '@whisp.fyi';
$secret->drawBox(
    title: 'Share this with ' . $authorizedGitHubUsername . ' so they can access the secret:',
    content: $fullCommand
);

// Attempt to copy to clipboard
echo copyToClipboard($fullCommand);

outro("We should have put that command in your clipboard for easy sharing too!\n\nThanks for using Whisp Secrets!");

// Attempt to send a notification to the terminal
echo "\033]9;👋 Secret should be in your clipboard 🔮, keep being awesome! 💪\007";
