/* =====================================================
   solicitudes.js — Panel lateral de solicitudes (admin/user)
   ===================================================== */

const panelSol     = document.getElementById('panelSolicitudes');
const overlaySol   = document.getElementById('overlaySolicitudes');
const btnAbrirSol  = document.getElementById('btnSolicitudes');
const btnCerrarSol = document.getElementById('cerrarSolicitudes');

function abrirPanelSolicitudes() {
    panelSol?.classList.add('visible');
    overlaySol?.classList.add('visible');
    if (document.getElementById('filtrosSol')) {
        cargarSolicitudesAdmin();
    } else {
        cargarMisSolicitudes();
    }
}

function cerrarPanelSolicitudes() {
    panelSol?.classList.remove('visible');
    overlaySol?.classList.remove('visible');
}

btnAbrirSol?.addEventListener('click', abrirPanelSolicitudes);
btnCerrarSol?.addEventListener('click', cerrarPanelSolicitudes);
overlaySol?.addEventListener('click', cerrarPanelSolicitudes);

// ─── Utilidad ────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─── ADMIN ───────────────────────────────────────────────────────────────────

let filtroActivo = 'todas';
let _datosSolicitudes = [];

function cargarSolicitudesAdmin() {
    api.obtener('solicitudes.php?accion=listar')
        .then(res => {
            if (!res.exito) return;
            _datosSolicitudes = res.datos;
            renderizarTablaSolicitudes();
        })
        .catch(() => {});
}

function renderizarTablaSolicitudes() {
    const tbody = document.getElementById('cuerpTablaSol');
    if (!tbody) return;

    const filtrados = filtroActivo === 'todas'
        ? _datosSolicitudes
        : _datosSolicitudes.filter(s => s.estado === filtroActivo);

    if (!filtrados.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:20px;">Sin solicitudes</td></tr>';
        return;
    }

    tbody.innerHTML = filtrados.map(s => `
        <tr>
            <td>${escHtml(s.solicitante)}</td>
            <td>${escHtml(s.categoria)}</td>
            <td class="celda-motivo" title="${escHtml(s.motivo)}">${escHtml(s.motivo)}</td>
            <td><span class="badge-estado badge-${s.estado}">${s.estado}</span></td>
            <td>${s.fecha_solicitud.slice(0, 10)}</td>
            <td>
                ${s.estado === 'pendiente'
                    ? `<button class="btn-aprobar" onclick="abrirResolucion(${s.id},'aprobada')">✓</button>
                       <button class="btn-rechazar" onclick="abrirResolucion(${s.id},'rechazada')">✗</button>`
                    : escHtml(s.comentario_admin ?? '—')
                }
            </td>
        </tr>
    `).join('');
}

function actualizarBadgeAdmin() {
    const badge = document.getElementById('badgePendientes');
    if (!badge) return;
    api.obtener('solicitudes.php?accion=contar_pendientes')
        .then(res => {
            if (res.exito && res.pendientes > 0) {
                badge.textContent = res.pendientes;
                badge.classList.add('visible');
            } else {
                badge.classList.remove('visible');
            }
        })
        .catch(() => {});
}

// Filtros
document.querySelectorAll('.filtro-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('activo'));
        btn.classList.add('activo');
        filtroActivo = btn.dataset.filtro;
        renderizarTablaSolicitudes();
    });
});

// ── Mini modal resolución ──────────────────────────────────────────────────
let _resolId    = null;
let _resolEstado = null;

function abrirResolucion(id, estado) {
    _resolId     = id;
    _resolEstado = estado;
    const modal = document.getElementById('modalResolucion');
    if (!modal) return;
    document.getElementById('resolTitulo').textContent =
        estado === 'aprobada' ? '✓ Aprobar solicitud' : '✗ Rechazar solicitud';
    document.getElementById('resolComentario').value = '';
    document.getElementById('msgResolucion').textContent = '';
    modal.classList.add('abierto');
}

document.getElementById('cancelarResolucion')?.addEventListener('click', () => {
    document.getElementById('modalResolucion')?.classList.remove('abierto');
});

document.getElementById('confirmarResolucion')?.addEventListener('click', () => {
    const comentario = document.getElementById('resolComentario')?.value.trim() ?? '';
    const msg = document.getElementById('msgResolucion');
    const datos = new FormData();
    datos.append('accion', 'resolver');
    datos.append('id', _resolId);
    datos.append('estado', _resolEstado);
    datos.append('comentario', comentario);

    api.enviar('solicitudes.php', datos)
        .then(res => {
            msg.style.color = res.exito ? '#22c55e' : '#ef4444';
            msg.textContent  = res.mensaje;
            if (res.exito) {
                setTimeout(() => {
                    document.getElementById('modalResolucion')?.classList.remove('abierto');
                    cargarSolicitudesAdmin();
                    actualizarBadgeAdmin();
                }, 900);
            }
        })
        .catch(() => {
            msg.style.color = '#ef4444';
            msg.textContent  = 'Error de red';
        });
});

// ─── USER ────────────────────────────────────────────────────────────────────

function cargarMisSolicitudes() {
    const tbody = document.getElementById('cuerpoMisSol');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:16px;">Cargando...</td></tr>';

    api.obtener('solicitudes.php?accion=listar')
        .then(res => {
            if (!res.exito) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:red;">Error al cargar</td></tr>';
                return;
            }
            if (!res.datos.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:16px;">Aún no tienes solicitudes</td></tr>';
                return;
            }
            tbody.innerHTML = res.datos.map(s => `
                <tr>
                    <td>${escHtml(s.categoria)}</td>
                    <td class="celda-motivo" title="${escHtml(s.motivo)}">${escHtml(s.motivo)}</td>
                    <td><span class="badge-estado badge-${s.estado}">${s.estado}</span></td>
                    <td>${s.fecha_solicitud.slice(0, 10)}</td>
                    <td>${escHtml(s.comentario_admin ?? '—')}</td>
                </tr>
            `).join('');
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:red;">Error de red</td></tr>';
        });
}

document.getElementById('formNuevaSolicitud')?.addEventListener('submit', e => {
    e.preventDefault();
    const form     = e.target;
    const categoria = form.categoria.value;
    const motivo    = form.motivo.value.trim();
    const msg       = document.getElementById('msgNuevaSolicitud');

    if (!categoria || !motivo) {
        msg.style.color = '#ef4444';
        msg.textContent  = 'Completa todos los campos';
        return;
    }

    const datos = new FormData();
    datos.append('accion', 'crear');
    datos.append('categoria', categoria);
    datos.append('motivo', motivo);

    api.enviar('solicitudes.php', datos)
        .then(res => {
            msg.style.color = res.exito ? '#22c55e' : '#ef4444';
            msg.textContent  = res.mensaje;
            if (res.exito) {
                form.reset();
                cargarMisSolicitudes();
                setTimeout(() => { msg.textContent = ''; }, 3000);
            }
        })
        .catch(() => {
            msg.style.color = '#ef4444';
            msg.textContent  = 'Error de red';
        });
});

// Inicialización: badge solo en admin
if (document.getElementById('badgePendientes')) actualizarBadgeAdmin();
