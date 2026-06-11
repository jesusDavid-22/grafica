<?php
require_once __DIR__ . '/../includes/db.php';

$presupuesto_total_cop = 65880028500; 
// 236,000 USD * 3552.25 = 838331000
$meta_ahorro_cop = 838331000;

$stmt = $pdo->prepare("UPDATE presupuestos SET total = ?, meta_ahorro_monto = ?, meta_ahorro_pct = ? WHERE periodo = '2026'");
$stmt->execute([$presupuesto_total_cop, $meta_ahorro_cop, 0]);

echo "Valores corregidos.";
