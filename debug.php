<?php
require_once __DIR__ . '/includes/db.php';
$stmt = $pdo->query('SELECT * FROM gastos ORDER BY id DESC LIMIT 5;');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('SELECT * FROM metas_categoria ORDER BY id DESC LIMIT 5;');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
