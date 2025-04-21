<?php

[$rows, $cols] = explode(' ', exec('stty size'));

function card(array $laracon, int $width = 110): string
{
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
    $lines[] = $colorPrefix . $chars['top_left'] . $chars['horizontal'] . ' ' . $title . ' ' . str_repeat($chars['horizontal'], $remainingHeaderWidth) . $chars['top_right'] . $colorSuffix;

    $excerptLines = "\n" . wordwrap($laracon['excerpt'], $width - 4, "\n") . "\n";
    foreach (explode("\n", $excerptLines) as $line) {
        $lines[] = $chars['vertical'] . ' ' . $line . str_repeat(' ', $width - strlen($line) - 2) . $chars['vertical'];
    }

    $cfpString = $laracon['cfp_open'] ? sprintf('%s∙ CFP: %s', PHP_EOL, hyperlink($laracon['cfp_url'], $laracon['cfp_url'])) : 'CFP Closed';
    $footer = sprintf("∙ Website: %s%s", hyperlink($laracon['url'], $laracon['url']), $cfpString);
    $footerLines = explode("\n", $footer);
    foreach ($footerLines as $line) {
        $lines[] = $chars['vertical'] . ' ' . $line . str_repeat(' ', $width - mb_strlen(removeAnsi($line)) - 2) . $chars['vertical'];
    }

    $lines[] = $chars['vertical'] . str_repeat(' ', $width - 1) . $chars['vertical'];

    $bottomBarText = sprintf('From %s to %s', $laracon['dates']['start']->format('M d'), $laracon['dates']['end']->format('M d'));
    $repeatWidth = $width - mb_strlen(removeAnsi($bottomBarText)) - 4;
    $lines[] = $chars['bottom_left'] . $chars['horizontal'] . ' ' . $bottomBarText . ' ' . str_repeat($chars['horizontal'], $repeatWidth) . $chars['bottom_right'];

    return implode("\n", $lines);
}

function removeAnsi(string $text): string
{
    return preg_replace('/\033\[[0-9;]*m/', '', $text);
}

function black(string $text): string
{
    return sprintf("\033[30m%s\033[0m", $text);
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
    return sprintf("\033[34m%s\033[0m", $url);
}

function welcome(string $text, int $terminalWidth = 111): string
{
    $textLength = mb_strlen($text);

    // Calculate padding needed to center the text
    $padding = (($terminalWidth - $textLength) / 2) - 1;

    // Create the full-width string with centered text
    $fullLine = $padding > 0 ? str_repeat(' ', (int) floor($padding)).$text.str_repeat(' ', (int) ceil($padding)) : $text;

    // Style the entire line
    $styled = bold($fullLine);
    $styled = black($styled);
    $styled = bgMagenta($styled);
    $emptyBgColorLine = bgMagenta(str_repeat(' ', $terminalWidth));

    return "{$emptyBgColorLine}\n{$styled}\n{$emptyBgColorLine}\n\n";
}

$laracons = [
    [
        'color' => 'green',
        'name' => 'Laravel Live UK',
        'virtual' => false,
        'excerpt' => 'Laravel Live UK is the official Laravel conference for the UK.

Join over 300 Laravel and PHP enthusiasts for inspiring talks, valuable networking, and incredible learning experiences.

Dive deep into the Laravel ecosystem with sessions dedicated to Laravel and related technologies. ',
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
