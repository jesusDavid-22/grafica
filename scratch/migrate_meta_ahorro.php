<?php
$pdo = new PDO('sqlite:c:/xampp/htdocs/grafica/database/finanzas.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $pdo->exec('ALTER TABLE presupuestos ADD COLUMN meta_ahorro_pct REAL DEFAULT 0');
    echo "Columna meta_ahorro_pct agregada a presupuestos.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "La columna ya existía.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
