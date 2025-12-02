<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['id_rol'] != 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No tiene permisos de administrador para realizar esta acción.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_asignacion = (int)($data['id_asignacion'] ?? 0);

if ($id_asignacion <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'ID de asignación inválido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Eliminar los productos entregados asociados a la asignación
    $stmt = $pdo->prepare("DELETE FROM producto_entregas WHERE id_asignacion = ?");
    $stmt->execute([$id_asignacion]);

    // 2. Eliminar el detalle de la asignación
    $stmt = $pdo->prepare("DELETE FROM proyecto_asignacion_detalle WHERE id_asignacion = ?");
    $stmt->execute([$id_asignacion]);
    
    // 3. Eliminar el resumen de la asignación
    $stmt = $pdo->prepare("DELETE FROM asignacion_entregas_resumen WHERE id_asignacion = ?");
    $stmt->execute([$id_asignacion]);

    // 4. Eliminar la asignación principal
    $stmt = $pdo->prepare("DELETE FROM proyecto_asignacion WHERE id_asignacion = ?");
    $stmt->execute([$id_asignacion]);
    $rowCount = $stmt->rowCount();

    if ($rowCount > 0) {
        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => 'Asignación eliminada correctamente.']);
    } else {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'La asignación no fue encontrada o ya fue eliminada.']);
    }

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Error al eliminar asignación: " . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Error del servidor: ' . $e->getMessage()]);
}