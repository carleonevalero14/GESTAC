<?php
/**
 * Cierre de sesión de GESTAC.
 * Destruye la sesión y la cookie, y devuelve al usuario al login.
 */
require_once __DIR__ . '/../configuracion/sesion.php';

// Vacía las variables de sesión.
$_SESSION = [];

// Elimina la cookie de sesión del navegador.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destruye la sesión en el servidor.
session_destroy();

header('Location: ../HTML/login.php');
exit;
