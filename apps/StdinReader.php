<?php

declare(strict_types=1);

namespace Apps;

class StdinReader
{
    public function read(int $timeout_sec = 5): ?string
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
            $input = '';
            while (!feof(STDIN) && ($line = fgets(STDIN)) !== false) {
              fwrite(STDERR, 'we readin');
                $input .= $line;
            }

            if (! empty($input)) {
                return $input;
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
