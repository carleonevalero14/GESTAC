/* =====================================================
   metricas.js — Panel lateral de métricas (admin)
   ===================================================== */

const VERDES_MET = ['#2b5a2b', '#4CAF50', '#81C784', '#a5d6a7', '#c8e6c9'];

const panel    = document.getElementById('panelMetricas');
const overlay  = document.getElementById('overlayMetricas');
const btnAbrir  = document.getElementById('btnMetricas');
const btnCerrar = document.getElementById('cerrarMetricas');

const graficasPanel = {};

function abrirPanel() {
    panel.classList.add('visible');
    overlay.classList.add('visible');
    cargarMetricas();
}

function cerrarPanel() {
    panel.classList.remove('visible');
    overlay.classList.remove('visible');
    Object.values(graficasPanel).forEach(g => g.destroy());
    Object.keys(graficasPanel).forEach(k => delete graficasPanel[k]);
}

btnAbrir?.addEventListener('click', abrirPanel);
btnCerrar?.addEventListener('click', cerrarPanel);
overlay?.addEventListener('click', cerrarPanel);

function cargarMetricas() {
    fetch('../servicios/metricas.php')
        .then(r => r.json())
        .then(datos => {
            if (!datos.exito) return;
            renderizarKpis(datos.kpis);
            renderizarAsignadosDisponibles(datos.kpis);
            renderizarPorCategoria(datos.por_categoria);
            renderizarPorUbicacion(datos.por_ubicacion);
            renderizarPorMes(datos.por_mes);
        })
        .catch(() => {
            document.getElementById('kpiGrid').innerHTML =
                '<p style="color:red">Error al cargar métricas.</p>';
        });
}

// Tarjetas con los números clave del sistema
function renderizarKpis(kpis) {
    const contenedor = document.getElementById('kpiGrid');
    contenedor.innerHTML = '';

    const items = [
        { numero: kpis.total_activos,       etiqueta: 'Total Activos'    },
        { numero: kpis.total_asignados,     etiqueta: 'Asignados'        },
        { numero: kpis.total_disponibles,   etiqueta: 'Disponibles'      },
        { numero: kpis.pct_uso + '%',       etiqueta: '% Uso'            },
        { numero: kpis.total_usuarios,      etiqueta: 'Empleados'        },
        { numero: kpis.usuarios_con_equipo, etiqueta: 'Con Equipo'       },
        { numero: kpis.usuarios_sin_equipo, etiqueta: 'Sin Equipo'       },
        { numero: kpis.total_perifericos,   etiqueta: 'Periféricos'      },
    ];

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'kpi-tarjeta';
        div.innerHTML = `
            <div class="kpi-numero">${item.numero}</div>
            <div class="kpi-etiqueta">${item.etiqueta}</div>
        `;
        contenedor.appendChild(div);
    });
}

// Dona: cuántos activos están en uso vs cuántos no tienen dueño
function renderizarAsignadosDisponibles(kpis) {
    if (graficasPanel.dona) graficasPanel.dona.destroy();
    graficasPanel.dona = new Chart(
        document.getElementById('chartDona'), {
            type: 'doughnut',
            data: {
                labels: ['Asignados', 'Disponibles'],
                datasets: [{
                    data: [kpis.total_asignados, kpis.total_disponibles],
                    backgroundColor: [VERDES_MET[0], VERDES_MET[2]],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 } } }
                },
                animation: { duration: 600 }
            }
        }
    );
}

// Barras: cuántos equipos hay de cada tipo (Laptop, PC, All in one...)
function renderizarPorCategoria(datos) {
    if (graficasPanel.categoria) graficasPanel.categoria.destroy();
    graficasPanel.categoria = new Chart(
        document.getElementById('chartCategoria'), {
            type: 'bar',
            data: {
                labels: datos.map(d => d.categoria),
                datasets: [{
                    data: datos.map(d => d.total),
                    backgroundColor: VERDES_MET,
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { font: { size: 10 } }, grid: { color: '#eee' } },
                    y: { ticks: { font: { size: 11 } }, grid: { display: false } }
                },
                animation: { duration: 600 }
            }
        }
    );
}

// Barras: en qué áreas de la empresa están los equipos asignados
function renderizarPorUbicacion(datos) {
    if (graficasPanel.ubicacion) graficasPanel.ubicacion.destroy();
    const canvas = document.getElementById('chartUbicacion');
    if (!datos.length) {
        canvas.closest('.seccion-grafica').style.display = 'none';
        return;
    }
    graficasPanel.ubicacion = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: datos.map(d => d.ubicacion),
            datasets: [{
                data: datos.map(d => d.total),
                backgroundColor: VERDES_MET,
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font: { size: 10 } }, grid: { color: '#eee' } },
                y: { ticks: { font: { size: 11 } }, grid: { display: false } }
            },
            animation: { duration: 600 }
        }
    });
}

// Línea: en qué meses se han hecho más asignaciones (últimos 6 meses)
function renderizarPorMes(datos) {
    if (graficasPanel.mes) graficasPanel.mes.destroy();
    const canvas = document.getElementById('chartMes');
    if (!datos.length) {
        canvas.closest('.seccion-grafica').style.display = 'none';
        return;
    }
    graficasPanel.mes = new Chart(canvas, {
        type: 'line',
        data: {
            labels: datos.map(d => d.mes),
            datasets: [{
                label: 'Asignaciones',
                data: datos.map(d => d.total),
                borderColor: VERDES_MET[0],
                backgroundColor: 'rgba(43, 90, 43, 0.1)',
                borderWidth: 2,
                pointRadius: 4,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { font: { size: 10 } }, grid: { display: false } },
                y: { ticks: { font: { size: 10 }, stepSize: 1 }, grid: { color: '#eee' } }
            },
            animation: { duration: 600 }
        }
    });
}
