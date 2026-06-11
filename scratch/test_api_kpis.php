<?php
$_GET['action'] = 'get_dashboard_data';
$_GET['periodo'] = '2026-05';
$_GET['vista'] = 'mes';
$request = $_GET;
require 'api.php';
echo json_encode($response['kpis'], JSON_PRETTY_PRINT);
