<?php
require_once __DIR__ . '/db.php';

function getDashboardKPIs($pdo, $periodo, $filtro = 'mes') {
    $anio = substr($periodo, 0, 4);
    $mes = (int)substr($periodo, 5, 2);
    
    // Condición de fecha dinámica
    if ($filtro === 'anio') {
        $fecha_cond = "strftime('%Y', fecha) = :anio";
        $params = [':anio' => $anio];
        $presupuesto_factor = 1.0;
    } elseif ($filtro === 'trimestre') {
        $trimestre = ceil($mes / 3);
        $mes_inicio = str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT);
        $mes_fin = str_pad($trimestre * 3, 2, '0', STR_PAD_LEFT);
        $fecha_cond = "strftime('%Y', fecha) = :anio AND strftime('%m', fecha) BETWEEN :m_ini AND :m_fin";
        $params = [':anio' => $anio, ':m_ini' => $mes_inicio, ':m_fin' => $mes_fin];
        $presupuesto_factor = 3.0 / 12.0;
    } elseif ($filtro === 'dia') {
        // Usa el día actual si coincide el mes, si no usa el 1
        $dia_actual = (int)date('d');
        if ($anio == date('Y') && $mes == date('m')) {
            $dia = str_pad($dia_actual, 2, '0', STR_PAD_LEFT);
        } else {
            $dia = '01'; // Fallback
        }
        $fecha_exacta = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-$dia";
        $fecha_cond = "strftime('%Y-%m-%d', fecha) = :fecha";
        $params = [':fecha' => $fecha_exacta];
        $presupuesto_factor = 1.0 / 365.0;
    } else {
        // 'mes' o 'semana'
        $fecha_cond = "strftime('%Y-%m', fecha) = :periodo";
        $params = [':periodo' => substr($periodo, 0, 7)];
        $presupuesto_factor = 1.0 / 12.0;
    }

    $stmt = $pdo->prepare("SELECT total, meta_ahorro_pct, meta_ahorro_monto FROM presupuestos WHERE periodo = :anio");
    $stmt->execute([':anio' => $anio]);
    $presupuestoRow = $stmt->fetch();
    
    $metaAhorroPct   = 0.0;
    $metaAhorroMonto = 0.0;
    
    if ($presupuestoRow !== false) {
        $presupuestoAnual    = (float)$presupuestoRow['total'];
        $presupuestoTotal    = $presupuestoAnual * $presupuesto_factor;
        $metaAhorroPct       = (float)($presupuestoRow['meta_ahorro_pct'] ?? 0.0);
        $metaAhorroMonto     = (float)($presupuestoRow['meta_ahorro_monto'] ?? 0.0);
    } else {
        $presupuestoTotal  = 0.0;
        $presupuestoAnual  = 0.0;
    }
    
    $stmt = $pdo->prepare("SELECT SUM(monto) FROM gastos WHERE $fecha_cond");
    $stmt->execute($params);
    $gastoTotal = (float)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT SUM(monto) FROM ingresos WHERE $fecha_cond");
    $stmt->execute($params);
    $ingresoTotal = (float)$stmt->fetchColumn();
    
    $dineroRestante = max(0, $presupuestoTotal - $gastoTotal);
    $porcentajeUsado = $presupuestoTotal > 0 ? ($gastoTotal / $presupuestoTotal) * 100 : 0;
    
    // Proyección (simplificada)
    $diasTranscurridos = (int)date('d');
    $diasMes = cal_days_in_month(CAL_GREGORIAN, date('m'), date('Y'));
    $gastoDiarioPromedio = $diasTranscurridos > 0 ? $gastoTotal / $diasTranscurridos : 0;
    $proyeccionFinMes = $gastoDiarioPromedio * $diasMes;
    $riesgoSobrecosto = $proyeccionFinMes > $presupuestoTotal;
    
    return [
        'presupuesto'       => round($presupuestoTotal, 2),
        'presupuesto_anual' => round($presupuestoAnual, 2),
        'gasto_total'       => round($gastoTotal, 2),
        'ingreso_total'     => round($ingresoTotal, 2),
        'dinero_restante'   => round($dineroRestante, 2),
        'porcentaje_usado'  => round($porcentajeUsado, 1),
        'meta_ahorro_pct'   => $metaAhorroPct,
        'meta_ahorro_monto' => $metaAhorroMonto,
        'proyeccion_fin_mes'=> round($proyeccionFinMes, 2),
        'riesgo_sobrecosto' => $riesgoSobrecosto
    ];
}
