<?php
require_once __DIR__ . '/../configuracion/sesion.php';
requerirSesionApi('admin');
require_once __DIR__ . '/../configuracion/base_datos.php';

header('Content-Type: application/json');

$pdo = obtenerConexion();

// KPIs principales
$total_activos     = (int)$pdo->query("SELECT COUNT(*) FROM activos")->fetchColumn();
$total_asignados   = (int)$pdo->query("SELECT COUNT(DISTINCT activo) FROM usuarios WHERE activo IS NOT NULL AND activo != ''")->fetchColumn();
$total_disponibles = $total_activos - $total_asignados;
$pct_uso           = $total_activos > 0 ? round(($total_asignados / $total_activos) * 100, 1) : 0;

$total_usuarios       = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$usuarios_con_equipo  = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo IS NOT NULL AND activo != ''")->fetchColumn();
$usuarios_sin_equipo  = $total_usuarios - $usuarios_con_equipo;

$total_perifericos = (int)$pdo->query("SELECT COUNT(*) FROM inventario_perifericos")->fetchColumn();

// Activos por categoría
$por_categoria = $pdo->query(
    "SELECT categoria, COUNT(*) AS total FROM activos GROUP BY categoria ORDER BY total DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Top 5 ubicaciones con más asignaciones
$por_ubicacion = $pdo->query(
    "SELECT ubicacion, COUNT(*) AS total FROM usuarios
     WHERE activo IS NOT NULL AND activo != ''
     GROUP BY ubicacion ORDER BY total DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

// Asignaciones por mes (últimos 6 meses, fecha guardada como YYYY-MM-DD)
$por_mes = $pdo->query(
    "SELECT DATE_FORMAT(STR_TO_DATE(fecha, '%Y-%m-%d'), '%b %Y') AS mes,
            DATE_FORMAT(STR_TO_DATE(fecha, '%Y-%m-%d'), '%Y-%m') AS orden,
            COUNT(*) AS total
     FROM usuarios
     WHERE fecha IS NOT NULL AND fecha != ''
       AND STR_TO_DATE(fecha, '%Y-%m-%d') IS NOT NULL
     GROUP BY mes, orden
     ORDER BY orden DESC
     LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'exito' => true,
    'kpis'  => [
        'total_activos'      => $total_activos,
        'total_asignados'    => $total_asignados,
        'total_disponibles'  => $total_disponibles,
        'pct_uso'            => $pct_uso,
        'total_usuarios'     => $total_usuarios,
        'usuarios_con_equipo'=> $usuarios_con_equipo,
        'usuarios_sin_equipo'=> $usuarios_sin_equipo,
        'total_perifericos'  => $total_perifericos,
    ],
    'por_categoria' => $por_categoria,
    'por_ubicacion' => $por_ubicacion,
    'por_mes'       => array_reverse($por_mes),
]);
