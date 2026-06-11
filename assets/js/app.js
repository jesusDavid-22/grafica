/**
 * Orquestador de la Aplicación y Control del DOM
 */

let currentTransactions    = []; // Almacenamiento en memoria para exportación a Excel
let lastKPIs               = {}; // KPIs más recientes para el Radar
let lastTendenciaStacked   = null; // Datos de tendencia apilada para alternancia de vista
let currentTrendVista      = 'mes'; // Vista actual de la gráfica de tendencia: 'mes' | 'anio'
let autoRefreshTimer       = null; // Intervalo de auto-refresh
let countdownTimer         = null; // Intervalo de countdown visual
const AUTO_REFRESH_SECONDS = 60;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Inicializar Librerías de Visualización
    lucide.createIcons();
    AppCharts.init();

    // 2. Cargar Período Actual y Enlazar Eventos
    const periodPicker = document.getElementById('global-period-picker');
    if (periodPicker) {
        // Inicializar filtro global visual y lógica
        const defaultBtn = document.querySelector('.global-filters .filter-btn[data-filtro="mes"]');
        setFiltroGlobal('mes', defaultBtn);
        
        // Cargar datos al iniciar
        refreshDashboardData(periodPicker.value);

        // Actualizar datos al cambiar de mes manualmente
        periodPicker.addEventListener('change', (e) => {
            // Resetear filtros al modo Mes cuando el picker cambia manualmente
            currentFiltro     = 'mes';
            currentTrendVista = 'mes';
            document.querySelectorAll('.global-filters .filter-btn').forEach(b => b.classList.remove('active'));
            document.querySelector('.global-filters .filter-btn[data-filtro="mes"]')?.classList.add('active');
            document.getElementById('filtro-periodo-label').textContent = '';
            document.getElementById('btn-trend-mes')?.classList.add('active');
            document.getElementById('btn-trend-anio')?.classList.remove('active');
            refreshDashboardData(e.target.value);
        });
    }

    // 3. Cargar Categorías para el Formulario de Gastos
    loadCategoriesForSelect();

    // 4. Agregar efecto hover interactivo 3D con luz en los paneles glassmorphic
    initGlassPanelsHoverEffect();

    // 5. Ajustar dinámicamente el tamaño de los Canvas de ECharts antes y después de imprimir
    window.addEventListener('beforeprint', () => {
        AppCharts.gauge?.resize();
        AppCharts.trend?.resize();
        AppCharts.bar?.resize();
        AppCharts.donut?.resize();
        AppCharts.radar?.resize();
    });
    window.addEventListener('afterprint', () => {
        AppCharts.gauge?.resize();
        AppCharts.trend?.resize();
        AppCharts.bar?.resize();
        AppCharts.donut?.resize();
        AppCharts.radar?.resize();
        AppCharts.metaVsEjec?.resize();
        AppCharts.comparativo?.resize();
    });

    // Configurar toggle de Ahorro MCI
    const btnAcumulado = document.getElementById('btn-ahorro-acumulado');
    const btnAislado = document.getElementById('btn-ahorro-aislado');
    
    if (btnAcumulado && btnAislado) {
        btnAcumulado.addEventListener('click', (e) => {
            modoAhorroMci = 'acumulado';
            e.target.style.background = 'white';
            e.target.style.color = '#047857';
            btnAislado.style.background = 'transparent';
            btnAislado.style.color = 'white';
            refreshMciDashboard(document.getElementById('global-period-picker').value);
        });

        btnAislado.addEventListener('click', (e) => {
            modoAhorroMci = 'aislado';
            e.target.style.background = 'white';
            e.target.style.color = '#047857';
            btnAcumulado.style.background = 'transparent';
            btnAcumulado.style.color = 'white';
            refreshMciDashboard(document.getElementById('global-period-picker').value);
        });
    }

    // 6. Iniciar Auto-Refresh
    startAutoRefresh();

    // 7. Autocompletado en campos de concepto
    initAutocompletado('gasto-concepto');
    initAutocompletado('ingreso-concepto');

    // 8. Navegación (Tabs)
    initNavigation();

    // 9. Lógica de Carga Masiva (Drag & Drop)
    initBulkUpload();
});

/**
 * Formatea un número como moneda local ($0,000.00)
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2
    }).format(value);
}

/**
 * Abre un Modal específico agregando la clase activa
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        
        // Auto-enfocar el primer input del formulario
        const firstInput = modal.querySelector('input');
        if (firstInput) setTimeout(() => firstInput.focus(), 150);
    }
}

/**
 * Cierra un Modal específico removiendo la clase activa y limpiando campos
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        const form = modal.querySelector('form');
        if (form) form.reset();
    }
}

/**
 * Carga la lista de categorías del API y la inyecta en el selector HTML
 */
function loadCategoriesForSelect() {
    fetch('api.php?action=get_categorias')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const selectElement = document.getElementById('gasto-categoria');
                if (selectElement) {
                    selectElement.innerHTML = '<option value="" disabled selected>Selecciona una categoría...</option>';
                    data.categorias.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.nombre;
                        selectElement.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error cargando categorías para el select:', error);
        });
}

/**
 * Actualiza la información y gráficos de la pantalla de forma completa
 */
function refreshDashboardData(periodo = null) {
    if (!periodo) {
        periodo = document.getElementById('global-period-picker').value;
    }

    // Actualizar texto del encabezado
    const [anio, mes] = periodo.split('-');
    const fechaObj = new Date(anio, parseInt(mes) - 1, 1);
    const nombreMesCompleto = AppCharts.gauge ? translateMonthName(fechaObj.toLocaleString('es-ES', { month: 'long', year: 'numeric' })) : periodo;
    document.getElementById('dashboard-title-text').textContent = `Dashboard de Rendimiento · ${nombreMesCompleto}`;

    // Indicador visual de recarga
    const badge = document.getElementById('auto-refresh-badge');
    if (badge) badge.classList.add('refreshing');

    fetch(`api.php?action=get_dashboard_data&periodo=${periodo}&vista=${currentFiltro}&_t=${Date.now()}`)
        .then(response => {
            if (!response.ok) throw new Error('Error al conectar con la API');
            return response.json();
        })
        .then(data => {
            if (badge) badge.classList.remove('refreshing');
            if (data.success) {
                currentTransactions = data.recientes || [];
                lastKPIs = data.kpis || {};

                // KPIs principales
                renderKPIs(data.kpis);

                // Gráficas ECharts
                AppCharts.updateGauge(data.kpis.porcentaje_usado, data.kpis.semaforo);
                // Gráfica de Tendencia: Stacked Bar por Categoría (responde al filtro de período)
                if (data.tendencia_stacked) {
                    lastTendenciaStacked = data.tendencia_stacked;
                    AppCharts.updateTrendStacked(data.tendencia_stacked, currentTrendVista);
                } else {
                    AppCharts.updateTrend(data.tendencia.dias, data.tendencia.diario, data.tendencia.acumulado);
                }
                AppCharts.updateBar(data.gastos_mes);
                AppCharts.updateDonut(data.gastos_categoria);
                AppCharts.updateRadar({
                    eficiencia:         data.kpis.eficiencia,
                    porcentaje_usado:   data.kpis.porcentaje_usado,
                    ingreso_total:      data.kpis.ingreso_total,
                    proyeccion_fin_mes: data.kpis.proyeccion_fin_mes,
                    dinero_restante:    data.kpis.dinero_restante,
                    presupuesto:        data.kpis.presupuesto
                });

                renderLedgerTable(data.recientes);
                renderCategoriesProgress(data.gastos_categoria, data.kpis.presupuesto);

                // Secciones estratégicas nuevas
                if (data.resumen_ejecutivo) renderResumenEjecutivo(data.resumen_ejecutivo, data.kpis);
                if (data.alertas)           renderAlertas(data.alertas);
                if (data.comparativo_mes)   renderComparativoMes(data.comparativo_mes);
                if (data.gastos_categoria)  renderMetaVsEjec(data.gastos_categoria, data.kpis.presupuesto);

                // Cargar secciones asíncronas
                const anio = periodo.split('-')[0];
                loadDesviacion(periodo);
                loadProyeccionCat(periodo);
                renderAhorroGlobal(data.kpis);
                if (data.balance_anual) renderBalanceAnual(data.balance_anual);
                loadBitacora();
                
                // NUEVO: Cargar Dashboard MCI 2026
                refreshMciDashboard(periodo);
            }
        })
        .catch(error => {
            if (badge) badge.classList.remove('refreshing');
            console.error('Error al actualizar dashboard:', error);
            showToast('No se pudieron actualizar los datos del dashboard.', 'danger', 5000);
        });
}

/**
 * Inicia el auto-refresh cada 60 segundos con countdown visual
 */
function startAutoRefresh() {
    let secsLeft = AUTO_REFRESH_SECONDS;
    const countdownEl = document.getElementById('refresh-countdown');

    countdownTimer = setInterval(() => {
        secsLeft--;
        if (countdownEl) countdownEl.textContent = secsLeft + 's';
        if (secsLeft <= 0) {
            secsLeft = AUTO_REFRESH_SECONDS;
            refreshDashboardData();
        }
    }, 1000);
}

/**
 * Alterna la vista de la gráfica de Tendencia entre Mensual (días) y Anual (meses)
 */
function switchTrendView(vista) {
    currentTrendVista = vista;

    // Actualizar botones
    document.getElementById('btn-trend-mes')?.classList.toggle('active', vista === 'mes');
    document.getElementById('btn-trend-anio')?.classList.toggle('active', vista === 'anio');

    // Re-renderizar con los datos ya cargados (sin nueva petición)
    if (lastTendenciaStacked) {
        AppCharts.updateTrendStacked(lastTendenciaStacked, vista);
    } else {
        // Si no hay datos aún, forzar recarga
        refreshDashboardData();
    }
}

/**
 * Filtro global de Vista: Día / Semana / Mes / Trimestre / Año
 * Cambia el período consultado y actualiza todo el dashboard.
 */
let currentFiltro = 'mes'; // filtro activo global
let modoAhorroMci = 'acumulado'; // 'acumulado' o 'aislado'

function setFiltroGlobal(filtro, btn) {
    currentFiltro = filtro;
    
    // Mostrar/ocultar toggle de ahorro según vista
    const toggleContainer = document.getElementById('ahorro-toggle-container');
    if (toggleContainer) {
        if (filtro === 'mes' || filtro === 'trimestre') {
            toggleContainer.style.display = 'flex';
        } else {
            toggleContainer.style.display = 'none';
        }
    }

    // Actualizar estilos de botones
    document.querySelectorAll('.global-filters .filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const picker   = document.getElementById('global-period-picker');
    const labelEl  = document.getElementById('filtro-periodo-label');
    const hoy      = new Date();
    const anio     = hoy.getFullYear();
    const mes      = String(hoy.getMonth() + 1).padStart(2, '0');
    const dia      = String(hoy.getDate()).padStart(2, '0');
    const periodoMes = `${anio}-${mes}`; // YYYY-MM

    // Calcular semana actual (lunes → domingo)
    const lunes = new Date(hoy);
    lunes.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
    const domingo = new Date(lunes);
    domingo.setDate(lunes.getDate() + 6);
    const fmtDate = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;

    // Trimestre actual
    const trimestreNum = Math.ceil((hoy.getMonth() + 1) / 3);
    const trimestreNombres = ['', 'Ene-Mar', 'Abr-Jun', 'Jul-Sep', 'Oct-Dic'];

    switch (filtro) {
        case 'dia':
            picker.value = periodoMes;
            if (labelEl) labelEl.textContent = `Hoy: ${dia}/${mes}/${anio}`;
            // Vista mensual en la gráfica, pero el día de hoy se destacará
            currentTrendVista = 'mes';
            document.getElementById('btn-trend-mes')?.classList.add('active');
            document.getElementById('btn-trend-anio')?.classList.remove('active');
            refreshDashboardData(periodoMes);
            // Mostrar aviso
            showToast(`📅 Mostrando mes actual — día ${dia} resaltado en tendencia`, 'info', 3000);
            break;

        case 'semana':
            picker.value = periodoMes;
            if (labelEl) labelEl.textContent = `Semana: ${fmtDate(lunes).substring(5)} → ${fmtDate(domingo).substring(5)}`;
            currentTrendVista = 'mes';
            document.getElementById('btn-trend-mes')?.classList.add('active');
            document.getElementById('btn-trend-anio')?.classList.remove('active');
            refreshDashboardData(periodoMes);
            showToast(`📅 Semana ${fmtDate(lunes)} al ${fmtDate(domingo)}`, 'info', 3500);
            break;

        case 'mes':
            // Usar el valor actual del picker (ya es mensual)
            if (labelEl) labelEl.textContent = '';
            currentTrendVista = 'mes';
            document.getElementById('btn-trend-mes')?.classList.add('active');
            document.getElementById('btn-trend-anio')?.classList.remove('active');
            refreshDashboardData(picker.value);
            break;

        case 'trimestre':
            // Forzar al primer mes del trimestre actual del picker
            const pickerMes    = parseInt((picker.value || periodoMes).split('-')[1]) || (hoy.getMonth() + 1);
            const pickerAnio   = parseInt((picker.value || periodoMes).split('-')[0]) || anio;
            const trimActual   = Math.ceil(pickerMes / 3);
            const primerMesTrim = String((trimActual - 1) * 3 + 1).padStart(2, '0');
            const periodoTrim  = `${pickerAnio}-${primerMesTrim}`;

            picker.value = periodoTrim;
            if (labelEl) labelEl.textContent = `Q${trimActual} ${anio}: ${trimestreNombres[trimActual]}`;
            currentTrendVista = 'mes';
            document.getElementById('btn-trend-mes')?.classList.add('active');
            document.getElementById('btn-trend-anio')?.classList.remove('active');
            refreshDashboardData(periodoTrim);
            showToast(`📊 Trimestre ${trimActual} — ${trimestreNombres[trimActual]} ${pickerAnio}`, 'info', 3000);
            break;

        case 'anio':
            // En vista anual el picker sigue siendo YYYY-MM (usamos el año del picker)
            const pickerAnioStr = (picker.value || periodoMes).split('-')[0];
            // Poner enero del año como punto de referencia para la API
            const periodoAnio = `${pickerAnioStr}-01`;
            picker.value = periodoAnio;
            if (labelEl) labelEl.textContent = `Año completo ${pickerAnioStr}`;
            // Cambiar la gráfica de tendencia a vista anual automáticamente
            currentTrendVista = 'anio';
            document.getElementById('btn-trend-mes')?.classList.remove('active');
            document.getElementById('btn-trend-anio')?.classList.add('active');
            refreshDashboardData(periodoAnio);
            showToast(`📈 Vista anual ${pickerAnioStr} — tendencia por meses`, 'info', 3000);
            break;
    }
}

/**
 * Renderiza los valores de las tarjetas principales (KPI Cards)
 */
function renderKPIs(kpis) {
    // Presupuesto
    document.getElementById('kpi-val-presupuesto').textContent = formatCurrency(kpis.presupuesto);
    
    // Actualizar footer del presupuesto
    const kpiFootPres = document.querySelector('#card-presupuesto .kpi-footer');
    if (kpiFootPres) {
        if (kpis.presupuesto_anual > 0) {
            kpiFootPres.innerHTML = `<span>Anual: <b>${formatCurrency(kpis.presupuesto_anual)}</b> (/12 prorrateado)</span>`;
        } else {
            kpiFootPres.innerHTML = `<span>Sin presupuesto anual fijado</span>`;
        }
    }
    
    // Gasto Ejecutado
    document.getElementById('kpi-val-gastos').textContent = formatCurrency(kpis.gasto_total);
    const diffPorcentajeText = kpis.presupuesto > 0 
        ? `${kpis.porcentaje_usado}% del límite mensual`
        : 'Sin presupuesto anual';
    document.getElementById('kpi-foot-gastos').innerHTML = `<span>${diffPorcentajeText}</span>`;

    // Fondos Disponibles
    const dineroRestanteText = document.getElementById('kpi-val-restante');
    dineroRestanteText.textContent = formatCurrency(kpis.dinero_restante);
    
    // Desviación Presupuestal
    if (kpis.dinero_restante < 0) {
        dineroRestanteText.style.color = '#FCA5A5'; // Resaltar déficit en rojo suave
    } else {
        dineroRestanteText.style.color = ''; // Color por defecto
    }
    
    // Semáforo de Consumo
    const badge = document.getElementById('traffic-light-badge');
    const badgeText = document.getElementById('traffic-light-text');
    
    // Reset de clases
    badge.className = 'traffic-light-badge';
    
    if (kpis.semaforo === 'rojo') {
        badge.classList.add('badge-rojo');
        badgeText.textContent = 'SOBRECOSTO';
        showToast('Presupuesto mensual superado', 'warning', 5000);
    } else if (kpis.semaforo === 'amarillo') {
        badge.classList.add('badge-amarillo');
        badgeText.textContent = 'ALERTA LÍMITE';
    } else {
        badge.classList.add('badge-verde');
        badgeText.textContent = 'ESTABLE';
    }

    // Proyección Fin de Mes (Forecast)
    const valProyeccion = document.getElementById('kpi-val-proyeccion');
    const footProyeccion = document.getElementById('kpi-foot-proyeccion');
    const iconProyeccion = document.getElementById('kpi-icon-proyeccion');
    
    if (valProyeccion && footProyeccion) {
        valProyeccion.textContent = formatCurrency(kpis.proyeccion_fin_mes);
        const promDiarioStr = formatCurrency(kpis.promedio_diario);
        
        if (kpis.riesgo_sobrecosto) {
            valProyeccion.style.color = '#FCA5A5';
            if (iconProyeccion) { iconProyeccion.style.background = 'rgba(239,68,68,0.15)'; iconProyeccion.style.color = 'var(--danger)'; }
            footProyeccion.innerHTML = `<span style="color:var(--danger);font-weight:600;"><i data-lucide="alert-triangle" style="width:12px;display:inline;"></i> PROYECCIÓN EXCEDIDA (Prom: ${promDiarioStr}/día)</span>`;
        } else {
            valProyeccion.style.color = '#A7F3D0';
            if (iconProyeccion) { iconProyeccion.style.background = 'rgba(16,185,129,0.15)'; iconProyeccion.style.color = 'var(--success)'; }
            footProyeccion.innerHTML = `<span style="color:var(--success);"><i data-lucide="check-circle" style="width:12px;display:inline;"></i> Dentro del límite (Prom: ${promDiarioStr}/día)</span>`;
        }
    }

    // KPI: Ingresos del período
    const valIngresos = document.getElementById('kpi-val-ingresos');
    if (valIngresos) valIngresos.textContent = formatCurrency(kpis.ingreso_total);

    lucide.createIcons();
}

/**
 * Renderiza el historial ledger de transacciones recientes
 */
function renderLedgerTable(transacciones) {
    const tbody = document.getElementById('ledger-tbody');
    const emptyState = document.getElementById('ledger-empty-state');
    
    tbody.innerHTML = '';
    
    if (!transacciones || transacciones.length === 0) {
        emptyState.style.display = 'flex';
        return;
    }
    
    emptyState.style.display = 'none';
    
    transacciones.forEach(tx => {
        const tr = document.createElement('tr');
        tr.setAttribute('data-tx-id', tx.id);
        tr.setAttribute('data-tx-type', tx.tipo);
        
        // Concepto e Icono
        const iconColor = tx.tipo === 'ingreso' ? 'var(--success)' : tx.color;
        const iconBg = tx.tipo === 'ingreso' ? 'var(--success-glow)' : 'rgba(255, 255, 255, 0.05)';
        
        // Formatear Fecha
        const dateParts = tx.fecha.split('-');
        const dateObj = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
        const formattedDate = dateObj.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' });

        tr.innerHTML = `
            <td>
                <div class="tx-concept-cell">
                    <div class="tx-icon-circle" style="background: ${iconBg}; color: ${iconColor}; border: 1px solid rgba(255,255,255,0.02)">
                        <i data-lucide="${tx.icono}"></i>
                    </div>
                    <div>
                        <p style="font-weight:600; color:var(--text-primary);">${tx.concepto}</p>
                        <span style="font-size:0.75rem; color:var(--text-muted);">${tx.tipo.toUpperCase()}</span>
                    </div>
                </div>
            </td>
            <td style="color: var(--text-secondary); font-family: Outfit; font-weight: 500;">
                ${formattedDate}
            </td>
            <td>
                <span class="tx-category-badge" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); color: ${tx.color};">
                    ${tx.categoria}
                </span>
            </td>
            <td class="tx-amount ${tx.tipo}">
                ${tx.tipo === 'ingreso' ? '+' : '-'}${formatCurrency(tx.monto)}
            </td>
            <td style="text-align: center; padding: 0.95rem 0.5rem;">
                <div class="tx-actions" style="display: flex; gap: 0.35rem; justify-content: center;">
                    <button type="button" class="tx-action-btn edit" data-tx-id="${tx.id}" data-tx-type="${tx.tipo}" data-cat-id="${tx.categoria_id||''}" data-concepto="${(tx.concepto||'').replace(/"/g,'&quot;')}" data-monto="${tx.monto}" data-fecha="${tx.fecha}" title="Editar" style="background: rgba(99,102,241,0.15); color: var(--primary); border: none; padding: 0.4rem 0.6rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                        <i data-lucide="edit-2" style="width: 14px; height: 14px;"></i>
                    </button>
                    <button type="button" class="tx-action-btn delete" data-tx-id="${tx.id}" data-tx-type="${tx.tipo}" title="Eliminar" style="background: rgba(239,68,68,0.15); color: var(--danger); border: none; padding: 0.4rem 0.6rem; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                        <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(tr);
    });
    
    lucide.createIcons();
    
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const vista = e.target.dataset.vista;
            if (vista) {
                setFiltroGlobal(vista, e.target);
                refreshDashboardData();
            }
        });
    });

    // Agregar event listeners a los botones de editar y eliminar
    document.querySelectorAll('.tx-action-btn.edit').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const txId    = this.getAttribute('data-tx-id');
            const txType  = this.getAttribute('data-tx-type');
            const catId   = this.getAttribute('data-cat-id');
            const concepto= this.getAttribute('data-concepto');
            const monto   = this.getAttribute('data-monto');
            const fecha   = this.getAttribute('data-fecha');
            abrirEditarTx(parseInt(txId), txType, concepto, parseFloat(monto), fecha, catId || null);
        });
    });
    
    document.querySelectorAll('.tx-action-btn.delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const txId = this.getAttribute('data-tx-id');
            const txType = this.getAttribute('data-tx-type');
            deleteTransactionConfirm(parseInt(txId), txType);
        });
    });
}

/**
 * Renderiza la distribución de categorías con barras de progreso fluidas
 */
function renderCategoriesProgress(categorias, presupuestoTotal) {
    const container = document.getElementById('categories-progress-container');
    const emptyState = document.getElementById('categories-empty-state');
    
    container.innerHTML = '';
    
    // Filtrar categorías que tienen algún gasto
    const categoriasConGastos = categorias.filter(c => c.total > 0);
    
    if (categoriasConGastos.length === 0) {
        emptyState.style.display = 'flex';
        return;
    }
    
    emptyState.style.display = 'none';
    
    // Obtener el total gastado en el mes
    const totalGastoMes = categorias.reduce((acc, current) => acc + (parseFloat(current.total) || 0), 0);
    
    categoriasConGastos.forEach(cat => {
        const item = document.createElement('div');
        item.className = 'cat-progress-item';
        
        // Calcular porcentaje con respecto al total gastado en el mes
        const porcentajeDelGasto = totalGastoMes > 0 ? (cat.total / totalGastoMes) * 100 : 0;
        
        item.innerHTML = `
            <div class="cat-progress-info">
                <span class="cat-progress-name" style="color: ${cat.color}">
                    <i data-lucide="${cat.icono}"></i> ${cat.nombre}
                </span>
                <span class="cat-progress-amount">
                    ${formatCurrency(cat.total)} <span style="color:var(--text-muted); font-size:0.75rem;">(${porcentajeDelGasto.toFixed(0)}%)</span>
                </span>
            </div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="background-color: ${cat.color}; width: 0%"></div>
            </div>
        `;
        
        container.appendChild(item);
        
        // Trigger animación fluida de llenado de barra de progreso
        setTimeout(() => {
            const fill = item.querySelector('.progress-bar-fill');
            if (fill) fill.style.width = `${porcentajeDelGasto}%`;
        }, 100);
    });
    
    lucide.createIcons();
}

/**
 * Gestiona el envío de formularios de forma unificada vía Fetch AJAX
 */
function handleFormSubmit(event, action) {
    event.preventDefault();
    
    let url = 'api.php';
    let payload = { action: action };
    let modalId = '';
    let isValid = true;
    
    // Determinar parámetros según acción
    if (action === 'add_gasto') {
        modalId = 'modal-gasto';
        const concepto = document.getElementById('gasto-concepto').value.trim();
        const monto = parseFloat(document.getElementById('gasto-monto').value);
        const fecha = document.getElementById('gasto-fecha').value;
        const categoria_id = document.getElementById('gasto-categoria').value;
        
        // Validar campos locales antes de enviar
        if (!concepto || concepto.length < 3) {
            showToast('El concepto debe tener al menos 3 caracteres.', 'warning', 3500);
            isValid = false;
        }
        if (!monto || monto <= 0) {
            showToast('El monto debe ser mayor a 0.', 'warning', 3500);
            isValid = false;
        }
        if (!fecha) {
            showToast('Debe seleccionar una fecha válida.', 'warning', 3500);
            isValid = false;
        }
        if (!categoria_id) {
            showToast('Debe seleccionar una categoría.', 'warning', 3500);
            isValid = false;
        }
        
        if (!isValid) return;
        
        payload.concepto = concepto;
        payload.monto = monto;
        payload.fecha = fecha;
        payload.categoria_id = categoria_id;
        
    } else if (action === 'add_ingreso') {
        modalId = 'modal-ingreso';
        const concepto = document.getElementById('ingreso-concepto').value.trim();
        const monto = parseFloat(document.getElementById('ingreso-monto').value);
        const fecha = document.getElementById('ingreso-fecha').value;
        
        if (!concepto || concepto.length < 3) {
            showToast('El concepto debe tener al menos 3 caracteres.', 'warning', 3500);
            isValid = false;
        }
        if (!monto || monto <= 0) {
            showToast('El monto debe ser mayor a 0.', 'warning', 3500);
            isValid = false;
        }
        if (!fecha) {
            showToast('Debe seleccionar una fecha válida.', 'warning', 3500);
            isValid = false;
        }
        
        if (!isValid) return;
        
        payload.concepto = concepto;
        payload.monto = monto;
        payload.fecha = fecha;
        
    } else if (action === 'set_presupuesto') {
        modalId = 'modal-presupuesto';
        const total = parseFloat(document.getElementById('presupuesto-total').value);
        const periodo = document.getElementById('presupuesto-periodo').value;
        
        if (!total || total <= 0) {
            showToast('El presupuesto debe ser mayor a 0.', 'warning', 3500);
            isValid = false;
        }
        if (!periodo) {
            showToast('Debe seleccionar un año válido.', 'warning', 3500);
            isValid = false;
        }
        
        if (!isValid) return;
        
        payload.total = total;
        payload.periodo = periodo;
    }
    
    // Enviar peticiones POST
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success', 3500);
            closeModal(modalId);
            
            // Si fijamos el presupuesto, re-ajustar el picker de fecha al periodo del año correspondiente
            if (action === 'set_presupuesto') {
                const picker = document.getElementById('global-period-picker');
                const yearSelected = payload.periodo; // Ej: '2026'
                const currentMonth = picker.value.split('-')[1] || '01';
                picker.value = `${yearSelected}-${currentMonth}`;
            }
            
            // Refrescar todos los datos del dashboard instantáneamente
            refreshDashboardData();
        } else {
            showToast(data.message || 'Ocurrió un error al procesar el registro.', 'danger', 4500);
        }
    })
    .catch(error => {
        console.error('Error al guardar datos:', error);
        showToast('Error de conexión con la base de datos local.', 'danger', 4500);
    });
}

/**
 * Muestra una notificación Toast flotante premium mejorada
 * @param {string} message Mensaje de la notificación
 * @param {string} type Tipo de notificación ('success', 'danger', 'warning', 'info')
 * @param {number} duration Duración en ms antes de desaparecer (0 = manual)
 */
function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toast-wrapper');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    // Mapeo de iconos por tipo
    const iconMap = {
        'success': 'check-circle',
        'danger': 'alert-triangle',
        'warning': 'alert-circle',
        'info': 'info'
    };
    
    const titleMap = {
        'success': '¡Éxito!',
        'danger': 'Error',
        'warning': 'Advertencia',
        'info': 'Información'
    };
    
    const iconName = iconMap[type] || 'info';
    const titleText = titleMap[type] || 'Notificación';
    
    // HTML mejorado con estructura
    toast.innerHTML = `
        <div class="toast-header">
            <div class="toast-icon" data-lucide="${iconName}"></div>
            <div class="toast-title">${titleText}</div>
        </div>
        <div class="toast-content">
            <p class="toast-message">${message}</p>
        </div>
        <button type="button" class="toast-close" onclick="this.closest('.toast').removeToast()">
            <i data-lucide="x"></i>
        </button>
        <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
    `;
    
    // Agregar método para remover con animación
    toast.removeToast = function() {
        this.classList.add('hide');
        setTimeout(() => this.remove(), 450);
    };
    
    container.appendChild(toast);
    lucide.createIcons();
    
    // Trigger animación de entrada
    setTimeout(() => toast.classList.add('show'), 50);
    
    // Auto-remover si duration > 0
    if (duration > 0) {
        setTimeout(() => {
            if (toast.parentElement) {
                toast.removeToast();
            }
        }, duration);
    }
}

/**
 * Traduce el nombre del mes de formato nativo JS (inglés/sistema) a español formal
 */
function translateMonthName(monthYearStr) {
    const meses = {
        'january': 'Enero', 'february': 'Febrero', 'march': 'Marzo', 'april': 'Abril',
        'may': 'Mayo', 'june': 'Junio', 'july': 'Julio', 'august': 'Agosto',
        'september': 'Septiembre', 'october': 'Octubre', 'november': 'Noviembre', 'december': 'Diciembre'
    };
    
    let text = monthYearStr.toLowerCase();
    for (let key in meses) {
        if (text.includes(key)) {
            text = text.replace(key, meses[key]);
            break;
        }
    }
    // Capitalizar primera letra
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/**
 * Agrega el efecto hover 3D con luz focal en los paneles de cristal
 */
function initGlassPanelsHoverEffect() {
    document.querySelectorAll('.glass-panel').forEach(panel => {
        panel.addEventListener('mousemove', e => {
            const rect = panel.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            panel.style.setProperty('--x', `${x}px`);
            panel.style.setProperty('--y', `${y}px`);
        });
    });
}

/**
 * Carga los datos específicos para el tablero MCI 2026
 */
function refreshMciDashboard(periodo) {
    fetch(`api.php?action=get_mci_dashboard&periodo=${periodo}&vista=${currentFiltro}&modo=${modoAhorroMci}&_t=${Date.now()}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderMciKpis(data.mci_desviacion);
                renderMciTables(data.mci_desviacion);
                renderMciPivotTable(data.mci_matriz);
                
                // Actualizar gráficas ECharts
                if (AppCharts.mciAvance) AppCharts.updateMciAvance(data.mci_desviacion.detalles, data.mci_desviacion.totales);
                if (AppCharts.mciBarHorizontal) AppCharts.updateMciBarHorizontal(data.mci_desviacion.detalles);
            }
        })
        .catch(e => console.error("Error loading MCI Dashboard:", e));
}

function renderMciKpis(desviacion) {
    const kpiAvance = document.getElementById('mci-kpi-avance');
    const kpiAhorro = document.getElementById('mci-kpi-ahorro');
    
    if (!desviacion || !desviacion.totales) return;
    
    const t = desviacion.totales;
    // Avance real
    const pctAvance = t.mci_anual > 0 ? (t.ejecutado / t.mci_anual) * 100 : 0;
    if (kpiAvance) kpiAvance.textContent = pctAvance.toFixed(1) + '%';
    
    // Ahorro $ = Presupuesto del periodo (estimado) - Gastado del periodo (ejecutado)
    const ahorro = Math.round(t.estimado - t.ejecutado);
    if (kpiAhorro) kpiAhorro.textContent = '$ ' + Math.max(0, ahorro).toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0});
}

function renderMciTables(desviacion) {
    const tbodyCump = document.querySelector('#mci-table-cumplimiento tbody');
    const tbodyDesv = document.querySelector('#mci-table-desviacion tbody');
    
    if (!tbodyCump || !tbodyDesv || !desviacion.detalles) return;
    
    tbodyCump.innerHTML = '';
    tbodyDesv.innerHTML = '';
    
    // Nombres de los meses fijos o basados en desviacion.mes_analizado
    const mesNombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const mesName = mesNombres[(desviacion.mes_analizado || 5) - 1].toUpperCase();
    
    // Tabla de Cumplimiento (NOMINA, RUTAS, etc)
    desviacion.detalles.forEach(d => {
        const pct = d.mci_anual > 0 ? (d.ejecutado / d.mci_anual) * 100 : 0;
        tbodyCump.innerHTML += `
            <tr>
                <td style="font-weight:600;">${d.nombre.toUpperCase()}</td>
                <td style="text-align:right; white-space:nowrap;">$ ${d.ejecutado.toLocaleString('es-MX', {minimumFractionDigits:0})}</td>
                <td style="text-align:right; white-space:nowrap;">$ ${d.mci_anual.toLocaleString('es-MX', {minimumFractionDigits:0})}</td>
                <td style="text-align:right; white-space:nowrap;">${pct.toFixed(0)}%</td>
            </tr>
        `;
    });
    
    // Fila de TOTALES Cumplimiento
    const t = desviacion.totales;
    const tPct = t.mci_anual > 0 ? (t.ejecutado / t.mci_anual) * 100 : 0;
    tbodyCump.innerHTML += `
        <tr style="background:rgba(255,255,255,0.05); font-weight:bold;">
            <td>TOTAL</td>
            <td style="text-align:right; white-space:nowrap;">$ ${t.ejecutado.toLocaleString('es-MX', {minimumFractionDigits:0})}</td>
            <td style="text-align:right; white-space:nowrap;">$ ${t.mci_anual.toLocaleString('es-MX', {minimumFractionDigits:0})}</td>
            <td style="text-align:right; white-space:nowrap;">${tPct.toFixed(0)}%</td>
        </tr>
    `;
    
    // Tabla de Desviación
    desviacion.detalles.forEach(d => {
        const semaforoHtml = d.estado === 'verde' ? '<i data-lucide="check-circle" style="color:#10B981;width:14px;height:14px;"></i>' : '<i data-lucide="alert-circle" style="color:#EF4444;width:14px;height:14px;"></i>';
        tbodyDesv.innerHTML += `
            <tr>
                <td style="font-weight:600; color:var(--text-primary);"><div style="display:flex;align-items:center;gap:0.4rem;">${semaforoHtml} ${d.nombre.toUpperCase()}</div></td>
                <td style="text-align:right; white-space:nowrap;">$ ${d.mci_anual.toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
                <td style="text-align:right; white-space:nowrap;">$ ${d.estimado.toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
                <td style="text-align:right; white-space:nowrap;">$ ${d.ejecutado.toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
                <td style="text-align:right; white-space:nowrap;">$ ${Math.abs(d.diferencia).toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
            </tr>
        `;
    });
    
    // Fila de TOTALES Desviación
    const iconTot = t.diferencia < 0 ? '<i data-lucide="arrow-down" style="color:var(--success);width:14px;height:14px;"></i>' : 
                                    '<i data-lucide="arrow-up" style="color:var(--danger);width:14px;height:14px;"></i>';
    tbodyDesv.innerHTML += `
        <tr style="background:rgba(255,255,255,0.05); font-weight:800; font-size:0.9rem; color:var(--text-primary);">
            <td>TOTAL ACUMULADO ${mesName}</td>
            <td style="text-align:right; white-space:nowrap;">$ ${t.mci_anual.toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
            <td style="text-align:right; white-space:nowrap;">$ ${t.estimado.toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
            <td style="text-align:right; white-space:nowrap;">$ ${t.ejecutado.toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
            <td style="text-align:right; white-space:nowrap;">${iconTot} $ ${Math.abs(t.diferencia).toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0})}</td>
        </tr>
    `;
}

function renderMciPivotTable(matriz) {
    const theadCats = document.getElementById('mci-pivot-cat-headers');
    const tbody = document.getElementById('mci-pivot-tbody');
    
    if (!theadCats || !tbody || !matriz) return;
    
    // Render headers
    theadCats.outerHTML = matriz.categorias.map(c => `<th style="text-align:right;">${c.nombre.toUpperCase()}</th>`).join('') + '<th style="text-align:right;">TOTAL</th>';
    
    // Render body
    tbody.innerHTML = '';
    const anio = document.getElementById('global-period-picker').value.split('-')[0];
    
    let totalesColumna = {};
    matriz.categorias.forEach(c => totalesColumna[c.id] = 0);
    let granTotal = 0;
    
    matriz.filas.forEach(f => {
        let colsHTML = '';
        matriz.categorias.forEach(c => {
            const val = f.categorias[c.id] || 0;
            totalesColumna[c.id] += val;
            colsHTML += `<td style="text-align:right;">$ ${val > 0 ? val.toLocaleString('es-MX', {minimumFractionDigits:0}) : '-'}</td>`;
        });
        granTotal += f.total_mes;
        
        tbody.innerHTML += `
            <tr>
                <td>${anio}</td>
                <td style="font-weight:600;">${f.mes}</td>
                ${colsHTML}
                <td style="text-align:right; font-weight:bold;">$ ${f.total_mes > 0 ? f.total_mes.toLocaleString('es-MX', {minimumFractionDigits:0}) : '-'}</td>
            </tr>
        `;
    });
    
    // Fila Total Acumulado
    let colsTotHTML = '';
    matriz.categorias.forEach(c => {
        colsTotHTML += `<td style="text-align:right;">$ ${totalesColumna[c.id].toLocaleString('es-MX', {minimumFractionDigits:0})}</td>`;
    });
    tbody.innerHTML += `
        <tr style="background:rgba(255,255,255,0.08); font-weight:bold;">
            <td colspan="2">${anio} ACUMULADO</td>
            ${colsTotHTML}
            <td style="text-align:right;">$ ${granTotal.toLocaleString('es-MX', {minimumFractionDigits:0})}</td>
        </tr>
    `;
}

/**
 * Abre el modal del Administrador de Categorías
 */
function openCategoriesManager() {
    openModal('modal-gestionar-categorias');
    loadCategoriesManagerList();
}

/**
 * Carga y renderiza la lista de categorías dentro del modal administrador
 */
function loadCategoriesManagerList() {
    const listContainer = document.getElementById('cat-manager-list');
    if (!listContainer) return;
    
    listContainer.innerHTML = '<p style="color:var(--text-muted); font-size:0.85rem; padding:1rem; text-align:center;">Cargando categorías...</p>';
    
    fetch('api.php?action=get_categorias')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                listContainer.innerHTML = '';
                if (data.categorias.length === 0) {
                    listContainer.innerHTML = '<p style="color:var(--text-muted); font-size:0.85rem; padding:1rem; text-align:center;">No hay categorías registradas.</p>';
                    return;
                }
                
                data.categorias.forEach(cat => {
                    const item = document.createElement('div');
                    item.style.display = 'flex';
                    item.style.justify = 'space-between';
                    item.style.alignItems = 'center';
                    item.style.padding = '0.5rem 0.75rem';
                    item.style.background = 'rgba(255, 255, 255, 0.02)';
                    item.style.border = '1px solid rgba(255, 255, 255, 0.05)';
                    item.style.borderRadius = '10px';
                    
                    item.innerHTML = `
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div style="width:24px; height:24px; border-radius:6px; background:${cat.color}22; color:${cat.color}; display:flex; align-items:center; justify-content:center;">
                                <i data-lucide="${cat.icono}" style="width:14px; height:14px;"></i>
                            </div>
                            <span style="font-weight:600; font-size:0.85rem; color:var(--text-primary);">${cat.nombre}</span>
                        </div>
                        <button type="button" class="btn btn-outline" style="padding:0.3rem 0.6rem; font-size:0.75rem; display:inline-flex; gap:0.25rem; align-items:center;" 
                                onclick="setupEditCategory(${cat.id}, '${cat.nombre.replace(/'/g, "\\'")}', '${cat.color}', '${cat.icono}')">
                            <i data-lucide="pencil" style="width:10px; height:10px;"></i> Editar
                        </button>
                    `;
                    listContainer.appendChild(item);
                });
                
                lucide.createIcons();
            }
        })
        .catch(error => {
            console.error('Error cargando lista del administrador:', error);
            listContainer.innerHTML = '<p style="color:var(--danger); font-size:0.85rem; padding:1rem; text-align:center;">Error al cargar las categorías.</p>';
        });
}

/**
 * Prepara el formulario para editar una categoría seleccionada
 */
function setupEditCategory(id, nombre, color, icono) {
    document.getElementById('cat-manager-id').value = id;
    document.getElementById('cat-manager-nombre').value = nombre;
    document.getElementById('cat-manager-color').value = color;
    document.getElementById('cat-manager-icono').value = icono;
    
    document.getElementById('cat-manager-form-title').textContent = `Editar Categoría: ${nombre}`;
    document.getElementById('cat-manager-form-title').style.color = '#F59E0B'; // Color warning para edición
    
    document.getElementById('btn-cat-manager-cancel').style.display = 'inline-flex';
    document.getElementById('btn-cat-manager-submit').textContent = 'Guardar Cambios';
    document.getElementById('btn-cat-manager-submit').className = 'btn btn-primary';
}

/**
 * Reinicia el formulario de categorías al estado de creación
 */
function resetCategoryForm() {
    document.getElementById('cat-manager-id').value = '';
    const form = document.getElementById('form-manager-categoria');
    if (form) form.reset();
    
    document.getElementById('cat-manager-form-title').textContent = 'Crear Nueva Categoría';
    document.getElementById('cat-manager-form-title').style.color = 'var(--primary)';
    
    document.getElementById('btn-cat-manager-cancel').style.display = 'none';
    document.getElementById('btn-cat-manager-submit').textContent = 'Crear Categoría';
    document.getElementById('btn-cat-manager-submit').className = 'btn btn-primary';
}

/**
 * Procesa el envío del formulario de administración (Agregar o Editar Categoría)
 */
function handleCategoryManagerSubmit(event) {
    event.preventDefault();
    
    const id = document.getElementById('cat-manager-id').value;
    const nombre = document.getElementById('cat-manager-nombre').value.trim();
    const color = document.getElementById('cat-manager-color').value;
    const icono = document.getElementById('cat-manager-icono').value;
    
    const action = id ? 'edit_categoria' : 'add_categoria';
    
    const payload = {
        action: action,
        nombre: nombre,
        color: color,
        icono: icono
    };
    
    if (id) {
        payload.id = id;
    }
    
    fetch('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success', 3500);
            
            // Recargar lista del gestor
            loadCategoriesManagerList();
            
            // Recargar selects en formularios
            loadCategoriesForSelect();
            
            // Reiniciar formulario
            resetCategoryForm();
            
            // Refrescar Dashboard general (gráficas, barras de categorías, etc)
            refreshDashboardData();
        } else {
            showToast(data.message || 'Error al procesar la categoría.', 'danger', 4000);
        }
    })
    .catch(error => {
        console.error('Error al guardar categoría:', error);
        showToast('Error de conexión con el backend local.', 'danger', 4000);
    });
}

/**
 * Exporta el reporte financiero a Excel con formato profesional
 */
/**
 * Exporta el dashboard actual como PDF ejecutivo usando la API de impresión del navegador
 */
function exportarPDF() {
    showToast('Preparando PDF ejecutivo...', 'info', 2000);

    // Guardar scroll position
    const scrollY = window.scrollY;

    // Trigger print dialog (relies on @media print CSS)
    setTimeout(() => {
        window.print();
    }, 300);
}

function exportLedgerToCSV() {

    if (currentTransactions.length === 0) {
        showToast('No hay transacciones en este mes para exportar.', 'warning', 3500);
        return;
    }

    try {
        const periodoAct = document.getElementById('global-period-picker').value;

        // ── HOJA 1: RESUMEN (usando lastKPIs en lugar de DOM) ──────────────
        const kpis        = lastKPIs || {};
        const presupuesto = kpis.presupuesto        || 0;
        const gastos      = kpis.gasto_total        || 0;
        const ingresos    = kpis.ingreso_total      || 0;
        const saldo       = kpis.dinero_restante    || 0;
        const eficiencia  = kpis.eficiencia         || 0;
        const proyeccion  = kpis.proyeccion_fin_mes || 0;
        const porcentaje  = kpis.porcentaje_usado   || 0;
        const semaforo    = kpis.semaforo           || 'verde';

        const resumenData = [
            ['REPORTE FINANCIERO DASHBOARD', periodoAct],
            ['Generado el', new Date().toLocaleString('es-MX')],
            [],
            ['INDICADOR', 'VALOR'],
            ['Presupuesto Mensual Asignado', presupuesto],
            ['Total Gastos del Mes',         gastos],
            ['Total Ingresos del Mes',        ingresos],
            ['Fondo Disponible',             saldo],
            ['Proyección Fin de Mes',        proyeccion],
            ['% Presupuesto Utilizado',      porcentaje + '%'],
            ['Eficiencia (Ing/Gas)',         eficiencia + 'x'],
            ['Estado Semáforo',             semaforo.toUpperCase()]
        ];

        // ── HOJA 2: TRANSACCIONES ─────────────────────────────────────────
        const transaccionesData = [
            ['DETALLE DE TRANSACCIONES', periodoAct, '', '', ''],
            [],
            ['Fecha', 'Tipo', 'Concepto', 'Categoría', 'Monto']
        ];

        let totalGastos   = 0;
        let totalIngresos = 0;

        currentTransactions.forEach(tx => {
            const monto = parseFloat(tx.monto);
            if (tx.tipo === 'gasto') {
                totalGastos += monto;
                transaccionesData.push([tx.fecha, 'GASTO',   tx.concepto, tx.categoria || '', monto * -1]);
            } else {
                totalIngresos += monto;
                transaccionesData.push([tx.fecha, 'INGRESO', tx.concepto, 'Ingreso',           monto]);
            }
        });

        transaccionesData.push([]);
        transaccionesData.push(['', '', '', 'Total Gastos:',   totalGastos   * -1]);
        transaccionesData.push(['', '', '', 'Total Ingresos:', totalIngresos]);
        transaccionesData.push(['', '', '', 'Balance Neto:',   totalIngresos - totalGastos]);

        // ── WORKBOOK ──────────────────────────────────────────────────────
        const wb  = XLSX.utils.book_new();
        const ws1 = XLSX.utils.aoa_to_sheet(resumenData);
        const ws2 = XLSX.utils.aoa_to_sheet(transaccionesData);

        ws1['!cols'] = [{ wch: 30 }, { wch: 22 }];
        ws2['!cols'] = [{ wch: 12 }, { wch: 10 }, { wch: 32 }, { wch: 20 }, { wch: 16 }];

        XLSX.utils.book_append_sheet(wb, ws1, 'Resumen KPIs');
        XLSX.utils.book_append_sheet(wb, ws2, 'Transacciones');

        const fileName = `FinancialDash_${periodoAct}_${Date.now()}.xlsx`;
        XLSX.writeFile(wb, fileName);

        showToast('📊 Reporte Excel descargado correctamente.', 'success', 4000);

    } catch (error) {
        console.error('Error al exportar Excel:', error);
        showToast('Error al generar el reporte Excel. Intenta nuevamente.', 'danger', 4000);
    }
}

// ============================================================================
// FUNCIONES DE LAS NUEVAS SECCIONES ESTRATÉGICAS
// ============================================================================

function renderResumenEjecutivo(resumen, kpis) {
    if (!resumen) return;
    
    const rePctEjecutado = document.getElementById('re-pct-ejecutado');
    if (rePctEjecutado) rePctEjecutado.textContent = formatCurrency(kpis.gasto_total) + ' (' + resumen.pct_ejecutado.toFixed(1) + '%)';
    
    const reFondo = document.getElementById('re-fondo');
    if (reFondo) reFondo.textContent = formatCurrency(resumen.fondo_disponible);
    
    const reMayorCat = document.getElementById('re-mayor-cat');
    if (reMayorCat) reMayorCat.textContent = resumen.mayor_gasto_cat || 'N/A';
    
    const reMayorMonto = document.getElementById('re-mayor-monto');
    if (reMayorMonto) reMayorMonto.textContent = formatCurrency(resumen.mayor_gasto_monto);
    
    const proyNode = document.getElementById('re-proyeccion');
    const proyEstado = document.getElementById('re-proyeccion-estado');
    if (proyNode && proyEstado) {
        proyNode.textContent = formatCurrency(resumen.proyeccion_monto);
        if (resumen.proyeccion_riesgo) {
            proyNode.className = 'ri-value rojo';
            proyEstado.textContent = 'Riesgo de sobrecosto detectado';
        } else {
            proyNode.className = 'ri-value verde';
            proyEstado.textContent = 'Proyección dentro del límite';
        }
    }

    if (resumen.cumplimiento_gral) {
        const c = resumen.cumplimiento_gral;
        const cGralNode = document.getElementById('re-cumplimiento');

        if (cGralNode) {
            if (c.nivel === 'sin_meta') {
                cGralNode.textContent = 'Sin datos';
                cGralNode.className = 'ri-value';
                cGralNode.style.color = 'var(--text-muted)';
            } else {
                cGralNode.textContent = c.pct.toFixed(1) + '%';
                cGralNode.style.color = '';
                if (c.nivel === 'rojo')      cGralNode.className = 'ri-value rojo';
                else if (c.nivel === 'amarillo') cGralNode.className = 'ri-value amarillo';
                else                         cGralNode.className = 'ri-value verde';
            }
        }

        // Actualizar KPI de Cumplimiento Estratégico en tarjetas
        const kpiCumplimiento = document.getElementById('kpi-val-cumplimiento');
        const kpiFootCumpl    = document.getElementById('kpi-foot-cumplimiento');

        if (kpiCumplimiento) {
            if (c.nivel === 'sin_meta') {
                kpiCumplimiento.textContent = '-';
                kpiCumplimiento.className   = 'kpi-value';
                kpiCumplimiento.style.color = 'var(--text-muted)';
                if (kpiFootCumpl) kpiFootCumpl.innerHTML = '<span>Configura un presupuesto anual</span>';
            } else {
                kpiCumplimiento.textContent = c.pct.toFixed(1) + '%';
                kpiCumplimiento.style.color = '';
                kpiCumplimiento.className   = 'kpi-value';

                const fuente = c.fuente === 'presupuesto'
                    ? `vs. presupuesto proporcional (${formatCurrency(c.meta)})`
                    : `vs. metas anuales (${formatCurrency(c.meta)})`;

                if (c.nivel === 'rojo') {
                    kpiCumplimiento.classList.add('text-danger');
                    if (kpiFootCumpl) kpiFootCumpl.innerHTML = `<span style="color:var(--danger)">⚠️ Ejecución elevada — ${fuente}</span>`;
                } else if (c.nivel === 'amarillo') {
                    if (kpiFootCumpl) kpiFootCumpl.innerHTML = `<span style="color:var(--warning)">🔶 Control moderado — ${fuente}</span>`;
                } else {
                    kpiCumplimiento.classList.add('text-success');
                    if (kpiFootCumpl) kpiFootCumpl.innerHTML = `<span style="color:var(--success)">✅ Dentro del plan — ${fuente}</span>`;
                }
            }
        }
    }
}

function toggleAlertsPanel() {
    document.getElementById('alerts-panel').classList.toggle('open');
}

function renderAlertas(alertas) {
    const badge = document.getElementById('alerts-badge');
    const panelBody = document.getElementById('alerts-panel-body');
    badge.textContent = alertas.length;
    badge.setAttribute('data-count', alertas.length);
    
    if (alertas.length === 0) {
        panelBody.innerHTML = '<div class="alerts-empty">Sin alertas activas para este período.</div>';
        return;
    }
    
    let html = '';
    alertas.forEach(a => {
        const icon = a.nivel === 'peligro' ? 'alert-triangle' : 'alert-circle';
        const cls = a.nivel === 'peligro' ? 'danger' : 'warning';
        html += `
            <div class="alert-item ${cls}">
                <i data-lucide="${icon}" class="ai-icon"></i>
                <div class="ai-msg">${a.mensaje}</div>
            </div>
        `;
    });
    panelBody.innerHTML = html;
    lucide.createIcons();
}

function markAllAlertsRead() {
    const periodo = document.getElementById('global-period-picker').value;
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'marcar_alertas_leidas', periodo: periodo})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('alerts-badge').textContent = '0';
            document.getElementById('alerts-badge').setAttribute('data-count', '0');
            document.getElementById('alerts-panel-body').innerHTML = '<div class="alerts-empty">Todas las alertas fueron marcadas como leídas.</div>';
        }
    });
}

function loadDesviacion(periodo) {
    fetch(`api.php?action=get_desviacion&periodo=${periodo}`)
    .then(r=>r.json()).then(d => {
        if(d.success) {
            const data = d.desviacion;
            document.getElementById('desv-esperado').textContent = formatCurrency(data.esperado);
            document.getElementById('desv-ejecutado').textContent = formatCurrency(data.ejecutado);
            
            const desvNode = document.getElementById('desv-valor');
            desvNode.textContent = formatCurrency(data.desviacion) + ` (${data.desviacion_pct.toFixed(1)}%)`;
            
            const badgeNode = document.getElementById('re-desviacion');
            if (badgeNode) badgeNode.textContent = formatCurrency(data.desviacion);
            
            const badgeLvl = document.getElementById('desv-nivel-badge');
            if(data.nivel === 'verde') {
                desvNode.className = 'desv-value negativo';
                badgeLvl.textContent = 'Dentro de margen';
                badgeLvl.style.color = 'var(--success)';
            } else if(data.nivel === 'rojo') {
                desvNode.className = 'desv-value positivo';
                badgeLvl.textContent = 'Desviación Crítica';
                badgeLvl.style.color = 'var(--danger)';
            } else {
                desvNode.className = 'desv-value';
                desvNode.style.color = 'var(--warning)';
                badgeLvl.textContent = 'Desviación Moderada';
                badgeLvl.style.color = 'var(--warning)';
            }
        }
    });
}

function loadProyeccionCat(periodo) {
    fetch(`api.php?action=get_proyeccion_cat&periodo=${periodo}`)
    .then(r => r.json()).then(d => {
        const container = document.getElementById('proyeccion-cat-cards');
        if (!container) return;
        if (!d.success || !d.proyecciones || d.proyecciones.length === 0) {
            container.innerHTML = '<div style="color:var(--text-muted);padding:1.5rem;text-align:center;">No hay datos de gastos para este período.</div>';
            return;
        }
        container.innerHTML = d.proyecciones.map(c => {
            const metaMensual = c.meta_mensual || 0;
            
            // Calculate progress percentage
            let pct = 0;
            let pctDisplay = '0.0';
            let barColor = 'var(--success)';
            let statusBadge = '';
            
            if (metaMensual > 0) {
                pct = Math.min((c.ejecutado / metaMensual) * 100, 120);
                pctDisplay = ((c.ejecutado / metaMensual) * 100).toFixed(1);
                
                const proyPct = (c.proyeccion / metaMensual) * 100;
                if (proyPct > 100) {
                    barColor = 'var(--danger)';
                    statusBadge = '<span class="status-badge red" style="background:rgba(239,68,68,0.15); color:var(--danger); font-size:0.72rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">🔴 Excedido</span>';
                } else if (proyPct > 80) {
                    barColor = 'var(--warning)';
                    statusBadge = '<span class="status-badge yellow" style="background:rgba(245,158,11,0.15); color:var(--warning); font-size:0.72rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">🟡 Al límite</span>';
                } else {
                    barColor = 'var(--success)';
                    statusBadge = '<span class="status-badge green" style="background:rgba(16,185,129,0.15); color:var(--success); font-size:0.72rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">🟢 Estable</span>';
                }
            } else {
                const monthPct = c.proyeccion > 0 ? (c.ejecutado / c.proyeccion) * 100 : 0;
                pct = c.pct_del_mes > 0 ? Math.min(c.pct_del_mes, 120) : Math.min(monthPct, 100);
                pctDisplay = c.pct_del_mes > 0 ? c.pct_del_mes.toFixed(1) : monthPct.toFixed(1);
                barColor = 'var(--info)';
                statusBadge = '<span class="status-badge blue" style="background:rgba(59,130,246,0.15); color:var(--info); font-size:0.72rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px;">🔵 Activo</span>';
            }
            
            const catColor  = c.color || '#6366f1';
            const hist = (c.historial_3m || [0,0,0]);
            const maxHist = Math.max(...hist, 1);
            const histBars = hist.map((v, i) => {
                const h = Math.round((v / maxHist) * 28);
                const opacity = 0.4 + (i * 0.3);
                return `<div style="width:8px;height:${h}px;background:${catColor};opacity:${opacity};border-radius:2px;align-self:flex-end;" title="Mes -${3-i}: ${formatCurrency(v)}"></div>`;
            }).join('');
            
            return `
            <div class="proycat-card" style="border-left:3px solid ${catColor}; display:flex; flex-direction:column; gap:0.85rem; padding:1.25rem;">
                <div class="proycat-top" style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="proycat-name" style="display:flex; align-items:center; gap:0.6rem; font-weight:700; font-size:0.95rem; color:var(--text-primary);">
                        <div class="proycat-icon-wrapper" style="width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:${catColor}1a; color:${catColor};">
                            <i data-lucide="${c.icono || 'folder'}" style="width:16px; height:16px;"></i>
                        </div>
                        <span>${c.nombre}</span>
                    </div>
                    <div class="proycat-hist" style="display:flex; align-items:flex-end; gap:3px; height:32px;" title="Últimos 3 meses (antiguo → reciente)">${histBars}</div>
                </div>
                
                <div style="background:rgba(255,255,255,0.02); border-radius:10px; padding:0.75rem; border:1px solid rgba(255,255,255,0.03);">
                    <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:0.4rem;">
                        <span style="color:var(--text-muted);">Presupuesto Mensual:</span>
                        <span style="font-weight:600; color:var(--text-primary);">${metaMensual > 0 ? formatCurrency(metaMensual) : 'Sin límite'}</span>
                    </div>
                    <div class="proycat-bar-row" style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="proycat-bar-track" style="flex:1; height:6px; background:rgba(255,255,255,0.08); border-radius:999px; overflow:hidden;">
                            <div class="proycat-bar-fill" style="width:${Math.min(pct,100)}%; background:${barColor}; height:100%; border-radius:999px; transition:width 0.6s ease;"></div>
                        </div>
                        <span class="proycat-pct" style="color:${barColor}; font-size:0.75rem; font-weight:700; min-width:38px; text-align:right;">${pctDisplay}%</span>
                    </div>
                </div>
                
                <div class="proycat-nums" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.5rem; text-align:center;">
                    <div class="proycat-stat">
                        <div class="proycat-stat-lbl" style="font-size:0.68rem; color:var(--text-muted); margin-bottom:0.25rem;">Ejecutado</div>
                        <div class="proycat-stat-val" style="font-size:0.8rem; font-weight:700; color:var(--text-primary); white-space: nowrap;">${formatCurrency(c.ejecutado)}</div>
                    </div>
                    <div class="proycat-stat">
                        <div class="proycat-stat-lbl" style="font-size:0.68rem; color:var(--text-muted); margin-bottom:0.25rem;">Prom. Diario</div>
                        <div class="proycat-stat-val" style="font-size:0.8rem; font-weight:700; color:var(--warning); white-space: nowrap;">${formatCurrency(c.prom_diario)}</div>
                    </div>
                    <div class="proycat-stat">
                        <div class="proycat-stat-lbl" style="font-size:0.68rem; color:var(--text-muted); margin-bottom:0.25rem;">Proyección</div>
                        <div class="proycat-stat-val" style="font-size:0.8rem; font-weight:800; color:${barColor}; white-space: nowrap;">${formatCurrency(c.proyeccion)}</div>
                    </div>
                </div>
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.25rem; border-top:1px solid rgba(255,255,255,0.05); padding-top:0.6rem;">
                    <span style="font-size:0.7rem; color:var(--text-muted);">Semáforo:</span>
                    ${statusBadge}
                </div>
            </div>`;
        }).join('');
        lucide.createIcons();
    });
}

function renderAhorroGlobal(kpis) {
    if (!kpis) return;

    const montoInput    = document.getElementById('meta-ahorro-input');
    const limiteEl      = document.getElementById('ahorro-limite');
    const proyectadoEl  = document.getElementById('ahorro-proyectado');
    const progressBar   = document.getElementById('ahorro-progress-bar');
    const pctLabel      = document.getElementById('ahorro-pct-label');
    const statusMsg     = document.getElementById('ahorro-status-msg');

    if (!montoInput) return;

    const presAnual       = kpis.presupuesto_anual     || 0;
    const metaAhorroMonto = kpis.meta_ahorro_monto     || 0;
    const gastoAnualAcum  = kpis.gasto_anual_acumulado || 0;

    // Rellenar input si no tiene foco
    if (document.activeElement !== montoInput) {
        montoInput.value = metaAhorroMonto > 0 ? metaAhorroMonto : '';
    }

    // Gasto máximo permitido = Presupuesto Anual - Meta de Ahorro
    const gastoMaxPermitido = presAnual > 0 ? presAnual - metaAhorroMonto : 0;
    if (limiteEl) limiteEl.textContent = formatCurrency(gastoMaxPermitido);

    // Proyección de gasto anual (promedio mensual × 12)
    const mesActual    = new Date().getMonth() + 1;
    const promMensual  = mesActual > 0 ? gastoAnualAcum / mesActual : 0;
    const gastoProyAno = promMensual * 12;
    if (proyectadoEl) proyectadoEl.textContent = formatCurrency(gastoProyAno);

    // Porcentaje del gasto acumulado vs. límite permitido
    const pct = gastoMaxPermitido > 0 ? Math.min((gastoAnualAcum / gastoMaxPermitido) * 100, 120) : 0;

    if (progressBar) {
        progressBar.style.width = Math.min(pct, 100) + '%';
        if (pct > 100) {
            progressBar.style.background = 'linear-gradient(90deg, #ef4444, #f87171)';
            progressBar.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.5)';
        } else if (pct > 80) {
            progressBar.style.background = 'linear-gradient(90deg, #f59e0b, #fbbf24)';
            progressBar.style.boxShadow = '0 0 10px rgba(245, 158, 11, 0.5)';
        } else {
            progressBar.style.background = 'linear-gradient(90deg, var(--success), #34d399)';
            progressBar.style.boxShadow = '0 0 10px rgba(16, 185, 129, 0.5)';
        }
    }
    if (pctLabel) {
        pctLabel.textContent = pct.toFixed(1) + '% ejecutado';
        pctLabel.style.color = pct > 100 ? '#ef4444' : pct > 80 ? '#f59e0b' : '#10b981';
    }

    // Mensaje de estado
    if (statusMsg) {
        if (gastoProyAno > gastoMaxPermitido) {
            const exceso = gastoProyAno - gastoMaxPermitido;
            statusMsg.innerHTML = `<span style="color:var(--danger)">⚠️ Proyección supera el límite. Riesgo de exceder por <b>${formatCurrency(exceso)}</b></span>`;
        } else {
            const margen = gastoMaxPermitido - gastoAnualAcum;
            statusMsg.innerHTML = `<span style="color:var(--success)">✅ Vas bien. Margen disponible: <b>${formatCurrency(margen)}</b></span>`;
        }
        statusMsg.style.color = '';
    }
}


function saveMetaAhorroGlobal(e) {
    e.preventDefault();
    const val  = parseFloat(document.getElementById('meta-ahorro-input').value) || 0;
    const anio = document.getElementById('global-period-picker').value.split('-')[0];

    if (val < 0) {
        showToast('El monto de ahorro no puede ser negativo.', 'warning', 3000);
        return;
    }

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'set_meta_ahorro_global',
            anio:   anio,
            monto:  val     // enviamos monto en pesos, no porcentaje
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const pctEquiv = d.pct_equivalente ? ` (${d.pct_equivalente}% del presupuesto)` : '';
            showToast(`Objetivo de Ahorro guardado: ${formatCurrency(val)}${pctEquiv}`, 'success', 3500);
            refreshDashboardData();
        } else {
            showToast(d.message || 'Error al guardar la meta de ahorro', 'danger', 3000);
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error de conexión', 'danger', 3000);
    });
}

/**
 * Renderiza el comparativo Ingresos vs Gastos mensual del año
 */
function renderBalanceAnual(data) {
    if (!data) return;
    const balanceEl = document.getElementById('balance-anual-monto');
    const ingrEl    = document.getElementById('balance-anual-ingresos');
    const gastEl    = document.getElementById('balance-anual-gastos');
    if (balanceEl) {
        balanceEl.textContent = formatCurrency(data.balance);
        balanceEl.style.color = data.balance >= 0 ? 'var(--success)' : 'var(--danger)';
    }
    if (ingrEl) ingrEl.textContent = formatCurrency(data.ingresos_total);
    if (gastEl) gastEl.textContent  = formatCurrency(data.gastos_total);
    // Actualizar gráfica comparativa
    if (AppCharts.comparativo && data.meses) {
        AppCharts.updateComparativo(data.meses);
    }
}


function loadBitacora() {
    const anio = document.getElementById('bitacora-anio').value;
    const mes = document.getElementById('bitacora-mes').value;
    const cat = document.getElementById('bitacora-cat').value;
    
    // Si bitacora-cat esta vacio y no tiene opciones, cargarlas:
    const catSelect = document.getElementById('bitacora-cat');
    if(catSelect.options.length <= 1) {
        fetch('api.php?action=get_categorias').then(r=>r.json()).then(data=>{
            if(data.success){
                data.categorias.forEach(c => {
                    catSelect.innerHTML += `<option value="${c.id}">${c.nombre}</option>`;
                });
            }
        });
    }

    // Mostrar transacciones filtradas por año, mes y categoría
    fetch(`api.php?action=get_bitacora&anio=${anio}&mes=${mes}&categoria_id=${cat}`)
    .then(r=>r.json()).then(d => {
        if(d.success) {
            const tbody = document.getElementById('bitacora-tbody');
            tbody.innerHTML = '';
            if(d.bitacora.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No hay registros para este filtro.</td></tr>';
                return;
            }
            d.bitacora.forEach(tx => {
                const color = tx.tipo === 'ingreso' ? 'var(--success)' : 'var(--danger)';
                const sgn = tx.tipo === 'ingreso' ? '+' : '-';
                tbody.innerHTML += `
                    <tr>
                        <td style="font-weight:600;color:var(--text-primary);">${tx.concepto}</td>
                        <td>${tx.fecha}</td>
                        <td><span class="tx-category-badge" style="background:rgba(255,255,255,0.05);">${tx.categoria||'Ingreso'}</span></td>
                        <td style="text-align:right;color:${color};font-weight:700;">${sgn}${formatCurrency(tx.monto)}</td>
                    </tr>
                `;
            });
        }
    });
}

// ============================================================================
// BUSQUEDA EN EL LEDGER
// ============================================================================
function filterLedger(query) {
    const q = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#ledger-tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const show = !q || text.includes(q);
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const empty = document.getElementById('ledger-empty-state');
    if (empty) empty.style.display = visible === 0 ? 'flex' : 'none';
}

// ============================================================================
// EDITAR TRANSACCION
// ============================================================================
function abrirEditarTx(id, tipo, concepto, monto, fecha, categoriaId) {
    document.getElementById('edit-tx-id').value      = id;
    document.getElementById('edit-tx-tipo').value    = tipo;
    document.getElementById('edit-tx-concepto').value= concepto;
    document.getElementById('edit-tx-monto').value   = monto;
    document.getElementById('edit-tx-fecha').value   = fecha;

    const title = document.getElementById('editar-tx-title');
    title.textContent = tipo === 'ingreso' ? 'Editar Ingreso' : 'Editar Gasto';

    // Mostrar/ocultar campo de categoria
    const catGroup = document.getElementById('edit-tx-cat-group');
    const catSel   = document.getElementById('edit-tx-categoria');
    catGroup.style.display = tipo === 'gasto' ? '' : 'none';

    if (tipo === 'gasto' && catSel.options.length <= 1) {
        fetch('api.php?action=get_categorias').then(r => r.json()).then(d => {
            if (d.success) {
                catSel.innerHTML = '<option value="">Sin categoria</option>';
                d.categorias.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.nombre;
                    if (c.id == categoriaId) opt.selected = true;
                    catSel.appendChild(opt);
                });
            }
        });
    } else if (tipo === 'gasto' && categoriaId) {
        catSel.value = categoriaId;
    }

    openModal('modal-editar-tx');
}

function submitEditarTx(e) {
    e.preventDefault();
    const id   = document.getElementById('edit-tx-id').value;
    const tipo = document.getElementById('edit-tx-tipo').value;
    const payload = {
        action:        'editar_transaccion',
        id:            parseInt(id),
        tipo:          tipo,
        concepto:      document.getElementById('edit-tx-concepto').value.trim(),
        monto:         parseFloat(document.getElementById('edit-tx-monto').value),
        fecha:         document.getElementById('edit-tx-fecha').value,
        categoria_id:  tipo === 'gasto' ? parseInt(document.getElementById('edit-tx-categoria').value) || null : null,
    };

    fetch('api.php', {
        method:  'POST',
        headers: {'Content-Type':'application/json'},
        body:    JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Transaccion actualizada correctamente.', 'success', 3000);
            closeModal('modal-editar-tx');
            refreshDashboardData();
        } else {
            showToast(d.message || 'Error al actualizar.', 'danger', 4000);
        }
    });
}

// ============================================================================
// ELIMINAR TRANSACCION
// ============================================================================
function eliminarTx(id, tipo) {
    if (!confirm('¿Confirmas eliminar esta transaccion? Esta accion no se puede deshacer.')) return;
    fetch('api.php', {
        method:  'POST',
        headers: {'Content-Type':'application/json'},
        body:    JSON.stringify({ action:'eliminar_transaccion', id:parseInt(id), tipo:tipo }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Transaccion eliminada.', 'success', 3000);
            refreshDashboardData();
        } else {
            showToast(d.message || 'Error al eliminar.', 'danger', 4000);
        }
    });
}

// ============================================================================
// PRESUPUESTO POR CATEGORIA
// ============================================================================
function abrirMetaCat() {
    const list = document.getElementById('meta-cat-list');
    list.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:1rem;">Cargando...</div>';
    openModal('modal-meta-cat');

    const anio = document.getElementById('global-period-picker').value.split('-')[0];
    fetch('api.php?action=get_categorias').then(r => r.json()).then(d => {
        if (!d.success) { list.innerHTML = 'Error al cargar.'; return; }
        list.innerHTML = d.categorias.map(c => `
        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0;border-bottom:1px solid var(--panel-border);">
            <span style="width:10px;height:10px;border-radius:50%;background:${c.color};flex-shrink:0;"></span>
            <span style="flex:1;font-size:0.85rem;font-weight:600;color:var(--text-primary);">${c.nombre}</span>
            <div style="position:relative;display:flex;align-items:center;">
                <span style="position:absolute;left:8px;font-size:0.8rem;color:var(--text-muted);pointer-events:none;">$</span>
                <input type="number" class="form-control" id="meta-cat-${c.id}"
                    value="${c.meta_cat > 0 ? c.meta_cat : ''}"
                    placeholder="Sin meta"
                    min="0" step="1000"
                    style="width:140px;padding-left:1.5rem;font-size:0.82rem;">
            </div>
            <button class="btn btn-primary" style="padding:0.3rem 0.7rem;font-size:0.75rem;" onclick="guardarMetaCat(${c.id}, '${anio}')">
                Guardar
            </button>
        </div>`).join('');
    });
}

function guardarMetaCat(catId, anio) {
    const val = parseFloat(document.getElementById('meta-cat-'+catId).value) || 0;
    fetch('api.php', {
        method:  'POST',
        headers: {'Content-Type':'application/json'},
        body:    JSON.stringify({ action:'set_meta_categoria', categoria_id:catId, anio:parseInt(anio), valor_meta:val }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) showToast('Meta guardada correctamente.', 'success', 2500);
        else showToast(d.message || 'Error.', 'danger', 3000);
    });
}

// ============================================================================
// EXPORTAR EXCEL PROFESIONAL (PHP endpoint)
// ============================================================================
function exportExcelProfesional() {
    const periodo = document.getElementById('global-period-picker').value;
    showToast('Generando reporte Excel profesional...', 'info', 2000);
    const url = `export_excel.php?periodo=${periodo}`;
    const a = document.createElement('a');
    a.href = url;
    a.download = `Reporte_Financiero_${periodo}.xlsx`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ============================================================================
// COMPARATIVO MES ANTERIOR
// ============================================================================
function renderComparativoMes(data) {
    if (!data) return;
    const ids = {
        gasto_actual:    'cmp-gasto-actual',
        gasto_anterior:  'cmp-gasto-anterior',
        gasto_diff:      'cmp-gasto-diff',
        ingreso_actual:  'cmp-ingreso-actual',
        ingreso_anterior:'cmp-ingreso-anterior',
        ingreso_diff:    'cmp-ingreso-diff',
        balance_actual:  'cmp-balance-actual',
        balance_anterior:'cmp-balance-anterior',
        balance_diff:    'cmp-balance-diff',
        mes_label:       'cmp-mes-label',
        mes_prev_label:  'cmp-mes-prev-label',
    };
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    const setColor = (id, positive) => { const el = document.getElementById(id); if(el) el.style.color = positive ? 'var(--success)' : 'var(--danger)'; };
    const arrow = (diff) => diff > 0 ? '▲' : diff < 0 ? '▼' : '—';

    set(ids.mes_label,       data.periodo_actual   || '');
    set(ids.mes_prev_label,  data.periodo_anterior || '');
    set(ids.gasto_actual,    formatCurrency(data.actual?.gastos   || 0));
    set(ids.gasto_anterior,  formatCurrency(data.anterior?.gastos || 0));
    set(ids.ingreso_actual,  formatCurrency(data.actual?.ingresos   || 0));
    set(ids.ingreso_anterior,formatCurrency(data.anterior?.ingresos || 0));
    set(ids.balance_actual,  formatCurrency(data.actual?.balance   || 0));
    set(ids.balance_anterior,formatCurrency(data.anterior?.balance || 0));

    const dg = data.diff_gastos   || 0;
    const di = data.diff_ingresos || 0;
    const db = data.diff_balance  || 0;

    set(ids.gasto_diff,   `${arrow(dg)} ${formatCurrency(Math.abs(dg))} (${(data.pct_gastos||0).toFixed(1)}%)`);
    set(ids.ingreso_diff, `${arrow(di)} ${formatCurrency(Math.abs(di))} (${(data.pct_ingresos||0).toFixed(1)}%)`);
    set(ids.balance_diff, `${arrow(db)} ${formatCurrency(Math.abs(db))}`);

    // Gastos: mas gastos = rojo. Ingresos/balance: mas = verde
    setColor(ids.gasto_diff,    dg <= 0);
    setColor(ids.ingreso_diff,  di >= 0);
    setColor(ids.balance_diff,  db >= 0);

    // Alerta si gastas mucho mas
    if (data.alerta_gastos) {
        showToast(`⚠️ Gastos +${data.pct_gastos.toFixed(1)}% vs mes anterior`, 'warning', 5000);
    }
}

// ============================================================================
// AUTOCOMPLETADO DE CONCEPTOS
// ============================================================================
function initAutocompletado(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    let listEl = null;
    let debounceTimer = null;

    input.setAttribute('autocomplete', 'off');

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const q = input.value.trim();
        if (q.length < 2) { removeList(); return; }
        debounceTimer = setTimeout(() => {
            fetch(`api.php?action=get_autocomplete_conceptos&q=${encodeURIComponent(q)}`)
            .then(r => r.json()).then(d => {
                removeList();
                if (!d.success || !d.conceptos.length) return;
                listEl = document.createElement('ul');
                listEl.className = 'autocomplete-list';
                d.conceptos.forEach(c => {
                    const li = document.createElement('li');
                    li.textContent = c;
                    li.addEventListener('mousedown', () => {
                        input.value = c;
                        removeList();
                    });
                    listEl.appendChild(li);
                });
                input.parentNode.style.position = 'relative';
                input.parentNode.appendChild(listEl);
            });
        }, 250);
    });

    input.addEventListener('blur', () => setTimeout(removeList, 200));

    function removeList() {
        if (listEl) { listEl.remove(); listEl = null; }
    }
}

// ============================================================================
// META VS EJECUTADO — trigger desde datos de categorias
// ============================================================================
function renderMetaVsEjec(gastosCat, presupuesto) {
    if (!AppCharts.metaVsEjec) return;
    // Convertir gastos_categoria al formato que espera updateMetaVsEjecutado
    const metasData = (gastosCat || []).map(c => ({
        nombre:    c.nombre,
        color:     c.color || '#6366f1',
        ejecutado: c.total || 0,
        meta_anual: c.meta_cat || 0,
        pct:       c.meta_cat > 0 ? (c.total / c.meta_cat * 100) : 0,
    }));
    AppCharts.updateMetaVsEjecutado(metasData);
}

// ============================================================================
// CARGA MASIVA (BULK UPLOAD)
// ============================================================================
let bulkDataToSubmit = [];

function initBulkUpload() {
    const dropZone = document.getElementById('bulk-drop-zone');
    const fileInput = document.getElementById('bulk-file-input');
    
    if (!dropZone || !fileInput) return;

    // Abrir selector al hacer click
    dropZone.addEventListener('click', () => fileInput.click());

    // Manejar Drag & Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.style.background = 'rgba(99,102,241,0.15)';
            dropZone.style.borderColor = 'var(--primary)';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.style.background = 'rgba(99,102,241,0.05)';
            dropZone.style.borderColor = 'rgba(99,102,241,0.4)';
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleBulkFiles(files);
    });

    fileInput.addEventListener('change', function() {
        handleBulkFiles(this.files);
    });
}

function handleBulkFiles(files) {
    if (files.length === 0) return;
    const file = files[0];
    
    const validExts = ['.xlsx', '.xls', '.csv'];
    const fileExt = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
    
    if (!validExts.includes(fileExt)) {
        showToast('Formato no válido. Sube un archivo .xlsx o .csv', 'danger');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            // Convertir a JSON
            const json = XLSX.utils.sheet_to_json(worksheet, {raw: false});
            processBulkData(json);
        } catch (error) {
            console.error(error);
            showToast('Error al leer el archivo. Verifica el formato.', 'danger');
        }
    };
    reader.readAsArrayBuffer(file);
}

function processBulkData(data) {
    const type = document.getElementById('bulk-upload-type').value;
    const tbody = document.getElementById('bulk-preview-tbody');
    const thead = document.getElementById('bulk-preview-thead');
    const btnSubmit = document.getElementById('btn-bulk-submit');
    const errorCountSpan = document.getElementById('bulk-error-count');
    
    document.getElementById('bulk-preview-container').style.display = 'block';
    tbody.innerHTML = '';
    bulkDataToSubmit = [];
    
    let errors = 0;

    if (type === 'transacciones') {
        thead.innerHTML = `<tr><th>Fecha</th><th>Concepto</th><th>Categoría</th><th>Monto</th><th>Tipo</th><th>Estado</th></tr>`;
        
        data.forEach((row, index) => {
            const getVal = (keys) => {
                const k = Object.keys(row).find(key => keys.includes(key.toLowerCase().trim()));
                return k ? row[k] : null;
            };

            const fecha = getVal(['fecha', 'date']);
            const concepto = getVal(['concepto', 'descripción', 'description', 'detalle']);
            const categoria = getVal(['categoría', 'categoria', 'category']);
            let monto = getVal(['monto', 'cantidad', 'amount', 'valor']);
            let tipo = getVal(['tipo', 'type']) || 'gasto'; // default gasto

            monto = parseFloat(String(monto).replace(/[^0-9.-]+/g,""));
            tipo = String(tipo).toLowerCase().includes('ingreso') ? 'ingreso' : 'gasto';

            let rowStatus = '✅ OK';
            let rowColor = 'var(--success)';
            let isValid = true;

            if (!fecha || isNaN(Date.parse(fecha))) { isValid = false; rowStatus = '❌ Fecha inválida'; }
            if (!concepto || concepto.length < 3) { isValid = false; rowStatus = '❌ Concepto corto'; }
            if (!categoria && tipo === 'gasto') { isValid = false; rowStatus = '❌ Falta categoría'; }
            if (isNaN(monto) || monto <= 0) { isValid = false; rowStatus = '❌ Monto inválido'; }

            if (!isValid) {
                errors++;
                rowColor = 'var(--danger)';
            } else {
                bulkDataToSubmit.push({ fecha, concepto, categoria, monto, tipo });
            }

            tbody.innerHTML += `
                <tr style="color:${isValid ? '' : 'rgba(239,68,68,0.8)'}">
                    <td>${fecha || '-'}</td>
                    <td>${concepto || '-'}</td>
                    <td>${categoria || '-'}</td>
                    <td>$${isNaN(monto) ? '0' : monto.toLocaleString('es-MX')}</td>
                    <td><span class="tx-category-badge" style="background:rgba(255,255,255,0.05); color:${tipo==='ingreso'?'var(--success)':'var(--danger)'}">${tipo.toUpperCase()}</span></td>
                    <td style="color:${rowColor}; font-weight:bold; font-size:0.85rem;">${rowStatus}</td>
                </tr>
            `;
        });
    } else {
        // Metas MCI
        thead.innerHTML = `<tr><th>Categoría (Proceso)</th><th>Año</th><th>Meta (MCI Anual)</th><th>Estado</th></tr>`;
        
        data.forEach((row, index) => {
            const getVal = (keys) => {
                const k = Object.keys(row).find(key => keys.includes(key.toLowerCase().trim()));
                return k ? row[k] : null;
            };

            const categoria = getVal(['categoría', 'categoria', 'proceso']);
            let anio = getVal(['año', 'anio', 'year']);
            let meta = getVal(['meta', 'mci', 'mci anual', 'monto']);

            anio = parseInt(anio) || new Date().getFullYear();
            meta = parseFloat(String(meta).replace(/[^0-9.-]+/g,""));

            let rowStatus = '✅ OK';
            let rowColor = 'var(--success)';
            let isValid = true;

            if (!categoria) { isValid = false; rowStatus = '❌ Falta categoría'; }
            if (isNaN(meta) || meta <= 0) { isValid = false; rowStatus = '❌ Meta inválida'; }

            if (!isValid) {
                errors++;
                rowColor = 'var(--danger)';
            } else {
                bulkDataToSubmit.push({ categoria, anio, meta });
            }

            tbody.innerHTML += `
                <tr style="color:${isValid ? '' : 'rgba(239,68,68,0.8)'}">
                    <td>${categoria || '-'}</td>
                    <td>${anio}</td>
                    <td>$${isNaN(meta) ? '0' : meta.toLocaleString('es-MX')}</td>
                    <td style="color:${rowColor}; font-weight:bold; font-size:0.85rem;">${rowStatus}</td>
                </tr>
            `;
        });
    }

    if (errors > 0) {
        errorCountSpan.textContent = `⚠️ ${errors} fila(s) con errores serán ignoradas.`;
        errorCountSpan.style.display = 'inline-block';
    } else {
        errorCountSpan.style.display = 'none';
    }

    if (bulkDataToSubmit.length > 0) {
        btnSubmit.style.opacity = '1';
        btnSubmit.style.pointerEvents = 'auto';
        btnSubmit.innerHTML = `<i data-lucide="save"></i> Subir ${bulkDataToSubmit.length} Registros`;
    } else {
        btnSubmit.style.opacity = '0.5';
        btnSubmit.style.pointerEvents = 'none';
        btnSubmit.innerHTML = `<i data-lucide="save"></i> Subir Datos Válidos`;
    }
    
    lucide.createIcons();
}

function submitBulkData() {
    if (bulkDataToSubmit.length === 0) return;
    
    const type = document.getElementById('bulk-upload-type').value;
    const action = type === 'transacciones' ? 'bulk_upload_transacciones' : 'bulk_upload_metas';
    const btnSubmit = document.getElementById('btn-bulk-submit');
    
    btnSubmit.innerHTML = `<i data-lucide="loader" class="spin"></i> Procesando...`;
    btnSubmit.style.pointerEvents = 'none';
    
    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, data: bulkDataToSubmit })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(`¡Carga exitosa! ${data.insertados} registros guardados.`, 'success', 5000);
            document.getElementById('bulk-preview-container').style.display = 'none';
            document.getElementById('bulk-file-input').value = '';
            bulkDataToSubmit = [];
            refreshDashboardData(); // Refrescar los gráficos
            
            // Volver al dashboard
            document.querySelector('a.nav-item[href="#dashboard"]').click();
        } else {
            showToast(data.message || 'Error en la carga masiva.', 'danger', 5000);
            btnSubmit.innerHTML = `<i data-lucide="save"></i> Reintentar Subida`;
            btnSubmit.style.pointerEvents = 'auto';
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error de conexión al subir los datos.', 'danger');
        btnSubmit.innerHTML = `<i data-lucide="save"></i> Reintentar Subida`;
        btnSubmit.style.pointerEvents = 'auto';
    });
}

function updateBulkTemplateLink() {
    // Solo cambiar el comportamiento del botón de plantilla según el tipo seleccionado
}

function downloadBulkTemplate() {
    const type = document.getElementById('bulk-upload-type').value;
    
    // Crear un libro de Excel (Workbook)
    const wb = XLSX.utils.book_new();
    let ws_data = [];
    let fileName = '';

    if (type === 'transacciones') {
        fileName = 'Plantilla_Transacciones_Financieras.xlsx';
        // Encabezados
        ws_data.push(["Fecha", "Concepto", "Categoría", "Monto", "Tipo"]);
        // Fila de ejemplo 1
        ws_data.push(["2026-05-15", "Compra de Servidores Cloud", "Tecnología & Software", 50000.50, "Gasto"]);
        // Fila de ejemplo 2
        ws_data.push(["2026-05-16", "Venta de Servicios Web", "", 15000.00, "Ingreso"]);
        // Fila de ejemplo 3
        ws_data.push(["2026-05-18", "Pago de Planillas Mensuales", "Nómina & Personal", 120000.00, "Gasto"]);
    } else {
        fileName = 'Plantilla_Metas_MCI_2026.xlsx';
        // Encabezados
        ws_data.push(["Categoría (Proceso)", "Año", "Meta (MCI Anual)"]);
        // Ejemplos
        ws_data.push(["Nómina & Personal", 2026, 10000000]);
        ws_data.push(["Tecnología & Software", 2026, 8000000]);
        ws_data.push(["Marketing & Publicidad", 2026, 2000000]);
    }

    // Convertir el array de datos a una hoja de cálculo
    const ws = XLSX.utils.aoa_to_sheet(ws_data);

    // Darle un poco de formato a los anchos de columna para que no se vea "simple"
    const wscols = type === 'transacciones' 
        ? [{wch: 15}, {wch: 35}, {wch: 25}, {wch: 15}, {wch: 10}]
        : [{wch: 30}, {wch: 10}, {wch: 20}];
    ws['!cols'] = wscols;

    // Agregar la hoja al libro
    XLSX.utils.book_append_sheet(wb, ws, "Plantilla");

    // Generar y descargar el archivo XLSX real
    XLSX.writeFile(wb, fileName);
}

// ============================================================================
// NAVEGACIÓN (TABS EN SIDEBAR)
// ============================================================================
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item[href^="#"]');
    const main = document.getElementById('dashboard');
    const cargaMasiva = document.getElementById('cargamasiva-view');
    
    // Crear un wrapper para el dashboard principal si no existe
    let dashView = document.getElementById('main-dashboard-view');
    if (!dashView) {
        dashView = document.createElement('div');
        dashView.id = 'main-dashboard-view';
        dashView.style.width = '100%';
        
        // Mover todos los hijos de main (excepto cargaMasiva) al dashView
        Array.from(main.childNodes).forEach(child => {
            if (child.id !== 'cargamasiva-view') {
                dashView.appendChild(child);
            }
        });
        
        main.insertBefore(dashView, main.firstChild);
    }
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = item.getAttribute('href').substring(1);
            
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
            
            if (targetId === 'cargamasiva') {
                dashView.style.display = 'none';
                cargaMasiva.style.display = 'block';
                document.getElementById('dashboard-title-text').textContent = 'Carga Masiva';
            } else {
                cargaMasiva.style.display = 'none';
                dashView.style.display = 'block';
                refreshDashboardData(); // Refresca el título y datos
            }
        });
    });
}
