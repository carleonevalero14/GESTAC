/* =====================================================
   temas.js — Selección y persistencia de tema
   ===================================================== */

const TEMA_KEY    = 'gestac_tema';
const TEMAS_VALID = ['claro', 'original', 'oscuro'];

function aplicarTema(tema) {
    if (!TEMAS_VALID.includes(tema)) tema = 'original';

    const root = document.documentElement;
    TEMAS_VALID.forEach(t => root.classList.remove('tema-' + t));
    if (tema !== 'original') root.classList.add('tema-' + tema);

    localStorage.setItem(TEMA_KEY, tema);

    document.querySelectorAll('[data-tema]').forEach(el => {
        el.classList.toggle('tema-activo', el.dataset.tema === tema);
    });
}

// Listeners del submenú de selección
document.querySelectorAll('[data-tema]').forEach(el => {
    el.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        aplicarTema(el.dataset.tema);
        const configMenu = document.getElementById('configMenu');
        if (configMenu) configMenu.style.display = 'none';
    });
});

// Aplicar al cargar (sincroniza UI con el valor ya en <html>)
const temaGuardado = localStorage.getItem(TEMA_KEY) || 'original';
aplicarTema(temaGuardado);
