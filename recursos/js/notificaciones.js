/* =====================================================
   notificaciones.js — Panel integrado en menú de config
   ===================================================== */

const iconoNotif  = document.getElementById('iconoNotif');
const panelNotif  = document.getElementById('panelNotif');
const badgeNotif  = document.getElementById('badgeNotif');
const listaNotif  = document.getElementById('listaNotif');
const configMenu  = document.getElementById('configMenu');

let panelAbierto = false;

// ── Abrir/cerrar panel desde el ítem del menú de configuración ──────────────
iconoNotif?.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    panelAbierto = !panelAbierto;
    panelNotif?.classList.toggle('visible', panelAbierto);
    if (configMenu) configMenu.style.display = 'none';
    if (panelAbierto) cargarNotificaciones();
});

// ── Cerrar con botón ✕ ───────────────────────────────────────────────────────
document.getElementById('cerrarPanelNotif')?.addEventListener('click', e => {
    e.stopPropagation();
    panelAbierto = false;
    panelNotif?.classList.remove('visible');
});

// ── Cerrar al hacer clic fuera del panel ─────────────────────────────────────
document.addEventListener('click', e => {
    const wrapper = document.getElementById('wrapperEngranaje');
    if (panelAbierto &&
        !panelNotif?.contains(e.target) &&
        !wrapper?.contains(e.target)) {
        panelAbierto = false;
        panelNotif?.classList.remove('visible');
    }
});

// ── Marcar todas como leídas ─────────────────────────────────────────────────
document.getElementById('marcarTodasLeidas')?.addEventListener('click', e => {
    e.stopPropagation();
    const datos = new FormData();
    datos.append('accion', 'marcar_todas_leidas');
    api.enviar('notificaciones.php', datos)
        .then(() => {
            cargarNotificaciones();
            actualizarBadgeNotif();
        })
        .catch(() => {});
});

// ── Cargar lista de notificaciones ───────────────────────────────────────────
function cargarNotificaciones() {
    if (!listaNotif) return;
    listaNotif.innerHTML = '<p style="text-align:center;color:#999;padding:16px;font-size:0.85em;">Cargando...</p>';

    api.obtener('notificaciones.php?accion=listar_no_leidas')
        .then(res => {
            if (!res.exito) {
                listaNotif.innerHTML = '<p style="text-align:center;color:#999;padding:16px;font-size:0.85em;">Error al cargar</p>';
                return;
            }
            if (!res.datos.length) {
                listaNotif.innerHTML = '<p style="text-align:center;color:#999;padding:20px;font-size:0.85em;">Sin notificaciones nuevas 🎉</p>';
                return;
            }
            listaNotif.innerHTML = res.datos.map(n => `
                <div class="notif-item no-leida" data-id="${n.id}"${n.url ? ` data-url="${escNotif(n.url)}"` : ''}>
                    <span class="notif-punto"></span>
                    <div class="notif-texto">
                        <div>${escNotif(n.mensaje)}</div>
                        <div class="notif-fecha">${formatFecha(n.fecha)}</div>
                    </div>
                </div>
            `).join('');

            listaNotif.querySelectorAll('.notif-item').forEach(el => {
                el.addEventListener('click', () => marcarLeida(el));
            });
        })
        .catch(() => {
            listaNotif.innerHTML = '<p style="text-align:center;color:red;padding:16px;font-size:0.85em;">Error de red</p>';
        });
}

// ── Marcar una notificación como leída ───────────────────────────────────────
function marcarLeida(el) {
    const id  = el.dataset.id;
    const url = el.dataset.url;
    const datos = new FormData();
    datos.append('accion', 'marcar_leida');
    datos.append('id', id);
    api.enviar('notificaciones.php', datos)
        .then(res => {
            if (res.exito) {
                el.classList.remove('no-leida');
                el.querySelector('.notif-punto')?.remove();
                actualizarBadgeNotif();
                if (url) window.location.href = url;
            }
        })
        .catch(() => {});
}

// ── Actualizar badge en el engranaje ────────────────────────────────────────
function actualizarBadgeNotif() {
    if (!badgeNotif) return;
    api.obtener('notificaciones.php?accion=contar_no_leidas')
        .then(res => {
            if (res.exito && res.total > 0) {
                badgeNotif.textContent = res.total > 99 ? '99+' : res.total;
                badgeNotif.classList.add('visible');
            } else {
                badgeNotif.classList.remove('visible');
            }
        })
        .catch(() => {});
}

// ── Utilidades ────────────────────────────────────────────────────────────────
function escNotif(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatFecha(fechaStr) {
    if (!fechaStr) return '';
    const d = new Date(fechaStr.replace(' ', 'T'));
    return d.toLocaleDateString('es-MX', {
        day: '2-digit', month: 'short',
        hour: '2-digit', minute: '2-digit'
    });
}

// ── Inicialización: badge inmediato + polling cada 30 s ──────────────────────
actualizarBadgeNotif();
setInterval(actualizarBadgeNotif, 30000);
