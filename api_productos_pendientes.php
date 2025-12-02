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
    
    // Construir consulta según permisos - TODOS los productos NO entregados (sin importar estado del proyecto)
    if ($es_admin) {
        // Administrador ve todos los productos pendientes de TODOS los proyectos
        $stmt = $pdo->query("
            SELECT 
                pe.id_entrega,
                pe.id_asignacion,
                pe.id_producto,
                pe.cantidad_asignada,
                pe.cantidad_entregada,
                pe.fecha_entrega,
                pe.entregado,
                pe.observaciones,
                pe.entregado_por,
                cp.descripcion as producto_nombre,
                cp.unidad,
                pa.comunidad_nombre,
                pa.bloque,
                pa.beneficiarios,
                pa.fecha_asignacion,
                p.nombre as proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre as tecnico_nombre,
                u_creador.nombre as creador_nombre,
                aer.fecha_completado,
                aer.completado as proyecto_completado,
                aer.porcentaje_entrega
            FROM producto_entregas pe
            JOIN catalogo_productos cp ON cp.id_producto = pe.id_producto
            JOIN proyecto_asignacion pa ON pa.id_asignacion = pe.id_asignacion
            JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pe.id_asignacion
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            WHERE pe.entregado = 0
            ORDER BY pa.fecha_asignacion DESC, cp.descripcion ASC
        ");
    } else {
        // Técnicos solo ven productos pendientes de proyectos de su bloque o que crearon
        $stmt = $pdo->prepare("
            SELECT 
                pe.id_entrega,
                pe.id_asignacion,
                pe.id_producto,
                pe.cantidad_asignada,
                pe.cantidad_entregada,
                pe.fecha_entrega,
                pe.entregado,
                pe.observaciones,
                pe.entregado_por,
                cp.descripcion as producto_nombre,
                cp.unidad,
                pa.comunidad_nombre,
                pa.bloque,
                pa.beneficiarios,
                pa.fecha_asignacion,
                p.nombre as proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre as tecnico_nombre,
                u_creador.nombre as creador_nombre,
                aer.fecha_completado,
                aer.completado as proyecto_completado,
                aer.porcentaje_entrega
            FROM producto_entregas pe
            JOIN catalogo_productos cp ON cp.id_producto = pe.id_producto
            JOIN proyecto_asignacion pa ON pa.id_asignacion = pe.id_asignacion
            JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pe.id_asignacion
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            WHERE pe.entregado = 0
            AND (p.creado_por = ? OR u_tecnico.usuario = ?)
            ORDER BY pa.fecha_asignacion DESC, cp.descripcion ASC
        ");
        $stmt->execute([$usuario_actual, $usuario_actual]);
    }
    
    $productos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas
    $total_pendientes = count($productos_pendientes);
    $bloques_pendientes = count(array_unique(array_column($productos_pendientes, 'bloque')));
    $comunidades_pendientes = count(array_unique(array_column($productos_pendientes, 'comunidad_nombre')));
    $proyectos_pendientes = count(array_unique(array_column($productos_pendientes, 'proyecto_nombre')));
    
    echo json_encode([
        'ok' => true,
        'productos' => $productos_pendientes,
        'estadisticas' => [
            'total_pendientes' => $total_pendientes,
            'bloques_pendientes' => $bloques_pendientes,
            'comunidades_pendientes' => $comunidades_pendientes,
            'proyectos_pendientes' => $proyectos_pendientes
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