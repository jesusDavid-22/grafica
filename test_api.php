<?php
require 'includes/db.php';
require 'includes/functions.php';

$p = date('Y-m');
$anio = date('Y');

echo "=== RESUMEN EJECUTIVO ===\n";
$r = getResumenEjecutivo($pdo, $p);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== CUMPLIMIENTO GENERAL ===\n";
$c = getCumplimientoGeneral($pdo, $anio);
echo json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== TOP 5 GASTOS ===\n";
$t = getTopGastos($pdo, 5, $p);
foreach ($t as $g) {
    echo "  [{$g['categoria']}] {$g['concepto']}: \${$g['monto']}\n";
}

echo "\n=== DESVIACION PRESUPUESTARIA ===\n";
$d = getDesviacionPresupuestaria($pdo, $p);
echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== HISTORICO MENSUAL (primeros 3 meses) ===\n";
$h = getHistoricoMensual($pdo, $anio, 12);
foreach (array_slice($h, 0, 4) as $m) {
    echo "  {$m['mes']}: Gastos=\${$m['gastos']} Ingresos=\${$m['ingresos']} Cumpl={$m['cumplimiento']}%\n";
}

echo "\n=== PROYECCION POR CATEGORIA ===\n";
$pr = getProyeccionPorCategoria($pdo, $p);
foreach ($pr as $cat) {
    echo "  {$cat['nombre']}: Ejecutado=\${$cat['ejecutado']} -> Proyeccion=\${$cat['proyeccion']}\n";
}

echo "\n=== ALERTAS ===\n";
$alertas = getAlertasActivas($pdo, $p);
echo "Total alertas: " . count($alertas) . "\n";
foreach ($alertas as $a) {
    echo "  [{$a['nivel']}] {$a['mensaje']}\n";
}

echo "\n=== METAS ===\n";
$metas = getMetas($pdo, $anio);
foreach ($metas as $m) {
    echo "  {$m['nombre']}: Meta=\${$m['meta_anual']} Ejecutado=\${$m['ejecutado']} ({$m['pct']}%)\n";
}

echo "\n=== AVANCE POR PROCESO ===\n";
$procesos = getAvancePorProceso($pdo, $anio);
foreach ($procesos as $proc) {
    echo "  {$proc['nombre']}: Meta=\${$proc['meta']} Ejecutado=\${$proc['ejecutado']} ({$proc['pct']}%)\n";
}

echo "\nTodo OK!\n";
