/* =====================================================
   cmdb.js — Tablero y detalle del módulo CMDB.
   Usa api.obtener()/api.enviar() (peticiones.js),
   animarContador() (utilidades.js) y Chart.js.
   ===================================================== */

const ENDPOINT_CMDB = 'cmdb.php';
const VERDES_CMDB   = ['#4CAF50', '#81C784', '#2b5a2b', '#a5d6a7', '#388E3C', '#66BB6A'];
let equiposGlobal   = [];
let graficaTendencia = null;
let marcaActualizacion = null;   // instante (local) del último reporte recibido de un agente

/* ── Reloj del último reporte ──
   Muestra hace cuánto llegó el último reporte REAL de un agente. Se ancla
   con la hora del servidor y la fecha de última conexión guardadas en la
   base de datos, por lo que el conteo sobrevive a refrescos de página y no
   depende del reloj de la máquina del usuario. */
function anclarReloj(fechaUltimoReporte, ahoraServidor) {
    if (!fechaUltimoReporte || !ahoraServidor) {
        marcaActualizacion = null;
        const tiempo = document.getElementById('relojCmdbTiempo');
        if (tiempo) tiempo.textContent = '—';
        return;
    }
    const aMs = t => new Date(String(t).replace(' ', 'T')).getTime();
    const transcurrido = Math.max(aMs(ahoraServidor) - aMs(fechaUltimoReporte), 0);
    marcaActualizacion = Date.now() - transcurrido;
}

function iniciarRelojCmdb() {
    const tiempo = document.getElementById('relojCmdbTiempo');
    if (!tiempo) return;
    setInterval(() => {
        if (marcaActualizacion === null) return;
        const seg = Math.max(Math.floor((Date.now() - marcaActualizacion) / 1000), 0);
        const h   = Math.floor(seg / 3600);
        const m   = Math.floor((seg % 3600) / 60);
        const s   = seg % 60;
        const dos = n => String(n).padStart(2, '0');
        tiempo.textContent = h > 0 ? `${dos(h)}:${dos(m)}:${dos(s)}` : `${dos(m)}:${dos(s)}`;
    }, 1000);
}

/* ── Helpers ── */
function escapar(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function claseNivel(pct) {
    if (pct < 10) return 'nivel-critico';
    if (pct < 20) return 'nivel-bajo';
    return '';
}

function chipEstado(estado) {
    const etiquetas = { online: 'En línea', offline: 'Sin reportar', baja: 'Baja' };
    const clase     = estado === 'online' ? '' : ` ${estado}`;
    return `<span class="chip${clase}">${etiquetas[estado] || estado}</span>`;
}

function barraAlmacenamiento(pct, texto) {
    const usado = Math.max(0, Math.min(100, 100 - pct));
    return `
        <div class="barra-almacen">
            <div class="barra-almacen-pista">
                <div class="barra-almacen-relleno ${claseNivel(pct)}" style="width:${usado}%"></div>
            </div>
            <span class="barra-almacen-texto">${escapar(texto)}</span>
        </div>`;
}

function exportarExcel() {
    window.location.href = `../servicios/${ENDPOINT_CMDB}?accion=exportar_excel`;
}

/* =====================================================
   TABLERO (cmdb.php)
   ===================================================== */

async function cargarTablero() {
    const cuerpo  = document.getElementById('cuerpoEquipos');
    const infoPag = document.querySelector('.pagination-info');
    try {
        const respuesta = await api.obtener(`${ENDPOINT_CMDB}?accion=listar`);
        equiposGlobal = respuesta.datos || [];
        aplicarFiltrosCmdb();

        // El reloj se ancla al reporte más reciente de todo el parque.
        // (El formato AAAA-MM-DD HH:MM:SS permite comparar como texto.)
        const masReciente = equiposGlobal
            .map(e => e.ultima_conexion)
            .filter(Boolean)
            .sort()
            .pop() || null;
        anclarReloj(masReciente, respuesta.ahora);
    } catch (err) {
        console.error(err);
        cuerpo.innerHTML = '<tr><td colspan="7" class="celda-vacia" style="color:#ffdddd">Error al cargar los equipos</td></tr>';
        if (infoPag) infoPag.textContent = 'Mostrando Equipos 0 de 0';
    }
}

function aplicarFiltrosCmdb() {
    const termino = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
    const estado  = document.getElementById('filterEstado')?.value || '';

    const filtrados = equiposGlobal.filter(e => {
        const coincideEstado = !estado || e.estado === estado;
        const coincideTerm   = !termino || [e.hostname, e.usuario_asignado, e.ip_privada, e.id_activo, e.so_nombre]
            .some(v => String(v || '').toLowerCase().includes(termino));
        return coincideEstado && coincideTerm;
    });
    renderizarTablaEquipos(filtrados);
}

function renderizarTablaEquipos(equipos) {
    const cuerpo  = document.getElementById('cuerpoEquipos');
    const infoPag = document.querySelector('.pagination-info');
    cuerpo.innerHTML = '';

    if (!equipos.length) {
        cuerpo.innerHTML = '<tr><td colspan="7" class="celda-vacia">No se encontraron equipos</td></tr>';
        if (infoPag) infoPag.textContent = 'Mostrando Equipos 0 de 0';
        return;
    }

    equipos.forEach(equipo => {
        const pct    = parseFloat(equipo.libre_pct_min ?? 100);
        const alerta = parseInt(equipo.alertas_abiertas || 0, 10);
        const fila   = document.createElement('tr');
        fila.innerHTML = `
            <td><strong>${escapar(equipo.hostname)}</strong>${alerta ? ` <span title="${alerta} alerta(s) abierta(s)">⚠</span>` : ''}</td>
            <td>${escapar(equipo.usuario_asignado || 'N/A')}</td>
            <td>${escapar(equipo.ip_privada || 'N/A')}</td>
            <td>${barraAlmacenamiento(pct, equipo.resumen_discos || 'Sin discos reportados')}</td>
            <td>${chipEstado(equipo.estado)}</td>
            <td>${escapar(equipo.ultima_conexion || 'Nunca')}</td>
            <td><button class="btn-exportar btn-carta" data-id="${equipo.id_equipo}">Detalles</button></td>
        `;
        cuerpo.appendChild(fila);
    });

    if (infoPag) infoPag.textContent = `Mostrando Equipos 1 a ${equipos.length} de ${equipos.length}`;
}

/* ── Alertas preventivas (sección del detalle) ── */
function renderizarAlertas(alertas) {
    const lista = document.getElementById('listaAlertas');
    lista.innerHTML = '';

    if (!alertas.length) {
        lista.innerHTML = '<p class="celda-vacia" style="margin:0;">Sin alertas activas ✔</p>';
        return;
    }

    alertas.forEach(alerta => {
        const item = document.createElement('div');
        item.className = `cmdb-alerta severidad-${alerta.severidad}`;
        const origen = alerta.hostname ? `<strong>${escapar(alerta.hostname)}</strong>` : '';
        const botones = window.CMDB_ES_ADMIN ? `
            <div class="cmdb-alerta-acciones">
                ${alerta.estado === 'abierta' ? `<button class="btn-exportar btn-carta" data-alerta="${alerta.id}" data-estado="reconocida">Reconocer</button>` : ''}
                <button class="btn-eliminar btn-carta" data-alerta="${alerta.id}" data-estado="resuelta">Resolver</button>
            </div>` : '';
        item.innerHTML = `
            <div class="cmdb-alerta-texto">
                ${origen}
                ${escapar(alerta.mensaje)}
                <div class="cmdb-alerta-meta">${escapar(alerta.fecha)} · ${escapar(alerta.tipo)} · ${escapar(alerta.estado)}</div>
            </div>
            ${botones}
        `;
        lista.appendChild(item);
    });
}

async function resolverAlerta(idAlerta, estado, boton) {
    boton.disabled = true;
    try {
        const fd = new FormData();
        fd.append('id_alerta', idAlerta);
        fd.append('estado', estado);
        const respuesta = await api.enviar(`${ENDPOINT_CMDB}?accion=resolver_alerta`, fd);
        if (!respuesta.exito) alert(respuesta.mensaje || 'No se pudo actualizar la alerta');
    } catch {
        alert('Error de comunicación con el servidor');
    } finally {
        boton.disabled = false;
        recargarVista();
    }
}

/* =====================================================
   DETALLE (cmdb_detalle.php)
   ===================================================== */

function idEquipoActual() {
    return parseInt(new URLSearchParams(window.location.search).get('id') || '0', 10);
}

function filaDato(etiqueta, valor) {
    return `<div class="cmdb-dato"><dt>${etiqueta}</dt><dd title="${escapar(valor ?? 'N/A')}">${escapar(valor ?? 'N/A') || 'N/A'}</dd></div>`;
}

async function cargarDetalle() {
    const id = idEquipoActual();
    try {
        const respuesta = await api.obtener(`${ENDPOINT_CMDB}?accion=detalle&id=${id}`);
        if (!respuesta.exito) {
            document.getElementById('tituloEquipo').textContent = 'EQUIPO NO ENCONTRADO';
            return;
        }
        const { equipo, discos, alertas, cambios, telemetria } = respuesta.datos;

        document.getElementById('tituloEquipo').textContent = equipo.hostname.toUpperCase();
        document.title = `${equipo.hostname} — CMDB — GESTAC`;

        document.getElementById('tarjetaHardware').innerHTML =
            filaDato('Marca / Modelo', [equipo.marca, equipo.modelo].filter(Boolean).join(' ') || 'N/A') +
            filaDato('Núm. de serie', equipo.num_serie) +
            filaDato('CPU', equipo.cpu) +
            filaDato('RAM total', equipo.ram_total_gb ? `${equipo.ram_total_gb} GB` : 'N/A') +
            filaDato('Activo vinculado', equipo.id_activo ? `${equipo.id_activo} — ${equipo.nombre_activo || ''}` : 'Sin vincular');

        document.getElementById('tarjetaRed').innerHTML =
            filaDato('IP privada', equipo.ip_privada) +
            filaDato('IP pública', equipo.ip_publica) +
            filaDato('MAC', equipo.mac) +
            filaDato('Batería', telemetria && telemetria.bateria_pct != null ? `${telemetria.bateria_pct}%` : 'N/A') +
            filaDato('Uptime', telemetria && telemetria.uptime_horas != null ? `${telemetria.uptime_horas} h` : 'N/A');

        document.getElementById('tarjetaSesion').innerHTML =
            filaDato('Usuario asignado', equipo.usuario_asignado) +
            filaDato('Sistema operativo', equipo.so_nombre) +
            filaDato('Versión', equipo.so_version) +
            filaDato('Última conexión', equipo.ultima_conexion || 'Nunca') +
            `<div class="cmdb-dato"><dt>Estado</dt><dd>${chipEstado(equipo.estado)}</dd></div>`;

        renderizarUnidades(discos);
        renderizarAlertas((alertas || []).filter(a => a.estado !== 'resuelta'));
        renderizarLineaTiempo(cambios || []);
        anclarReloj(equipo.ultima_conexion, respuesta.ahora);
    } catch (err) {
        console.error(err);
        document.getElementById('tituloEquipo').textContent = 'ERROR AL CARGAR';
    }
}

function renderizarUnidades(discos) {
    const lista = document.getElementById('listaUnidades');
    lista.innerHTML = '';
    if (!discos.length) {
        lista.innerHTML = '<p class="celda-vacia" style="margin:0;">El agente aún no reporta discos</p>';
        return;
    }
    discos.forEach(disco => {
        const pct   = parseFloat(disco.libre_pct ?? 100);
        const smart = String(disco.estado_smart || 'OK').toUpperCase();
        const tarjeta = document.createElement('div');
        tarjeta.className = 'cmdb-unidad';
        tarjeta.innerHTML = `
            <div class="cmdb-unidad-encabezado">
                <span>Unidad ${escapar(disco.unidad)}</span>
                <span class="chip${smart === 'OK' ? '' : ' vencido'}">SMART ${escapar(smart)}</span>
            </div>
            ${barraAlmacenamiento(pct, `${disco.libre_gb} GB libres de ${disco.capacidad_gb} GB (${pct}%)`)}
        `;
        lista.appendChild(tarjeta);
    });
}

function renderizarLineaTiempo(cambios) {
    const linea = document.getElementById('lineaTiempo');
    linea.innerHTML = '';
    if (!cambios.length) {
        linea.innerHTML = '<p class="celda-vacia" style="margin:0;">Sin cambios detectados</p>';
        return;
    }
    cambios.forEach(cambio => {
        const evento = document.createElement('div');
        evento.className = 'cmdb-evento';
        evento.innerHTML = `
            <div class="cmdb-evento-fecha">${escapar(cambio.fecha)} · ${escapar(cambio.tipo)}</div>
            <strong>${escapar(cambio.campo)}</strong>:
            ${escapar(cambio.valor_anterior || '—')} → <strong>${escapar(cambio.valor_nuevo || '—')}</strong>
        `;
        linea.appendChild(evento);
    });
}

async function cargarTendencia() {
    const lienzo = document.getElementById('chartTendencia');
    if (!lienzo || typeof Chart === 'undefined') return;
    try {
        const respuesta = await api.obtener(`${ENDPOINT_CMDB}?accion=tendencia&id=${idEquipoActual()}`);
        const datos = respuesta.datos || [];
        if (graficaTendencia) graficaTendencia.destroy();
        graficaTendencia = new Chart(lienzo, {
            type: 'line',
            data: {
                labels: datos.map(d => d.fecha_reporte),
                datasets: [
                    {
                        label: 'Uso de CPU (%)',
                        data: datos.map(d => d.uso_cpu),
                        borderColor: VERDES_CMDB[0],
                        backgroundColor: 'rgba(76, 175, 80, 0.15)',
                        tension: 0.3, fill: true,
                    },
                    {
                        label: 'RAM libre (GB)',
                        data: datos.map(d => d.ram_libre_gb),
                        borderColor: VERDES_CMDB[1],
                        backgroundColor: 'transparent',
                        tension: 0.3,
                    },
                ],
            },
            options: {
                plugins: { legend: { labels: { color: '#fff', font: { size: 11 } } } },
                scales: {
                    x: { ticks: { color: 'rgba(255,255,255,0.7)', font: { size: 10 }, maxTicksLimit: 8 }, grid: { color: 'rgba(255,255,255,0.08)' } },
                    y: { ticks: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.08)' } },
                },
            },
        });
    } catch (err) {
        console.error(err);
    }
}

/* =====================================================
   INICIALIZACIÓN
   ===================================================== */

function recargarVista() {
    if (document.getElementById('cuerpoEquipos')) {
        cargarTablero();
    } else if (document.getElementById('cuerpoDetalle')) {
        cargarDetalle();
        cargarTendencia();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    iniciarRelojCmdb();

    /* Tablero */
    if (document.getElementById('cuerpoEquipos')) {
        cargarTablero();
        document.getElementById('searchInput')?.addEventListener('input', aplicarFiltrosCmdb);
        document.getElementById('filterEstado')?.addEventListener('change', aplicarFiltrosCmdb);

        document.getElementById('cuerpoEquipos').addEventListener('click', e => {
            const btn = e.target.closest('[data-id]');
            if (btn) window.location.href = `cmdb_detalle.php?id=${btn.dataset.id}`;
        });

        // El tablero se refresca solo (el admin no captura nada a mano).
        setInterval(cargarTablero, 60000);
    }

    /* Detalle */
    if (document.getElementById('cuerpoDetalle')) {
        cargarDetalle();
        cargarTendencia();

        // El detalle también se refresca solo: el agente reporta cada minuto.
        setInterval(() => { cargarDetalle(); cargarTendencia(); }, 60000);
    }

    /* Acciones de alertas (delegación compartida) */
    document.getElementById('listaAlertas')?.addEventListener('click', e => {
        const btn = e.target.closest('[data-alerta]');
        if (btn) resolverAlerta(btn.dataset.alerta, btn.dataset.estado, btn);
    });
});
