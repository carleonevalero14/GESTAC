<?php require_once __DIR__ . '/../configuracion/sesion.php'; requerirSesion(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Equipo — CMDB — GESTAC</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <script>(function(){var t=localStorage.getItem('gestac_tema');if(t&&t!=='original')document.documentElement.classList.add('tema-'+t);}());</script>
    <link rel="stylesheet" href="../recursos/css/base.css">
    <link rel="stylesheet" href="../recursos/css/temas.css">
    <link rel="stylesheet" href="../recursos/css/componentes.css">
    <link rel="stylesheet" href="../recursos/css/paginas.css">
    <link rel="stylesheet" href="../recursos/css/cmdb.css?v=<?php echo filemtime(__DIR__ . '/../recursos/css/cmdb.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>window.CMDB_ES_ADMIN = <?php echo esAdmin() ? 'true' : 'false'; ?>;</script>
</head>
<body style="background-image: url('../recursos/imagenes/fondo.png'); background-repeat: no-repeat; background-size: cover; background-position: center;">

    <div class="contenedor">
        <header class="encabezado" style="position: relative;">
            <button class="btn-volver" onclick="window.location.href='cmdb.php'">←</button>
            <h1 class="titulo-pagina" id="tituloEquipo">DETALLE DE EQUIPO</h1>
        </header>

        <div class="cuerpo-pagina" id="cuerpoDetalle">
            <div class="cmdb-reloj-fila">
                <span class="cmdb-reloj" id="relojCmdb" title="Tiempo desde el último reporte recibido de este equipo">
                    <span class="punto"></span>Último reporte hace <strong id="relojCmdbTiempo">—</strong>
                </span>
            </div>

            <!-- ── Tarjetas: Hardware / Red & Energía / Cloud-Sesión ── -->
            <div class="cmdb-tarjetas">
                <div class="cmdb-tarjeta">
                    <h3>🖥 Hardware</h3>
                    <dl id="tarjetaHardware"></dl>
                </div>
                <div class="cmdb-tarjeta">
                    <h3>🌐 Red &amp; Energía</h3>
                    <dl id="tarjetaRed"></dl>
                </div>
                <div class="cmdb-tarjeta">
                    <h3>☁ Cloud / Sesión</h3>
                    <dl id="tarjetaSesion"></dl>
                </div>
            </div>

            <!-- ── Almacenamiento por unidad ── -->
            <h3 class="titulo-seccion">💾 Almacenamiento por unidad</h3>
            <div class="cmdb-unidades" id="listaUnidades">
                <p class="celda-vacia" style="margin:0;">Cargando...</p>
            </div>

            <!-- ── Alertas preventivas del equipo ── -->
            <h3 class="titulo-seccion">⚠ Alertas preventivas</h3>
            <div class="cmdb-alertas" id="listaAlertas">
                <p class="celda-vacia" style="margin:0;">Cargando...</p>
            </div>

            <!-- ── Tendencia de telemetría ── -->
            <h3 class="titulo-seccion">📈 Tendencia (últimos reportes)</h3>
            <div class="cmdb-grafica">
                <canvas id="chartTendencia" height="90"></canvas>
            </div>

            <!-- ── Línea de tiempo de cambios ── -->
            <h3 class="titulo-seccion">🕓 Línea de tiempo de cambios</h3>
            <div class="cmdb-timeline" id="lineaTiempo">
                <p class="celda-vacia" style="margin:0;">Cargando...</p>
            </div>
        </div>
    </div>

    <script src="../recursos/js/peticiones.js"></script>
    <script src="../recursos/js/utilidades.js"></script>
    <script src="../recursos/js/cmdb.js?v=<?php echo filemtime(__DIR__ . '/../recursos/js/cmdb.js'); ?>"></script>
</body>
</html>
