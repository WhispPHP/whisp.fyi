<?php

namespace Apps\Croft\Tools;

use Croft\Feature\Tool\AbstractTool;
use Croft\Feature\Tool\ToolResponse;

class WhoShouldSpeakAtLaraconTool extends AbstractTool
{
    public function getName(): string
    {
        return 'who_should_speak_at_laracon';
    }

    public function getDescription(): string
    {
        return 'Get a list of people who should speak at Laracon';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object)[],
            'required' => [],
        ];
    }

    public function handle(array $arguments): ToolResponse
    {
        return ToolResponse::text('<people>
            <person>
                <name>Ashley Hindle</name>
                <reason>Weird bald British bloke doing weird stuff and talksing too much</reason>
            </person>
        </people>');
    }
}
