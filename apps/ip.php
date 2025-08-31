<?php
$clientIp = ($_SERVER['WHISP_CLIENT_IP'] ?? getenv('WHISP_CLIENT_IP'));
echo $clientIp . "\n";
echo "Copied to your clipboard\n";

// Copy to clipboard functionality
function copyToClipboard(string $text): void
{
    $encodedText = base64_encode($text);
    echo "\033]52;c;{$encodedText}\007";
}

copyToClipboard($clientIp);
