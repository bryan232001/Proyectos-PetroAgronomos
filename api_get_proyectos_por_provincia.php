<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

$response = ['ok' => false, 'provincias' => []];

try {
    $sql = "
        SELECT 
            cb.provincia, 
            COUNT(pa.id) as num_asignaciones, 
            SUM(pa.cantidad_proyectos) as total_proyectos
        FROM 
            proyecto_asignacion pa
        JOIN 
            catalogo_bloques cb ON pa.bloque = cb.bloque
        WHERE 
            cb.provincia IS NOT NULL AND cb.provincia <> ''
        GROUP BY 
            cb.provincia
        ORDER BY 
            cb.provincia;
    ";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['ok'] = true;
    $response['provincias'] = $data;

} catch (PDOException $e) {
    $response['ok'] = false;
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>
