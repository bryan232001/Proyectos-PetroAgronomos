<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

$zona = $_GET['zona'] ?? null;
$bloque = $_GET['bloque'] ?? null;

$response = ['ok' => false, 'provincias' => []];

if (empty($zona) && empty($bloque)) {
    $response['message'] = 'Debe proporcionar una zona o un bloque.';
    echo json_encode($response);
    exit;
}

try {
    if (!empty($zona)) {
        $sql = "SELECT DISTINCT provincia FROM catalogo_bloques WHERE zona = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$zona]);
    } else { // !empty($bloque)
        $sql = "SELECT provincia FROM catalogo_bloques WHERE bloque = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bloque]);
    }

    $provincias = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $response['ok'] = true;
    $response['provincias'] = $provincias;

} catch (PDOException $e) {
    $response['ok'] = false;
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>
