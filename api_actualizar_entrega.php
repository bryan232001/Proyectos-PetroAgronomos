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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }
    
    $id_asignacion = (int)($input['id_asignacion'] ?? 0);
    $id_producto = (int)($input['id_producto'] ?? 0);
    $entregado = isset($input['entregado']) ? (bool)$input['entregado'] : null;
    $observaciones = trim($input['observaciones'] ?? '');
    $usuario_actual = $_SESSION['usuario'];

    if (!$id_asignacion || !$id_producto) {
        throw new Exception('ID de asignación y producto requeridos');
    }
    
    // --- INICIO Bloque de Permisos ---
    $rol_actual = $_SESSION['id_rol'] ?? 2;
    if (($rol_actual != 1)) { // Si no es admin, verificar permisos
        $stmt_perm = $pdo->prepare("
            SELECT p.creado_por, u.usuario as tecnico_usuario
            FROM proyecto_asignacion pa
            JOIN proyectos p ON p.id = pa.id_proyecto
            LEFT JOIN usuarios u ON u.id_usuario = pa.id_tecnico
            WHERE pa.id_asignacion = ?
        ");
        $stmt_perm->execute([$id_asignacion]);
        $permisos = $stmt_perm->fetch(PDO::FETCH_ASSOC);
        if (!$permisos || ($permisos['creado_por'] !== $usuario_actual && $permisos['tecnico_usuario'] !== $usuario_actual)) {
            throw new Exception('No tienes permisos para modificar esta asignación');
        }
    }
    // --- FIN Bloque de Permisos ---
    
    $pdo->beginTransaction();
    
    // Verificar si ya existe una entrada para este producto en esta asignación
    $stmt_check = $pdo->prepare("SELECT id_entrega FROM producto_entregas WHERE id_asignacion = ? AND id_producto = ?");
    $stmt_check->execute([$id_asignacion, $id_producto]);
    $id_entrega_existente = $stmt_check->fetchColumn();

    if ($entregado !== null) { // Caso: se está marcando/desmarcando el checkbox "entregado"
        $fecha_entrega = $entregado ? date('Y-m-d H:i:s') : null;

        if ($id_entrega_existente) {
            // La entrada ya existe, así que la ACTUALIZAMOS
            $stmt_update = $pdo->prepare("
                UPDATE producto_entregas 
                SET entregado = ?, fecha_entrega = ?, observaciones = ?, entregado_por = ?
                WHERE id_entrega = ?
            ");
            $stmt_update->execute([$entregado ? 1 : 0, $fecha_entrega, $observaciones, $usuario_actual, $id_entrega_existente]);
        } else {
            // La entrada no existe, así que la INSERTAMOS
            $stmt_qty = $pdo->prepare("
                SELECT cantidad FROM proyecto_asignacion_detalle
                WHERE id_asignacion = ? AND id_producto = ?
            ");
            $stmt_qty->execute([$id_asignacion, $id_producto]);
            $cantidad_asignada = $stmt_qty->fetchColumn();

            if ($cantidad_asignada === false) {
                throw new Exception("El producto no pertenece al proyecto de esta asignación.");
            }

            $stmt_insert = $pdo->prepare("
                INSERT INTO producto_entregas
                    (id_asignacion, id_producto, cantidad_asignada, entregado, fecha_entrega, observaciones, entregado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->execute([$id_asignacion, $id_producto, $cantidad_asignada, $entregado ? 1 : 0, $fecha_entrega, $observaciones, $usuario_actual]);
        }
    } else { // Caso: solo se están actualizando las observaciones
        if ($id_entrega_existente) {
            $stmt_obs = $pdo->prepare("UPDATE producto_entregas SET observaciones = ? WHERE id_entrega = ?");
            $stmt_obs->execute([$observaciones, $id_entrega_existente]);
        } 
    }

    // Recalcular el resumen de la asignación
    $stmt_resumen = $pdo->prepare("
        SELECT 
            COUNT(*) as total_productos,
            SUM(CASE WHEN entregado = 1 THEN 1 ELSE 0 END) as productos_entregados
        FROM producto_entregas 
        WHERE id_asignacion = ?
    ");
    $stmt_resumen->execute([$id_asignacion]);
    $resumen = $stmt_resumen->fetch(PDO::FETCH_ASSOC);
    
    $total = $resumen['total_productos'] ?? 0;
    $entregados = $resumen['productos_entregados'] ?? 0;
    $porcentaje = $total > 0 ? ($entregados / $total) * 100 : 0;
    $completado = ($total > 0 && $entregados == $total) ? 1 : 0;
    $fecha_completado = $completado ? date('Y-m-d H:i:s') : null;
    
    // Actualizar o insertar en la tabla de resumen
    $stmt_upsert_resumen = $pdo->prepare("
        INSERT INTO asignacion_entregas_resumen (id_asignacion, total_productos, productos_entregados, porcentaje_entrega, completado, fecha_completado)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_productos = VALUES(total_productos),
            productos_entregados = VALUES(productos_entregados),
            porcentaje_entrega = VALUES(porcentaje_entrega),
            completado = VALUES(completado),
            fecha_completado = VALUES(fecha_completado)
    ");
    $stmt_upsert_resumen->execute([$id_asignacion, $total, $entregados, $porcentaje, $completado, $fecha_completado]);

    if ($completado) {
        $stmt_estado = $pdo->prepare("UPDATE proyecto_asignacion SET estado_asignacion = 'ENTREGADO', fecha_estado = NOW() WHERE id_asignacion = ?");
        $stmt_estado->execute([$id_asignacion]);

        // Verifico si todas las asignaciones del proyecto están completadas
        $stmt_get_proyecto = $pdo->prepare("SELECT id_proyecto FROM proyecto_asignacion WHERE id_asignacion = ?");
        $stmt_get_proyecto->execute([$id_asignacion]);
        $id_proyecto = $stmt_get_proyecto->fetchColumn();

        if ($id_proyecto) {
            $stmt_check_all_completed = $pdo->prepare("
                SELECT COUNT(*) 
                FROM proyecto_asignacion 
                WHERE id_proyecto = ? AND estado_asignacion != 'ENTREGADO'
            ");
            $stmt_check_all_completed->execute([$id_proyecto]);
            $asignaciones_pendientes = $stmt_check_all_completed->fetchColumn();

            if ($asignaciones_pendientes == 0) {
                $id_estado_completado = 4; // ID para 'Completado'
                $stmt_update_proyecto = $pdo->prepare("UPDATE proyectos SET id_estado = ? WHERE id = ?");
                $stmt_update_proyecto->execute([$id_estado_completado, $id_proyecto]);
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'ok' => true,
        'msg' => 'Entrega actualizada correctamente',
        'porcentaje' => round($porcentaje, 2),
        'completado' => $completado,
        'productos_entregados' => (int)$entregados,
        'total_productos' => (int)$total
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'msg' => $e->getMessage()
    ]);
}
?>
