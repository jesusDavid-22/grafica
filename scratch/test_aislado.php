<?php
require 'includes/db.php';
require 'includes/mci_functions.php';
echo "Aislado Mayo:\n";
print_r(getDesviacionCategoriasMCI($pdo, '2026-05', 'mes', 'aislado')['totales']);
echo "\nAcumulado Mayo:\n";
print_r(getDesviacionCategoriasMCI($pdo, '2026-05', 'mes', 'acumulado')['totales']);
