<?php
require_once __DIR__ . '/../includes/db.php';

echo "Iniciando inyección de datos desde MCI 2026...\n";

// Vaciar tablas de transacciones
$pdo->exec("DELETE FROM gastos");
$pdo->exec("DELETE FROM ingresos");
echo "Tablas gastos e ingresos limpiadas.\n";

// Buscar o crear categoría 'Rutas'
$stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = 'Rutas' OR nombre LIKE '%Rutas%' LIMIT 1");
$stmt->execute();
$cat_id = $stmt->fetchColumn();

if (!$cat_id) {
    $pdo->exec("INSERT INTO categorias (nombre, color, icono) VALUES ('Rutas', '#f97316', 'truck')");
    $cat_id = $pdo->lastInsertId();
    echo "Categoría 'Rutas' creada con ID: $cat_id\n";
} else {
    echo "Usando categoría 'Rutas' existente (ID: $cat_id)\n";
}

// Mapeo de meses a número
$mesesMap = [
    'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
    'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
    'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'
];

$csvFile = __DIR__ . '/base.csv';
if (!file_exists($csvFile)) {
    die("Error: No se encontró base.csv\n");
}

$handle = fopen($csvFile, 'r');
$headerSkipped = false;
$inserted = 0;

$stmtGasto = $pdo->prepare("INSERT INTO gastos (concepto, monto, fecha, categoria_id) VALUES (?, ?, ?, ?)");

while (($data = fgetcsv($handle)) !== FALSE) {
    if (!$headerSkipped) {
        $headerSkipped = true;
        continue;
    }

    $anio = trim($data[0]);
    $mesStr = strtolower(trim($data[1]));
    
    // Validar fila
    if (empty($anio) || !isset($mesesMap[$mesStr])) continue;

    $mesNum = $mesesMap[$mesStr];
    $fecha = "$anio-$mesNum-28"; // Usamos el 28 de cada mes para evitar problemas con Febrero

    // Limpiar montos (remover $, comas y espacios)
    $limpiarMonto = function($str) {
        return (float) str_replace(['$', ',', ' '], '', $str);
    };

    $rutasBasicas = $limpiarMonto($data[2]);
    $rutasAdicionales = $limpiarMonto($data[3]);
    $descuentos = $limpiarMonto($data[4]);

    // Insertar Rutas Básicas
    if ($rutasBasicas > 0) {
        $stmtGasto->execute(["Rutas Básicas", $rutasBasicas, $fecha, $cat_id]);
        $inserted++;
    }

    // Insertar Rutas Adicionales
    if ($rutasAdicionales > 0) {
        $stmtGasto->execute(["Rutas Adicionales", $rutasAdicionales, $fecha, $cat_id]);
        $inserted++;
    }

    // Insertar Descuentos (Como gasto negativo)
    if ($descuentos > 0) {
        $stmtGasto->execute(["Descuentos", -$descuentos, $fecha, $cat_id]);
        $inserted++;
    }
}
fclose($handle);

echo "Inyección completada exitosamente. Total de transacciones insertadas: $inserted\n";
