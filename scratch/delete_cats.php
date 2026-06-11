<?php
require_once __DIR__ . '/../includes/db.php';

echo "Borrando categorias sin uso...\n";

// Borrar categorías que no tienen gastos ni procesos
$pdo->exec("DELETE FROM categorias WHERE 
    (SELECT COUNT(*) FROM gastos WHERE categoria_id = categorias.id) = 0 
    AND 
    (SELECT COUNT(*) FROM procesos WHERE categoria_id = categorias.id) = 0");

echo "Categorías basura eliminadas exitosamente.\n";
