<?php
$file = 'index.php';
$content = file_get_contents($file);

// Find the line with A'O (or AÑO) and replace its parent <tr> and its siblings.
$target = '<tr style="background:rgba(255,255,255,0.05);">
                                <th style="width:60px;">A';

$replacement = '<tr style="background:rgba(255,255,255,0.05);" id="mci-pivot-cat-headers">
                                <th style="width:60px;">A';

$content = str_replace($target, $replacement, $content);

// Remove the old <th id="mci-pivot-cat-headers"></th>
$content = str_replace('<th id="mci-pivot-cat-headers"></th>', '', $content);
$content = str_replace('<!-- Las categoras se generarǭn dinǭmicamente -->', '', $content);

file_put_contents($file, $content);
echo "Patched successfully!";
