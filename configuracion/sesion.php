<?php
/**
 * Gestión de sesión y control de acceso de GESTAC.
 * Avance 1 — Seguridad de sesión.
 *
 * Este archivo se incluye al inicio de cada página protegida y de cada
 * servicio. Arranca la sesión y expone funciones para verificar el acceso.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Indica si existe una sesión iniciada. */
function haySesion(): bool {
    return isset($_SESSION['usuario']) && $_SESSION['usuario'] !== '';
}

/** Devuelve el rol del usuario en sesión ('admin', 'user' o ''). */
function rolActual(): string {
    return $_SESSION['rol'] ?? '';
}

/** Devuelve el nombre de usuario en sesión. */
function usuarioActual(): string {
    return $_SESSION['usuario'] ?? '';
}

/** Indica si el usuario en sesión es administrador. */
function esAdmin(): bool {
    return rolActual() === 'admin';
}

/**
 * Protege una PÁGINA. Si no hay sesión válida redirige al login;
 * si se exige rol 'admin' y el usuario no lo es, lo manda a su menú.
 */
function requerirSesion(string $rolRequerido = ''): void {
    if (!haySesion()) {
        header('Location: login.php?expirada=1');
        exit;
    }
    if ($rolRequerido === 'admin' && !esAdmin()) {
        header('Location: menu_user.php?denegado=1');
        exit;
    }
}

/**
 * Protege un SERVICIO (API JSON). Si no hay sesión válida responde 401;
 * si se exige rol 'admin' y el usuario no lo es, responde 403.
 */
function requerirSesionApi(string $rolRequerido = ''): void {
    if (!haySesion()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['exito' => false, 'mensaje' => 'Sesión no válida', 'sesion' => false]);
        exit;
    }
    if ($rolRequerido === 'admin' && !esAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['exito' => false, 'mensaje' => 'Acceso restringido a administradores']);
        exit;
    }
}

/**
 * Registra un evento en la tabla `logs`. Nunca interrumpe el flujo:
 * si el registro falla, el error se ignora silenciosamente.
 */
function registrarLog(PDO $pdo, string $tabla, string $accion, $registroId, string $descripcion): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO logs (tabla, accion, registro_id, descripcion) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$tabla, $accion, (string) $registroId, $descripcion]);
    } catch (Throwable $e) {
        // El registro de bitácora no debe romper la operación principal.
    }
}
