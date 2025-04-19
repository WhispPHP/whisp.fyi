<?php

require_once __DIR__.'/vendor/autoload.php';

use Croft\Server;

$server = new Server('Demo MCP-over-SSH Server');
$server->configurePing(intervalMs: 15_000); // Whisp will disconnect after 60 sec of inactivity
$server->tool(new \Croft\Tools\Flux\ListComponents);
$server->tool(new \Croft\Tools\Flux\GetComponentDetails);
$server->tool(new \Croft\Tools\Flux\GetComponentExamples);
$server->tool(new \Apps\Croft\Tools\WhoWouldGiveAGreatTalkAtLaraconTool);
$server->run();
