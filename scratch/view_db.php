<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query('SELECT * FROM presupuestos');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
