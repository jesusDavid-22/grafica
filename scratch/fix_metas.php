<?php
require_once __DIR__ . '/../includes/db.php';

echo "Corrigiendo metas MCI...\n";

// 1. Pasar las metas de 'procesos' a 'metas_categoria'
$pdo->exec("DELETE FROM metas_categoria WHERE anio = 2026");

$stmt = $pdo->query("SELECT categoria_id, meta_anual FROM procesos WHERE anio = 2026");
$procesos = $stmt->fetchAll();

$insert = $pdo->prepare("INSERT INTO metas_categoria (categoria_id, anio, valor_meta) VALUES (?, 2026, ?)");
foreach($procesos as $p) {
    if ($p['categoria_id']) {
        $insert->execute([$p['categoria_id'], $p['meta_anual']]);
        echo "Meta insertada para categoria {$p['categoria_id']} -> {$p['meta_anual']}\n";
    }
}

// 2. Crear el Presupuesto 2026
// Ppto aprobado: 18,782 millones
// Ppto ajustado (meta): 18,546 millones
// Ahorro: 236 millones
$pdo->exec("DELETE FROM presupuestos WHERE periodo = '2026'");
$stmtPpto = $pdo->prepare("INSERT INTO presupuestos (total, periodo, meta_ahorro_pct, meta_ahorro_monto) VALUES (?, '2026', 0, ?)");
$stmtPpto->execute([18782000000, 236000000]);
echo "Presupuesto global 2026 insertado.\n";

echo "Corrección terminada.\n";
