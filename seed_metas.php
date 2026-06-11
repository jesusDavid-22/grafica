<?php
require_once __DIR__ . '/includes/db.php';

$anio = 2026;

// Some realistic metas for testing based on their "Ejecutado"
// From the image, Nomina has around 3M ejecutado. Let's make meta 10M.
// Tecnologia has around 2.6M ejecutado. Let's make meta 8M.
// Compras has around 1.5M ejecutado. Let's make meta 5M.

$stmt = $pdo->query("SELECT id, nombre FROM categorias");
$categorias = $stmt->fetchAll();

foreach ($categorias as $cat) {
    $meta = 0;
    if (strpos(strtolower($cat['nombre']), 'nómina') !== false) {
        $meta = 10000000;
    } elseif (strpos(strtolower($cat['nombre']), 'tecnología') !== false) {
        $meta = 8000000;
    } elseif (strpos(strtolower($cat['nombre']), 'compras') !== false) {
        $meta = 5000000;
    } elseif (strpos(strtolower($cat['nombre']), 'servicios') !== false) {
        $meta = 2000000;
    } else {
        $meta = 1000000;
    }

    $pdo->prepare("
        INSERT INTO metas_categoria (categoria_id, anio, valor_meta) 
        VALUES (?, ?, ?)
        ON CONFLICT(categoria_id, anio) DO UPDATE SET valor_meta = excluded.valor_meta
    ")->execute([$cat['id'], $anio, $meta]);
}

echo "Metas actualizadas para $anio.\n";
