<?php require_once __DIR__ . '/../configuracion/sesion.php'; requerirSesion(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualización de Activos — GESTAC</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <script>(function(){var t=localStorage.getItem('gestac_tema');if(t&&t!=='original')document.documentElement.classList.add('tema-'+t);}());</script>
    <link rel="stylesheet" href="../recursos/css/base.css">
    <link rel="stylesheet" href="../recursos/css/temas.css">
    <link rel="stylesheet" href="../recursos/css/componentes.css">
    <link rel="stylesheet" href="../recursos/css/paginas.css">
    <link rel="stylesheet" href="../recursos/css/qr.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body style="background-image: url('../recursos/imagenes/fondo.png'); background-repeat: no-repeat; background-size: cover; background-position: center;">

    <div class="contenedor">
        <header class="encabezado" style="position: relative;">
            <button class="btn-volver" onclick="history.back()">←</button>
            <h1 class="titulo-pagina">VISUALIZACIÓN DE ACTIVOS</h1>
        </header>

        <div class="cuerpo-pagina">
            <div class="encabezado-lista">
                <h2 class="titulo-lista">Lista de Activos</h2>
                <div class="barra-controles">
                    <button class="btn-exportar" onclick="exportarExcel()">Exportar a Excel</button>
                    <div class="caja-busqueda">
                        <span class="icono-busqueda">🔎</span>
                        <input type="text" id="searchInput" placeholder="Buscar activo">
                    </div>
                    <div class="desplegable">
                        <select id="tipoFilter">
                            <option value="todos">Todos</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="contenedor-tabla">
                <table class="tabla-activos">
                    <thead>
                        <tr>
                            <th class="col-id">ID Producto</th>
                            <th class="col-titulo">Título</th>
                            <th class="col-categoria">Categoría</th>
                            <th class="col-procesador">Procesador</th>
                            <th class="col-caracteristicas">Características</th>
                            <th class="col-lote">Lote</th>
                            <th class="col-qr">QR</th>
                        </tr>
                    </thead>
                    <tbody id="assets-data">
                        <tr><td colspan="6" style="text-align:center;padding:20px">Cargando activos...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pie-tabla">
                <span class="pagination-info">Mostrando Activos 0 de 0</span>
            </div>
        </div>
    </div>

    <!-- Modal QR individual -->
    <div id="qr-modal" class="modal">
        <div class="modal-contenido" style="width:280px; text-align:center;">
            <h3 id="qr-modal-titulo" style="margin-bottom:4px;"></h3>
            <p id="qr-modal-id" style="font-size:0.8em; margin:0 0 12px; font-family:monospace;"></p>
            <div id="qr-modal-codigo" style="display:flex; justify-content:center; margin-bottom:16px;"></div>
            <div class="formulario-botones" style="justify-content:center;">
                <button id="cerrar-qr-modal">Cerrar</button>
            </div>
        </div>
    </div>

    <script src="../recursos/js/peticiones.js"></script>
    <script src="../recursos/js/qr.js"></script>
    <script src="../recursos/js/activos.js"></script>
    <script>
        /* Pre-filtrar por ?id= al cargar la página */
        document.addEventListener('DOMContentLoaded', () => {
            const id = new URLSearchParams(window.location.search).get('id');
            if (id) {
                const campo = document.getElementById('searchInput');
                if (campo) { campo.value = id; }
            }
        });

        /* Modal QR */
        document.getElementById('assets-data')?.addEventListener('click', e => {
            const btn = e.target.closest('.btn-qr');
            if (!btn) return;
            const id     = btn.dataset.id;
            const nombre = btn.dataset.nombre;
            document.getElementById('qr-modal-titulo').textContent = nombre || id;
            document.getElementById('qr-modal-id').textContent = id;
            const contenedorQR = document.getElementById('qr-modal-codigo');
            generarQR(contenedorQR, urlBaseActivo() + '?id=' + id, 150);
            document.getElementById('qr-modal').classList.add('abierto');
        });

        document.getElementById('cerrar-qr-modal')?.addEventListener('click', () => {
            document.getElementById('qr-modal').classList.remove('abierto');
            document.getElementById('qr-modal-codigo').innerHTML = '';
        });

        window.addEventListener('click', e => {
            const modal = document.getElementById('qr-modal');
            if (e.target === modal) {
                modal.classList.remove('abierto');
                document.getElementById('qr-modal-codigo').innerHTML = '';
            }
        });
    </script>
</body>
</html>
