<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Themes\Default\Renderer;

use function Laravel\Prompts\{select, info};

class ClipboardPrompt extends Prompt
{
    public function __construct()
    {
        static::$themes['default'][static::class] = ClipboardRenderer::class;
    }

    public function copyToClipboard(string $text)
    {
        $encodedText = base64_encode($text);
        static::output()->write("\033]52;c;{$encodedText}\007");
    }

    public function exit()
    {
        exit(42);
    }

    public function value(): string
    {
        return 'Hello, world!';
    }
}

class ClipboardRenderer extends Renderer
{
    public function __invoke(ClipboardPrompt $prompt)
    {
        $creators = [
            'Ollie Read',
            'Josh Cirre',
            'Steve King',
            'Ryan Chandler',
            'Jeffrey Way',
            'Povilas Korop',
            'Christoph Rumpel',
            'Aaron Francis',
            'TJ Miller',
            'Andrew Schmelyun',
            'Mohamed Said',
        ];

        shuffle($creators); // Randomise them on each run
        $coolestLaravelCreator = select('Who is the coolest Laravel creator?', $creators);

        info('You\'re right, the coolest Laravel creator ' . $this->italic('is ') . $this->bgGreen($this->bold($this->black(" {$coolestLaravelCreator} "))) . ', ' . $this->red('not Ashley Hindle'));

        $prompt->copyToClipboard('Be excellent to each other');
        info('Check your clipboard for the answer to life, the universe, and everything else.');

        $prompt->exit();
        return '';
    }
}

$prompt = new ClipboardPrompt();
$prompt->prompt();
