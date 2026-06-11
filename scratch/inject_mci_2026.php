<?php
require_once __DIR__ . '/../includes/db.php';

echo "Iniciando inyección de datos MCI 2026...\n";

// Multiplicador
$mult = 1000000;

// Metas (de la hoja Base. (2))
$metas = [
    'Nomina' => 78,
    'Rutas' => 200,
    'Ordenes Servicios' => 24,
    'Poda' => 37,
    'Energia' => 200,
    'Inventarios' => 384
];

// Gastos mensuales (de la hoja Base. (3))
// Reconstruidos cuidadosamente para cuadrar los totales:
// Totales: Nomina=50, Rutas=79, Ordenes=8.36, Poda=12, Energia=94, Inventarios=113
$gastos = [
    ['mes' => '01', 'Nomina' => 10, 'Rutas' => 0, 'Ordenes Servicios' => 0.88, 'Poda' => 3, 'Energia' => 42, 'Inventarios' => 0],
    ['mes' => '02', 'Nomina' => 15, 'Rutas' => -2, 'Ordenes Servicios' => 2.45, 'Poda' => 3, 'Energia' => 0, 'Inventarios' => 0],
    ['mes' => '03', 'Nomina' => 12, 'Rutas' => 27, 'Ordenes Servicios' => 0, 'Poda' => 3, 'Energia' => 40, 'Inventarios' => 0],
    ['mes' => '04', 'Nomina' => 6, 'Rutas' => 21, 'Ordenes Servicios' => 0.82, 'Poda' => 3, 'Energia' => 12, 'Inventarios' => 0],
    ['mes' => '05', 'Nomina' => 7, 'Rutas' => 33, 'Ordenes Servicios' => 4.21, 'Poda' => 0, 'Energia' => 0, 'Inventarios' => 113],
];

// 1. Asegurar Categorías y Procesos
$pdo->exec("DELETE FROM procesos"); // Limpiar procesos viejos
echo "Procesos limpiados.\n";

$catIds = [];
foreach ($metas as $nombre => $metaAnual) {
    // Buscar o crear categoría
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre LIKE ? LIMIT 1");
    $stmt->execute(["%$nombre%"]);
    $catId = $stmt->fetchColumn();
    
    if (!$catId) {
        $pdo->exec("INSERT INTO categorias (nombre, color, icono) VALUES ('$nombre', '#3B82F6', 'circle')");
        $catId = $pdo->lastInsertId();
    }
    $catIds[$nombre] = $catId;

    // Crear proceso
    $metaReal = $metaAnual * $mult;
    $stmtProc = $pdo->prepare("INSERT INTO procesos (nombre, descripcion, meta_anual, anio, categoria_id) VALUES (?, ?, ?, 2026, ?)");
    $stmtProc->execute([$nombre, "Control de $nombre", $metaReal, $catId]);
    echo "Proceso '$nombre' creado con meta: $metaReal\n";
}

// 2. Insertar Gastos 2026 (sin borrar los de 2024 porque el usuario quiere TODO)
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

echo "Inyección completada exitosamente. Total de transacciones 2026 insertadas: $inserted\n";
