<?php

declare(strict_types=1);

namespace Apps;

class StdinReader
{
    public function read(int $timeout_sec = 1): ?string
    {
        $read_streams = [STDIN];
        $write_streams = null;
        $except_streams = null;

        // Check if STDIN has data ready within the timeout period
        $num_ready_streams = stream_select($read_streams, $write_streams, $except_streams, $timeout_sec);

        if ($num_ready_streams === false) {
            // Error during stream_select
            fwrite(STDERR, "Error checking STDIN for data.\n");

            return null;
        } elseif ($num_ready_streams > 0) {
            // Data is available, read it
            // Switch to non-blocking mode to read all available data without hanging
            $initialBlocking = stream_get_meta_data(STDIN)['blocked'] ?? true;
            stream_set_blocking(STDIN, false);

            $input = '';
            while (($chunk = fgets(STDIN)) !== false) {
                $input .= $chunk;
            }

            // Restore original blocking mode
            if ($initialBlocking) {
                stream_set_blocking(STDIN, true);
            }

            if (! empty($input)) {
                return trim($input);
            } else {
                fwrite(STDERR, "Failed to read from STDIN after data was expected.\n");

                return null;
            }
        } else {
            // Timeout occurred
            fwrite(STDERR, "Timeout: No input received on STDIN within {$timeout_sec} seconds.\n");

            return null;
        }
    }
}
