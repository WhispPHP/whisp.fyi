<?php

// Simply read from stdin and echo the result, but uppercase. This is for testing piping
// i.e. echo 'howdy' | ssh pipe@whisp.fyi
echo strtoupper(
    fread(STDIN, 1024)
);
