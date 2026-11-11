/* =====================================================
   qr.js — Utilidad compartida de generación de QR
   Depende de: qrcodejs (CDN)
   ===================================================== */

function generarQR(contenedor, url, size) {
    size = size || 110;
    contenedor.innerHTML = '';
    new QRCode(contenedor, {
        text: url,
        width: size,
        height: size,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

/* Construye la URL base de ver_activo.php según la ubicación actual */
function urlBaseActivo() {
    const partes = window.location.href.split('/HTML/');
    return partes[0] + '/HTML/ver_activo.php';
}
