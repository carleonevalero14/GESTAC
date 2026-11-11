const BASE_SERVICIOS = '../servicios';

async function realizarPeticion(url, opciones = {}) {
    const respuesta = await fetch(url, { ...opciones, cache: 'no-store' });
    // Sesión no válida o expirada: se regresa al usuario al login.
    if (respuesta.status === 401) {
        window.location.href = 'login.php?expirada=1';
        throw new Error('Sesión expirada');
    }
    if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
    return respuesta.json();
}

const api = {
    obtener: (ruta) => realizarPeticion(`${BASE_SERVICIOS}/${ruta}`),
    enviar:  (ruta, cuerpo) => realizarPeticion(`${BASE_SERVICIOS}/${ruta}`, { method: 'POST', body: cuerpo }),
};
