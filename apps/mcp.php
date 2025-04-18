<?php

require_once __DIR__ . '/vendor/autoload.php';

use Croft\Server;

$server = new Server('Demo MCP-over-SSH Server');
$server->tool(new \Apps\Croft\Tools\CheeseTool());
$server->tool(new \Apps\Croft\Tools\WhoWouldGiveAGreatTalkAtLaraconTool());
$server->run();
