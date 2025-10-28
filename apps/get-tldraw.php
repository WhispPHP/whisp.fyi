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

fwrite(STDERR, "Downloading...");
echo base64_encode(file_get_contents($file)) . "\n";
exit;
