<?php require_once __DIR__ . '/../configuracion/sesion.php'; requerirSesion(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear QR — GESTAC</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../recursos/css/base.css">
    <link rel="stylesheet" href="../recursos/css/componentes.css">
    <link rel="stylesheet" href="../recursos/css/paginas.css">
    <link rel="stylesheet" href="../recursos/css/qr.css">
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body style="background-image: url('../recursos/imagenes/fondo.png'); background-repeat: no-repeat; background-size: cover; background-position: center;">

    <div class="contenedor">
        <header class="encabezado" style="position: relative;">
            <button class="btn-volver" onclick="history.back()">←</button>
            <h1 class="titulo-pagina">ESCANEAR QR</h1>
        </header>

        <div class="cuerpo-pagina">
            <div class="contenedor-escaner">
                <p class="instruccion-escaneo">Apunta la cámara hacia el código QR de un activo GESTAC</p>
                <div id="visor-escaner"></div>
                <div id="resultado-escaneo"></div>
            </div>
        </div>
    </div>

    <script>
        const resultado = document.getElementById('resultado-escaneo');

        const escaner = new Html5QrcodeScanner('visor-escaner', {
            fps: 10,
            qrbox: { width: 260, height: 260 },
            rememberLastUsedCamera: true
        });

        escaner.render(texto => {
            resultado.style.display = 'block';
            if (texto.includes('ver_activo.php?id=')) {
                resultado.className = 'resultado-exito';
                resultado.textContent = 'QR detectado, abriendo activo...';
                escaner.clear().then(() => {
                    window.location.href = texto;
                }).catch(() => {
                    window.location.href = texto;
                });
            } else {
                resultado.className = 'resultado-error';
                resultado.textContent = 'QR no reconocido como activo GESTAC';
            }
        }, () => { /* errores de lectura: ignorar */ });
    </script>
</body>
</html>
