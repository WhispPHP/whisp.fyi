<?php

echo "Howdy, we're going to loop through a few cursor shapes until you tell us to stop.".PHP_EOL;

echo "\033]12;0\007"; // Block
sleep(3);
echo "\033]12;1\007"; // Vertical Bar
sleep(3);
echo "\033]12;2\007"; // Underline
sleep(3);
exit;

enum CursorShape: string
{
    case BLOCK = '0';
    case VERTICAL_BAR = '1';
    case UNDERLINE = '2';
}

function setCursorShape(CursorShape $shape): void
{
    echo "\033]12;{$shape->value}\007";
}

setCursorShape(CursorShape::BLOCK);
echo PHP_EOL.'Start typing with your BLOCK cursor shape.'.PHP_EOL;

sleep(3);

setCursorShape(CursorShape::VERTICAL_BAR);
echo PHP_EOL.'Start typing with your VERTICAL BAR cursor shape.'.PHP_EOL;

sleep(3);

setCursorShape(CursorShape::UNDERLINE);
echo PHP_EOL.'Start typing with your UNDERLINE cursor shape.'.PHP_EOL;

sleep(3);
