<?php
/**
 * Funciones Analíticas y de Negocio para el Dashboard Financiero
 */

require_once __DIR__ . '/db.php';

/**
 * Obtener todos los KPIs financieros de un período específico (YYYY-MM)
 */
function getDashboardKPIs($pdo, $periodo) {
    // Extraer el año del período (formato YYYY de YYYY-MM)
    $anio = substr($periodo, 0, 4);
    
    // 1. Obtener Presupuesto del año para prorratear entre 12 y su meta de ahorro
    $stmt = $pdo->prepare("SELECT total, meta_ahorro_pct, meta_ahorro_monto FROM presupuestos WHERE periodo = :anio");
    $stmt->execute([':anio' => $anio]);
    $presupuestoRow = $stmt->fetch();
    
    $metaAhorroPct   = 0.0;
    $metaAhorroMonto = 0.0;
    
    // Agregar columna si no existe (compatibilidad)
    try { $pdo->exec("ALTER TABLE presupuestos ADD COLUMN meta_ahorro_monto REAL DEFAULT 0"); } catch (Exception $e) {}
    
    if ($presupuestoRow !== false) {
        $presupuestoAnual    = (float)$presupuestoRow['total'];
        $presupuestoTotal    = $presupuestoAnual / 12.0; // Prorrateado
        $metaAhorroPct       = (float)($presupuestoRow['meta_ahorro_pct'] ?? 0.0);
        $metaAhorroMonto     = (float)($presupuestoRow['meta_ahorro_monto'] ?? 0.0);
    } else {
        // Fallback: Buscar presupuesto tradicional de mes específico
        $stmt = $pdo->prepare("SELECT total, meta_ahorro_pct, meta_ahorro_monto FROM presupuestos WHERE periodo = :periodo");
        $stmt->execute([':periodo' => $periodo]);
        $presupuestoMensualEspecifico = $stmt->fetch();
        if ($presupuestoMensualEspecifico !== false) {
            $presupuestoTotal    = (float)$presupuestoMensualEspecifico['total'];
            $presupuestoAnual    = $presupuestoTotal * 12.0;
            $metaAhorroPct       = (float)($presupuestoMensualEspecifico['meta_ahorro_pct'] ?? 0.0);
            $metaAhorroMonto     = (float)($presupuestoMensualEspecifico['meta_ahorro_monto'] ?? 0.0);
        } else {
            $presupuestoTotal  = 0.0;
            $presupuestoAnual  = 0.0;
        }
    }
    
    // 2. Obtener Gasto Total del período (mes actual)
    $stmt = $pdo->prepare("
        SELECT SUM(monto) FROM gastos 
        WHERE strftime('%Y-%m', fecha) = :periodo
    ");
    $stmt->execute([':periodo' => $periodo]);
    $gastoTotal = $stmt->fetchColumn();
    $gastoTotal = $gastoTotal !== false ? (float)$gastoTotal : 0.0;
    
    // 3. Obtener Ingreso Total del período (mes actual)
    $stmt = $pdo->prepare("
        SELECT SUM(monto) FROM ingresos 
        WHERE strftime('%Y-%m', fecha) = :periodo
    ");
    $stmt->execute([':periodo' => $periodo]);
    $ingresoTotal = $stmt->fetchColumn();
    $ingresoTotal = $ingresoTotal !== false ? (float)$ingresoTotal : 0.0;
    
    // 4. Obtener Ingreso Histórico y Gasto Histórico para Eficiencia Global
    $stmt = $pdo->query("SELECT SUM(monto) FROM ingresos");
    $ingresoHistorico = $stmt->fetchColumn();
    $ingresoHistorico = $ingresoHistorico !== false ? (float)$ingresoHistorico : 0.0;
    
    $stmt = $pdo->query("SELECT SUM(monto) FROM gastos");
    $gastoHistorico = $stmt->fetchColumn();
    $gastoHistorico = $gastoHistorico !== false ? (float)$gastoHistorico : 0.0;
    
    // 5. Cálculos de Indicadores
    $porcentajeUsado = 0.0;
    if ($presupuestoTotal > 0) {
        $porcentajeUsado = ($gastoTotal / $presupuestoTotal) * 100;
    }
    
    $dineroRestante = $presupuestoTotal - $gastoTotal;
    
    // Desviación presupuestal = gasto real - gasto planificado (presupuesto)
    $desviacion = $gastoTotal - $presupuestoTotal;
    
    // Semáforo de Consumo
    $semaforo = 'verde';
    if ($porcentajeUsado >= 95) {
        $semaforo = 'rojo';
    } elseif ($porcentajeUsado >= 80) {
        $semaforo = 'amarillo';
    }
    
    // Relación Rendimiento vs Gasto (Eficiencia = Ingresos Históricos / Gastos Históricos)
    // Coeficiente: > 1 indica que entra más dinero del que sale.
    $eficiencia = 0.0;
    if ($gastoHistorico > 0) {
        $eficiencia = $ingresoHistorico / $gastoHistorico;
    } elseif ($ingresoHistorico > 0) {
        $eficiencia = 10.0; // Alto rendimiento por tener 0 gastos
    }
    
    // --- LÓGICA DE PROYECCIÓN MENSUAL (FORECAST / PRONÓSTICO) ---
    $anioActual = (int)date('Y');
    $mesActual = (int)date('m');
    $diaActual = (int)date('d');
    
    $selAnio = (int)substr($periodo, 0, 4);
    $selMes = (int)substr($periodo, 5, 2);
    
    $totalDiasMes = cal_days_in_month(CAL_GREGORIAN, $selMes, $selAnio);
    
    if ($selAnio < $anioActual || ($selAnio == $anioActual && $selMes < $mesActual)) {
        // Mes en el pasado: completo
        $diasTranscurridos = $totalDiasMes;
    } elseif ($selAnio == $anioActual && $selMes == $mesActual) {
        // Mes actual: días transcurridos hasta la fecha de hoy
        $diasTranscurridos = $diaActual;
    } else {
        // Mes futuro
        $diasTranscurridos = 0;
    }
    
    $promedioDiario = 0.0;
    $proyeccionFinMes = 0.0;
    if ($diasTranscurridos > 0) {
        $promedioDiario = $gastoTotal / $diasTranscurridos;
        $proyeccionFinMes = $promedioDiario * $totalDiasMes;
    } else {
        // Si es futuro, la proyección inicial es cero
        $proyeccionFinMes = 0.0;
    }
    
    // Si la proyección supera el límite mensual prorrateado, hay riesgo latente de sobrecosto
    $riesgoSobrecosto = false;
    if ($presupuestoTotal > 0 && $proyeccionFinMes > $presupuestoTotal) {
        $riesgoSobrecosto = true;
    }
    
    // Gasto anual acumulado real (para comparar contra meta de ahorro)
    $stmtAnual = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE strftime('%Y', fecha) = :anio");
    $stmtAnual->execute([':anio' => $anio]);
    $gastoAnualAcumulado = (float)$stmtAnual->fetchColumn();
    
    // Si meta_ahorro_monto es 0 pero pct > 0, calcular monto equivalente
    if ($metaAhorroMonto == 0.0 && $metaAhorroPct > 0 && $presupuestoAnual > 0) {
        $metaAhorroMonto = $presupuestoAnual * ($metaAhorroPct / 100.0);
    }
    
    return [
        'presupuesto'           => $presupuestoTotal,
        'presupuesto_anual'     => $presupuestoAnual,
        'meta_ahorro_pct'       => $metaAhorroPct,
        'meta_ahorro_monto'     => round($metaAhorroMonto, 2),
        'gasto_anual_acumulado' => round($gastoAnualAcumulado, 2),
        'gasto_total'           => $gastoTotal,
        'ingreso_total'         => $ingresoTotal,
        'porcentaje_usado'      => round($porcentajeUsado, 1),
        'dinero_restante'       => $dineroRestante,
        'desviacion'            => $desviacion,
        'semaforo'              => $semaforo,
        'eficiencia'            => round($eficiencia, 2),
        'periodo'               => $periodo,
        'promedio_diario'       => round($promedioDiario, 2),
        'proyeccion_fin_mes'    => round($proyeccionFinMes, 2),
        'riesgo_sobrecosto'     => $riesgoSobrecosto
    ];
}

/**
 * Obtener distribución de gastos por categoría en un período
 */
function getGastosPorCategoria($pdo, $periodo, $vista = 'mes') {
    $filtro = ($vista === 'anio') ? substr($periodo, 0, 4) : $periodo;
    
    $stmt = $pdo->prepare("
        SELECT c.nombre, c.color, c.icono, COALESCE(SUM(g.monto), 0) as total
        FROM categorias c
        LEFT JOIN gastos g ON c.id = g.categoria_id AND g.fecha LIKE :filtro || '%'
        GROUP BY c.id
        ORDER BY total DESC
    ");
    $stmt->execute([':filtro' => $filtro]);
    return $stmt->fetchAll();
}

/**
 * Obtener detalle de conceptos para una categoría específica en un período (Drill-down)
 */
function getGastosDetallePorCategoria($pdo, $periodo, $vista, $categoriaNombre) {
    $filtro = ($vista === 'anio') ? substr($periodo, 0, 4) : $periodo;
    
    $stmt = $pdo->prepare("
        SELECT g.concepto as nombre, c.color, SUM(g.monto) as total
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        WHERE c.nombre = :cat_nombre AND g.fecha LIKE :filtro || '%'
        GROUP BY g.concepto
        ORDER BY total DESC
    ");
    $stmt->execute([
        ':cat_nombre' => $categoriaNombre,
        ':filtro' => $filtro
    ]);
    return $stmt->fetchAll();
}

/**
 * Obtener listado de transacciones exactas para una categoría en un periodo (para Modal)
 */
function getHistorialTransaccionesPorCategoria($pdo, $categoriaNombre, $periodo, $vista) {
    // Si la vista es año, buscamos todo el año. Si es mes, buscamos ese mes.
    $filtro = ($vista === 'anio') ? substr($periodo, 0, 4) : $periodo;
    
    $stmt = $pdo->prepare("
        SELECT g.id, g.concepto, g.monto, g.fecha, c.color, c.icono
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        WHERE c.nombre = :cat_nombre AND g.fecha LIKE :filtro || '%'
        ORDER BY g.fecha DESC
    ");
    $stmt->execute([
        ':cat_nombre' => $categoriaNombre,
        ':filtro' => $filtro
    ]);
    return $stmt->fetchAll();
}

/**
 * Obtener histórico de gastos de los últimos 6 meses (para el gráfico de barras)
 */
function getGastosPorMes($pdo) {
    // Consulta para agrupar ingresos y gastos de los últimos 6 meses de forma unificada
    $stmt = $pdo->query("
        SELECT 
            strftime('%Y-%m', fecha) as mes,
            SUM(monto) as total
        FROM gastos
        GROUP BY mes
        ORDER BY mes DESC
        LIMIT 6
    ");
    $gastos = $stmt->fetchAll();
    
    $stmt = $pdo->query("
        SELECT 
            strftime('%Y-%m', fecha) as mes,
            SUM(monto) as total
        FROM ingresos
        GROUP BY mes
        ORDER BY mes DESC
        LIMIT 6
    ");
    $ingresos = $stmt->fetchAll();
    
    // Unir y ordenar cronológicamente
    $datosMensuales = [];
    
    // Obtener lista de los últimos 6 meses calendario
    for ($i = 5; $i >= 0; $i--) {
        $mesStr = date('Y-m', strtotime("-$i months"));
        $datosMensuales[$mesStr] = [
            'mes' => $mesStr,
            'nombre_mes' => translateMonth(date('F Y', strtotime("-$i months"))),
            'gastos' => 0.0,
            'ingresos' => 0.0
        ];
    }
    
    foreach ($gastos as $g) {
        if (isset($datosMensuales[$g['mes']])) {
            $datosMensuales[$g['mes']]['gastos'] = (float)$g['total'];
        }
    }
    
    foreach ($ingresos as $ing) {
        if (isset($datosMensuales[$ing['mes']])) {
            $datosMensuales[$ing['mes']]['ingresos'] = (float)$ing['total'];
        }
    }
    
    return array_values($datosMensuales);
}

/**
 * Obtener tendencia temporal de gastos diarios dentro del mes actual (para el gráfico de área spline)
 */
function getTendenciaTemporal($pdo, $periodo) {
    // Traer todos los días del mes y su suma de gastos correspondientes
    $stmt = $pdo->prepare("
        SELECT strftime('%d', fecha) as dia, SUM(monto) as total, fecha
        FROM gastos
        WHERE strftime('%Y-%m', fecha) = :periodo
        GROUP BY dia
        ORDER BY dia ASC
    ");
    $stmt->execute([':periodo' => $periodo]);
    $gastosDiarios = $stmt->fetchAll();
    
    // Rellenar días del mes para que la gráfica no tenga vacíos y se vea fluida
    $numDias = cal_days_in_month(CAL_GREGORIAN, (int)substr($periodo, 5, 2), (int)substr($periodo, 0, 4));
    $tendencia = [];
    
    for ($dia = 1; $dia <= $numDias; $dia++) {
        $diaPad = str_pad($dia, 2, '0', STR_PAD_LEFT);
        $tendencia[$diaPad] = 0.0;
    }
    
    foreach ($gastosDiarios as $gd) {
        $tendencia[$gd['dia']] = (float)$gd['total'];
    }
    
    // Acumular los gastos día a día para una visualización de curva de consumo acumulativo
    $acumulado = 0.0;
    $dias = [];
    $valoresDiarios = [];
    $valoresAcumulados = [];
    
    foreach ($tendencia as $dia => $monto) {
        $acumulado += $monto;
        $dias[] = 'Día ' . (int)$dia;
        $valoresDiarios[] = $monto;
        $valoresAcumulados[] = round($acumulado, 2);
    }
    
    return [
        'dias' => $dias,
        'diario' => $valoresDiarios,
        'acumulado' => $valoresAcumulados
    ];
}

/**
 * Tendencia de gastos apilados por categoría.
 * Vista mensual  → eje X = días del mes,   cada serie = una categoría.
 * Vista anual    → eje X = meses del año,  cada serie = una categoría.
 */
function getTendenciaStackedBar($pdo, $periodo) {
    $anio = substr($periodo, 0, 4);
    $mes  = (int)substr($periodo, 5, 2);

    // Obtener todas las categorías activas (con al menos 1 gasto histórico)
    $stmtCats = $pdo->query("
        SELECT DISTINCT c.id, c.nombre, c.color
        FROM categorias c
        INNER JOIN gastos g ON g.categoria_id = c.id
        ORDER BY c.nombre ASC
    ");
    $categorias = $stmtCats->fetchAll();

    // ── Vista MENSUAL: datos día a día ────────────────────────────────────
    $numDias = cal_days_in_month(CAL_GREGORIAN, $mes, (int)$anio);
    $labels  = [];
    for ($d = 1; $d <= $numDias; $d++) { $labels[] = $d; }

    $stmt = $pdo->prepare("
        SELECT CAST(strftime('%d', fecha) AS INTEGER) as dia,
               categoria_id,
               SUM(monto) as total
        FROM gastos
        WHERE strftime('%Y-%m', fecha) = :periodo
        GROUP BY dia, categoria_id
    ");
    $stmt->execute([':periodo' => $periodo]);
    $rows = $stmt->fetchAll();

    $byDiaCat = [];
    foreach ($rows as $r) {
        $byDiaCat[(int)$r['categoria_id']][(int)$r['dia']] = (float)$r['total'];
    }

    $seriesMes = [];
    foreach ($categorias as $cat) {
        $values = [];
        for ($d = 1; $d <= $numDias; $d++) {
            $values[] = $byDiaCat[(int)$cat['id']][$d] ?? 0.0;
        }
        $seriesMes[] = ['nombre' => $cat['nombre'], 'color' => $cat['color'], 'datos' => $values];
    }

    // ── Vista ANUAL: datos mes a mes ──────────────────────────────────────
    $nombresMeses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

    $stmtA = $pdo->prepare("
        SELECT CAST(strftime('%m', fecha) AS INTEGER) as mes,
               categoria_id,
               SUM(monto) as total
        FROM gastos
        WHERE strftime('%Y', fecha) = :anio
        GROUP BY mes, categoria_id
    ");
    $stmtA->execute([':anio' => $anio]);
    $rowsA = $stmtA->fetchAll();

    $byMesCat = [];
    foreach ($rowsA as $r) {
        $byMesCat[(int)$r['categoria_id']][(int)$r['mes']] = (float)$r['total'];
    }

    $seriesAnio = [];
    foreach ($categorias as $cat) {
        $values = [];
        for ($m = 1; $m <= 12; $m++) {
            $values[] = $byMesCat[(int)$cat['id']][$m] ?? 0.0;
        }
        $seriesAnio[] = ['nombre' => $cat['nombre'], 'color' => $cat['color'], 'datos' => $values];
    }

    return [
        'vista_mes'  => ['labels' => $labels,       'series' => $seriesMes],
        'vista_anio' => ['labels' => $nombresMeses,  'series' => $seriesAnio],
    ];
}

/**
 * Tendencia de conceptos específicos apilados para una sola categoría (Drill-down).
 */
function getTendenciaDetalleCategoria($pdo, $periodo, $categoriaNombre) {
    $anio = substr($periodo, 0, 4);
    $mes  = (int)substr($periodo, 5, 2);

    // Obtener todos los conceptos usados en esta categoría en el año/mes para tenerlos como series fijas
    $stmtCats = $pdo->prepare("
        SELECT DISTINCT g.concepto, c.color
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        WHERE c.nombre = :cat_nombre AND strftime('%Y', g.fecha) = :anio
        ORDER BY g.concepto ASC
    ");
    $stmtCats->execute([':cat_nombre' => $categoriaNombre, ':anio' => $anio]);
    $conceptos = $stmtCats->fetchAll();

    // ── Vista MENSUAL: datos día a día ────────────────────────────────────
    $numDias = cal_days_in_month(CAL_GREGORIAN, $mes, (int)$anio);
    $labels  = [];
    for ($d = 1; $d <= $numDias; $d++) { $labels[] = $d; }

    $stmt = $pdo->prepare("
        SELECT CAST(strftime('%d', g.fecha) AS INTEGER) as dia,
               g.concepto,
               SUM(g.monto) as total
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        WHERE strftime('%Y-%m', g.fecha) = :periodo AND c.nombre = :cat_nombre
        GROUP BY dia, g.concepto
    ");
    $stmt->execute([':periodo' => $periodo, ':cat_nombre' => $categoriaNombre]);
    $rows = $stmt->fetchAll();

    $byDiaConcepto = [];
    foreach ($rows as $r) {
        $concepto = $r['concepto'] ?: 'Sin concepto';
        $byDiaConcepto[$concepto][(int)$r['dia']] = (float)$r['total'];
    }

    $seriesMes = [];
    foreach ($conceptos as $index => $c) {
        $conceptoName = $c['concepto'] ?: 'Sin concepto';
        $values = [];
        for ($d = 1; $d <= $numDias; $d++) {
            $values[] = $byDiaConcepto[$conceptoName][$d] ?? 0.0;
        }
        // Variar un poco el color base de la categoría para distinguir los conceptos
        // En ECharts podemos simplemente omitir el color para que use la paleta por defecto o intentar usar una paleta
        $seriesMes[] = ['nombre' => $conceptoName, 'datos' => $values];
    }

    // ── Vista ANUAL: datos mes a mes ──────────────────────────────────────
    $nombresMeses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

    $stmtA = $pdo->prepare("
        SELECT CAST(strftime('%m', g.fecha) AS INTEGER) as mes_num,
               g.concepto,
               SUM(g.monto) as total
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        WHERE strftime('%Y', g.fecha) = :anio AND c.nombre = :cat_nombre
        GROUP BY mes_num, g.concepto
    ");
    $stmtA->execute([':anio' => $anio, ':cat_nombre' => $categoriaNombre]);
    $rowsA = $stmtA->fetchAll();

    $byMesConcepto = [];
    foreach ($rowsA as $r) {
        $concepto = $r['concepto'] ?: 'Sin concepto';
        $byMesConcepto[$concepto][(int)$r['mes_num']] = (float)$r['total'];
    }

    $seriesAnio = [];
    foreach ($conceptos as $c) {
        $conceptoName = $c['concepto'] ?: 'Sin concepto';
        $values = [];
        for ($m = 1; $m <= 12; $m++) {
            $values[] = $byMesConcepto[$conceptoName][$m] ?? 0.0;
        }
        $seriesAnio[] = ['nombre' => $conceptoName, 'datos' => $values];
    }

    return [
        'vista_mes'  => ['labels' => $labels,       'series' => $seriesMes],
        'vista_anio' => ['labels' => $nombresMeses,  'series' => $seriesAnio],
    ];
}

/**
 * Obtener transacciones recientes (Ledger unificado de gastos e ingresos)
 */
function getTransaccionesRecientes($pdo, $limite = 200, $periodo = null) {
    $sql = "
        SELECT 'gasto' as tipo, g.id, g.concepto, g.monto, g.fecha, c.nombre as categoria, c.color, c.icono, g.categoria_id
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        WHERE (:periodo IS NULL OR strftime('%Y-%m', g.fecha) = :periodo)
        
        UNION ALL
        
        SELECT 'ingreso' as tipo, i.id, i.concepto, i.monto, i.fecha, 'Ingreso' as categoria, '#10B981' as color, 'arrow-up-right' as icono, NULL as categoria_id
        FROM ingresos i
        WHERE (:periodo2 IS NULL OR strftime('%Y-%m', i.fecha) = :periodo2)
        
        ORDER BY fecha DESC, id DESC
        LIMIT :limite
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':periodo',  $periodo, PDO::PARAM_STR);
    $stmt->bindValue(':periodo2', $periodo, PDO::PARAM_STR);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Helper para traducir nombres de meses en inglés al español
 */
function translateMonth($englishDate) {
    $monthsEng = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $monthsEsp = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return str_ireplace($monthsEng, $monthsEsp, $englishDate);
}

// ─────────────────────────────────────────────────────────────────────────────
// FUNCIONES ESTRATÉGICAS — METAS, AVANCE, DESVIACIONES, ALERTAS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Obtener metas anuales por categoría con % de cumplimiento real
 */
function getMetas($pdo, $anio) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.nombre, c.color, c.icono,
            COALESCE(m.valor_meta, 0) as meta_anual,
            COALESCE(SUM(g.monto), 0) as ejecutado
        FROM categorias c
        LEFT JOIN metas_categoria m ON c.id = m.categoria_id AND m.anio = :anio
        LEFT JOIN gastos g ON c.id = g.categoria_id AND strftime('%Y', g.fecha) = :anio_str
        GROUP BY c.id
        ORDER BY ejecutado DESC
    ");
    $stmt->execute([':anio' => (int)$anio, ':anio_str' => (string)$anio]);
    $rows = $stmt->fetchAll();

    return array_map(function($r) {
        $meta     = (float)$r['meta_anual'];
        $ejecutado = (float)$r['ejecutado'];
        $pct = ($meta > 0) ? min(round(($ejecutado / $meta) * 100, 1), 999) : 0;
        $semaforo = 'verde';
        if ($pct > 90) $semaforo = 'rojo';
        elseif ($pct > 70) $semaforo = 'amarillo';
        return [
            'id'         => $r['id'],
            'nombre'     => $r['nombre'],
            'color'      => $r['color'],
            'icono'      => $r['icono'],
            'meta_anual' => $meta,
            'ejecutado'  => $ejecutado,
            'diferencia' => $meta - $ejecutado,
            'pct'        => $pct,
            'semaforo'   => $semaforo,
        ];
    }, $rows);
}

/**
 * Meta vs Ejecutado por categoría (comparativo para gráfica de barras)
 */
function getMetaVsEjecutado($pdo, $anio) {
    return getMetas($pdo, $anio);
}

/**
 * Avance por proceso estratégico — puede filtrarse por mes, trimestre o año
 */
function getAvancePorProceso($pdo, $anio, $filtro = 'anio', $mes = null) {
    $stmt = $pdo->prepare("SELECT id, nombre, descripcion, meta_anual FROM procesos WHERE anio = :anio ORDER BY nombre ASC");
    $stmt->execute([':anio' => (int)$anio]);
    $procesos = $stmt->fetchAll();

    // Gastos agrupados por categoría vinculada al proceso
    $resultado = [];
    foreach ($procesos as $proc) {
        // Buscar la categoría vinculada (por nombre similar)
        $nombreProc = $proc['nombre'];
        $stmtCat = $pdo->prepare("
            SELECT COALESCE(SUM(g.monto), 0) as ejecutado
            FROM gastos g
            JOIN categorias c ON g.categoria_id = c.id
            WHERE c.nombre LIKE :nombre
              AND (
                  :filtro = 'anio' AND strftime('%Y', g.fecha) = :anio_str
                  OR :filtro2 = 'mes' AND strftime('%Y-%m', g.fecha) = :mes_str
                  OR :filtro3 = 'trimestre' AND strftime('%Y', g.fecha) = :anio_str2
                     AND CAST(strftime('%m', g.fecha) AS INTEGER) BETWEEN :mes_ini AND :mes_fin
              )
        ");
        $mesPad  = $mes ? str_pad($mes, 2, '0', STR_PAD_LEFT) : '01';
        $mesStr  = $anio . '-' . $mesPad;
        $trimMesIni = $mes ? (int)$mes : 1;
        $trimMesFin = $mes ? min((int)$mes + 2, 12) : 12;

        $stmtCat->execute([
            ':nombre'    => '%' . $nombreProc . '%',
            ':filtro'    => $filtro,
            ':anio_str'  => (string)$anio,
            ':filtro2'   => $filtro,
            ':mes_str'   => $mesStr,
            ':filtro3'   => $filtro,
            ':anio_str2' => (string)$anio,
            ':mes_ini'   => $trimMesIni,
            ':mes_fin'   => $trimMesFin,
        ]);
        $ejecutado = (float)($stmtCat->fetchColumn() ?? 0);
        $meta      = (float)$proc['meta_anual'];

        // Ajustar meta al período
        if ($filtro === 'mes') $metaPeriodo = $meta / 12;
        elseif ($filtro === 'trimestre') $metaPeriodo = $meta / 4;
        else $metaPeriodo = $meta;

        $pct = ($metaPeriodo > 0) ? min(round(($ejecutado / $metaPeriodo) * 100, 1), 999) : 0;

        $resultado[] = [
            'id'          => $proc['id'],
            'nombre'      => $proc['nombre'],
            'descripcion' => $proc['descripcion'],
            'meta'        => $metaPeriodo,
            'ejecutado'   => $ejecutado,
            'pct'         => $pct,
            'semaforo'    => $pct > 90 ? 'rojo' : ($pct > 70 ? 'amarillo' : 'verde'),
        ];
    }
    return $resultado;
}

/**
 * Desviación presupuestaria del período (esperado pro-rata vs ejecutado real)
 */
function getDesviacionPresupuestaria($pdo, $periodo) {
    $kpis = getDashboardKPIs($pdo, $periodo);
    $anio = substr($periodo, 0, 4);
    $mes  = (int)substr($periodo, 5, 2);
    $dia  = (int)date('d');
    $totalDias = cal_days_in_month(CAL_GREGORIAN, $mes, (int)$anio);

    // Gasto esperado pro-rata: presupuesto mensual × (días transcurridos / días del mes)
    $pct_tiempo = ($totalDias > 0) ? $dia / $totalDias : 1;
    $esperado = $kpis['presupuesto'] * $pct_tiempo;
    $ejecutado = $kpis['gasto_total'];
    $desviacion = $ejecutado - $esperado;
    $desviacion_pct = ($esperado > 0) ? round(($desviacion / $esperado) * 100, 1) : 0;

    $nivel = 'verde';
    if ($desviacion_pct > 15) $nivel = 'rojo';
    elseif ($desviacion_pct > 5) $nivel = 'amarillo';

    return [
        'esperado'       => round($esperado, 2),
        'ejecutado'      => $ejecutado,
        'desviacion'     => round($desviacion, 2),
        'desviacion_pct' => $desviacion_pct,
        'nivel'          => $nivel,
        'pct_tiempo'     => round($pct_tiempo * 100, 1),
    ];
}

/**
 * Histórico mensual de los últimos N meses o de un año completo
 */
function getHistoricoMensual($pdo, $anio, $meses = 12) {
    $resultado = [];
    for ($m = 1; $m <= $meses; $m++) {
        $mesStr = $anio . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);

        $stmtG = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM gastos WHERE strftime('%Y-%m', fecha) = :p");
        $stmtG->execute([':p' => $mesStr]);
        $gastos = (float)$stmtG->fetchColumn();

        $stmtI = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM ingresos WHERE strftime('%Y-%m', fecha) = :p");
        $stmtI->execute([':p' => $mesStr]);
        $ingresos = (float)$stmtI->fetchColumn();

        // Buscar presupuesto: anual prorrateado
        $stmtP = $pdo->prepare("SELECT total FROM presupuestos WHERE periodo = :anio");
        $stmtP->execute([':anio' => (string)$anio]);
        $presAnual = $stmtP->fetchColumn();
        $presupuesto = $presAnual !== false ? round((float)$presAnual / 12, 2) : 0;

        $balance = $ingresos - $gastos;
        $cumplimiento = ($presupuesto > 0) ? round(($gastos / $presupuesto) * 100, 1) : 0;

        $nombreMes = translateMonth(date('F', mktime(0,0,0,$m,1,(int)$anio)));
        $resultado[] = [
            'periodo'     => $mesStr,
            'mes'         => $nombreMes,
            'ingresos'    => $ingresos,
            'gastos'      => $gastos,
            'balance'     => $balance,
            'presupuesto' => $presupuesto,
            'cumplimiento'=> $cumplimiento,
        ];
    }
    return $resultado;
}

/**
 * Bitácora financiera filtrable por año, mes y/o categoría
 */
function getBitacoraFinanciera($pdo, $anio = null, $mes = null, $categoria_id = null, $limite = 100) {
    $where = [];
    $params = [];

    if ($anio) {
        $where[] = "strftime('%Y', g.fecha) = :anio";
        $params[':anio'] = (string)$anio;
    }
    if ($mes) {
        $where[] = "strftime('%m', g.fecha) = :mes";
        $params[':mes'] = str_pad($mes, 2, '0', STR_PAD_LEFT);
    }
    if ($categoria_id) {
        $where[] = "g.categoria_id = :cat_id";
        $params[':cat_id'] = (int)$categoria_id;
    }

    $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT g.id, g.concepto, g.monto, g.fecha,
               c.nombre as categoria, c.color, c.icono,
               'gasto' as tipo
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        $whereSQL
        ORDER BY g.fecha DESC, g.id DESC
        LIMIT :limite
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Proyección de gasto al fin de mes por categoría
 */
function getProyeccionPorCategoria($pdo, $periodo) {
    $anio = (int)substr($periodo, 0, 4);
    $mes  = (int)substr($periodo, 5, 2);
    $diaActual = (int)date('d');
    $totalDias = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $diasTrans = max($diaActual, 1);

    $stmt = $pdo->prepare("
        SELECT c.id, c.nombre, c.color, c.icono, COALESCE(SUM(g.monto), 0) as ejecutado,
               COALESCE(mc.valor_meta, 0) as meta_anual
        FROM categorias c
        LEFT JOIN gastos g ON c.id = g.categoria_id AND strftime('%Y-%m', g.fecha) = :periodo
        LEFT JOIN metas_categoria mc ON c.id = mc.categoria_id AND mc.anio = :anio
        GROUP BY c.id
        HAVING ejecutado > 0
        ORDER BY ejecutado DESC
    ");
    $stmt->execute([':periodo' => $periodo, ':anio' => $anio]);
    $rows = $stmt->fetchAll();

    return array_map(function($r) use ($pdo, $diasTrans, $totalDias, $anio, $mes) {
        $ejecutado  = (float)$r['ejecutado'];
        $promDiario = $ejecutado / $diasTrans;
        $proyeccion = round($promDiario * $totalDias, 2);
        $metaAnual  = (float)$r['meta_anual'];

        // pct_del_mes: % del mes actual ejecutado vs mes anterior (o vs proyección)
        // Try to get last month's spending for this category
        $mesPrevNum  = $mes - 1;
        $anioPrev    = $anio;
        if ($mesPrevNum < 1) { $mesPrevNum = 12; $anioPrev--; }
        $periodoPrev = $anioPrev . '-' . str_pad($mesPrevNum, 2, '0', STR_PAD_LEFT);
        $stmtPrev = $pdo->prepare("
            SELECT COALESCE(SUM(monto), 0) FROM gastos
            WHERE categoria_id = :cid AND strftime('%Y-%m', fecha) = :p
        ");
        $stmtPrev->execute([':cid' => $r['id'], ':p' => $periodoPrev]);
        $gastoPrevMes = (float)$stmtPrev->fetchColumn();

        if ($gastoPrevMes > 0) {
            $pctDelMes = min(round(($ejecutado / $gastoPrevMes) * 100, 1), 100);
        } elseif ($proyeccion > 0) {
            $pctDelMes = min(round(($ejecutado / $proyeccion) * 100, 1), 100);
        } else {
            $pctDelMes = 0.0;
        }

        // historial_3m: last 3 months' spending (oldest → newest), excluding current month
        $historial = [];
        for ($i = 3; $i >= 1; $i--) {
            $hMes  = $mes - $i;
            $hAnio = $anio;
            while ($hMes < 1) { $hMes += 12; $hAnio--; }
            $hPeriodo = $hAnio . '-' . str_pad($hMes, 2, '0', STR_PAD_LEFT);
            $stmtH = $pdo->prepare("
                SELECT COALESCE(SUM(monto), 0) FROM gastos
                WHERE categoria_id = :cid AND strftime('%Y-%m', fecha) = :p
            ");
            $stmtH->execute([':cid' => $r['id'], ':p' => $hPeriodo]);
            $historial[] = (float)$stmtH->fetchColumn();
        }

        return [
            'id'           => $r['id'],
            'nombre'       => $r['nombre'],
            'color'        => $r['color'],
            'icono'        => $r['icono'],
            'ejecutado'    => $ejecutado,
            'prom_diario'  => round($promDiario, 2),
            'proyeccion'   => $proyeccion,
            'pct_del_mes'  => $pctDelMes,
            'historial_3m' => $historial,
            'meta_anual'   => $metaAnual,
            'meta_mensual' => round($metaAnual / 12, 2)
        ];
    }, $rows);
}

/**
 * Top N gastos del período ordenados por monto descendente
 */
function getTopGastos($pdo, $limite = 10, $periodo = null) {
    $where  = $periodo ? "WHERE strftime('%Y-%m', g.fecha) = :periodo" : '';
    $params = $periodo ? [':periodo' => $periodo] : [];

    $stmt = $pdo->prepare("
        SELECT g.id, g.concepto, g.monto, g.fecha,
               c.nombre as categoria, c.color, c.icono
        FROM gastos g
        JOIN categorias c ON g.categoria_id = c.id
        $where
        ORDER BY g.monto DESC
        LIMIT :limite
    ");
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Resumen ejecutivo automático del período
 */
function getResumenEjecutivo($pdo, $periodo) {
    $kpis = getDashboardKPIs($pdo, $periodo);
    $anio = substr($periodo, 0, 4);

    // Mayor y menor gasto por categoría
    $stmtMax = $pdo->prepare("
        SELECT c.nombre, SUM(g.monto) as total
        FROM gastos g JOIN categorias c ON g.categoria_id = c.id
        WHERE strftime('%Y-%m', g.fecha) = :p
        GROUP BY c.id ORDER BY total DESC LIMIT 1
    ");
    $stmtMax->execute([':p' => $periodo]);
    $mayorGasto = $stmtMax->fetch();

    $stmtMin = $pdo->prepare("
        SELECT c.nombre, SUM(g.monto) as total
        FROM gastos g JOIN categorias c ON g.categoria_id = c.id
        WHERE strftime('%Y-%m', g.fecha) = :p
        GROUP BY c.id ORDER BY total ASC LIMIT 1
    ");
    $stmtMin->execute([':p' => $periodo]);
    $menorGasto = $stmtMin->fetch();

    $cumplGeneral = getCumplimientoGeneral($pdo, $anio);

    return [
        'periodo'           => $periodo,
        'pct_ejecutado'     => $kpis['porcentaje_usado'],
        'fondo_disponible'  => $kpis['dinero_restante'],
        'mayor_gasto_cat'   => $mayorGasto ? $mayorGasto['nombre'] : '—',
        'mayor_gasto_monto' => $mayorGasto ? (float)$mayorGasto['total'] : 0,
        'menor_gasto_cat'   => $menorGasto ? $menorGasto['nombre'] : '—',
        'menor_gasto_monto' => $menorGasto ? (float)$menorGasto['total'] : 0,
        'proyeccion_riesgo' => $kpis['riesgo_sobrecosto'],
        'proyeccion_monto'  => $kpis['proyeccion_fin_mes'],
        'cumplimiento_gral' => $cumplGeneral,
        'semaforo'          => $kpis['semaforo'],
    ];
}

/**
 * KPI de cumplimiento estratégico anual.
 * Prioridad 1: Usa metas de categorías si están definidas.
 * Prioridad 2: Usa el presupuesto anual prorrateado al mes actual si no hay metas de categoría.
 * La lógica es: ¿cuánto del presupuesto esperado para esta fecha ya se ejecutó?
 * Un % bajo = buen control presupuestal (gastando menos de lo esperado).
 */
function getCumplimientoGeneral($pdo, $anio) {
    // Gasto real acumulado en el año
    $stmtGasto = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM gastos WHERE strftime('%Y', fecha) = :anio");
    $stmtGasto->execute([':anio' => (string)$anio]);
    $totalGasto = (float)$stmtGasto->fetchColumn();

    // Intentar metas de categorías
    $stmtMeta = $pdo->prepare("SELECT COALESCE(SUM(valor_meta),0) FROM metas_categoria WHERE anio = :anio");
    $stmtMeta->execute([':anio' => (int)$anio]);
    $totalMetas = (float)$stmtMeta->fetchColumn();

    if ($totalMetas > 0) {
        // Tiene metas por categoría → comparar gasto vs meta total
        $pct   = min(round(($totalGasto / $totalMetas) * 100, 1), 999);
        $nivel = $pct > 90 ? 'rojo' : ($pct > 70 ? 'amarillo' : 'verde');
        return [
            'pct'       => $pct,
            'ejecutado' => $totalGasto,
            'meta'      => $totalMetas,
            'nivel'     => $nivel,
            'fuente'    => 'metas_categoria'
        ];
    }

    // Sin metas de categoría → usar presupuesto anual como referencia
    $stmtPres = $pdo->prepare("SELECT COALESCE(total,0) FROM presupuestos WHERE periodo = :anio");
    $stmtPres->execute([':anio' => (string)$anio]);
    $presAnual = (float)$stmtPres->fetchColumn();

    if ($presAnual <= 0) {
        // Sin presupuesto ni metas: no hay referencia
        return ['pct' => 0, 'ejecutado' => $totalGasto, 'meta' => 0, 'nivel' => 'sin_meta', 'fuente' => 'sin_referencia'];
    }

    // Calcular qué fracción del año ha transcurrido hasta el mes actual
    $anioActual = (int)date('Y');
    $mesActual  = (int)date('m');
    $selAnio    = (int)$anio;

    // Cuántos meses del año seleccionado ya pasaron
    if ($selAnio < $anioActual) {
        $mesesTranscurridos = 12;
    } elseif ($selAnio === $anioActual) {
        $mesesTranscurridos = $mesActual;
    } else {
        $mesesTranscurridos = 0;
    }

    // Presupuesto esperado proporcional a los meses transcurridos
    $presEsperado = ($mesesTranscurridos / 12.0) * $presAnual;

    if ($presEsperado <= 0) {
        return ['pct' => 0, 'ejecutado' => $totalGasto, 'meta' => $presAnual, 'nivel' => 'verde', 'fuente' => 'presupuesto'];
    }

    // Ejecución presupuestal: qué % del presupuesto proporcional ya se gastó
    $pct   = min(round(($totalGasto / $presEsperado) * 100, 1), 200);
    // Verde = gastando ≤100% de lo esperado; amarillo = 100-120%; rojo = >120%
    $nivel = $pct > 120 ? 'rojo' : ($pct > 100 ? 'amarillo' : 'verde');

    return [
        'pct'       => $pct,
        'ejecutado' => $totalGasto,
        'meta'      => $presEsperado,   // presupuesto proporcional al período
        'presAnual' => $presAnual,
        'nivel'     => $nivel,
        'fuente'    => 'presupuesto'
    ];
}

/**
 * Obtener alertas activas (no leídas) del período
 */
function getAlertasActivas($pdo, $periodo) {
    // Primero regenerar alertas con los datos actuales
    generarAlertas($pdo, $periodo);

    $stmt = $pdo->prepare("
        SELECT id, tipo, mensaje, nivel, periodo, created_at
        FROM alertas
        WHERE periodo = :periodo AND leida = 0
        ORDER BY
            CASE nivel WHEN 'danger' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END,
            created_at DESC
    ");
    $stmt->execute([':periodo' => $periodo]);
    return $stmt->fetchAll();
}

/**
 * Generar alertas automáticas según reglas de negocio
 */
function generarAlertas($pdo, $periodo) {
    $kpis = getDashboardKPIs($pdo, $periodo);
    $anio = substr($periodo, 0, 4);

    $alertasNuevas = [];

    // Regla 1: Ejecución > 80%
    if ($kpis['porcentaje_usado'] >= 95) {
        $alertasNuevas[] = [
            'tipo'    => 'presupuesto',
            'mensaje' => sprintf('⚠️ Ejecución crítica: %.1f%% del presupuesto utilizado. Quedan $%s disponibles.',
                $kpis['porcentaje_usado'], number_format($kpis['dinero_restante'], 0, '.', ',')),
            'nivel'   => 'danger',
        ];
    } elseif ($kpis['porcentaje_usado'] >= 80) {
        $alertasNuevas[] = [
            'tipo'    => 'presupuesto',
            'mensaje' => sprintf('📊 Ejecución alta: %.1f%% del presupuesto utilizado.',
                $kpis['porcentaje_usado']),
            'nivel'   => 'warning',
        ];
    }

    // Regla 2: Proyección supera el presupuesto
    if ($kpis['riesgo_sobrecosto']) {
        $alertasNuevas[] = [
            'tipo'    => 'proyeccion',
            'mensaje' => sprintf('🔴 Riesgo financiero: la proyección al cierre es $%s, superando el presupuesto de $%s.',
                number_format($kpis['proyeccion_fin_mes'], 0, '.', ','),
                number_format($kpis['presupuesto'], 0, '.', ',')),
            'nivel'   => 'danger',
        ];
    }

    // Regla 3: Cumplimiento de metas > 90%
    $cumpl = getCumplimientoGeneral($pdo, $anio);
    if ($cumpl['pct'] >= 90 && $cumpl['meta'] > 0) {
        $alertasNuevas[] = [
            'tipo'    => 'meta',
            'mensaje' => sprintf('🎯 Metas al %.1f%% de cumplimiento — riesgo de sobrepasar los objetivos anuales.',
                $cumpl['pct']),
            'nivel'   => 'warning',
        ];
    }

    // Insertar solo alertas del mismo tipo que no existan ya sin leer
    foreach ($alertasNuevas as $a) {
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) FROM alertas
            WHERE tipo = :tipo AND periodo = :periodo AND leida = 0
        ");
        $stmtCheck->execute([':tipo' => $a['tipo'], ':periodo' => $periodo]);
        if ($stmtCheck->fetchColumn() == 0) {
            $ins = $pdo->prepare("
                INSERT INTO alertas (tipo, mensaje, nivel, periodo)
                VALUES (:tipo, :mensaje, :nivel, :periodo)
            ");
            $ins->execute([
                ':tipo'    => $a['tipo'],
                ':mensaje' => $a['mensaje'],
                ':nivel'   => $a['nivel'],
                ':periodo' => $periodo,
            ]);
        }
    }
}

/**
 * Balance anual: ingresos vs gastos por mes para un año completo
 */
function getBalanceAnual($pdo, $periodo, $vista) {
    $nombresMeses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    $anio = substr($periodo, 0, 4);
    $mes = substr($periodo, 5, 2);

    // Totales del periodo solicitado (anio o mes)
    $filtroAnio = "strftime('%Y', fecha) = :anio";
    $filtroMes = "strftime('%Y-%m', fecha) = :periodo";
    $filtro = ($vista === 'anio') ? $filtroAnio : $filtroMes;
    $param = ($vista === 'anio') ? [':anio' => (string)$anio] : [':periodo' => (string)$periodo];

    $stmtI = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE $filtro");
    $stmtI->execute($param);
    $ingresosTotal = (float)$stmtI->fetchColumn();

    $stmtG = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE $filtro");
    $stmtG->execute($param);
    $gastosTotal = (float)$stmtG->fetchColumn();

    $meses = [];
    
    if ($vista === 'anio') {
        // Desglose mensual
        for ($m = 1; $m <= 12; $m++) {
            $mesStr = (string)$anio . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);

            $stmtMI = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE strftime('%Y-%m', fecha) = :p");
            $stmtMI->execute([':p' => $mesStr]);
            $ingresosMes = (float)$stmtMI->fetchColumn();

            $stmtMG = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE strftime('%Y-%m', fecha) = :p");
            $stmtMG->execute([':p' => $mesStr]);
            $gastosMes = (float)$stmtMG->fetchColumn();

            $meses[] = [
                'label'    => $nombresMeses[$m - 1],
                'ingresos' => $ingresosMes,
                'gastos'   => $gastosMes,
            ];
        }
    } else {
        // Desglose diario
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, (int)$mes, (int)$anio);
        for ($d = 1; $d <= $diasEnMes; $d++) {
            $diaStr = $periodo . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);

            $stmtDI = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE fecha = :p");
            $stmtDI->execute([':p' => $diaStr]);
            $ingresosDia = (float)$stmtDI->fetchColumn();

            $stmtDG = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE fecha = :p");
            $stmtDG->execute([':p' => $diaStr]);
            $gastosDia = (float)$stmtDG->fetchColumn();

            $meses[] = [
                'label'    => "Día $d",
                'ingresos' => $ingresosDia,
                'gastos'   => $gastosDia,
            ];
        }
    }

    return [
        'ingresos_total' => $ingresosTotal,
        'gastos_total'   => $gastosTotal,
        'balance'        => $ingresosTotal - $gastosTotal,
        'meses'          => $meses,
    ];
}

/**
 * Compara el mes actual con el mes anterior en gastos, ingresos y balance
 */
function getComparativoMesAnterior($pdo, $periodo) {
    // Calculate previous period
    $parts = explode('-', $periodo);
    $anio  = (int)$parts[0];
    $mes   = (int)$parts[1];
    $mes--;
    if ($mes < 1) { $mes = 12; $anio--; }
    $periodoPrev = sprintf('%04d-%02d', $anio, $mes);

    $getData = function($p) use ($pdo) {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE((SELECT SUM(monto) FROM gastos  WHERE strftime('%Y-%m', fecha) = :p1), 0) as gastos,
                COALESCE((SELECT SUM(monto) FROM ingresos WHERE strftime('%Y-%m', fecha) = :p2), 0) as ingresos
        ");
        $stmt->execute([':p1' => $p, ':p2' => $p]);
        $r = $stmt->fetch();
        return [
            'gastos'   => (float)$r['gastos'],
            'ingresos' => (float)$r['ingresos'],
            'balance'  => (float)$r['ingresos'] - (float)$r['gastos'],
        ];
    };

    $actual   = $getData($periodo);
    $anterior = $getData($periodoPrev);

    $diff_gastos   = $actual['gastos']   - $anterior['gastos'];
    $diff_ingresos = $actual['ingresos'] - $anterior['ingresos'];
    $diff_balance  = $actual['balance']  - $anterior['balance'];

    $pct_gastos = $anterior['gastos'] > 0
        ? round(($diff_gastos / $anterior['gastos']) * 100, 1) : 0;
    $pct_ingresos = $anterior['ingresos'] > 0
        ? round(($diff_ingresos / $anterior['ingresos']) * 100, 1) : 0;

    return [
        'periodo_actual'   => $periodo,
        'periodo_anterior' => $periodoPrev,
        'actual'           => $actual,
        'anterior'         => $anterior,
        'diff_gastos'      => $diff_gastos,
        'diff_ingresos'    => $diff_ingresos,
        'diff_balance'     => $diff_balance,
        'pct_gastos'       => $pct_gastos,
        'pct_ingresos'     => $pct_ingresos,
        'alerta_gastos'    => $pct_gastos > 10,   // >10% more spending = alert
    ];
}
