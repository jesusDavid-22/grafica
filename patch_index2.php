<?php
$lines = file('index.php');

foreach ($lines as $i => $line) {
    if (strpos($line, 'id="mci-pivot-cat-headers"') !== false) {
        $lines[$i] = ''; // remove old th tag
    }
    if (strpos($line, '<tr style="background:rgba(255,255,255,0.05);">') !== false) {
        $lines[$i] = str_replace('<tr style="background:rgba(255,255,255,0.05);">', '<tr style="background:rgba(255,255,255,0.05);" id="mci-pivot-cat-headers">', $line);
    }
}

file_put_contents('index.php', implode("", $lines));
