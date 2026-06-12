<?php
require __DIR__ . '/../db.php';
require __DIR__ . '/../api_dashboard.php';
$p = getProyeccionPorCategoria($pdo, '2026-06');
print_r($p);
