<?php
// api_get_estadisticas_dashboard.php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

try {
    // 1. Proyectos por estado de entrega
    $estadosProyectos = $pdo->query("
        SELECT
            estado,
            color,
            COUNT(id_asignacion) as cantidad
        FROM (
            SELECT
                pa.id_asignacion,
                CASE
                    WHEN aer.porcentaje_entrega = 100 THEN 'Completado'
                    WHEN aer.porcentaje_entrega > 0 AND aer.porcentaje_entrega < 100 THEN 'En Progreso'
                    ELSE 'No Iniciado'
                END as estado,
                CASE
                    WHEN aer.porcentaje_entrega = 100 THEN '#22c55e' -- verde
                    WHEN aer.porcentaje_entrega > 0 AND aer.porcentaje_entrega < 100 THEN '#f59e0b' -- ambar
                    ELSE '#ef4444' -- rojo
                END as color
            FROM
                proyecto_asignacion pa
            LEFT JOIN
                asignacion_entregas_resumen aer ON pa.id_asignacion = aer.id_asignacion
        ) as subquery
        GROUP BY
            estado, color
        ORDER BY
            CASE estado
                WHEN 'Completado' THEN 1
                WHEN 'En Progreso' THEN 2
                ELSE 3
            END
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
