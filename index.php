<?php
/**
 * Interfaz de Usuario Principal - Dashboard Financiero Estratégico
 */
require_once __DIR__ . '/includes/db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinancialDash — Dashboard Estratégico</title>
    <meta name="description" content="Dashboard financiero estratégico con KPIs, metas, proyecciones y análisis de desviaciones en tiempo real.">
    <link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body>

    <div class="app-container">
        
        <!-- ══════════════════════════════════════════
             SIDEBAR IZQUIERDO
        ══════════════════════════════════════════ -->
        <aside class="sidebar">
            <div class="brand-section">
                <div class="brand-icon">
                    <i data-lucide="trending-up"></i>
                </div>
                <span class="brand-name">FinancialDash</span>
            </div>
            
            <nav>
                <ul class="nav-menu">
                    <li><a class="nav-item active" href="#dashboard">
                        <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
                    </a></li>
                    <li><a class="nav-item" href="#metas">
                        <i data-lucide="target"></i><span>Metas</span>
                    </a></li>
                    <li><a class="nav-item" href="#procesos">
                        <i data-lucide="bar-chart-horizontal"></i><span>Procesos</span>
                    </a></li>
                    <li><a class="nav-item" href="#historico">
                        <i data-lucide="calendar-days"></i><span>Histórico</span>
                    </a></li>
                    <li><a class="nav-item" href="#bitacora">
                        <i data-lucide="book-open"></i><span>Bitácora</span>
                    </a></li>
                    <li><a class="nav-item" href="#cargamasiva">
                        <i data-lucide="upload-cloud"></i><span>Carga Masiva</span>
                    </a></li>
                    <li><hr style="border:none;border-top:1px solid rgba(255,255,255,0.06);margin:0.5rem 0;"></li>
                    <li><a class="nav-item" onclick="openCategoriesManager()">
                        <i data-lucide="folder-cog"></i><span>Categorías</span>
                    </a></li>
                    <li><a class="nav-item" onclick="openModal('modal-presupuesto')">
                        <i data-lucide="wallet"></i><span>Presupuesto</span>
                    </a></li>
                    <li><a class="nav-item" onclick="openModal('modal-gasto')">
                        <i data-lucide="receipt"></i><span>Registrar Gasto</span>
                    </a></li>
                    <li><a class="nav-item" onclick="openModal('modal-ingreso')">
                        <i data-lucide="arrow-down-to-dot"></i><span>Registrar Ingreso</span>
                    </a></li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar">AD</div>
                    <div class="user-info">
                        <p class="username">Admin Finanzas</p>
                        <p class="role">Dirección Administrativa</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ══════════════════════════════════════════
             CONTENIDO PRINCIPAL
        ══════════════════════════════════════════ -->
        <main class="main-content" id="dashboard">
            
            <!-- ── ENCABEZADO ─────────────────────── -->
            <header class="dashboard-header" style="flex-direction:column; align-items:flex-start; gap:0.5rem;">
                <div class="header-title" style="width:100%;">
                    <h1 id="dashboard-title-text" style="font-size:1.8rem;">Direccion Administrativa</h1>
                    <p style="font-size:1rem; font-weight:bold; color:var(--text-primary); margin-top:0.2rem;">MCI 2026 Reducir los gastos operativos del ppto aprobado, pasando de 18.782 a 18.546 millones de dolares generando ahorros de 236.000 dolares a Dic 31 de 2026</p>
                </div>
                <div class="header-actions">
                    <!-- Campana de alertas -->
                    <button class="alerts-bell-btn" id="alerts-bell" onclick="toggleAlertsPanel()" title="Alertas automáticas">
                        <i data-lucide="bell" style="width:16px;height:16px;"></i>
                        <span id="alerts-badge" class="alerts-badge">0</span>
                    </button>

                    <div class="date-selector-container">
                        <input type="month" id="global-period-picker" class="date-input" value="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="auto-refresh-badge" id="auto-refresh-badge" title="Auto-actualización cada 60 seg">
                        <span class="refresh-dot"></span>
                        <span id="refresh-countdown">60s</span>
                    </div>
                    <button class="btn btn-download-all" onclick="AppCharts.downloadAllCharts()" title="Descargar gráficas PNG">
                        <i data-lucide="download-cloud"></i> PNG
                    </button>
                    <button class="btn btn-outline" onclick="exportarPDF()" title="Exportar PDF ejecutivo">
                        <i data-lucide="file-text"></i> PDF
                    </button>
                    <button class="btn btn-outline" onclick="window.print()">
                        <i data-lucide="printer"></i> Imprimir
                    </button>
                    <button class="btn btn-success" onclick="openModal('modal-ingreso')">
                        <i data-lucide="plus-circle"></i> Ingreso
                    </button>
                    <button class="btn btn-primary" onclick="openModal('modal-gasto')">
                        <i data-lucide="minus-circle"></i> Gasto
                    </button>
                    <button class="btn btn-outline" onclick="abrirMetaCat()" title="Definir presupuesto por categoria" style="border-color:rgba(245,158,11,0.4);color:var(--warning);">
                        <i data-lucide="target"></i> Metas Cat.
                    </button>
                </div>
            </header>

            <!-- ── PANEL DE ALERTAS (oculto por defecto) ──────────── -->
            <div class="alerts-panel" id="alerts-panel">
                <div class="alerts-panel-header">
                    <h4>🔔 Alertas Automáticas</h4>
                    <button class="btn-mark-all-read" onclick="markAllAlertsRead()">Marcar todas como leídas</button>
                </div>
                <div id="alerts-panel-body">
                    <div class="alerts-empty">Sin alertas activas para este período.</div>
                </div>
            </div>



            <!-- ── FILTROS GLOBALES ───────────────────────────────── -->
            <div class="global-filters">
                <label>Vista:</label>
                <button class="filter-btn" data-filtro="dia" onclick="setFiltroGlobal('dia',this)">Día</button>
                <button class="filter-btn" data-filtro="semana" onclick="setFiltroGlobal('semana',this)">Semana</button>
                <button class="filter-btn active" data-filtro="mes" onclick="setFiltroGlobal('mes',this)">Mes</button>
                <button class="filter-btn" data-filtro="trimestre" onclick="setFiltroGlobal('trimestre',this)">Trimestre</button>
                <button class="filter-btn" data-filtro="anio" onclick="setFiltroGlobal('anio',this)">Año</button>
                <span style="margin-left:auto;font-size:0.72rem;color:var(--text-muted)" id="filtro-periodo-label"></span>
            </div>

            <!-- ── KPIs GRID ─────────────────────────────────────── -->
            <!-- Seccion MCI 2026 -->
            <section id="metas" style="display:flex; flex-wrap:wrap; gap:1.5rem; margin-top:1.5rem; margin-bottom:1.5rem;">
                <!-- Avance MCI -->
                <div style="background:var(--primary); color:white; border-radius:12px; padding:1.5rem; flex:1; min-width:200px; box-shadow:0 8px 20px rgba(99,102,241,0.3); border:2px solid rgba(255,255,255,0.1); position:relative; overflow:hidden;">
                    <div style="font-size:1rem; font-weight:700; margin-bottom:0.5rem; text-transform:uppercase; letter-spacing:1px; z-index:2; position:relative;">Avance MCI</div>
                    <div id="mci-kpi-avance" style="font-size:clamp(1.5rem, 3vw, 2.5rem); font-weight:900; font-family:Outfit; text-align:right; z-index:2; position:relative; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">--%</div>
                    <div style="position:absolute; top:-20px; left:-20px; width:100px; height:100px; background:rgba(255,255,255,0.1); border-radius:50%; pointer-events:none;"></div>
                </div>
                
                <!-- Ahorro $ -->
                <div style="background:linear-gradient(135deg, #059669 0%, #047857 100%); color:white; border-radius:12px; padding:1.5rem; flex:1; min-width:200px; box-shadow:0 8px 20px rgba(5,150,105,0.3); border:2px solid rgba(255,255,255,0.1); position:relative; overflow:hidden;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem; z-index:2; position:relative;">
                        <div style="font-size:1rem; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Ahorro $</div>
                        <div id="ahorro-toggle-container" style="background:rgba(0,0,0,0.2); border-radius:20px; display:flex; padding:2px; font-size:0.75rem; display:none;">
                            <button id="btn-ahorro-acumulado" style="padding:4px 8px; border-radius:18px; background:white; color:#047857; font-weight:bold; border:none; cursor:pointer; transition:all 0.2s;">Acumulado</button>
                            <button id="btn-ahorro-aislado" style="padding:4px 8px; border-radius:18px; background:transparent; color:white; font-weight:bold; border:none; cursor:pointer; transition:all 0.2s;">Aislado</button>
                        </div>
                    </div>
                    <div id="mci-kpi-ahorro" style="font-size:clamp(1.5rem, 3vw, 2.5rem); font-weight:900; font-family:Outfit; text-align:right; z-index:2; position:relative; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">$--</div>
                    <i data-lucide="piggy-bank" style="position:absolute; bottom:-10px; left:-10px; width:100px; height:100px; color:rgba(255,255,255,0.1); pointer-events:none; transform:rotate(-15deg);"></i>
                </div>
            </section>

            <!-- Tarjetas de KPIs Generales -->
            <section id="procesos" class="kpis-grid">
                
                <div class="glass-panel kpi-card" id="card-presupuesto">
                    <div class="kpi-header">
                        <span class="kpi-title">Presupuesto Asignado</span>
                        <div class="kpi-icon-wrapper" style="background:rgba(99,102,241,0.15);color:var(--primary);">
                            <i data-lucide="landmark"></i>
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-val-presupuesto">$0.00</div>
                    <div class="kpi-footer"><span>Período de análisis mensual</span></div>
                </div>

                <div class="glass-panel kpi-card" id="card-gastos">
                    <div class="kpi-header">
                        <span class="kpi-title">Gasto Ejecutado</span>
                        <div class="kpi-icon-wrapper" style="background:rgba(239,68,68,0.15);color:var(--danger);">
                            <i data-lucide="shopping-bag"></i>
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-val-gastos">$0.00</div>
                    <div class="kpi-footer" id="kpi-foot-gastos"><span>Cargando...</span></div>
                </div>

                <div class="glass-panel kpi-card" id="card-restante">
                    <div class="kpi-header">
                        <span class="kpi-title">Fondo Disponible</span>
                        <div class="kpi-icon-wrapper" style="background:rgba(16,185,129,0.15);color:var(--success);">
                            <i data-lucide="banknote"></i>
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-val-restante">$0.00</div>
                    <div class="kpi-footer" id="kpi-foot-restante">
                        <div class="traffic-light-wrapper">
                            <span>Estado:</span>
                            <span id="traffic-light-badge" class="traffic-light-badge badge-verde">
                                <span class="traffic-light-dot"></span>
                                <span id="traffic-light-text">Estable</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="glass-panel kpi-card" id="card-proyeccion">
                    <div class="kpi-header">
                        <span class="kpi-title">Proyección Fin de Mes</span>
                        <div class="kpi-icon-wrapper" id="kpi-icon-proyeccion" style="background:rgba(14,165,233,0.15);color:var(--info);">
                            <i data-lucide="calendar-range"></i>
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-val-proyeccion">$0.00</div>
                    <div class="kpi-footer" id="kpi-foot-proyeccion"><span>Calculando proyección...</span></div>
                </div>

                <!-- KPI: Cumplimiento Estratégico -->
                <div class="glass-panel kpi-card" id="card-cumplimiento">
                    <div class="kpi-header">
                        <span class="kpi-title">Cumplimiento Estratégico</span>
                        <div class="kpi-icon-wrapper" style="background:rgba(245,158,11,0.15);color:var(--warning);">
                            <i data-lucide="target"></i>
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-val-cumplimiento">—</div>
                    <div class="kpi-footer" id="kpi-foot-cumplimiento"><span>vs. metas anuales</span></div>
                </div>

                <!-- KPI: Ingreso del Período -->
                <div class="glass-panel kpi-card" id="card-ingresos">
                    <div class="kpi-header">
                        <span class="kpi-title">Ingresos del Período</span>
                        <div class="kpi-icon-wrapper" style="background:rgba(16,185,129,0.15);color:var(--success);">
                            <i data-lucide="arrow-up-right"></i>
                        </div>
                    </div>
                    <div class="kpi-value" id="kpi-val-ingresos">$0.00</div>
                    <div class="kpi-footer"><span>Total ingresos registrados</span></div>
                </div>

                <!-- Balance Anual (Movido a KPIs) -->
                <div class="glass-panel kpi-card" style="grid-column: span 2; display:flex; flex-direction:column; justify-content: space-between;">
                    <div class="kpi-header">
                        <span class="kpi-title">Balance Anual</span>
                        <div class="kpi-icon-wrapper" style="background:rgba(14,165,233,0.15);color:var(--info);">
                            <i data-lucide="scale"></i>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <div>
                            <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.2rem;">Balance Neto del Año</div>
                            <div id="balance-anual-monto" style="font-size:2rem;font-weight:900;font-family:Outfit;color:var(--success);">-</div>
                        </div>
                        <div style="display:flex;gap:1.5rem;">
                            <div style="text-align:right;">
                                <div style="font-size:0.72rem;color:var(--text-muted);">Ingresos Totales</div>
                                <div id="balance-anual-ingresos" style="font-size:1.1rem;font-weight:700;color:var(--success);">-</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.72rem;color:var(--text-muted);">Gastos Totales</div>
                                <div id="balance-anual-gastos" style="font-size:1.1rem;font-weight:700;color:var(--danger);">-</div>
                            </div>
                        </div>
                    </div>
                </div>

            </section>

            <!-- ── GRÁFICAS PRINCIPALES Y TABLAS MCI ─────────────────────────── -->
            <section class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
                
                <!-- Gráfica Avance Proceso (Izquierda) -->
                <div class="glass-panel chart-panel">
                    <div class="panel-header">
                        <span class="panel-title">Avance $ proceso</span>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <button class="btn-chart-dl" onclick="AppCharts.downloadChart('mciAvance','Avance_MCI')" title="Descargar">
                                <i data-lucide="download" style="width:13px;height:13px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container" id="chart-mci-avance-container" style="min-height:350px;"></div>
                </div>

                <!-- Tabla Acumulado vs Meta (Derecha) -->
                <div class="glass-panel" style="display:flex; flex-direction:column; padding:1.5rem;">
                    <div class="panel-header" style="margin-bottom:1rem;">
                        <span class="panel-title">Cumplimiento por Proceso</span>
                    </div>
                    <div class="transactions-table-wrapper" style="border:1px solid var(--panel-border); border-radius:12px; overflow-x:auto;">
                        <table class="transactions-table table-compact" style="font-size:0.85rem;" id="mci-table-cumplimiento">
                            <thead>
                                <tr>
                                    <th>CATEGORÍA</th>
                                    <th style="text-align:right;">EJECUTADO</th>
                                    <th style="text-align:right;">META 2026</th>
                                    <th style="text-align:right;">CUMPLIMIENTO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Llenado por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>

            <section class="dashboard-grid" style="grid-template-columns: 1fr 1fr; margin-top:1.5rem;">
                
                <!-- Gráfica Avance por proceso (Barras horizontales) -->
                <div class="glass-panel chart-panel">
                    <div class="panel-header">
                        <span class="panel-title">Avance por proceso</span>
                    </div>
                    <div class="chart-container" id="chart-mci-bar-horizontal-container" style="min-height:300px;"></div>
                </div>

                <!-- Tabla Ejecutado vs Estimado -->
                <div class="glass-panel" style="display:flex; flex-direction:column; padding:1.5rem;">
                    <div class="panel-header" style="margin-bottom:1rem;">
                        <span class="panel-title">Desviación del Estimado</span>
                    </div>
                    <div class="transactions-table-wrapper" style="border:1px solid var(--panel-border); border-radius:12px; overflow-x:auto;">
                        <table class="transactions-table table-compact" style="font-size:0.85rem;" id="mci-table-desviacion">
                            <thead>
                                <tr>
                                    <th>CATEGORÍA</th>
                                    <th style="text-align:right;">META ANUAL</th>
                                    <th style="text-align:right;">ESTIMADO</th>
                                    <th style="text-align:right;">EJECUTADO</th>
                                    <th style="text-align:right; width:100px;">DIFERENCIA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Llenado por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>

            <!-- GAUGE + TENDENCIAS -->
            <section id="historico" class="dashboard-grid" style="margin-top:2.5rem;">
                
                <div class="glass-panel chart-panel">
                    <div class="panel-header">
                        <span class="panel-title">Nivel de Consumo Presupuestario</span>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <button class="btn-chart-dl" onclick="AppCharts.downloadChart('gauge','velocimetro')" title="Descargar">
                                <i data-lucide="download" style="width:13px;height:13px;"></i>
                            </button>
                            <i data-lucide="gauge" class="text-secondary" style="width:18px;"></i>
                        </div>
                    </div>
                    <div class="chart-container" id="chart-gauge-container"></div>
                </div>

                <div class="glass-panel chart-panel">
                    <div class="panel-header">
                        <span class="panel-title">Tendencia de Gastos por Categoría</span>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <!-- Toggle Vista Mensual / Anual -->
                            <div style="display:flex;gap:2px;background:rgba(0,0,0,0.25);border-radius:6px;padding:2px;">
                                <button id="btn-trend-mes" class="filter-btn active"
                                    onclick="switchTrendView('mes')"
                                    style="padding:0.22rem 0.55rem;font-size:0.72rem;border-radius:5px;">
                                    Mensual
                                </button>
                                <button id="btn-trend-anio" class="filter-btn"
                                    onclick="switchTrendView('anio')"
                                    style="padding:0.22rem 0.55rem;font-size:0.72rem;border-radius:5px;">
                                    Anual
                                </button>
                            </div>
                            <button class="btn-chart-dl" onclick="AppCharts.downloadChart('trend','Tendencia de Gastos por Categoria')" title="Descargar">
                                <i data-lucide="download" style="width:13px;height:13px;"></i>
                            </button>
                            <i data-lucide="bar-chart-2" class="text-secondary" style="width:18px;"></i>
                        </div>
                    </div>
                    
                    <div id="btn-back-trend-container" style="display:none; position:absolute; top:45px; left:20px; z-index:10;">
                        <button onclick="AppCharts.backToTrendCategories()" class="btn" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#fff; font-size:0.75rem; padding:0.3rem 0.6rem; border-radius:4px; display:flex; align-items:center; gap:0.3rem; backdrop-filter:blur(4px);">
                            <i data-lucide="arrow-left" style="width:12px; height:12px;"></i>
                            Volver a Categorías
                        </button>
                    </div>
                    
                    <div class="chart-container" id="chart-trend-container"></div>
                </div>


            </section>

            <!-- ── DONUT + RADAR ─────────────────────────────────── -->
            <section class="dashboard-grid" style="margin-top:1.25rem;">

                <div class="glass-panel chart-panel">
                    <div class="panel-header">
                        <span class="panel-title">Distribución por Categorías</span>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <button class="btn-chart-dl" onclick="AppCharts.downloadChart('donut','categorias')" title="Descargar">
                                <i data-lucide="download" style="width:13px;height:13px;"></i>
                            </button>
                            <i data-lucide="pie-chart" class="text-secondary" style="width:18px;"></i>
                        </div>
                    </div>
                    
                    <div id="btn-back-donut-container" style="display:none; position:absolute; top:45px; left:20px; z-index:10;">
                        <button onclick="AppCharts.backToCategories()" class="btn" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#fff; font-size:0.75rem; padding:0.3rem 0.6rem; border-radius:4px; display:flex; align-items:center; gap:0.3rem; backdrop-filter:blur(4px);">
                            <i data-lucide="arrow-left" style="width:12px; height:12px;"></i>
                            Volver
                        </button>
                    </div>
                    
                    <div class="chart-container" id="chart-donut-container"></div>
                </div>

            </section>

            <!-- ── HISTÓRICO 12 MESES (barras agrupadas) ─────────── -->
            <section class="glass-panel" style="margin-top:1.25rem;min-height:360px;display:flex;flex-direction:column;">
                <div class="panel-header">
                    <span class="panel-title">Ingresos vs Gastos — Histórico Anual</span>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <button class="btn-chart-dl" onclick="AppCharts.downloadChart('bar','historico')" title="Descargar">
                            <i data-lucide="download" style="width:13px;height:13px;"></i>
                        </button>
                        <i data-lucide="bar-chart-3" class="text-secondary" style="width:18px;"></i>
                    </div>
                </div>
                <div class="chart-container" id="chart-bar-container" style="flex:1;min-height:280px;"></div>
            </section>

            <!-- ── OBJETIVO DE AHORRO GLOBAL ────────────────────────────── -->
            <section class="ahorro-section" style="margin-bottom: 2.5rem; margin-top: 2.5rem;">
                <div class="section-header" style="margin-bottom: 1.2rem;">
                    <h2 class="section-title" style="display:flex; align-items:center; gap:0.5rem; font-size:1.3rem;">
                        <span class="st-icon" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size:1.5rem;">🎯</span> 
                        Objetivo de Ahorro Global
                    </h2>
                    <span class="section-badge" id="anio-ahorro-label" style="background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2); color: var(--primary); padding: 0.3rem 0.8rem; border-radius: 20px; font-weight: 700; box-shadow: 0 0 10px rgba(99,102,241,0.1);"><?php echo date('Y'); ?></span>
                </div>
                
                <div class="ahorro-panel glass-panel" style="position: relative; padding: 2rem; border-radius: 16px; background: linear-gradient(145deg, rgba(15,23,42,0.8) 0%, rgba(5,8,22,0.9) 100%); border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 10px 40px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.1); overflow: hidden;">
                    <!-- Decorative background glow -->
                    <div style="position:absolute; top:-50px; right:-50px; width:150px; height:150px; background: var(--primary); filter: blur(80px); opacity: 0.15; border-radius: 50%; pointer-events: none;"></div>
                    
                    <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.5rem; max-width: 600px; line-height: 1.5;">
                        Define cuánto dinero deseas conservar libre al final del año. El sistema calculará dinámicamente tu límite de gasto permitido y te mostrará una proyección inteligente.
                    </p>
                    
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:2rem;">
                        <form onsubmit="saveMetaAhorroGlobal(event)" class="ahorro-form" style="display:flex; gap:0.8rem; align-items:flex-end; flex-wrap:wrap; flex: 1; min-width: 250px;">
                            <div style="display:flex; flex-direction:column; gap:0.4rem;">
                                <label for="meta-ahorro-input" style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Meta de Ahorro Anual</label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--primary); font-size:1rem; font-weight: 800; pointer-events:none;">$</span>
                                    <input type="number" id="meta-ahorro-input" placeholder="Ej: 45000000" min="0" step="any"
                                        style="width:200px; background:rgba(0,0,0,0.25); border:1px solid rgba(99,102,241,0.3); color:#fff; font-family: Outfit; font-weight: 600; font-size: 1.05rem; border-radius:8px; padding:0.6rem 0.8rem; padding-left:2rem; transition: all 0.3s ease; outline:none; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="padding:0.65rem 1.2rem; border-radius: 8px; font-weight: 600; display:flex; align-items:center; gap:0.4rem; box-shadow: 0 4px 12px rgba(99,102,241,0.3);">
                                <i data-lucide="target" style="width: 16px; height: 16px;"></i>
                                Fijar Meta
                            </button>
                        </form>
                        
                        <div style="display:flex; gap:2.5rem; flex-wrap:wrap; background: rgba(255,255,255,0.02); padding: 1rem 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                            <div style="display:flex; flex-direction:column; align-items:flex-end;">
                                <div style="font-size:0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color:var(--text-muted); margin-bottom: 0.3rem;">Gasto Máx. Permitido</div>
                                <div id="ahorro-limite" style="font-size:1.6rem; font-weight:800; color:var(--success); font-family:Outfit; text-shadow: 0 0 15px rgba(16,185,129,0.3);">$0.00</div>
                            </div>
                            <div style="width: 1px; background: rgba(255,255,255,0.08);"></div>
                            <div style="display:flex; flex-direction:column; align-items:flex-end;">
                                <div style="font-size:0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color:var(--text-muted); margin-bottom: 0.3rem;">Proyección Gasto</div>
                                <div id="ahorro-proyectado" style="font-size:1.6rem; font-weight:800; color:var(--warning); font-family:Outfit; text-shadow: 0 0 15px rgba(245,158,11,0.3);">$0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ahorro-progress-container" style="margin-top: 2rem; background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                        <div style="display:flex; justify-content:space-between; align-items: flex-end; margin-bottom:0.6rem;">
                            <span style="font-size:0.8rem; font-weight: 600; color:var(--text-secondary); display:flex; align-items:center; gap:0.4rem;">
                                <i data-lucide="activity" style="width:14px; height:14px; color: var(--primary);"></i>
                                Estado de Ejecución
                            </span>
                            <span id="ahorro-pct-label" style="font-family: Outfit; font-weight:800; font-size: 1.1rem; color: #fff;">-</span>
                        </div>
                        <div style="background:rgba(255,255,255,0.06); border-radius:999px; height:12px; overflow:hidden; box-shadow: inset 0 2px 5px rgba(0,0,0,0.3); position: relative;">
                            <div id="ahorro-progress-bar" style="position:absolute; top:0; left:0; height:100%; width:0%; border-radius:999px; background: linear-gradient(90deg, var(--success), #34d399); transition:width 1s cubic-bezier(0.4, 0, 0.2, 1), background 0.5s ease; box-shadow: 0 0 10px rgba(16,185,129,0.5);"></div>
                        </div>
                        <div id="ahorro-status-msg" style="margin-top:0.8rem; font-size:0.8rem; font-weight: 500; color:var(--text-muted); text-align:center; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                            <i data-lucide="info" style="width:14px; height:14px;"></i>
                            Configura un presupuesto anual y tu meta para ver el análisis.
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── DESVIACIÓN PRESUPUESTARIA ─────────────────────── -->
            <section style="margin-bottom:2rem;">
                <div class="section-header">
                    <h2 class="section-title"><span class="st-icon">📐</span> Desviación Presupuestaria</h2>
                    <span class="section-badge" id="desv-nivel-badge">Calculando...</span>
                </div>
                <div class="desviacion-card" id="desviacion-card">
                    <div class="desv-item">
                        <div class="desv-label">Esperado pro-rata</div>
                        <div class="desv-value" id="desv-esperado">—</div>
                    </div>
                    <div class="desv-item">
                        <div class="desv-label">Ejecutado real</div>
                        <div class="desv-value" id="desv-ejecutado">—</div>
                    </div>
                    <div class="desv-item">
                        <div class="desv-label">Desviación</div>
                        <div class="desv-value" id="desv-valor">—</div>
                    </div>
                </div>
            </section>

            <!-- COMPARATIVO MES ANTERIOR -->
            <section class="comparativo-grid" style="margin-bottom:2rem;">

                <!-- Gastos -->
                <div class="glass-panel cmp-card">
                    <div class="cmp-card-header">
                        <i data-lucide="trending-down" style="width:16px;color:var(--danger);"></i>
                        <span class="panel-title" style="font-size:0.82rem;">Gastos del Mes</span>
                    </div>
                    <div class="cmp-value" id="cmp-gasto-actual">-</div>
                    <div class="cmp-sub">
                        <span style="color:var(--text-muted);font-size:0.72rem;">Mes anterior: <span id="cmp-gasto-anterior">-</span></span>
                        <span class="cmp-diff" id="cmp-gasto-diff">-</span>
                    </div>
                </div>

                <!-- Ingresos -->
                <div class="glass-panel cmp-card">
                    <div class="cmp-card-header">
                        <i data-lucide="trending-up" style="width:16px;color:var(--success);"></i>
                        <span class="panel-title" style="font-size:0.82rem;">Ingresos del Mes</span>
                    </div>
                    <div class="cmp-value" id="cmp-ingreso-actual">-</div>
                    <div class="cmp-sub">
                        <span style="color:var(--text-muted);font-size:0.72rem;">Mes anterior: <span id="cmp-ingreso-anterior">-</span></span>
                        <span class="cmp-diff" id="cmp-ingreso-diff">-</span>
                    </div>
                </div>

                <!-- Balance -->
                <div class="glass-panel cmp-card">
                    <div class="cmp-card-header">
                        <i data-lucide="scale" style="width:16px;color:var(--primary);"></i>
                        <span class="panel-title" style="font-size:0.82rem;">Balance vs Anterior</span>
                    </div>
                    <div class="cmp-value" id="cmp-balance-actual">-</div>
                    <div class="cmp-sub">
                        <span style="color:var(--text-muted);font-size:0.72rem;">Mes anterior: <span id="cmp-balance-anterior">-</span></span>
                        <span class="cmp-diff" id="cmp-balance-diff">-</span>
                    </div>
                </div>

            </section>

            <!-- META VS EJECUTADO POR CATEGORIA -->
            <section class="glass-panel" style="margin-bottom:2rem;min-height:300px;display:flex;flex-direction:column;">
                <div class="panel-header">
                    <span class="panel-title">Meta vs Ejecutado por Categoria</span>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <button class="btn btn-outline" onclick="abrirMetaCat()" style="padding:0.3rem 0.65rem;font-size:0.72rem;color:var(--warning);border-color:rgba(245,158,11,0.3);">
                            <i data-lucide="target" style="width:12px;height:12px;"></i> Definir metas
                        </button>
                        <button class="btn-chart-dl" onclick="AppCharts.downloadChart('metaVsEjec','Meta vs Ejecutado')" title="Descargar">
                            <i data-lucide="download" style="width:13px;height:13px;"></i>
                        </button>
                    </div>
                </div>
                <div class="chart-container" id="chart-metavs-container" style="flex:1;min-height:260px;"></div>
            </section>

            <!-- BALANCE ANUAL + COMPARATIVO -->
            <section class="dashboard-grid" style="margin-bottom:2rem; grid-template-columns: 1fr;">
                <!-- Comparativo Ingresos vs Gastos -->
                <div class="glass-panel chart-panel">
                    <div class="panel-header">
                        <span class="panel-title">Ingresos vs Gastos por Mes</span>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <button class="btn-chart-dl" onclick="AppCharts.downloadChart('comparativo','Ingresos vs Gastos')" title="Descargar">
                                <i data-lucide="download" style="width:13px;height:13px;"></i>
                            </button>
                            <i data-lucide="bar-chart" class="text-secondary" style="width:18px;"></i>
                        </div>
                    </div>
                    <div class="chart-container" id="chart-comparativo-container"></div>
                </div>
            </section>

            <!-- PROYECCION POR CATEGORIA (cards) -->
            <section class="proyeccion-cat-section" style="margin-bottom:2rem;">
                <div class="section-header">
                    <h2 class="section-title"><span class="st-icon">&#128302;</span> Proyeccion por Categoria (Fin de Mes)</h2>
                    <span class="section-badge">Semaforo de ejecucion</span>
                </div>
                <div id="proyeccion-cat-cards" class="proycat-grid">
                    <div style="color:var(--text-muted);padding:1.5rem;text-align:center;">Cargando...</div>
                </div>
            </section>
            <!-- LIBRO DE TRANSACCIONES -->
            <section class="bottom-grid">
                <div class="glass-panel transactions-panel">
                    <div class="panel-header">
                        <span class="panel-title">Libro de Transacciones</span>
                        <div style="display:flex;gap:0.5rem;align-items:center;">
                            <!-- Busqueda -->
                            <div class="ledger-search-wrap">
                                <i data-lucide="search" class="ledger-search-icon"></i>
                                <input type="text" id="ledger-search" class="ledger-search-input" placeholder="Buscar..." oninput="filterLedger(this.value)">
                            </div>
                            <button class="btn btn-outline" style="padding:0.4rem 0.8rem;font-size:0.75rem;color:var(--success);border-color:rgba(16,185,129,0.3);" onclick="exportExcelProfesional()">
                                <i data-lucide="download" style="width:12px;height:12px;"></i> Excel
                            </button>
                            <button class="btn btn-outline" style="padding:0.4rem 0.8rem;font-size:0.75rem;" onclick="refreshDashboardData()">
                                <i data-lucide="rotate-cw" style="width:12px;height:12px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="transactions-table-wrapper">
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>Concepto / Transaccion</th>
                                    <th>Fecha</th>
                                    <th>Categoria</th>
                                    <th style="text-align:right;">Monto</th>
                                    <th style="text-align:center;width:90px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ledger-tbody"></tbody>
                        </table>
                    </div>
                    <div id="ledger-empty-state" class="empty-state" style="display:none;">
                        <i data-lucide="inbox"></i>
                        <p>No hay transacciones para este periodo.</p>
                    </div>
                </div>

                <!-- Distribución por categorías (barra lateral) -->
                <div class="glass-panel category-distribution-panel">
                    <div class="panel-header">
                        <span class="panel-title">Distribución por Categorías</span>
                        <i data-lucide="pie-chart" class="text-secondary" style="width:18px;"></i>
                    </div>
                    <div class="category-progress-list" id="categories-progress-container"></div>
                    <div id="categories-empty-state" class="empty-state" style="display:none;">
                        <i data-lucide="folder-open"></i>
                        <p>No hay gastos en categorías.</p>
                    </div>
                </div>
            </section>

            <!-- ── BITÁCORA FINANCIERA ───────────────────────────── -->
            <section class="glass-panel bitacora-section" id="bitacora" style="margin-bottom:2rem;">
                <div class="panel-header" style="margin-bottom:1rem;">
                    <span class="panel-title" style="display:flex; align-items:center; gap:0.5rem;">
                        <i data-lucide="book-open" class="text-secondary" style="width:18px; height:18px;"></i>
                        Bitácora Financiera
                    </span>
                </div>
                <div class="bitacora-filters" style="margin-bottom:1.25rem;">
                    <select id="bitacora-anio" onchange="loadBitacora()">
                        <?php for($y=date('Y');$y>=2024;$y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($y==date('Y')) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <select id="bitacora-mes" onchange="loadBitacora()">
                        <option value="">Todos los meses</option>
                        <?php
                        $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                                  '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
                        foreach($meses as $k=>$v): ?>
                        <option value="<?php echo $k; ?>" <?php if($k==date('m')) echo 'selected'; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="bitacora-cat" onchange="loadBitacora()">
                        <option value="">Todas las categorías</option>
                        <!-- Cargado por JS -->
                    </select>
                    <button class="btn btn-outline" style="padding:0.4rem 0.8rem;font-size:0.78rem;" onclick="loadBitacora()">
                        <i data-lucide="filter" style="width:12px;height:12px;"></i> Filtrar
                    </button>
                </div>
                <div class="transactions-table-wrapper" style="border:1px solid var(--panel-border); border-radius:12px; overflow-x:auto;">
                    <table class="transactions-table" id="bitacora-table">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th>Fecha</th>
                                <th>Categoría</th>
                                <th style="text-align:right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody id="bitacora-tbody">
                            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:1rem;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ── BITÁCORA MATRIZ MCI (PIVOT) ───────────────────────────── -->
            <section class="glass-panel bitacora-section" id="bitacora-pivot" style="margin-bottom:2rem;">
                <div class="panel-header" style="margin-bottom:1rem;">
                    <span class="panel-title" style="display:flex; align-items:center; gap:0.5rem; font-size:1.2rem;">
                        <i data-lucide="grid" class="text-secondary" style="width:20px; height:20px;"></i>
                        BITÁCORA MCI 2026
                    </span>
                    <button class="btn btn-outline" style="padding:0.4rem 0.8rem;font-size:0.75rem;color:var(--success);border-color:rgba(16,185,129,0.3);" onclick="exportarBitacoraMCI()">
                        <i data-lucide="download" style="width:12px;height:12px;"></i> Exportar Excel
                    </button>
                </div>
                
                <div class="transactions-table-wrapper" style="border:1px solid var(--panel-border); border-radius:12px; overflow-x:auto;">
                    <table class="transactions-table" id="mci-pivot-table" style="font-size:0.85rem;">
                        <thead>
                            <tr style="background:rgba(255,255,255,0.05);" id="mci-pivot-cat-headers">
                                <th style="width:60px;">AÑO</th>
                                <th style="width:100px;">MES</th>
                                <!-- Las categorías se generarán dinámicamente -->
                                
                            </tr>
                        </thead>
                        <tbody id="mci-pivot-tbody">
                            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:1rem;">Calculando matriz...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ────────────────────────────────────────────────────────
                 MÓDULO: CARGA MASIVA (Oculto por defecto)
            ───────────────────────────────────────────────────────── -->
            <section id="cargamasiva-view" style="display:none; padding:1.5rem; animation:fadeIn 0.4s ease-out;">
                <div class="glass-panel" style="padding:2rem;">
                    <h2 style="font-size:1.5rem; font-weight:700; margin-bottom:0.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <i data-lucide="upload-cloud" style="color:var(--primary);"></i> Carga Masiva de Datos
                    </h2>
                    <p style="color:var(--text-secondary); margin-bottom:2rem; font-size:0.95rem;">
                        Sube registros masivos (Gastos, Ingresos o Metas MCI) arrastrando un archivo Excel (.xlsx) o CSV.
                    </p>

                    <!-- Selector de tipo de carga -->
                    <div style="display:flex; gap:1.5rem; margin-bottom:2rem;">
                        <div class="form-group" style="flex:1; max-width:300px;">
                            <label class="form-label" style="font-weight:600;">¿Qué deseas subir?</label>
                            <select id="bulk-upload-type" class="form-control" onchange="updateBulkTemplateLink()">
                                <option value="transacciones">Gastos e Ingresos (Histórico)</option>
                                <option value="metas">Metas Anuales (MCI)</option>
                            </select>
                        </div>
                        <div style="flex:1; display:flex; align-items:flex-end; padding-bottom:0.2rem;">
                            <button class="btn btn-outline" id="btn-download-template" onclick="downloadBulkTemplate()" style="display:flex; align-items:center; gap:0.5rem; border-color:var(--primary); color:var(--primary);">
                                <i data-lucide="download"></i> Descargar Plantilla
                            </button>
                        </div>
                    </div>

                    <!-- Drag & Drop Area -->
                    <div id="bulk-drop-zone" style="border: 2px dashed rgba(99,102,241,0.4); border-radius:12px; padding:3rem 2rem; text-align:center; background:rgba(99,102,241,0.05); cursor:pointer; transition:all 0.3s ease;">
                        <i data-lucide="file-spreadsheet" style="width:48px; height:48px; color:var(--primary); margin-bottom:1rem; opacity:0.8;"></i>
                        <h3 style="font-size:1.1rem; color:var(--text-primary); margin-bottom:0.5rem;">Arrastra tu archivo aquí o haz clic para seleccionarlo</h3>
                        <p style="color:var(--text-muted); font-size:0.85rem;">Soporta archivos .xlsx y .csv</p>
                        <input type="file" id="bulk-file-input" accept=".xlsx, .xls, .csv" style="display:none;">
                    </div>

                    <!-- Vista Previa de Tabla (Oculta hasta que se carga un archivo) -->
                    <div id="bulk-preview-container" style="display:none; margin-top:2rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="font-size:1.1rem; font-weight:600;"><i data-lucide="eye" style="display:inline; width:18px;"></i> Vista Previa de Registros</h3>
                            <div>
                                <span id="bulk-error-count" style="color:var(--danger); font-weight:600; margin-right:1rem; display:none;"></span>
                                <button class="btn btn-success" id="btn-bulk-submit" onclick="submitBulkData()" style="opacity:0.5; pointer-events:none;">
                                    <i data-lucide="save"></i> Subir Datos Válidos
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive" style="max-height:400px; overflow-y:auto; border:1px solid rgba(255,255,255,0.05); border-radius:8px;">
                            <table class="data-table" id="bulk-preview-table">
                                <thead id="bulk-preview-thead">
                                    <!-- Columnas generadas dinámicamente -->
                                </thead>
                                <tbody id="bulk-preview-tbody">
                                    <!-- Filas generadas dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- ══════════════════════════════════════════
         MODALES
    ══════════════════════════════════════════ -->

    <!-- Modal: Registrar Gasto -->
    <div id="modal-gasto" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Registrar Gasto Real</h3>
                <button class="modal-close-btn" onclick="closeModal('modal-gasto')"><i data-lucide="x"></i></button>
            </div>
            <form id="form-gasto" onsubmit="handleFormSubmit(event,'add_gasto')">
                <div class="form-group">
                    <label class="form-label" for="gasto-concepto">Concepto del Gasto</label>
                    <input type="text" id="gasto-concepto" class="form-control" placeholder="Ej. Pago de Hosting VPS" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="gasto-monto">Monto ($)</label>
                    <input type="number" id="gasto-monto" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="gasto-fecha">Fecha del Gasto</label>
                    <input type="date" id="gasto-fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="gasto-categoria">Categoría</label>
                    <select id="gasto-categoria" class="form-control" required></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-gasto')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar Gasto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Registrar Ingreso -->
    <div id="modal-ingreso" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Registrar Entrada de Ingreso</h3>
                <button class="modal-close-btn" onclick="closeModal('modal-ingreso')"><i data-lucide="x"></i></button>
            </div>
            <form id="form-ingreso" onsubmit="handleFormSubmit(event,'add_ingreso')">
                <div class="form-group">
                    <label class="form-label" for="ingreso-concepto">Concepto / Origen</label>
                    <input type="text" id="ingreso-concepto" class="form-control" placeholder="Ej. Facturación Servicio Web" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="ingreso-monto">Monto Ingresado ($)</label>
                    <input type="number" id="ingreso-monto" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="ingreso-fecha">Fecha de Ingreso</label>
                    <input type="date" id="ingreso-fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-ingreso')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Registrar Ingreso</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Editar Transaccion -->
    <div id="modal-editar-tx" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="editar-tx-title">Editar Transaccion</h3>
                <button class="modal-close-btn" onclick="closeModal('modal-editar-tx')"><i data-lucide="x"></i></button>
            </div>
            <form id="form-editar-tx" onsubmit="submitEditarTx(event)">
                <input type="hidden" id="edit-tx-id">
                <input type="hidden" id="edit-tx-tipo">
                <div class="form-group">
                    <label class="form-label">Concepto</label>
                    <input type="text" id="edit-tx-concepto" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Monto ($)</label>
                    <input type="number" id="edit-tx-monto" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha</label>
                    <input type="date" id="edit-tx-fecha" class="form-control" required>
                </div>
                <div class="form-group" id="edit-tx-cat-group">
                    <label class="form-label">Categoria</label>
                    <select id="edit-tx-categoria" class="form-control"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-editar-tx')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Presupuesto por Categoria -->
    <div id="modal-meta-cat" class="modal-overlay">
        <div class="modal-card" style="max-width:520px;">
            <div class="modal-header">
                <h3 class="modal-title">Presupuesto por Categoria</h3>
                <button class="modal-close-btn" onclick="closeModal('modal-meta-cat')"><i data-lucide="x"></i></button>
            </div>
            <div style="padding:0 1.5rem 0.5rem;">
                <p style="font-size:0.8rem;color:var(--text-muted);">Define el presupuesto anual maximo por categoria. El sistema te alertara cuando se acerque al limite.</p>
            </div>
            <div id="meta-cat-list" style="padding:0 1.5rem 1rem;display:flex;flex-direction:column;gap:0.75rem;max-height:60vh;overflow-y:auto;">
                <div style="color:var(--text-muted);text-align:center;padding:1rem;">Cargando categorias...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-meta-cat')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal: Fijar Presupuesto -->
    <div id="modal-presupuesto" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Fijar Presupuesto Anual</h3>
                <button class="modal-close-btn" onclick="closeModal('modal-presupuesto')"><i data-lucide="x"></i></button>
            </div>
            <form id="form-presupuesto" onsubmit="handleFormSubmit(event,'set_presupuesto')">
                <div class="form-group">
                    <label class="form-label" for="presupuesto-total">Presupuesto Anual Total ($)</label>
                    <input type="number" id="presupuesto-total" class="form-control" step="any" min="0.00" placeholder="Ej. 145000000" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="presupuesto-periodo">Año de Ejercicio</label>
                    <input type="number" id="presupuesto-periodo" class="form-control" min="2020" max="2099" value="<?php echo date('Y'); ?>" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-presupuesto')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Fijar Presupuesto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Gestionar Categorías -->
    <div id="modal-gestionar-categorias" class="modal-overlay">
        <div class="modal-card" style="max-width:600px;">
            <div class="modal-header">
                <h3 class="modal-title">Administración de Categorías</h3>
                <button class="modal-close-btn" onclick="closeModal('modal-gestionar-categorias')"><i data-lucide="x"></i></button>
            </div>
            <div style="max-height:200px;overflow-y:auto;margin-bottom:1.5rem;padding-right:0.5rem;border-bottom:1px solid rgba(255,255,255,0.05);">
                <div id="cat-manager-list" style="display:flex;flex-direction:column;gap:0.65rem;"></div>
            </div>
            <form id="form-manager-categoria" onsubmit="handleCategoryManagerSubmit(event)">
                <input type="hidden" id="cat-manager-id" value="">
                <h4 id="cat-manager-form-title" style="font-size:0.95rem;font-weight:700;margin-bottom:1rem;color:var(--primary);">Crear Nueva Categoría</h4>
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label" for="cat-manager-nombre">Nombre de la Categoría</label>
                        <input type="text" id="cat-manager-nombre" class="form-control" placeholder="Ej. Viajes & Logística" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cat-manager-color">Color Visual</label>
                        <input type="color" id="cat-manager-color" class="form-control" style="padding:0.15rem;height:38px;width:100%;cursor:pointer;" value="#6366f1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cat-manager-icono">Ícono Identificador</label>
                    <select id="cat-manager-icono" class="form-control" required>
                        <option value="tag" selected>Etiqueta (tag)</option>
                        <option value="zap">Rayo / Servicios (zap)</option>
                        <option value="megaphone">Megáfono / Marketing</option>
                        <option value="users">Usuarios / Nómina</option>
                        <option value="cpu">Procesador / Software</option>
                        <option value="briefcase">Maletín / Oficina</option>
                        <option value="help-circle">Interrogación / Otros</option>
                        <option value="shopping-cart">Carrito / Compras</option>
                        <option value="plane">Avión / Viajes</option>
                        <option value="car">Auto / Transporte</option>
                        <option value="utensils">Cubiertos / Comida</option>
                        <option value="wrench">Llave / Mantenimiento</option>
                        <option value="leaf">Hoja / Poda</option>
                        <option value="zap">Energía eléctrica</option>
                        <option value="package">Caja / Inventarios</option>
                        <option value="truck">Camión / Rutas</option>
                    </select>
                </div>
                <div class="modal-footer" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid rgba(255,255,255,0.05);display:flex;justify-content:flex-end;gap:0.5rem;">
                    <button type="button" id="btn-cat-manager-cancel" class="btn btn-outline" style="display:none;" onclick="resetCategoryForm()">Cancelar Edición</button>
                    <button type="submit" id="btn-cat-manager-submit" class="btn btn-primary">Crear Categoría</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Editar Transacción -->
    <div id="modal-editar-transaccion" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-edit-title">Editar Transacción</h3>
                <button class="modal-close-btn" onclick="closeModal('modal-editar-transaccion')"><i data-lucide="x"></i></button>
            </div>
            <form id="form-editar-transaccion" onsubmit="handleEditTransactionSubmit(event)">
                <input type="hidden" id="edit-tx-id">
                <input type="hidden" id="edit-tx-tipo">
                <div class="form-group">
                    <label class="form-label" for="edit-concepto">Concepto</label>
                    <input type="text" id="edit-concepto" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-monto">Monto ($)</label>
                    <input type="number" id="edit-monto" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit-fecha">Fecha</label>
                    <input type="date" id="edit-fecha" class="form-control" required>
                </div>
                <div class="form-group" id="edit-categoria-group" style="display:none;">
                    <label class="form-label" for="edit-categoria">Categoría</label>
                    <select id="edit-categoria" class="form-control"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-editar-transaccion')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Confirmar Eliminación -->
    <div id="modal-confirmar-eliminacion" class="modal-overlay">
        <div class="modal-card" style="max-width:450px;">
            <div class="modal-header" style="border-bottom:2px solid var(--danger);">
                <h3 class="modal-title" style="display:flex;align-items:center;gap:0.75rem;color:var(--danger);">
                    <i data-lucide="alert-triangle" style="width:24px;height:24px;"></i>
                    Confirmar Eliminación
                </h3>
                <button class="modal-close-btn" onclick="closeModal('modal-confirmar-eliminacion')"><i data-lucide="x"></i></button>
            </div>
            <div style="padding:1.5rem;display:flex;flex-direction:column;gap:1rem;">
                <p style="color:rgba(255,255,255,0.9);font-weight:500;">¿Deseas eliminar esta transacción?</p>
                <div style="background:rgba(239,68,68,0.08);border-left:3px solid var(--danger);padding:1rem;border-radius:0.375rem;display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:rgba(255,255,255,0.7);font-size:0.875rem;text-transform:uppercase;letter-spacing:0.05em;">Tipo:</span>
                        <span style="color:var(--danger);font-weight:600;" id="confirm-delete-tipo">-</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:rgba(255,255,255,0.7);font-size:0.875rem;text-transform:uppercase;letter-spacing:0.05em;">Concepto:</span>
                        <span style="color:rgba(255,255,255,0.95);font-weight:500;" id="confirm-delete-concepto">-</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:rgba(255,255,255,0.7);font-size:0.875rem;text-transform:uppercase;letter-spacing:0.05em;">Monto:</span>
                        <span style="color:var(--danger);font-weight:700;font-size:1.125rem;" id="confirm-delete-monto">$0.00</span>
                    </div>
                </div>
                <p style="color:rgba(255,255,255,0.6);font-size:0.875rem;font-style:italic;">⚠️ Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer" style="gap:0.75rem;border-top:1px solid rgba(255,255,255,0.05);">
                <input type="hidden" id="confirm-delete-tx-id" value="">
                <input type="hidden" id="confirm-delete-tx-type" value="">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-confirmar-eliminacion')">Cancelar</button>
                <button type="button" class="btn" style="background:var(--danger);color:white;border:none;padding:0.625rem 1.5rem;border-radius:0.375rem;font-weight:600;cursor:pointer;" onclick="confirmDeleteFromModal()">
                    <i data-lucide="trash-2" style="width:16px;height:16px;margin-right:0.5rem;display:inline;"></i> Sí, Eliminar
                </button>
            </div>
        </div>
    </div>
    


    <!-- Toast Notifications -->
    <div class="toast-container" id="toast-wrapper"></div>

    <!-- ══════════════════════════════════════════
         SCRIPTS
    ══════════════════════════════════════════ -->
    <script src="assets/js/vendor/lucide.min.js"></script>
    <script src="assets/js/vendor/echarts.min.js"></script>
    <script src="assets/js/vendor/xlsx.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="assets/js/charts.js?v=<?= time() ?>"></script>
    <script src="assets/js/app.js?v=2"></script>
    <script src="assets/js/transactions.js?v=<?= time() ?>"></script>

</body>
</html>
