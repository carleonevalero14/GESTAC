<?php require_once __DIR__ . '/../configuracion/sesion.php'; requerirSesion('admin'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas QR — GESTAC</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../recursos/css/base.css">
    <link rel="stylesheet" href="../recursos/css/componentes.css">
    <link rel="stylesheet" href="../recursos/css/paginas.css">
    <link rel="stylesheet" href="../recursos/css/qr.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body style="background-image: url('../recursos/imagenes/fondo.png'); background-repeat: no-repeat; background-size: cover; background-position: center;">

    <div class="contenedor">
        <header class="encabezado" style="position: relative;">
            <button class="btn-volver" onclick="history.back()">←</button>
            <h1 class="titulo-pagina">ETIQUETAS QR</h1>
            <button class="btn-exportar btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>
        </header>

        <div class="cuerpo-pagina">
            <div class="grid-etiquetas" id="gridEtiquetas">
                <p style="text-align:center; color:#fff; grid-column:1/-1">Cargando activos...</p>
            </div>
        </div>
    </div>

    <script src="../recursos/js/peticiones.js"></script>
    <script src="../recursos/js/qr.js"></script>
    <script>
        realizarPeticion('../servicios/activos.php').then(datos => {
            const grid = document.getElementById('gridEtiquetas');
            const activos = datos.datos || [];
            grid.innerHTML = '';

            if (!activos.length) {
                grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#fff">No hay activos registrados.</p>';
                return;
            }

            const base = urlBaseActivo();

            activos.forEach(activo => {
                const etiqueta = document.createElement('div');
                etiqueta.className = 'etiqueta';
                etiqueta.innerHTML = `
                    <div class="etiqueta-nombre">${activo.nombre || 'Sin nombre'}</div>
                    <div class="etiqueta-id">${activo.id_producto}</div>
                    <div id="qr-${activo.id_producto}"></div>
                `;
                grid.appendChild(etiqueta);
                generarQR(document.getElementById('qr-' + activo.id_producto), base + '?id=' + activo.id_producto, 100);
            });
        }).catch(() => {
            document.getElementById('gridEtiquetas').innerHTML =
                '<p style="grid-column:1/-1;text-align:center;color:red">Error al cargar activos.</p>';
        });
    </script>
</body>
</html>
