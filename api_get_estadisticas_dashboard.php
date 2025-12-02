<?php
// api_get_estadisticas_dashboard.php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

try {
    // 1. Proyectos por estado
    $estadosProyectos = $pdo->query("
        SELECT 
            pe.nombre as estado,
            pe.color,
            COUNT(p.id) as cantidad
        FROM proyecto_estados pe
        LEFT JOIN proyectos p ON pe.id_estado = p.id_estado
        GROUP BY pe.id_estado, pe.nombre, pe.color
        ORDER BY pe.orden
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Inversión por mes (últimos 12 meses)
    $inversionPorMes = $pdo->query("
        SELECT 
            DATE_FORMAT(aer.fecha_completado, '%Y-%m') as mes,
            SUM(pad.cantidad * cp.precio_unit) as total_inversion
        FROM proyecto_asignacion_detalle pad
        JOIN catalogo_productos cp ON cp.id_producto = pad.id_producto
        JOIN asignacion_entregas_resumen aer ON aer.id_asignacion = pad.id_asignacion
        WHERE aer.completado = 1 AND aer.fecha_completado IS NOT NULL
        GROUP BY mes
        ORDER BY mes ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Preparamos los datos para devolverlos como JSON
    $data = [
        'proyectosPorEstado' => $estadosProyectos,
        'inversionPorMes' => $inversionPorMes
    ];

    echo json_encode($data);

} catch (PDOException $e) {
    // En caso de error, devolvemos un error 500
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener las estadísticas: ' . $e->getMessage()]);
}
