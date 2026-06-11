<?php
require 'includes/db.php';
require 'includes/functions.php';
$res = getGastosPorCategoria($pdo, '2026', 'anio');
print_r($res);
