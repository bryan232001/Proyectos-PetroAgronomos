<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['id_rol'] != 1) {
    header('HTTP/1.1 403 Forbidden');
    echo "Acceso denegado. Solo los administradores pueden ejecutar este script.";
    exit;
}

require_once __DIR__ . '/includes/db.php';

try {
    $pdo->beginTransaction();

    // Encontrar y eliminar registros huérfanos en producto_entregas
    $sql_delete_entregas = "
        DELETE pe FROM producto_entregas pe
        LEFT JOIN proyecto_asignacion pa ON pe.id_asignacion = pa.id_asignacion
        WHERE pa.id_asignacion IS NULL
    ";
    $stmt_entregas = $pdo->prepare($sql_delete_entregas);
    $stmt_entregas->execute();
    $deleted_entregas_count = $stmt_entregas->rowCount();

    // Encontrar y eliminar registros huérfanos en asignacion_entregas_resumen
    $sql_delete_resumen = "
        DELETE aer FROM asignacion_entregas_resumen aer
        LEFT JOIN proyecto_asignacion pa ON aer.id_asignacion = pa.id_asignacion
        WHERE pa.id_asignacion IS NULL
    ";
    $stmt_resumen = $pdo->prepare($sql_delete_resumen);
    $stmt_resumen->execute();
    $deleted_resumen_count = $stmt_resumen->rowCount();

    $pdo->commit();

    echo "Limpieza completada.<br>";
    echo "Registros eliminados de 'producto_entregas': " . $deleted_entregas_count . "<br>";
    echo "Registros eliminados de 'asignacion_entregas_resumen': " . $deleted_resumen_count . "<br>";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error durante la limpieza: " . $e->getMessage();
}
