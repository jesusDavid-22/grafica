<?php
/**
 * Script de Poblado (Seed) de Datos Financieros de Demostración
 */

require_once __DIR__ . '/db.php';

echo "Iniciando poblado de datos financieros...\n";

try {
    // 1. Limpiar base de datos para evitar duplicados si se corre varias veces
    $pdo->exec("DELETE FROM gastos;");
    $pdo->exec("DELETE FROM ingresos;");
    $pdo->exec("DELETE FROM presupuestos;");
    echo "Base de datos limpia para seeding.\n";

    // Obtener IDs de categorías existentes (nombre como llave, id como valor)
    $categorias = $pdo->query("SELECT nombre, id FROM categorias")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Validar que tengamos las categorías creadas en db.php
    if (empty($categorias)) {
        throw new Exception("No se encontraron categorías. Ejecuta db.php primero.");
    }

    // 2. Insertar Presupuesto Anual (Año 2026)
    // $108,000.00 anual / 12 = $9,000.00 mensual prorrateado
    $presupuestos = [
        '2026' => 108000.00
    ];

    $insPresupuesto = $pdo->prepare("INSERT INTO presupuestos (total, periodo) VALUES (:total, :periodo)");
    foreach ($presupuestos as $periodo => $total) {
        $insPresupuesto->execute([':total' => $total, ':periodo' => $periodo]);
    }
    echo "Presupuesto anual del año 2026 creado con éxito.\n";

    // 3. Insertar Ingresos Históricos
    $ingresosSemilla = [
        // Enero
        ['concepto' => 'Facturación Proyecto Web Cliente A', 'monto' => 6500.00, 'fecha' => '2026-01-10'],
        ['concepto' => 'Soporte Mensual Servidores Cliente B', 'monto' => 3800.00, 'fecha' => '2026-01-25'],
        // Febrero
        ['concepto' => 'Facturación Desarrollo SaaS Cliente C', 'monto' => 8500.00, 'fecha' => '2026-02-05'],
        ['concepto' => 'Licencias de Software Renovación Anual', 'monto' => 2400.00, 'fecha' => '2026-02-18'],
        // Marzo
        ['concepto' => 'Consultoría de Seguridad IT Cliente D', 'monto' => 12000.00, 'fecha' => '2026-03-12'],
        ['concepto' => 'Soporte Mensual Servidores Cliente B', 'monto' => 3800.00, 'fecha' => '2026-03-25'],
        // Abril
        ['concepto' => 'Facturación Proyecto Web Cliente E', 'monto' => 7000.00, 'fecha' => '2026-04-10'],
        ['concepto' => 'Servicios Cloud Freelance Cliente F', 'monto' => 3500.00, 'fecha' => '2026-04-20'],
        // Mayo
        ['concepto' => 'Desarrollo MVP App Móvil Cliente G', 'monto' => 13500.00, 'fecha' => '2026-05-08'],
        ['concepto' => 'Soporte Mensual Servidores Cliente B', 'monto' => 3800.00, 'fecha' => '2026-05-25'],
        // Junio (Mes Actual)
        ['concepto' => 'Facturación Cobro Mensual SaaS Enterprise', 'monto' => 9500.00, 'fecha' => '2026-06-02'],
        ['concepto' => 'Consultoría Arquitectura de Cloud', 'monto' => 5200.00, 'fecha' => '2026-06-15']
    ];

    $insIngreso = $pdo->prepare("INSERT INTO ingresos (concepto, monto, fecha) VALUES (:concepto, :monto, :fecha)");
    foreach ($ingresosSemilla as $ing) {
        $insIngreso->execute($ing);
    }
    echo "Ingresos históricos creados con éxito.\n";

    // 4. Insertar Gastos Históricos
    $gastosSemilla = [
        // Enero 2026 (Presupuesto: 8000, Total Gasto: ~6100)
        ['concepto' => 'Alquiler Oficinas Centrales', 'monto' => 2500.00, 'fecha' => '2026-01-02', 'cat' => 'Oficina & Suministros'],
        ['concepto' => 'Pago Honorarios Contable Externo', 'monto' => 1200.00, 'fecha' => '2026-01-05', 'cat' => 'Nómina & Personal'],
        ['concepto' => 'Suscripciones AWS Cloud Server', 'monto' => 850.00, 'fecha' => '2026-01-15', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Campaña Google Ads Tráfico Frío', 'monto' => 1000.00, 'fecha' => '2026-01-20', 'cat' => 'Marketing & Publicidad'],
        ['concepto' => 'Pago Recibo Luz e Internet', 'monto' => 550.00, 'fecha' => '2026-01-22', 'cat' => 'Servicios'],

        // Febrero 2026 (Presupuesto: 8500, Total Gasto: ~6900)
        ['concepto' => 'Alquiler Oficinas Centrales', 'monto' => 2500.00, 'fecha' => '2026-02-02', 'cat' => 'Oficina & Suministros'],
        ['concepto' => 'Honorarios Desarrollador Backend Freelance', 'monto' => 1800.00, 'fecha' => '2026-02-05', 'cat' => 'Nómina & Personal'],
        ['concepto' => 'Suscripciones AWS Cloud Server', 'monto' => 920.00, 'fecha' => '2026-02-15', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Campaña Facebook Ads Prospectos', 'monto' => 1200.00, 'fecha' => '2026-02-20', 'cat' => 'Marketing & Publicidad'],
        ['concepto' => 'Pago Recibo Luz e Internet', 'monto' => 480.00, 'fecha' => '2026-02-22', 'cat' => 'Servicios'],

        // Marzo 2026 (Presupuesto: 9500, Total Gasto: ~7800)
        ['concepto' => 'Alquiler Oficinas Centrales', 'monto' => 2500.00, 'fecha' => '2026-03-02', 'cat' => 'Oficina & Suministros'],
        ['concepto' => 'Nómina Integrantes de Equipo', 'monto' => 3000.00, 'fecha' => '2026-03-05', 'cat' => 'Nómina & Personal'],
        ['concepto' => 'Suscripciones AWS Cloud Server', 'monto' => 980.00, 'fecha' => '2026-03-15', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Campaña Google Ads Remarketing', 'monto' => 800.00, 'fecha' => '2026-03-20', 'cat' => 'Marketing & Publicidad'],
        ['concepto' => 'Pago Recibo Luz e Internet', 'monto' => 520.00, 'fecha' => '2026-03-22', 'cat' => 'Servicios'],

        // Abril 2026 (Presupuesto: 9000, Total Gasto: ~9650 -> Sobrecosto de 650!)
        ['concepto' => 'Alquiler Oficinas Centrales', 'monto' => 2500.00, 'fecha' => '2026-04-02', 'cat' => 'Oficina & Suministros'],
        ['concepto' => 'Nómina Integrantes de Equipo', 'monto' => 3000.00, 'fecha' => '2026-04-05', 'cat' => 'Nómina & Personal'],
        ['concepto' => 'Compra de Licencias Corporativas Extra', 'monto' => 1500.00, 'fecha' => '2026-04-12', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Suscripciones AWS Cloud Server', 'monto' => 1100.00, 'fecha' => '2026-04-15', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Campaña Facebook Ads Inundación de Leads', 'monto' => 1000.00, 'fecha' => '2026-04-20', 'cat' => 'Marketing & Publicidad'],
        ['concepto' => 'Reparación Aire Acondicionado Oficina', 'monto' => 550.00, 'fecha' => '2026-04-22', 'cat' => 'Otros'],

        // Mayo 2026 (Presupuesto: 10000, Total Gasto: ~8900)
        ['concepto' => 'Alquiler Oficinas Centrales', 'monto' => 2500.00, 'fecha' => '2026-05-02', 'cat' => 'Oficina & Suministros'],
        ['concepto' => 'Nómina Integrantes de Equipo', 'monto' => 3500.00, 'fecha' => '2026-05-05', 'cat' => 'Nómina & Personal'],
        ['concepto' => 'Suscripciones AWS Cloud Server', 'monto' => 1250.00, 'fecha' => '2026-05-15', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Campaña Google Ads Búsqueda', 'monto' => 1200.00, 'fecha' => '2026-05-20', 'cat' => 'Marketing & Publicidad'],
        ['concepto' => 'Comisión Pasarelas de Pago Stripe', 'monto' => 450.00, 'fecha' => '2026-05-28', 'cat' => 'Servicios'],

        // Junio 2026 - Mes de análisis principal (Presupuesto: 11000, Total Gasto: ~8200)
        ['concepto' => 'Alquiler Oficinas Centrales', 'monto' => 2500.00, 'fecha' => '2026-06-02', 'cat' => 'Oficina & Suministros'],
        ['concepto' => 'Nómina Integrantes de Equipo', 'monto' => 3500.00, 'fecha' => '2026-06-05', 'cat' => 'Nómina & Personal'],
        ['concepto' => 'Renovación de Dominios Web y SSL', 'monto' => 150.00, 'fecha' => '2026-06-10', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Suscripciones AWS Cloud Server', 'monto' => 1100.00, 'fecha' => '2026-06-15', 'cat' => 'Tecnología & Software'],
        ['concepto' => 'Campaña Retargeting Conversiones', 'monto' => 600.00, 'fecha' => '2026-06-20', 'cat' => 'Marketing & Publicidad'],
        ['concepto' => 'Recibo Agua, Luz y Fibra Óptica', 'monto' => 350.00, 'fecha' => '2026-06-22', 'cat' => 'Servicios']
    ];

    $insGasto = $pdo->prepare("
        INSERT INTO gastos (concepto, monto, fecha, categoria_id) 
        VALUES (:concepto, :monto, :fecha, :categoria_id)
    ");
    
    foreach ($gastosSemilla as $g) {
        $catName = $g['cat'];
        $catId = $categorias[$catName] ?? null;
        
        if ($catId === null) {
            echo "Alerta: Categoría '$catName' no encontrada para el gasto.\n";
            continue;
        }
        
        $insGasto->execute([
            ':concepto' => $g['concepto'],
            ':monto' => $g['monto'],
            ':fecha' => $g['fecha'],
            ':categoria_id' => $catId
        ]);
    }
    
    echo "Gastos históricos creados con éxito.\n";
    echo "¡Poblado de datos finalizado exitosamente!\n";

} catch (Exception $e) {
    die("Error durante el seeding: " . $e->getMessage() . "\n");
}
