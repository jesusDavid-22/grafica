<?php
$z = new ZipArchive();
if ($z->open('test_borders.xlsx') === TRUE) {
    file_put_contents('sheet1.xml', $z->getFromName('xl/worksheets/sheet1.xml'));
    file_put_contents('sheet4.xml', $z->getFromName('xl/worksheets/sheet4.xml'));
    echo "Extracted.\n";
} else {
    echo "Invalid zip.\n";
}
