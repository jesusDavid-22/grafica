<?php
/**
 * Conexión y Gestión de Base de Datos SQLite para Dashboard Financiero
 */

define('DB_PATH', __DIR__ . '/../database/finanzas.db');

try {
    // 1. Establecer conexión PDO SQLite
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 2. Activar integridad referencial de llaves foráneas
    $pdo->exec("PRAGMA foreign_keys = ON;");
    
    // 3. Crear esquema de tablas si no existen
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categorias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            color TEXT NOT NULL,
            icono TEXT NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS presupuestos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            total REAL NOT NULL,
            periodo TEXT NOT NULL UNIQUE, -- Formato YYYY-MM o YYYY
            meta_ahorro_pct REAL NOT NULL DEFAULT 0
        );
        
        CREATE TABLE IF NOT EXISTS gastos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            concepto TEXT NOT NULL,
            monto REAL NOT NULL,
            fecha TEXT NOT NULL, -- Formato YYYY-MM-DD
            categoria_id INTEGER NOT NULL,
            FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS ingresos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            concepto TEXT NOT NULL,
            monto REAL NOT NULL,
            fecha TEXT NOT NULL -- Formato YYYY-MM-DD
        );

        -- NUEVAS TABLAS ESTRATÉGICAS --

        CREATE TABLE IF NOT EXISTS metas_categoria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria_id INTEGER NOT NULL,
            anio INTEGER NOT NULL,
            valor_meta REAL NOT NULL DEFAULT 0,
            UNIQUE(categoria_id, anio),
            FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS procesos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            descripcion TEXT,
            meta_anual REAL NOT NULL DEFAULT 0,
            anio INTEGER NOT NULL DEFAULT 0,
            categoria_id INTEGER,
            FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS historico_mensual (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            periodo TEXT NOT NULL UNIQUE, -- Formato YYYY-MM
            ingresos REAL NOT NULL DEFAULT 0,
            gastos REAL NOT NULL DEFAULT 0,
            balance REAL NOT NULL DEFAULT 0,
            presupuesto REAL NOT NULL DEFAULT 0,
            cumplimiento_pct REAL NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS alertas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo TEXT NOT NULL,  -- 'presupuesto', 'proyeccion', 'meta'
            mensaje TEXT NOT NULL,
            nivel TEXT NOT NULL DEFAULT 'info', -- 'info', 'warning', 'danger'
            periodo TEXT NOT NULL,
            leida INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");
    
    // 3b. Migraciones de columnas nuevas (si no existen)
    try { $pdo->exec("ALTER TABLE presupuestos ADD COLUMN meta_ahorro_monto REAL DEFAULT 0"); } catch (Exception $e) { /* ya existe */ }
    
    // 4. Poblar categorías predeterminadas si la tabla está vacía
    $stmt = $pdo->query("SELECT COUNT(*) FROM categorias");
    if ($stmt->fetchColumn() == 0) {
        $categoriasIniciales = [
            ['nombre' => 'Servicios', 'color' => '#3B82F6', 'icono' => 'zap'],
            ['nombre' => 'Marketing & Publicidad', 'color' => '#EC4899', 'icono' => 'megaphone'],
            ['nombre' => 'Nómina & Personal', 'color' => '#10B981', 'icono' => 'users'],
            ['nombre' => 'Tecnología & Software', 'color' => '#8B5CF6', 'icono' => 'cpu'],
            ['nombre' => 'Oficina & Suministros', 'color' => '#F59E0B', 'icono' => 'briefcase'],
            ['nombre' => 'Otros', 'color' => '#6B7280', 'icono' => 'help-circle']
        ];
        
        $insert = $pdo->prepare("INSERT INTO categorias (nombre, color, icono) VALUES (:nombre, :color, :icono)");
        foreach ($categoriasIniciales as $cat) {
            $insert->execute($cat);
        }
    }

    // 5. Poblar procesos estratégicos predeterminados si la tabla está vacía
    $stmt = $pdo->query("SELECT COUNT(*) FROM procesos");
    if ($stmt->fetchColumn() == 0) {
        $anioActual = (int)date('Y');
        $procesosIniciales = [
            ['nombre' => 'Nómina',       'descripcion' => 'Costos de nómina y personal',         'meta_anual' => 0, 'anio' => $anioActual],
            ['nombre' => 'Rutas',        'descripcion' => 'Transporte y logística de rutas',       'meta_anual' => 0, 'anio' => $anioActual],
            ['nombre' => 'Órdenes',      'descripcion' => 'Órdenes de compra y suministros',       'meta_anual' => 0, 'anio' => $anioActual],
            ['nombre' => 'Poda',         'descripcion' => 'Mantenimiento y poda de áreas verdes',  'meta_anual' => 0, 'anio' => $anioActual],
            ['nombre' => 'Energía',      'descripcion' => 'Consumo energético y servicios',        'meta_anual' => 0, 'anio' => $anioActual],
            ['nombre' => 'Inventarios',  'descripcion' => 'Control y gestión de inventarios',      'meta_anual' => 0, 'anio' => $anioActual],
        ];
        $insert = $pdo->prepare("INSERT INTO procesos (nombre, descripcion, meta_anual, anio) VALUES (:nombre, :descripcion, :meta_anual, :anio)");
        foreach ($procesosIniciales as $proc) {
            $insert->execute($proc);
        }
    }

} catch (PDOException $e) {
    die("Error crítico de conexión o base de datos: " . $e->getMessage());
}
