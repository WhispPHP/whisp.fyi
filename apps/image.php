<?php
$cols = $_SERVER['WHISP_COLS'];//(int) trim(`tput cols`);
$rows = $_SERVER['WHISP_ROWS'];//(int) trim(`tput lines`);

function renderPath(string $path) {
global $cols, $rows;
	$path64 = base64_encode($path);
	echo $path . PHP_EOL;
	echo "\e_Gf=100,a=T,Y=15,t=f,c={$cols},r={$rows};{$path64}\e\\" . PHP_EOL;
}

renderPath(sprintf('C:\Users\%s\Downloads\Untitled.png', $_SERVER['WHISP_USERNAME']));
renderPath(sprintf('C:\Users\%s\Downloads\image.png', $_SERVER['WHISP_USERNAME']));
renderPath(sprintf('C:\Users\%s\Downloads\screenshot.png', $_SERVER['WHISP_USERNAME']));
renderPath(sprintf('/Users/%s/Downloads/Untitled.png', $_SERVER['WHISP_USERNAME']));
renderPath(sprintf('/Users/%s/Downloads/image.png', $_SERVER['WHISP_USERNAME']));
renderPath(sprintf('/Users/%s/Downloads/screenshot.png', $_SERVER['WHISP_USERNAME']));
