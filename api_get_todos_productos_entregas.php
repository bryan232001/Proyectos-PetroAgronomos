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
        // Administrador ve todos los productos
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
                p.nombre as proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre as tecnico_nombre,
                u_creador.nombre as creador_nombre
            FROM producto_entregas pe
            JOIN catalogo_productos cp ON cp.id_producto = pe.id_producto
            JOIN proyecto_asignacion pa ON pa.id_asignacion = pe.id_asignacion
            JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            ORDER BY pe.entregado ASC, cp.descripcion ASC, pa.comunidad_nombre ASC
        ");
    } else {
        // Usuarios normales solo ven productos de sus proyectos o asignaciones
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
                p.nombre as proyecto_nombre,
                p.creado_por,
                u_tecnico.nombre as tecnico_nombre,
                u_creador.nombre as creador_nombre
            FROM producto_entregas pe
            JOIN catalogo_productos cp ON cp.id_producto = pe.id_producto
            JOIN proyecto_asignacion pa ON pa.id_asignacion = pe.id_asignacion
            JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u_tecnico ON u_tecnico.id_usuario = pa.id_tecnico
            LEFT JOIN usuarios u_creador ON u_creador.usuario = p.creado_por
            WHERE p.creado_por = ? OR u_tecnico.usuario = ?
            ORDER BY pe.entregado ASC, cp.descripcion ASC, pa.comunidad_nombre ASC
        ");
        $stmt->execute([$usuario_actual, $usuario_actual]);
    }
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas
    $total_productos = count($productos);
    $productos_entregados = count(array_filter($productos, function($p) { return $p['entregado'] == 1; }));
    $productos_no_entregados = $total_productos - $productos_entregados;
    $porcentaje_entrega = $total_productos > 0 ? ($productos_entregados / $total_productos) * 100 : 0;
    
    echo json_encode([
        'ok' => true,
        'productos' => $productos,
        'estadisticas' => [
            'total_productos' => $total_productos,
            'productos_entregados' => $productos_entregados,
            'productos_no_entregados' => $productos_no_entregados,
            'porcentaje_entrega' => $porcentaje_entrega
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