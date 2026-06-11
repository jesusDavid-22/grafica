<?php
require_once __DIR__ . '/../includes/db.php';

// Valores proporcionados por el usuario
$presupuesto_total_cop = 66748680076; 
// Si 18,782,000 USD = 66,748,680,076 COP, entonces la tasa es ~3553.86
// Ahorro = 236,000 USD * 3553.86 = 838,711,985 COP
$meta_ahorro_cop = 838711985;

echo "Actualizando presupuesto global a pesos colombianos...\n";

$stmt = $pdo->prepare("UPDATE presupuestos SET total = ?, meta_ahorro_monto = ? WHERE periodo = '2026'");
$stmt->execute([$presupuesto_total_cop, $meta_ahorro_cop]);

echo "Presupuesto actualizado exitosamente a $presupuesto_total_cop COP y ahorro meta a $meta_ahorro_cop COP.\n";
