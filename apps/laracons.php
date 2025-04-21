<?php

[$rows, $cols] = explode(' ', exec('stty size'));

function card(array $laracon, int $width = 110): string
{
    $width -= 2;
    // Define border characters
    $colorPrefix = match ($laracon['color']) {
        'blue' => "\033[34m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        default => '',
    };
    $colorSuffix = "\033[0m";


    $chars = [
        'top_left' => '┌', 'top_mid' => '┬', 'top_right' => '┐',
        'mid_left' => '├', 'mid_mid' => '┼', 'mid_right' => '┤',
        'bottom_left' => '└', 'bottom_mid' => '┴', 'bottom_right' => '┘',
        'horizontal' => '─', 'vertical' => '│',
    ];
    foreach ($chars as $key => $char) {
        $chars[$key] = $colorPrefix . $char . $colorSuffix;
    }

    $lines = [];

    $title = sprintf('%s ∙ %s ∙ %s', bold($laracon['name']), $laracon['location'], italic($laracon['days_to_go'] . ' days to go'));
    $remainingHeaderWidth = $width - mb_strlen(removeAnsi($title)) - 4;
    $lines[] = ' ' . $chars['top_left'] . $chars['horizontal'] . ' ' . $title . ' ' . str_repeat($chars['horizontal'], $remainingHeaderWidth) . $chars['top_right'];

    $excerptLines = "\n" . wordwrap($laracon['excerpt'], $width - 4, "\n") . "\n";
    foreach (explode("\n", $excerptLines) as $line) {
        $lines[] = $chars['vertical'] . ' ' . $line . str_repeat(' ', $width - strlen($line) - 2) . $chars['vertical'];
    }

    $cfpString = $laracon['cfp_open'] ? sprintf('%s⇾ CFP: %s', PHP_EOL, hyperlink($laracon['cfp_url'], $laracon['cfp_url'])) : '';
    $footer = sprintf("⇾ Website: %s%s", hyperlink($laracon['url'], $laracon['url']), $cfpString);
    $footerLines = explode("\n", $footer);
    foreach ($footerLines as $line) {
        $paddingWidth = max(0, $width - mb_strlen(removeAnsi($line)) - 2);
        $lines[] = $chars['vertical'] . ' ' . $line . str_repeat(' ', $paddingWidth) . $chars['vertical'];
    }

    $lines[] = $chars['vertical'] . str_repeat(' ', $width - 1) . $chars['vertical'];

    $bottomBarText = sprintf('From %s to %s', $laracon['dates']['start']->format('M d'), $laracon['dates']['end']->format('M d'));
    $repeatWidth = $width - mb_strlen(removeAnsi($bottomBarText)) - 4;
    $lines[] = $chars['bottom_left'] . $chars['horizontal'] . ' ' . $bottomBarText . ' ' . str_repeat($chars['horizontal'], $repeatWidth) . $chars['bottom_right'];

    return implode(" \n ", $lines);
}

function removeAnsi(string $text): string
{
    $cleanText = preg_replace('/\033\[[0-9;]*m/', '', $text);

    // make sure we remove any \e]8;;url[text]\e]8;;\e, but make sure we keep the text, we just remove the URL
    // \033]8;;{$url}\007{$text}\033]8;;\033\\
    // So we keep 'text', but remove everything else
    $cleanText = preg_replace('/\033\]8;;(.+?)\007(.+?)\033]8;;\033\\\/', '$2', $cleanText);
    return $cleanText;
}

// $text = '⇾ Website: ' . hyperlink('https://laracon.us/', 'https://laracon.us/');
// var_dump(
// removeAnsi($text),
// bin2hex(removeAnsi($text)),
// );
// exit;

function black(string $text): string
{
    return sprintf("\033[30m%s\033[0m", $text);
}

function blue(string $text): string
{
    return "\033[34m{$text}\033[0m";
}

function dim(string $text): string
{
    return sprintf("\033[2m%s\033[0m", $text);
}

function bgMagenta(string $text): string
{
    return sprintf("\033[45m%s\033[0m", $text);
}

function bold(string $text): string
{
    return sprintf("\033[1m%s\033[0m", $text);
}

function italic(string $text): string
{
    return sprintf("\033[3m%s\033[0m", $text);
}

function hyperlink(string $text, string $url): string
{
    // \e]8;;url[text]\e]8;;\e
    // You 'open' and close a hyperlink with \e]8;; basically
    return blue("\033]8;;{$url}\007{$text}\033]8;;\033\\");
}

function center(string $text, int $width = 111): string
{
    $textLength = mb_strlen(removeAnsi($text));
    $padding = (($width - $textLength) / 2) - 1;
    return str_repeat(' ', (int) floor($padding)).$text;
}

function welcome(string $text, int $terminalWidth = 111): string
{
    $textLength = mb_strlen($text);
    $terminalWidth -= 1;

    // Calculate padding needed to center the text
    $padding = (($terminalWidth - $textLength) / 2) - 1;

    // Create the full-width string with centered text
    $fullLine = $padding > 0 ? str_repeat(' ', (int) floor($padding)).$text.str_repeat(' ', (int) ceil($padding)) : $text;

    // Style the entire line
    $styled = bold($fullLine);
    $styled = black($styled);
    $styled = bgMagenta($styled);
    $emptyBgColorLine = bgMagenta(str_repeat(' ', $terminalWidth));

    return " {$emptyBgColorLine}\n {$styled}\n {$emptyBgColorLine}\n\n";
}

$laracons = [
    [
        'color' => 'green',
        'name' => 'Laravel Live UK',
        'virtual' => false,
        'excerpt' => 'The official Laravel conference for the UK.
Join over 300 Laravel and PHP enthusiasts for inspiring talks, valuable networking, and incredible learning experiences.',
        'location' => 'Shaw Theatre, London, UK',
        'url' => 'https://laravellive.uk/',
        'dates' => [
            'start' => new \DateTime('2025-06-10'),
            'end' => new \DateTime('2025-06-11'),
        ],
        'cfp_open' => true,
        'cfp_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSfHSkhxRpr5qMXeJ871OKQopOoV1Yl-Xn7ehT63UwoC-3nMYQ/viewform',
    ],
    [
        'color' => 'red',
        'name' => 'Laracon US',
        'virtual' => false,
        'excerpt' => 'Laracon is back for 2025. The flagship Laravel event of the year and the largest PHP conference in the United States is heading to Denver, Colorado for two days of learning and networking with the Laravel community. Come away ready to build something amazing.',
        'location' => 'The Mission Ballroom, Denver, US',
        'url' => 'https://laracon.us/',
        'dates' => [
            'start' => new \DateTime('2025-07-29'),
            'end' => new \DateTime('2025-07-30'),
        ],
        'cfp_open' => true,
        'cfp_url' => 'https://frequent-pick-a8d.notion.site/1843f372b480802c9cf8ffb63a2c51f5',
    ],
    [
        'color' => 'blue',
        'name' => 'Laracon Australia',
        'virtual' => false,
        'excerpt' => 'This year is set to be the biggest event in Laracon Australia\'s history, with a return to Brisbane Australia\'s bustling tech hub!',
        'location' => 'Brisbane, AU',
        'url' => 'https://laracon.au/',
        'dates' => [
            'start' => new \DateTime('2025-11-13'),
            'end' => new \DateTime('2025-11-14'),
        ],
        'cfp_open' => true,
        'cfp_url' => 'https://laracon.au/speak',
    ],
];

foreach ($laracons as $key => $laracon) {
    $laracons[$key]['days_to_go'] = $laracon['dates']['start']->diff(new \DateTime())->days;
}

// Remove any conferences that are in the past
$laracons = array_filter($laracons, function ($laracon) {
    return $laracon['days_to_go'] > 0;
});


// Sort laracons by days to go
usort($laracons, function ($a, $b) {
    return $a['days_to_go'] <=> $b['days_to_go'];
});

echo welcome(' 🎤 Upcoming Laravel Conferences 💪 ', $cols - 1);

foreach ($laracons as $laracon) {
    echo card($laracon, $cols - 1);
    echo "\n\n";
}

echo dim(center(sprintf("CFP closed? More conferences to add? Email me %s\n", hyperlink('laracons@ashleyhindle.com', 'mailto:laracons@ashleyhindle.com')), $cols));
