<?php

// Display the Claude mascot in ASCII art with burnt orange color
// Run: ssh whisp.fyi claude

// Burnt orange color (Anthropic's brand color)
$burntOrange = "\033[38;2;191;87;0m";
$reset = "\033[0m";

$claude = <<<ASCII

{$burntOrange}
                        ████████████
                    ████            ████
                  ██                    ██
                ██                        ██
              ██                            ██
            ██                                ██
            ██          ████      ████          ██
          ██          ██  ██    ██  ██          ██
          ██          ██  ██    ██  ██          ██
          ██            ██        ██            ██
          ██                                    ██
          ██              ████████              ██
            ██          ██        ██          ██
            ██          ██        ██          ██
              ██          ████████          ██
                ██                        ██
                  ██                    ██
                    ████            ████
                        ████████████

              ╔═══════════════════════════╗
              ║   Claude - AI Assistant   ║
              ║    Powered by Anthropic   ║
              ╔═══════════════════════════╝
{$reset}

ASCII;

echo $claude;
