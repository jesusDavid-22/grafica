<?php
/**
 * Router API JSON para el Dashboard Financiero
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mci_functions.php';

// Capturar el cuerpo de la petición si es JSON
$inputData = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $inputData = json_decode($rawInput, true) ?? [];
}

// Combinar variables GET, POST y JSON INPUT
$request = array_merge($_GET, $_POST, $inputData);
$action = $request['action'] ?? '';

// Estructura de respuesta por defecto
$response = [
    'success' => false,
    'message' => 'Acción no definida o inválida'
];

try {
    switch ($action) {
        
        case 'get_dashboard_data':
            $periodo = $request['periodo'] ?? date('Y-m');
            $vista   = $request['vista'] ?? 'mes';
            $anio    = substr($periodo, 0, 4);
            
            $response = [
                'success'              => true,
                'kpis'                 => getDashboardKPIs($pdo, $periodo, $vista),
                'gastos_categoria'     => getGastosPorCategoria($pdo, $periodo, $vista),
                'gastos_mes'           => getGastosPorMes($pdo),
                'tendencia'            => getTendenciaTemporal($pdo, $periodo),
                'tendencia_stacked'    => getTendenciaStackedBar($pdo, $periodo),
                'recientes'            => getTransaccionesRecientes($pdo, 200, $periodo),
                'resumen_ejecutivo'    => getResumenEjecutivo($pdo, $periodo),
                'alertas'              => getAlertasActivas($pdo, $periodo),
                'top_gastos'           => getTopGastos($pdo, 5, $periodo),
                'balance_anual'        => getBalanceAnual($pdo, $periodo, $vista),
                'comparativo_mes'      => getComparativoMesAnterior($pdo, $periodo),
            ];
            break;
            
        case 'get_detalle_categoria':
            $periodo = $request['periodo'] ?? date('Y-m');
            $vista   = $request['vista'] ?? 'mes';
            $categoriaNombre = $request['categoria'] ?? '';
            
            if (empty($categoriaNombre)) {
                throw new Exception("Nombre de categoría requerido.");
            }
            
            $response = [
                'success' => true,
                'detalle' => getGastosDetallePorCategoria($pdo, $periodo, $vista, $categoriaNombre)
            ];
            break;

        case 'get_tendencia_detalle_categoria':
            $periodo = $request['periodo'] ?? date('Y-m');
            $categoriaNombre = $request['categoria'] ?? '';
            
            if (empty($categoriaNombre)) {
                throw new Exception("Nombre de categoría requerido.");
            }
            
            $response = [
                'success' => true,
                'tendencia_stacked' => getTendenciaDetalleCategoria($pdo, $periodo, $categoriaNombre)
            ];
            break;

        case 'get_mci_dashboard':
            $periodo = $request['periodo'] ?? date('Y-m');
            $vista   = $request['vista'] ?? 'mes';
            $modo    = $request['modo'] ?? 'acumulado';
            $anio = substr($periodo, 0, 4);
            $mes = (int)substr($periodo, 5, 2);
            
            $response = [
                'success' => true,
                'kpis' => getDashboardKPIs($pdo, $periodo, $vista),
                'mci_desviacion' => getDesviacionCategoriasMCI($pdo, $periodo, $vista, $modo),
                'mci_matriz' => getMatrizMCI($pdo, $anio)
            ];
            break;

        // ── METAS ──────────────────────────────────────────────────────────
        case 'get_metas':
            $anio = $request['anio'] ?? date('Y');
            $response = [
                'success' => true,
                'metas'   => getMetas($pdo, $anio),
            ];
            break;

        case 'set_meta_ahorro_global':
            $anio  = trim($request['anio'] ?? date('Y'));
            // Aceptamos 'monto' (cantidad de dinero a ahorrar) como campo principal.
            // Internamente lo guardamos como porcentaje calculado si hay presupuesto,
            // pero también guardamos el monto absoluto en meta_ahorro_monto.
            $monto = (float)($request['monto'] ?? 0.0);
            
            if ($monto < 0) {
                throw new Exception("El monto de ahorro no puede ser negativo.");
            }
            
            // Obtener el presupuesto anual para calcular el porcentaje equivalente
            $stmtPres = $pdo->prepare("SELECT total FROM presupuestos WHERE periodo = :anio");
            $stmtPres->execute([':anio' => $anio]);
            $rowPres = $stmtPres->fetch();
            $presAnual = $rowPres ? (float)$rowPres['total'] : 0.0;
            $pct = ($presAnual > 0) ? min(($monto / $presAnual) * 100, 100) : 0.0;
            
            // Upsert: Actualizar monto y porcentaje equivalente
            // Primero verificamos si la columna meta_ahorro_monto existe
            try {
                $pdo->exec("ALTER TABLE presupuestos ADD COLUMN meta_ahorro_monto REAL DEFAULT 0");
            } catch (Exception $e) { /* ya existe */ }
            
            $upsert = $pdo->prepare("
                INSERT INTO presupuestos (total, periodo, meta_ahorro_pct, meta_ahorro_monto) 
                VALUES (0, :anio, :pct, :monto)
                ON CONFLICT(periodo) DO UPDATE 
                SET meta_ahorro_pct = excluded.meta_ahorro_pct,
                    meta_ahorro_monto = excluded.meta_ahorro_monto
            ");
            $upsert->execute([':anio' => $anio, ':pct' => $pct, ':monto' => $monto]);
            $response = ['success' => true, 'message' => 'Objetivo de ahorro actualizado.', 'pct_equivalente' => round($pct, 1)];
            break;

        // ── PROCESOS ───────────────────────────────────────────────────────
        case 'get_avance_procesos':
            $anio   = $request['anio'] ?? date('Y');
            $filtro = $request['filtro'] ?? 'anio';
            $mes    = $request['mes'] ?? null;
            $response = [
                'success'  => true,
                'procesos' => getAvancePorProceso($pdo, $anio, $filtro, $mes),
            ];
            break;

        case 'add_proceso':
            $nombre      = trim($request['nombre'] ?? '');
            $descripcion = trim($request['descripcion'] ?? '');
            $meta_anual  = (float)($request['meta_anual'] ?? 0);
            $anio        = (int)($request['anio'] ?? date('Y'));
            if (empty($nombre)) throw new Exception("El nombre del proceso es obligatorio.");
            $ins = $pdo->prepare("INSERT INTO procesos (nombre, descripcion, meta_anual, anio) VALUES (:n, :d, :m, :a)");
            $ins->execute([':n' => $nombre, ':d' => $descripcion, ':m' => $meta_anual, ':a' => $anio]);
            $response = ['success' => true, 'message' => 'Proceso creado.', 'id' => $pdo->lastInsertId()];
            break;

        case 'set_proceso_meta':
            $id         = (int)($request['id'] ?? 0);
            $meta_anual = (float)($request['meta_anual'] ?? 0);
            if ($id <= 0) throw new Exception("ID del proceso inválido.");
            $upd = $pdo->prepare("UPDATE procesos SET meta_anual = :m WHERE id = :id");
            $upd->execute([':m' => $meta_anual, ':id' => $id]);
            $response = ['success' => true, 'message' => 'Meta del proceso actualizada.'];
            break;

        // ── ANALÍTICOS ────────────────────────────────────────────────────
        case 'get_meta_vs_ejecutado':
            $anio = $request['anio'] ?? date('Y');
            $response = [
                'success' => true,
                'data'    => getMetaVsEjecutado($pdo, $anio),
            ];
            break;

        case 'get_desviacion':
            $periodo = $request['periodo'] ?? date('Y-m');
            $response = [
                'success'   => true,
                'desviacion'=> getDesviacionPresupuestaria($pdo, $periodo),
            ];
            break;

        case 'get_historico_mensual':
            $anio  = $request['anio'] ?? date('Y');
            $meses = (int)($request['meses'] ?? 12);
            $response = [
                'success'  => true,
                'historico'=> getHistoricoMensual($pdo, $anio, $meses),
            ];
            break;

        case 'get_bitacora':
            $anio         = $request['anio'] ?? null;
            $mes          = $request['mes'] ?? null;
            $categoria_id = isset($request['categoria_id']) ? (int)$request['categoria_id'] : null;
            $limite       = (int)($request['limite'] ?? 100);
            $response = [
                'success'  => true,
                'bitacora' => getBitacoraFinanciera($pdo, $anio, $mes, $categoria_id, $limite),
            ];
            break;

        case 'get_proyeccion_cat':
            $periodo = $request['periodo'] ?? date('Y-m');
            $response = [
                'success'     => true,
                'proyecciones'=> getProyeccionPorCategoria($pdo, $periodo),
            ];
            break;

        case 'get_top_gastos':
            $limite  = (int)($request['limite'] ?? 10);
            $periodo = $request['periodo'] ?? null;
            $response = [
                'success'    => true,
                'top_gastos' => getTopGastos($pdo, $limite, $periodo),
            ];
            break;

        case 'get_resumen_ejecutivo':
            $periodo = $request['periodo'] ?? date('Y-m');
            $response = [
                'success' => true,
                'resumen' => getResumenEjecutivo($pdo, $periodo),
            ];
            break;

        case 'get_cumplimiento_general':
            $anio = $request['anio'] ?? date('Y');
            $response = [
                'success'      => true,
                'cumplimiento' => getCumplimientoGeneral($pdo, $anio),
            ];
            break;

        // ── ALERTAS ───────────────────────────────────────────────────────
        case 'get_alertas':
            $periodo = $request['periodo'] ?? date('Y-m');
            $response = [
                'success' => true,
                'alertas' => getAlertasActivas($pdo, $periodo),
            ];
            break;

        case 'marcar_alerta_leida':
            $id = (int)($request['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID de alerta inválido.");
            $upd = $pdo->prepare("UPDATE alertas SET leida = 1 WHERE id = :id");
            $upd->execute([':id' => $id]);
            $response = ['success' => true, 'message' => 'Alerta marcada como leída.'];
            break;

        case 'marcar_todas_leidas':
            $periodo = $request['periodo'] ?? date('Y-m');
            $upd = $pdo->prepare("UPDATE alertas SET leida = 1 WHERE periodo = :p");
            $upd->execute([':p' => $periodo]);
            $response = ['success' => true, 'message' => 'Todas las alertas marcadas como leídas.'];
            break;

        case 'get_categorias':
            $anio_cat = (int)(date('Y'));
            $stmt = $pdo->prepare("
                SELECT c.id, c.nombre, c.color, c.icono,
                       COALESCE(mc.valor_meta, 0) AS meta_cat
                FROM categorias c
                LEFT JOIN metas_categoria mc
                       ON mc.categoria_id = c.id AND mc.anio = :anio
                ORDER BY c.nombre ASC
            ");
            $stmt->execute([':anio' => $anio_cat]);
            $response = [
                'success'   => true,
                'categorias'=> $stmt->fetchAll()
            ];
            break;

        case 'add_gasto':
            $concepto = trim($request['concepto'] ?? '');
            $monto = (float)($request['monto'] ?? 0.0);
            $fecha = trim($request['fecha'] ?? '');
            $categoria_id = (int)($request['categoria_id'] ?? 0);
            
            if (empty($concepto) || $monto <= 0 || empty($fecha) || $categoria_id <= 0) {
                throw new Exception("Todos los campos del gasto son obligatorios y el monto debe ser positivo.");
            }
            
            // Validar existencia de categoría
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE id = ?");
            $stmt->execute([$categoria_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("La categoría seleccionada no es válida.");
            }
            
            $insert = $pdo->prepare("
                INSERT INTO gastos (concepto, monto, fecha, categoria_id) 
                VALUES (:concepto, :monto, :fecha, :categoria_id)
            ");
            $insert->execute([
                ':concepto' => $concepto,
                ':monto' => $monto,
                ':fecha' => $fecha,
                ':categoria_id' => $categoria_id
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Gasto registrado correctamente.',
                'id' => $pdo->lastInsertId()
            ];
            break;
            
        case 'add_ingreso':
            $concepto = trim($request['concepto'] ?? '');
            $monto = (float)($request['monto'] ?? 0.0);
            $fecha = trim($request['fecha'] ?? '');
            
            if (empty($concepto) || $monto <= 0 || empty($fecha)) {
                throw new Exception("Todos los campos del ingreso son obligatorios y el monto debe ser positivo.");
            }
            
            $insert = $pdo->prepare("
                INSERT INTO ingresos (concepto, monto, fecha) 
                VALUES (:concepto, :monto, :fecha)
            ");
            $insert->execute([
                ':concepto' => $concepto,
                ':monto' => $monto,
                ':fecha' => $fecha
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Ingreso registrado correctamente.',
                'id' => $pdo->lastInsertId()
            ];
            break;
            
        case 'set_presupuesto':
            $total = (float)($request['total'] ?? 0.0);
            $periodo = trim($request['periodo'] ?? '');
            
            if ($total < 0 || empty($periodo) || (!preg_match('/^\d{4}$/', $periodo) && !preg_match('/^\d{4}-\d{2}$/', $periodo))) {
                throw new Exception("El presupuesto debe ser un número positivo y el período debe ser un año (YYYY) o un mes (YYYY-MM).");
            }
            
            // Upsert: Insertar o actualizar presupuesto de forma atómica en SQLite 3.24+
            $upsert = $pdo->prepare("
                INSERT INTO presupuestos (total, periodo) 
                VALUES (:total, :periodo)
                ON CONFLICT(periodo) 
                DO UPDATE SET total = excluded.total
            ");
            $upsert->execute([
                ':total' => $total,
                ':periodo' => $periodo
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Presupuesto establecido correctamente.'
            ];
            break;

        case 'add_categoria':
            $nombre = trim($request['nombre'] ?? '');
            $color = trim($request['color'] ?? '#6B7280');
            $icono = trim($request['icono'] ?? 'help-circle');
            
            if (empty($nombre)) {
                throw new Exception("El nombre de la categoría es obligatorio.");
            }
            
            $insert = $pdo->prepare("
                INSERT INTO categorias (nombre, color, icono) 
                VALUES (:nombre, :color, :icono)
            ");
            $insert->execute([
                ':nombre' => $nombre,
                ':color' => $color,
                ':icono' => $icono
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Categoría creada con éxito.',
                'id' => $pdo->lastInsertId()
            ];
            break;

        case 'edit_gasto':
            $id = (int)($request['id'] ?? 0);
            $concepto = trim($request['concepto'] ?? '');
            $monto = (float)($request['monto'] ?? 0.0);
            $fecha = trim($request['fecha'] ?? '');
            $categoria_id = (int)($request['categoria_id'] ?? 0);
            
            if ($id <= 0 || empty($concepto) || $monto <= 0 || empty($fecha) || $categoria_id <= 0) {
                throw new Exception("Todos los campos del gasto son obligatorios y el monto debe ser positivo.");
            }
            
            // Validar existencia de categoría
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE id = ?");
            $stmt->execute([$categoria_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("La categoría seleccionada no es válida.");
            }
            
            $update = $pdo->prepare("
                UPDATE gastos 
                SET concepto = :concepto, monto = :monto, fecha = :fecha, categoria_id = :categoria_id 
                WHERE id = :id
            ");
            $update->execute([
                ':concepto' => $concepto,
                ':monto' => $monto,
                ':fecha' => $fecha,
                ':categoria_id' => $categoria_id,
                ':id' => $id
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Gasto actualizado correctamente.'
            ];
            break;

        case 'delete_gasto':
            $id = (int)($request['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception("ID de gasto inválido.");
            }
            
            $delete = $pdo->prepare("DELETE FROM gastos WHERE id = :id");
            $delete->execute([':id' => $id]);
            
            if ($delete->rowCount() == 0) {
                throw new Exception("No se encontró el gasto para eliminar.");
            }
            
            $response = [
                'success' => true,
                'message' => 'Gasto eliminado correctamente.'
            ];
            break;

        case 'edit_ingreso':
            $id = (int)($request['id'] ?? 0);
            $concepto = trim($request['concepto'] ?? '');
            $monto = (float)($request['monto'] ?? 0.0);
            $fecha = trim($request['fecha'] ?? '');
            
            if ($id <= 0 || empty($concepto) || $monto <= 0 || empty($fecha)) {
                throw new Exception("Todos los campos del ingreso son obligatorios y el monto debe ser positivo.");
            }
            
            $update = $pdo->prepare("
                UPDATE ingresos 
                SET concepto = :concepto, monto = :monto, fecha = :fecha 
                WHERE id = :id
            ");
            $update->execute([
                ':concepto' => $concepto,
                ':monto' => $monto,
                ':fecha' => $fecha,
                ':id' => $id
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Ingreso actualizado correctamente.'
            ];
            break;

        case 'delete_ingreso':
            $id = (int)($request['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception("ID de ingreso inválido.");
            }
            
            $delete = $pdo->prepare("DELETE FROM ingresos WHERE id = :id");
            $delete->execute([':id' => $id]);
            
            if ($delete->rowCount() == 0) {
                throw new Exception("No se encontró el ingreso para eliminar.");
            }
            
            $response = [
                'success' => true,
                'message' => 'Ingreso eliminado correctamente.'
            ];
            break;

        case 'edit_categoria':
            $id = (int)($request['id'] ?? 0);
            $nombre = trim($request['nombre'] ?? '');
            $color = trim($request['color'] ?? '');
            $icono = trim($request['icono'] ?? '');
            
            if ($id <= 0 || empty($nombre) || empty($color) || empty($icono)) {
                throw new Exception("Todos los campos son obligatorios para editar la categoría.");
            }
            
            $update = $pdo->prepare("
                UPDATE categorias 
                SET nombre = :nombre, color = :color, icono = :icono 
                WHERE id = :id
            ");
            $update->execute([
                ':nombre' => $nombre,
                ':color' => $color,
                ':icono' => $icono,
                ':id' => $id
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Categoría actualizada con éxito.'
            ];
            break;
            
        case 'editar_transaccion':
            $id     = (int)($request['id'] ?? 0);
            $tipo   = $request['tipo'] ?? 'gasto';  // 'gasto' or 'ingreso'
            $concepto = trim($request['concepto'] ?? '');
            $monto    = (float)($request['monto'] ?? 0);
            $fecha    = $request['fecha'] ?? '';
            $categoria_id = isset($request['categoria_id']) ? (int)$request['categoria_id'] : null;
            if (!$id || !$concepto || !$monto || !$fecha) {
                $response = ['success'=>false,'message'=>'Datos incompletos'];
                break;
            }
            if ($tipo === 'ingreso') {
                $stmt = $pdo->prepare('UPDATE ingresos SET concepto=:c, monto=:m, fecha=:f WHERE id=:id');
                $stmt->execute([':c'=>$concepto,':m'=>$monto,':f'=>$fecha,':id'=>$id]);
            } else {
                $stmt = $pdo->prepare('UPDATE gastos SET concepto=:c, monto=:m, fecha=:f, categoria_id=:cat WHERE id=:id');
                $stmt->execute([':c'=>$concepto,':m'=>$monto,':f'=>$fecha,':cat'=>$categoria_id,':id'=>$id]);
            }
            $response = ['success'=>true,'message'=>'Transacción actualizada correctamente.'];
            break;

        case 'eliminar_transaccion':
            $id   = (int)($request['id'] ?? 0);
            $tipo = $request['tipo'] ?? 'gasto';
            if (!$id) { 
                $response = ['success'=>false,'message'=>'ID requerido']; 
                break; 
            }
            $table = $tipo === 'ingreso' ? 'ingresos' : 'gastos';
            $pdo->prepare("DELETE FROM $table WHERE id=:id")->execute([':id'=>$id]);
            $response = ['success'=>true,'message'=>'Transacción eliminada correctamente.'];
            break;

        case 'set_meta_categoria':
            $categoria_id = (int)($request['categoria_id'] ?? 0);
            $anio_m       = (int)($request['anio'] ?? date('Y'));
            $valor_meta   = (float)($request['valor_meta'] ?? 0);
            if (!$categoria_id) { echo json_encode(['success'=>false,'message'=>'Categoria requerida']); exit; }
            $stmt = $pdo->prepare('INSERT INTO metas_categoria (categoria_id, anio, valor_meta) VALUES (:c,:a,:v) ON CONFLICT(categoria_id, anio) DO UPDATE SET valor_meta=:v2');
            $stmt->execute([':c'=>$categoria_id,':a'=>$anio_m,':v'=>$valor_meta,':v2'=>$valor_meta]);
            echo json_encode(['success'=>true,'message'=>'Meta de categoria guardada']);
            break;

        case 'get_autocomplete_conceptos':
            $q = trim($request['q'] ?? '');
            $stmt = $pdo->prepare("
                SELECT DISTINCT concepto FROM gastos
                WHERE concepto LIKE :q
                ORDER BY concepto ASC
                LIMIT 10
            ");
            $stmt->execute([':q' => '%' . $q . '%']);
            $conceptos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt2 = $pdo->prepare("
                SELECT DISTINCT concepto FROM ingresos
                WHERE concepto LIKE :q
                ORDER BY concepto ASC
                LIMIT 5
            ");
            $stmt2->execute([':q' => '%' . $q . '%']);
            $conceptos2 = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success'=>true, 'conceptos' => array_unique(array_merge($conceptos, $conceptos2))]);
            break;

        case 'bulk_upload_transacciones':
            $data = $request['data'] ?? [];
            if (!is_array($data) || empty($data)) {
                echo json_encode(['success'=>false,'message'=>'No hay datos para subir']); exit;
            }
            $stmt = $pdo->query("SELECT id, LOWER(nombre) as name FROM categorias");
            $cats = [];
            foreach($stmt->fetchAll() as $c) { $cats[$c['name']] = $c['id']; }
            
            $pdo->beginTransaction();
            try {
                $insertGasto = $pdo->prepare("INSERT INTO gastos (fecha, concepto, categoria_id, monto) VALUES (?, ?, ?, ?)");
                $insertIngreso = $pdo->prepare("INSERT INTO ingresos (fecha, concepto, monto) VALUES (?, ?, ?)");
                
                $insertados = 0;
                foreach($data as $row) {
                    $fecha = $row['fecha'];
                    $concepto = $row['concepto'];
                    $monto = (float)$row['monto'];
                    $tipo = strtolower($row['tipo']);
                    
                    if ($tipo === 'ingreso') {
                        $insertIngreso->execute([$fecha, $concepto, $monto]);
                        $insertados++;
                    } else {
                        $catName = strtolower(trim($row['categoria']));
                        if (!isset($cats[$catName])) {
                            $insCat = $pdo->prepare("INSERT INTO categorias (nombre, color, icono) VALUES (?, '#64748B', 'help-circle')");
                            $insCat->execute([trim($row['categoria'])]);
                            $cats[$catName] = $pdo->lastInsertId();
                        }
                        $insertGasto->execute([$fecha, $concepto, $cats[$catName], $monto]);
                        $insertados++;
                    }
                }
                $pdo->commit();
                echo json_encode(['success'=>true, 'insertados'=>$insertados]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success'=>false, 'message'=>'Error en BD: '.$e->getMessage()]);
            }
            exit;

        case 'bulk_upload_metas':
            $data = $request['data'] ?? [];
            if (!is_array($data) || empty($data)) {
                echo json_encode(['success'=>false,'message'=>'No hay datos para subir']); exit;
            }
            $stmt = $pdo->query("SELECT id, LOWER(nombre) as name FROM categorias");
            $cats = [];
            foreach($stmt->fetchAll() as $c) { $cats[$c['name']] = $c['id']; }
            
            $pdo->beginTransaction();
            try {
                $insertMeta = $pdo->prepare("INSERT INTO metas_categoria (categoria_id, anio, valor_meta) VALUES (?, ?, ?) ON CONFLICT(categoria_id, anio) DO UPDATE SET valor_meta=excluded.valor_meta");
                
                $insertados = 0;
                foreach($data as $row) {
                    $catName = strtolower(trim($row['categoria']));
                    if (!isset($cats[$catName])) {
                        $insCat = $pdo->prepare("INSERT INTO categorias (nombre, color, icono) VALUES (?, '#64748B', 'help-circle')");
                        $insCat->execute([trim($row['categoria'])]);
                        $cats[$catName] = $pdo->lastInsertId();
                    }
                    $insertMeta->execute([$cats[$catName], (int)$row['anio'], (float)$row['meta']]);
                    $insertados++;
                }
                $pdo->commit();
                echo json_encode(['success'=>true, 'insertados'=>$insertados]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success'=>false, 'message'=>'Error en BD: '.$e->getMessage()]);
            }
            exit;

        default:
            http_response_code(400);
            $response['message'] = "La acción '$action' no está permitida o no existe.";
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
