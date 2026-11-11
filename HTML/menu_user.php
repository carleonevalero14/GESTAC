<?php require_once __DIR__ . '/../configuracion/sesion.php'; requerirSesion(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú Principal — GESTAC</title>
    <script>(function(){var t=localStorage.getItem('gestac_tema');if(t&&t!=='original')document.documentElement.classList.add('tema-'+t);}());</script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../recursos/css/base.css">
    <link rel="stylesheet" href="../recursos/css/temas.css">
    <link rel="stylesheet" href="../recursos/css/componentes.css">
    <link rel="stylesheet" href="../recursos/css/menu.css">
    <link rel="stylesheet" href="../recursos/css/solicitudes.css">
    <link rel="stylesheet" href="../recursos/css/notificaciones.css">
    <link rel="stylesheet" href="../recursos/css/cmdb.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Modal de aviso (solo usuario) */
        #modalAviso {
            display: none;
            position: fixed;
            z-index: 9999;
            inset: 0;
            background: rgba(0,0,0,0.55);
            justify-content: center;
            align-items: center;
        }
        .contenido-aviso {
            background: #fff;
            padding: 25px 30px;
            width: 330px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
            animation: emerger 0.2s ease-out;
        }
        @keyframes emerger {
            0%   { transform: scale(0.7); opacity: 0; }
            100% { transform: scale(1);   opacity: 1; }
        }
        .boton-aviso {
            margin-top: 12px;
            padding: 10px 20px;
            border: none;
            background: #007eff;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        .boton-aviso:hover { background: #005fcc; }
    </style>
</head>

<body style="background-image: url('../recursos/imagenes/fondo.png'); background-repeat: no-repeat; background-size: cover; background-position: center;">

    <div class="contenedor-menu con-cmdb">
        <div class="panel-izquierdo">
            <h1 id="GESTAC">GESTAC</h1>
            <div class="contenedor-dona">
                <canvas id="chartCategorias"></canvas>
                <div id="counterDash" class="tablero-contadores">
                    <div class="tarjeta-contador">
                        <span class="numero-contador" id="cntActivos">0</span>
                        <span class="etiqueta-contador">Activos</span>
                    </div>
                    <div class="tarjeta-contador">
                        <span class="numero-contador" id="cntPrestamos">0</span>
                        <span class="etiqueta-contador">Préstamos</span>
                    </div>
                </div>
            </div>
            <div class="nav-tablero">
                
                <p class="etiqueta-dona" id="dashLabel">ACTIVOS POR CATEGORÍA</p>
                
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
                        <a href="#" class="bloqueado">MODIFICAR ACTIVO</a>
                        <a href="ver_activo.php">VER ACTIVO</a>
                    </div>
                </div>

                <!-- ASIGNAR -->
                <div class="tarjeta-modulo tiene-submenu">
                    <img src="../recursos/imagenes/asignar.png" alt="Asignar" class="imagen-modulo">
                    <span class="texto-modulo">ASIGNAR</span>
                    <div class="submenu submenu-derecho">
                        <a href="#" id="btnAsignarActivo">ASIGNAR ACTIVOS</a>
                        <a href="#" class="bloqueado">MODIFICAR ASIGNADO</a>
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

                <!-- CMDB / MONITOREO (solo consulta: las acciones sobre
                     alertas quedan restringidas a administradores) -->
                <a href="cmdb.php" class="tarjeta-modulo tarjeta-modulo-ancha">
                    <img src="../recursos/imagenes/cmdb.png" alt="CMDB" class="imagen-modulo">
                    <span class="texto-modulo">CMDB / MONITOREO</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ================= CONFIGURACIONES ================= -->
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
        <a href="../servicios/logout.php">Cerrar Sesión</a>
    </div>

    <div class="leyenda-usuario">Usuario: <span id="user-type"><?php echo htmlspecialchars(usuarioActual(), ENT_QUOTES, 'UTF-8'); ?></span></div>

    <!-- ========== MODAL AVISO (acceso restringido) ========== -->
    <div id="modalAviso">
        <div class="contenido-aviso">
            <h3>Acceso restringido</h3>
            <p>No cuentas con usuario administrador</p>
            <button id="cerrarAviso" class="boton-aviso">Entendido</button>
        </div>
    </div>

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
                    <div><label>Nombre del producto</label><input type="text" name="nombre"></div>
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
        <div class="modal-contenido">
            <h2>Asignar Activo</h2>
            <form id="formAsignarActivo" method="POST">
                <div class="formulario-cuadricula">
                    <div class="autocompletar">
                        <label>Usuario</label>
                        <input type="text" id="usuario" name="usuario" placeholder="Buscar usuario..." autocomplete="off">
                        <div class="items-autocompletar" id="usuarioList"></div>
                    </div>
                    <div class="autocompletar">
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

    <!-- ========== MODAL AGREGAR USUARIO (placeholder para que el JS no falle) ========== -->
    <div id="modalAgregarUsuario" class="modal">
        <div class="modal-contenido">
            <h2>Agregar Usuario</h2>
            <form id="formAgregarUsuario">
                <div class="formulario-cuadricula">
                    <div class="ancho-completo"><label>Correo</label><input type="email" name="correo" placeholder="usuario@ejemplo.com"></div>
                    <div class="ancho-completo"><label>Contraseña</label><input type="password" name="password" placeholder="Contraseña"></div>
                    <div class="ancho-completo">
                        <label>Rol</label>
                        <div class="selector-personalizado" id="selectorRol">
                            <input type="hidden" name="rol">
                            <span class="opcion-seleccionada">Seleccionar</span>
                            <div class="lista-opciones"><div>admin</div><div>user</div></div>
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

    <!-- ========== MODAL VER USUARIOS (placeholder) ========== -->
    <div id="modalVerUsuarios" class="modal">
        <div class="modal-contenido">
            <h2>Ver Usuarios</h2>
            <table id="tablaUsuarios">
                <thead><tr><th>Usuario</th><th>Rol</th><th>Cambiar Rol</th><th>Acción</th></tr></thead>
                <tbody></tbody>
            </table>
            <div class="formulario-botones">
                <button type="button" id="cerrarVerUsuarios">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- ========== SOLICITUDES: botón y panel ========== -->
    <button id="btnSolicitudes" title="Solicitudes de equipo">📋 Solicitudes</button>

    <div id="overlaySolicitudes"></div>
    <div id="panelSolicitudes">
        <div class="panel-sol-header">
            <h2>📋 Solicitudes de equipo</h2>
            <button id="cerrarSolicitudes">✕</button>
        </div>
        <div class="panel-sol-cuerpo">
            <!-- Formulario nueva solicitud -->
            <p class="sol-seccion-titulo">Nueva solicitud</p>
            <form id="formNuevaSolicitud" class="form-sol">
                <div>
                    <label for="solCategoria">Categoría del equipo</label>
                    <select id="solCategoria" name="categoria">
                        <option value="">Seleccionar...</option>
                        <option value="Laptop">Laptop</option>
                        <option value="PC">PC</option>
                        <option value="All in one">All in one</option>
                    </select>
                </div>
                <div>
                    <label for="solMotivo">Motivo de la solicitud</label>
                    <textarea id="solMotivo" name="motivo" placeholder="Describe por qué necesitas el equipo..."></textarea>
                </div>
                <button type="submit" class="btn-enviar-sol">Enviar solicitud</button>
                <p id="msgNuevaSolicitud" class="msg-sol"></p>
            </form>

            <!-- Mis solicitudes -->
            <p class="sol-seccion-titulo">Mis solicitudes</p>
            <div style="overflow-x:auto;">
                <table class="tabla-sol">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Comentario</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoMisSol">
                        <tr><td colspan="5" style="text-align:center;color:#999;padding:16px;">—</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../recursos/js/peticiones.js"></script>
    <script src="../recursos/js/utilidades.js"></script>
    <script src="../recursos/js/menu.js"></script>
    <script src="../recursos/js/solicitudes.js?v=4"></script>
    <script src="../recursos/js/notificaciones.js?v=6"></script>
    <script src="../recursos/js/temas.js?v=1"></script>
</body>
</html>
