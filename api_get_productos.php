<?php
session_start();
if (!isset($_SESSION['usuario'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php'; // $pdo

$id_proyecto = (int)($_GET['id_proyecto'] ?? 0);
if ($id_proyecto<=0){ echo json_encode(['ok'=>false,'msg'=>'Proyecto inválido']); exit; }

$sql = "
  SELECT
    pi.producto_id AS id_producto,
    cp.descripcion AS nombre,
    cp.unidad      AS unidad,
    pi.cantidad    AS cantidad_default
  FROM proyecto_items pi
  JOIN catalogo_productos cp ON cp.id_producto = pi.producto_id
  WHERE pi.proyecto_id = ?
  ORDER BY cp.descripcion
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_proyecto]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($productos as &$p){
  $p['id_producto'] = (int)$p['id_producto'];
  $p['cantidad_default'] = is_null($p['cantidad_default']) ? 0 : (float)$p['cantidad_default'];
}
echo json_encode(['ok'=>true, 'productos'=>$productos], JSON_UNESCAPED_UNICODE);
