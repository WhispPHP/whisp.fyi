<?php
require_once __DIR__ . '/vendor/autoload.php';

use function Laravel\Prompts\{clear, outro, error, warning};
use Apps\Secret;

// Get hashid from environment or argv
$hashid = (!empty($_ENV['WHISP_PARAM_HASHID'])) ? $_ENV['WHISP_PARAM_HASHID'] : $argv[1];
if (empty($hashid)) {
    error('No hashid provided');
    exit(1);
}

// Get SSH key from environment
$sshKey = $_ENV['WHISP_USER_PUBLIC_KEY'] ?? '';

if (empty($sshKey)) {
    error('No SSH key provided, couldn\'t verify your identity');
    exit(1);
}

// Initialize Secret class and view the secret
$secret = new Secret();
$decryptedSecret = $secret->viewSecret($hashid, $sshKey);

if ($decryptedSecret === false) {
    error('This secret doesn\'t exist, has already been viewed, or you don\'t have access');
    exit(1);
}

// Display the secret
clear();
echo 'Your Secret' . PHP_EOL;
echo str_repeat('-', mb_strlen('Your Secret')) . PHP_EOL;
echo $decryptedSecret . PHP_EOL; // Don't mess with the display of the secret in anyway, whitespace & what not could really mess it up
echo str_repeat('-', mb_strlen('Your Secret')) . PHP_EOL;

// Copy to clipboard functionality
function copyToClipboard(string $text): void {
    $encodedText = base64_encode($text);
    echo "\033]52;c;{$encodedText}\007";
}

// Copy the secret to clipboard
copyToClipboard($decryptedSecret);

// Info box about one-time viewing
warning('This secret can only be viewed once. Make sure you have copied it somewhere safe.' . PHP_EOL . 'We should have put it in your clipboard for you.');

// Alert box about sharing
outro('Share Your Own Secret: To share your own secret, just run: ssh secrets@whisp.fyi');
