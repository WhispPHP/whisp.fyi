<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/StdinReader.php';

use Apps\StdinReader;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use AMoschou\CommonMark\Alert\AlertExtension;
use Phiki\Phiki;

$validDocs = [
"artisan",
"authentication",
"authorization",
"billing",
"blade",
"broadcasting",
"cache",
"cashier-paddle",
"collections",
"concurrency",
"configuration",
"console-tests",
"container",
"context",
"contracts",
"contributions",
"controllers",
"csrf",
"database-testing",
"database",
"deployment",
"documentation",
"dusk",
"eloquent-collections",
"eloquent-factories",
"eloquent-mutators",
"eloquent-relationships",
"eloquent-resources",
"eloquent-serialization",
"eloquent",
"encryption",
"envoy",
"errors",
"events",
"facades",
"filesystem",
"folio",
"fortify",
"frontend",
"hashing",
"helpers",
"homestead",
"horizon",
"http-client",
"http-tests",
"installation",
"license",
"lifecycle",
"localization",
"logging",
"mail",
"middleware",
"migrations",
"mix",
"mocking",
"mongodb",
"notifications",
"octane",
"packages",
"pagination",
"passport",
"passwords",
"pennant",
"pint",
"precognition",
"processes",
"prompts",
"providers",
"pulse",
"queries",
"queues",
"rate-limiting",
"readme",
"redirects",
"redis",
"releases",
"requests",
"responses",
"reverb",
"routing",
"sail",
"sanctum",
"scheduling",
"scout",
"seeding",
"session",
"socialite",
"starter-kits",
"strings",
"structure",
"telescope",
"testing",
"upgrade",
"urls",
"valet",
"validation",
"verification",
"views",
"vite"
];
$doc = $argv[1];
if (empty($doc) || !in_array($doc, $validDocs)) {
    die('Need a valid doc please: ' . implode(',', $validDocs) . PHP_EOL);
}

class TerminalMarkdownRenderer
{
    private array $colors;
    private array $alertStyles;
    private int $terminalWidth = 80;
    private int $terminalHeight = 22;
    private int $maxWidth = 80;
    private int $boxWidth;
    private int $contentWidth;
    private int $leftPadding = 0;
    private Phiki $phiki;
    
    public function __construct(array $config = [])
    {
        $this->maxWidth = $config['max_width'] ?? 80;
        $this->initializeColors();
        $this->initializeAlertStyles();
        $this->detectTerminalDimensions();
        $this->calculateBoxWidth();
        $this->initializePhiki();
    }
    
    private function initializeColors(): void
    {
        $this->colors = [
            'red' => "\033[31m",
            'blue' => "\033[34m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'bold' => "\033[1m",
            'italic' => "\033[3m",
            'reset' => "\033[0m"
        ];
    }
    
    private function initializeAlertStyles(): void
    {
        $this->alertStyles = [
            'note' => [
                'border' => "\033[38;2;0;122;255m",
                'border_bg' => "\033[48;2;0;122;255m",
                'bg' => "\033[48;2;240;249;255m",
                'text' => '',//"\033[38;2;33;38;45m",
                'icon' => 'ⓘ',
                'icon_color' => "\033[38;2;0;122;255m"
            ],
            'tip' => [
                'border' => "\033[38;2;0;200;83m",
                'border_bg' => "\033[48;2;0;200;83m",
                'bg' => "\033[48;2;240;255;240m",
                'text' => "\033[38;2;33;38;45m",
                'icon' => '●',
                'icon_color' => "\033[38;2;0;200;83m"
            ],
            'important' => [
                'border' => "\033[38;2;130;80;223m",
                'border_bg' => "\033[48;2;130;80;223m",
                'bg' => "\033[48;2;248;240;255m",
                'text' => "\033[38;2;33;38;45m",
                'icon' => '!',
                'icon_color' => "\033[38;2;130;80;223m"
            ],
            'warning' => [
                'border' => "\033[38;2;212;153;0m",
                'border_bg' => "\033[48;2;212;153;0m",
                'bg' => "\033[48;2;255;248;240m",
                'text' => "\033[38;2;33;38;45m",
                'icon' => '▲',
                'icon_color' => "\033[38;2;212;153;0m"
            ],
            'caution' => [
                'border' => "\033[38;2;218;54;51m",
                'border_bg' => "\033[48;2;218;54;51m",
                'bg' => "\033[48;2;255;240;240m",
                'text' => "\033[38;2;33;38;45m",
                'icon' => '⚠',
                'icon_color' => "\033[38;2;218;54;51m"
            ]
        ];
    }
    
    private function detectTerminalDimensions(): void
    {
        $sizeOutput = trim(`stty size 2>/dev/null`);
        if (empty($sizeOutput)) {
            // Fallback to default values if stty fails
            $this->terminalWidth = 80;
            $this->terminalHeight = 24;
            return;
        }
        
        $parts = explode(' ', $sizeOutput);
        if (count($parts) >= 2) {
            $height = (int)$parts[0];
            $width = (int)$parts[1];
            $this->terminalWidth = $width ?: 80;
            $this->terminalHeight = $height ?: 24;
        } else {
            $this->terminalWidth = 80;
            $this->terminalHeight = 24;
        }
    }
    
    private function calculateBoxWidth(): void
    {
        // Content area should be smaller than terminal width to allow centering
        // Use 80% of terminal width or maxWidth, whichever is smaller
        $availableWidth = min($this->maxWidth, (int)($this->terminalWidth * 0.8));
        $this->contentWidth = min(100, max(50, $availableWidth));
        $this->boxWidth = $this->contentWidth + 4; // Add padding for compatibility
        
        // Calculate left padding for centering the content area
        $this->leftPadding = max(0, (int)(($this->terminalWidth - $this->contentWidth) / 2));
    }
    
    private function initializePhiki(): void
    {
        $this->phiki = new Phiki();
    }
    
    // Helper methods for text styling
    public function bold(string $text): string
    {
        return $this->colors['bold'] . $text . $this->reset();
    }

    public function linkBlue(string $text): string
    {
        return "\033[34m{$text}\033[0m";
    }

    public function linkText(string $url, string $text): string
    {
        return "\033]8;;{$url}\007{$text}\033]8;;\033\\";
    }

    public function italic(string $text): string
    {
        return $this->colors['italic'] . $text . "\033[23m";
    }
    
    public function color(string $text, string $color): string
    {
        return ($this->colors[$color] ?? '') . $text . $this->reset();
    }
    
    // === Enhanced SGR Text Formatting Methods ===
    
    /**
     * Convert hex color to RGB values
     */
    public function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * Set text color using hex code
     */
    public function textHex(string $text, string $hex): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        return "\033[38;2;{$r};{$g};{$b}m" . $text . $this->reset();
    }
    
    /**
     * Set background color using hex code
     */
    public function backgroundHex(string $text, string $hex): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        return "\033[48;2;{$r};{$g};{$b}m" . $text . $this->reset();
    }
    
    /**
     * Set text color using RGB values
     */
    public function textRgb(string $text, int $r, int $g, int $b): string
    {
        return "\033[38;2;{$r};{$g};{$b}m" . $text . $this->reset();
    }
    
    /**
     * Set background color using RGB values
     */
    public function backgroundRgb(string $text, int $r, int $g, int $b): string
    {
        return "\033[48;2;{$r};{$g};{$b}m" . $text . $this->reset();
    }
    
    /**
     * Combine text and background colors
     */
    public function coloredText(string $text, string $textHex, ?string $bgHex = null): string
    {
        [$tr, $tg, $tb] = $this->hexToRgb($textHex);
        $output = "\033[38;2;{$tr};{$tg};{$tb}m";
        
        if ($bgHex) {
            [$br, $bg, $bb] = $this->hexToRgb($bgHex);
            $output .= "\033[48;2;{$br};{$bg};{$bb}m";
        }
        
        return $output . $text . $this->reset();
    }
    
    // === Advanced Text Styling ===
    
    /**
     * Dim text
     */
    public function dim(string $text): string
    {
        return "\033[2m" . $text . "\033[22m";
    }
    
    /**
     * Strikethrough text
     */
    public function strikethrough(string $text): string
    {
        return "\033[9m" . $text . "\033[29m";
    }
    
    /**
     * Overline text
     */
    public function overline(string $text): string
    {
        return "\033[53m" . $text . "\033[55m";
    }
    
    /**
     * Standard underline
     */
    public function underline(string $text): string
    {
        return "\033[4m" . $text . "\033[24m";
    }
    
    /**
     * Double underline (Kitty extension)
     */
    public function doubleUnderline(string $text): string
    {
        return "\033[4:2m" . $text . "\033[4:0m";
    }
    
    /**
     * Curly/wavy underline (Kitty extension)
     */
    public function curlyUnderline(string $text): string
    {
        return "\033[4:3m" . $text . "\033[4:0m";
    }
    
    /**
     * Dotted underline (Kitty extension)
     */
    public function dottedUnderline(string $text): string
    {
        return "\033[4:4m" . $text . "\033[4:0m";
    }
    
    /**
     * Dashed underline (Kitty extension)
     */
    public function dashedUnderline(string $text): string
    {
        return "\033[4:5m" . $text . "\033[4:0m";
    }
    
    /**
     * Colored underline (Kitty extension)
     */
    public function coloredUnderline(string $text, string $hex, string $style = 'standard'): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        
        $styleCode = match($style) {
            'double' => "\033[4:2m",
            'curly' => "\033[4:3m",
            'dotted' => "\033[4:4m",
            'dashed' => "\033[4:5m",
            default => "\033[4m"
        };
        
        return $styleCode . "\033[58;2;{$r};{$g};{$b}m" . $text . "\033[4:0m\033[59m";
    }
    
    // === Combination Effects ===
    
    /**
     * Bold and italic
     */
    public function boldItalic(string $text): string
    {
        return "\033[1;3m" . $text . "\033[22;23m";
    }
    
    /**
     * Bold with color
     */
    public function boldColor(string $text, string $hex): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        return "\033[1;38;2;{$r};{$g};{$b}m" . $text . $this->reset();
    }
    
    /**
     * Italic with color
     */
    public function italicColor(string $text, string $hex): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        return "\033[3;38;2;{$r};{$g};{$b}m" . $text . $this->reset();
    }
    
    // === Special Effects ===
    
    /**
     * Create gradient text effect
     */
    public function gradient(string $text, string $startHex, string $endHex): string
    {
        $length = mb_strlen($text);
        if ($length <= 1) {
            return $this->textHex($text, $startHex);
        }
        
        [$sr, $sg, $sb] = $this->hexToRgb($startHex);
        [$er, $eg, $eb] = $this->hexToRgb($endHex);
        
        $output = '';
        for ($i = 0; $i < $length; $i++) {
            $ratio = $i / ($length - 1);
            $r = (int)($sr + ($er - $sr) * $ratio);
            $g = (int)($sg + ($eg - $sg) * $ratio);
            $b = (int)($sb + ($eb - $sb) * $ratio);
            
            $char = mb_substr($text, $i, 1);
            $output .= "\033[38;2;{$r};{$g};{$b}m" . $char;
        }
        
        return $output . $this->reset();
    }
    
    /**
     * Rainbow text effect
     */
    public function rainbow(string $text): string
    {
        $colors = [
            [255, 0, 0],   // Red
            [255, 127, 0], // Orange
            [255, 255, 0], // Yellow
            [0, 255, 0],   // Green
            [0, 0, 255],   // Blue
            [75, 0, 130],  // Indigo
            [148, 0, 211]  // Violet
        ];
        
        $output = '';
        $length = mb_strlen($text);
        
        for ($i = 0; $i < $length; $i++) {
            $color = $colors[$i % count($colors)];
            $char = mb_substr($text, $i, 1);
            $output .= "\033[38;2;{$color[0]};{$color[1]};{$color[2]}m" . $char;
        }
        
        return $output . $this->reset();
    }
    
    /**
     * Blinking text (may not work in all terminals)
     */
    public function blink(string $text): string
    {
        return "\033[5m" . $text . "\033[25m";
    }
    
    /**
     * Reverse video (swap foreground and background)
     */
    public function reverse(string $text): string
    {
        return "\033[7m" . $text . "\033[27m";
    }
    
    /**
     * Hidden/invisible text
     */
    public function hidden(string $text): string
    {
        return "\033[8m" . $text . "\033[28m";
    }

    public function reset(): string
    {
        return $this->colors['reset'];
    }
    
    // === Terminal Control Methods ===
    
    /**
     * Switch to alternate buffer
     */
    public function enterAltBuffer(): string
    {
        return "\033[?1049h";
    }
    
    /**
     * Exit alternate buffer
     */
    public function exitAltBuffer(): string
    {
        return "\033[?1049l";
    }
    
    /**
     * Clear screen
     */
    public function clearScreen(): string
    {
        return "\033[2J";
    }
    
    /**
     * Move cursor to position
     */
    public function moveCursor(int $row, int $col): string
    {
        return "\033[{$row};{$col}H";
    }
    
    /**
     * Hide cursor
     */
    public function hideCursor(): string
    {
        return "\033[?25l";
    }
    
    /**
     * Show cursor
     */
    public function showCursor(): string
    {
        return "\033[?25h";
    }
    
    /**
     * Get terminal height
     */
    public function getTerminalHeight(): int
    {
        return $this->terminalHeight;
    }
    
    /**
     * Get terminal width
     */
    public function getTerminalWidth(): int
    {
        return $this->terminalWidth;
    }
    
    /**
     * Refresh terminal dimensions (for window resize)
     */
    public function refreshTerminalDimensions(): void
    {
        $oldWidth = $this->terminalWidth;
        $oldHeight = $this->terminalHeight;
        
        $this->detectTerminalDimensions();
        $this->calculateBoxWidth();
        
        error_log("Terminal dimensions refreshed: {$oldWidth}x{$oldHeight} -> {$this->terminalWidth}x{$this->terminalHeight}");
    }
    
    /**
     * Clear current line
     */
    public function clearLine(): string
    {
        return "\033[2K";
    }
    
    public function stripAnsi(string $text): string
    {
        // Remove ANSI color codes
        $text = preg_replace('/\033\[[0-9;]*m/', '', $text) ?? $text;
        
        // Remove OSC 8 hyperlink sequences
        $text = preg_replace('/\033]8;;[^\007]*\007/', '', $text) ?? $text;
        $text = preg_replace('/\033]8;;\033\\\\/', '', $text) ?? $text;

        return $text;
    }
    
    private function parseAnsiSequences(string $text): array
    {
        $sequences = [];
        $visiblePosition = 0;
        $offset = 0;
        
        while ($offset < strlen($text)) {
            // Find next ANSI sequence
            $ansiMatch = null;
            $ansiPos = null;
            
            // Check for ANSI color codes
            if (preg_match('/\033\[[0-9;]*m/', $text, $ansiMatch, PREG_OFFSET_CAPTURE, $offset)) {
                $ansiPos = $ansiMatch[0][1];
                $ansiSeq = $ansiMatch[0][0];
            }
            
            // Check for OSC 8 hyperlink sequences
            $oscMatch = null;
            if (preg_match('/\033]8;;[^\007]*\007/', $text, $oscMatch, PREG_OFFSET_CAPTURE, $offset)) {
                if ($ansiPos === null || $oscMatch[0][1] < $ansiPos) {
                    $ansiPos = $oscMatch[0][1];
                    $ansiSeq = $oscMatch[0][0];
                }
            }
            
            if (preg_match('/\033]8;;\033\\\\/', $text, $oscMatch, PREG_OFFSET_CAPTURE, $offset)) {
                if ($ansiPos === null || $oscMatch[0][1] < $ansiPos) {
                    $ansiPos = $oscMatch[0][1];
                    $ansiSeq = $oscMatch[0][0];
                }
            }
            
            if ($ansiPos !== null) {
                // Add visible characters before this ANSI sequence
                $beforeAnsi = substr($text, $offset, $ansiPos - $offset);
                $visiblePosition += mb_strlen($beforeAnsi);
                
                // Store the ANSI sequence
                $sequences[] = [
                    'sequence' => $ansiSeq,
                    'position' => $visiblePosition
                ];
                
                // Move offset past the ANSI sequence
                $offset = $ansiPos + strlen($ansiSeq);
            } else {
                // No more ANSI sequences, count remaining visible characters
                $remaining = substr($text, $offset);
                $visiblePosition += mb_strlen($remaining);
                break;
            }
        }
        
        return $sequences;
    }
    
    private function mapPositionsToWrapped(string $originalText, array $wrappedLines): array
    {
        $mapping = [];
        $originalPos = 0;
        $wrappedLine = 0;
        $wrappedCol = 0;
        
        // Create plain text version for comparison
        $plainText = $this->stripAnsi($originalText);
        $plainLines = explode("\n", $plainText);
        
        // Create a joined version of wrapped lines to match against
        $joinedWrapped = implode("\n", $wrappedLines);
        
        // Map each visible character position in original to wrapped position
        $plainPos = 0;
        $wrappedPos = 0;
        
        while ($plainPos < mb_strlen($plainText) && $wrappedPos < mb_strlen($joinedWrapped)) {
            $plainChar = mb_substr($plainText, $plainPos, 1);
            $wrappedChar = mb_substr($joinedWrapped, $wrappedPos, 1);
            
            if ($plainChar === $wrappedChar) {
                // Calculate line and column in wrapped text
                $linesBeforePos = mb_substr_count(mb_substr($joinedWrapped, 0, $wrappedPos), "\n");
                $lastNewlinePos = mb_strrpos(mb_substr($joinedWrapped, 0, $wrappedPos), "\n");
                $colPos = $lastNewlinePos === false ? $wrappedPos : $wrappedPos - $lastNewlinePos - 1;
                
                $mapping[$plainPos] = [
                    'line' => $linesBeforePos,
                    'col' => $colPos
                ];
                
                $plainPos++;
                $wrappedPos++;
            } else {
                // Skip characters that don't match (shouldn't happen with proper wrapping)
                $wrappedPos++;
            }
        }
        
        return $mapping;
    }
    
    private function applyAnsiToWrapped(array $wrappedLines, array $ansiSequences, array $positionMapping): array
    {
        // Create array to store ANSI sequences for each line
        $lineSequences = [];
        
        // Group ANSI sequences by their new line positions
        foreach ($ansiSequences as $sequence) {
            $originalPos = $sequence['position'];
            
            // Find the mapped position for this ANSI sequence
            if (isset($positionMapping[$originalPos])) {
                $newPos = $positionMapping[$originalPos];
                $lineNum = $newPos['line'];
                $colNum = $newPos['col'];
                
                if (!isset($lineSequences[$lineNum])) {
                    $lineSequences[$lineNum] = [];
                }
                
                $lineSequences[$lineNum][] = [
                    'sequence' => $sequence['sequence'],
                    'position' => $colNum
                ];
            }
        }
        
        // Apply ANSI sequences to each wrapped line
        $result = [];
        foreach ($wrappedLines as $lineNum => $line) {
            if (isset($lineSequences[$lineNum])) {
                // Sort sequences by position (descending) to insert from right to left
                usort($lineSequences[$lineNum], function($a, $b) {
                    return $b['position'] - $a['position'];
                });
                
                // Insert ANSI sequences at their positions
                foreach ($lineSequences[$lineNum] as $seq) {
                    $pos = $seq['position'];
                    $sequence = $seq['sequence'];
                    
                    // Insert the ANSI sequence at the correct position
                    if ($pos <= mb_strlen($line)) {
                        $line = mb_substr($line, 0, $pos) . $sequence . mb_substr($line, $pos);
                    }
                }
            }
            
            $result[] = $line;
        }
        
        return $result;
    }
    
    public function centerContent(string $content): string
    {
        if ($this->leftPadding <= 0) {
            return $content;
        }
        
        $lines = explode("\n", $content);
        $centeredLines = [];
        
        foreach ($lines as $line) {
            // Check if this line is part of a table (contains table border characters)
            // Tables handle their own centering, so don't add padding to them
            if (preg_match('/^[ ]*[\x{250C}\x{252C}\x{2510}\x{251C}\x{253C}\x{2524}\x{2514}\x{2534}\x{2518}\x{2502}]/u', $line)) {
                $centeredLines[] = $line; // Table lines already have their own padding
            } else {
                $centeredLines[] = str_repeat(' ', (int)$this->leftPadding) . $line;
            }
        }
        
        return implode("\n", $centeredLines);
    }
    
    public function wrapText(string $text, int $width): array
    {
        // Parse ANSI sequences and their positions
        $ansiSequences = $this->parseAnsiSequences($text);
        
        // Get plain text for wrapping calculations
        $plainText = $this->stripAnsi($text);
        $wrappedLines = [];
        $lines = explode("\n", $plainText);
        
        foreach ($lines as $line) {
            if (trim($line) === '') {
                $wrappedLines[] = '';
                continue;
            }
            
            // Use wordwrap with cut_long_words=false to preserve words
            $wrapped = wordwrap($line, $width, "\n", false);
            $wrappedLines = array_merge($wrappedLines, explode("\n", $wrapped));
        }
        
        // If no ANSI sequences, return plain wrapped lines
        if (empty($ansiSequences)) {
            return $wrappedLines;
        }
        
        // Map original positions to wrapped positions
        $positionMapping = $this->mapPositionsToWrapped($text, $wrappedLines);
        
        // Apply ANSI sequences to wrapped lines
        $wrappedLinesWithAnsi = $this->applyAnsiToWrapped($wrappedLines, $ansiSequences, $positionMapping);
        
        return $wrappedLinesWithAnsi;
    }
    
    public function render(Node $node): string
    {
        if ($node instanceof \AMoschou\CommonMark\Alert\Alert) {
            return $this->renderAlert($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Block\Heading) {
            return $this->renderHeading($node);
        }
        
        if ($node instanceof \League\CommonMark\Node\Block\Paragraph) {
            return $this->renderParagraph($node);
        }
        
        if ($node instanceof \League\CommonMark\Node\Inline\Text) {
            return $this->renderText($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Inline\Strong) {
            return $this->renderStrong($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis) {
            return $this->renderEmphasis($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Inline\Code) {
            return $this->renderInlineCode($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Block\FencedCode) {
            return $this->renderCodeBlock($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak) {
            return $this->renderThematicBreak($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Block\ListBlock) {
            return $this->renderList($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Block\ListItem) {
            return $this->renderListItem($node);
        }

        if ($node instanceof \League\CommonMark\Extension\CommonMark\Node\Inline\Link) {
            return $this->renderLink($node);
        }
        
        if ($node instanceof \League\CommonMark\Extension\Table\Table) {
            return $this->renderTable($node);
        }
        
        // Default: render children
        return $this->renderChildren($node);
    }
    
    private function renderChildren(Node $node): string
    {
        $output = '';
        foreach ($node->children() as $child) {
            $output .= $this->render($child);
        }
        return $output;
    }
    
    private function renderAlert(\AMoschou\CommonMark\Alert\Alert $node): string
    {
        $alertType = $node->getType();
        $style = $this->alertStyles[$alertType] ?? $this->alertStyles['note'];
        
        $output = "\n";
        
        // Create header
        $headerText = strtoupper($alertType);
        $headerLength = mb_strlen($headerText) + mb_strlen($style['icon']) + 4;
        $headerPadding = max(0, $this->boxWidth - $headerLength - 2);

        $leftBorder = ' ' . $style['border_bg'] . '░' . $this->reset() . ' ';
        $output .= $leftBorder . $style['icon_color'] . $this->bold($style['icon']) . $style['border'] . " " . $this->bold($headerText) . " " . str_repeat(' ', $headerPadding) . $this->reset() . "\n";
        
        // Get content and wrap it
        $alertContent = $this->renderChildren($node);
        $wrappedLines = $this->wrapText($alertContent, $this->contentWidth);
        
        // Render each line (skip empty lines at the end)
        $wrappedLines = array_filter($wrappedLines, function($line) {
            return trim($line) !== '';
        });
        
        foreach ($wrappedLines as $line) {
            $lineLength = strlen($line);
            $padding = max(0, $this->contentWidth - $lineLength);
            $output .= $leftBorder . $style['text'] . $line . str_repeat(' ', $padding) . "\n";
        }
        
        $output .= "\n";

        return $output;
    }
    
    private function renderHeading(\League\CommonMark\Extension\CommonMark\Node\Block\Heading $node): string
    {
        $level = $node->getLevel();
        $headingText = $this->renderChildren($node);
        
        if ($level === 1) {
            // Purple background with padding for top-level headings
            $purpleText = $this->bold($this->coloredText('# ' . $headingText . ' ', '#8B5CF6'));

            // Add curly underline
            $underlinedText = $this->curlyUnderline($purpleText);
            
            return "\n" . $underlinedText . "\n\n";
        }
        
        $color = match($level) {
            2 => $this->colors['blue'] . $this->colors['bold'],
            3 => $this->colors['green'] . $this->colors['bold'],
            4 => $this->colors['yellow'] . $this->colors['bold'],
            5 => $this->colors['magenta'] . $this->colors['bold'],
            6 => $this->colors['cyan'] . $this->colors['bold'],
            default => $this->colors['bold']
        };
        
        return $color . $headingText . $this->reset() . "\n\n";
    }
    
    private function renderParagraph(\League\CommonMark\Node\Block\Paragraph $node): string
    {
        $content = $this->renderChildren($node);
        $wrappedLines = $this->wrapText($content, $this->contentWidth);
        return implode("\n", $wrappedLines) . "\n\n";
    }
    
    private function renderText(\League\CommonMark\Node\Inline\Text $node): string
    {
        return $node->getLiteral();
    }
    
    private function renderStrong(\League\CommonMark\Extension\CommonMark\Node\Inline\Strong $node): string
    {
        return $this->bold($this->renderChildren($node));
    }
    
    private function renderEmphasis(\League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis $node): string
    {
        return $this->italic($this->renderChildren($node));
    }
    
    private function renderInlineCode(\League\CommonMark\Extension\CommonMark\Node\Inline\Code $node): string
    {
        return $this->color($node->getLiteral(), 'cyan');
    }
    
    private function renderCodeBlock(\League\CommonMark\Extension\CommonMark\Node\Block\FencedCode $node): string
    {
        $code = $node->getLiteral();
        $language = $node->getInfoWords()[0] ?? 'text';
        
        try {
            // Use Phiki for syntax highlighting
            $highlighted = $this->phiki->codeToTerminal($code, $language, \Phiki\Theme\Theme::NightOwl);
            return $highlighted . "\n";
        } catch (\Exception $e) {
            // Fallback to simple cyan coloring if Phiki fails
            return $this->color($code, 'cyan') . "\n";
        }
    }
    
    private function renderThematicBreak(\League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak $node): string
    {
        return $this->color(str_repeat('─', 50), 'yellow') . "\n\n";
    }
    
    private function renderList(\League\CommonMark\Extension\CommonMark\Node\Block\ListBlock $node): string
    {
        $output = '';
        $listData = $node->getListData();
        $isOrdered = $listData->type === 'ordered';
        $counter = 1;
        
        foreach ($node->children() as $child) {
            if ($child instanceof \League\CommonMark\Extension\CommonMark\Node\Block\ListItem) {
                $output .= $this->renderListItemWithBullet($child, $isOrdered, $counter, 0);
                if ($isOrdered) {
                    $counter++;
                }
            }
        }
        
        return $output . "\n";
    }
    
    private function renderListItem(\League\CommonMark\Extension\CommonMark\Node\Block\ListItem $node): string
    {
        // This is called when a list item is rendered directly, fallback to basic rendering
        return $this->renderChildren($node);
    }
    
    private function renderListItemWithBullet(\League\CommonMark\Extension\CommonMark\Node\Block\ListItem $node, bool $isOrdered, int $counter, int $depth): string
    {
        // Only indent for nested lists (depth > 0)
        $indent = $depth > 0 ? str_repeat('  ', $depth) : '';
        
        if ($isOrdered) {
            $bullet = $counter . '. ';
        } else {
            // Use different Unicode bullets for different depths
            $bullets = ['• ', '◦ ', '▪ ', '▫ '];
            $bullet = $bullets[$depth % count($bullets)];
        }
        
        $output = $indent . $bullet;
        
        // Render the content of the list item
        $content = '';
        foreach ($node->children() as $child) {
            if ($child instanceof \League\CommonMark\Extension\CommonMark\Node\Block\ListBlock) {
                // Handle nested lists
                $nestedCounter = 1;
                $nestedListData = $child->getListData();
                $nestedIsOrdered = $nestedListData->type === 'ordered';
                foreach ($child->children() as $nestedItem) {
                    if ($nestedItem instanceof \League\CommonMark\Extension\CommonMark\Node\Block\ListItem) {
                        $nestedContent = $this->renderListItemWithBullet($nestedItem, $nestedIsOrdered, $nestedCounter, $depth + 1);
                        $content .= "\n" . rtrim($nestedContent, "\n");
                        if ($nestedIsOrdered) {
                            $nestedCounter++;
                        }
                    }
                }
            } else {
                // Regular content - don't apply centering here as it will be handled by the parent
                $childContent = $this->renderChildren($child);
                
                // Handle paragraph content with proper wrapping
                if ($child instanceof \League\CommonMark\Node\Block\Paragraph) {
                    $availableWidth = $this->contentWidth - strlen($indent . $bullet);
                    $wrappedLines = $this->wrapText($childContent, $availableWidth);
                    $childContent = implode("\n" . $indent . str_repeat(' ', strlen($bullet)), $wrappedLines);
                }
                
                // Remove trailing newlines from paragraphs in list items
                $childContent = rtrim($childContent, "\n");
                $content .= $childContent;
            }
        }
        
        $output .= $content . "\n";
        
        return $output;
    }

    private function renderLink(\League\CommonMark\Extension\CommonMark\Node\Inline\Link $link): string
    {
        // OSC 8, blue color, underlined
        $url = $link->getUrl();
        $label = $link->firstChild()->getLiteral();

        if (!str_starts_with($url, 'http')) {
            return $this->renderChildren($link);
        }

        // Truncate long link text to prevent display issues
        if (mb_strlen($label) > 50) {
            $label = mb_substr($label, 0, 47) . '...';
        }

        // Only render external links
        return $this->linkText($url, $this->underline($this->linkBlue($label)));
    }

    private function renderTable(\League\CommonMark\Extension\Table\Table $node): string
    {
        $headers = [];
        $rows = [];
        
        // Get table sections
        foreach ($node->children() as $section) {
            if ($section instanceof \League\CommonMark\Extension\Table\TableSection) {
                // Check if this is the head section by checking if it has a type property
                $isHead = false;
                try {
                    $reflection = new \ReflectionClass($section);
                    if ($reflection->hasProperty('type')) {
                        $typeProperty = $reflection->getProperty('type');
                        $typeProperty->setAccessible(true);
                        $type = $typeProperty->getValue($section);
                        $isHead = ($type === 'head');
                    }
                } catch (\Exception $e) {
                    // Ignore reflection errors
                }
                
                foreach ($section->children() as $row) {
                    if ($row instanceof \League\CommonMark\Extension\Table\TableRow) {
                        if ($isHead) {
                            // Header row
                            foreach ($row->children() as $cell) {
                                if ($cell instanceof \League\CommonMark\Extension\Table\TableCell) {
                                    $cellContent = trim($this->renderChildren($cell));
                                    $headers[] = $cellContent;
                                }
                            }
                        } else {
                            // Data row
                            $rowData = [];
                            foreach ($row->children() as $cell) {
                                if ($cell instanceof \League\CommonMark\Extension\Table\TableCell) {
                                    $cellContent = trim($this->renderChildren($cell));
                                    $rowData[] = $cellContent;
                                }
                            }
                            if (!empty($rowData)) {
                                $rows[] = $rowData;
                            }
                        }
                    }
                }
            }
        }
        
        // Build our own table instead of using Laravel Prompts
        if (!empty($headers) && !empty($rows)) {
            $tableOutput = $this->buildSimpleTable($headers, $rows);
            return $tableOutput . "\n\n";
        }
        
        return '';
    }
    
    private function buildSimpleTable(array $headers, array $rows): string
    {
        if (empty($headers) || empty($rows)) {
            return '';
        }
        
        // Calculate column widths
        $columnWidths = [];
        foreach ($headers as $i => $header) {
            $columnWidths[$i] = mb_strlen($header);
        }
        
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $columnWidths[$i] = max($columnWidths[$i], mb_strlen($cell));
            }
        }
        
        // Calculate total table width (including borders and padding)
        $totalTableWidth = 1; // Left border
        foreach ($columnWidths as $width) {
            $totalTableWidth += $width + 2 + 1; // content + padding + right border
        }
        
        // Check if table fits in content area, if not use adaptive centering
        $tablePadding = $this->leftPadding;
        if ($totalTableWidth > $this->contentWidth) {
            // Table is too wide for content area, center it in full terminal width
            $tablePadding = max(0, (int)(($this->terminalWidth - $totalTableWidth) / 2));
        }
        
        // Build table with adaptive padding
        $output = "\n";
        $leftPad = str_repeat(' ', $tablePadding);
        
        // Top border
        $topRow = $leftPad . "┌";
        foreach ($columnWidths as $width) {
            $topRow .= str_repeat("─", $width + 2) . "┬";
        }
        $topRow = rtrim($topRow, "┬") . "┐\n";
        $output .= $topRow;
        
        // Header row
        $headerRow = $leftPad . "│";
        foreach ($headers as $i => $header) {
            $headerRow .= " " . $this->bold(str_pad($header, $columnWidths[$i])) . " │";
        }
        $output .= $headerRow . "\n";
        
        // Header separator
        $separatorRow = $leftPad . "├";
        foreach ($columnWidths as $width) {
            $separatorRow .= str_repeat("─", $width + 2) . "┼";
        }
        $separatorRow = rtrim($separatorRow, "┼") . "┤\n";
        $output .= $separatorRow;
        
        // Data rows
        foreach ($rows as $row) {
            $dataRow = $leftPad . "│";
            foreach ($row as $i => $cell) {
                $dataRow .= " " . str_pad($cell, $columnWidths[$i]) . " │";
            }
            $output .= $dataRow . "\n";
        }
        
        // Bottom border
        $bottomRow = $leftPad . "└";
        foreach ($columnWidths as $width) {
            $bottomRow .= str_repeat("─", $width + 2) . "┴";
        }
        $bottomRow = rtrim($bottomRow, "┴") . "┘\n";
        $output .= $bottomRow;
        
        return $output;
    }
}

class MarkdownPager
{
    private array $lines = [];
    private int $currentLine = 0;
    private int $terminalHeight;
    private int $contentHeight;
    private TerminalMarkdownRenderer $renderer;
    private bool $flashActive = false;
    private float $flashStartTime = 0;
    
    public function __construct(TerminalMarkdownRenderer $renderer)
    {
        $this->renderer = $renderer;
        $this->terminalHeight = $renderer->getTerminalHeight();
        $this->contentHeight = $this->terminalHeight - 1; // Reserve space for status bar
    }
    
    public function setContent(string $content): void
    {
        $this->lines = explode("\n", $content);
        $this->currentLine = 0;
    }
    
    public function getCurrentPage(): string
    {
        $startLine = $this->currentLine;
        $endLine = min($startLine + $this->contentHeight, count($this->lines));
        
        $pageLines = array_slice($this->lines, $startLine, $this->contentHeight);
        
        // Pad with empty lines if needed
        while (count($pageLines) < $this->contentHeight) {
            $pageLines[] = '';
        }
        
        return implode("\n", $pageLines);
    }
    
    public function getStatusBar(): string
    {
        $totalLines = count($this->lines);
        
        // Calculate percentage based on whether we can see the end of the file
        $currentPercent = 0;
        if ($totalLines > 0) {
            $lastVisibleLine = $this->currentLine + $this->contentHeight;
            if ($lastVisibleLine >= $totalLines) {
                // We can see the end of the file, so we're at 100%
                $currentPercent = 100;
            } else {
                // Calculate based on how much of the file we've seen
                $currentPercent = (int)(($lastVisibleLine / $totalLines) * 100);
            }
        }
        
        $lineInfo = "Line " . ($this->currentLine + 1) . "/" . $totalLines . " ({$currentPercent}%)";
        
        $controls = "↑/↓: Line | PgUp/PgDn: Page | q: Quit ";
        
        // Create status bar with background that fills the entire width
        $statusWidth = $this->renderer->getTerminalWidth() + 4;
        $contentLength = strlen($lineInfo) + strlen($controls);
        $padding = max(1, $statusWidth - $contentLength);
        
        $statusText = $lineInfo . str_repeat(' ', $padding) . $controls;
        
        // Ensure the status bar is exactly the terminal width
        if (strlen($statusText) > $statusWidth) {
            $statusText = substr($statusText, 0, $statusWidth);
        } else {
            $statusText = str_pad($statusText, $statusWidth, ' ');
        }
        
        // Check if flash should be active (400ms duration)
        if ($this->flashActive && (microtime(true) - $this->flashStartTime) < 0.4) {
            // Flash with a slightly lighter background
            return $this->renderer->coloredText($statusText, '#E0E0E0', '#3A3A3A');
        } else {
            // Reset flash state if duration has passed
            $this->flashActive = false;
            // Normal status bar - very dark background with bright text
            return $this->renderer->coloredText($statusText, '#E0E0E0', '#1A1A1A');
        }
    }
    
    private function createSemiTransparentBar(string $text): string
    {
        // Create a semi-transparent effect using Unicode shade characters
        $output = '';
        $length = strlen($text);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === ' ') {
                // Use light shade character for spaces to create transparency effect
                $output .= $this->renderer->textRgb('░', 60, 60, 60);
            } else {
                // Regular text with slight background
                $output .= $this->renderer->coloredText($char, '#FFFFFF', '#2A2A2A');
            }
        }
        
        return $output;
    }
    
    public function canScrollUp(): bool
    {
        return $this->currentLine > 0;
    }
    
    public function canScrollDown(): bool
    {
        return $this->currentLine + $this->contentHeight < count($this->lines);
    }
    
    private function triggerFlash(): void
    {
        $this->flashActive = true;
        $this->flashStartTime = microtime(true);
    }
    
    public function scrollUp(int $lines = 1): void
    {
        $newLine = $this->currentLine - $lines;
        if ($newLine < 0) {
            $this->triggerFlash();
            $newLine = 0;
        }
        $this->currentLine = $newLine;
    }
    
    public function scrollDown(int $lines = 1): void
    {
        $maxLine = max(0, count($this->lines) - $this->contentHeight);
        $newLine = $this->currentLine + $lines;
        if ($newLine > $maxLine) {
            $this->triggerFlash();
            $newLine = $maxLine;
        }
        $this->currentLine = $newLine;
    }
    
    public function pageUp(): void
    {
        $this->scrollUp($this->contentHeight);
    }
    
    public function pageDown(): void
    {
        $this->scrollDown($this->contentHeight);
    }
    
    public function goToTop(): void
    {
        $this->currentLine = 0;
    }
    
    public function goToBottom(): void
    {
        $this->currentLine = max(0, count($this->lines) - $this->contentHeight);
    }
    
    public function handleWindowResize(): void
    {
        error_log("handleWindowResize called");
        
        // Refresh terminal dimensions in the renderer
        $this->renderer->refreshTerminalDimensions();
        
        // Update our cached dimensions
        $oldHeight = $this->terminalHeight;
        $this->terminalHeight = $this->renderer->getTerminalHeight();
        $this->contentHeight = $this->terminalHeight - 5; // Reserve space for status bar
        
        error_log("Pager height updated: {$oldHeight} -> {$this->terminalHeight}, content height: {$this->contentHeight}");
        
        // Adjust current line if needed to prevent showing past end of content
        $maxLine = max(0, count($this->lines) - $this->contentHeight);
        if ($this->currentLine > $maxLine) {
            $this->currentLine = $maxLine;
        }
    }
    
    public function render(): string
    {
        $termWidth = $this->renderer->getTerminalWidth();
        error_log("render() called with terminal width: {$termWidth}");
        
        $output = '';
        $output .= $this->renderer->clearScreen();
        $output .= $this->renderer->moveCursor(1, 1);
        
        // Render content - make sure we don't add extra newlines
        $pageContent = $this->getCurrentPage();
        $output .= $pageContent;
        
        // Position status bar at the very bottom
        $actualHeight = $this->renderer->getTerminalHeight();
        $output .= $this->renderer->moveCursor($actualHeight, 1);
        $output .= $this->renderer->clearLine();
        $output .= $this->getStatusBar();
        
        return $output;
    }
    
    public function readKey(): string
    {
        // Enable raw mode to read single keystrokes
        system('stty cbreak -echo');
        
        $key = fgetc(STDIN);
        
        // Check for escape sequences (arrow keys, etc.)
        if ($key === "\033") {
            $key .= fgetc(STDIN);
            if ($key === "\033[") {
                $key .= fgetc(STDIN);
            }
        }
        
        // Restore normal mode
        system('stty icanon echo');
        
        return $key;
    }
    
    public function run(): void
    {
        echo $this->renderer->enterAltBuffer();
        echo $this->renderer->hideCursor();
        
        // Set up signal handlers
        pcntl_signal(SIGINT, function() {
            echo $this->renderer->showCursor();
            echo $this->renderer->exitAltBuffer();
            exit(0);
        });
        
        // Handle window resize signal
        pcntl_signal(SIGWINCH, function() {
            error_log("SIGWINCH signal received!");
            usleep(50000); // Wait 50ms for terminal to update
            $this->handleWindowResize();
            error_log("About to render after resize");
            echo $this->render(); // Re-render immediately after resize
            flush(); // Force output to be displayed immediately
            error_log("Render completed after resize");
        });
        
        // Enable asynchronous signal handling
        pcntl_async_signals(true);
        
        while (true) {
            echo $this->render();
            
            pcntl_signal_dispatch();
            $key = $this->readKey();
            
            switch ($key) {
                case 'q':
                case 'Q':
                    echo $this->renderer->showCursor();
                    echo $this->renderer->exitAltBuffer();
                    return;
                    
                case "\033[A": // Up arrow
                    $this->scrollUp();
                    break;
                    
                case "\033[B": // Down arrow
                    $this->scrollDown();
                    break;
                    
                case "\033[5~": // Page Up
                case ' ': // Space for page down (like less)
                    $this->pageDown();
                    break;
                    
                case "\033[6~": // Page Down
                    $this->pageUp();
                    break;
                    
                case 'g': // Go to top
                    $this->goToTop();
                    break;
                    
                case 'G': // Go to bottom
                    $this->goToBottom();
                    break;
            }
        }
    }
}

// Check for piped input using the same approach as memos.php
$whispTty = $_SERVER['WHISP_TTY'] ?? $_ENV['WHISP_TTY'] ?? null;
$piping = !$whispTty;

$markdown = '';

if ($piping) {
    // Check if we have piped input
    $stdinReader = new StdinReader();
    $pipedContent = $stdinReader->read();
    
    if ($pipedContent !== null) {
        // We have piped content, use it
        $markdown = trim($pipedContent);
    }
}

// If no piped content, use the default file
if (empty($markdown)) {
    $file = __DIR__ . '/laravel-docs/' . $doc . '.md';
    if (!file_exists($file)) {
        echo "Error: docs not found\n";
        exit(1);
    }
    
    // Read the markdown content from file
    $markdown = file_get_contents($file);
}

// Create environment with GitHub Flavored Markdown
$environment = new Environment([
    'html_input' => 'strip',
    'allow_unsafe_links' => false,
]);

$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new GithubFlavoredMarkdownExtension());
$environment->addExtension(new AlertExtension());

// Create parser and renderer
$parser = new MarkdownParser($environment);
$renderer = new HtmlRenderer($environment);

// Parse the markdown into an AST
$document = $parser->parse($markdown);

// Create the terminal renderer with max width configuration and render the document
$renderer = new TerminalMarkdownRenderer(['max_width' => 80]);
$content = $renderer->render($document);

// Apply centering to the entire content (tables handle their own adaptive centering)
$centeredContent = $renderer->centerContent($content);

if ($piping) {
    // When piping, output directly to stdout without interactive pager
    echo $centeredContent;
} else {
    // Only use interactive pager when running in a TTY
    $pager = new MarkdownPager($renderer);
    $pager->setContent($centeredContent);
    $pager->run();
}
