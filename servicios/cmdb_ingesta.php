<?php
/**
 * CMDB — Ingesta de reportes del agente.
 * Avance 6 — Módulo CMDB.
 *
 * Este servicio NO usa sesión: lo consume el agente instalado en cada
 * equipo. La autenticación es por token_agente (uno por equipo).
 *
 *   POST ?accion=alta     Registra un equipo nuevo (requiere la clave de
 *                         alta definida en cmdb_umbrales) y devuelve su token.
 *   POST (sin acción)     Reporte periódico: valida token, actualiza el
 *                         equipo y sus discos, inserta telemetría, detecta
 *                         cambios (diff) y evalúa las alertas preventivas.
 */
ob_start();
require_once __DIR__ . '/../configuracion/sesion.php';      // solo para registrarLog()
require_once __DIR__ . '/../configuracion/base_datos.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

$accion = $_GET['accion'] ?? 'reporte';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exito' => false, 'mensaje' => 'Solo se aceptan peticiones POST']);
    exit;
}

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
    echo json_encode(['exito' => false, 'mensaje' => 'Cuerpo JSON inválido']);
    exit;
}

try {
    $pdo = obtenerConexion();

    // ===================== ALTA DE EQUIPO NUEVO =====================
    if ($accion === 'alta') {
        $hostname = trim($datos['hostname'] ?? '');
        if ($hostname === '') {
            echo json_encode(['exito' => false, 'mensaje' => 'hostname es obligatorio']);
            exit;
        }
        if (($datos['clave_alta'] ?? '') !== obtenerUmbral($pdo, 'clave_alta', '')) {
            echo json_encode(['exito' => false, 'mensaje' => 'Clave de alta inválida']);
            exit;
        }

        $chk = $pdo->prepare("SELECT COUNT(*) FROM cmdb_equipos WHERE hostname = ?");
        $chk->execute([$hostname]);
        if ((int) $chk->fetchColumn() > 0) {
            echo json_encode(['exito' => false, 'mensaje' => 'El equipo ya está registrado']);
            exit;
        }

        // Si mandan id_activo se valida que exista para no romper la FK.
        $idActivo = trim($datos['id_activo'] ?? '');
        if ($idActivo !== '') {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM activos WHERE id_producto = ?");
            $chk->execute([$idActivo]);
            if ((int) $chk->fetchColumn() === 0) $idActivo = '';
        }

        $token = bin2hex(random_bytes(32));
        $stmt  = $pdo->prepare(
            "INSERT INTO cmdb_equipos
               (hostname, id_activo, usuario_asignado, marca, modelo, num_serie, cpu,
                ram_total_gb, so_nombre, so_version, mac, ip_privada, token_agente)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $hostname,
            $idActivo !== '' ? $idActivo : null,
            $datos['usuario_asignado'] ?? null,
            $datos['marca']            ?? null,
            $datos['modelo']           ?? null,
            $datos['num_serie']        ?? null,
            $datos['cpu']              ?? null,
            $datos['ram_total_gb']     ?? null,
            $datos['so_nombre']        ?? null,
            $datos['so_version']       ?? null,
            $datos['mac']              ?? null,
            $datos['ip_privada']       ?? null,
            $token,
        ]);
        $idEquipo = (int) $pdo->lastInsertId();
        registrarLog($pdo, 'cmdb_equipos', 'alta', $idEquipo, "Equipo $hostname dado de alta en el CMDB");

        echo json_encode(['exito' => true, 'mensaje' => 'Equipo registrado', 'id_equipo' => $idEquipo, 'token' => $token]);
        exit;
    }

    // ===================== REPORTE PERIÓDICO =====================
    $token = trim($datos['token'] ?? '');
    if ($token === '') {
        echo json_encode(['exito' => false, 'mensaje' => 'token es obligatorio']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM cmdb_equipos WHERE token_agente = ?");
    $stmt->execute([$token]);
    $equipo = $stmt->fetch();
    if (!$equipo) {
        http_response_code(401);
        echo json_encode(['exito' => false, 'mensaje' => 'Token no reconocido']);
        exit;
    }
    if ($equipo['estado'] === 'baja') {
        echo json_encode(['exito' => false, 'mensaje' => 'El equipo está dado de baja']);
        exit;
    }

    $idEquipo = (int) $equipo['id_equipo'];
    $reporte  = $datos['equipo']     ?? [];
    $discos   = $datos['discos']     ?? [];
    $telem    = $datos['telemetria'] ?? [];

    // ---------- Detección de cambios (diff de campos sensibles) ----------
    $sensibles = [
        'hostname'         => 'sistema',
        'usuario_asignado' => 'sistema',
        'cpu'              => 'hardware',
        'ram_total_gb'     => 'hardware',
        'so_nombre'        => 'software',
        'so_version'       => 'software',
        'mac'              => 'red',
        'ip_privada'       => 'red',
    ];
    $cambios       = [];
    $huboHardware  = false;
    foreach ($sensibles as $campo => $tipo) {
        if (!array_key_exists($campo, $reporte)) continue;
        $anterior = trim((string) ($equipo[$campo] ?? ''));
        $nuevo    = trim((string) $reporte[$campo]);
        if ($campo === 'ram_total_gb') {
            $anterior = $anterior === '' ? '' : number_format((float) $anterior, 1, '.', '');
            $nuevo    = $nuevo    === '' ? '' : number_format((float) $nuevo, 1, '.', '');
        }
        if ($nuevo !== '' && $nuevo !== $anterior) {
            $cambios[] = [$tipo, $campo, $anterior, $nuevo];
            if ($tipo === 'hardware') $huboHardware = true;
        }
    }

    // ---------- Diff de discos (unidades agregadas / retiradas / cambiadas) ----------
    $stmt = $pdo->prepare("SELECT unidad, capacidad_gb FROM cmdb_discos WHERE id_equipo = ?");
    $stmt->execute([$idEquipo]);
    $discosPrevios = [];
    foreach ($stmt->fetchAll() as $d) $discosPrevios[$d['unidad']] = (float) $d['capacidad_gb'];

    $unidadesReportadas = [];
    foreach ($discos as $disco) {
        $unidad = trim($disco['unidad'] ?? '');
        if ($unidad === '') continue;
        $unidadesReportadas[] = $unidad;
        $capacidad = (float) ($disco['capacidad_gb'] ?? 0);
        if (!isset($discosPrevios[$unidad])) {
            // La primera carga del equipo (sin discos previos) no cuenta como cambio.
            if (!empty($discosPrevios)) {
                $cambios[] = ['hardware', "disco $unidad", '(sin unidad)', "$capacidad GB"];
                $huboHardware = true;
            }
        } elseif (abs($discosPrevios[$unidad] - $capacidad) > 1) {
            $cambios[] = ['hardware', "disco $unidad", $discosPrevios[$unidad] . ' GB', "$capacidad GB"];
            $huboHardware = true;
        }
    }
    foreach ($discosPrevios as $unidad => $capacidad) {
        if (!in_array($unidad, $unidadesReportadas, true)) {
            $cambios[] = ['hardware', "disco $unidad", "$capacidad GB", '(retirada)'];
            $huboHardware = true;
        }
    }

    // ---------- Upsert del equipo ----------
    $stmt = $pdo->prepare(
        "UPDATE cmdb_equipos SET
            hostname         = COALESCE(NULLIF(?, ''), hostname),
            usuario_asignado = COALESCE(NULLIF(?, ''), usuario_asignado),
            cpu              = COALESCE(NULLIF(?, ''), cpu),
            ram_total_gb     = COALESCE(?, ram_total_gb),
            so_nombre        = COALESCE(NULLIF(?, ''), so_nombre),
            so_version       = COALESCE(NULLIF(?, ''), so_version),
            mac              = COALESCE(NULLIF(?, ''), mac),
            ip_privada       = COALESCE(NULLIF(?, ''), ip_privada),
            ip_publica       = COALESCE(NULLIF(?, ''), ip_publica),
            ultima_conexion  = NOW(),
            estado           = 'online'
         WHERE id_equipo = ?"
    );
    $stmt->execute([
        $reporte['hostname']         ?? '',
        $reporte['usuario_asignado'] ?? '',
        $reporte['cpu']              ?? '',
        isset($reporte['ram_total_gb']) ? (float) $reporte['ram_total_gb'] : null,
        $reporte['so_nombre']        ?? '',
        $reporte['so_version']       ?? '',
        $reporte['mac']              ?? '',
        $reporte['ip_privada']       ?? '',
        $reporte['ip_publica']       ?? '',
        $idEquipo,
    ]);

    // ---------- Actualización de discos ----------
    if (!empty($unidadesReportadas)) {
        $marcadores = implode(',', array_fill(0, count($unidadesReportadas), '?'));
        $stmt = $pdo->prepare("DELETE FROM cmdb_discos WHERE id_equipo = ? AND unidad NOT IN ($marcadores)");
        $stmt->execute(array_merge([$idEquipo], $unidadesReportadas));

        $stmt = $pdo->prepare(
            "INSERT INTO cmdb_discos (id_equipo, unidad, capacidad_gb, usado_gb, libre_gb, libre_pct, estado_smart)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                capacidad_gb = VALUES(capacidad_gb),
                usado_gb     = VALUES(usado_gb),
                libre_gb     = VALUES(libre_gb),
                libre_pct    = VALUES(libre_pct),
                estado_smart = VALUES(estado_smart)"
        );
        foreach ($discos as $disco) {
            $unidad = trim($disco['unidad'] ?? '');
            if ($unidad === '') continue;
            $capacidad = (float) ($disco['capacidad_gb'] ?? 0);
            $libre     = (float) ($disco['libre_gb'] ?? 0);
            $usado     = isset($disco['usado_gb']) ? (float) $disco['usado_gb'] : max($capacidad - $libre, 0);
            $librePct  = $capacidad > 0 ? round($libre / $capacidad * 100, 1) : 0;
            $stmt->execute([$idEquipo, $unidad, $capacidad, $usado, $libre, $librePct, $disco['estado_smart'] ?? 'OK']);
        }
    }

    // ---------- Telemetría ----------
    $stmt = $pdo->prepare(
        "INSERT INTO cmdb_telemetria (id_equipo, uso_cpu, ram_libre_gb, uptime_horas, bateria_pct, ip, payload_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $idEquipo,
        isset($telem['uso_cpu'])      ? (float) $telem['uso_cpu']      : null,
        isset($telem['ram_libre_gb']) ? (float) $telem['ram_libre_gb'] : null,
        isset($telem['uptime_horas']) ? (float) $telem['uptime_horas'] : null,
        isset($telem['bateria_pct'])  ? (int) $telem['bateria_pct']    : null,
        $reporte['ip_privada'] ?? ($equipo['ip_privada'] ?? null),
        json_encode($datos, JSON_UNESCAPED_UNICODE),
    ]);

    // ---------- Registro de cambios detectados ----------
    if (!empty($cambios)) {
        $stmt = $pdo->prepare(
            "INSERT INTO cmdb_cambios (id_equipo, tipo, campo, valor_anterior, valor_nuevo) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($cambios as [$tipo, $campo, $anterior, $nuevo]) {
            $stmt->execute([$idEquipo, $tipo, $campo, $anterior, $nuevo]);
        }
        registrarLog($pdo, 'cmdb_cambios', 'diff', $idEquipo,
            count($cambios) . ' cambio(s) detectado(s) en ' . $equipo['hostname']);
    }

    // ---------- Evaluación de alertas preventivas ----------
    $alertasNuevas = 0;
    $pctCritico = (float) obtenerUmbral($pdo, 'almacenamiento_critico_pct', '10');
    $pctBajo    = (float) obtenerUmbral($pdo, 'almacenamiento_bajo_pct', '20');
    $pctBateria = (float) obtenerUmbral($pdo, 'bateria_degradada_pct', '60');

    // El equipo volvió a reportar: se resuelven alertas de "sin reporte".
    $pdo->prepare("UPDATE cmdb_alertas SET estado = 'resuelta'
                   WHERE id_equipo = ? AND tipo = 'sin_reporte' AND estado <> 'resuelta'")
        ->execute([$idEquipo]);

    foreach ($discos as $disco) {
        $unidad = trim($disco['unidad'] ?? '');
        if ($unidad === '') continue;
        $capacidad = (float) ($disco['capacidad_gb'] ?? 0);
        $libre     = (float) ($disco['libre_gb'] ?? 0);
        $librePct  = $capacidad > 0 ? round($libre / $capacidad * 100, 1) : 100;

        if ($librePct < $pctCritico) {
            $alertasNuevas += crearAlerta($pdo, $idEquipo, 'almacenamiento_critico', 'critica',
                "Unidad $unidad: $librePct% de espacio libre (umbral crítico $pctCritico%)", "Unidad $unidad:%");
        } elseif ($librePct < $pctBajo) {
            $alertasNuevas += crearAlerta($pdo, $idEquipo, 'almacenamiento_bajo', 'media',
                "Unidad $unidad: $librePct% de espacio libre (umbral bajo $pctBajo%)", "Unidad $unidad:%");
        } else {
            // La unidad volvió a estar sana: se resuelven sus alertas abiertas.
            $pdo->prepare("UPDATE cmdb_alertas SET estado = 'resuelta'
                           WHERE id_equipo = ? AND tipo IN ('almacenamiento_critico','almacenamiento_bajo')
                             AND estado <> 'resuelta' AND mensaje LIKE ?")
                ->execute([$idEquipo, "Unidad $unidad:%"]);
        }

        $smart = strtoupper(trim($disco['estado_smart'] ?? 'OK'));
        if ($smart !== '' && $smart !== 'OK') {
            $alertasNuevas += crearAlerta($pdo, $idEquipo, 'falla_smart', 'critica',
                "Unidad $unidad: SMART reporta estado $smart", "Unidad $unidad:%");
        }
    }

    if (isset($telem['bateria_pct']) && (float) $telem['bateria_pct'] < $pctBateria) {
        $alertasNuevas += crearAlerta($pdo, $idEquipo, 'bateria_degradada', 'media',
            'Salud de batería en ' . (int) $telem['bateria_pct'] . "% (umbral $pctBateria%)");
    }

    if ($huboHardware) {
        $detalle = [];
        foreach ($cambios as [$tipo, $campo, $anterior, $nuevo]) {
            if ($tipo === 'hardware') $detalle[] = "$campo pasó de $anterior a $nuevo";
        }
        $alertasNuevas += crearAlerta($pdo, $idEquipo, 'cambio_hardware', 'media',
            'Cambio de hardware detectado: ' . implode('; ', array_slice($detalle, 0, 2)));
    }

    echo json_encode([
        'exito'          => true,
        'mensaje'        => 'Reporte procesado',
        'cambios'        => count($cambios),
        'alertas_nuevas' => $alertasNuevas,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error en el servidor']);
}

// ===================== FUNCIONES AUXILIARES =====================

/** Lee un parámetro de cmdb_umbrales con valor por defecto. */
function obtenerUmbral(PDO $pdo, string $parametro, string $porDefecto): string {
    $stmt = $pdo->prepare("SELECT valor FROM cmdb_umbrales WHERE parametro = ?");
    $stmt->execute([$parametro]);
    $valor = $stmt->fetchColumn();
    return $valor !== false ? (string) $valor : $porDefecto;
}

/**
 * Crea una alerta si no existe ya una abierta/reconocida del mismo tipo para
 * el equipo (para almacenamiento se distingue por unidad vía $patronMensaje).
 * Las alertas críticas se notifican a los administradores. Devuelve 1 si creó.
 */
function crearAlerta(PDO $pdo, int $idEquipo, string $tipo, string $severidad, string $mensaje, string $patronMensaje = ''): int {
    $sql    = "SELECT COUNT(*) FROM cmdb_alertas
               WHERE id_equipo = ? AND tipo = ? AND estado <> 'resuelta'";
    $params = [$idEquipo, $tipo];
    if ($patronMensaje !== '') {
        $sql .= " AND mensaje LIKE ?";
        $params[] = $patronMensaje;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ((int) $stmt->fetchColumn() > 0) return 0;

    $stmt = $pdo->prepare(
        "INSERT INTO cmdb_alertas (id_equipo, tipo, severidad, mensaje) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$idEquipo, $tipo, $severidad, $mensaje]);
    registrarLog($pdo, 'cmdb_alertas', 'alerta', $pdo->lastInsertId(), "[$severidad] $mensaje");

    // Las críticas se envían al centro de notificaciones de los admins.
    if ($severidad === 'critica') {
        try {
            $hostname = $pdo->query("SELECT hostname FROM cmdb_equipos WHERE id_equipo = " . (int) $idEquipo)->fetchColumn();
            $admins   = $pdo->query("SELECT username FROM users WHERE rol = 'admin'")->fetchAll();
            $notif    = $pdo->prepare(
                "INSERT INTO notificaciones (destinatario, tipo, mensaje, url) VALUES (?, 'cmdb', ?, ?)"
            );
            foreach ($admins as $admin) {
                $notif->execute([$admin['username'], "⚠ $hostname: $mensaje", 'cmdb.php']);
            }
        } catch (Throwable $e) {
            // La notificación no debe romper la ingesta.
        }
    }
    return 1;
}
