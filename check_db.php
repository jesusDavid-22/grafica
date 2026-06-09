<?php
require 'includes/db.php';
require 'includes/functions.php';

$p = date('Y-m');
$cats = getGastosPorCategoria($pdo, $p);
$kpis = getDashboardKPIs($pdo, $p);

echo "Periodo actual: $p\n";
echo "Presupuesto: {$kpis['presupuesto']}\n";
echo "Gasto total: {$kpis['gasto_total']}\n";
echo "Ingreso total: {$kpis['ingreso_total']}\n";
echo "\nCategorias con gastos en $p:\n";
foreach($cats as $c) {
    if($c['total'] > 0) {
        echo "  {$c['nombre']}: \${$c['total']}\n";
    }
}

$stmt = $pdo->query("SELECT MIN(fecha) as min_f, MAX(fecha) as max_f FROM gastos");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nRango fechas gastos: {$r['min_f']} a {$r['max_f']}\n";

$stmt2 = $pdo->query("SELECT COUNT(*) FROM gastos");
echo "Total registros gastos: " . $stmt2->fetchColumn() . "\n";

$stmt3 = $pdo->query("SELECT COUNT(*) FROM ingresos");
echo "Total registros ingresos: " . $stmt3->fetchColumn() . "\n";

$stmt4 = $pdo->query("SELECT DISTINCT strftime('%Y-%m', fecha) as mes FROM gastos ORDER BY mes DESC LIMIT 8");
echo "\nMeses con gastos disponibles:\n";
while($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$row['mes']}\n";
}
