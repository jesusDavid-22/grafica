<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mci_functions.php';

$desv = getDesviacionCategoriasMCI($pdo, 2026, 6);
$t = $desv['totales'];

echo "MCI Anual (Suma metas categorias): " . number_format($t['mci_anual']) . "\n";
echo "Estimado a Junio (Prorrateado): " . number_format($t['estimado']) . "\n";
echo "Ejecutado a Junio: " . number_format($t['ejecutado']) . "\n";
echo "Diferencia (Ahorro calculado actual): " . number_format($t['diferencia']) . "\n";

$stmt = $pdo->query("SELECT total, meta_ahorro_monto FROM presupuestos WHERE periodo = '2026'");
$ppto = $stmt->fetch();
echo "\nPresupuesto Global Anual: " . number_format($ppto['total']) . "\n";
echo "Presupuesto Global a Junio (Prorrateado): " . number_format($ppto['total'] * (6/12)) . "\n";
echo "Ahorro vs Presupuesto Global: " . number_format(($ppto['total'] * (6/12)) - $t['ejecutado']) . "\n";

