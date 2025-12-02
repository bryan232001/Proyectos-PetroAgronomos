<?php
// Agregar ítem al carrito (sin restricción de grupo)
session_start();
header('Content-Type: application/json');

$response = ['ok' => false, 'message' => 'Error desconocido.'];

if (!isset($_SESSION['usuario'])) {
    $response['message'] = 'No ha iniciado sesión.';
    echo json_encode($response);
    exit;
}

require_once __DIR__ . '/includes/db.php';

$proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
$producto_id = (int)($_POST['producto_id'] ?? 0);
$cantidad    = (float)($_POST['cantidad'] ?? 0);

if ($proyecto_id > 0) {
    $stmt = $pdo->prepare("SELECT creado_por FROM proyectos WHERE id = ?");
    $stmt->execute([$proyecto_id]);
    $creador = $stmt->fetchColumn();

    if ($creador !== $_SESSION['usuario']) {
        $response['message'] = 'No tiene permisos para modificar este proyecto.';
        echo json_encode($response);
        exit;
    }
}

if ($proyecto_id <= 0 || $producto_id <= 0 || $cantidad <= 0) {
    $response['message'] = 'Datos inválidos.';
    echo json_encode($response);
    exit;
}

/* 1) Leer producto desde el catálogo */
$stmt = $pdo->prepare("
  SELECT id_producto, descripcion, unidad, precio_unit, grupo
  FROM catalogo_productos
  WHERE id_producto = ?
  LIMIT 1
");
$stmt->execute([$producto_id]);
$prod = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prod) {
    $response['message'] = 'Producto no encontrado en el catálogo.';
    echo json_encode($response);
    exit;
}

/* 2) Carrito en sesión */
$cartKey = "cart_$proyecto_id";
if (!isset($_SESSION[$cartKey])) $_SESSION[$cartKey] = [];

/* 3) Si ya existe, sumar cantidad */
$found = false;
$item_added = [];
foreach($_SESSION[$cartKey] as &$it){
  if ($it['id'] == (int)$prod['id_producto']) {
    $it['cantidad'] += $cantidad;
    $found = true;
    $item_added = $it;
    break;
  }
}
unset($it);

if (!$found) {
  $new_item = [
    'id'       => (int)$prod['id_producto'],
    'nombre'   => '['.$prod['grupo'].'] '.$prod['descripcion'],
    'unidad'   => $prod['unidad'],
    'precio'   => (float)$prod['precio_unit'],
    'cantidad' => (float)$cantidad
  ];
  $_SESSION[$cartKey][] = $new_item;
  $item_added = $new_item;
}

$response['ok'] = true;
$response['message'] = 'Producto agregado correctamente.';
$response['item'] = $item_added;

echo json_encode($response);