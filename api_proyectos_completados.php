<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/includes/db.php';

try {
    $usuario_actual = $_SESSION['usuario'] ?? '';
    $rol_actual = $_SESSION['id_rol'] ?? 2;
    $es_admin = ($rol_actual == 1);
    
    // Construir consulta según permisos
    if ($es_admin) {
        // Administrador ve todos los proyectos completados
        $stmt = $pdo->query("
            SELECT 
                pa.id_asignacion,
                pa.comunidad_nombre,
                pa.bloque,
                pa.beneficiarios,
                p.nombre as proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre as tecnico_nombre,
                u_creador.nombre as creador_nombre,
                -- Usamos la fecha de la última entrega como fecha de completado
                MAX(pe.fecha_entrega) as fecha_completado,
                100 as porcentaje_entrega, -- Si está aquí, está 100% completado
                COUNT(pe.id_entrega) as total_productos,
                SUM(CASE WHEN pe.entregado = 1 THEN 1 ELSE 0 END) as productos_entregados
            FROM proyecto_asignacion pa
            JOIN proyectos p ON p.id = pa.id_proyecto
            -- Unimos con producto_entregas para poder contar los productos
            LEFT JOIN producto_entregas pe ON pe.id_asignacion = pa.id_asignacion
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            WHERE pa.id_asignacion IN (SELECT id_asignacion FROM producto_entregas)
            GROUP BY pa.id_asignacion, p.nombre, pa.comunidad_nombre, pa.bloque, pa.beneficiarios, p.creado_por, u_tecnico.nombre, u_creador.nombre
            -- La condición clave: solo mostrar si todos los productos están entregados
            HAVING total_productos = productos_entregados AND total_productos > 0
            ORDER BY fecha_completado DESC
        ");
    } else {
        // Técnicos solo ven proyectos de su bloque o que crearon
        $stmt = $pdo->prepare("
            SELECT 
                pa.id_asignacion,
                pa.comunidad_nombre,
                pa.bloque,
                pa.beneficiarios,
                p.nombre as proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre as tecnico_nombre,
                u_creador.nombre as creador_nombre,
                MAX(pe.fecha_entrega) as fecha_completado,
                100 as porcentaje_entrega,
                COUNT(pe.id_entrega) as total_productos,
                SUM(CASE WHEN pe.entregado = 1 THEN 1 ELSE 0 END) as productos_entregados
            FROM proyecto_asignacion pa
            JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN producto_entregas pe ON pe.id_asignacion = pa.id_asignacion
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            WHERE pa.id_asignacion IN (SELECT id_asignacion FROM producto_entregas)
            AND (p.creado_por = ? OR u_tecnico.usuario = ?)
            GROUP BY pa.id_asignacion, p.nombre, pa.comunidad_nombre, pa.bloque, pa.beneficiarios, p.creado_por, u_tecnico.nombre, u_creador.nombre
            HAVING total_productos = productos_entregados AND total_productos > 0
            ORDER BY fecha_completado DESC
        ");
        $stmt->execute([$usuario_actual, $usuario_actual]);
    }
    
    $proyectos_completados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas adicionales
    $total_completados = count($proyectos_completados);
    $total_beneficiarios = array_sum(array_column($proyectos_completados, 'beneficiarios'));
    $bloques_completados = count(array_unique(array_column($proyectos_completados, 'bloque')));
    
    echo json_encode([
        'ok' => true,
        'proyectos' => $proyectos_completados,
        'estadisticas' => [
            'total_completados' => $total_completados,
            'total_beneficiarios' => $total_beneficiarios,
            'bloques_completados' => $bloques_completados
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'msg' => $e->getMessage()
    ]);
}
?>