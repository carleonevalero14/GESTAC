<?php
require_once __DIR__ . '/../configuracion/sesion.php';
require_once __DIR__ . '/../configuracion/base_datos.php';
requerirSesionApi();
header('Content-Type: application/json; charset=utf-8');

$accion  = $_GET['accion'] ?? ($_POST['accion'] ?? '');
$usuario = usuarioActual();

try {
    $pdo = obtenerConexion();

    // ── GET listar_no_leidas ────────────────────────────────────────────────
    if ($accion === 'listar_no_leidas') {
        $stmt = $pdo->prepare(
            "SELECT id, tipo, mensaje, url, leida, fecha
             FROM notificaciones
             WHERE destinatario = ? AND leida = 0
             ORDER BY fecha DESC LIMIT 20"
        );
        $stmt->execute([$usuario]);
        echo json_encode(['exito' => true, 'datos' => $stmt->fetchAll()]);
        exit;
    }

    // ── GET contar_no_leidas ────────────────────────────────────────────────
    if ($accion === 'contar_no_leidas') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notificaciones WHERE destinatario = ? AND leida = 0"
        );
        $stmt->execute([$usuario]);
        echo json_encode(['exito' => true, 'total' => (int) $stmt->fetchColumn()]);
        exit;
    }

    // ── POST marcar_leida ───────────────────────────────────────────────────
    if ($accion === 'marcar_leida') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['exito' => false]); exit; }
        $stmt = $pdo->prepare(
            "UPDATE notificaciones SET leida = 1 WHERE id = ? AND destinatario = ?"
        );
        $stmt->execute([$id, $usuario]);
        echo json_encode(['exito' => true]);
        exit;
    }

    // ── POST marcar_todas_leidas ────────────────────────────────────────────
    if ($accion === 'marcar_todas_leidas') {
        $stmt = $pdo->prepare(
            "UPDATE notificaciones SET leida = 1 WHERE destinatario = ? AND leida = 0"
        );
        $stmt->execute([$usuario]);
        echo json_encode(['exito' => true]);
        exit;
    }

    echo json_encode(['exito' => false, 'mensaje' => 'Acción desconocida']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error del servidor']);
}
