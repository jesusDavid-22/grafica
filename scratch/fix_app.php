<?php
$file = __DIR__ . '/../assets/js/app.js';
$content = file_get_contents($file);

$search = "cGralNode.className = 'ri-value';\r\n        return;\r\n    }\r\n\r\n    // Porcentaje del gasto acumulado vs. límite permitido";
if (strpos($content, $search) === false) {
    // Try with unix newlines
    $search = "cGralNode.className = 'ri-value';\n        return;\n    }\n\n    // Porcentaje del gasto acumulado vs. límite permitido";
}

if (strpos($content, $search) === false) {
    die("Error: Search pattern not found in app.js\n");
}

$replacement = <<<'EOT'
cGralNode.className = 'ri-value';
            cGralNode.style.color = 'var(--text-muted)';
        } else {
            cGralNode.textContent = c.pct.toFixed(1) + '%';
            cGralNode.style.color = '';
            if (c.nivel === 'rojo')      cGralNode.className = 'ri-value rojo';
            else if (c.nivel === 'amarillo') cGralNode.className = 'ri-value amarillo';
            else                         cGralNode.className = 'ri-value verde';
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
                return `<div style="width:8px;height:${h}px;background:${catColor};opacity:${opacity};border-radius:2px;align-self:flex-end;" title="Mes -${3-i}: \${formatCurrency(v)}"></div>`;
            }).join('');
            
            return `
            <div class="proycat-card" style="border-left:3px solid \${catColor}; display:flex; flex-direction:column; gap:0.85rem; padding:1.25rem;">
                <div class="proycat-top" style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="proycat-name" style="display:flex; align-items:center; gap:0.6rem; font-weight:700; font-size:0.95rem; color:var(--text-primary);">
                        <div class="proycat-icon-wrapper" style="width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:\${catColor}1a; color:\${catColor};">
                            <i data-lucide="\${c.icono || 'folder'}" style="width:16px; height:16px;"></i>
                        </div>
                        <span>\${c.nombre}</span>
                    </div>
                    <div class="proycat-hist" style="display:flex; align-items:flex-end; gap:3px; height:32px;" title="Últimos 3 meses (antiguo → reciente)">\${histBars}</div>
                </div>
                
                <div style="background:rgba(255,255,255,0.02); border-radius:10px; padding:0.75rem; border:1px solid rgba(255,255,255,0.03);">
                    <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:0.4rem;">
                        <span style="color:var(--text-muted);">Presupuesto Mensual:</span>
                        <span style="font-weight:600; color:var(--text-primary);">\${metaMensual > 0 ? formatCurrency(metaMensual) : 'Sin límite'}</span>
                    </div>
                    <div class="proycat-bar-row" style="display:flex; align-items:center; gap:0.75rem;">
                        <div class="proycat-bar-track" style="flex:1; height:6px; background:rgba(255,255,255,0.08); border-radius:999px; overflow:hidden;">
                            <div class="proycat-bar-fill" style="width:\${Math.min(pct,100)}%; background:\${barColor}; height:100%; border-radius:999px; transition:width 0.6s ease;"></div>
                        </div>
                        <span class="proycat-pct" style="color:\${barColor}; font-size:0.75rem; font-weight:700; min-width:38px; text-align:right;">\${pctDisplay}%</span>
                    </div>
                </div>
                
                <div class="proycat-nums" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.5rem; text-align:center;">
                    <div class="proycat-stat">
                        <div class="proycat-stat-lbl" style="font-size:0.68rem; color:var(--text-muted); margin-bottom:0.25rem;">Ejecutado</div>
                        <div class="proycat-stat-val" style="font-size:0.85rem; font-weight:700; color:var(--text-primary);">\${formatCurrency(c.ejecutado)}</div>
                    </div>
                    <div class="proycat-stat">
                        <div class="proycat-stat-lbl" style="font-size:0.68rem; color:var(--text-muted); margin-bottom:0.25rem;">Prom. Diario</div>
                        <div class="proycat-stat-val" style="font-size:0.85rem; font-weight:700; color:var(--warning);">\${formatCurrency(c.prom_diario)}</div>
                    </div>
                    <div class="proycat-stat">
                        <div class="proycat-stat-lbl" style="font-size:0.68rem; color:var(--text-muted); margin-bottom:0.25rem;">Proyección</div>
                        <div class="proycat-stat-val" style="font-size:0.85rem; font-weight:700; color:\${barColor}; font-weight:800;">\${formatCurrency(c.proyeccion)}</div>
                    </div>
                </div>
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:0.25rem; border-top:1px solid rgba(255,255,255,0.05); padding-top:0.6rem;">
                    <span style="font-size:0.7rem; color:var(--text-muted);">Semáforo:</span>
                    \${statusBadge}
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
EOT;

$new_content = str_replace($search, $replacement, $content);
file_put_contents($file, $new_content);
echo "Successfully fixed app.js!\n";
?>
