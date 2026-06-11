<?php
require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->query("SELECT id, nombre FROM categorias");
$cats = $stmt->fetchAll();
foreach($cats as $c) {
    echo "ID: {$c['id']} | Nombre: {$c['nombre']}\n";
}
