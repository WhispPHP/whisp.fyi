<?php
echo "\n\033[44mLet's have a quick look at how to create links in the terminal.\033[0m\n\n";

function blueText(string $text): string
{
    return "\033[34m{$text}\033[0m";
}

function linkText(string $url, string $text): string
{
    // \e]8;;url[text]\e]8;;\e
    // You 'open' and close a hyperlink with \e]8;; basically
    return "\033]8;;{$url}\007[{$text}]\033]8;;\033\\";
}

echo "I am regular text, but: ";
echo linkText("https://x.com/ashleyhindle?1", blueText("Click here to follow me on X"));

echo "\n\n";

echo linkText("https://x.com/ashleyhindle?2", blueText("https://x.com/ashleyhindle")) . " is a link, pretty snazzy hey!";

echo "\n\n";

echo "Now we're testing without the special escape codes, just regular text: " . blueText("https://x.com/ashleyhindle?3");

echo "\n\n";
