<?php
ob_start();
require_once __DIR__ . '/../configuracion/sesion.php';
require_once __DIR__ . '/../configuracion/base_datos.php';
requerirSesionApi();
ob_clean();
header('Content-Type: application/json; charset=utf-8');

$accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');

try {
    $pdo = obtenerConexion();

    // ── GET listar ──────────────────────────────────────────────────────────
    if ($accion === 'listar') {
        if (esAdmin()) {
            $stmt = $pdo->query(
                "SELECT id, solicitante, categoria, motivo, estado,
                        fecha_solicitud, fecha_resolucion, comentario_admin, activo_asignado
                 FROM solicitudes ORDER BY fecha_solicitud DESC"
            );
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, solicitante, categoria, motivo, estado,
                        fecha_solicitud, fecha_resolucion, comentario_admin, activo_asignado
                 FROM solicitudes WHERE solicitante = ?
                 ORDER BY fecha_solicitud DESC"
            );
            $stmt->execute([usuarioActual()]);
        }
        echo json_encode(['exito' => true, 'datos' => $stmt->fetchAll()]);
        exit;
    }

    // ── GET contar_pendientes (solo admin) ──────────────────────────────────
    if ($accion === 'contar_pendientes') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'Sin permiso']);
            exit;
        }
        $stmt = $pdo->query("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'");
        echo json_encode(['exito' => true, 'pendientes' => (int) $stmt->fetchColumn()]);
        exit;
    }

    // ── POST crear (cualquier sesión) ───────────────────────────────────────
    if ($accion === 'crear') {
        $categoria = trim($_POST['categoria'] ?? '');
        $motivo    = trim($_POST['motivo']    ?? '');
        if (!$categoria || !$motivo) {
            echo json_encode(['exito' => false, 'mensaje' => 'Categoría y motivo son requeridos']);
            exit;
        }
        $solicitante = usuarioActual();
        $stmt = $pdo->prepare(
            "INSERT INTO solicitudes (solicitante, categoria, motivo) VALUES (?, ?, ?)"
        );
        $stmt->execute([$solicitante, $categoria, $motivo]);
        $idNuevo = $pdo->lastInsertId();
        registrarLog($pdo, 'solicitudes', 'INSERT', $idNuevo,
            "Solicitud de $categoria por $solicitante");
        // Notificar a todos los admins
        $admins   = $pdo->query("SELECT username FROM users WHERE rol = 'admin'")->fetchAll();
        $stmtNotif = $pdo->prepare(
            "INSERT INTO notificaciones (destinatario, tipo, mensaje) VALUES (?, 'solicitud', ?)"
        );
        foreach ($admins as $admin) {
            $stmtNotif->execute([
                $admin['username'],
                "📋 $solicitante solicitó un equipo: $categoria"
            ]);
        }
        echo json_encode(['exito' => true, 'mensaje' => 'Solicitud enviada correctamente']);
        exit;
    }

    // ── POST resolver (solo admin) ──────────────────────────────────────────
    if ($accion === 'resolver') {
        if (!esAdmin()) {
            http_response_code(403);
            echo json_encode(['exito' => false, 'mensaje' => 'Sin permiso']);
            exit;
        }
        $id              = (int) ($_POST['id']              ?? 0);
        $estado          =        $_POST['estado']          ?? '';
        $comentario      = trim(  $_POST['comentario']      ?? '');
        $activo_asignado = trim(  $_POST['activo_asignado'] ?? '');

        if (!$id || !in_array($estado, ['aprobada', 'rechazada'])) {
            echo json_encode(['exito' => false, 'mensaje' => 'Datos inválidos']);
            exit;
        }
        $stmt = $pdo->prepare(
            "UPDATE solicitudes
             SET estado = ?, fecha_resolucion = NOW(),
                 comentario_admin = ?, activo_asignado = ?
             WHERE id = ? AND estado = 'pendiente'"
        );
        $stmt->execute([
            $estado,
            $comentario ?: null,
            $activo_asignado ?: null,
            $id
        ]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['exito' => false, 'mensaje' => 'La solicitud ya fue resuelta']);
            exit;
        }
        // Notificar al solicitante
        $stmtInfo = $pdo->prepare("SELECT solicitante, categoria FROM solicitudes WHERE id = ?");
        $stmtInfo->execute([$id]);
        $infoSol = $stmtInfo->fetch();
        if ($infoSol) {
            $emoji = $estado === 'aprobada' ? '✅' : '❌';
            $pdo->prepare(
                "INSERT INTO notificaciones (destinatario, tipo, mensaje) VALUES (?, 'resolucion', ?)"
            )->execute([
                $infoSol['solicitante'],
                "$emoji Tu solicitud de {$infoSol['categoria']} fue $estado"
            ]);
        }
        registrarLog($pdo, 'solicitudes', 'UPDATE', $id,
            "Solicitud $estado por " . usuarioActual());
        echo json_encode(['exito' => true, 'mensaje' => "Solicitud $estado correctamente"]);
        exit;
    }

    echo json_encode(['exito' => false, 'mensaje' => 'Acción desconocida']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error del servidor']);
}
