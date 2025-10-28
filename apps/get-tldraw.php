<?php
// Get SSH key from environment
$sshKey = $_SERVER['WHISP_USER_PUBLIC_KEY'] ?? $_ENV['WHISP_USER_PUBLIC_KEY'] ?? '';

if (empty($sshKey)) {
    echo "Authentication failed - no SSH key provided\n";
    exit(1);
}

// Calculate the file path based on SSH key hash
$userHash = hash('sha256', $sshKey);
$file = sys_get_temp_dir() . "/tldraw-{$userHash}.png";

if (!file_exists($file)) {
    fwrite(STDERR, "No drawing found. Draw something first!\n");
    fwrite(STDERR, "Run: ssh whisp.fyi discount-tldraw\n");
    exit(1);
}

fwrite(STDERR, "Downloading...\n");

// Base64 encode the entire file first
$encoded = base64_encode(file_get_contents($file));

// Output in chunks to avoid buffering issues
$chunkSize = 8192; // 8KB chunks
$length = strlen($encoded);
$offset = 0;

while ($offset < $length) {
    echo substr($encoded, $offset, $chunkSize);
    $offset += $chunkSize;
    usleep(10000); // 10ms delay between chunks
}
