<?php
/**
 * CMDB — Consultas del tablero y el detalle de equipos.
 * Avance 6 — Módulo CMDB.
 *
 * Acciones (GET ?accion=):
 *   listar          Equipos con su peor unidad de disco y alertas abiertas + KPIs.
 *   detalle&id=     Ficha completa de un equipo (discos, telemetría, alertas, cambios).
 *   tendencia&id=   Últimos reportes de telemetría para la gráfica de tendencia.
 *   alertas         Alertas preventivas activas (abiertas y reconocidas).
 *   cambios[&id=]   Bitácora de cambios (global o de un equipo).
 *   exportar_excel  Reporte del parque en Excel (patrón HTML del proyecto).
 *
 * Acciones (POST):
 *   resolver_alerta Cambia el estado de una alerta (solo administradores).
 */
ob_start();
require_once __DIR__ . '/../configuracion/sesion.php';
require_once __DIR__ . '/../configuracion/base_datos.php';
requerirSesionApi();

$accion = $_GET['accion'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// ===================== EXPORTAR EXCEL =====================
if ($metodo === 'GET' && $accion === 'exportar_excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=reporte_cmdb.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    $pdo   = obtenerConexion();
    $stmt  = $pdo->query(
        "SELECT e.hostname, e.id_activo, e.usuario_asignado, e.marca, e.modelo, e.cpu,
                e.ram_total_gb, e.so_nombre, e.so_version, e.ip_privada, e.mac,
                e.ultima_conexion, e.estado,
                COALESCE(MIN(d.libre_pct), 100) AS libre_pct_min,
                COUNT(DISTINCT a.id) AS alertas_abiertas
         FROM cmdb_equipos e
         LEFT JOIN cmdb_discos d ON d.id_equipo = e.id_equipo
         LEFT JOIN cmdb_alertas a ON a.id_equipo = e.id_equipo AND a.estado = 'abierta'
         GROUP BY e.id_equipo
         ORDER BY e.hostname"
    );
    $filas = $stmt->fetchAll();

    echo generarExcelHtml('REPORTE CMDB — MONITOREO DE EQUIPOS',
        ['Hostname', 'Activo', 'Usuario', 'Marca', 'Modelo', 'CPU', 'RAM (GB)', 'SO', 'Versión SO',
         'IP privada', 'MAC', 'Última conexión', 'Estado', '% libre mínimo', 'Alertas abiertas'],
        $filas,
        ['hostname', 'id_activo', 'usuario_asignado', 'marca', 'modelo', 'cpu', 'ram_total_gb',
         'so_nombre', 'so_version', 'ip_privada', 'mac', 'ultima_conexion', 'estado',
         'libre_pct_min', 'alertas_abiertas']
    );
    exit;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = obtenerConexion();

    // ===================== LISTAR (tablero) =====================
    if ($metodo === 'GET' && $accion === 'listar') {
        marcarEquiposSinReporte($pdo);

        $stmt = $pdo->query(
            "SELECT e.id_equipo, e.hostname, e.id_activo, e.usuario_asignado, e.ip_privada,
                    e.so_nombre, e.ultima_conexion, e.estado,
                    COALESCE(MIN(d.libre_pct), 100) AS libre_pct_min,
                    GROUP_CONCAT(DISTINCT CONCAT(d.unidad, ' ', d.libre_pct, '%') SEPARATOR ' · ') AS resumen_discos,
                    MIN(CASE WHEN UPPER(d.estado_smart) <> 'OK' THEN d.estado_smart END) AS smart_alerta,
                    COUNT(DISTINCT a.id) AS alertas_abiertas
             FROM cmdb_equipos e
             LEFT JOIN cmdb_discos d ON d.id_equipo = e.id_equipo
             LEFT JOIN cmdb_alertas a ON a.id_equipo = e.id_equipo AND a.estado = 'abierta'
             GROUP BY e.id_equipo
             ORDER BY (e.estado = 'online') DESC, e.hostname"
        );
        $equipos = $stmt->fetchAll();

        $kpis = ['total' => 0, 'online' => 0, 'offline' => 0, 'baja' => 0, 'alertas_abiertas' => 0];
        foreach ($equipos as $e) {
            $kpis['total']++;
            if (isset($kpis[$e['estado']])) $kpis[$e['estado']]++;
        }
        $kpis['alertas_abiertas'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM cmdb_alertas WHERE estado = 'abierta'"
        )->fetchColumn();

        // 'ahora' permite al frontend calcular "hace cuánto" sin depender
        // del reloj de la máquina del usuario.
        echo json_encode(['exito' => true, 'datos' => $equipos, 'kpis' => $kpis, 'ahora' => date('Y-m-d H:i:s')]);
        exit;
    }

    // ===================== DETALLE DE UN EQUIPO =====================
    if ($metodo === 'GET' && $accion === 'detalle') {
        $id = (int) ($_GET['id'] ?? 0);
        marcarEquiposSinReporte($pdo);

        $stmt = $pdo->prepare(
            "SELECT e.*, a.nombre AS nombre_activo, a.categoria AS categoria_activo
             FROM cmdb_equipos e
             LEFT JOIN activos a ON a.id_producto = e.id_activo
             WHERE e.id_equipo = ?"
        );
        $stmt->execute([$id]);
        $equipo = $stmt->fetch();
        if (!$equipo) {
            echo json_encode(['exito' => false, 'mensaje' => 'Equipo no encontrado']);
            exit;
        }
        unset($equipo['token_agente']); // el token nunca viaja al frontend

        $stmt = $pdo->prepare("SELECT * FROM cmdb_discos WHERE id_equipo = ? ORDER BY unidad");
        $stmt->execute([$id]);
        $discos = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT fecha, tipo, severidad, mensaje, estado, id
             FROM cmdb_alertas WHERE id_equipo = ?
             ORDER BY (estado = 'abierta') DESC, fecha DESC LIMIT 30"
        );
        $stmt->execute([$id]);
        $alertas = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT fecha, tipo, campo, valor_anterior, valor_nuevo
             FROM cmdb_cambios WHERE id_equipo = ? ORDER BY fecha DESC LIMIT 50"
        );
        $stmt->execute([$id]);
        $cambios = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT uso_cpu, ram_libre_gb, uptime_horas, bateria_pct, fecha_reporte
             FROM cmdb_telemetria WHERE id_equipo = ? ORDER BY fecha_reporte DESC LIMIT 1"
        );
        $stmt->execute([$id]);
        $telemetria = $stmt->fetch() ?: null;

        echo json_encode(['exito' => true, 'datos' => [
            'equipo' => $equipo, 'discos' => $discos, 'alertas' => $alertas,
            'cambios' => $cambios, 'telemetria' => $telemetria,
        ], 'ahora' => date('Y-m-d H:i:s')]);
        exit;
    }

    // ===================== TENDENCIA (telemetría histórica) =====================
    if ($metodo === 'GET' && $accion === 'tendencia') {
        $id   = (int) ($_GET['id'] ?? 0);
        $stmt = $pdo->prepare(
            "SELECT fecha_reporte, uso_cpu, ram_libre_gb, bateria_pct
             FROM cmdb_telemetria WHERE id_equipo = ?
             ORDER BY fecha_reporte DESC LIMIT 30"
        );
        $stmt->execute([$id]);
        echo json_encode(['exito' => true, 'datos' => array_reverse($stmt->fetchAll())]);
        exit;
    }

    // ===================== ALERTAS ACTIVAS =====================
    if ($metodo === 'GET' && $accion === 'alertas') {
        marcarEquiposSinReporte($pdo);
        $stmt = $pdo->query(
            "SELECT a.id, a.fecha, a.tipo, a.severidad, a.mensaje, a.estado,
                    e.id_equipo, e.hostname
             FROM cmdb_alertas a
             JOIN cmdb_equipos e ON e.id_equipo = a.id_equipo
             WHERE a.estado <> 'resuelta'
             ORDER BY (a.severidad = 'critica') DESC, a.fecha DESC LIMIT 100"
        );
        echo json_encode(['exito' => true, 'datos' => $stmt->fetchAll()]);
        exit;
    }

    // ===================== BITÁCORA DE CAMBIOS =====================
    if ($metodo === 'GET' && $accion === 'cambios') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare(
                "SELECT c.fecha, c.tipo, c.campo, c.valor_anterior, c.valor_nuevo, e.hostname
                 FROM cmdb_cambios c JOIN cmdb_equipos e ON e.id_equipo = c.id_equipo
                 WHERE c.id_equipo = ? ORDER BY c.fecha DESC LIMIT 100"
            );
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->query(
                "SELECT c.fecha, c.tipo, c.campo, c.valor_anterior, c.valor_nuevo, e.hostname
                 FROM cmdb_cambios c JOIN cmdb_equipos e ON e.id_equipo = c.id_equipo
                 ORDER BY c.fecha DESC LIMIT 100"
            );
        }
        echo json_encode(['exito' => true, 'datos' => $stmt->fetchAll()]);
        exit;
    }

    // ===================== POST: RESOLVER / RECONOCER ALERTA =====================
    if ($metodo === 'POST' && ($accion === 'resolver_alerta' || isset($_POST['id_alerta']))) {
        if (!esAdmin()) {
            echo json_encode(['exito' => false, 'mensaje' => 'Acción restringida a administradores']);
            exit;
        }
        $idAlerta = (int) ($_POST['id_alerta'] ?? 0);
        $estado   = $_POST['estado'] ?? '';
        if ($idAlerta <= 0 || !in_array($estado, ['reconocida', 'resuelta'], true)) {
            echo json_encode(['exito' => false, 'mensaje' => 'Datos incompletos']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE cmdb_alertas SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $idAlerta]);
        if ($stmt->rowCount() > 0) {
            registrarLog($pdo, 'cmdb_alertas', $estado, $idAlerta, "Alerta $idAlerta marcada como $estado");
            echo json_encode(['exito' => true, 'mensaje' => "Alerta marcada como $estado"]);
        } else {
            echo json_encode(['exito' => false, 'mensaje' => 'Alerta no encontrada']);
        }
        exit;
    }

    echo json_encode(['exito' => false, 'mensaje' => 'Acción no reconocida']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error en el servidor']);
}

// ===================== FUNCIONES AUXILIARES =====================

/**
 * Marca offline a los equipos online que exceden el umbral de horas sin
 * reportar y les abre una alerta 'sin_reporte' si aún no la tienen.
 */
function marcarEquiposSinReporte(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT valor FROM cmdb_umbrales WHERE parametro = 'horas_sin_reportar'");
    $stmt->execute();
    $horas = (int) ($stmt->fetchColumn() ?: 24);

    $pdo->prepare(
        "UPDATE cmdb_equipos SET estado = 'offline'
         WHERE estado = 'online' AND (ultima_conexion IS NULL OR ultima_conexion < NOW() - INTERVAL ? HOUR)"
    )->execute([$horas]);

    $pdo->prepare(
        "INSERT INTO cmdb_alertas (id_equipo, tipo, severidad, mensaje)
         SELECT e.id_equipo, 'sin_reporte', 'media', CONCAT('El equipo lleva más de ', ?, ' h sin reportar')
         FROM cmdb_equipos e
         WHERE e.estado = 'offline'
           AND (e.ultima_conexion IS NULL OR e.ultima_conexion < NOW() - INTERVAL ? HOUR)
           AND NOT EXISTS (
               SELECT 1 FROM cmdb_alertas a
               WHERE a.id_equipo = e.id_equipo AND a.tipo = 'sin_reporte' AND a.estado <> 'resuelta'
           )"
    )->execute([$horas, $horas]);
}

// ===================== FUNCIÓN AUXILIAR EXCEL =====================
function generarExcelHtml(string $titulo, array $encabezados, array $filas, array $columnas): string {
    $html = '
    <html><head><meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
        th { background-color: #2f6b2f; color: white; padding: 8px; border: 1px solid black; text-align: center; }
        td { border: 1px solid black; padding: 6px; }
        tr:nth-child(even) { background-color: #e6ffe6; }
        .titulo { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }
    </style></head><body>
    <div class="titulo">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</div>
    <table><tr>';

    foreach ($encabezados as $enc) {
        $html .= '<th>' . htmlspecialchars($enc, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr>';

    foreach ($filas as $fila) {
        $html .= '<tr>';
        foreach ($columnas as $col) {
            $html .= '<td>' . htmlspecialchars((string) ($fila[$col] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</table></body></html>';
    return $html;
}
