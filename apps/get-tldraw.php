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

// Read and output file in chunks to avoid buffering issues
$chunkSize = 8192; // 8KB chunks
$handle = fopen($file, 'rb');
if ($handle === false) {
    fwrite(STDERR, "Failed to open file\n");
    exit(1);
}

while (!feof($handle)) {
    $chunk = fread($handle, $chunkSize);
    if ($chunk === false) {
        break;
    }
    echo base64_encode($chunk);
    flush(); // Force output to be sent immediately
}

fclose($handle);
echo "\n";
exit;
