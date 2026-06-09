<?php
/**
 * Funciones dedicadas al Dashboard MCI (Excel Replica)
 */

// 1. Obtener Matriz de Bitácora (Meses x Categorías)
function getMatrizMCI($pdo, $anio) {
    // Primero, obtener todas las categorías
    $stmtCats = $pdo->query("SELECT id, nombre FROM categorias ORDER BY id");
    $categorias = $stmtCats->fetchAll();
    
    $mesesNombres = [
        1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 
        5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 
        9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
    ];
    
    // Obtener gastos del año agrupados por mes y categoria
    $stmt = $pdo->prepare("
        SELECT 
            CAST(strftime('%m', fecha) AS INTEGER) as mes,
            categoria_id,
            SUM(monto) as total
        FROM gastos 
        WHERE strftime('%Y', fecha) = :anio
        GROUP BY mes, categoria_id
    ");
    $stmt->execute([':anio' => $anio]);
    $gastos = $stmt->fetchAll();
    
    // Organizar en matriz
    $matriz = [];
    for($m=1; $m<=12; $m++) {
        $row = ['mes' => $mesesNombres[$m], 'mes_num' => $m, 'categorias' => [], 'total_mes' => 0];
        foreach($categorias as $cat) {
            $row['categorias'][$cat['id']] = 0;
        }
        $matriz[$m] = $row;
    }
    
    foreach($gastos as $g) {
        $m = $g['mes'];
        $cid = $g['categoria_id'];
        $monto = (float)$g['total'];
        
        if (isset($matriz[$m])) {
            $matriz[$m]['categorias'][$cid] = $monto;
            $matriz[$m]['total_mes'] += $monto;
        }
    }
    
    // Formatear salida para JSON (eliminar llaves de mes)
    return [
        'categorias' => $categorias,
        'filas' => array_values($matriz)
    ];
}

// 2. Obtener Tabla de Desviación por Categoría (Ejecutado vs Estimado vs MCI)
function getDesviacionCategoriasMCI($pdo, $anio, $mes_limite = null) {
    if (!$mes_limite) $mes_limite = (int)date('m');
    
    // Para prorrateo: asumimos que la meta se divide en 12 meses.
    // El "estimado a mayo" sería = Meta_Anual * (mes_limite / 12)
    // Opcionalmente proporcional por días. Usaremos (mes_limite/12) para que empate con el excel.
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.nombre, c.color,
            COALESCE(mc.valor_meta, 0) as meta_anual,
            COALESCE(
                (SELECT SUM(monto) FROM gastos g 
                 WHERE g.categoria_id = c.id 
                 AND strftime('%Y', g.fecha) = :anio
                 AND CAST(strftime('%m', g.fecha) AS INTEGER) <= :mes_limite
                ), 0
            ) as ejecutado
        FROM categorias c
        LEFT JOIN metas_categoria mc ON c.id = mc.categoria_id AND mc.anio = :anio
        ORDER BY c.id
    ");
    $stmt->execute([':anio' => $anio, ':mes_limite' => $mes_limite]);
    $data = $stmt->fetchAll();
    
    $resultado = [];
    $total_meta = 0;
    $total_estimado = 0;
    $total_ejecutado = 0;
    
    foreach($data as $row) {
        $meta = (float)$row['meta_anual'];
        $ejecutado = (float)$row['ejecutado'];
        
        $estimado = $meta * ($mes_limite / 12.0);
        $diferencia = $estimado - $ejecutado; 
        
        // Semáforo: Verde si gastó menos o igual a lo estimado
        $estado = ($ejecutado <= $estimado) ? 'verde' : 'rojo';
        
        $resultado[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'color' => $row['color'],
            'mci_anual' => $meta,
            'estimado' => $estimado,
            'ejecutado' => $ejecutado,
            'diferencia' => $diferencia,
            'estado' => $estado
        ];
        
        $total_meta += $meta;
        $total_estimado += $estimado;
        $total_ejecutado += $ejecutado;
    }
    
    return [
        'detalles' => $resultado,
        'totales' => [
            'mci_anual' => $total_meta,
            'estimado' => $total_estimado,
            'ejecutado' => $total_ejecutado,
            'diferencia' => $total_estimado - $total_ejecutado,
            'estado' => ($total_ejecutado <= $total_estimado) ? 'verde' : 'rojo'
        ],
        'mes_analizado' => $mes_limite
    ];
}
