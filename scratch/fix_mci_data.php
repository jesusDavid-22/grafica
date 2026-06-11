<?php
require_once __DIR__ . '/../includes/db.php';

echo "Limpiando gastos de 2026...\n";
$pdo->exec("DELETE FROM gastos WHERE strftime('%Y', fecha) = '2026'");

// Multiplicador
$mult = 1000000;

$metas = [
    'Nomina' => 78,
    'Rutas' => 200,
    'Ordenes Servicios' => 24,
    'Poda' => 37,
    'Energia' => 200,
    'Inventarios' => 384
];

$gastos = [
    ['mes' => '01', 'Nomina' => 10, 'Rutas' => 0, 'Ordenes Servicios' => 0.88, 'Poda' => 3, 'Energia' => 42, 'Inventarios' => 0],
    ['mes' => '02', 'Nomina' => 15, 'Rutas' => -2, 'Ordenes Servicios' => 2.45, 'Poda' => 3, 'Energia' => 0, 'Inventarios' => 0],
    ['mes' => '03', 'Nomina' => 12, 'Rutas' => 27, 'Ordenes Servicios' => 0, 'Poda' => 3, 'Energia' => 40, 'Inventarios' => 0],
    ['mes' => '04', 'Nomina' => 6, 'Rutas' => 21, 'Ordenes Servicios' => 0.82, 'Poda' => 3, 'Energia' => 12, 'Inventarios' => 0],
    ['mes' => '05', 'Nomina' => 4, 'Rutas' => 27, 'Ordenes Servicios' => 4.21, 'Poda' => 3, 'Energia' => 0, 'Inventarios' => 113],
];

$catIds = [];
foreach ($metas as $nombre => $metaAnual) {
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre LIKE ? LIMIT 1");
    $stmt->execute(["%$nombre%"]);
    $catId = $stmt->fetchColumn();
    $catIds[$nombre] = $catId;
}

$stmtGasto = $pdo->prepare("INSERT INTO gastos (concepto, monto, fecha, categoria_id) VALUES (?, ?, ?, ?)");
$inserted = 0;

foreach ($gastos as $row) {
    $fecha = "2026-" . $row['mes'] . "-28";
    
    foreach ($row as $catName => $valor) {
        if ($catName === 'mes' || $valor == 0) continue;
        
        $montoReal = $valor * $mult;
        $stmtGasto->execute([$catName, $montoReal, $fecha, $catIds[$catName]]);
        $inserted++;
    }
}

echo "Inyeccion corregida completada exitosamente. Total insertados: $inserted\n";
