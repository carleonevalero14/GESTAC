<?php require_once __DIR__ . '/../configuracion/sesion.php'; requerirSesion('admin'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú Principal — GESTAC Admin</title>
    <script>(function(){var t=localStorage.getItem('gestac_tema');if(t&&t!=='original')document.documentElement.classList.add('tema-'+t);}());</script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../recursos/css/base.css">
    <link rel="stylesheet" href="../recursos/css/temas.css">
    <link rel="stylesheet" href="../recursos/css/componentes.css">
    <link rel="stylesheet" href="../recursos/css/menu.css">
    <link rel="stylesheet" href="../recursos/css/metricas.css">
    <link rel="stylesheet" href="../recursos/css/solicitudes.css">
    <link rel="stylesheet" href="../recursos/css/notificaciones.css">
    <link rel="stylesheet" href="../recursos/css/cmdb.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body style="background-image: url('../recursos/imagenes/fondo.png'); background-repeat: no-repeat; background-size: cover; background-position: center;">

    <!-- ===================== MODAL LOGS ===================== -->
    <div id="modalLogs" class="modal">
        <div class="modal-contenido modal-logs">
            <h2>Historial de Cambios (Logs)</h2>
            <div class="contenedor-logs">
                <table id="tablaLogs" class="tabla-activos">
                    <colgroup>
                        <col style="width:14%">
                        <col style="width:12%">
                        <col style="width:12%">
                        <col style="width:10%">
                        <col style="width:52%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Acción</th>
                            <th>Registro</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="formulario-botones">
                <button id="cerrarLogs">Cerrar</button>
            </div>
        </div>
    </div>

    <div class="contenedor-menu con-cmdb">
        <div class="panel-izquierdo">
            <h1 id="GESTAC">GESTAC</h1>
            <div class="contenedor-dona">
                <canvas id="chartCategorias" width="180" height="180"></canvas>
                <p class="etiqueta-dona">ACTIVOS POR CATEGORÍA</p>
            </div>
        </div>

        <div class="panel-derecho">
            <div class="cuadricula-modulos">
                <!-- ACTIVOS -->
                <div class="tarjeta-modulo tiene-submenu">
                    <img src="../recursos/imagenes/activos.png" alt="Activos" class="imagen-modulo">
                    <span class="texto-modulo">ACTIVOS</span>
                    <div class="submenu submenu-izquierdo">
                        <a href="#" id="btnNuevoActivo">NUEVO ACTIVO</a>
                        <a href="modificar_activo.php">MODIFICAR ACTIVO</a>
                        <a href="ver_activo.php">VER ACTIVO</a>
                        <a href="etiquetas_qr.php">ETIQUETAS QR</a>
                        <a href="escanear.php">ESCANEAR QR</a>
                    </div>
                </div>

                <!-- ASIGNAR -->
                <div class="tarjeta-modulo tiene-submenu">
                    <img src="../recursos/imagenes/asignar.png" alt="Asignar" class="imagen-modulo">
                    <span class="texto-modulo">ASIGNAR</span>
                    <div class="submenu submenu-derecho">
                        <a href="#" id="btnAsignarActivo">ASIGNAR ACTIVOS</a>
                        <a href="modificar_asignado.php">MODIFICAR ASIGNADO</a>
                        <a href="ver_asignado.php">VER USUARIO</a>
                    </div>
                </div>

                <!-- INVENTARIO -->
                <a href="inventario.php" class="tarjeta-modulo">
                    <img src="../recursos/imagenes/perifericos.png" alt="Periféricos" class="imagen-modulo">
                    <span class="texto-modulo">INVENTARIO PERIFÉRICOS</span>
                </a>

                <!-- PRÉSTAMOS -->
                <a href="prestamo.php" class="tarjeta-modulo">
                    <img src="../recursos/imagenes/prestamos.png" alt="Préstamos" class="imagen-modulo">
                    <span class="texto-modulo">PRESTAMOS PERIFERICOS</span>
                </a>

                <!-- CMDB / MONITOREO -->
                <a href="cmdb.php" class="tarjeta-modulo tarjeta-modulo-ancha">
                    <img src="../recursos/imagenes/cmdb.png" alt="CMDB" class="imagen-modulo">
                    <span class="texto-modulo">CMDB / MONITOREO</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ================= CONFIGURACIONES ================= -->
    <!-- Botón de métricas -->
    <button id="btnMetricas"> Métricas</button>

    <!-- Overlay y panel lateral de métricas -->
    <div id="overlayMetricas"></div>
    <div id="panelMetricas">
        <div class="panel-metricas-header">
            <h2>📊 Métricas del Sistema</h2>
            <button id="cerrarMetricas">✕</button>
        </div>
        <div class="panel-metricas-cuerpo">
            <!-- KPIs -->
            <div class="kpi-grid" id="kpiGrid"></div>

            <!-- Gráfica 1: Asignados vs Disponibles -->
            <div class="seccion-grafica">
                <h3>Asignados vs Disponibles</h3>
                <canvas id="chartDona"></canvas>
            </div>

            <!-- Gráfica 2: Activos por categoría -->
            <div class="seccion-grafica">
                <h3>Activos por Categoría</h3>
                <canvas id="chartCategoria"></canvas>
            </div>

            <!-- Gráfica 3: Top ubicaciones -->
            <div class="seccion-grafica">
                <h3>Top Ubicaciones</h3>
                <canvas id="chartUbicacion"></canvas>
            </div>

            <!-- Gráfica 4: Tendencia por mes -->
            <div class="seccion-grafica">
                <h3>Asignaciones por Mes</h3>
                <canvas id="chartMes"></canvas>
            </div>
        </div>
    </div>

    <!-- ========== NOTIFICACIONES (panel) ========== -->
    <div id="panelNotif">
        <div class="notif-header">
            <span> Notificaciones</span>
            <div class="notif-header-acciones">
                <button id="marcarTodasLeidas">Marcar todas</button>
                <button id="cerrarPanelNotif">✕</button>
            </div>
        </div>
        <div id="listaNotif">
            <p style="text-align:center;color:#999;padding:16px;font-size:0.85em;">—</p>
        </div>
    </div>

    <div id="wrapperEngranaje">
        <a href="#" class="icono-config" id="configIcon" title="Configuraciones">⚙️</a>
        <span id="badgeNotif" class="badge-notif"></span>
    </div>
    <div class="menu-config" id="configMenu">
        <a href="#" id="iconoNotif"> Notificaciones</a>
        <div class="tiene-submenu">
            <span> Tema ▸</span>
            <div class="submenu-config">
                <a href="#" data-tema="claro"> Claro</a>
                <a href="#" data-tema="original"> Original</a>
                <a href="#" data-tema="oscuro"> Oscuro</a>
            </div>
        </div>
        <div class="tiene-submenu">
            <span id="usuariosMenu">Usuarios ▸</span>
            <div class="submenu-config" id="submenuUsuarios">
                <a href="#" id="btnVerUsuarios">Ver Usuarios</a>
                <a href="#" id="btnAgregarUsuario">Agregar Usuario</a>
            </div>
        </div>
        
        <a href="../servicios/logout.php">Cerrar Sesión</a>
    </div>

    <div class="leyenda-usuario">Usuario: <span id="user-type"><?php echo htmlspecialchars(usuarioActual(), ENT_QUOTES, 'UTF-8'); ?></span></div>

    <!-- ========== MODAL NUEVO ACTIVO ========== -->
    <div id="modalNuevoActivo" class="modal">
        <div class="modal-contenido">
            <h2>Nuevo Activo</h2>
            <form id="formNuevoActivo" method="POST">
                <div class="formulario-cuadricula">
                    <div>
                        <label>Categoría de activo</label>
                        <div class="selector-personalizado">
                            <input type="hidden" name="categoria">
                            <span class="opcion-seleccionada">Seleccionar</span>
                            <div class="lista-opciones">
                                <div>Laptop</div>
                                <div>PC</div>
                                <div>All in one</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label>Cantidad</label>
                        <div class="selector-personalizado" id="selectorCantidad">
                            <input type="hidden" name="cantidad">
                            <span class="opcion-seleccionada">Seleccionar</span>
                            <div class="lista-opciones">
                                <div>1</div><div>2</div><div>3</div><div>4</div><div>5</div>
                            </div>
                        </div>
                    </div>
                    <div id="serieContainer" class="ancho-completo"></div>
                    <div class="ancho-completo">
                        <label>Número de Lote</label>
                        <input type="text" name="lote" placeholder="Ej. LOTE-2025-01">
                    </div>
                    <div>
                        <label>Nombre del producto</label>
                        <input type="text" name="nombre">
                    </div>
                    <div class="ancho-completo">
                        <label>Descripción del activo</label>
                        <textarea name="descripcion"></textarea>
                    </div>
                    <div><label>Marca</label><input type="text" name="marca"></div>
                    <div><label>Procesador</label><input type="text" name="procesador"></div>
                    <div><label>Almacenamiento</label><input type="text" name="almacenamiento"></div>
                    <div><label>RAM</label><input type="text" name="ram"></div>
                </div>
                <div class="formulario-botones">
                    <button type="button" id="cancelar">Cancelar</button>
                    <button type="button" id="guardarNuevoActivo">Guardar Activo</button>
                </div>
                <div id="mensajeNuevoActivo" style="margin-top:10px;font-weight:bold;text-align:center;"></div>
            </form>
        </div>
    </div>

    <!-- ========== MODAL ASIGNAR ACTIVO ========== -->
    <div id="modalAsignarActivo" class="modal">
        <div class="modal-contenido" style="max-width:540px;">
            <h2>Asignar Activo</h2>
            <form id="formAsignarActivo" method="POST">
                <div class="formulario-cuadricula">
                    <div class="autocompletar ancho-completo">
                        <label>Usuario</label>
                        <input type="text" id="usuario" name="usuario" placeholder="Buscar usuario..." autocomplete="off">
                        <div class="items-autocompletar" id="usuarioList"></div>
                    </div>
                    <div class="autocompletar ancho-completo">
                        <label>Activo</label>
                        <input type="text" id="activo" name="activo" placeholder="Buscar activo..." autocomplete="off">
                        <div class="items-autocompletar" id="activoList"></div>
                    </div>
                    <div class="ancho-completo">
                        <label>Fecha de Asignación</label>
                        <input type="date" name="fecha_asignacion" required>
                    </div>
                </div>
                <div class="formulario-botones">
                    <button type="button" id="cancelarAsignacion">Cancelar</button>
                    <button type="button" id="guardarAsignacion">Guardar Asignación</button>
                </div>
                <div id="mensajeAsignacion" style="margin-top:10px;font-weight:bold;"></div>
            </form>
        </div>
    </div>

    <!-- ========== MODAL AGREGAR USUARIO ========== -->
    <div id="modalAgregarUsuario" class="modal">
        <div class="modal-contenido">
            <h2>Agregar Usuario</h2>
            <form id="formAgregarUsuario" method="POST">
                <div class="formulario-cuadricula">
                    <div class="ancho-completo">
                        <label>Correo</label>
                        <input type="email" name="correo" placeholder="usuario@ejemplo.com" required>
                    </div>
                    <div class="ancho-completo">
                        <label>Contraseña</label>
                        <input type="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <div class="ancho-completo">
                        <label>Rol</label>
                        <div class="selector-personalizado" id="selectorRol">
                            <input type="hidden" name="rol">
                            <span class="opcion-seleccionada">Seleccionar</span>
                            <div class="lista-opciones">
                                <div>admin</div>
                                <div>user</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="formulario-botones">
                    <button type="button" id="cancelarUsuario">Cancelar</button>
                    <button type="button" id="guardarUsuario">Agregar Usuario</button>
                </div>
                <div id="mensajeAgregarUsuario" style="margin-top:10px;font-weight:bold;"></div>
            </form>
        </div>
    </div>

    <!-- ========== MODAL VER USUARIOS ========== -->
    <div id="modalVerUsuarios" class="modal">
        <div class="modal-contenido" style="max-width:680px;">
            <h2>Ver Usuarios</h2>
            <div class="contenedor-tabla" style="max-height:340px;">
                <table id="tablaUsuarios" class="tabla-activos">
                    <colgroup>
                        <col style="width:40%">
                        <col style="width:16%">
                        <col style="width:28%">
                        <col style="width:16%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Cambiar Rol</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="formulario-botones">
                <button type="button" id="cerrarVerUsuarios">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- ========== MODAL CAMBIO DE ROL ========== -->
    <div id="modalCambioRol" class="modal">
        <div class="modal-contenido">
            <h3>Confirmar Cambio de Rol</h3>
            <input type="hidden" id="cambioRolUsername">
            <input type="hidden" id="cambioRolNuevoRol">
            <input type="password" id="passwordAdminInput" placeholder="Contraseña de administrador">
            <div id="mensajeCambioRol" style="margin-bottom:10px;"></div>
            <div class="formulario-botones">
                <button type="button" id="cancelarCambioRol">Cancelar</button>
                <button type="button" id="confirmarCambioRol">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- ========== SOLICITUDES: botón, panel y modal resolución ========== -->
    <button id="btnSolicitudes" style="bottom:70px;" title="Solicitudes de equipo">
        📋 Solicitudes <span id="badgePendientes" class="badge-contador"></span>
    </button>

    <div id="overlaySolicitudes"></div>
    <div id="panelSolicitudes">
        <div class="panel-sol-header">
            <h2>📋 Solicitudes de equipo</h2>
            <button id="cerrarSolicitudes">✕</button>
        </div>
        <div class="panel-sol-cuerpo">
            <div id="filtrosSol" class="filtros-sol">
                <button class="filtro-btn activo" data-filtro="todas">Todas</button>
                <button class="filtro-btn" data-filtro="pendiente">🟡 Pendientes</button>
                <button class="filtro-btn" data-filtro="aprobada">🟢 Aprobadas</button>
                <button class="filtro-btn" data-filtro="rechazada">🔴 Rechazadas</button>
            </div>
            <div style="overflow-x:auto;">
                <table class="tabla-sol">
                    <thead>
                        <tr>
                            <th>Solicitante</th>
                            <th>Categoría</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpTablaSol">
                        <tr><td colspan="6" style="text-align:center;color:#999;padding:20px;">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mini modal: aprobar o rechazar solicitud -->
    <div id="modalResolucion" class="modal">
        <div class="modal-contenido" style="width:420px;max-width:95%;">
            <h2 id="resolTitulo">Resolver solicitud</h2>
            <div class="formulario-cuadricula">
                <div class="ancho-completo">
                    <label>Comentario (opcional)</label>
                    <textarea id="resolComentario" placeholder="Agrega un comentario para el solicitante..."></textarea>
                </div>
            </div>
            <div id="msgResolucion" style="text-align:center;font-weight:700;font-size:0.9em;min-height:1.4em;margin-top:10px;"></div>
            <div class="formulario-botones">
                <button type="button" id="cancelarResolucion">Cancelar</button>
                <button type="button" id="confirmarResolucion">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="../recursos/js/peticiones.js?v=2"></script>
    <script src="../recursos/js/utilidades.js?v=2"></script>
    <script src="../recursos/js/menu.js?v=3"></script>
    <script src="../recursos/js/metricas.js?v=2"></script>
    <script src="../recursos/js/solicitudes.js?v=4"></script>
    <script src="../recursos/js/notificaciones.js?v=6"></script>
    <script src="../recursos/js/temas.js?v=1"></script>
</body>
</html>
