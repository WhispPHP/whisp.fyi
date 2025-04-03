<?php

declare(strict_types=1);

namespace App;

use Laravel\Prompts\Prompt;

class GuessTheElephantPrompt extends Prompt
{
    public function value(): bool
    {
        return true;
    }
}
