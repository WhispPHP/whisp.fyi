<?php

$artisan = <<<ASCII
 ░▒▓██████▓▒░░▒▓███████▓▒░▒▓████████▓▒░▒▓█▓▒░░▒▓███████▓▒░░▒▓██████▓▒░░▒▓███████▓▒░  
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░   ░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░   ░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓████████▓▒░▒▓███████▓▒░  ░▒▓█▓▒░   ░▒▓█▓▒░░▒▓██████▓▒░░▒▓████████▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░   ░▒▓█▓▒░      ░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░   ░▒▓█▓▒░      ░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ ░▒▓█▓▒░   ░▒▓█▓▒░▒▓███████▓▒░░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░░▒▓█▓▒░ 
ASCII;

// Get terminal dimensions
$cols = (int)$_SERVER['WHISP_COLS'];
$rows = (int)$_SERVER['WHISP_ROWS'];

// ANSI color codes
$red = "\033[38;2;220;53;69m";
$reset = "\033[0m";

// Use consistent square blocks with density patterns for gradient
$square = '■';

// Function to determine if a square should be shown based on gradient level
function shouldShowSquare($row, $col, $gradient_level) {
    switch ($gradient_level) {
        case 0: return true; // Solid
        case 1: return ($row + $col) % 2 == 0; // Checkerboard 50%
        case 2: return ($row + $col) % 3 != 2; // 66% density
        case 3: return ($row + $col) % 3 == 0; // 33% density  
        case 4: return ($row + $col) % 4 == 0; // 25% density
        case 5: return ($row + $col) % 6 == 0; // ~16% density
        default: return false; // Empty
    }
}

// Split the ASCII art into lines and get dimensions
$ascii_lines = explode("\n", $artisan);
$ascii_height = count($ascii_lines);

// Find the longest line in ASCII art
$max_ascii_width = 0;
foreach ($ascii_lines as $line) {
    $max_ascii_width = max($max_ascii_width, mb_strlen($line));
}

// Calculate available space for gradient
$available_rows = ($rows - $ascii_height);
$rows_above = (int)($available_rows / 2);
$rows_below = $available_rows - $rows_above;

// Calculate red box width (60% of ASCII width)
$red_width = max(20, (int)($max_ascii_width * 0.6));

// Calculate starting positions to center everything
$start_col = (int)(($cols - $max_ascii_width) / 2);
$red_start_col = (int)(($cols - $red_width) / 2);

$output = "";

// Render the complete display
for ($row = 0; $row < $rows; $row++) {
    $line = "";
    
    // Add horizontal padding to center content
    $line .= str_repeat(" ", $start_col);
    
    // Determine which section we're in
    if ($row < $rows_above) {
        // Above ASCII - gradient from solid to sparse
        for ($col = 0; $col < $max_ascii_width; $col++) {
            $global_col = $col + $start_col;
            $in_red_area = ($global_col >= $red_start_col && $global_col < $red_start_col + $red_width);
            
            if ($in_red_area) {
                // Progressive gradient: 0 = solid (top), approaching rows_above = sparse
                $gradient_pos = min(5, (int)(($row / max(1, $rows_above)) * 6));
                $char = shouldShowSquare($row, $col, $gradient_pos) ? $square : ' ';
                $line .= $red . $char . $reset;
            } else {
                $line .= " ";
            }
        }
    } else if ($row < $rows_above + $ascii_height) {
        // ASCII section
        $ascii_row_index = $row - $rows_above;
        $ascii_line = $ascii_lines[$ascii_row_index];
        
        for ($col = 0; $col < $max_ascii_width; $col++) {
            if ($col < mb_strlen($ascii_line)) {
                $line .= $red . mb_substr($ascii_line, $col, 1) . $reset;
            } else {
                $line .= " ";
            }
        }
    } else {
        // Below ASCII - gradient from sparse to solid
        $rows_from_ascii = $row - ($rows_above + $ascii_height);
        
        for ($col = 0; $col < $max_ascii_width; $col++) {
            $global_col = $col + $start_col;
            $in_red_area = ($global_col >= $red_start_col && $global_col < $red_start_col + $red_width);
            
            if ($in_red_area) {
                // Progressive gradient: 0 = sparse (just after ASCII), approaching rows_below = solid
                $gradient_pos = 5 - min(5, (int)(($rows_from_ascii / max(1, $rows_below)) * 6));
                $char = shouldShowSquare($row, $col, $gradient_pos) ? $square : ' ';
                $line .= $red . $char . $reset;
            } else {
                $line .= " ";
            }
        }
    }
    
    $line .= "\n";
    $output .= $line;
}

echo $output;
