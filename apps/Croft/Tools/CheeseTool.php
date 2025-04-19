<?php

namespace Apps\Croft\Tools;

use Croft\Feature\Tool\AbstractTool;
use Croft\Feature\Tool\ToolResponse;

class CheeseTool extends AbstractTool
{
    public function getName(): string
    {
        return 'get_favorite_cheese';
    }

    public function getDescription(): string
    {
        return 'Get the user\'s favorite cheese';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
            'required' => [],
        ];
    }

    public function handle(array $arguments): ToolResponse
    {
        return ToolResponse::text('<favorite-cheese>Extra mature cheddar</favorite-cheese>');
    }
}
