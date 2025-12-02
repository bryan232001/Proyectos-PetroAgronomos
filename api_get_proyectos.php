<?php
session_start();
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php'; // $pdo

$q = trim($_GET['q'] ?? '');
$usuario_actual = $_SESSION['usuario'];
$es_admin = isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 1;

// Construir la consulta base - TODOS pueden ver TODOS los proyectos
$base_query = "SELECT id AS id_proyecto, nombre, creado_por FROM proyectos";
$where_conditions = [];
$params = [];

// Filtro por búsqueda
if ($q !== '') {
    $where_conditions[] = "nombre LIKE ?";
    $params[] = '%'.$q.'%';
}

// Construir consulta final
$sql = $base_query;
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}
$sql .= " ORDER BY nombre, id_proyecto ASC"; // Ordenar por nombre y luego por ID

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar cuántas veces aparece cada nombre de proyecto
$name_counts = array_count_values(array_column($proyectos, 'nombre'));

// Rastrear el contador para cada nombre mientras se itera
$name_counters = [];

// Agregar información sobre si el usuario puede asignar y diferenciar nombres duplicados
foreach ($proyectos as &$proyecto) {
    $nombre = $proyecto['nombre'];

    // Si el nombre está duplicado, agregar un contador
    if (isset($name_counts[$nombre]) && $name_counts[$nombre] > 1) {
        if (!isset($name_counters[$nombre])) {
            $name_counters[$nombre] = 1;
        }
        
        // Anexar el contador al nombre
        $proyecto['nombre'] = $nombre . ' (' . $name_counters[$nombre] . ')';
        
        $name_counters[$nombre]++;
    }

    $proyecto['puede_asignar'] = $es_admin || $proyecto['creado_por'] === $usuario_actual;
}
unset($proyecto); // Romper la referencia del bucle

echo json_encode(['ok'=>true,'proyectos'=>$proyectos], JSON_UNESCAPED_UNICODE);
