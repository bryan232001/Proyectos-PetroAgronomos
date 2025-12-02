<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_asignacion = (int)($data['id_asignacion'] ?? 0);
$id_producto = (int)($data['id_producto'] ?? 0);

if ($id_asignacion <= 0 || $id_producto <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Datos inválidos.']);
    exit;
}

// Verificación de permisos
$stmt = $pdo->prepare("
    SELECT p.creado_por, pa.id_tecnico
    FROM proyecto_asignacion pa
    JOIN proyectos p ON pa.id_proyecto = p.id
    WHERE pa.id_asignacion = ?
");
$stmt->execute([$id_asignacion]);
$permisos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$permisos) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Asignación no encontrada.']);
    exit;
}

$id_usuario_actual = (int)($_SESSION['id_usuario'] ?? 0);
$es_admin = ($_SESSION['id_rol'] == 1);
$es_creador = ($permisos['creado_por'] === $_SESSION['usuario']);
$es_tecnico_asignado = ($permisos['id_tecnico'] == $id_usuario_actual);

if (!$es_admin && !$es_creador && !$es_tecnico_asignado) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No tiene permisos para eliminar productos de esta asignación.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Eliminar de producto_entregas
    $stmt = $pdo->prepare("DELETE FROM producto_entregas WHERE id_asignacion = ? AND id_producto = ?");
    $stmt->execute([$id_asignacion, $id_producto]);

    // 2. Eliminar de proyecto_asignacion_detalle (si existe)
    $stmt = $pdo->prepare("DELETE FROM proyecto_asignacion_detalle WHERE id_asignacion = ? AND id_producto = ?");
    $stmt->execute([$id_asignacion, $id_producto]);

    // 3. Recalcular y actualizar el resumen de entregas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_productos,
            SUM(CASE WHEN entregado = 1 THEN 1 ELSE 0 END) as productos_entregados
        FROM producto_entregas 
        WHERE id_asignacion = ?
    ");
    $stmt->execute([$id_asignacion]);
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_productos = (int)$resumen['total_productos'];
    $productos_entregados = (int)$resumen['productos_entregados'];
    $porcentaje = $total_productos > 0 ? ($productos_entregados / $total_productos) * 100 : 0;
    $completado = ($total_productos > 0 && $total_productos === $productos_entregados);

    $stmt = $pdo->prepare("
        UPDATE asignacion_entregas_resumen 
        SET 
            total_productos = ?,
            productos_entregados = ?,
            porcentaje_entrega = ?,
            completado = ?,
            fecha_completado = CASE WHEN ? THEN NOW() ELSE NULL END
        WHERE id_asignacion = ?
    ");
    $stmt->execute([$total_productos, $productos_entregados, $porcentaje, $completado, $completado, $id_asignacion]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'msg' => 'Producto eliminado de la asignación.',
        'total_productos' => $total_productos,
        'productos_entregados' => $productos_entregados,
        'porcentaje' => $porcentaje,
        'completado' => $completado
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Error al eliminar producto de asignación: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor: ' . $e->getMessage()]);
}
