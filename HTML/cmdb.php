<?php require_once __DIR__ . '/../configuracion/sesion.php'; requerirSesion(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMDB — Monitoreo de Equipos — GESTAC</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <script>(function(){var t=localStorage.getItem('gestac_tema');if(t&&t!=='original')document.documentElement.classList.add('tema-'+t);}());</script>
    <link rel="stylesheet" href="../recursos/css/base.css">
    <link rel="stylesheet" href="../recursos/css/temas.css">
    <link rel="stylesheet" href="../recursos/css/componentes.css">
    <link rel="stylesheet" href="../recursos/css/paginas.css">
    <link rel="stylesheet" href="../recursos/css/cmdb.css?v=<?php echo filemtime(__DIR__ . '/../recursos/css/cmdb.css'); ?>">
</head>
<body style="background-image: url('../recursos/imagenes/fondo.png'); background-repeat: no-repeat; background-size: cover; background-position: center;">

    <div class="contenedor">
        <header class="encabezado" style="position: relative;">
            <button class="btn-volver" onclick="history.back()">←</button>
            <h1 class="titulo-pagina">CMDB — MONITOREO</h1>
        </header>

        <div class="cuerpo-pagina">
            <!-- ── Tabla de equipos ── -->
            <div class="encabezado-lista">
                <h2 class="titulo-lista">Equipos monitoreados</h2>
                <div class="barra-controles">
                    <span class="cmdb-reloj" id="relojCmdb" title="Tiempo desde el último reporte recibido de un agente">
                        <span class="punto"></span>Último reporte hace <strong id="relojCmdbTiempo">—</strong>
                    </span>
                    <button class="btn-exportar" onclick="exportarExcel()">Exportar a Excel</button>
                    <label class="caja-busqueda" aria-label="Buscar equipo">
                        <span class="icono-busqueda" aria-hidden="true">🔎</span>
                        <input type="text" id="searchInput" placeholder="Buscar equipo o usuario">
                    </label>
                    <div class="desplegable">
                        <select id="filterEstado" aria-label="Filtrar por estado">
                            <option value="">Todos</option>
                            <option value="online">En línea</option>
                            <option value="offline">Sin reportar</option>
                            <option value="baja">Baja</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="contenedor-tabla">
                <table class="tabla-activos" role="grid">
                    <colgroup>
                        <col style="width:17%">
                        <col style="width:20%">
                        <col style="width:12%">
                        <col style="width:22%">
                        <col style="width:10%">
                        <col style="width:12%">
                        <col style="width:7%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Equipo</th>
                            <th scope="col">Usuario</th>
                            <th scope="col">IP</th>
                            <th scope="col">Almacenamiento</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Última conexión</th>
                            <th scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoEquipos">
                        <tr><td colspan="7" class="celda-vacia">Cargando equipos...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pie-tabla">
                <span class="pagination-info">Mostrando Equipos 0 de 0</span>
            </div>
        </div>
    </div>

    <script src="../recursos/js/peticiones.js"></script>
    <script src="../recursos/js/utilidades.js"></script>
    <script src="../recursos/js/cmdb.js?v=<?php echo filemtime(__DIR__ . '/../recursos/js/cmdb.js'); ?>"></script>
</body>
</html>
