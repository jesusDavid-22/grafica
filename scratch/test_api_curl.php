<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/grafica/api.php?action=get_mci_dashboard&periodo=2026-05&vista=mes&modo=acumulado");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($ch);
curl_close($ch);
echo $output;
