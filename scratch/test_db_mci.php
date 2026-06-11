<?php
require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->query("SELECT strftime('%Y-%m', fecha) as mes, c.nombre, SUM(monto) as total FROM gastos g JOIN categorias c ON g.categoria_id = c.id WHERE c.id >= 11 AND c.id <= 16 GROUP BY mes, c.nombre ORDER BY mes ASC");
$db_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "DATOS EN LA BASE DE DATOS:\n";
foreach($db_data as $row) {
    echo "Mes: " . $row['mes'] . " | Cat: " . $row['nombre'] . " | Total: " . number_format($row['total']) . "\n";
}

echo "\nTotal DB: ";
$stmt = $pdo->query("SELECT SUM(monto) FROM gastos WHERE categoria_id BETWEEN 11 AND 16");
echo number_format($stmt->fetchColumn()) . "\n";
