<?php

// Timeout in seconds
$timeout_sec = 5; // Or choose a suitable timeout

// Array of streams to watch for reading
$read_streams = [STDIN];
$write_streams = null;
$except_streams = null;

// Check if STDIN has data ready within the timeout period
$num_ready_streams = stream_select($read_streams, $write_streams, $except_streams, $timeout_sec);

if ($num_ready_streams === false) {
    // Error during stream_select
    fwrite(STDERR, "Error checking STDIN for data.\n");
    exit(1);
} elseif ($num_ready_streams > 0) {
    // Data is available, read it
    $input = trim(fread(STDIN, 8096)); // Reads a line

    if ($input !== false) {
        // Decode the input instead of encoding
        $decoded = base64_decode(trim($input), true); // Use trim to remove potential newline, strict mode for validation
        if ($decoded !== false) {
            echo $decoded;
        } else {
            fwrite(STDERR, "Invalid base64 input received.\n");
            exit(1);
        }
    } else {
        fwrite(STDERR, "Failed to read from STDIN after data was expected.\n");
        exit(1);
    }
} else {
    // Timeout occurred
    fwrite(STDERR, "Timeout: No input received on STDIN within {$timeout_sec} seconds.\n");
    exit(1); // Exit with an error code
}
