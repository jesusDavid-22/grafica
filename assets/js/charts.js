/**
 * Controlador Avanzado de Gráficos con Apache ECharts
 * Gráficas: Velocímetro · Tendencia · Barras Históricas · Donut Categorías · Radar KPI
 */

const AppCharts = {
    gauge:       null,
    trend:       null,
    bar:         null,
    donut:       null,
    radar:       null,
    metaVsEjec:  null,
    comparativo: null,

    mciAvance:   null,
    mciBarHorizontal: null,

    // ──────────────────────────────────────────────
    //  INICIALIZACIÓN
    // ──────────────────────────────────────────────
    init() {
        const ids  = ['chart-gauge-container','chart-trend-container','chart-bar-container','chart-donut-container','chart-metavs-container','chart-comparativo-container', 'chart-mci-avance-container', 'chart-mci-bar-horizontal-container'];
        const keys = ['gauge','trend','bar','donut','metaVsEjec','comparativo', 'mciAvance', 'mciBarHorizontal'];
        ids.forEach((id, i) => {
            const el = document.getElementById(id);
            if (el) this[keys[i]] = echarts.init(el, null, { renderer: 'svg' });
        });

        window.addEventListener('resize', () => {
            this.gauge?.resize();
            this.trend?.resize();
            this.bar?.resize();
            this.donut?.resize();
            this.metaVsEjec?.resize();
            this.comparativo?.resize();
            this.mciAvance?.resize();
            this.mciBarHorizontal?.resize();
        });

        // Forzar resize inicial tras el primer paint para que ECharts detecte las dimensiones reales
        setTimeout(() => {
            this.gauge?.resize();
            this.trend?.resize();
            this.bar?.resize();
            this.donut?.resize();
            this.metaVsEjec?.resize();
            this.comparativo?.resize();
            this.mciAvance?.resize();
            this.mciBarHorizontal?.resize();
        }, 200);
    },

    // ────────────────────────────────────────────────
    //  INGRESOS vs GASTOS COMPARATIVO
    // ────────────────────────────────────────────────
    updateComparativo(meses) {
        if (!this.comparativo || !meses) return;
        const labels   = meses.map(m => m.label || m.mes);
        const ingresos = meses.map(m => m.ingresos);
        const gastos   = meses.map(m => m.gastos);
        const balance  = meses.map(m => m.ingresos - m.gastos);

        this.comparativo.setOption({
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                backgroundColor: 'rgba(15,23,42,0.95)',
                borderColor: 'rgba(99,102,241,0.3)',
                textStyle: { color: '#e2e8f0', fontSize: 12 },
                formatter(params) {
                    const fmt = v => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(v);
                    let html = `<b>${params[0].axisValue}</b><br/>`;
                    params.forEach(p => { html += `${p.marker} ${p.seriesName}: ${fmt(p.value)}<br/>`; });
                    return html;
                }
            },
            legend: {
                data: ['Ingresos', 'Gastos', 'Balance'],
                textStyle: { color: '#94a3b8', fontSize: 11 },
                top: 8
            },
            grid: { left: '3%', right: '4%', bottom: '8%', top: '15%', containLabel: true },
            xAxis: {
                type: 'category',
                data: labels,
                axisLabel: { color: '#64748b', fontSize: 11 },
                axisLine: { lineStyle: { color: 'rgba(255,255,255,0.08)' } },
                splitLine: { show: false }
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    color: '#64748b', fontSize: 10,
                    formatter: v => v >= 1e6 ? (v/1e6).toFixed(1)+'M' : v >= 1e3 ? (v/1e3).toFixed(0)+'K' : v
                },
                splitLine: { lineStyle: { color: 'rgba(255,255,255,0.05)' } }
            },
            series: [
                {
                    name: 'Ingresos',
                    type: 'bar',
                    barGap: '10%',
                    data: ingresos,
                    itemStyle: { color: '#10b981', borderRadius: [4,4,0,0] },
                    emphasis: { itemStyle: { color: '#34d399' } }
                },
                {
                    name: 'Gastos',
                    type: 'bar',
                    data: gastos,
                    itemStyle: { color: '#ef4444', borderRadius: [4,4,0,0] },
                    emphasis: { itemStyle: { color: '#f87171' } }
                },
                {
                    name: 'Balance',
                    type: 'line',
                    data: balance,
                    smooth: true,
                    symbol: 'circle',
                    symbolSize: 6,
                    lineStyle: { color: '#6366f1', width: 2 },
                    itemStyle: { color: '#6366f1' },
                    areaStyle: { color: { type: 'linear', x:0, y:0, x2:0, y2:1, colorStops: [{offset:0, color:'rgba(99,102,241,0.2)'},{offset:1, color:'rgba(99,102,241,0)'}] } }
                }
            ]
        });
        setTimeout(() => this.comparativo?.resize(), 100);
    },

    // ────────────────────────────────────────────────
    //  MCI AVANCE $ PROCESO (Barras + Línea)
    // ────────────────────────────────────────────────
    updateMciAvance(detalles, totales) {
        if (!this.mciAvance || !detalles) return;
        
        // Agregar "TOTAL" al final para replicar el Excel
        const labels = detalles.map(d => d.nombre.toUpperCase()).concat(['TOTAL']);
        const metas = detalles.map(d => d.mci_anual).concat([totales.mci_anual]);
        const ejecutado = detalles.map(d => d.ejecutado).concat([totales.ejecutado]);

        this.mciAvance.setOption({
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                backgroundColor: 'rgba(5,8,22,0.92)',
                borderColor: 'rgba(99,102,241,0.3)',
                textStyle: { color: '#F8FAFC' }
            },
            legend: {
                data: ['META 2026', 'ACUMULADO MAYO'],
                bottom: 0,
                textStyle: { color: '#94A3B8', fontSize: 11, fontFamily: 'Outfit' }
            },
            grid: { left: '3%', right: '4%', bottom: '15%', top: '10%', containLabel: true },
            xAxis: {
                type: 'category',
                data: labels,
                axisLabel: { color: '#64748b', fontSize: 9, fontFamily: 'Outfit', interval: 0, width: 80, overflow: 'break', rotate: 25 },
                axisLine: { lineStyle: { color: 'rgba(255,255,255,0.08)' } }
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    color: '#64748b', fontSize: 10,
                    formatter: v => '$' + v
                },
                splitLine: { lineStyle: { color: 'rgba(255,255,255,0.05)' } }
            },
            series: [
                {
                    name: 'META 2026',
                    type: 'bar',
                    data: metas,
                    barWidth: '30%',
                    itemStyle: { color: '#2e7d32', borderRadius: [4,4,0,0] } // Verde oscuro tipo excel
                },
                {
                    name: 'ACUMULADO MAYO',
                    type: 'line',
                    data: ejecutado,
                    symbol: 'circle',
                    symbolSize: 8,
                    itemStyle: { color: '#1976d2' }, // Azul claro tipo excel
                    lineStyle: { color: '#1976d2', width: 3 },
                    label: { show: true, position: 'top', color: '#1976d2', formatter: p => '$' + p.value, fontSize: 10 }
                }
            ]
        }, true);
    },

    // ────────────────────────────────────────────────
    //  MCI AVANCE POR PROCESO (Barras Horizontales)
    // ────────────────────────────────────────────────
    updateMciBarHorizontal(detalles) {
        if (!this.mciBarHorizontal || !detalles) return;
        
        // El excel muestra de arriba hacia abajo, ECharts por defecto es de abajo hacia arriba en barras horizontales
        // Invertimos el array para que aparezcan en el mismo orden visual
        const rev = [...detalles].reverse();
        const labels = rev.map(d => d.nombre.toUpperCase());
        const mci = rev.map(d => d.mci_anual);
        const estimado = rev.map(d => d.estimado);
        const ejecutado = rev.map(d => d.ejecutado);

        this.mciBarHorizontal.setOption({
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                borderColor: 'rgba(99, 102, 241, 0.3)',
                borderWidth: 1,
                padding: [12, 16],
                textStyle: { color: '#F8FAFC', fontFamily: 'Outfit' },
                extraCssText: 'box-shadow: 0 8px 32px rgba(0,0,0,0.6); backdrop-filter: blur(8px); border-radius: 12px;',
                formatter(params) {
                    let h = `<div style="font-weight:700;margin-bottom:8px;color:#a5b4fc;font-size:13px;">${params[0].axisValue}</div>`;
                    params.forEach(p => {
                        const dot = `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${p.color.colorStops ? p.color.colorStops[0].color : p.color};margin-right:8px;box-shadow:0 0 5px ${p.color.colorStops ? p.color.colorStops[0].color : p.color};"></span>`;
                        h += `<div style="display:flex;justify-content:space-between;gap:24px;font-size:12px;margin:4px 0">
                            <span style="display:flex;align-items:center;color:#CBD5E1;">${dot}${p.seriesName}</span>
                            <b style="color:#F8FAFC">$${Number(p.value).toLocaleString('es-MX',{minimumFractionDigits:0})}</b>
                        </div>`;
                    });
                    return h;
                }
            },
            legend: {
                data: ['EJECUTADO', 'ESTIMADO A MAYO', 'MCI'],
                bottom: 0,
                textStyle: { color: '#94A3B8', fontSize: 11, fontFamily: 'Outfit' },
                itemGap: 20,
                icon: 'roundRect'
            },
            grid: { left: '2%', right: '6%', bottom: '15%', top: '5%', containLabel: true },
            xAxis: {
                type: 'value',
                axisLabel: { 
                    color: '#64748b', 
                    fontSize: 10, 
                    fontFamily: 'Outfit',
                    formatter: v => {
                        if (v >= 1000000) return '$' + (v / 1000000).toFixed(1) + 'M';
                        if (v >= 1000) return '$' + (v / 1000).toFixed(0) + 'K';
                        return '$' + v;
                    }
                },
                splitLine: { lineStyle: { color: 'rgba(255,255,255,0.03)', type: 'dashed' } }
            },
            yAxis: {
                type: 'category',
                data: labels,
                axisLabel: { color: '#e2e8f0', fontSize: 10, fontFamily: 'Outfit', fontWeight: '500' },
                axisLine: { lineStyle: { color: 'rgba(255,255,255,0.08)' } },
                axisTick: { show: false }
            },
            series: [
                {
                    name: 'EJECUTADO',
                    type: 'bar',
                    data: ejecutado,
                    barWidth: '20%',
                    barGap: '15%',
                    itemStyle: { 
                        color: new echarts.graphic.LinearGradient(1, 0, 0, 0, [
                            { offset: 0, color: '#10B981' }, // Verde brillante
                            { offset: 1, color: '#047857' }  // Verde oscuro
                        ]),
                        borderRadius: [0,4,4,0],
                        shadowBlur: 8,
                        shadowColor: 'rgba(16, 185, 129, 0.4)'
                    },
                    emphasis: { itemStyle: { shadowBlur: 15, shadowColor: 'rgba(16, 185, 129, 0.8)' } }
                },
                {
                    name: 'ESTIMADO A MAYO',
                    type: 'bar',
                    data: estimado,
                    barWidth: '20%',
                    barGap: '15%',
                    itemStyle: { 
                        color: new echarts.graphic.LinearGradient(1, 0, 0, 0, [
                            { offset: 0, color: '#F59E0B' }, // Ambar
                            { offset: 1, color: '#B45309' }  // Naranja oscuro
                        ]),
                        borderRadius: [0,4,4,0],
                        shadowBlur: 8,
                        shadowColor: 'rgba(245, 158, 11, 0.4)'
                    },
                    emphasis: { itemStyle: { shadowBlur: 15, shadowColor: 'rgba(245, 158, 11, 0.8)' } }
                },
                {
                    name: 'MCI',
                    type: 'bar',
                    data: mci,
                    barWidth: '20%',
                    barGap: '15%',
                    itemStyle: { 
                        color: new echarts.graphic.LinearGradient(1, 0, 0, 0, [
                            { offset: 0, color: '#6366F1' }, // Indigo
                            { offset: 1, color: '#4338CA' }  // Indigo oscuro
                        ]),
                        borderRadius: [0,4,4,0],
                        shadowBlur: 8,
                        shadowColor: 'rgba(99, 102, 241, 0.4)'
                    },
                    emphasis: { itemStyle: { shadowBlur: 15, shadowColor: 'rgba(99, 102, 241, 0.8)' } }
                }
            ]
        }, true);
    },

    // ──────────────────────────────────────────────
    //  1. VELOCÍMETRO (Gauge)
    // ──────────────────────────────────────────────
    updateGauge(porcentaje, semaforo) {
        if (!this.gauge) return;

        const colors = {
            verde:    { main: '#10B981', shadow: 'rgba(16,185,129,0.5)'  },
            amarillo: { main: '#F59E0B', shadow: 'rgba(245,158,11,0.5)' },
            rojo:     { main: '#EF4444', shadow: 'rgba(239,68,68,0.5)'  }
        };
        const c = colors[semaforo] || colors.verde;
        const gaugeVal = Math.min(porcentaje, 120);

        const option = {
            backgroundColor: 'transparent',
            series: [
                // Aro exterior decorativo
                {
                    type: 'gauge',
                    startAngle: 200, endAngle: -20,
                    min: 0, max: 100,
                    radius: '98%', center: ['50%','62%'],
                    splitNumber: 0,
                    axisLine: {
                        lineStyle: {
                            width: 3,
                            color: [[1, 'rgba(99,102,241,0.08)']]
                        }
                    },
                    axisTick: { show: false },
                    splitLine: { show: false },
                    axisLabel: { show: false },
                    progress: { show: false },
                    pointer: { show: false },
                    detail: { show: false }
                },
                // Aro de progreso principal
                {
                    type: 'gauge',
                    startAngle: 200, endAngle: -20,
                    min: 0, max: 100,
                    radius: '88%', center: ['50%','62%'],
                    splitNumber: 5,
                    axisLine: {
                        lineStyle: {
                            width: 14,
                            color: [
                                [0.8,  'rgba(16,185,129,0.12)'],
                                [0.95, 'rgba(245,158,11,0.12)'],
                                [1,    'rgba(239,68,68,0.12)']
                            ]
                        }
                    },
                    progress: {
                        show: true, width: 14,
                        itemStyle: {
                            color: new echarts.graphic.LinearGradient(0,0,1,0,[
                                { offset: 0,   color: '#6366f1' },
                                { offset: 0.6, color: c.main    },
                                { offset: 1,   color: c.main    }
                            ]),
                            shadowColor: c.shadow,
                            shadowBlur: 16
                        }
                    },
                    pointer: {
                        show: true,
                        icon: 'path://M12.8,0.7l12,85.3c0.6,3.9-2.2,7.5-6.1,7.9c-0.4,0-0.8,0.1-1.2,0.1H4.5c-3.9,0-7.1-3.2-7.1-7.1c0-0.4,0-0.8,0.1-1.2l12-85.3C10,1.2,12.3,1.2,12.8,0.7z',
                        length: '72%', width: 6,
                        offsetCenter: [0, -5],
                        itemStyle: { color: '#F8FAFC', shadowBlur: 8, shadowColor: 'rgba(248,250,252,0.4)' }
                    },
                    axisTick: { distance: 14, lineStyle: { color: '#334155', width: 1 } },
                    splitLine: { distance: 14, length: 10, lineStyle: { color: '#64748B', width: 2 } },
                    axisLabel: {
                        distance: 26, color: '#64748B', fontSize: 10, fontFamily: 'Outfit',
                        formatter: v => v + '%'
                    },
                    anchor: {
                        show: true, showAbove: true, size: 18,
                        itemStyle: { borderWidth: 4, borderColor: c.main, color: '#050816', shadowBlur: 10, shadowColor: c.shadow }
                    },
                    title: { show: false },
                    detail: {
                        valueAnimation: true,
                        fontSize: 28, fontWeight: '800', fontFamily: 'Outfit',
                        offsetCenter: [0, '30%'],
                        formatter: v => `{val|${v.toFixed(0)}}{pct|%}`,
                        rich: {
                            val: { fontSize: 36, fontWeight: '900', color: '#F8FAFC', fontFamily: 'Outfit' },
                            pct: { fontSize: 18, color: '#94A3B8', padding: [0,0,8,4] }
                        }
                    },
                    data: [{ value: gaugeVal }]
                }
            ]
        };
        this.gauge.setOption(option);
    },

    // ──────────────────────────────────────────────
    //  2. TENDENCIA → STACKED BAR POR CATEGORÍA
    //     vista: 'mes'  → etiquetas son días
    //            'anio' → etiquetas son meses
    // ──────────────────────────────────────────────
    updateTrendStacked(stackedData, vista = 'mes') {
        if (!this.trend) return;

        const viewData = vista === 'anio' ? stackedData.vista_anio : stackedData.vista_mes;
        if (!viewData || !viewData.series) {
            this.trend.clear();
            return;
        }

        const { labels, series } = viewData;

        // Filtrar series vacías
        const seriesActivas = series.filter(s => s.datos.some(v => v > 0));
        if (seriesActivas.length === 0) {
            this.trend.setOption({
                backgroundColor: 'transparent',
                graphic: [{ type:'text', left:'center', top:'middle',
                    style:{ text:'Sin gastos en este período', fill:'#475569', fontSize:14, fontFamily:'Outfit' } }]
            }, true);
            return;
        }

        // Calcular el total para la línea de tendencia general
        const totales = labels.map((_, i) => seriesActivas.reduce((sum, s) => sum + Number(s.datos[i] || 0), 0));

        const chartTitle = vista === 'anio'
            ? 'Tendencia Anual de Gastos por Categoría'
            : 'Tendencia Mensual de Gastos por Categoría';

        const option = {
            backgroundColor: 'transparent',
            title: {
                text: chartTitle,
                left: 'center',
                textStyle: { color: 'rgba(148,163,184,0.7)', fontSize: 11, fontFamily: 'Outfit', fontWeight: '500' },
                top: 2
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                backgroundColor: 'rgba(5,8,22,0.94)',
                borderColor: 'rgba(99,102,241,0.35)',
                borderWidth: 1,
                padding: [12, 16],
                extraCssText: 'box-shadow:0 8px 32px rgba(0,0,0,0.6);backdrop-filter:blur(12px);border-radius:14px;',
                textStyle: { color: '#F8FAFC', fontFamily: 'Plus Jakarta Sans', fontSize: 12 },
                formatter(params) {
                    let total = 0;
                    let rows = '';
                    params.forEach(p => {
                        if (!p.value || p.value === 0) return;
                        total += Number(p.value);
                        const dot = `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${p.color};margin-right:6px;flex-shrink:0"></span>`;
                        rows += `<div style="display:flex;justify-content:space-between;gap:20px;font-size:11px;margin:3px 0">
                            <span style="display:flex;align-items:center">${dot}${p.seriesName}</span>
                            <b>$${Number(p.value).toLocaleString('es-MX',{minimumFractionDigits:0})}</b>
                        </div>`;
                    });
                    const label = vista === 'anio' ? params[0].name : `Día ${params[0].name}`;
                    return `<div style="font-weight:700;margin-bottom:8px;font-family:Outfit;color:#a5b4fc">${label}</div>
                            ${rows}
                            <div style="margin-top:6px;padding-top:6px;border-top:1px solid rgba(255,255,255,0.1);font-size:11px;color:#e2e8f0;font-weight:700">
                              Total: $${total.toLocaleString('es-MX',{minimumFractionDigits:0})}
                            </div>`;
                }
            },
            legend: {
                type: 'scroll',
                data: seriesActivas.map(s => s.nombre),
                bottom: 0,
                textStyle: { color: '#94A3B8', fontFamily: 'Outfit', fontSize: 10 },
                icon: 'roundRect',
                pageIconColor: '#818cf8',
                pageTextStyle: { color: '#94A3B8' }
            },
            grid: { left: '1%', right: '1%', bottom: '14%', top: '14%', containLabel: true },
            xAxis: {
                type: 'category',
                data: labels,
                boundaryGap: true,
                axisLine: { lineStyle: { color: 'rgba(255,255,255,0.04)' } },
                axisTick: { show: false },
                axisLabel: {
                    color: '#475569',
                    fontFamily: 'Outfit',
                    fontSize: 9,
                    interval: vista === 'mes' ? Math.max(0, Math.floor(labels.length / 10) - 1) : 0
                }
            },
            yAxis: {
                type: 'value',
                splitLine: { lineStyle: { color: 'rgba(255,255,255,0.03)', type: 'dashed' } },
                axisLabel: {
                    color: '#475569',
                    fontFamily: 'Outfit',
                    fontSize: 9,
                    formatter: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v >= 1000 ? (v/1000).toFixed(0)+'k' : v
                }
            },
            series: [
                ...seriesActivas.map(s => ({
                    name: s.nombre,
                    type: 'bar',
                    stack: 'total',
                    data: s.datos,
                    itemStyle: {
                        color: s.color,
                        borderRadius: [2, 2, 0, 0], // Bordes sutilmente redondeados
                        opacity: 0.85 // Ligeramente transparente
                    },
                    emphasis: {
                        focus: 'series',
                        itemStyle: { opacity: 1, shadowBlur: 12, shadowColor: s.color + '66' }
                    },
                    barMaxWidth: vista === 'anio' ? 45 : 25
                })),
                {
                    name: 'Total General',
                    type: 'line',
                    smooth: true,
                    data: totales,
                    symbol: 'circle',
                    symbolSize: 8,
                    itemStyle: { color: '#FCD34D', shadowBlur: 10, shadowColor: 'rgba(252,211,77,0.8)' },
                    lineStyle: { width: 3, shadowBlur: 10, shadowColor: 'rgba(252,211,77,0.4)', type: 'dashed' },
                    z: 10 // Para que se dibuje por encima de las barras
                }
            ]
        };

        this.trend.setOption(option, true);
        
        // Recalcular la línea de Total General si el usuario oculta/muestra categorías en la leyenda
        this.trend.off('legendselectchanged');
        this.trend.on('legendselectchanged', params => {
            const selected = params.selected;
            const newTotales = labels.map((_, i) => {
                return seriesActivas.reduce((sum, s) => {
                    // Solo sumar si la categoría no ha sido desactivada
                    if (selected[s.nombre] !== false) {
                        return sum + Number(s.datos[i] || 0);
                    }
                    return sum;
                }, 0);
            });
            
            this.trend.setOption({
                series: [{
                    name: 'Total General',
                    data: newTotales
                }]
            });
        });
        
        // Evento dblclick para mostrar el desglose de motivos (Drill-down) en la gráfica de barras
        this.trend.off('dblclick');
        this.trend.on('dblclick', params => {
            if (this.isTrendDrilldown) return;
            if (params.seriesType === 'bar' && params.seriesName !== 'Total General') {
                const categoriaName = params.seriesName;
                const periodo = document.getElementById('global-period-picker').value;
                const filterVista = typeof currentFiltro !== 'undefined' ? currentFiltro : 'mes';
                
                this.trend.showLoading({ text: 'Cargando motivos...', color: '#6366f1', textColor: '#94A3B8', maskColor: 'rgba(5, 8, 22, 0.8)' });
                
                fetch(`api.php?action=get_tendencia_detalle_categoria&categoria=${encodeURIComponent(categoriaName)}&periodo=${periodo}&vista=${filterVista}&_t=${Date.now()}`)
                    .then(r => r.json())
                    .then(d => {
                        this.trend.hideLoading();
                        if (d.success && d.tendencia_stacked) {
                            this.isTrendDrilldown = true;
                            document.getElementById('btn-back-trend-container').style.display = 'block';
                            this.updateTrendDetalle(d.tendencia_stacked, categoriaName, filterVista);
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching detalle tendencia:', err);
                        this.trend.hideLoading();
                    });
            }
        });

        setTimeout(() => this.trend?.resize(), 100);
    },

    // Renderizar tendencia de conceptos para una categoría (Drill-down)
    updateTrendDetalle(stackedData, categoriaName, vista = 'mes') {
        if (!this.trend) return;
        
        const viewData = vista === 'anio' ? stackedData.vista_anio : stackedData.vista_mes;
        if (!viewData || !viewData.series) {
            this.trend.clear();
            return;
        }

        const { labels, series } = viewData;

        // Filtrar series vacías
        const seriesActivas = series.filter(s => s.datos.some(v => v > 0));
        if (seriesActivas.length === 0) {
            this.trend.setOption({
                backgroundColor: 'transparent',
                graphic: [{ type:'text', left:'center', top:'middle',
                    style:{ text:'Sin gastos en este período', fill:'#475569', fontSize:14, fontFamily:'Outfit' } }]
            }, true);
            return;
        }

        const chartTitle = `Tendencia de Motivos: ${categoriaName}`;

        const option = {
            backgroundColor: 'transparent',
            title: {
                text: chartTitle,
                left: 'center',
                textStyle: { color: '#F8FAFC', fontSize: 13, fontFamily: 'Outfit', fontWeight: 'bold' },
                top: 2
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                backgroundColor: 'rgba(5,8,22,0.94)',
                borderColor: 'rgba(99,102,241,0.35)',
                borderWidth: 1,
                padding: [12, 16],
                extraCssText: 'box-shadow:0 8px 32px rgba(0,0,0,0.6);backdrop-filter:blur(12px);border-radius:14px;',
                textStyle: { color: '#F8FAFC', fontFamily: 'Plus Jakarta Sans', fontSize: 12 },
                formatter(params) {
                    let total = 0;
                    let rows = '';
                    params.forEach(p => {
                        if (!p.value || p.value === 0) return;
                        total += p.value;
                        const dot = `<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${p.color};margin-right:6px;box-shadow:0 0 5px ${p.color}"></span>`;
                        rows += `<div style="display:flex;justify-content:space-between;gap:24px;font-size:11px;margin:4px 0">
                            <span style="color:#CBD5E1;display:flex;align-items:center;">${dot}${p.seriesName}</span>
                            <b style="color:#F8FAFC">$${Number(p.value).toLocaleString('es-MX',{minimumFractionDigits:2})}</b>
                        </div>`;
                    });
                    
                    if (total === 0) return '';
                    
                    let h = `<div style="font-weight:700;margin-bottom:8px;font-family:Outfit;color:#a5b4fc;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:4px;">
                        ${vista === 'anio' ? 'Mes: ' : 'Día '}${params[0].axisValue}
                    </div>`;
                    h += rows;
                    h += `<div style="display:flex;justify-content:space-between;gap:24px;font-size:12px;margin-top:8px;padding-top:6px;border-top:1px solid rgba(255,255,255,0.1)">
                        <span style="color:#94A3B8">Total del periodo:</span>
                        <b style="color:#10B981">$${Number(total).toLocaleString('es-MX',{minimumFractionDigits:2})}</b>
                    </div>`;
                    return h;
                }
            },
            legend: {
                type: 'scroll',
                data: seriesActivas.map(s => s.nombre),
                bottom: 0,
                textStyle: { color: '#94A3B8', fontFamily: 'Outfit', fontSize: 10 },
                icon: 'roundRect',
                pageIconColor: '#818cf8',
                pageTextStyle: { color: '#94A3B8' }
            },
            grid: { left: '1%', right: '1%', bottom: '14%', top: '14%', containLabel: true },
            xAxis: {
                type: 'category',
                data: labels,
                boundaryGap: true,
                axisLine: { lineStyle: { color: 'rgba(255,255,255,0.04)' } },
                axisTick: { show: false },
                axisLabel: {
                    color: '#475569',
                    fontFamily: 'Outfit',
                    fontSize: 9,
                    interval: vista === 'mes' ? Math.max(0, Math.floor(labels.length / 10) - 1) : 0
                }
            },
            yAxis: {
                type: 'value',
                splitLine: { lineStyle: { color: 'rgba(255,255,255,0.03)', type: 'dashed' } },
                axisLabel: {
                    color: '#475569',
                    fontFamily: 'Outfit',
                    fontSize: 9,
                    formatter: v => v >= 1000000 ? (v/1000000).toFixed(1)+'M' : v >= 1000 ? (v/1000).toFixed(0)+'k' : v
                }
            },
            series: [
                ...seriesActivas.map(s => ({
                    name: s.nombre,
                    type: 'bar',
                    stack: 'total',
                    data: s.datos,
                    itemStyle: {
                        borderRadius: [2, 2, 0, 0],
                        opacity: 0.85
                    },
                    emphasis: {
                        focus: 'series',
                        itemStyle: { opacity: 1, shadowBlur: 12 }
                    },
                    barMaxWidth: vista === 'anio' ? 45 : 25
                })),
                {
                    name: 'Total Categoría',
                    type: 'line',
                    data: labels.map((_, i) => seriesActivas.reduce((sum, s) => sum + (s.datos[i] || 0), 0)),
                    smooth: true,
                    itemStyle: { color: '#FCD34D', shadowBlur: 10, shadowColor: 'rgba(252,211,77,0.8)' },
                    lineStyle: { width: 3, shadowBlur: 10, shadowColor: 'rgba(252,211,77,0.4)', type: 'dashed' },
                    z: 10
                }
            ]
        };

        this.trend.setOption(option, true);
    },

    // Volver a la gráfica de tendencia original
    backToTrendCategories() {
        this.isTrendDrilldown = false;
        document.getElementById('btn-back-trend-container').style.display = 'none';
        
        const vista = typeof currentFiltro !== 'undefined' ? currentFiltro : 'mes';
        
        if (typeof lastTendenciaStacked !== 'undefined' && lastTendenciaStacked) {
            this.updateTrendStacked(lastTendenciaStacked, vista);
        } else {
            if (typeof refreshDashboardData === 'function') refreshDashboardData();
        }
    },

    // Compat: mantener updateTrend para no romper código existente
    updateTrend(dias, diario, acumulado) {
        // No-op: la gráfica usa updateTrendStacked ahora
    },

    // ──────────────────────────────────────────────
    //  3. HISTÓRICO MENSUAL (Barras Agrupadas)
    // ──────────────────────────────────────────────
    updateBar(mesesData) {
        if (!this.bar) return;

        const meses    = mesesData.map(m => m.nombre_mes);
        const ingresos = mesesData.map(m => m.ingresos);
        const gastos   = mesesData.map(m => m.gastos);
        const balance  = mesesData.map(m => m.ingresos - m.gastos);

        const option = {
            backgroundColor: 'transparent',
            tooltip: {
                trigger:'axis', axisPointer:{ type:'shadow' },
                backgroundColor:'rgba(5,8,22,0.92)', borderColor:'rgba(99,102,241,0.3)', borderWidth:1,
                padding:[12,16], extraCssText:'box-shadow:0 8px 32px rgba(0,0,0,0.6);backdrop-filter:blur(12px);border-radius:14px;',
                textStyle:{ color:'#F8FAFC', fontFamily:'Plus Jakarta Sans', fontSize:12 },
                formatter(params) {
                    let h = `<div style="font-weight:700;margin-bottom:6px;font-family:Outfit;color:#a5b4fc">${params[0].axisValue}</div>`;
                    params.forEach(p => {
                        const dot = `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${p.color};margin-right:6px;"></span>`;
                        const color = p.value >= 0 ? '#F8FAFC' : '#FCA5A5';
                        h += `<div style="display:flex;justify-content:space-between;gap:24px;font-size:11px;margin:3px 0">
                            <span>${dot}${p.seriesName}</span>
                            <b style="color:${color}">$${Number(p.value).toLocaleString('es-MX',{minimumFractionDigits:2})}</b>
                        </div>`;
                    });
                    return h;
                }
            },
            legend: {
                data:['Ingresos','Gastos','Balance Neto'],
                textStyle:{ color:'#94A3B8', fontFamily:'Outfit', fontSize:11 },
                right:'2%', icon:'circle', top:4
            },
            grid: { left:'1%', right:'2%', bottom:'4%', top:'14%', containLabel:true },
            xAxis: [{
                type:'category', data:meses, axisTick:{show:false},
                axisLine:{ lineStyle:{color:'rgba(255,255,255,0.04)'} },
                axisLabel:{ color:'#475569', fontFamily:'Outfit', fontSize:10 }
            }],
            yAxis: [{
                type:'value',
                splitLine:{ lineStyle:{color:'rgba(255,255,255,0.03)',type:'dashed'} },
                axisLabel:{ color:'#475569', fontFamily:'Outfit', fontSize:9, formatter: v => v>=1000?(v/1000).toFixed(0)+'k':v }
            }],
            series: [
                {
                    name:'Ingresos', type:'bar', barWidth:'28%', data:ingresos,
                    itemStyle:{
                        color: new echarts.graphic.LinearGradient(0,0,0,1,[
                            {offset:0, color:'#34d399'}, {offset:1, color:'#065f4644'}
                        ]),
                        borderRadius:[6,6,0,0]
                    },
                    emphasis:{ focus:'series', itemStyle:{shadowBlur:20,shadowColor:'rgba(52,211,153,0.5)'} }
                },
                {
                    name:'Gastos', type:'bar', barWidth:'28%', data:gastos,
                    itemStyle:{
                        color: new echarts.graphic.LinearGradient(0,0,0,1,[
                            {offset:0, color:'#818cf8'}, {offset:1, color:'#1e1b4b44'}
                        ]),
                        borderRadius:[6,6,0,0]
                    },
                    emphasis:{ focus:'series', itemStyle:{shadowBlur:20,shadowColor:'rgba(129,140,248,0.5)'} }
                },
                {
                    name:'Balance Neto', type:'line', data:balance,
                    smooth:0.4, showSymbol:true, symbolSize:6,
                    lineStyle:{ width:2.5, color:'#fb923c', shadowBlur:12, shadowColor:'rgba(251,146,60,0.4)' },
                    itemStyle:{ color:'#fb923c', borderWidth:2, borderColor:'#050816' },
                    areaStyle:{
                        color: new echarts.graphic.LinearGradient(0,0,0,1,[
                            {offset:0, color:'rgba(251,146,60,0.15)'},{offset:1, color:'rgba(251,146,60,0)'}
                        ])
                    }
                }
            ]
        };
        this.bar.setOption(option);
    },

    // ──────────────────────────────────────────────
    //  4. DONUT DE CATEGORÍAS (Pie Neón)
    // ──────────────────────────────────────────────
    updateDonut(categorias) {
        if (!this.donut) return;

        // Validar que categorias sea un array
        const categoriasArray = Array.isArray(categorias) ? categorias : [];
        
        const data = categoriasArray
            .filter(c => c && typeof c === 'object' && parseFloat(c.total) > 0)
            .map(c => ({
                name:  c.nombre || 'Sin nombre',
                value: parseFloat(c.total) || 0,
                itemStyle: {
                    color: c.color || '#6366f1',
                    shadowBlur: 12,
                    shadowColor: (c.color || '#6366f1') + '66'
                }
            }));

        if (data.length === 0) {
            // Mostrar estado vacío elegante en lugar de limpiar
            this.donut.setOption({
                backgroundColor: 'transparent',
                title: {
                    text: 'Sin gastos registrados',
                    left: 'center',
                    top: 'center',
                    textStyle: {
                        color: '#64748B',
                        fontSize: 14,
                        fontFamily: 'Outfit',
                        fontWeight: 'normal'
                    }
                },
                graphic: [{
                    type: 'text',
                    left: 'center', 
                    top: 'middle',
                    style: { 
                        text: 'Agrega gastos para visualizar distribución', 
                        fill: '#475569', 
                        fontSize: 12, 
                        fontFamily: 'Outfit' 
                    }
                }]
            });
            setTimeout(() => this.donut?.resize(), 100);
            return;
        }

        const total = data.reduce((a, b) => a + b.value, 0);

        const option = {
            backgroundColor: 'transparent',
            tooltip: {
                trigger:'item',
                backgroundColor:'rgba(5,8,22,0.92)', 
                borderColor:'rgba(99,102,241,0.3)', 
                borderWidth:1,
                padding:[12,16], 
                extraCssText:'box-shadow:0 8px 32px rgba(0,0,0,0.6);backdrop-filter:blur(12px);border-radius:14px;',
                textStyle:{ color:'#F8FAFC', fontFamily:'Plus Jakarta Sans', fontSize:12 },
                formatter(p) {
                    if (!p.value) return '';
                    return `<div style="font-weight:700;margin-bottom:4px;font-family:Outfit;color:${p.color}">${p.name}</div>
                            <div style="font-size:11px"><b>$${Number(p.value).toLocaleString('es-MX',{minimumFractionDigits:2})}</b></div>
                            <div style="font-size:11px;color:#94A3B8">${(p.percent || 0).toFixed(1)}% del gasto total</div>`;
                }
            },
            legend: {
                orient:'vertical', 
                right:'2%', 
                top:'center',
                textStyle:{ color:'#94A3B8', fontFamily:'Outfit', fontSize:10 },
                formatter: name => {
                    const item = data.find(d => d.name === name);
                    if (!item) return name;
                    const pct = total > 0 ? ((item.value/total)*100).toFixed(0) : 0;
                    return `${name}  ${pct}%`;
                }
            },
            series: [{
                name:'Distribución',
                type:'pie',
                radius:['42%','70%'],
                center:['38%','50%'],
                avoidLabelOverlap: true,
                itemStyle: { borderRadius:6, borderColor:'rgba(5,8,22,0.6)', borderWidth:2 },
                label: {
                    show: true, 
                    position:'outside',
                    color:'#94A3B8', 
                    fontFamily:'Outfit', 
                    fontSize:10,
                    formatter: p => `${(p.percent || 0).toFixed(0)}%`
                },
                labelLine: { length:10, length2:8, lineStyle:{ color:'rgba(148,163,184,0.4)' } },
                emphasis: {
                    itemStyle:{ shadowBlur:24, shadowOffsetX:0, shadowColor:'rgba(0,0,0,0.5)', scale:true, scaleSize:6 },
                    label:{ show:true, fontSize:13, fontWeight:'700' }
                },
                data
            }]
        };
        this.donut.setOption(option);
        
        // Remover evento previo para no duplicar
        this.donut.off('legendselectchanged');
        
        // Manejar el clic en la leyenda para recalcular porcentajes dinámicos
        this.donut.on('legendselectchanged', params => {
            const selected = params.selected;
            const activeData = data.filter(d => selected[d.name] !== false);
            const newTotal = activeData.reduce((sum, item) => sum + item.value, 0);
            
            this.donut.setOption({
                legend: {
                    formatter: name => {
                        const item = data.find(d => d.name === name);
                        if (!item) return name;
                        if (selected[name] === false) return `${name}  0%`;
                        const pct = newTotal > 0 ? ((item.value/newTotal)*100).toFixed(0) : 0;
                        return `${name}  ${pct}%`;
                    }
                }
            });
        });
        
        // Evento Click para Drill-down
        this.donut.off('click');
        this.donut.on('click', params => {
            // Si ya estamos en una vista de detalle (no hay datos en gastos_categoria global), ignorar
            if (this.isDonutDrilldown) return;
            
            const categoriaName = params.name;
            const periodo = document.getElementById('global-period-picker').value;
            const vista = typeof currentFiltro !== 'undefined' ? currentFiltro : 'mes';
            
            // Mostrar indicador de carga en el contenedor del donut
            this.donut.showLoading({ text: 'Cargando detalle...', color: '#6366f1', textColor: '#94A3B8', maskColor: 'rgba(5, 8, 22, 0.8)' });
            
            fetch(`api.php?action=get_detalle_categoria&categoria=${encodeURIComponent(categoriaName)}&periodo=${periodo}&vista=${vista}&_t=${Date.now()}`)
                .then(r => r.json())
                .then(d => {
                    this.donut.hideLoading();
                    if (d.success && d.detalle) {
                        this.isDonutDrilldown = true;
                        document.getElementById('btn-back-donut-container').style.display = 'block';
                        this.updateDonutDetalle(d.detalle, categoriaName);
                    }
                })
                .catch(err => {
                    this.donut.hideLoading();
                    console.error('Error cargando detalle:', err);
                });
        });

        setTimeout(() => this.donut?.resize(), 100);
    },

    // Renderizar dona con el detalle de conceptos
    updateDonutDetalle(detalleData, categoriaName) {
        if (!this.donut) return;
        
        const data = detalleData
            .filter(c => parseFloat(c.total) > 0)
            .map(c => ({
                name: c.nombre || 'Sin concepto',
                value: parseFloat(c.total),
                itemStyle: {
                    color: c.color || '#6366f1',
                    shadowBlur: 12,
                    shadowColor: (c.color || '#6366f1') + '66'
                }
            }));
            
        const total = data.reduce((a, b) => a + b.value, 0);

        const option = {
            backgroundColor: 'transparent',
            title: {
                text: categoriaName,
                subtext: 'Detalle por concepto',
                left: 'center',
                top: 'center',
                textStyle: { color: '#F8FAFC', fontSize: 13, fontFamily: 'Outfit', fontWeight: 'bold' },
                subtextStyle: { color: '#94A3B8', fontSize: 10, fontFamily: 'Outfit' }
            },
            tooltip: {
                trigger:'item',
                backgroundColor:'rgba(5,8,22,0.92)', 
                borderColor:'rgba(99,102,241,0.3)', 
                borderWidth:1,
                padding:[12,16], 
                extraCssText:'box-shadow:0 8px 32px rgba(0,0,0,0.6);backdrop-filter:blur(12px);border-radius:14px;',
                textStyle:{ color:'#F8FAFC', fontFamily:'Plus Jakarta Sans', fontSize:12 },
                formatter(p) {
                    if (!p.value) return '';
                    return `<div style="font-weight:700;margin-bottom:4px;font-family:Outfit;color:${p.color}">${p.name}</div>
                            <div style="font-size:11px"><b>$${Number(p.value).toLocaleString('es-MX',{minimumFractionDigits:2})}</b></div>
                            <div style="font-size:11px;color:#94A3B8">${(p.percent || 0).toFixed(1)}% de ${categoriaName}</div>`;
                }
            },
            legend: {
                orient:'vertical', 
                right:'2%', 
                top:'center',
                textStyle:{ color:'#94A3B8', fontFamily:'Outfit', fontSize:10 },
                formatter: name => {
                    const item = data.find(d => d.name === name);
                    if (!item) return name;
                    const pct = total > 0 ? ((item.value/total)*100).toFixed(0) : 0;
                    return `${name}  ${pct}%`;
                }
            },
            series: [{
                name:'Detalle',
                type:'pie',
                radius:['48%', '75%'],
                center:['38%','50%'],
                avoidLabelOverlap: true,
                itemStyle: { borderRadius:4, borderColor:'rgba(5,8,22,0.6)', borderWidth:2 },
                label: { show: false },
                labelLine: { show: false },
                emphasis: {
                    itemStyle:{ shadowBlur:24, shadowOffsetX:0, shadowColor:'rgba(0,0,0,0.5)', scale:true, scaleSize:6 }
                },
                data
            }]
        };
        
        this.donut.setOption(option, true);
    },

    // Volver a la gráfica de categorías original
    backToCategories() {
        this.isDonutDrilldown = false;
        document.getElementById('btn-back-donut-container').style.display = 'none';
        
        // Usar los datos globales si existen
        if (typeof lastDashboardData !== 'undefined' && lastDashboardData.gastos_categoria) {
            this.updateDonut(lastDashboardData.gastos_categoria);
        } else {
            // Recargar datos globales
            if (typeof refreshDashboardData === 'function') {
                refreshDashboardData();
            }
        }
    },

    // ──────────────────────────────────────────────
    //  5. RADAR DE KPIs
    // ──────────────────────────────────────────────
    updateRadar(kpis) {
        if (!this.radar) return;

        // Validar que kpis sea un objeto
        if (!kpis || typeof kpis !== 'object') {
            this.radar.setOption({
                backgroundColor: 'transparent',
                graphic: [{
                    type: 'text',
                    left: 'center',
                    top: 'middle',
                    style: {
                        text: 'Sin datos de KPIs disponibles',
                        fill: '#475569',
                        fontSize: 12,
                        fontFamily: 'Outfit'
                    }
                }]
            });
            return;
        }

        // Normalizar cada métrica entre 0-100 de forma robusta
        const pres = Math.max(parseFloat(kpis.presupuesto) || 0, 1); // evitar división por 0

        // Eficiencia: ratio ingreso/gasto histórico (óptimo = 2x → 100 pts)
        const eficienciaNorm = Math.min((Math.max(parseFloat(kpis.eficiencia) || 0, 0) / 2) * 100, 100);

        // Control de gasto: qué tan por debajo estamos del presupuesto (invertido)
        const consumoInv = Math.max(100 - (parseFloat(kpis.porcentaje_usado) || 0), 0);

        // Cobertura de ingresos vs presupuesto mensual
        const ingresoNorm = Math.min((Math.max(parseFloat(kpis.ingreso_total) || 0, 0) / pres) * 100, 100);

        // Proyección: si proyección <= presupuesto = 100, si excede = proporcional
        const proyMes = parseFloat(kpis.proyeccion_fin_mes) || 0;
        const proyRatio = proyMes > 0
            ? Math.max(100 - (((proyMes - pres) / pres) * 100), 0)
            : 100;
        const proyNorm = Math.min(proyRatio, 100);

        // Saldo disponible como % del presupuesto (0-100)
        const saldoNorm = Math.min(Math.max((Math.max(parseFloat(kpis.dinero_restante) || 0, 0) / pres) * 100, 0), 100);

        // Valores finales garantizados entre 0 y 100
        const valores = [
            Math.round(eficienciaNorm),
            Math.round(saldoNorm),
            Math.round(consumoInv),
            Math.round(proyNorm),
            Math.round(ingresoNorm)
        ];

        const option = {
            backgroundColor: 'transparent',
            tooltip: {
                trigger:'item',
                backgroundColor:'rgba(5,8,22,0.92)', 
                borderColor:'rgba(99,102,241,0.3)', 
                borderWidth:1,
                padding:[12,16], 
                extraCssText:'box-shadow:0 8px 32px rgba(0,0,0,0.6);backdrop-filter:blur(12px);border-radius:14px;',
                textStyle:{ color:'#F8FAFC', fontFamily:'Plus Jakarta Sans', fontSize:12 },
                formatter(p) {
                    const names = ['Eficiencia','Saldo Disp.','Control Gasto','Proyección','Cobertura'];
                    let html = `<div style="font-weight:700;margin-bottom:6px;font-family:Outfit;color:#a5b4fc">Radar de KPIs</div>`;
                    if (Array.isArray(p.value)) {
                        p.value.forEach((v, i) => {
                            html += `<div style="font-size:10px;margin:2px 0"><span style="color:#94A3B8">${names[i] || ''}:</span> <b>${v.toFixed(0)}</b></div>`;
                        });
                    }
                    return html;
                }
            },
            radar: {
                radius: '60%',
                center: ['50%', '55%'],
                splitNumber: 4,
                axisName: {
                    color: '#94A3B8',
                    fontFamily: 'Outfit',
                    fontSize: 10,
                    formatter: function (value) {
                        return '{a|' + value + '}';
                    },
                    rich: {
                        a: { color: '#e2e8f0', align: 'center' }
                    }
                },
                indicator: [
                    { name:'Eficiencia',    max:100 },
                    { name:'Saldo Disp.',   max:100 },
                    { name:'Control Gasto', max:100 },
                    { name:'Proyección',    max:100 },
                    { name:'Cobertura',     max:100 }
                ],
                splitArea: {
                    areaStyle: {
                        color: ['rgba(99,102,241,0.04)','rgba(99,102,241,0.08)','rgba(99,102,241,0.04)','rgba(99,102,241,0.08)','rgba(99,102,241,0.04)']
                    }
                },
                name: { textStyle: { color:'#94A3B8', fontFamily:'Outfit', fontSize:11 } }
            },
            series: [{
                name:'KPIs', 
                type:'radar',
                data: [{
                    value: valores,
                    name: 'Rendimiento',
                    itemStyle: { color:'#818cf8' },
                    lineStyle: { width:2.5, color:'#818cf8', shadowBlur:14, shadowColor:'rgba(129,140,248,0.55)' },
                    areaStyle: {
                        color: new echarts.graphic.LinearGradient(0,0,0,1,[
                            {offset:0, color:'rgba(129,140,248,0.35)'},
                            {offset:1, color:'rgba(129,140,248,0.05)'}
                        ])
                    },
                    symbol:'circle', 
                    symbolSize:7
                }]
            }]
        };
        this.radar.setOption(option);
        setTimeout(() => this.radar?.resize(), 100);
    },

    // ──────────────────────────────────────────────
    //  6. META VS EJECUTADO (Barras Horizontales)
    // ──────────────────────────────────────────────
    updateMetaVsEjecutado(metasData) {
        if (!this.metaVsEjec) return;

        // Filtrar metas que tengan meta anual definida
        const data = metasData.filter(m => m.meta_anual > 0).sort((a,b) => b.pct - a.pct);
        
        if (data.length === 0) {
            this.metaVsEjec.setOption({
                backgroundColor: 'transparent',
                title: {
                    text: 'Sin metas definidas',
                    left: 'center', top: 'center',
                    textStyle: { color: '#64748B', fontSize: 14, fontFamily: 'Outfit', fontWeight: 'normal' }
                }
            });
            return;
        }

        const categorias = data.map(d => d.nombre);
        const ejecutado = data.map(d => d.ejecutado);
        const metas = data.map(d => d.meta_anual);
        const colores = data.map(d => d.color || '#6366f1');

        const option = {
            backgroundColor: 'transparent',
            tooltip: {
                trigger: 'axis', axisPointer: { type: 'shadow' },
                backgroundColor: 'rgba(5,8,22,0.92)', borderColor: 'rgba(99,102,241,0.3)', borderWidth: 1,
                padding: [12,16], extraCssText: 'box-shadow:0 8px 32px rgba(0,0,0,0.6);backdrop-filter:blur(12px);border-radius:14px;',
                textStyle: { color: '#F8FAFC', fontFamily: 'Plus Jakarta Sans', fontSize: 12 },
                formatter(params) {
                    let h = `<div style="font-weight:700;margin-bottom:6px;font-family:Outfit;color:#a5b4fc">${params[0].axisValue}</div>`;
                    params.forEach(p => {
                        const dot = `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${p.color};margin-right:6px;"></span>`;
                        h += `<div style="display:flex;justify-content:space-between;gap:24px;font-size:11px;margin:3px 0">
                            <span>${dot}${p.seriesName}</span>
                            <b>$${Number(p.value).toLocaleString('es-MX',{minimumFractionDigits:2})}</b>
                        </div>`;
                    });
                    const diff = params[1].value - params[0].value;
                    const diffText = diff >= 0 ? `Faltan $${diff.toLocaleString('es-MX',{minimumFractionDigits:2})}` : `Excedido por $${Math.abs(diff).toLocaleString('es-MX',{minimumFractionDigits:2})}`;
                    const diffColor = diff >= 0 ? '#10B981' : '#EF4444';
                    h += `<div style="margin-top:6px;padding-top:6px;border-top:1px solid rgba(255,255,255,0.1);font-size:11px;color:${diffColor};font-weight:700;">${diffText}</div>`;
                    return h;
                }
            },
            legend: {
                data: ['Ejecutado', 'Meta Anual'],
                textStyle: { color: '#94A3B8', fontFamily: 'Outfit', fontSize: 11 },
                top: 0
            },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { type: 'value', splitLine: { lineStyle: { color: 'rgba(255,255,255,0.03)', type: 'dashed' } }, axisLabel: { color: '#475569', fontSize: 10 } },
            yAxis: { type: 'category', data: categorias, axisLine: { lineStyle: { color: 'rgba(255,255,255,0.04)' } }, axisLabel: { color: '#94A3B8', fontFamily: 'Outfit', fontSize: 11, width: 100, overflow: 'truncate' } },
            series: [
                {
                    name: 'Ejecutado', type: 'bar', data: ejecutado,
                    itemStyle: {
                        color: (params) => {
                            const color = colores[params.dataIndex];
                            return new echarts.graphic.LinearGradient(1,0,0,0,[
                                {offset:0, color: color}, {offset:1, color: color+'44'}
                            ]);
                        },
                        borderRadius: [0,4,4,0]
                    },
                    z: 3
                },
                {
                    name: 'Meta Anual', type: 'bar', barGap: '-100%', data: metas,
                    itemStyle: { color: 'rgba(255,255,255,0.05)', borderColor: 'rgba(255,255,255,0.2)', borderWidth: 1, borderRadius: [0,4,4,0] },
                    z: 2
                }
            ]
        };
        this.metaVsEjec.setOption(option, true);
        setTimeout(() => this.metaVsEjec?.resize(), 100);
    },

    // ──────────────────────────────────────────────
    //  DESCARGA INDIVIDUAL (con título en la imagen)
    // ──────────────────────────────────────────────
    downloadChart(key, filename) {
        const chart = this[key];
        if (!chart) { showToast('Gráfica no disponible.', 'warning', 3000); return; }

        const chartUrl = chart.getDataURL({ type: 'png', pixelRatio: 2.5, backgroundColor: '#050816' });

        // Componer título sobre el canvas
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const pad = 48;
            canvas.width  = img.width;
            canvas.height = img.height + pad;

            const ctx = canvas.getContext('2d');
            // Fondo
            ctx.fillStyle = '#050816';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            // Imagen de la gráfica
            ctx.drawImage(img, 0, pad);
            // Título
            ctx.fillStyle = '#e2e8f0';
            ctx.font = `bold ${Math.round(img.width / 50)}px Outfit, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(filename.replace(/_/g, ' ').replace(/^\d+\s*/, ''), canvas.width / 2, pad / 2);

            const finalUrl = canvas.toDataURL('image/png');
            const a = document.createElement('a');
            a.href = finalUrl;
            a.download = filename + '.png';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            showToast(`✅ Descargado: ${filename}.png`, 'success', 3000);
        };
        img.src = chartUrl;
    },

    // ──────────────────────────────────────────────
    //  DESCARGA DE TODAS LAS GRÁFICAS (Secuencial)
    // ──────────────────────────────────────────────
    async downloadAllCharts() {
        const charts = [
            { key:'gauge',       name:'01 Velocímetro de Presupuesto'       },
            { key:'trend',       name:'02 Tendencia de Gastos por Categoría'},
            { key:'bar',         name:'03 Histórico Mensual'                },
            { key:'donut',       name:'04 Distribución por Categorías'      },
            { key:'metaVsEjec',  name:'06 Presupuesto vs Real'              }
        ];
        showToast('⬇️ Descargando todas las gráficas...', 'info', 4000);

        for (let i = 0; i < charts.length; i++) {
            const c = charts[i];
            const chart = this[c.key];
            if (!chart) continue;
            await new Promise(res => setTimeout(res, 500));

            const chartUrl = chart.getDataURL({ type: 'png', pixelRatio: 2.5, backgroundColor: '#050816' });
            await new Promise(resolve => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const pad = 52;
                    canvas.width  = img.width;
                    canvas.height = img.height + pad;
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#050816';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, pad);
                    ctx.fillStyle = '#e2e8f0';
                    ctx.font = `bold ${Math.round(img.width / 48)}px Outfit, sans-serif`;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(c.name, canvas.width / 2, pad / 2);

                    const finalUrl = canvas.toDataURL('image/png');
                    const a = document.createElement('a');
                    a.href = finalUrl;
                    a.download = c.name.replace(/\s+/g, '_') + '.png';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    resolve();
                };
                img.src = chartUrl;
            });
        }
        showToast('✅ ¡Todas las gráficas descargadas!', 'success', 4000);
    }
};
