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
    $id_asignacion = (int)($_GET['id_asignacion'] ?? 0);
    
    if (!$id_asignacion) {
        throw new Exception('ID de asignación requerido');
    }
    
    // Verificar permisos
    $usuario_actual = $_SESSION['usuario'];
    $rol_actual = $_SESSION['id_rol'] ?? 2;
    $es_admin = ($rol_actual == 1);
    
    if (!$es_admin) {
        // Verificar que el usuario puede ver esta asignación
        $stmt = $pdo->prepare("
            SELECT p.creado_por, pa.id_tecnico, u.usuario as tecnico_usuario
            FROM proyecto_asignacion pa
            JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u ON u.id_usuario = pa.id_tecnico
            WHERE pa.id_asignacion = ?
        ");
        $stmt->execute([$id_asignacion]);
        $asignacion_permiso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$asignacion_permiso) {
            throw new Exception('Asignación no encontrada');
        }
        
        $puede_ver = ($asignacion_permiso['creado_por'] === $usuario_actual) || 
                     ($asignacion_permiso['tecnico_usuario'] === $usuario_actual);
        
        if (!$puede_ver) {
            throw new Exception('No tienes permisos para ver esta asignación');
        }
    }
    
    // Obtener información de la asignación
    $stmt = $pdo->prepare("
        SELECT 
            pa.id_asignacion,
            pa.id_proyecto,
            pa.comunidad_nombre,
            pa.bloque,
            pa.beneficiarios,
            p.nombre as proyecto_nombre,
            p.creado_por,
            u.nombre as tecnico_nombre,
            aer.total_productos,
            aer.productos_entregados,
            aer.porcentaje_entrega,
            aer.completado,
            aer.fecha_completado
        FROM proyecto_asignacion pa
        LEFT JOIN proyectos p ON p.id = pa.id_proyecto
        LEFT JOIN usuarios u ON u.id_usuario = pa.id_tecnico
        LEFT JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pa.id_asignacion
        WHERE pa.id_asignacion = ?
    ");
    $stmt->execute([$id_asignacion]);
    $info_asignacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$info_asignacion) {
        throw new Exception('Asignación no encontrada');
    }
    
    $id_proyecto = $info_asignacion['id_proyecto'];
    
    // Obtener todos los productos del proyecto y hacer LEFT JOIN con su estado de entrega
    $stmt = $pdo->prepare("
        SELECT 
            pi.producto_id AS id_producto,
            cp.descripcion AS producto_nombre,
            cp.unidad,
            COALESCE(pe.cantidad_asignada, pi.cantidad) AS cantidad_asignada,
            pe.id_entrega,
            pe.cantidad_entregada,
            pe.entregado,
            pe.fecha_entrega,
            pe.observaciones,
            u_entrega.nombre as entregado_por
        FROM proyecto_items pi
        JOIN catalogo_productos cp ON cp.id_producto = pi.producto_id
        LEFT JOIN producto_entregas pe ON pe.id_producto = pi.producto_id AND pe.id_asignacion = :id_asignacion
        LEFT JOIN usuarios u_entrega ON u_entrega.usuario COLLATE utf8mb4_unicode_ci = pe.entregado_por
        WHERE pi.proyecto_id = :id_proyecto
        ORDER BY cp.descripcion
    ");
    $stmt->execute([
        ':id_asignacion' => $id_asignacion,
        ':id_proyecto' => $id_proyecto
    ]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'asignacion' => $info_asignacion,
        'productos' => $productos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'msg' => $e->getMessage()
    ]);
}
?>