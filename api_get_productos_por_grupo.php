<?php
require_once __DIR__ . '/includes/db.php';

$grupo_sel = isset($_GET['g']) ? trim($_GET['g']) : '';

if ($grupo_sel !== '') {
  $stmt = $pdo->prepare("
    SELECT id_producto AS id, descripcion, unidad, precio_unit, grupo
    FROM catalogo_productos
    WHERE grupo = ?
    ORDER BY descripcion
  ");
  $stmt->execute([$grupo_sel]);
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $productos = $pdo->query("
    SELECT id_producto AS id, descripcion, unidad, precio_unit, grupo
    FROM catalogo_productos
    ORDER BY grupo, descripcion
  ")->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode(['productos' => $productos]);
