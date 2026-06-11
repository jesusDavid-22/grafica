<?php
require 'includes/db.php';
$stmt = $pdo->prepare("SELECT SUM(monto) FROM gastos WHERE strftime('%Y-%m', fecha) = :periodo");
$stmt->execute([':periodo' => '2026-04']);
var_dump($stmt->fetchColumn());
