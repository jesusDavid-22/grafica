<?php
$_GET['action'] = 'get_mci_dashboard';
$_GET['periodo'] = '2026-05';
$_GET['vista'] = 'mes';
$request = $_GET;
require 'api.php';
echo json_encode($response['mci_desviacion']['totales'], JSON_PRETTY_PRINT);
