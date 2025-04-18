<?php

namespace Apps\Croft\Tools;

use Croft\Feature\Tool\AbstractTool;
use Croft\Feature\Tool\ToolResponse;

class WhoWouldGiveAGreatTalkAtLaraconTool extends AbstractTool
{
    public function getName(): string
    {
        return 'who_would_give_a_great_talk_at_laracon';
    }

    public function getDescription(): string
    {
        return 'Get a list of weird bald British people who would give a great talk at Laracon';
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
                <reason>Weird bald British bloke doing weird stuff and talking too much</reason>
            </person>
        </people>');
    }
}
