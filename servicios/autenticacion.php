<?php
require_once __DIR__ . '/../configuracion/sesion.php';
require_once __DIR__ . '/../configuracion/base_datos.php';
header('Content-Type: application/json; charset=utf-8');

// Parámetros del bloqueo por intentos fallidos.
const MAX_INTENTOS    = 5;   // intentos permitidos antes de bloquear
const MINUTOS_BLOQUEO = 15;  // duración del bloqueo de la cuenta

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// ===================== CAMBIO DE CONTRASEÑA =====================
if ($accion === 'cambiar_contrasena') {
    $usuarioActualizar = trim($_POST['usuario_update'] ?? '');
    $nuevaContrasena   = trim($_POST['new_password'] ?? '');

    if (empty($usuarioActualizar) || empty($nuevaContrasena)) {
        echo json_encode(['exito' => false, 'mensaje' => '❌ Usuario o nueva contraseña vacíos']);
        exit;
    }

    try {
        $pdo  = obtenerConexion();
        $stmt = $pdo->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->execute([$usuarioActualizar]);

        if ($stmt->fetch()) {
            $contrasenaHash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
            if ($upd->execute([$contrasenaHash, $usuarioActualizar])) {
                registrarLog($pdo, 'users', 'CLAVE', $usuarioActualizar, "Cambio de contraseña: $usuarioActualizar");
                echo json_encode(['exito' => true, 'mensaje' => '✅ Contraseña actualizada correctamente']);
            } else {
                echo json_encode(['exito' => false, 'mensaje' => '❌ Error al actualizar contraseña']);
            }
        } else {
            echo json_encode(['exito' => false, 'mensaje' => '❌ Usuario no encontrado']);
        }
    } catch (Throwable $e) {
        echo json_encode(['exito' => false, 'mensaje' => 'Error en el servidor']);
    }
    exit;
}

// ===================== INICIO DE SESIÓN =====================
$usuario    = trim($_POST['usuario'] ?? '');
$contrasena = trim($_POST['password'] ?? '');

if (empty($usuario) || empty($contrasena)) {
    echo json_encode(['exito' => false, 'mensaje' => '❌ Usuario o contraseña vacíos']);
    exit;
}

try {
    $pdo  = obtenerConexion();
    $stmt = $pdo->prepare(
        "SELECT ID, username, rol, password, intentos_fallidos, bloqueado_hasta
         FROM users WHERE username = ?"
    );
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    // Usuario inexistente: respuesta genérica para no revelar cuentas.
    if (!$fila) {
        echo json_encode(['exito' => false, 'mensaje' => '❌ Usuario o contraseña incorrectos']);
        exit;
    }

    // ¿La cuenta está bloqueada temporalmente?
    if (!empty($fila['bloqueado_hasta']) && strtotime($fila['bloqueado_hasta']) > time()) {
        $restan = (int) ceil((strtotime($fila['bloqueado_hasta']) - time()) / 60);
        echo json_encode(['exito' => false, 'mensaje' => "🔒 Cuenta bloqueada. Vuelve a intentar en $restan min."]);
        exit;
    }

    if (password_verify($contrasena, $fila['password'])) {
        // ---- Credenciales correctas: se abre la sesión ----
        $pdo->prepare(
            "UPDATE users SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE ID = ?"
        )->execute([$fila['ID']]);

        session_regenerate_id(true); // evita fijación de sesión
        $_SESSION['usuario'] = $fila['username'];
        $_SESSION['rol']     = $fila['rol'];
        $_SESSION['id']      = $fila['ID'];

        registrarLog($pdo, 'users', 'LOGIN', $fila['ID'], "Inicio de sesión: {$fila['username']}");
        echo json_encode(['exito' => true, 'rol' => $fila['rol']]);
    } else {
        // ---- Credenciales incorrectas: se cuenta el intento ----
        $intentos = (int) $fila['intentos_fallidos'] + 1;

        if ($intentos >= MAX_INTENTOS) {
            $pdo->prepare(
                "UPDATE users SET intentos_fallidos = ?, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE ID = ?"
            )->execute([$intentos, MINUTOS_BLOQUEO, $fila['ID']]);
            registrarLog($pdo, 'users', 'BLOQUEO', $fila['ID'], "Cuenta bloqueada por intentos fallidos: {$fila['username']}");
            echo json_encode(['exito' => false, 'mensaje' => '🔒 Demasiados intentos. Cuenta bloqueada ' . MINUTOS_BLOQUEO . ' min.']);
        } else {
            $pdo->prepare("UPDATE users SET intentos_fallidos = ? WHERE ID = ?")
                ->execute([$intentos, $fila['ID']]);
            $restantes = MAX_INTENTOS - $intentos;
            echo json_encode(['exito' => false, 'mensaje' => "❌ Usuario o contraseña incorrectos. Intentos restantes: $restantes"]);
        }
    }
} catch (Throwable $e) {
    echo json_encode(['exito' => false, 'mensaje' => 'Error de conexión']);
}
