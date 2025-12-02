<?php
session_start();
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

$id_asignacion = (int)($_GET['id'] ?? 0);
if ($id_asignacion <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID de asignación inválido']);
    exit;
}

try {
    // Paso 1: Contar cuántos detalles existen para esta asignación en la tabla de detalles.
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM proyecto_asignacion_detalle WHERE id_asignacion = ?");
    $stmt_check->execute([$id_asignacion]);
    $count_detalles = $stmt_check->fetchColumn();

    $detalles = [];
    $fuente = 'ninguna'; // Fuente por defecto

    if ($count_detalles > 0) {
        // Si hay detalles, intentamos obtenerlos con el nombre del producto.
        $stmt_join = $pdo->prepare("
            SELECT pad.cantidad, cp.descripcion, cp.unidad
            FROM proyecto_asignacion_detalle pad
            LEFT JOIN catalogo_productos cp ON cp.id_producto = pad.id_producto
            WHERE pad.id_asignacion = ?
            ORDER BY cp.descripcion
        ");
        $stmt_join->execute([$id_asignacion]);
        $detalles = $stmt_join->fetchAll(PDO::FETCH_ASSOC);
        $fuente = 'asignacion';

        // Caso de INCONSISTENCIA: Hay registros en 'detalle' pero el JOIN no trae nada
        // (p.ej. se borró un producto del catálogo pero no de las asignaciones).
        if (empty($detalles)) {
            $stmt_raw = $pdo->prepare("SELECT id_producto, cantidad FROM proyecto_asignacion_detalle WHERE id_asignacion = ?");
            $stmt_raw->execute([$id_asignacion]);
            $raw_details = $stmt_raw->fetchAll(PDO::FETCH_ASSOC);
            foreach ($raw_details as $raw_item) {
                $detalles[] = [
                    'cantidad' => $raw_item['cantidad'],
                    'descripcion' => '[ID ' . $raw_item['id_producto'] . '] PRODUCTO NO ENCONTRADO EN CATÁLOGO',
                    'unidad' => 'N/A'
                ];
            }
            $fuente = 'asignacion_roto'; // Fuente especial para indicar problema
        }
    } else {
        // Si no hay detalles específicos, buscamos en la plantilla del proyecto como fallback.
        $stmt_fallback = $pdo->prepare("
            SELECT pi.cantidad, pi.descripcion, pi.unidad FROM proyecto_items pi
            WHERE pi.proyecto_id = (SELECT id_proyecto FROM proyecto_asignacion WHERE id_asignacion = ? LIMIT 1)
            ORDER BY pi.descripcion
        ");
        $stmt_fallback->execute([$id_asignacion]);
        $detalles = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($detalles)) { $fuente = 'plantilla'; }
    }

    echo json_encode(['ok' => true, 'detalles' => $detalles, 'fuente' => $fuente]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error en la base de datos: ' . $e->getMessage()]);
}
