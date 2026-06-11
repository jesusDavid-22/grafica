<?php
require_once __DIR__ . '/includes/db.php';
$stmt = $pdo->query('SELECT * FROM gastos WHERE categoria_id IN (9, 10)');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
